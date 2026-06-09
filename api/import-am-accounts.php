<?php
/**
 * アカウントマネジメントリスト 取込（本番用・冪等）
 *
 * Googleスプレッドシート「アカウントマネジメントリスト」を読み、顧客名で名寄せして
 * customers に取込む。既存顧客はAM項目を補完更新、無ければ新規作成（source=am_sheet）。
 *
 * 実行方法: admin ログイン状態で /api/import-am-accounts.php を開く（何度開いても安全＝再取込）
 *           dry-run で確認したい場合は ?dry=1 を付ける。
 *
 * 前提: 先に /api/migrate-am-fields.php でカラムを追加しておくこと。
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/google-sheets.php';
require_once __DIR__ . '/../functions/customer-rank.php';

if (!isAdmin()) {
    http_response_code(403);
    echo 'admin only';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

if (!class_exists('Database') || Database::getMode() === 'json') {
    echo "DB モードでないため取込不可。\n";
    exit;
}

const AM_SHEET_ID    = '123exkvDCLCfPLqEnwNg_4M7ncrNCH4h9u0hKPl6ZUNg';
const AM_SHEET_TITLE = 'アカウントマネジメントリスト';

$dry = isset($_GET['dry']) && $_GET['dry'] === '1';
echo ($dry ? "[ dry-run ]\n" : "[ 取込実行 ]\n");

// カラム存在チェック（migrate 済みか確認）
$pdo = Database::connect();
$chk = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name='customers' AND column_name='am_number'")->fetchColumn();
if ((int)$chk === 0) {
    echo "ERROR: customers.am_number が未追加です。先に /api/migrate-am-fields.php を実行してください。\n";
    exit;
}

// シート読込
try {
    $client = new GoogleSheetsClient(AM_SHEET_ID);
    $rows = $client->getValues(AM_SHEET_TITLE . '!A1:Z200');
} catch (\Throwable $e) {
    echo "ERROR: シート読込失敗: " . $e->getMessage() . "\n";
    exit;
}
if (!is_array($rows) || count($rows) < 2) {
    echo "ERROR: シートにデータがありません。\n";
    exit;
}
array_shift($rows); // ヘッダ除去

$C = ['am_number'=>1,'name'=>2,'status'=>3,'type'=>4,'type_memo'=>5,'hq'=>6,'priority'=>9,'rank_now'=>10,'rank_challenge'=>11,'tanto'=>12,'memo'=>13];
$cell = fn(array $r, int $i) => trim((string)($r[$i] ?? ''));
$rankLetter = fn(string $v) => preg_match('/[SAB]/u', $v, $m) ? $m[0] : '';

// 既存顧客を正規化名でインデックス
$existing = Database::queryEntity('customers', ['not_deleted' => true]);
$byNorm = [];
foreach ($existing as $c) {
    $byNorm[normalizeCompanyName($c['companyName'] ?? '')] = $c;
}

$created = $updated = $skipped = 0;
$now = formatDateIso();

foreach ($rows as $row) {
    $name = $cell($row, $C['name']);
    if ($name === '') { $skipped++; continue; }
    $norm = normalizeCompanyName($name);

    $am = [
        'am_number'         => $cell($row, $C['am_number']),
        'account_status'    => $cell($row, $C['status']),
        'account_type'      => $cell($row, $C['type']),
        'account_type_memo' => $cell($row, $C['type_memo']),
        'hq_location'       => $cell($row, $C['hq']),
        'priority'          => $cell($row, $C['priority']),
        'customer_rank'     => isValidCustomerRank($rankLetter($cell($row, $C['rank_now']))) ? $rankLetter($cell($row, $C['rank_now'])) : '',
        'rank_challenge'    => $rankLetter($cell($row, $C['rank_challenge'])),
        'am_person'         => $cell($row, $C['tanto']),
        'am_memo'           => $cell($row, $C['memo']),
        'updated_at'        => $now,
    ];

    if (isset($byNorm[$norm])) {
        $rowData = array_merge($byNorm[$norm], $am);
        $updated++;
    } else {
        $rowData = array_merge(['id' => 'c_' . uniqid(), 'companyName' => $name, 'source' => 'am_sheet', 'created_at' => $now], $am);
        $created++;
    }

    if (!$dry) {
        Database::saveEntityRow('customers', $rowData);
    }
}

echo "新規作成: {$created} / 更新: {$updated} / スキップ: {$skipped}\n";
echo $dry ? "※ dry-run（保存していません）。?dry=1 を外すと実取込します。\n" : "取込完了。アカウントマネジメントタブで確認してください。\n";
