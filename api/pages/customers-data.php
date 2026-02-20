<?php
/**
 * 顧客管理ページ用データAPI
 * Next.jsフロントエンドから呼び出される読み取り専用エンドポイント
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';
require_once __DIR__ . '/../../functions/encryption.php';
require_once __DIR__ . '/../../functions/soft-delete.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET'],
    'rateLimit' => 100
]);

// 編集権限チェック（customers.phpと同じ）
if (!canEdit()) {
    errorResponse('権限がありません', 403);
}

$data = getData();
decryptCustomerData($data);

$customers = filterDeleted($data['customers'] ?? []);

// MF請求書から取引先名を抽出
$mfPartners = [];
foreach ($data['mf_invoices'] ?? [] as $inv) {
    $name = trim($inv['partner_name'] ?? '');
    if (!empty($name) && mb_strlen($name) >= 3 && !in_array($name, $mfPartners)) {
        $mfPartners[] = $name;
    }
}
sort($mfPartners);

// MFに存在しない顧客を検出
$orphanCustomers = [];
foreach ($customers as $c) {
    $companyName = $c['companyName'] ?? '';
    if (!empty($companyName) && !in_array($companyName, $mfPartners)) {
        $orphanCustomers[] = [
            'id' => $c['id'],
            'companyName' => $c['companyName'] ?? '',
        ];
    }
}

// 検索フィルタ
$searchQuery = trim($_GET['q'] ?? '');
// 検索クエリの長さ制限（CPU枯渇攻撃防止）
if (mb_strlen($searchQuery) > 100) {
    errorResponse('検索クエリが長すぎます（最大100文字）', 400);
}
if (!empty($searchQuery)) {
    $customers = array_values(array_filter($customers, function($c) use ($searchQuery) {
        return stripos($c['companyName'] ?? '', $searchQuery) !== false ||
               stripos($c['contactPerson'] ?? '', $searchQuery) !== false ||
               stripos($c['notes'] ?? '', $searchQuery) !== false;
    }));
}

// ソート（会社名順）
usort($customers, function($a, $b) {
    return strcmp($a['companyName'] ?? '', $b['companyName'] ?? '');
});

// 営業所の削除済みを除外、不要な内部データを除去
foreach ($customers as &$c) {
    if (isset($c['branches']) && is_array($c['branches'])) {
        $c['branches'] = array_values(array_filter($c['branches'], function($b) {
            return !isset($b['deleted_at']);
        }));
    } else {
        $c['branches'] = [];
    }
}
unset($c);

$totalCount = count(filterDeleted($data['customers'] ?? []));
$lastSync = $data['customers_sync_timestamp'] ?? null;
$mfConfigured = class_exists('MFApiClient') && MFApiClient::isConfigured();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'customers' => array_values($customers),
        'totalCount' => $totalCount,
        'searchCount' => count($customers),
        'lastSync' => $lastSync,
        'mfConfigured' => $mfConfigured,
        'orphanCount' => count($orphanCustomers),
        'orphanCustomers' => $orphanCustomers,
        'userRole' => $_SESSION['user_role'] ?? 'sales',
    ]
], JSON_UNESCAPED_UNICODE);
