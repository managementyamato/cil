<?php
/**
 * アカウントマネジメントリスト（Googleスプレッドシート）を customers に一回取込するスクリプト。
 *
 * 方針（ユーザー合意 2026-05-28）:
 *   - このシートをアカウントマネジメントの「正」とし、一回だけインポート。以降はアプリで編集。
 *   - 顧客名で名寄せ。既存顧客があればAM項目を補完更新、無ければ新規作成（source=am_sheet）。
 *   - ランク現在 → customer_rank（S/A/B、'-'は未設定）、ランクチャレンジ → rank_challenge（S/A/B抽出）。
 *   - 直近5ヶ月の金額はシートが空のため取り込まない（アプリ側でMF請求から算出表示する）。
 *
 * 使い方:
 *   scripts/php.exe scripts/import-am-sheet.php            ← dry-run（変更しない）
 *   scripts/php.exe scripts/import-am-sheet.php --apply     ← 実取込
 */

if (php_sapi_name() !== 'cli') die('CLI only');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/google-sheets.php';
require_once __DIR__ . '/../functions/customer-rank.php';

const AM_SHEET_ID    = '123exkvDCLCfPLqEnwNg_4M7ncrNCH4h9u0hKPl6ZUNg';
const AM_SHEET_TITLE = 'アカウントマネジメントリスト';

$apply = in_array('--apply', $argv, true);

echo "==================================================================\n";
echo " アカウントマネジメントリスト 取込\n";
echo ' ' . ($apply ? '*** 適用モード (--apply) ***' : '[ dry-run — 変更しません ]') . "\n";
echo "==================================================================\n\n";

// ---- シート読込 ----
$client = new GoogleSheetsClient(AM_SHEET_ID);
$rows = $client->getValues(AM_SHEET_TITLE . '!A1:Z200');
if (!is_array($rows) || count($rows) < 2) {
    fwrite(STDERR, "シートを読めませんでした\n");
    exit(1);
}
array_shift($rows); // ヘッダ除去

// 列インデックス
$C = ['am_number'=>1,'name'=>2,'status'=>3,'type'=>4,'type_memo'=>5,'hq'=>6,'priority'=>9,'rank_now'=>10,'rank_challenge'=>11,'tanto'=>12,'memo'=>13];

function cell(array $row, int $i): string { return trim((string)($row[$i] ?? '')); }
function rankLetter(string $v): string { return preg_match('/[SAB]/u', $v, $m) ? $m[0] : ''; }

// ---- 既存顧客を正規化名でインデックス ----
$existing = Database::queryEntity('customers', ['not_deleted' => true]);
$byNorm = [];
foreach ($existing as $c) {
    $byNorm[normalizeCompanyName($c['companyName'] ?? '')] = $c;
}

$created = $updated = $skipped = 0;
$now = formatDateIso();

foreach ($rows as $row) {
    $name = cell($row, $C['name']);
    if ($name === '') { $skipped++; continue; }
    $norm = normalizeCompanyName($name);

    $amFields = [
        'am_number'         => cell($row, $C['am_number']),
        'account_status'    => cell($row, $C['status']),
        'account_type'      => cell($row, $C['type']),
        'account_type_memo' => cell($row, $C['type_memo']),
        'hq_location'       => cell($row, $C['hq']),
        'priority'          => cell($row, $C['priority']),
        'customer_rank'     => isValidCustomerRank(rankLetter(cell($row, $C['rank_now']))) ? rankLetter(cell($row, $C['rank_now'])) : '',
        'rank_challenge'    => rankLetter(cell($row, $C['rank_challenge'])),
        'am_person'         => cell($row, $C['tanto']),
        'am_memo'           => cell($row, $C['memo']),
        'updated_at'        => $now,
    ];

    if (isset($byNorm[$norm])) {
        $c = array_merge($byNorm[$norm], $amFields);
        $action = '更新';
        $updated++;
    } else {
        $c = array_merge([
            'id'          => 'c_' . uniqid(),
            'companyName' => $name,
            'source'      => 'am_sheet',
            'created_at'  => $now,
        ], $amFields);
        $action = '新規';
        $created++;
    }

    printf("  [%-2s] %-4s %-28s status=%-4s rank=%-2s tanto=%s\n",
        $amFields['am_number'], $action, mb_strimwidth($name, 0, 26, '..'),
        $amFields['account_status'], ($amFields['customer_rank'] ?: '-'), $amFields['am_person']);

    if ($apply) {
        Database::saveEntityRow('customers', $c);
    }
}

echo "\n------------------------------------------------------------------\n";
echo "  新規作成: {$created} / 更新: {$updated} / スキップ: {$skipped}\n";
if (!$apply) {
    echo "  ※ dry-run。実取込は --apply を付けて再実行してください。\n";
}
echo "==================================================================\n";
