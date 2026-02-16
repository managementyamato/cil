<?php
/**
 * アルコールチェックCSVダウンロード
 */
require_once '../api/auth.php';
require_once '../functions/photo-attendance-functions.php';

// パラメータ取得
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if (empty($startDate) || empty($endDate)) {
    http_response_code(400);
    exit('開始日と終了日を指定してください');
}

// 日付バリデーション
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    http_response_code(400);
    exit('日付形式が不正です');
}

if ($startDate > $endDate) {
    http_response_code(400);
    exit('開始日は終了日以前である必要があります');
}

// 従業員一覧取得
$employees = getEmployees();
$employeeMap = [];
foreach ($employees as $emp) {
    $employeeMap[$emp['id']] = $emp;
}

// アルコールチェックデータ取得
$allData = getPhotoAttendanceData();

// CSVデータを構築
$csvData = [];

// 日付範囲をループ
$currentDate = $startDate;
while ($currentDate <= $endDate) {
    $uploadStatus = getUploadStatusForDate($currentDate);

    foreach ($employees as $emp) {
        $empId = (string)$emp['id'];  // 文字列にキャストして型を統一
        $status = $uploadStatus[$empId] ?? null;

        // 出勤情報
        $startTime = '';
        $startAlcohol = '';
        $startPhoto = '';
        if (!empty($status['start'])) {
            // timeフィールドがあればそれを使用、なければuploaded_atから時刻を抽出
            if (!empty($status['start']['time'])) {
                $startTime = $status['start']['time'];
            } elseif (!empty($status['start']['uploaded_at'])) {
                $startTime = date('H:i', strtotime($status['start']['uploaded_at']));
            }
            $startAlcohol = isset($status['start']['alcohol_value']) ? $status['start']['alcohol_value'] : '';
            $startPhoto = !empty($status['start']['photo_path']) ? 'あり' : '';
        }

        // 退勤情報
        $endTime = '';
        $endAlcohol = '';
        $endPhoto = '';
        if (!empty($status['end'])) {
            // timeフィールドがあればそれを使用、なければuploaded_atから時刻を抽出
            if (!empty($status['end']['time'])) {
                $endTime = $status['end']['time'];
            } elseif (!empty($status['end']['uploaded_at'])) {
                $endTime = date('H:i', strtotime($status['end']['uploaded_at']));
            }
            $endAlcohol = isset($status['end']['alcohol_value']) ? $status['end']['alcohol_value'] : '';
            $endPhoto = !empty($status['end']['photo_path']) ? 'あり' : '';
        }

        // データがある場合のみ追加
        if (!empty($startTime) || !empty($endTime)) {
            $csvData[] = [
                'date' => $currentDate,
                'employee_code' => $emp['code'] ?? '',
                'employee_name' => $emp['name'] ?? '',
                'vehicle_number' => $emp['vehicle_number'] ?? '',
                'start_time' => $startTime,
                'start_alcohol' => $startAlcohol,
                'start_photo' => $startPhoto,
                'end_time' => $endTime,
                'end_alcohol' => $endAlcohol,
                'end_photo' => $endPhoto,
            ];
        }
    }

    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
}

// ファイル名生成
$filename = 'alcohol_check_' . str_replace('-', '', $startDate) . '_' . str_replace('-', '', $endDate) . '.csv';

// CSVヘッダー
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM（Excel対応）
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// ヘッダー行
fputcsv($output, [
    '日付',
    '社員コード',
    '氏名',
    '車両番号',
    '出勤時刻',
    '出勤時アルコール値',
    '出勤時写真',
    '退勤時刻',
    '退勤時アルコール値',
    '退勤時写真',
]);

// データ行
foreach ($csvData as $row) {
    fputcsv($output, [
        $row['date'],
        $row['employee_code'],
        $row['employee_name'],
        $row['vehicle_number'],
        $row['start_time'],
        $row['start_alcohol'],
        $row['start_photo'],
        $row['end_time'],
        $row['end_alcohol'],
        $row['end_photo'],
    ]);
}

fclose($output);

// 監査ログ
writeAuditLog('export', 'alcohol_check', 'アルコールチェックCSVエクスポート: ' . count($csvData) . '件', [
    'start_date' => $startDate,
    'end_date' => $endDate
]);
