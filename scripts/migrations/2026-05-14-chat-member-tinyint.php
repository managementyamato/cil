<?php
/**
 * Migration: employees.chat_member VARCHAR(255) -> TINYINT(1)
 *
 * database.php が bool として扱っているが create-tables.sql が VARCHAR(255) だったため
 * 文字列が常に true 評価される不具合を修正。
 * 実データは '0'/'1' のみ確認済みのため安全に変換可能。
 */

define('BASE_PATH', dirname(__DIR__, 2));
chdir(BASE_PATH);
require_once 'config/config.php';

if (!class_exists('Database')) {
    echo "ERROR: Database class not found. DB_MODE が json の環境では実行不要。\n";
    exit(1);
}

$pdo = Database::connect();

// 現状確認
$col = $pdo->query("
    SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'chat_member'
")->fetch(PDO::FETCH_ASSOC);

echo "現在のカラム定義: " . ($col ? json_encode($col) : 'カラムなし') . "\n";

if ($col && strtolower($col['COLUMN_TYPE']) === 'tinyint(1)') {
    echo "既に TINYINT(1) です。スキップ。\n";
    exit(0);
}

// 安全確認
$unexpected = $pdo->query("
    SELECT COUNT(*) FROM employees
    WHERE chat_member IS NOT NULL
      AND chat_member NOT IN ('0', '1', '', '0', '1')
")->fetchColumn();

if ($unexpected > 0) {
    echo "ERROR: 変換不能な値が {$unexpected} 件あります。手動確認が必要。\n";
    exit(1);
}

// ALTER TABLE
$pdo->exec("ALTER TABLE employees MODIFY COLUMN chat_member TINYINT(1) NOT NULL DEFAULT 0");
echo "OK: chat_member を TINYINT(1) NOT NULL DEFAULT 0 に変更しました。\n";

// 確認
$after = $pdo->query("
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'employees'
      AND COLUMN_NAME = 'chat_member'
")->fetchColumn();
echo "変更後: {$after}\n";
