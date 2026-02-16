<?php
/**
 * Excelテンプレート構造分析スクリプト
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$excelFile = __DIR__ . '/../templates/invoice-formats/アクティオ.xlsx';

if (!file_exists($excelFile)) {
    die("ファイルが見つかりません: {$excelFile}\n");
}

echo "=== Excelテンプレート構造分析 ===\n\n";
echo "ファイル: " . basename($excelFile) . "\n";
echo "サイズ: " . round(filesize($excelFile) / 1024, 2) . " KB\n\n";

try {
    $spreadsheet = IOFactory::load($excelFile);
    $sheet = $spreadsheet->getActiveSheet();

    echo "シート名: " . $sheet->getTitle() . "\n";
    echo "最大行: " . $sheet->getHighestRow() . "\n";
    echo "最大列: " . $sheet->getHighestColumn() . "\n\n";

    echo "=== セルの内容（値が入っているセルのみ）===\n\n";

    $cellData = [];
    $maxRow = min($sheet->getHighestRow(), 50); // 最初の50行まで

    for ($row = 1; $row <= $maxRow; $row++) {
        for ($col = 'A'; $col <= 'Z'; $col++) {
            $cellAddress = $col . $row;
            $cell = $sheet->getCell($cellAddress);
            $value = $cell->getValue();

            if ($value !== null && $value !== '') {
                // 数式の場合は計算結果も表示
                if ($cell->isFormula()) {
                    $calculatedValue = $cell->getCalculatedValue();
                    echo sprintf("%-5s = [数式] %s (結果: %s)\n",
                        $cellAddress,
                        $value,
                        $calculatedValue
                    );
                } else {
                    echo sprintf("%-5s = %s\n", $cellAddress, $value);
                }

                $cellData[$cellAddress] = $value;
            }
        }
    }

    echo "\n=== 入力が必要そうなセル（テキストから推測）===\n\n";

    $keywords = [
        '請求書' => [],
        '日付' => [],
        '期限' => [],
        '御中' => [],
        '品目' => [],
        '数量' => [],
        '単価' => [],
        '金額' => [],
        '合計' => [],
        '小計' => [],
        '消費税' => [],
        '備考' => []
    ];

    foreach ($cellData as $address => $value) {
        foreach ($keywords as $keyword => $matches) {
            if (mb_strpos($value, $keyword) !== false) {
                $keywords[$keyword][] = $address . ': ' . $value;
            }
        }
    }

    foreach ($keywords as $keyword => $matches) {
        if (!empty($matches)) {
            echo "[{$keyword}]\n";
            foreach ($matches as $match) {
                echo "  " . $match . "\n";
            }
            echo "\n";
        }
    }

    echo "=== 分析完了 ===\n";

} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    echo "スタックトレース:\n" . $e->getTraceAsString() . "\n";
}
