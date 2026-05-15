<?php
/**
 * 価格表スプレッドシートの構造調査スクリプト（開発用・1回きり）
 *
 * 実行: C:\xampp\php\php.exe scripts\inspect-price-sheet.php
 *
 * 全シート名 + 各シートの先頭30行をテキスト出力。
 * 構造を確認したら、本格的な同期スクリプトを設計する。
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/google-sheets.php';

$SPREADSHEET_ID = '1cJGD2QiaNhOeZsLuLzhbTL83vTcgnMftiv4qTIt_UYc';

try {
    $client = new GoogleSheetsClient($SPREADSHEET_ID);
    $sheets = $client->getSheets();

    echo "=== シート一覧 ===\n";
    foreach ($sheets as $s) {
        $t = $s['properties']['title'] ?? '';
        $id = $s['properties']['sheetId'] ?? '';
        echo "- [$id] $t\n";
    }
    echo "\n";

    foreach ($sheets as $s) {
        $title = $s['properties']['title'] ?? '';
        echo "================================================================\n";
        echo "シート: $title\n";
        echo "================================================================\n";
        try {
            $values = $client->getValues($title . '!A1:Z40');
            if (empty($values)) {
                echo "(空)\n";
            } else {
                foreach ($values as $i => $row) {
                    $line = '';
                    foreach ($row as $cell) {
                        $line .= str_pad(mb_substr((string)$cell, 0, 18), 20);
                    }
                    echo sprintf("%3d| %s\n", $i + 1, $line);
                }
            }
        } catch (Exception $e) {
            echo "[ERROR] " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    fwrite(STDERR, "FATAL: " . $e->getMessage() . "\n");
    exit(1);
}
