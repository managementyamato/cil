<?php
/**
 * AI見積アシスタント API
 *
 * 自然言語の指示文を受け取り、商品マスタ・顧客マスタを参照して
 * 構造化された見積データ(件名/顧客/明細/メモ)を返す。
 *
 * POST application/json:
 *   request_text: string  自然言語の指示文
 *
 * 認証: sales 以上
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/sales-master.php';
require_once __DIR__ . '/google-gemini.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['POST'],
]);

if (!hasPermission('sales')) errorResponse('権限がありません', 403);

$input = getJsonInput();
$text  = trim((string)($input['request_text'] ?? ''));
if ($text === '') errorResponse('指示文を入力してください', 400);
if (mb_strlen($text) > 4000) errorResponse('指示文が長すぎます（4000文字以内）', 400);

$gemini = new GoogleGeminiClient();
if (!$gemini->isConfigured()) {
    errorResponse('Gemini API キーが未設定です（.env の GEMINI_API_KEY）', 500);
}

// マスター読込
$products   = getDemoProductMaster();
$customers  = getDemoCustomerMaster();
$rankMult   = getDemoRankMultipliers();
$today      = date('Y-m-d');
$expireDate = date('Y-m-d', strtotime('+30 days'));

// 本番価格表（同期済みなら使う）
$realPriceList = getPriceListAsPromptText(80);

// AIへの指示プロンプト
$productsJson  = json_encode($products,  JSON_UNESCAPED_UNICODE);
$customersJson = json_encode($customers, JSON_UNESCAPED_UNICODE);
$rankJson      = json_encode($rankMult,  JSON_UNESCAPED_UNICODE);

$priceListBlock = $realPriceList
    ? "【本番価格表（同期済み）】\n以下は実際の販売価格表・運搬費・設置調整費・顧客ランク定義などの一次資料です。\n"
      . "ユーザー指示にマッチする項目があればこちらを最優先で参照してください。\n"
      . "暫定の商品マスタ・ランク補正は本番価格表で読み取れない場合のフォールバックです。\n"
      . "回答（notesなど）の中ではこれらを単に「価格表」「運搬費表」等と表現し、"
      . "「シート」「スプレッドシート」という言葉は使わないでください。\n\n"
      . $realPriceList
    : "【本番価格表】未同期。暫定の商品マスタ・ランク補正のみで対応してください。";

$systemInstruction = <<<TXT
あなたは建設業界向けLEDビジョン・ディスプレイ販売会社の見積アシスタントです。
ユーザーから自然言語で見積の指示が入力されます。下記の参照データを使って、
JSONスキーマに従って構造化された見積データを返してください。

{$priceListBlock}

【暫定商品マスタ（本番価格表で見つからない場合のフォールバック）】
{$productsJson}

【暫定顧客マスタ（本番の顧客ランク定義がある場合はそちらを優先）】
{$customersJson}

【暫定ランク別単価補正（本番価格表に明示の値がある場合はそちらを優先）】
{$rankJson}

【ガイドライン】
1. 顧客名は曖昧マッチでよい（例「ニッケン」→「ニッケン(株)」）。
   本番価格表に顧客ランク定義があればそこからランク（A層/B層/C層/D層 等）を判定する。
   マッチしない場合は customer は入力値そのまま、customer_rank は "" を返す。
2. 商品マッチング: ユーザー記述から商品名・型番・インチサイズを抽出し、本番価格表の該当行を探す。
   見つかったら name に商品名（インチ・型番込み）、price にその単価を入れる。
   暫定マスタにマッチした場合は product_id も埋める（monitarou / monisuke / monimaru）。
3. ランク補正: 本番価格表に「A層10%OFF」等の記述があればそれに従う。なければ暫定補正値。
4. 数量はユーザー記述から抽出（例「3台」→ 3）。省略されたら 1。
5. 設置工事費・運搬費は別行で立てる:
   - 運搬費: 本番の運搬費表を参照（地域別・インチ別）。地域が不明なら関東基準で算出してnotesに明記。
   - 設置調整費: 本番の設置調整費表を参照。読めない場合は概算（LEDビジョン50k/台 など）。
6. 件名は「{顧客名} {主商品名}一式」など簡潔に。顧客名不明なら主商品のみで命名。
7. 不明確だった点・想定した値・参照した区分名は notes に短くまとめる。
   「シート」「スプレッドシート」という言葉は使わない。
8. 本番価格表で扱えない品名は type="other" の自由行として記載。
TXT;

$prompt = "【見積指示】\n" . $text;

$schema = [
    'type' => 'object',
    'properties' => [
        'subject'       => ['type' => 'string'],
        'customer'      => ['type' => 'string'],
        'customer_rank' => ['type' => 'string'],
        'issue_date'    => ['type' => 'string'],
        'expire_date'   => ['type' => 'string'],
        'items' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'type'       => ['type' => 'string'],  // product / install / shipping / other
                    'name'       => ['type' => 'string'],
                    'product_id' => ['type' => 'string'],
                    'qty'        => ['type' => 'number'],
                    'price'      => ['type' => 'number'],
                ],
                'required' => ['type','name','qty','price'],
            ],
        ],
        'notes' => ['type' => 'string'],
    ],
    'required' => ['subject', 'items'],
];

try {
    $resp = $gemini->generateContent(
        [ ['text' => $prompt] ],
        [
            'system_instruction' => $systemInstruction,
            'generation_config' => [
                'response_mime_type' => 'application/json',
                'response_schema'    => $schema,
                'temperature'        => 0.2,
            ],
        ]
    );
} catch (Exception $e) {
    error_log('[quote-ai] Gemini call failed: ' . $e->getMessage());
    errorResponse('AI見積生成に失敗しました: ' . $e->getMessage(), 502);
}

$txt = $gemini->extractText($resp);
$parsed = json_decode($txt, true);
if (!is_array($parsed)) {
    error_log('[quote-ai] Failed to parse JSON: ' . substr($txt, 0, 500));
    errorResponse('AIレスポンスの解析に失敗しました', 502);
}

// サニタイズ＆既定値
$quote = [
    'subject'       => (string)($parsed['subject']       ?? ''),
    'customer'      => (string)($parsed['customer']      ?? ''),
    'customer_rank' => (string)($parsed['customer_rank'] ?? ''),
    'issue_date'    => (string)($parsed['issue_date']    ?? $today),
    'expire_date'   => (string)($parsed['expire_date']   ?? $expireDate),
    'items'         => [],
    'notes'         => (string)($parsed['notes']         ?? ''),
];
$allowedTypes = ['product','install','shipping','other'];
foreach (($parsed['items'] ?? []) as $it) {
    if (!is_array($it)) continue;
    $type = in_array(($it['type'] ?? 'other'), $allowedTypes, true) ? $it['type'] : 'other';
    $quote['items'][] = [
        'type'       => $type,
        'name'       => (string)($it['name'] ?? ''),
        'product_id' => (string)($it['product_id'] ?? ''),
        'qty'        => (float)($it['qty'] ?? 1),
        'price'      => (float)($it['price'] ?? 0),
    ];
}

successResponse([
    'quote' => $quote,
    'model' => $gemini->getModel(),
], '見積案を生成しました');
