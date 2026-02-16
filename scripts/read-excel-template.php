<?php
/**
 * Excelテンプレート読み込みスクリプト
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$excelFile = 'C:\Users\User\Desktop\2602_アクティオ指定請求書（適格請求書）※Gmailで各営業所宛に送付が必要.xlsx';

if (!file_exists($excelFile)) {
    echo "❌ ファイルが見つかりません: {$excelFile}\n";
    exit(1);
}

try {
    echo "📂 Excelファイルを読み込み中...\n";
    $spreadsheet = IOFactory::load($excelFile);

    echo "✅ 読み込み完了\n\n";

    // シート一覧を表示
    echo "📊 シート一覧:\n";
    $sheetNames = $spreadsheet->getSheetNames();
    foreach ($sheetNames as $index => $name) {
        echo "  [" . ($index + 1) . "] {$name}\n";
    }
    echo "\n";

    // 各シートの内容をサンプル表示
    foreach ($sheetNames as $sheetIndex => $sheetName) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "シート: {$sheetName}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $sheet = $spreadsheet->getSheet($sheetIndex);
        $highestRow = min($sheet->getHighestRow(), 40); // 最大40行まで表示
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        echo "最大行: {$highestRow}, 最大列: {$highestColumn}\n\n";

        // セルの内容を表示（最初の40行）
        for ($row = 1; $row <= $highestRow; $row++) {
            $hasContent = false;
            $rowData = [];

            for ($col = 1; $col <= min($highestColumnIndex, 20); $col++) { // 最大20列まで
                $cell = $sheet->getCellByColumnAndRow($col, $row);
                $value = $cell->getCalculatedValue();

                if ($value !== null && $value !== '') {
                    $hasContent = true;
                    $rowData[] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row . ': ' . $value;
                }
            }

            if ($hasContent) {
                echo "行{$row}: " . implode(' | ', $rowData) . "\n";
            }
        }

        echo "\n\n";

        // 最初のシートだけ詳細表示
        if ($sheetIndex === 0) {
            break;
        }
    }

} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}
