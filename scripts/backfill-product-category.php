<?php
/**
 * PJのproduct_categoryをメーカーマスタのmaker_idsマッピングで一括補完するスクリプト
 *
 * 使い方:
 *   php scripts/backfill-product-category.php          # ドライラン（確認のみ）
 *   php scripts/backfill-product-category.php --apply  # 実際に書き込む
 */

require_once __DIR__ . '/../config/config.php';

$isDryRun = !in_array('--apply', $argv ?? []);

if ($isDryRun) {
    echo "[DRY RUN] 実際の書き込みは行いません。--apply を付けると適用されます。\n\n";
}

$data = getData();

// ① メーカー名 → メーカーID のマップ
$makerNameToId = [];
foreach ($data['manufacturers'] ?? [] as $m) {
    if (!empty($m['name']) && !empty($m['id'])) {
        $makerNameToId[trim($m['name'])] = $m['id'];
    }
}

// ② メーカーID → 製品カテゴリ名 のマップ
$makerIdToCategory = [];
foreach ($data['productCategories'] ?? [] as $cat) {
    if (empty($cat['maker_ids']) || empty($cat['name'])) continue;
    foreach ($cat['maker_ids'] as $mid) {
        $makerIdToCategory[$mid] = $cat['name'];
    }
}

echo "マスタ情報:\n";
echo "  メーカー登録数: " . count($makerNameToId) . "\n";
echo "  製品カテゴリ数: " . count($data['productCategories'] ?? []) . "\n";
echo "  メーカー→カテゴリ対応数: " . count($makerIdToCategory) . "\n\n";

// ③ product_categoryが空のPJを対象に補完
$updated = 0;
$skipped = 0;
$noMapping = [];

foreach ($data['projects'] as $i => $pj) {
    $cat = trim($pj['product_category'] ?? '');
    if ($cat !== '') continue; // すでに設定済みはスキップ

    $makerName = trim($pj['maker'] ?? '');
    if ($makerName === '' || $makerName === '-') {
        $skipped++;
        continue;
    }

    $makerId = $makerNameToId[$makerName] ?? null;
    if (!$makerId) {
        $noMapping[$makerName] = ($noMapping[$makerName] ?? 0) + 1;
        continue;
    }

    $categoryName = $makerIdToCategory[$makerId] ?? null;
    if (!$categoryName) {
        $noMapping[$makerName] = ($noMapping[$makerName] ?? 0) + 1;
        continue;
    }

    echo "  " . $pj['id'] . " | maker=" . $makerName . " → " . $categoryName . "\n";
    $updated++;

    if (!$isDryRun) {
        $data['projects'][$i]['product_category'] = $categoryName;
        $data['projects'][$i]['updated_at'] = date('Y-m-d H:i:s');
    }
}

echo "\n=== 結果 ===\n";
echo "補完対象: {$updated}件\n";
echo "メーカー未設定のためスキップ: {$skipped}件\n";

if (!empty($noMapping)) {
    echo "マッピングなし（カテゴリ未登録のメーカー）:\n";
    foreach ($noMapping as $name => $cnt) {
        echo "  {$name}: {$cnt}件\n";
    }
}

if (!$isDryRun && $updated > 0) {
    saveData($data);
    echo "\n✅ {$updated}件を更新しました。\n";
} elseif ($isDryRun) {
    echo "\n[DRY RUN] 実際の書き込みは行っていません。適用するには --apply を付けてください。\n";
}
