<?php
/**
 * 顧客マスター × AM連動 (M2) のスキーマ移行スクリプト
 *
 *   - customers テーブルに営業情報統合カラムを ALTER ADD（不足分のみ）
 *   - customer_cc テーブルを CREATE（無ければ）
 *
 * 冪等: 既に存在するカラム/テーブルはスキップする。何度実行しても安全。
 *
 * 使い方:
 *   scripts/php.exe scripts/migrate-customer-rank-am.php            ← dry-run (デフォルト・変更しない)
 *   scripts/php.exe scripts/migrate-customer-rank-am.php --apply    ← 実適用
 *
 * 本番反映: 本番MySQLに対して実行する場合は、本番DB接続の .env を読む環境で
 *          --apply を実行すること（CLAUDE.md: 本番DB変更は要確認）。
 */

if (php_sapi_name() !== 'cli') die('CLI only');

require_once __DIR__ . '/../config/config.php';

$apply = in_array('--apply', $argv, true);

echo "==================================================================\n";
echo " 顧客マスター × AM連動 (M2) スキーマ移行\n";
echo ' ' . ($apply ? '*** 適用モード (--apply) ***' : '[ dry-run モード — 変更しません ]') . "\n";
echo "==================================================================\n\n";

$pdo    = Database::connect();
$dbname = env('DB_NAME', 'yamato_mgt');

/** カラム存在チェック */
function columnExists(PDO $pdo, string $dbname, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $stmt->execute([$dbname, $table, $column]);
    return $stmt->fetch() !== false;
}

/** テーブル存在チェック */
function tableExists(PDO $pdo, string $dbname, string $table): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1'
    );
    $stmt->execute([$dbname, $table]);
    return $stmt->fetch() !== false;
}

// ------------------------------------------------------------------
// 1. customers への ALTER ADD COLUMN（不足分のみ）
// ------------------------------------------------------------------
$customerColumns = [
    'customer_code'  => "ADD COLUMN `customer_code` VARCHAR(64) DEFAULT NULL",
    'customer_rank'  => "ADD COLUMN `customer_rank` VARCHAR(8) DEFAULT NULL",
    'rank_mode'      => "ADD COLUMN `rank_mode` VARCHAR(16) DEFAULT 'auto'",
    'rank_manual'    => "ADD COLUMN `rank_manual` VARCHAR(8) DEFAULT NULL",
    'am_employee_id' => "ADD COLUMN `am_employee_id` VARCHAR(36) DEFAULT NULL",
    'industry'       => "ADD COLUMN `industry` VARCHAR(255) DEFAULT NULL",
    'trade_start'    => "ADD COLUMN `trade_start` DATE DEFAULT NULL",
    'credit_limit'   => "ADD COLUMN `credit_limit` DECIMAL(15,2) DEFAULT NULL",
    'area'           => "ADD COLUMN `area` VARCHAR(64) DEFAULT NULL",
    // アカウントマネジメント項目（戦略アカウント管理リスト由来）
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

echo "■ customers テーブル: カラム追加\n";
if (!tableExists($pdo, $dbname, 'customers')) {
    echo "  ⚠ customers テーブルが存在しません。create-tables.sql を先に流してください。\n\n";
} else {
    $toAdd = [];
    foreach ($customerColumns as $col => $clause) {
        if (columnExists($pdo, $dbname, 'customers', $col)) {
            echo "  - {$col}: 既に存在 → スキップ\n";
        } else {
            echo "  + {$col}: 追加対象\n";
            $toAdd[] = $clause;
        }
    }
    if (!empty($toAdd) && $apply) {
        $sql = 'ALTER TABLE `customers` ' . implode(', ', $toAdd);
        $pdo->exec($sql);
        // インデックス（存在しなければ追加）— エラーは握りつぶす（既存時の重複対策）
        foreach ([
            'idx_am'             => '`am_employee_id`',
            'idx_rank'           => '`customer_rank`',
            'idx_am_number'      => '`am_number`',
            'idx_account_status' => '`account_status`',
        ] as $idx => $colExpr) {
            try { $pdo->exec("ALTER TABLE `customers` ADD INDEX `{$idx}` ({$colExpr})"); }
            catch (\Throwable $e) { /* 既存インデックス */ }
        }
        echo "  → ALTER 実行完了（" . count($toAdd) . "カラム）\n";
    }
}
echo "\n";

// ------------------------------------------------------------------
// 2. customer_cc テーブルの CREATE（無ければ）
// ------------------------------------------------------------------
echo "■ customer_cc テーブル\n";
if (tableExists($pdo, $dbname, 'customer_cc')) {
    echo "  - 既に存在 → スキップ\n";
} else {
    echo "  + 作成対象\n";
    if ($apply) {
        $pdo->exec(
            "CREATE TABLE `customer_cc` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  → CREATE TABLE 実行完了\n";
    }
}
echo "\n";

echo "==================================================================\n";
if ($apply) {
    echo " 適用完了。\n";
} else {
    echo " dry-run 完了。実適用するには --apply を付けて再実行してください。\n";
}
echo "==================================================================\n";
