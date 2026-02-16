<?php
/**
 * 横断検索API
 * 案件・顧客・従業員・トラブルを横断的に検索する
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'rateLimit' => 100,
    'allowedMethods' => ['GET']
]);

$query = trim($_GET['q'] ?? '');
$limit = min((int)($_GET['limit'] ?? 20), 100);

if (empty($query) || mb_strlen($query) < 2) {
    successResponse(['results' => [], 'total' => 0]);
}

$data = getData();
$results = [];
$queryLower = mb_strtolower($query);

// 案件を検索
if (isset($data['projects'])) {
    foreach ($data['projects'] as $pj) {
        if (!empty($pj['deleted_at'])) continue;
        $searchFields = [
            $pj['id'] ?? '',
            $pj['name'] ?? '',
            $pj['customer_name'] ?? '',
            $pj['sales_assignee'] ?? '',
            $pj['dealer_name'] ?? '',
            $pj['address'] ?? '',
            $pj['product_name'] ?? '',
            $pj['memo'] ?? '',
        ];
        $combined = mb_strtolower(implode(' ', $searchFields));
        if (mb_strpos($combined, $queryLower) !== false) {
            $results[] = [
                'type' => 'project',
                'type_label' => '案件',
                'id' => $pj['id'] ?? '',
                'title' => ($pj['id'] ?? '') . ' ' . ($pj['name'] ?? ''),
                'subtitle' => $pj['customer_name'] ?? '',
                'status' => $pj['status'] ?? '',
                'url' => '/pages/master.php?search_pj=' . urlencode($pj['id'] ?? ''),
                'updated_at' => $pj['updated_at'] ?? $pj['created_at'] ?? '',
            ];
        }
    }
}

// トラブルを検索
if (isset($data['troubles'])) {
    foreach ($data['troubles'] as $t) {
        if (!empty($t['deleted_at'])) continue;
        $searchFields = [
            $t['pj_number'] ?? $t['project_name'] ?? '',
            $t['title'] ?? $t['trouble_content'] ?? '',
            $t['description'] ?? $t['response_content'] ?? '',
            $t['responder'] ?? '',
            $t['reporter'] ?? '',
        ];
        $combined = mb_strtolower(implode(' ', $searchFields));
        if (mb_strpos($combined, $queryLower) !== false) {
            $results[] = [
                'type' => 'trouble',
                'type_label' => 'トラブル',
                'id' => $t['id'] ?? '',
                'title' => $t['title'] ?? $t['trouble_content'] ?? '',
                'subtitle' => $t['pj_number'] ?? $t['project_name'] ?? '',
                'status' => $t['status'] ?? '',
                'url' => '/pages/troubles.php?search=' . urlencode($query),
                'updated_at' => $t['updated_at'] ?? $t['created_at'] ?? '',
            ];
        }
    }
}

// 顧客を検索
if (isset($data['customers'])) {
    foreach ($data['customers'] as $c) {
        if (!empty($c['deleted_at'])) continue;
        $searchFields = [
            $c['companyName'] ?? '',
            $c['contact'] ?? '',
            $c['notes'] ?? '',
        ];
        // エイリアスも検索
        if (!empty($c['aliases']) && is_array($c['aliases'])) {
            $searchFields = array_merge($searchFields, $c['aliases']);
        }
        $combined = mb_strtolower(implode(' ', $searchFields));
        if (mb_strpos($combined, $queryLower) !== false) {
            $results[] = [
                'type' => 'customer',
                'type_label' => '顧客',
                'id' => $c['id'] ?? '',
                'title' => $c['companyName'] ?? '',
                'subtitle' => $c['contact'] ?? '',
                'status' => '',
                'url' => '/pages/customers.php',
                'updated_at' => $c['updated_at'] ?? $c['created_at'] ?? '',
            ];
        }
    }
}

// 従業員を検索
if (isset($data['employees'])) {
    foreach ($data['employees'] as $e) {
        if (!empty($e['deleted_at'])) continue;
        $searchFields = [
            $e['name'] ?? '',
            $e['email'] ?? '',
            $e['department'] ?? '',
        ];
        $combined = mb_strtolower(implode(' ', $searchFields));
        if (mb_strpos($combined, $queryLower) !== false) {
            $results[] = [
                'type' => 'employee',
                'type_label' => '従業員',
                'id' => $e['id'] ?? '',
                'title' => $e['name'] ?? '',
                'subtitle' => $e['department'] ?? '',
                'status' => '',
                'url' => '/pages/employees.php',
                'updated_at' => $e['updated_at'] ?? $e['created_at'] ?? '',
            ];
        }
    }
}

// 結果を制限
$total = count($results);
$results = array_slice($results, 0, $limit);

// カテゴリ別件数
$counts = [];
foreach (['project', 'trouble', 'customer', 'employee'] as $type) {
    $counts[$type] = count(array_filter($results, fn($r) => $r['type'] === $type));
}

successResponse([
    'results' => $results,
    'total' => $total,
    'counts' => $counts,
    'query' => $query,
]);
