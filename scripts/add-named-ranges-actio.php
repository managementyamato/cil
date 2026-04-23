<?php
/**
 * 現在の uploads/invoice-templates/actio.xlsx に「名前の定義」を追加する一回限りのスクリプト
 *
 * 実行: /c/xampp/php/php.exe -d memory_limit=512M scripts/add-named-ranges-actio.php
 * 出力: uploads/invoice-templates/actio-named.xlsx（オリジナルは保持）
 */
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\DefinedName;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

$inPath = __DIR__ . '/../uploads/invoice-templates/actio.xlsx';
$outPath = __DIR__ . '/../uploads/invoice-templates/actio-named.xlsx';

if (!file_exists($inPath)) {
    die("入力ファイルが見つかりません: $inPath\n");
}

// 原本シートのみロード（メモリ節約）
$reader = IOFactory::createReaderForFile($inPath);
$reader->setLoadSheetsOnly(['原本（管理部のみ編集）']);
$spreadsheet = $reader->load($inPath);

$sheet = $spreadsheet->getSheetByName('原本（管理部のみ編集）');
if (!$sheet) {
    die("原本シートが見つかりません\n");
}

// シート名を「請求書」に変更（出力時の見栄え）
$sheet->setTitle('請求書');
$sheetName = $sheet->getTitle();

// 定義する名前一覧
$namedRanges = [
    'branch_name'        => "'$sheetName'!\$J\$10",
    'billing_date_year'  => "'$sheetName'!\$AI\$10:\$AL\$10",
    'billing_date_month' => "'$sheetName'!\$AN\$10:\$AO\$10",
    'billing_date_day'   => "'$sheetName'!\$AQ\$10:\$AR\$10",
    'partner_code'       => "'$sheetName'!\$BG\$11:\$BP\$11",
    'items_table'        => "'$sheetName'!\$B\$14:\$BP\$26",
];

foreach ($namedRanges as $name => $range) {
    $spreadsheet->addDefinedName(DefinedName::createInstance($name, $sheet, $range));
    echo "定義済み: $name = $range\n";
}

// 隠しシート _branches を作成（営業所マスタ）
$branchesSheet = $spreadsheet->createSheet();
$branchesSheet->setTitle('_branches');
$branchesSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

$branches = [
    ['宮崎営業所', '1668202000'],
    ['熊本営業所', '1668202000'],
    ['福岡営業所', '1668202000'],
    ['坂出営業所', '1668202000'],
    ['麻布営業所', '1668202000'],
    ['八幡営業所', '1668202000'],
    ['長崎営業所', '1668202000'],
    ['小倉営業所', '1668202000'],
];
$branchesSheet->setCellValue('A1', '営業所名');
$branchesSheet->setCellValue('B1', '取引先コード');
foreach ($branches as $i => $b) {
    $row = $i + 2;
    $branchesSheet->setCellValue("A$row", $b[0]);
    $branchesSheet->setCellValueExplicit("B$row", $b[1], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
}
echo "隠しシート '_branches' 作成: " . count($branches) . "営業所登録\n";

// 出力
$writer = new XlsxWriter($spreadsheet);
$writer->save($outPath);
echo "\n出力: $outPath\n";
echo "サイズ: " . filesize($outPath) . " bytes\n";

$spreadsheet->disconnectWorksheets();
