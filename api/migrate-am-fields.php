<?php
/**
 * アカウントマネジメント スキーマ マイグレーション（本番用・冪等）
 *
 * - customers に AM/ランク管理カラムを ALTER ADD（無い分のみ）
 * - customer_cc テーブルを CREATE TABLE IF NOT EXISTS
 *
 * 実行方法: admin ログイン状態で /api/migrate-am-fields.php を開く（何度開いても安全）
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

// customers に追加するカラム（カラム名 => ADD 句）
$columns = [
    // 営業情報統合 (M2)
    'customer_code'  => "ADD COLUMN `customer_code` VARCHAR(64) DEFAULT NULL",
    'customer_rank'  => "ADD COLUMN `customer_rank` VARCHAR(8) DEFAULT NULL",
    'rank_mode'      => "ADD COLUMN `rank_mode` VARCHAR(16) DEFAULT 'auto'",
    'rank_manual'    => "ADD COLUMN `rank_manual` VARCHAR(8) DEFAULT NULL",
    'am_employee_id' => "ADD COLUMN `am_employee_id` VARCHAR(36) DEFAULT NULL",
    'industry'       => "ADD COLUMN `industry` VARCHAR(255) DEFAULT NULL",
    'trade_start'    => "ADD COLUMN `trade_start` DATE DEFAULT NULL",
    'credit_limit'   => "ADD COLUMN `credit_limit` DECIMAL(15,2) DEFAULT NULL",
    'area'           => "ADD COLUMN `area` VARCHAR(64) DEFAULT NULL",
    // アカウントマネジメント（戦略アカウント管理リスト由来）
    'am_number'         => "ADD COLUMN `am_number` VARCHAR(16) DEFAULT NULL",
    'account_status'    => "ADD COLUMN `account_status` VARCHAR(16) DEFAULT NULL",
    'account_type'      => "ADD COLUMN `account_type` VARCHAR(64) DEFAULT NULL",
    'account_type_memo' => "ADD COLUMN `account_type_memo` VARCHAR(255) DEFAULT NULL",
    'hq_location'       => "ADD COLUMN `hq_location` VARCHAR(255) DEFAULT NULL",
    'priority'          => "ADD COLUMN `priority` VARCHAR(32) DEFAULT NULL",
    'rank_challenge'    => "ADD COLUMN `rank_challenge` VARCHAR(8) DEFAULT NULL",
    'am_person'         => "ADD COLUMN `am_person` VARCHAR(64) DEFAULT NULL",
    'am_memo'           => "ADD COLUMN `am_memo` TEXT DEFAULT NULL",
];

try {
    $pdo = Database::connect();

    // ---- ステップ1: customers のカラム追加（不足分のみ） ----
    echo "■ customers カラム追加\n";
    $toAdd = [];
    foreach ($columns as $col => $clause) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'customers' AND column_name = ?
        ");
        $stmt->execute([$col]);
        if ((int)$stmt->fetchColumn() === 0) {
            $toAdd[] = $clause;
            echo "  + {$col}\n";
        } else {
            echo "  - {$col} (既存)\n";
        }
    }
    if (!empty($toAdd)) {
        $pdo->exec('ALTER TABLE `customers` ' . implode(', ', $toAdd));
        echo "  → ALTER 実行 (" . count($toAdd) . "カラム)\n";
        // インデックス（無ければ追加・エラーは無視）
        foreach ([
            'idx_am'             => '`am_employee_id`',
            'idx_rank'           => '`customer_rank`',
            'idx_am_number'      => '`am_number`',
            'idx_account_status' => '`account_status`',
        ] as $idx => $colExpr) {
            try { $pdo->exec("ALTER TABLE `customers` ADD INDEX `{$idx}` ({$colExpr})"); }
            catch (\Throwable $e) { /* 既存 */ }
        }
    } else {
        echo "  → 追加なし（全て既存）\n";
    }

    // ---- ステップ2: customer_cc テーブル ----
    echo "■ customer_cc テーブル\n";
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `customer_cc` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `customer_id` VARCHAR(36) NOT NULL,
    `employee_id` VARCHAR(36) DEFAULT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `role_label` VARCHAR(255) DEFAULT NULL,
    `note` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT DEFAULT 0,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_cc_customer` (`customer_id`),
    INDEX `idx_cc_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
    echo "  → CREATE TABLE IF NOT EXISTS 完了\n";

    echo "\nマイグレーション完了\n";
    echo "次: /api/import-am-accounts.php を開いてアカウントマネジメントリストを取込んでください。\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
