<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';
require_once __DIR__ . '/../../functions/photo-attendance-functions.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET']
]);

// 編集権限チェック
if (!canEdit()) {
    errorResponse('アクセス権限がありません', 403);
}

date_default_timezone_set('Asia/Tokyo');

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    $date = date('Y-m-d');
}
$isToday = ($date === date('Y-m-d'));

// 従業員一覧
$allEmployees = getEmployees();

// アルコールチェック対象者
$targetEmployeeIds = getAlcoholCheckTargetEmployeesForDate($date);
$employees = array_filter($allEmployees, function($emp) use ($targetEmployeeIds) {
    $empId = (string)($emp['id'] ?? '');
    return in_array($empId, $targetEmployeeIds, true);
});
$employees = array_values($employees);

// アップロード状況
$uploadStatus = getUploadStatusForDate($date);
$noCarUsageIds = getNoCarUsageForDate($date);
$unassignedPhotos = getUnassignedPhotosForDate($date);

// data.json から no_car_usage を取得
$dataJson = getData();
$noCarUsageData = $dataJson['no_car_usage'] ?? [];

// 統計
$complete = 0;
$partial = 0;
$missing = 0;
$todayMissing = [];

foreach ($employees as $emp) {
    $empId = (string)($emp['id'] ?? '');
    $isNoCarUsage = in_array($emp['id'], $noCarUsageIds);

    if ($isNoCarUsage) {
        $complete++;
        continue;
    }

    $status = $uploadStatus[$emp['id']] ?? ['start' => null, 'end' => null];
    if ($status['start'] && $status['end']) {
        $complete++;
    } elseif ($status['start'] || $status['end']) {
        $partial++;
    } else {
        $missing++;
    }

    // 未提出者
    if (empty($emp['leave_date'])) {
        $found = false;
        $attDataFile = dirname(dirname(__DIR__)) . '/config/photo-attendance-data.json';
        $attData = [];
        if (file_exists($attDataFile)) {
            $attData = json_decode(file_get_contents($attDataFile), true) ?: [];
        }
        foreach ($attData as $upload) {
            if (($upload['upload_date'] ?? '') === $date && (string)($upload['employee_id'] ?? '') === $empId) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            foreach ($noCarUsageData as $ncu) {
                if (($ncu['date'] ?? '') === $date && (string)($ncu['employeeId'] ?? '') === $empId) {
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) {
            $todayMissing[] = $emp['name'] ?? ('ID:' . $empId);
        }
    }
}

// 従業員データ成形
$employeeList = [];
foreach ($employees as $emp) {
    $isNoCarUsage = in_array($emp['id'], $noCarUsageIds);
    $status = $uploadStatus[$emp['id']] ?? ['start' => null, 'end' => null];

    $rowStatus = 'missing';
    if ($isNoCarUsage) {
        $rowStatus = 'no-car';
    } elseif ($status['start'] && $status['end']) {
        $rowStatus = 'complete';
    } elseif ($status['start'] || $status['end']) {
        $rowStatus = 'partial';
    }

    $startTime = null;
    if ($status['start'] && !empty($status['start']['uploaded_at'])) {
        $startTime = date('H:i', strtotime($status['start']['uploaded_at']));
    }
    $endTime = null;
    if ($status['end'] && !empty($status['end']['uploaded_at'])) {
        $endTime = date('H:i', strtotime($status['end']['uploaded_at']));
    }

    $employeeList[] = [
        'id' => $emp['id'] ?? '',
        'name' => $emp['name'] ?? '',
        'vehicle_number' => $emp['vehicle_number'] ?? '-',
        'status' => $rowStatus,
        'has_start' => !empty($status['start']),
        'has_end' => !empty($status['end']),
        'start_time' => $startTime,
        'end_time' => $endTime,
    ];
}

$isWeekday = date('N', strtotime($date)) <= 5;

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'date' => $date,
        'isToday' => $isToday,
        'hasEmployees' => !empty($allEmployees),
        'hasTargets' => !empty($employees),
        'employees' => $employeeList,
        'stats' => [
            'complete' => $complete,
            'partial' => $partial,
            'missing' => $missing,
        ],
        'todayMissing' => $todayMissing,
        'isWeekday' => $isWeekday,
        'unassignedCount' => count($unassignedPhotos),
        'canEdit' => canEdit(),
    ]
], JSON_UNESCAPED_UNICODE);
