<?php
/**
 * 価格表シート同期（CLI 用）
 *
 * 通常は管理画面の「Googleシートから同期」ボタンを使うが、初回セットアップや
 * デバッグ時のためのコマンドライン同期スクリプト。
 *
 * 実行: C:\xampp\php\php.exe scripts\sync-price-sheet.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/google-sheets.php';

$SPREADSHEET_ID = '1cJGD2QiaNhOeZsLuLzhbTL83vTcgnMftiv4qTIt_UYc';
$JSON_PATH      = __DIR__ . '/../data/product-prices.json';
$FETCH_RANGE    = 'A1:Z200';

function trimEmpty(array $values): array {
    while (!empty($values)) {
        $last = end($values);
        $isEmpty = true;
        if (is_array($last)) {
            foreach ($last as $cell) {
                if (trim((string)$cell) !== '') { $isEmpty = false; break; }
            }
        }
        if (!$isEmpty) break;
        array_pop($values);
    }
    return $values;
}

try {
    echo "Sheets API: 取得中...\n";
    $client = new GoogleSheetsClient($SPREADSHEET_ID);
    $sheets = $client->getSheets();
    echo "シート数: " . count($sheets) . "\n";

    $result = [
        'spreadsheet_id'  => $SPREADSHEET_ID,
        'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/' . $SPREADSHEET_ID . '/edit',
        'synced_at'       => date('Y-m-d H:i:s'),
        'synced_by'       => 'cli',
        'sheets'          => [],
    ];

    foreach ($sheets as $s) {
        $title   = $s['properties']['title']   ?? '';
        $sheetId = $s['properties']['sheetId'] ?? 0;
        if ($title === '') continue;
        echo "  [{$sheetId}] {$title} ... ";
        try {
            $values = $client->getValues($title . '!' . $FETCH_RANGE);
            $values = trimEmpty($values);
            $result['sheets'][] = [
                'title'    => $title,
                'sheet_id' => $sheetId,
                'values'   => $values,
            ];
            echo count($values) . " 行\n";
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            $result['sheets'][] = [
                'title'    => $title,
                'sheet_id' => $sheetId,
                'values'   => [],
                'error'    => $e->getMessage(),
            ];
        }
    }

    $dir = dirname($JSON_PATH);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (file_put_contents($JSON_PATH, $json, LOCK_EX) === false) {
        throw new Exception('JSON書き込み失敗');
    }
    echo "\n保存完了: " . $JSON_PATH . " (" . round(strlen($json) / 1024, 1) . " KB)\n";
} catch (Exception $e) {
    fwrite(STDERR, "FATAL: " . $e->getMessage() . "\n");
    exit(1);
}
