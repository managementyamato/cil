<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET']
]);

// 編集権限チェック
if (!canEdit()) {
    errorResponse('権限がありません', 403);
}

$data = getData();

// 請求書データ
$invoices = $data['mf_invoices'] ?? [];

// 月別集計
$monthlyTotals = [];
$customerTotals = [];
$assigneeTotals = [];
$availableYearMonths = [];

foreach ($invoices as $invoice) {
    $salesDate = $invoice['sales_date'] ?? '';
    if ($salesDate && preg_match('/^(\d{4})[-\/](\d{2})/', $salesDate, $matches)) {
        $yearMonth = $matches[1] . '-' . $matches[2];
        if (!in_array($yearMonth, $availableYearMonths)) {
            $availableYearMonths[] = $yearMonth;
        }
        if (!isset($monthlyTotals[$yearMonth])) {
            $monthlyTotals[$yearMonth] = ['sales' => 0, 'count' => 0];
        }
        $monthlyTotals[$yearMonth]['sales'] += floatval($invoice['total_amount'] ?? 0);
        $monthlyTotals[$yearMonth]['count']++;
    }

    // 顧客別集計
    $customerName = $invoice['partner_name'] ?? '不明';
    if (!isset($customerTotals[$customerName])) {
        $customerTotals[$customerName] = ['total' => 0, 'count' => 0];
    }
    $customerTotals[$customerName]['total'] += floatval($invoice['total_amount'] ?? 0);
    $customerTotals[$customerName]['count']++;

    // 担当者別集計
    $assignee = $invoice['assignee'] ?? '未設定';
    if (!isset($assigneeTotals[$assignee])) {
        $assigneeTotals[$assignee] = ['total' => 0, 'count' => 0];
    }
    $assigneeTotals[$assignee]['total'] += floatval($invoice['total_amount'] ?? 0);
    $assigneeTotals[$assignee]['count']++;
}

rsort($availableYearMonths);
krsort($monthlyTotals);

// 顧客別売上上位10社
uasort($customerTotals, function($a, $b) {
    return $b['total'] <=> $a['total'];
});
$topCustomers = array_slice($customerTotals, 0, 10, true);

// 担当者別集計をソート
uasort($assigneeTotals, function($a, $b) {
    return $b['total'] <=> $a['total'];
});

// 直近6ヶ月の月次比較
$monthlyComparison = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $monthlyComparison[$m] = $monthlyTotals[$m] ?? ['sales' => 0, 'count' => 0];
}

// 請求漏れチェック
$invoiceLeaks = [];
$invoicedCustomers = [];
foreach ($invoices as $inv) {
    $partner = $inv['partner_name'] ?? $inv['customer_name'] ?? '';
    if (!empty($partner)) {
        $invoicedCustomers[$partner] = true;
    }
}
foreach ($data['projects'] ?? [] as $pj) {
    $status = $pj['status'] ?? '';
    if (!in_array($status, ['設置済', '完了'])) continue;
    $customer = $pj['customer_name'] ?? '';
    $hasInvoice = false;
    foreach ($invoices as $inv) {
        $invCustomer = $inv['partner_name'] ?? $inv['customer_name'] ?? '';
        $invSubject = $inv['title'] ?? $inv['subject'] ?? '';
        if ($invCustomer === $customer || stripos($invSubject, $pj['id'] ?? '') !== false) {
            $hasInvoice = true;
            break;
        }
    }
    if (!$hasInvoice) {
        $invoiceLeaks[] = [
            'id' => $pj['id'] ?? '',
            'name' => $pj['name'] ?? '',
            'customer_name' => $customer,
            'status' => $status,
        ];
    }
}

// 請求書一覧（最新50件）
$sortedInvoices = $invoices;
usort($sortedInvoices, function($a, $b) {
    return strcmp($b['billing_number'] ?? '', $a['billing_number'] ?? '');
});
$recentInvoices = array_map(function($inv) {
    return [
        'id' => $inv['id'] ?? '',
        'billing_number' => $inv['billing_number'] ?? '',
        'partner_name' => $inv['partner_name'] ?? '',
        'title' => $inv['title'] ?? '',
        'sales_date' => $inv['sales_date'] ?? '',
        'total_amount' => floatval($inv['total_amount'] ?? 0),
        'subtotal' => floatval($inv['subtotal'] ?? 0),
        'tax' => floatval($inv['tax'] ?? 0),
        'project_id' => $inv['project_id'] ?? '',
        'assignee' => $inv['assignee'] ?? '',
        'tag_names' => $inv['tag_names'] ?? [],
    ];
}, array_slice($sortedInvoices, 0, 200));

// 最終同期日時
$syncTimestamp = $data['mf_sync_timestamp'] ?? null;

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'invoices' => $recentInvoices,
        'availableYearMonths' => $availableYearMonths,
        'monthlyComparison' => $monthlyComparison,
        'topCustomers' => $topCustomers,
        'assigneeTotals' => $assigneeTotals,
        'invoiceLeaks' => array_slice($invoiceLeaks, 0, 20),
        'syncTimestamp' => $syncTimestamp,
        'totalInvoiceCount' => count($invoices),
    ]
], JSON_UNESCAPED_UNICODE);
