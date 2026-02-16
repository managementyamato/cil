<?php
/**
 * MF請求書CSVダウンロード
 * 損益管理ページからのCSVエクスポート
 */
require_once '../api/auth.php';

$data = getData();
$invoices = $data['mf_invoices'] ?? [];

// フィルターパラメータ取得
$yearMonth = $_GET['year_month'] ?? '';
$searchTag = $_GET['search_tag'] ?? '';

// 年月フィルター（sales_dateを使用 - finance.phpと同じ）
if (!empty($yearMonth) && $yearMonth !== 'all') {
    $invoices = array_filter($invoices, function($inv) use ($yearMonth) {
        $salesDate = $inv['sales_date'] ?? '';
        // フォーマット統一（Y/m/d → Y-m-d）
        $normalizedDate = str_replace('/', '-', $salesDate);
        return strpos($normalizedDate, $yearMonth) === 0;
    });
}

// タグ検索フィルター（finance.phpと同じロジック）
if (!empty($searchTag)) {
    // カンマ区切り → OR検索
    $orGroups = preg_split('/[,、]+/', trim($searchTag));

    $invoices = array_filter($invoices, function($inv) use ($orGroups) {
        foreach ($orGroups as $orGroup) {
            $orGroup = trim($orGroup);
            if (empty($orGroup)) continue;

            // スペースでAND条件に分割
            $searchKeywords = preg_split('/\s+/', $orGroup);
            $groupMatch = true;

            foreach ($searchKeywords as $keyword) {
                if (empty($keyword)) continue;

                $keywordMatch = false;
                $tags = $inv['tag_names'] ?? [];

                // タグ名で検索
                foreach ($tags as $tag) {
                    if (mb_stripos($tag, $keyword) !== false) {
                        $keywordMatch = true;
                        break;
                    }
                }

                // PJ番号、担当者名、請求書番号でも検索
                if (!$keywordMatch && !empty($inv['project_id']) && mb_stripos($inv['project_id'], $keyword) !== false) {
                    $keywordMatch = true;
                }
                if (!$keywordMatch && !empty($inv['assignee']) && mb_stripos($inv['assignee'], $keyword) !== false) {
                    $keywordMatch = true;
                }
                if (!$keywordMatch && !empty($inv['invoice_number']) && mb_stripos($inv['invoice_number'], $keyword) !== false) {
                    $keywordMatch = true;
                }

                if (!$keywordMatch) {
                    $groupMatch = false;
                    break;
                }
            }

            if ($groupMatch) {
                return true;
            }
        }
        return false;
    });
}

// ソート（売上日降順）
usort($invoices, function($a, $b) {
    $dateA = str_replace('/', '-', $a['sales_date'] ?? '');
    $dateB = str_replace('/', '-', $b['sales_date'] ?? '');
    return strcmp($dateB, $dateA);
});

// ファイル名生成
$filename = 'mf_invoices_';
if (!empty($yearMonth) && $yearMonth !== 'all') {
    $filename .= str_replace('-', '', $yearMonth) . '_';
} else {
    $filename .= 'all_';
}
$filename .= date('Ymd_His') . '.csv';

// CSVヘッダー
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM（Excel対応）
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// ヘッダー行
fputcsv($output, [
    '売上日',
    '請求日',
    '支払期限',
    '請求書番号',
    '取引先名',
    '件名',
    'PJ番号',
    '担当者',
    '税抜金額',
    '消費税',
    '税込金額',
    'ステータス',
    '入金日',
    'タグ',
    'MF請求書URL'
]);

// データ行
foreach ($invoices as $inv) {
    // タグを文字列化
    $tagStr = '';
    if (!empty($inv['tag_names'])) {
        $tagStr = implode(', ', $inv['tag_names']);
    }

    // MF請求書URL
    $mfUrl = '';
    if (!empty($inv['id'])) {
        $mfUrl = 'https://invoice.moneyforward.com/billings/' . $inv['id'];
    }

    fputcsv($output, [
        $inv['sales_date'] ?? '',
        $inv['billing_date'] ?? '',
        $inv['due_date'] ?? '',
        $inv['invoice_number'] ?? '',
        $inv['partner_name'] ?? '',
        $inv['title'] ?? '',
        $inv['project_id'] ?? '',
        $inv['assignee'] ?? '',
        $inv['subtotal'] ?? 0,
        $inv['total_tax'] ?? 0,
        $inv['total_price'] ?? 0,
        $inv['status'] ?? '',
        $inv['payment_date'] ?? '',
        $tagStr,
        $mfUrl
    ]);
}

fclose($output);

// 監査ログ
writeAuditLog('export', 'mf_invoices', 'MF請求書CSVエクスポート: ' . count($invoices) . '件', [
    'year_month' => $yearMonth,
    'search_tag' => $searchTag
]);
