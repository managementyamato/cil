<?php
/**
 * トラブル一覧CSVダウンロード
 */
require_once '../api/auth.php';

// 編集権限チェック
if (!canEdit()) {
    header('Location: troubles.php');
    exit;
}

$data = getData();
$troubles = $data['troubles'] ?? [];

// フィルタパラメータを取得
$filterStatus = $_GET['status'] ?? '';
$filterPjNumber = $_GET['pj_number'] ?? '';
$filterSearch = $_GET['search'] ?? '';

// フィルタリング
if ($filterStatus) {
    $troubles = array_filter($troubles, function($t) use ($filterStatus) {
        return ($t['status'] ?? '') === $filterStatus;
    });
}
if ($filterPjNumber) {
    $troubles = array_filter($troubles, function($t) use ($filterPjNumber) {
        return ($t['pj_number'] ?? '') === $filterPjNumber;
    });
}
if ($filterSearch) {
    $troubles = array_filter($troubles, function($t) use ($filterSearch) {
        $searchText = mb_strtolower($filterSearch);
        $content = mb_strtolower($t['content'] ?? '');
        $solution = mb_strtolower($t['solution'] ?? '');
        $pjNumber = mb_strtolower($t['pj_number'] ?? '');
        return mb_strpos($content, $searchText) !== false ||
               mb_strpos($solution, $searchText) !== false ||
               mb_strpos($pjNumber, $searchText) !== false;
    });
}

// CSVヘッダー
$headers = [
    'ID',
    'P番号',
    '発生日',
    '記入者',
    '対応者',
    'ステータス',
    'トラブル内容',
    '対応内容',
    '有償フラグ',
    '備考',
    '登録日時'
];

// ファイル名
$filename = 'troubles_' . date('Ymd_His') . '.csv';

// CSVヘッダー送信
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM付きUTF-8（Excel対応）
echo "\xEF\xBB\xBF";

// 出力バッファ
$output = fopen('php://output', 'w');

// ヘッダー行
fputcsv($output, $headers);

// データ行
foreach ($troubles as $trouble) {
    $row = [
        $trouble['id'] ?? '',
        $trouble['pj_number'] ?? '',
        $trouble['occurrence_date'] ?? '',
        $trouble['reporter'] ?? '',
        $trouble['responder'] ?? '',
        $trouble['status'] ?? '',
        $trouble['content'] ?? '',
        $trouble['solution'] ?? '',
        isset($trouble['is_paid']) && $trouble['is_paid'] ? '有償' : '無償',
        $trouble['note'] ?? '',
        isset($trouble['created_at']) ? date('Y-m-d H:i', strtotime($trouble['created_at'])) : ''
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;
