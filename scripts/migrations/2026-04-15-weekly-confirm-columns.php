<?php
/**
 * 一時マイグレーション: weekly_reports に confirmed_at 等のカラムを追加
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/auth.php';

if (!isAdmin()) { echo json_encode(['error' => 'admin only']); exit; }

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connect();

    // カラム存在チェック
    $cols = $pdo->query("SHOW COLUMNS FROM weekly_reports")->fetchAll(PDO::FETCH_COLUMN);

    $added = [];

    if (!in_array('confirmed_at', $cols)) {
        $pdo->exec("ALTER TABLE weekly_reports ADD COLUMN `confirmed_at` DATETIME DEFAULT NULL");
        $added[] = 'confirmed_at';
    }
    if (!in_array('confirmed_by', $cols)) {
        $pdo->exec("ALTER TABLE weekly_reports ADD COLUMN `confirmed_by` VARCHAR(255) DEFAULT NULL");
        $added[] = 'confirmed_by';
    }
    if (!in_array('confirmed_by_name', $cols)) {
        $pdo->exec("ALTER TABLE weekly_reports ADD COLUMN `confirmed_by_name` VARCHAR(255) DEFAULT NULL");
        $added[] = 'confirmed_by_name';
    }
    if (!in_array('confirm_token', $cols)) {
        $pdo->exec("ALTER TABLE weekly_reports ADD COLUMN `confirm_token` VARCHAR(255) DEFAULT NULL");
        $added[] = 'confirm_token';
    }

    echo json_encode([
        'success' => true,
        'added' => $added ?: 'all columns already exist',
        'current_columns' => $pdo->query("SHOW COLUMNS FROM weekly_reports")->fetchAll(PDO::FETCH_COLUMN),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
