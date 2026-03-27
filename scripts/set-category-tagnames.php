<?php
/**
 * 既存 productCategories に tag_name を設定するスクリプト
 * PICLES、ゲンバルジャー、モニんじゃ に対応するタグ名をセット
 */
require_once __DIR__ . '/../config/config.php';

$data = getData();

// タグ名マッピング: カテゴリ名 => 請求書のtag_namesに含まれる文字列
$tagMap = [
    'PICLES'      => 'PICLES',
    'ゲンバルジャー' => 'ゲンバルジャー',
    'モニんじゃ'   => 'モニんじゃ',
];

$updated = 0;
foreach ($data['productCategories'] as &$cat) {
    $name = $cat['name'] ?? '';
    if (isset($tagMap[$name])) {
        $cat['tag_name'] = $tagMap[$name];
        $cat['updated_at'] = date('Y-m-d H:i:s');
        echo "✅ {$name} → tag_name = {$tagMap[$name]}\n";
        $updated++;
    }
}
unset($cat);

if ($updated > 0) {
    saveData($data);
    echo "\n{$updated}件のカテゴリに tag_name を設定しました。\n";
} else {
    echo "更新対象なし\n";
}
