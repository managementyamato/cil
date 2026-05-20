<?php
/**
 * DB移行前整合性チェック
 * data.json と MySQL のレコード数を比較
 */
$base = file_exists(__DIR__ . '/config/config.php') ? __DIR__ : dirname(__DIR__);
require_once $base . '/config/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DB移行整合性チェック ===\n";
echo "DB_MODE: " . Database::getMode() . "\n\n";

// JSONからデータ読み込み（直接ファイルから）
$jsonData = json_decode(file_get_contents(DATA_FILE), true);
if (!$jsonData) {
    echo "ERROR: data.json の読み込みに失敗\n";
    exit(1);
}

// 主要エンティティの比較（Database::getEntity()でMySQL読み取り）
$entities = [
    'projects', 'troubles', 'customers', 'partners', 'employees',
    'manufacturers', 'invoices', 'mf_invoices', 'loans', 'repayments',
    'invoice_templates', 'invoice_excel_templates', 'scheduled_invoices',
    'tasks', 'announcements', 'memos',
    'slides', 'company_rules', 'contacts', 'leads',
    'morning_todos', 'weekly_reports', 'discount_approvals',
    'slide_confirmations', 'workflow_requests', 'reminders', 'deals',
];

echo str_pad("entity", 30) . str_pad("JSON", 8) . str_pad("MySQL", 8) . "status\n";
echo str_repeat("-", 60) . "\n";

$issues = [];
foreach ($entities as $entity) {
    $jsonCount = count($jsonData[$entity] ?? []);

    try {
        $dbData = Database::getEntity($entity);
        $dbCount = is_array($dbData) ? count($dbData) : 0;
    } catch (Exception $e) {
        $dbCount = 'ERR';
    }

    if ($dbCount === 'ERR') {
        $status = 'NG table error';
        $issues[] = $entity;
    } elseif ($jsonCount === $dbCount) {
        $status = 'OK';
    } elseif ($dbCount === 0 && $jsonCount > 0) {
        $status = 'NG DB empty';
        $issues[] = $entity;
    } else {
        $status = 'DIFF';
        $issues[] = $entity;
    }

    echo str_pad($entity, 30) . str_pad((string)$jsonCount, 8) . str_pad((string)$dbCount, 8) . $status . "\n";
}

// メタエンティティ確認
echo "\n--- meta entities (system_meta) ---\n";
$metaEntities = ['assignees', 'productCategories', 'settings', 'troubleResponders', 'areas', 'contact_masters'];
foreach ($metaEntities as $meta) {
    $jsonVal = $jsonData[$meta] ?? null;
    $jsonType = is_array($jsonVal) ? count($jsonVal) . ' items' : gettype($jsonVal);

    try {
        $dbVal = Database::getEntity($meta);
        $dbType = is_array($dbVal) ? count($dbVal) . ' items' : gettype($dbVal);
    } catch (Exception $e) {
        $dbType = 'ERR';
        $issues[] = $meta;
    }

    echo str_pad($meta, 30) . str_pad($jsonType, 15) . str_pad($dbType, 15) . "\n";
}

// スポットチェック: DB読み取りで重要フィールドが入っているか
echo "\n--- spot check (DB read) ---\n";
$checks = [
    'projects' => ['name', 'status'],
    'employees' => ['name'],
    'customers' => ['companyName'],
];
foreach ($checks as $entity => $fields) {
    try {
        $rows = Database::getEntity($entity);
        if (!empty($rows)) {
            $row = $rows[0];
            foreach ($fields as $f) {
                $val = $row[$f] ?? '(missing)';
                $ok = !empty($val) && $val !== '(missing)';
                echo "  {$entity}.{$f} = " . mb_substr((string)$val, 0, 40) . ($ok ? ' OK' : ' NG') . "\n";
            }
        } else {
            echo "  {$entity}: no records\n";
        }
    } catch (Exception $e) {
        echo "  {$entity}: ERROR " . $e->getMessage() . "\n";
    }
}

echo "\n=== result ===\n";
if (empty($issues)) {
    echo "OK: All entities match. Ready for DB_MODE=db.\n";
} else {
    echo "ISSUES: " . implode(', ', $issues) . "\n";
    echo "Fix these before switching.\n";
}
