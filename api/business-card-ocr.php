<?php
/**
 * 名刺OCR API
 *
 * 受信した名刺画像を Gemini API に送り、構造化フィールドを抽出して返す。
 * 画像はこの段階では保存しない（リード保存時に leads-api 側で確定保存）。
 *
 * POST multipart/form-data:
 *   image: file (jpg/png/webp/heic)
 *
 * 認証: 全営業ユーザー可（sales 以上）
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/google-gemini.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['POST'],
]);

// ---- 入力検証 ----
if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['image']['error'] ?? -1;
    errorResponse('画像アップロードエラー (code: ' . $errCode . ')', 400);
}

$file  = $_FILES['image'];
$tmp   = $file['tmp_name'];
$size  = (int) $file['size'];

if ($size <= 0)                  errorResponse('画像ファイルが空です', 400);
if ($size > 10 * 1024 * 1024)    errorResponse('画像サイズは10MB以内にしてください', 400);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($tmp);
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/heic' => 'heic',
    'image/heif' => 'heif',
];
if (!isset($allowed[$mime])) {
    errorResponse('対応形式は JPEG / PNG / WebP / HEIC です（検出: ' . htmlspecialchars($mime) . '）', 400);
}

// ---- Gemini クライアント ----
$gemini = new GoogleGeminiClient();
if (!$gemini->isConfigured()) {
    errorResponse('Gemini API キーが未設定です（.env の GEMINI_API_KEY）', 500);
}

// ---- 画像を Base64 化して送信 ----
$binary = @file_get_contents($tmp);
if ($binary === false) {
    errorResponse('画像ファイルの読み込みに失敗しました', 500);
}
$base64 = base64_encode($binary);

$prompt = <<<TXT
あなたは名刺OCRアシスタントです。アップロードされた名刺画像から下記のフィールドを抽出し、
指定された JSON スキーマで返してください。読み取れない / 該当なしのフィールドは空文字 "" を返してください。
姓名は半角スペース区切り、電話番号はハイフン付きで返してください。
TXT;

$schema = [
    'type' => 'object',
    'properties' => [
        'company_name' => ['type' => 'string'],
        'person_name'  => ['type' => 'string'],
        'title'        => ['type' => 'string'],
        'department'   => ['type' => 'string'],
        'phone'        => ['type' => 'string'],
        'mobile'       => ['type' => 'string'],
        'fax'          => ['type' => 'string'],
        'email'        => ['type' => 'string'],
        'website'      => ['type' => 'string'],
        'address'      => ['type' => 'string'],
    ],
    'required' => ['company_name', 'person_name'],
];

try {
    $response = $gemini->generateContent(
        [
            ['text' => $prompt],
            ['inline_data' => [
                'mime_type' => $mime,
                'data'      => $base64,
            ]],
        ],
        [
            'generation_config' => [
                'response_mime_type' => 'application/json',
                'response_schema'    => $schema,
                'temperature'        => 0.1,
            ],
        ]
    );
} catch (Exception $e) {
    error_log('[business-card-ocr] Gemini call failed: ' . $e->getMessage());
    errorResponse('AI解析に失敗しました: ' . $e->getMessage(), 502);
}

$text = $gemini->extractText($response);
$parsed = json_decode($text, true);
if (!is_array($parsed)) {
    error_log('[business-card-ocr] Failed to parse JSON: ' . substr($text, 0, 500));
    errorResponse('AIレスポンスの解析に失敗しました', 502);
}

// 期待フィールドのみ抜き出し（null安全）
$fields = [];
foreach (array_keys($schema['properties']) as $key) {
    $v = $parsed[$key] ?? '';
    $fields[$key] = is_string($v) ? trim($v) : '';
}

// 画像を Base64 のままレスポンスに含め、保存時にクライアントから再送してもらう
// （往復のサイズを増やさないため image_data_url を返す）
$dataUrl = 'data:' . $mime . ';base64,' . $base64;

successResponse([
    'fields'        => $fields,
    'mime'          => $mime,
    'image_data_url'=> $dataUrl,
    'model'         => $gemini->getModel(),
], '名刺を解析しました');
