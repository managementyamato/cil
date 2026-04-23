<?php
/**
 * 指定請求書生成のスモークテスト
 * 1) 名前の定義済みテンプレ (actio-named.xlsx)
 * 2) 名前の定義なしテンプレ (actio.xlsx) - 自動検出が機能するか
 */
require_once __DIR__ . '/../functions/custom-invoice-generator.php';

$templates = [
    'named' => __DIR__ . '/../uploads/invoice-templates/actio-named.xlsx',
    'raw'   => __DIR__ . '/../uploads/invoice-templates/actio.xlsx',
];

$testItems = [
    ['delivery_date' => '2026-04-10', 'name' => 'テスト品A', 'quantity' => 1, 'unit_price' => 10000, 'amount' => 10000, 'reduced_tax' => false],
    ['delivery_date' => '2026-04-20', 'name' => 'テスト品B', 'quantity' => 2, 'unit_price' => 500, 'amount' => 1000, 'reduced_tax' => true],
];

foreach ($templates as $label => $path) {
    echo "=== [$label] $path ===\n";
    if (!file_exists($path)) { echo "  (skip: file missing)\n"; continue; }

    // 検出結果だけまず見る
    require_once __DIR__ . '/../vendor/autoload.php';
    $ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    $sheet = null;
    foreach ($ss->getAllSheets() as $s) {
        if ($s->getTitle() === '_branches') continue;
        if ($s->getSheetState() !== \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN) {
            $sheet = $s; break;
        }
    }
    if (!$sheet) $sheet = $ss->getActiveSheet();
    $auto = autoDetectTemplateRanges($sheet);
    echo "  自動検出結果: " . json_encode($auto, JSON_UNESCAPED_UNICODE) . "\n";
    $existingNames = [];
    foreach ($ss->getDefinedNames() as $dn) $existingNames[] = $dn->getName() . '=' . $dn->getValue();
    echo "  既存の名前の定義: " . (empty($existingNames) ? '(なし)' : implode(' | ', $existingNames)) . "\n";
    $ss->disconnectWorksheets();

    $input = [
        'template_path' => $path,
        'filename_prefix' => "test-$label",
        'branch_name' => '熊本営業所',
        'partner_code' => '1668202000',
        'billing_date' => '2026-04-30',
        'items' => $testItems,
    ];
    try {
        $out = generateCustomInvoiceXlsx($input, __DIR__ . '/../temp/custom-invoices');
        echo "  生成: " . basename($out) . "\n";
    } catch (Throwable $e) {
        echo "  エラー: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
