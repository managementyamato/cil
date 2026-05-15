<?php
/**
 * leads テーブル マイグレーション
 *
 * - 新規環境: leads テーブルを作成（または既存のスタブを置き換え）
 * - 既存環境: 不足カラムを ALTER で追加（冪等）
 *
 * 実行方法: admin ログイン状態で /api/migrate-leads.php を開く
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

    // ステップ1: テーブルがなければ作成
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `leads` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `company_name` VARCHAR(255) NOT NULL,
    `person_name` VARCHAR(255) DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `department` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(64) DEFAULT NULL,
    `mobile` VARCHAR(64) DEFAULT NULL,
    `fax` VARCHAR(64) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `website` VARCHAR(512) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `status` VARCHAR(32) DEFAULT '新規',
    `source` VARCHAR(32) DEFAULT 'manual',
    `business_card_image_path` VARCHAR(512) DEFAULT NULL,
    `am` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_company` (`company_name`),
    INDEX `idx_status` (`status`),
    INDEX `idx_deleted` (`deleted_at`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    $pdo->exec($sql);
    echo "Step 1: CREATE TABLE IF NOT EXISTS leads — OK\n";

    // ステップ2: 不足カラムを追加（既存のスタブテーブル対応）
    $needed = [
        'company_name'             => "VARCHAR(255) NOT NULL",
        'person_name'              => "VARCHAR(255) DEFAULT NULL",
        'title'                    => "VARCHAR(255) DEFAULT NULL",
        'department'               => "VARCHAR(255) DEFAULT NULL",
        'phone'                    => "VARCHAR(64) DEFAULT NULL",
        'mobile'                   => "VARCHAR(64) DEFAULT NULL",
        'fax'                      => "VARCHAR(64) DEFAULT NULL",
        'email'                    => "VARCHAR(255) DEFAULT NULL",
        'website'                  => "VARCHAR(512) DEFAULT NULL",
        'address'                  => "TEXT DEFAULT NULL",
        'status'                   => "VARCHAR(32) DEFAULT '新規'",
        'source'                   => "VARCHAR(32) DEFAULT 'manual'",
        'business_card_image_path' => "VARCHAR(512) DEFAULT NULL",
        'am'                       => "VARCHAR(255) DEFAULT NULL",
        'notes'                    => "TEXT DEFAULT NULL",
        'created_by'               => "VARCHAR(255) DEFAULT NULL",
        'created_at'               => "DATETIME DEFAULT NULL",
        'updated_at'               => "DATETIME DEFAULT NULL",
    ];

    $stmt = $pdo->query("SHOW COLUMNS FROM `leads`");
    $existing = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    foreach ($needed as $col => $def) {
        if (!in_array($col, $existing, true)) {
            $alter = "ALTER TABLE `leads` ADD COLUMN `{$col}` {$def}";
            $pdo->exec($alter);
            echo "Step 2: ADD COLUMN {$col} — OK\n";
        }
    }

    // ステップ3: 推奨インデックスを追加（既存なければ）
    $idx = $pdo->query("SHOW INDEX FROM `leads`")->fetchAll(PDO::FETCH_ASSOC);
    $idxNames = array_column($idx, 'Key_name');
    $wantIdx = [
        'idx_company' => '`company_name`',
        'idx_status'  => '`status`',
        'idx_deleted' => '`deleted_at`',
        'idx_created' => '`created_at`',
    ];
    foreach ($wantIdx as $name => $col) {
        if (!in_array($name, $idxNames, true)) {
            $pdo->exec("ALTER TABLE `leads` ADD INDEX `{$name}` ({$col})");
            echo "Step 3: ADD INDEX {$name} — OK\n";
        }
    }

    echo "\n完了。\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    http_response_code(500);
}
