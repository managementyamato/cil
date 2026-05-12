<?php
/**
 * マイグレーション: invoice_requests テーブル新設
 * 営業部からの請求書作成依頼を管理
 *
 * 実行URL: /scripts/migrations/2026-05-08-invoice-requests.php
 */
require_once __DIR__ . '/../../config/config.php';

if (empty($_SESSION['user_email'])) { http_response_code(401); echo json_encode(['error' => 'login required']); exit; }
if (!isAdmin()) { http_response_code(403); echo json_encode(['error' => 'admin only']); exit; }

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connect();

    // テーブル存在チェック
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoice_requests' LIMIT 1");
    $stmt->execute();
    $exists = $stmt->fetch() !== false;

    if (!$exists) {
        $pdo->exec("
            CREATE TABLE `invoice_requests` (
                `id` VARCHAR(36) NOT NULL PRIMARY KEY,

                -- 取込元情報
                `source` VARCHAR(50) DEFAULT 'manual',
                `source_row_id` VARCHAR(255) DEFAULT NULL,
                `source_timestamp` DATETIME DEFAULT NULL,

                -- 基本情報
                `requester_name` VARCHAR(255) DEFAULT NULL,
                `attached_file_id` VARCHAR(255) DEFAULT NULL,
                `pj_number` VARCHAR(50) DEFAULT NULL,
                `subject` TEXT DEFAULT NULL,

                -- 請求先情報
                `partner_name` VARCHAR(255) DEFAULT NULL,
                `partner_department` VARCHAR(255) DEFAULT NULL,
                `mf_partner_id` VARCHAR(50) DEFAULT NULL,
                `billing_method_1` VARCHAR(50) DEFAULT NULL,
                `billing_method_2` VARCHAR(50) DEFAULT NULL,

                -- 依頼種別
                `request_type` VARCHAR(100) DEFAULT NULL,

                -- レンタル詳細
                `billing_start_date` DATE DEFAULT NULL,
                `payment_due_date` DATE DEFAULT NULL,
                `closing_day` VARCHAR(50) DEFAULT NULL,
                `rental_period` VARCHAR(50) DEFAULT NULL,
                `auto_renew` TINYINT(1) DEFAULT 0,
                `has_prorated` TINYINT(1) DEFAULT 0,

                -- 品目（JSON配列）
                `items` JSON DEFAULT NULL,

                -- メモ
                `notes` TEXT DEFAULT NULL,
                `special_notes` TEXT DEFAULT NULL,

                -- ステータスとMF連携
                `status` VARCHAR(50) DEFAULT 'pending',
                `mf_initial_billing_id` VARCHAR(50) DEFAULT NULL,
                `mf_initial_billing_url` TEXT DEFAULT NULL,
                `mf_recurring_billing_id` VARCHAR(50) DEFAULT NULL,
                `mf_sent_at` DATETIME DEFAULT NULL,
                `mf_sent_by` VARCHAR(255) DEFAULT NULL,
                `mf_error_message` TEXT DEFAULT NULL,

                -- メタデータ
                `created_at` DATETIME DEFAULT NULL,
                `updated_at` DATETIME DEFAULT NULL,
                `deleted_at` DATETIME DEFAULT NULL,
                `deleted_by` VARCHAR(255) DEFAULT NULL,

                INDEX `idx_status` (`status`),
                INDEX `idx_pj_number` (`pj_number`),
                INDEX `idx_source_row` (`source_row_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tableCreated = 'invoice_requests created';
    } else {
        $tableCreated = 'invoice_requests already exists';
    }

    echo json_encode([
        'success' => true,
        'phase' => 'invoice_requests テーブル作成',
        'result' => $tableCreated,
        'columns' => $pdo->query("SHOW COLUMNS FROM invoice_requests")->fetchAll(PDO::FETCH_COLUMN),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
