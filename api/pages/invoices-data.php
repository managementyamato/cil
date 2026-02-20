<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';
require_once __DIR__ . '/../../api/mf-api.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET']
]);

// 管理者のみ
if (!isAdmin()) {
    errorResponse('管理者権限が必要です', 403);
}

set_time_limit(120);

$selectedMonth = $_GET['month'] ?? date('Y-m');
$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';

try {
    if (!MFApiClient::isConfigured()) {
        throw new Exception('MFクラウド請求書APIが設定されていません');
    }

    $client = new MFApiClient();
    $from = date('Y-m-01', strtotime($selectedMonth . '-01'));
    $to = date('Y-m-t', strtotime($selectedMonth . '-01'));

    $allInvoices = $client->getAllInvoices($from, $to, $forceRefresh);
    $cacheInfo = MFApiClient::getCacheInfo('invoices', ['from' => $from, 'to' => $to]);

    // タグ収集
    $allTagNames = [];
    foreach ($allInvoices as $invoice) {
        $tagNames = $invoice['tag_names'] ?? [];
        foreach ($tagNames as $tagName) {
            if ($tagName && !in_array($tagName, $allTagNames)) {
                $allTagNames[] = $tagName;
            }
        }
    }

    // フィルタ
    $filteredInvoices = array_filter($allInvoices, function($invoice) {
        $tagNames = $invoice['tag_names'] ?? [];
        foreach ($tagNames as $tagName) {
            if (mb_strpos($tagName, '指定フォーマット') !== false) {
                return true;
            }
        }
        return false;
    });

    $showingAll = empty($filteredInvoices);
    $invoices = $showingAll ? $allInvoices : $filteredInvoices;

    // 取引先ごとにグループ化
    $invoicesByPartner = [];
    foreach ($invoices as $invoice) {
        $partnerId = $invoice['partner_id'] ?? 'unknown';
        $partnerName = $invoice['partner_name'] ?? '（取引先不明）';

        if (!isset($invoicesByPartner[$partnerId])) {
            $invoicesByPartner[$partnerId] = [
                'partner_name' => $partnerName,
                'invoices' => []
            ];
        }

        // タグ分類
        $tagNames = $invoice['tag_names'] ?? [];
        $displayTags = [];
        $hasRecurringTag = false;

        foreach ($tagNames as $tagName) {
            if (strpos($tagName, '指定フォーマット') !== false) {
                $hasRecurringTag = true;
                $displayTags[] = ['name' => '指定フォーマット', 'type' => 'recurring'];
            } elseif (preg_match('/(20日〆|15日〆|末日〆|末〆)/', $tagName, $matches)) {
                $displayTags[] = ['name' => $matches[1], 'type' => 'closing'];
            } elseif (preg_match('/(メール|郵送|ＰＤＦ|PDF|紙)/', $tagName, $matches)) {
                $method = $matches[1] === 'ＰＤＦ' ? 'PDF' : $matches[1];
                $displayTags[] = ['name' => $method, 'type' => 'delivery'];
            } elseif (preg_match('/^[ぁ-んァ-ヶー一-龠]{2,4}$/', $tagName)) {
                $displayTags[] = ['name' => $tagName, 'type' => 'person'];
            }
        }

        $invoicesByPartner[$partnerId]['invoices'][] = [
            'id' => $invoice['id'] ?? '',
            'billing_number' => $invoice['billing_number'] ?? '-',
            'title' => $invoice['title'] ?? '-',
            'billing_date' => $invoice['billing_date'] ?? '-',
            'tags' => $displayTags,
            'has_recurring_tag' => $hasRecurringTag,
        ];
    }

    uasort($invoicesByPartner, function($a, $b) {
        return strcmp($a['partner_name'], $b['partner_name']);
    });

    foreach ($invoicesByPartner as &$partnerData) {
        usort($partnerData['invoices'], function($a, $b) {
            return strcmp($b['billing_date'] ?? '', $a['billing_date'] ?? '');
        });
    }
    unset($partnerData);

    sort($allTagNames);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data' => [
            'selectedMonth' => $selectedMonth,
            'invoicesByPartner' => $invoicesByPartner,
            'totalInvoices' => count($invoices),
            'totalPartners' => count($invoicesByPartner),
            'showingAll' => $showingAll,
            'allTagNames' => $allTagNames,
            'cacheInfo' => $cacheInfo,
            'forceRefresh' => $forceRefresh,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}
