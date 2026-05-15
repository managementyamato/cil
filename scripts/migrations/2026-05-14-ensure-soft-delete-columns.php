<?php
/**
 * 一時マイグレーション: 全テーブルに deleted_at / deleted_by カラムを追加
 *
 * softDelete() が値をセットしても、本番テーブルにカラムが無いと
 * saveEntityRow が黙って削除してしまうため、不足分を補う。
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/auth.php';

if (!isAdmin()) { echo json_encode(['error' => 'admin only']); exit; }

header('Content-Type: application/json; charset=utf-8');

$targetTables = [
    'projects', 'troubles', 'customers', 'partners', 'employees',
    'manufacturers', 'invoices', 'mf_invoices', 'loans', 'repayments',
    'tasks', 'announcements', 'memos', 'invoice_requests', 'leads',
    'weekly_reports', 'discount_approvals'
];

try {
    $pdo = Database::connect();

    $result = [];
    foreach ($targetTables as $tbl) {
        // テーブル存在確認
        $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tbl))->fetch();
        if (!$exists) {
            $result[$tbl] = 'SKIP (table not exist)';
            continue;
        }

        $cols = $pdo->query("SHOW COLUMNS FROM `{$tbl}`")->fetchAll(PDO::FETCH_COLUMN);
        $added = [];

        if (!in_array('deleted_at', $cols, true)) {
            $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL");
            $added[] = 'deleted_at';
        }
        if (!in_array('deleted_by', $cols, true)) {
            $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `deleted_by` VARCHAR(255) DEFAULT NULL");
            $added[] = 'deleted_by';
        }

        $result[$tbl] = $added ? ('ADDED: ' . implode(',', $added)) : 'OK (already has both)';
    }

    echo json_encode([
        'success' => true,
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ], JSON_UNESCAPED_UNICODE);
}
