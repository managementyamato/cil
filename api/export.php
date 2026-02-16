<?php
/**
 * データエクスポートAPI
 * 案件・トラブル・顧客・従業員をCSV/JSON形式でエクスポートする
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

// CSRFは不要（GETダウンロード）
initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'rateLimit' => 30,
    'allowedMethods' => ['GET']
]);

$entity = trim($_GET['entity'] ?? '');
$format = trim($_GET['format'] ?? 'csv');
$filters = $_GET['filters'] ?? '';

$validEntities = ['projects', 'troubles', 'customers', 'employees'];
if (!in_array($entity, $validEntities)) {
    errorResponse('無効なエンティティです。使用可能: ' . implode(', ', $validEntities), 400);
}

if (!in_array($format, ['csv', 'json'])) {
    errorResponse('無効なフォーマットです。csv または json を指定してください', 400);
}

$data = getData();
$items = $data[$entity] ?? [];

// 論理削除済みを除外
$items = array_filter($items, function($item) {
    return empty($item['deleted_at']);
});
$items = array_values($items);

// フィルター適用
if (!empty($filters)) {
    $filterPairs = explode(',', $filters);
    foreach ($filterPairs as $pair) {
        $parts = explode(':', $pair, 2);
        if (count($parts) === 2) {
            $field = trim($parts[0]);
            $value = trim($parts[1]);
            $items = array_filter($items, function($item) use ($field, $value) {
                return isset($item[$field]) && mb_strpos(mb_strtolower($item[$field]), mb_strtolower($value)) !== false;
            });
        }
    }
    $items = array_values($items);
}

// エンティティ別のカラム定義
$columnDefs = [
    'projects' => [
        'id' => 'P番号',
        'name' => '現場名',
        'customer_name' => '顧客名',
        'sales_assignee' => '営業担当',
        'status' => 'ステータス',
        'transaction_type' => '取引形態',
        'occurrence_date' => '案件発生日',
        'contract_date' => '成約日',
        'install_schedule_date' => '設置予定日',
        'install_complete_date' => '設置完了日',
        'product_category' => '商品カテゴリ',
        'product_name' => '商品名',
        'prefecture' => '都道府県',
        'address' => '住所',
        'install_partner' => '設置パートナー',
        'memo' => 'メモ',
    ],
    'troubles' => [
        'id' => 'ID',
        'pj_number' => 'P番号',
        'date' => '日付',
        'title' => 'タイトル',
        'trouble_content' => 'トラブル内容',
        'response_content' => '対応内容',
        'reporter' => '記入者',
        'responder' => '対応者',
        'status' => '状態',
        'customer_name' => '顧客名',
    ],
    'customers' => [
        'id' => 'ID',
        'companyName' => '会社名',
        'contact' => '担当者',
        'notes' => '備考',
    ],
    'employees' => [
        'id' => 'ID',
        'name' => '名前',
        'email' => 'メール',
        'department' => '部署',
        'role' => '権限',
    ],
];

$columns = $columnDefs[$entity] ?? [];

// 監査ログに記録
$count = count($items);
writeAuditLog('export', $entity, "{$entity}をエクスポート ({$format}, {$count}件)", [
    'format' => $format,
    'count' => count($items),
    'filters' => $filters,
]);

// エクスポート名
$entityNames = [
    'projects' => '案件',
    'troubles' => 'トラブル',
    'customers' => '顧客',
    'employees' => '従業員',
];
$entityName = $entityNames[$entity] ?? $entity;
$filename = $entityName . '_' . date('Y-m-d_His');

if ($format === 'json') {
    // JSON エクスポート
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.json\"");

    $exportData = array_map(function($item) use ($columns) {
        $row = [];
        foreach ($columns as $key => $label) {
            $row[$label] = $item[$key] ?? '';
        }
        return $row;
    }, $items);

    echo json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// CSV エクスポート
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");

// BOM (UTF-8 with BOM for Excel compatibility)
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// ヘッダー行
fputcsv($output, array_values($columns));

// データ行
foreach ($items as $item) {
    $row = [];
    foreach (array_keys($columns) as $key) {
        $value = $item[$key] ?? '';
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        $row[] = $value;
    }
    fputcsv($output, $row);
}

fclose($output);
exit;
