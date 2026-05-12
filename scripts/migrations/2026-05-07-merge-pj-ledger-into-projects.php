<?php
/**
 * マイグレーション: pj-ledger.json の項目を projects テーブルに統合
 *
 * 実行URL: /scripts/migrations/2026-05-07-merge-pj-ledger-into-projects.php
 * （admin のみ実行可能・複数回実行しても安全）
 *
 * Phase 1: projects テーブルへ ledger 専用カラムを追加
 *          monthly_profits テーブルを新設
 */
require_once __DIR__ . '/../../config/config.php';
// 注: api/auth.php は相対パスで config.php を require するため、サブディレクトリから呼ぶと壊れる
// config.php が既にセッションを開始しているので isAdmin() が使える
if (empty($_SESSION['user_email'])) { http_response_code(401); echo json_encode(['error' => 'login required']); exit; }
if (!isAdmin()) { http_response_code(403); echo json_encode(['error' => 'admin only']); exit; }

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connect();

    // 既存カラム一覧
    $cols = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    // 追加するカラム（pj-ledger.json由来・既存と重複しないもの）
    $migrations = [
        // 識別・連番
        'no'                       => "INT DEFAULT NULL",
        'space'                    => "VARCHAR(50) DEFAULT NULL",
        'invoice_number'           => "VARCHAR(255) DEFAULT NULL",

        // 営業
        'sales_dept'               => "VARCHAR(255) DEFAULT NULL",
        'ya_person'                => "VARCHAR(255) DEFAULT NULL",
        'branch_name'              => "VARCHAR(255) DEFAULT NULL",
        'contact_email'            => "VARCHAR(255) DEFAULT NULL",

        // 製品（既存にない項目）
        'indoor_outdoor'           => "VARCHAR(50) DEFAULT NULL",
        'pitch'                    => "VARCHAR(50) DEFAULT NULL",
        'mic1'                     => "VARCHAR(255) DEFAULT NULL",
        'mic2'                     => "VARCHAR(255) DEFAULT NULL",
        'orientation'              => "VARCHAR(50) DEFAULT NULL",
        'color'                    => "VARCHAR(50) DEFAULT NULL",
        'router'                   => "VARCHAR(255) DEFAULT NULL",

        // 期間
        'construction_date'        => "DATE DEFAULT NULL",
        'end_date'                 => "DATE DEFAULT NULL",
        'warranty_end_date'        => "DATE DEFAULT NULL",
        'rental_days'              => "INT DEFAULT NULL",
        'sales_working_days'       => "INT DEFAULT NULL",
        'period_months'            => "DECIMAL(10,2) DEFAULT NULL",

        // 数量
        'horizontal_panels'        => "INT DEFAULT NULL",
        'vertical_panels'          => "INT DEFAULT NULL",
        'total_panels'             => "INT DEFAULT NULL",

        // 金額
        'total_sales_estimate'     => "DECIMAL(15,2) DEFAULT NULL",
        'actual_invoice_amount'    => "DECIMAL(15,2) DEFAULT NULL",
        'monthly_rental_sales'     => "DECIMAL(15,2) DEFAULT NULL",
        'additional_sales'         => "DECIMAL(15,2) DEFAULT NULL",
        'initial_cost'             => "DECIMAL(15,2) DEFAULT NULL",
        'discount_amount'          => "DECIMAL(15,2) DEFAULT NULL",
        'additional_material_cost' => "DECIMAL(15,2) DEFAULT NULL",
        'support_material_cost'    => "DECIMAL(15,2) DEFAULT NULL",
        'expenses'                 => "DECIMAL(15,2) DEFAULT NULL",
        'profit'                   => "DECIMAL(15,2) DEFAULT NULL",

        // 分析
        'deviation_rate'           => "DECIMAL(10,6) DEFAULT NULL",
        'profit_rate'              => "DECIMAL(10,6) DEFAULT NULL",
        'tech_cost_ratio_estimate' => "DECIMAL(10,6) DEFAULT NULL",
        'tech_cost_ratio_actual'   => "DECIMAL(10,6) DEFAULT NULL",

        // メモ
        'remarks'                  => "TEXT DEFAULT NULL",

        // 旧idの保持（マイグレーション後の追跡用・最終的に不要なら削除可）
        'legacy_ledger_id'         => "VARCHAR(255) DEFAULT NULL",
    ];

    $added = [];
    foreach ($migrations as $colName => $colDef) {
        if (!in_array($colName, $cols, true)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN `{$colName}` {$colDef}");
            $added[] = $colName;
        }
    }

    // monthly_profits テーブル（月次利益データ）
    $mpExists = false;
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'monthly_profits' LIMIT 1");
        $stmt->execute();
        $mpExists = $stmt->fetch() !== false;
    } catch (Exception $e) { $mpExists = false; }

    if (!$mpExists) {
        $pdo->exec("
            CREATE TABLE `monthly_profits` (
                `id` VARCHAR(36) NOT NULL PRIMARY KEY,
                `project_id` VARCHAR(255) DEFAULT NULL,
                `month` VARCHAR(20) DEFAULT NULL,
                `amount` DECIMAL(15,2) DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL,
                `updated_at` DATETIME DEFAULT NULL,
                `deleted_at` DATETIME DEFAULT NULL,
                `deleted_by` VARCHAR(255) DEFAULT NULL,
                INDEX `idx_project_id` (`project_id`),
                INDEX `idx_month` (`month`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $tableCreated = 'monthly_profits';
    } else {
        $tableCreated = 'monthly_profits already exists';
    }

    echo json_encode([
        'success' => true,
        'phase'   => 'Phase 1: ALTER TABLE projects + CREATE monthly_profits',
        'added_columns'   => $added ?: 'all columns already exist',
        'table_created'   => $tableCreated,
        'current_columns' => $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN),
        'next_step' => 'Phase 2: /scripts/migrations/2026-05-07-merge-pj-ledger-data.php を実行してデータを移行してください',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
