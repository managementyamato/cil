<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/photo-attendance-functions.php';

initApi(['requireAuth' => true, 'requireCsrf' => false, 'allowedMethods' => ['GET']]);

$data = getData();
$todayDate  = date('Y-m-d');
$currentMonth = date('Y-m');
$lastMonth    = date('Y-m', strtotime('-1 month'));

// ---- トラブル統計 ----
$troubles   = $data['troubles'] ?? [];
$total      = count($troubles);
$pending    = count(array_filter($troubles, fn($t) => ($t['status'] ?? '') === '未対応'));
$inProgress = count(array_filter($troubles, fn($t) => ($t['status'] ?? '') === '対応中'));
$onHold     = count(array_filter($troubles, fn($t) => ($t['status'] ?? '') === '保留'));
$completed  = count(array_filter($troubles, fn($t) => ($t['status'] ?? '') === '完了'));
$completionRate = $total > 0 ? round(($completed / $total) * 100) : 0;

$currentMonthTroubles = 0;
$lastMonthTroubles    = 0;
foreach ($troubles as $t) {
    $createdAt = $t['occurred_date'] ?? $t['created_at'] ?? '';
    if (!empty($createdAt)) {
        $month = date('Y-m', strtotime(str_replace('/', '-', $createdAt)));
        if ($month === $currentMonth) {
            $currentMonthTroubles++;
        } elseif ($month === $lastMonth) {
            $lastMonthTroubles++;
        }
    }
}
$troubleMonthChange = $lastMonthTroubles > 0
    ? round((($currentMonthTroubles - $lastMonthTroubles) / $lastMonthTroubles) * 100)
    : 0;

$overdueCount = 0;
foreach ($troubles as $t) {
    if (($t['status'] ?? '') === '完了') continue;
    $dl = $t['deadline'] ?? '';
    if (!empty($dl) && strtotime($dl) < strtotime('today')) {
        $overdueCount++;
    }
}

// ---- 案件統計 ----
$projects   = $data['projects'] ?? [];
$statusList = ['案件発生', '成約', '製品手配中', '設置予定', '設置済', '完了'];
$projectByStatus = [];
foreach ($statusList as $s) {
    $projectByStatus[$s] = count(array_filter($projects, fn($p) => ($p['status'] ?? '') === $s));
}

// ---- 売上（finance権限がある場合のみ） ----
$canViewFinance = hasPermission('product');
$salesData = null;
if ($canViewFinance) {
    $currentMonthSales = 0;
    $lastMonthSales    = 0;
    foreach ($data['mf_invoices'] ?? [] as $invoice) {
        $salesDate = $invoice['sales_date'] ?? '';
        if ($salesDate) {
            $month = date('Y-m', strtotime(str_replace('/', '-', $salesDate)));
            if ($month === $currentMonth) {
                $currentMonthSales += floatval($invoice['total_amount'] ?? 0);
            } elseif ($month === $lastMonth) {
                $lastMonthSales += floatval($invoice['total_amount'] ?? 0);
            }
        }
    }
    $salesChange = $lastMonthSales > 0
        ? round((($currentMonthSales - $lastMonthSales) / $lastMonthSales) * 100, 1)
        : 0;
    $salesData = [
        'currentMonth'     => $currentMonthSales,
        'currentMonthWan'  => number_format($currentMonthSales / 10000),
        'change'           => $salesChange,
    ];
}

// ---- 最近のアクティビティ ----
$allItems = [];
foreach (array_slice($troubles, -5) as $t) {
    $allItems[] = [
        'type'   => 'trouble',
        'action' => ($t['status'] ?? '') === '完了' ? '完了' : '更新',
        'title'  => ($t['pj_number'] ?? 'トラブル') . ' - ' . mb_substr($t['trouble_content'] ?? '', 0, 25),
        'date'   => $t['updated_at'] ?? $t['created_at'] ?? '',
        'user'   => $t['responder'] ?? '不明',
    ];
}
usort($allItems, fn($a, $b) => strcmp($b['date'], $a['date']));
$recentActivities = array_slice($allItems, 0, 5);

// ---- アルコールチェック ----
$alcoholData = ['configured' => false, 'missingCount' => 0];
$canViewPhotoAttendance = hasPermission('sales');
if ($canViewPhotoAttendance) {
    $alcoholChatConfigFile = __DIR__ . '/../config/alcohol-chat-config.json';
    $alcoholChatConfig = file_exists($alcoholChatConfigFile)
        ? (json_decode(file_get_contents($alcoholChatConfigFile), true) ?: [])
        : [];

    if (!empty($alcoholChatConfig['space_id'])) {
        // Google Chatが設定されているかは簡易チェック
        $googleCredFile = __DIR__ . '/../config/google-service-account.json';
        $chatEnabled = file_exists($googleCredFile) || !empty($alcoholChatConfig['webhook_url']);
        if ($chatEnabled) {
            $alcoholData['configured'] = true;
            $employees = $data['employees'] ?? [];
            if (!empty($employees)) {
                $uploadStatus      = getUploadStatusForDate($todayDate);
                $noCarUsage        = getNoCarUsageForDate($todayDate);
                $targetEmployeeIds = getAlcoholCheckTargetEmployeesForDate($todayDate);
                $missing = 0;
                foreach ($employees as $emp) {
                    $empId = (string)($emp['id'] ?? '');
                    if (empty($empId) || !in_array($empId, $targetEmployeeIds, true)) continue;
                    if (!isset($uploadStatus[$empId]) && !in_array($empId, $noCarUsage)) {
                        $missing++;
                    }
                }
                $alcoholData['missingCount'] = $missing;
            }
        }
    }
}

// ---- Google Calendar 設定確認 ----
$calendarConfigFile = __DIR__ . '/../config/google-calendar-config.json';
$calendarConfigured = false;
if (file_exists($calendarConfigFile)) {
    $calCfg = json_decode(file_get_contents($calendarConfigFile), true) ?: [];
    $calendarConfigured = !empty($calCfg['calendar_id']) || !empty($calCfg['enabled']);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'troubles' => [
        'total'             => $total,
        'pending'           => $pending,
        'inProgress'        => $inProgress,
        'onHold'            => $onHold,
        'completed'         => $completed,
        'completionRate'    => $completionRate,
        'currentMonthCount' => $currentMonthTroubles,
        'monthChange'       => $troubleMonthChange,
        'overdueCount'      => $overdueCount,
    ],
    'projects' => [
        'byStatus' => $projectByStatus,
    ],
    'sales'            => $salesData,
    'recentActivities' => $recentActivities,
    'permissions' => [
        'canViewFinance'         => $canViewFinance,
        'canViewTroubles'        => hasPermission('sales'),
        'canViewMaster'          => hasPermission('sales'),
        'canViewPhotoAttendance' => $canViewPhotoAttendance,
        'canViewTroubleForm'     => hasPermission('sales'),
    ],
    'calendarConfigured' => $calendarConfigured,
    'alcoholCheck'       => $alcoholData,
], JSON_UNESCAPED_UNICODE);
