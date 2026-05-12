<?php
/**
 * マイグレーション: discount_approvals テーブルに不足カラムを追加
 * - レンタル期間 / 販売額 / Drive 添付情報 / 再申請・再送メタデータ
 *
 * 実行URL: /scripts/migrations/2026-05-01-discount-approval-columns.php
 * （admin のみ実行可能）
 */
require_once __DIR__ . '/../../config/config.php';
// auth.php は相対 require_once を含むため scripts/migrations/ から直接読めない。
// config.php で session_start 済み、isAdmin() も定義済み。

if (!isAdmin()) { http_response_code(403); echo json_encode(['error' => 'admin only']); exit; }

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connect();

    $cols = $pdo->query("SHOW COLUMNS FROM discount_approvals")->fetchAll(PDO::FETCH_COLUMN);

    $migrations = [
        'rental_period'        => "VARCHAR(255) DEFAULT NULL",
        'sales_amount'         => "VARCHAR(255) DEFAULT NULL",
        'drive_file_id'        => "VARCHAR(255) DEFAULT NULL",
        'drive_view_link'      => "TEXT DEFAULT NULL",
        'drive_download_link'  => "TEXT DEFAULT NULL",
        'drive_file_name'      => "VARCHAR(500) DEFAULT NULL",
        'original_name'        => "VARCHAR(500) DEFAULT NULL",
        'last_resent_at'       => "DATETIME DEFAULT NULL",
        'last_resent_by'       => "VARCHAR(255) DEFAULT NULL",
        'resend_count'         => "INT DEFAULT 0",
        'resubmitted_at'       => "DATETIME DEFAULT NULL",
        'resubmit_count'       => "INT DEFAULT 0",
    ];

    $added = [];
    foreach ($migrations as $colName => $colDef) {
        if (!in_array($colName, $cols, true)) {
            $pdo->exec("ALTER TABLE discount_approvals ADD COLUMN `{$colName}` {$colDef}");
            $added[] = $colName;
        }
    }

    echo json_encode([
        'success' => true,
        'added'   => $added ?: 'all columns already exist',
        'current_columns' => $pdo->query("SHOW COLUMNS FROM discount_approvals")->fetchAll(PDO::FETCH_COLUMN),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
