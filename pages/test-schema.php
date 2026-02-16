<?php
/**
 * データスキーマテスト
 */
require_once '../api/auth.php';

if (!isAdmin()) {
    die('Admin only');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== DataSchema Test ===\n\n";

try {
    echo "1. DataSchemaクラスの存在確認...\n";
    if (class_exists('DataSchema')) {
        echo "   ✓ DataSchemaクラスが見つかりました\n\n";
    } else {
        echo "   ✗ DataSchemaクラスが見つかりません\n\n";
        die();
    }

    echo "2. scheduled_invoicesエンティティの確認...\n";
    if (DataSchema::hasEntity('scheduled_invoices')) {
        echo "   ✓ scheduled_invoicesエンティティが定義されています\n\n";
    } else {
        echo "   ✗ scheduled_invoicesエンティティが定義されていません\n\n";
    }

    echo "3. getData()でスキーマ適用確認...\n";
    $data = getData();
    if (isset($data['scheduled_invoices'])) {
        echo "   ✓ scheduled_invoicesキーが存在します\n";
        echo "   データ型: " . gettype($data['scheduled_invoices']) . "\n";
        echo "   件数: " . count($data['scheduled_invoices']) . "\n\n";
    } else {
        echo "   ✗ scheduled_invoicesキーが存在しません\n\n";
    }

    echo "4. 全エンティティ一覧...\n";
    foreach (array_keys($data) as $key) {
        $count = is_array($data[$key]) ? count($data[$key]) : 'N/A';
        echo "   - {$key}: {$count}\n";
    }

    echo "\n=== テスト完了 ===\n";

} catch (Exception $e) {
    echo "\n✗ エラー発生: " . $e->getMessage() . "\n";
    echo "スタックトレース:\n" . $e->getTraceAsString() . "\n";
}
