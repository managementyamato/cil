<?php
/**
 * manuals テーブル マイグレーション
 *
 * - 新規環境: manuals テーブルを作成
 * - 既存環境: visible_to カラムを ALTER で追加 (冪等)
 *
 * 実行方法: admin ログイン状態で /api/migrate-manuals.php を開く
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

if (!isAdmin()) {
    http_response_code(403);
    echo 'admin only';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

if (!class_exists('Database')) {
    echo "Database class not loaded (DB_MODE=json?)\n";
    exit;
}

$mode = Database::getMode();
echo "DB_MODE: {$mode}\n";

if ($mode === 'json') {
    echo "JSON モードのためマイグレーション不要。\n";
    exit;
}

try {
    $pdo = Database::connect();

    // ステップ1: manuals テーブルを CREATE (既に存在すればスキップ)
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `manuals` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `url` TEXT NOT NULL,
    `description` TEXT DEFAULT NULL,
    `search_keywords` TEXT DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `tags` JSON DEFAULT NULL,
    `visible_to` JSON DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_category` (`category`),
    INDEX `idx_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $pdo->exec($sql);
    echo "Step 1: CREATE TABLE IF NOT EXISTS 完了\n";

    // ステップ2: 既存テーブルへの ALTER (visible_to カラム追加)
    // INFORMATION_SCHEMA でカラム存在確認
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'manuals' AND column_name = 'visible_to'
    ");
    $stmt->execute();
    $exists = (int)$stmt->fetchColumn();

    if ($exists === 0) {
        $pdo->exec("ALTER TABLE `manuals` ADD COLUMN `visible_to` JSON DEFAULT NULL AFTER `tags`");
        echo "Step 2: visible_to カラムを追加しました\n";
    } else {
        echo "Step 2: visible_to カラムは既に存在 (スキップ)\n";
    }

    $count = $pdo->query('SELECT COUNT(*) FROM manuals')->fetchColumn();
    echo "現在の件数: {$count}\n";
    echo "マイグレーション完了\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
