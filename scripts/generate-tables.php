<?php
/**
 * data.json の実データから CREATE TABLE SQL を自動生成
 *
 * Usage: php scripts/generate-tables.php
 */
$data = json_decode(file_get_contents(dirname(__DIR__) . '/backups/20260406_094130/data.json'), true);

$tableEntities = [
    'projects', 'troubles', 'customers', 'partners', 'employees',
    'manufacturers', 'invoices', 'mf_invoices', 'loans', 'repayments',
    'invoice_templates', 'invoice_excel_templates', 'scheduled_invoices',
    'tasks', 'announcements', 'memos',
    'slides', 'company_rules', 'contacts', 'leads',
    'morning_todos', 'weekly_reports', 'discount_approvals',
    'slide_confirmations',
    'workflow_requests', 'reminders', 'deals',
];

$jsonCols = [
    'projects' => ['invoice_ids'],
    'customers' => ['aliases', 'branches'],
    'tasks' => ['subtasks', 'mentions'],
    'announcements' => ['read_by'],
    'memos' => ['tags'],
    'slides' => ['required_for'],
    'weekly_reports' => ['private_recipients'],
    'workflow_requests' => ['approvers'],
];

$boolCols = [
    'announcements' => ['pinned'],
    'memos' => ['pinned'],
];

$dateCols = ['created_at', 'updated_at', 'deleted_at', 'confirmed_at', 'submitted_at',
    'reviewed_at', 'synced_at', 'last_read_at', 'email_token_expires_at', 'email_token_used_at'];
$dateOnlyCols = ['due_date', 'deadline', 'occurrence_date', 'start_date', 'end_date',
    'billing_date', 'issue_date', 'sales_date', 'closing_date', 'payment_date',
    'join_date', 'leave_date', 'expires_at', 'meeting_date', 'week_start', 'week_end'];
$textCols = ['content', 'html_template', 'description', 'memo', 'notes', 'note',
    'trouble_content', 'response_content', 'prevention_notes', 'reason', 'review_comment',
    'private_message', 'chat_url', 'google_docs_url', 'pdf_url', 'address',
    'shipping_address', 'mapping', 'tag_names'];
$decimalCols = ['amount', 'principal', 'balance', 'original_amount', 'discount_amount',
    'subtotal', 'tax', 'total_amount', 'principal_amount', 'interest_amount'];

$softDeleteCols = ['deleted_at', 'deleted_by'];

$sql = "-- ============================================================\n";
$sql .= "-- yamato_mgt テーブル定義（data.json から自動生成）\n";
$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- カラム名は data.json のキー名と完全一致\n";
$sql .= "-- ============================================================\n\n";
$sql .= "SET NAMES utf8mb4;\nSET CHARACTER SET utf8mb4;\n\n";

// system_meta
$sql .= "-- system_meta: キーバリューストア\n";
$sql .= "DROP TABLE IF EXISTS `system_meta`;\n";
$sql .= "CREATE TABLE `system_meta` (\n";
$sql .= "    `meta_key`    VARCHAR(100) NOT NULL PRIMARY KEY,\n";
$sql .= "    `meta_value`  LONGTEXT DEFAULT NULL,\n";
$sql .= "    `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n";
$sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

foreach ($tableEntities as $entity) {
    $allKeys = ['id' => true];
    if (isset($data[$entity]) && is_array($data[$entity])) {
        foreach ($data[$entity] as $row) {
            if (is_array($row)) {
                foreach (array_keys($row) as $k) $allKeys[$k] = true;
            }
        }
    }
    foreach ($softDeleteCols as $sc) $allKeys[$sc] = true;
    $entityJsonCols = $jsonCols[$entity] ?? [];
    $entityBoolCols = $boolCols[$entity] ?? [];
    foreach ($entityJsonCols as $jc) $allKeys[$jc] = true;
    foreach ($entityBoolCols as $bc) $allKeys[$bc] = true;

    $keys = array_keys($allKeys);

    $sql .= "-- {$entity}\n";
    $sql .= "DROP TABLE IF EXISTS `{$entity}`;\n";
    $sql .= "CREATE TABLE `{$entity}` (\n";

    $lines = [];
    $indexes = [];

    foreach ($keys as $key) {
        if ($key === 'id') {
            $lines[] = "    `id` VARCHAR(36) NOT NULL PRIMARY KEY";
            continue;
        }

        if (in_array($key, $entityJsonCols)) {
            $lines[] = "    `{$key}` JSON DEFAULT NULL";
        } elseif (in_array($key, $entityBoolCols)) {
            $lines[] = "    `{$key}` TINYINT(1) DEFAULT 0";
        } elseif (in_array($key, $dateCols)) {
            $lines[] = "    `{$key}` DATETIME DEFAULT NULL";
        } elseif (in_array($key, $dateOnlyCols)) {
            $lines[] = "    `{$key}` DATE DEFAULT NULL";
        } elseif (in_array($key, $textCols)) {
            $lines[] = "    `{$key}` TEXT DEFAULT NULL";
        } elseif (strpos($key, 'sec_') === 0) {
            $lines[] = "    `{$key}` MEDIUMTEXT DEFAULT NULL";
        } elseif (in_array($key, $decimalCols)) {
            $lines[] = "    `{$key}` DECIMAL(15,2) DEFAULT NULL";
        } elseif ($key === 'interest_rate') {
            $lines[] = "    `{$key}` DECIMAL(5,4) DEFAULT NULL";
        } elseif ($key === 'chapter_number' || $key === 'sort_order') {
            $lines[] = "    `{$key}` INT DEFAULT NULL";
        } else {
            $lines[] = "    `{$key}` VARCHAR(255) DEFAULT NULL";
        }

        // インデックス
        if ($key === 'status') $indexes[] = "    INDEX `idx_status` (`status`)";
        if ($key === 'email' && $entity === 'employees') $indexes[] = "    INDEX `idx_email` (`email`)";
        if ($key === 'user_email') $indexes[] = "    INDEX `idx_user` (`user_email`)";
        if ($key === 'slide_id') $indexes[] = "    INDEX `idx_slide` (`slide_id`)";
        if ($key === 'category') $indexes[] = "    INDEX `idx_category` (`category`)";
        if ($key === 'companyName') $indexes[] = "    INDEX `idx_company` (`companyName`)";
    }

    if ($entity === 'slide_confirmations') {
        $indexes[] = "    UNIQUE KEY `uq_slide_user` (`slide_id`, `user_email`)";
    }

    $allLines = array_merge($lines, $indexes);
    $sql .= implode(",\n", $allLines) . "\n";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
}

file_put_contents(dirname(__DIR__) . '/scripts/create-tables.sql', $sql);
echo "Generated: scripts/create-tables.sql (" . strlen($sql) . " bytes)\n";

// 検証
echo "\n=== 検証 ===\n";
$mismatches = 0;
foreach ($tableEntities as $entity) {
    if (!isset($data[$entity]) || !is_array($data[$entity]) || empty($data[$entity])) continue;
    $allKeys = [];
    foreach ($data[$entity] as $row) {
        if (is_array($row)) {
            foreach (array_keys($row) as $k) $allKeys[$k] = true;
        }
    }
    foreach (array_keys($allKeys) as $key) {
        if (strpos($sql, "`{$key}`") === false) {
            echo "  MISSING: {$entity}.{$key}\n";
            $mismatches++;
        }
    }
}
if ($mismatches === 0) echo "  OK: 全キーがSQL定義に含まれています\n";
