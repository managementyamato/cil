<?php
/**
 * 請求書データ CSV出力
 */

require_once __DIR__ . '/../api/auth.php';

// 編集権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

// データ読み込み
$data = getData();

// パラメータ取得
$selectedYearMonth = $_GET['year_month'] ?? '';
$searchTag = trim($_GET['search_tag'] ?? '');

// フィルタされた請求書データを取得
$filteredInvoices = array();

if (isset($data['mf_invoices']) && !empty($data['mf_invoices'])) {
    foreach ($data['mf_invoices'] as $invoice) {
        $salesDate = $invoice['sales_date'] ?? '';

        // 年月フィルタ
        $yearMonthMatch = true;
        if ($selectedYearMonth && $salesDate) {
            $normalizedDate = str_replace('/', '-', $salesDate);
            $yearMonthMatch = (strpos($normalizedDate, $selectedYearMonth) === 0);
        }

        // タグ検索フィルタ（スペース区切りで複数タグ検索対応）
        $tagMatch = true;
        if (!empty($searchTag)) {
            // スペースで区切って複数のキーワードに分割
            $searchKeywords = preg_split('/\s+/', trim($searchTag));
            $tagMatch = true;

            // 全てのキーワードが一致する必要がある（AND検索）
            foreach ($searchKeywords as $keyword) {
                if (empty($keyword)) continue;

                $keywordMatch = false;
                $tags = $invoice['tag_names'] ?? array();

                // タグ名で検索
                foreach ($tags as $tag) {
                    if (mb_stripos($tag, $keyword) !== false) {
                        $keywordMatch = true;
                        break;
                    }
                }

                // PJ番号、担当者名でも検索
                if (!$keywordMatch) {
                    if (!empty($invoice['project_id']) && mb_stripos($invoice['project_id'], $keyword) !== false) {
                        $keywordMatch = true;
                    }
                }
                if (!$keywordMatch) {
                    if (!empty($invoice['assignee']) && mb_stripos($invoice['assignee'], $keyword) !== false) {
                        $keywordMatch = true;
                    }
                }

                // 1つでもキーワードが見つからなければ、この請求書は除外
                if (!$keywordMatch) {
                    $tagMatch = false;
                    break;
                }
            }
        }

        // フィルタが一致した場合のみ追加
        if ($yearMonthMatch && $tagMatch) {
            $filteredInvoices[] = $invoice;
        }
    }
}

// ファイル名を生成
$filename = 'invoices';
if ($selectedYearMonth) {
    $filename .= '_' . $selectedYearMonth;
}
if ($searchTag) {
    $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $searchTag);
}
$filename .= '_' . date('Ymd') . '.csv';

// CSVヘッダーを設定
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM出力（Excelで文字化けしないように）
echo "\xEF\xBB\xBF";

// CSVヘッダー行
$headers = [
    'P番号',
    '顧客名',
    '担当者',
    '請求書番号',
    '案件名',
    '請求日',
    '支払期限',
    '売上日',
    '合計金額',
    '小計',
    '消費税',
    'ステータス',
    '郵送ステータス',
    'タグ',
    'メモ',
    '備考'
];
echo implode(',', $headers) . "\n";

// データ行
foreach ($filteredInvoices as $invoice) {
    // タグを結合
    $allTags = [];
    if (!empty($invoice['project_id'])) {
        $allTags[] = $invoice['project_id'];
    }
    if (!empty($invoice['assignee'])) {
        $allTags[] = $invoice['assignee'];
    }
    if (!empty($invoice['tag_names']) && is_array($invoice['tag_names'])) {
        foreach ($invoice['tag_names'] as $tag) {
            if ($tag !== $invoice['project_id'] && $tag !== $invoice['assignee']) {
                $allTags[] = $tag;
            }
        }
    }
    $tagsStr = implode(', ', $allTags);

    $csvRow = [
        $invoice['project_id'] ?? '',
        $invoice['partner_name'] ?? '',
        $invoice['assignee'] ?? '',
        $invoice['billing_number'] ?? '',
        $invoice['title'] ?? '',
        $invoice['billing_date'] ?? '',
        $invoice['due_date'] ?? '',
        $invoice['sales_date'] ?? '',
        $invoice['total_amount'] ?? 0,
        $invoice['subtotal'] ?? 0,
        $invoice['tax'] ?? 0,
        $invoice['payment_status'] ?? '',
        $invoice['posting_status'] ?? '',
        $tagsStr,
        $invoice['memo'] ?? '',
        $invoice['note'] ?? ''
    ];

    // CSVエスケープ
    $csvRow = array_map(function($field) {
        $field = (string)$field;
        // カンマ、改行、ダブルクォートが含まれる場合はダブルクォートで囲む
        if (strpos($field, ',') !== false || strpos($field, "\n") !== false || strpos($field, '"') !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }, $csvRow);

    echo implode(',', $csvRow) . "\n";
}

exit;
