<?php
/**
 * アルコールチェックデータ CSV出力
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/photo-attendance-functions.php';

// 管理者・編集者権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

// パラメータ取得
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // デフォルト: 今月の1日
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // デフォルト: 今日

// アルコールチェックデータを取得
$allData = getPhotoAttendanceData();

// 従業員データを取得
$employees = getEmployees();
$employeeMap = [];
foreach ($employees as $employee) {
    $employeeMap[$employee['id']] = $employee;
}

// 期間内のデータをフィルタリング
$filteredData = array_filter($allData, function($record) use ($startDate, $endDate) {
    $uploadDate = $record['upload_date'] ?? '';
    return $uploadDate >= $startDate && $uploadDate <= $endDate;
});

// 日付・従業員ごとにグループ化
$groupedData = [];
foreach ($filteredData as $record) {
    $date = $record['upload_date'];
    $employeeId = $record['employee_id'];
    $uploadType = $record['upload_type'];

    $key = $date . '_' . $employeeId;

    if (!isset($groupedData[$key])) {
        $groupedData[$key] = [
            'date' => $date,
            'employee_id' => $employeeId,
            'start' => null,
            'end' => null
        ];
    }

    $groupedData[$key][$uploadType] = $record;
}

// 日付・従業員IDでソート
usort($groupedData, function($a, $b) {
    if ($a['date'] !== $b['date']) {
        return strcmp($a['date'], $b['date']);
    }
    return $a['employee_id'] - $b['employee_id'];
});

// CSVヘッダーを設定
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="alcohol-check_' . $startDate . '_' . $endDate . '.csv"');

// BOM出力（Excelで文字化けしないように）
echo "\xEF\xBB\xBF";

// CSVヘッダー行
$headers = [
    '日付',
    '従業員ID',
    '従業員名',
    '出勤時_アップロード日時',
    '出勤時_写真',
    '退勤時_アップロード日時',
    '退勤時_写真',
    '車不使用'
];
echo implode(',', $headers) . "\n";

// データ行
foreach ($groupedData as $row) {
    $date = $row['date'];
    $employeeId = $row['employee_id'];
    $employee = $employeeMap[$employeeId] ?? null;
    $employeeName = $employee ? $employee['name'] : '不明';

    // 出勤時
    $startRecord = $row['start'];
    $startUploadTime = $startRecord ? ($startRecord['uploaded_at'] ?? '') : '';
    $startPhoto = $startRecord ? ($startRecord['file_path'] ?? '') : '';

    // 退勤時
    $endRecord = $row['end'];
    $endUploadTime = $endRecord ? ($endRecord['uploaded_at'] ?? '') : '';
    $endPhoto = $endRecord ? ($endRecord['file_path'] ?? '') : '';

    // 車不使用チェック
    $noCarUsage = '';
    if ($startRecord && isset($startRecord['no_car_usage']) && $startRecord['no_car_usage']) {
        $noCarUsage = '車不使用';
    } elseif ($endRecord && isset($endRecord['no_car_usage']) && $endRecord['no_car_usage']) {
        $noCarUsage = '車不使用';
    }

    $csvRow = [
        $date,
        $employeeId,
        $employeeName,
        $startUploadTime,
        $startPhoto,
        $endUploadTime,
        $endPhoto,
        $noCarUsage
    ];

    // CSVエスケープ
    $csvRow = array_map(function($field) {
        // カンマ、改行、ダブルクォートが含まれる場合はダブルクォートで囲む
        if (strpos($field, ',') !== false || strpos($field, "\n") !== false || strpos($field, '"') !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }, $csvRow);

    echo implode(',', $csvRow) . "\n";
}

exit;
