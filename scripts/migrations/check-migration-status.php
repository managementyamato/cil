<?php
/**
 * マイグレーション実行状況を一括確認
 *
 * 実行URL: /scripts/migrations/check-migration-status.php
 * （admin のみ）
 */
require_once __DIR__ . '/../../config/config.php';

// auth.php は相対 require_once を含むため scripts/migrations/ から直接読めない。
// 必要最小限のセッションチェックをここで行う。
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_email'])) { http_response_code(401); echo json_encode(['error' => 'login required']); exit; }
if (($_SESSION['user_role'] ?? '') !== 'admin') { http_response_code(403); echo json_encode(['error' => 'admin only']); exit; }

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connect();
    $result = ['db_mode' => Database::getMode()];

    // --- 1. 2026-05-01: discount_approvals カラム追加 ---
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM discount_approvals")->fetchAll(PDO::FETCH_COLUMN);
        $expected = ['rental_period','sales_amount','drive_file_id','drive_view_link','drive_download_link',
                     'drive_file_name','original_name','last_resent_at','last_resent_by','resend_count',
                     'resubmitted_at','resubmit_count'];
        $missing = array_diff($expected, $cols);
        $result['2026-05-01-discount-approval-columns'] = [
            'status'  => empty($missing) ? 'DONE' : 'MISSING',
            'missing_columns' => array_values($missing),
            'total_columns' => count($cols),
        ];
    } catch (Exception $e) {
        $result['2026-05-01-discount-approval-columns'] = ['error' => $e->getMessage()];
    }

    // --- 2. 2026-05-07: pj_ledger を projects に統合 ---
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);
        // 想定カラム（migrate-pj-ledger-into-projects で追加）
        $ledgerCols = ['initial_cost','discount_amount','monthly_rental_sales','additional_sales',
                       'expenses','profit','shipping_cost'];
        $foundLedger = array_intersect($ledgerCols, $cols);
        $result['2026-05-07-merge-pj-ledger-into-projects'] = [
            'status' => count($foundLedger) === count($ledgerCols) ? 'DONE' : 'PARTIAL_OR_MISSING',
            'found_ledger_columns' => array_values($foundLedger),
            'expected_ledger_columns' => $ledgerCols,
        ];
    } catch (Exception $e) {
        $result['2026-05-07-merge-pj-ledger-into-projects'] = ['error' => $e->getMessage()];
    }

    // --- 3. 2026-05-08: invoice_requests テーブル ---
    try {
        $tables = $pdo->query("SHOW TABLES LIKE 'invoice_requests'")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            $result['2026-05-08-invoice-requests'] = ['status' => 'MISSING', 'note' => 'invoice_requests テーブルが存在しません'];
        } else {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM invoice_requests")->fetchColumn();
            $result['2026-05-08-invoice-requests'] = ['status' => 'DONE', 'row_count' => $count];
        }
    } catch (Exception $e) {
        $result['2026-05-08-invoice-requests'] = ['error' => $e->getMessage()];
    }

    // --- 4. 2026-05-11: インデックス追加 ---
    try {
        $indexes = [];
        $tablesToCheck = ['projects','customers','mf_invoices','discount_approvals','troubles'];
        foreach ($tablesToCheck as $t) {
            try {
                $stmt = $pdo->query("SHOW INDEXES FROM `$t`");
                $idx = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $indexes[$t] = array_values(array_unique(array_column($idx, 'Key_name')));
            } catch (Exception $e) {
                $indexes[$t] = 'ERROR: ' . $e->getMessage();
            }
        }
        $result['2026-05-11-add-indexes'] = ['indexes_per_table' => $indexes, 'status' => 'CHECK_MANUALLY'];
    } catch (Exception $e) {
        $result['2026-05-11-add-indexes'] = ['error' => $e->getMessage()];
    }

    // --- 5. 2026-05-11: document_summaries 削除 ---
    try {
        $tables = $pdo->query("SHOW TABLES LIKE 'document_summaries'")->fetchAll(PDO::FETCH_COLUMN);
        $result['2026-05-11-drop-document-summaries'] = [
            'status' => empty($tables) ? 'DONE' : 'STILL_EXISTS',
            'note'   => empty($tables) ? 'document_summaries は存在しません（削除済み）' : 'document_summaries テーブルがまだ存在'
        ];
    } catch (Exception $e) {
        $result['2026-05-11-drop-document-summaries'] = ['error' => $e->getMessage()];
    }

    // --- 全体テーブル一覧 ---
    try {
        $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $result['_all_tables'] = $allTables;
        $result['_total_tables'] = count($allTables);
    } catch (Exception $e) {
        $result['_all_tables_error'] = $e->getMessage();
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
