<?php
/**
 * マイグレーション: 主要テーブルにインデックスを追加
 *
 * 対象（書き込み頻度が中以下のテーブル）:
 *   - projects   (900件)
 *   - troubles   (155件)
 *   - tasks
 *   - weekly_reports
 *   - customers
 *   - contacts
 *   - leads
 *   - discount_approvals
 *
 * 除外（書き込みが激しい大規模テーブル）:
 *   - mf_invoices (10K件) ← UPSERT 化したが念のため別途検証してから追加
 *
 * 実行URL: /scripts/migrations/2026-05-11-add-indexes.php
 *
 * 冪等性: 既に存在するインデックスはスキップする
 */
require_once __DIR__ . '/../../config/config.php';

if (empty($_SESSION['user_email'])) { http_response_code(401); echo json_encode(['error' => 'login required']); exit; }
if (!isAdmin()) { http_response_code(403); echo json_encode(['error' => 'admin only']); exit; }

header('Content-Type: application/json; charset=utf-8');

$plan = [
    'projects' => [
        'idx_deleted_at'      => 'deleted_at',
        'idx_tag'             => 'tag',
        'idx_transaction_type'=> 'transaction_type',
        'idx_sales_assignee'  => 'sales_assignee',
        'idx_occurrence_date' => 'occurrence_date',
        'idx_customer_name'   => 'customer_name',
    ],
    'troubles' => [
        'idx_project_id'   => 'project_id',
        'idx_status'       => 'status',
        'idx_deleted_at'   => 'deleted_at',
        'idx_pj_number'    => 'pj_number',
    ],
    'tasks' => [
        'idx_deleted_at'   => 'deleted_at',
        'idx_assignee'     => 'assignee',
        'idx_due_date'     => 'due_date',
    ],
    'weekly_reports' => [
        'idx_week_start'   => 'week_start',
        'idx_user_email'   => 'user_email',
        'idx_deleted_at'   => 'deleted_at',
        'idx_confirmed_at' => 'confirmed_at',
    ],
    'customers' => [
        'idx_deleted_at'   => 'deleted_at',
    ],
    'contacts' => [
        'idx_deleted_at'   => 'deleted_at',
        'idx_company_id'   => 'company_id',
    ],
    'leads' => [
        'idx_deleted_at'   => 'deleted_at',
        'idx_status'       => 'status',
    ],
    'discount_approvals' => [
        'idx_deleted_at'   => 'deleted_at',
        'idx_requester'    => 'requester_email',
    ],
    'invoice_requests' => [
        'idx_deleted_at'   => 'deleted_at',
        'idx_status'       => 'status',
        'idx_source'       => 'source',
    ],
];

try {
    $pdo = Database::connect();
    $dbname = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $results = [];

    foreach ($plan as $table => $indexes) {
        // テーブル存在チェック
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1");
        $stmt->execute([$dbname, $table]);
        if (!$stmt->fetch()) {
            $results[$table] = ['error' => 'table not found'];
            continue;
        }

        // 既存カラム一覧
        $colStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
        $colStmt->execute([$dbname, $table]);
        $existingCols = array_map('strtolower', $colStmt->fetchAll(PDO::FETCH_COLUMN));

        // 既存インデックス
        $idxStmt = $pdo->prepare("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
        $idxStmt->execute([$dbname, $table]);
        $existingIdx = array_map('strtolower', $idxStmt->fetchAll(PDO::FETCH_COLUMN));

        $tableResult = ['added' => [], 'skipped_exists' => [], 'skipped_no_column' => [], 'errors' => []];

        foreach ($indexes as $idxName => $colName) {
            if (in_array(strtolower($colName), $existingCols, true) === false) {
                $tableResult['skipped_no_column'][] = "{$idxName} (column {$colName} not found)";
                continue;
            }
            if (in_array(strtolower($idxName), $existingIdx, true)) {
                $tableResult['skipped_exists'][] = $idxName;
                continue;
            }
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$idxName}` (`{$colName}`)");
                $tableResult['added'][] = $idxName;
            } catch (Exception $e) {
                $tableResult['errors'][] = "{$idxName}: " . $e->getMessage();
            }
        }
        $results[$table] = $tableResult;
    }

    echo json_encode([
        'success' => true,
        'results' => $results,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
