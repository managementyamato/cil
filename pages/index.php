<?php
require_once '../api/auth.php';
require_once '../api/google-calendar.php';
require_once '../api/google-chat.php';
require_once '../functions/photo-attendance-functions.php';
$data = getData();

// Google Calendar設定チェック
$calendarClient = new GoogleCalendarClient();
$calendarConfigured = $calendarClient->isConfigured();

// Google Chat設定チェック（アルコールチェック同期用）
$googleChat = new GoogleChatClient();
$chatConfigured = $googleChat->isConfigured();
$alcoholChatConfig = [];
$alcoholChatConfigFile = __DIR__ . '/../config/alcohol-chat-config.json';
if (file_exists($alcoholChatConfigFile)) {
    $alcoholChatConfig = json_decode(file_get_contents($alcoholChatConfigFile), true) ?: [];
}

// ダッシュボード設定を読み込み
$dashboardSettingsFile = __DIR__ . '/../config/dashboard-settings.json';
$defaultSettings = [
    'widgets' => [
        'tasks' => ['enabled' => true, 'order' => 0],
        'summary' => ['enabled' => true, 'order' => 1],
        'calendar' => ['enabled' => true, 'order' => 2],
        'alerts' => ['enabled' => true, 'order' => 3],
        'troubles' => ['enabled' => true, 'order' => 4]
    ],
    'collapsed' => []
];
$dashboardSettings = $defaultSettings;
if (file_exists($dashboardSettingsFile)) {
    $loaded = json_decode(file_get_contents($dashboardSettingsFile), true);
    if ($loaded) {
        $dashboardSettings = array_merge($defaultSettings, $loaded);
    }
}

$total = count($data['troubles']);
$pending = count(array_filter($data['troubles'], function($t) { return $t['status'] === '未対応'; }));
$inProgress = count(array_filter($data['troubles'], function($t) { return $t['status'] === '対応中'; }));
$onHold = count(array_filter($data['troubles'], function($t) { return $t['status'] === '保留'; }));
$completed = count(array_filter($data['troubles'], function($t) { return $t['status'] === '完了'; }));

// 完了率を計算
$completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

// 今月・先月の売上
$currentMonth = date('Y-m');
$currentMonthSales = 0;
$lastMonth = date('Y-m', strtotime('-1 month'));
$lastMonthSales = 0;
foreach ($data['mf_invoices'] ?? array() as $invoice) {
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
$salesChange = $lastMonthSales > 0 ? round((($currentMonthSales - $lastMonthSales) / $lastMonthSales) * 100, 1) : 0;

// ===== 今日やることリスト =====
$todayDate = date('Y-m-d');
$soonDate = date('Y-m-d', strtotime('+7 days'));
$todayTasks = [];

// 1. 期限が今日のトラブル
foreach ($data['troubles'] ?? [] as $t) {
    if (($t['status'] ?? '') === '完了') continue;
    $deadline = $t['deadline'] ?? '';
    if ($deadline === $todayDate) {
        $todayTasks[] = [
            'type' => 'trouble_deadline',
            'priority' => 1,
            'icon' => 'warning',
            'title' => '期限: ' . ($t['pj_number'] ?? '') . ' トラブル対応',
            'description' => mb_substr($t['trouble_content'] ?? '', 0, 30),
            'link' => 'troubles.php?id=' . ($t['id'] ?? ''),
            'status' => $t['status'] ?? ''
        ];
    }
}

// 2. 期限超過トラブル（緊急）
foreach ($data['troubles'] ?? [] as $t) {
    if (($t['status'] ?? '') === '完了') continue;
    $deadline = $t['deadline'] ?? '';
    if (!empty($deadline) && $deadline < $todayDate) {
        $daysOver = (strtotime($todayDate) - strtotime($deadline)) / 86400;
        $todayTasks[] = [
            'type' => 'trouble_overdue',
            'priority' => 0,
            'icon' => 'danger',
            'title' => '【' . (int)$daysOver . '日超過】' . ($t['pj_number'] ?? '') . ' トラブル',
            'description' => mb_substr($t['trouble_content'] ?? '', 0, 30),
            'link' => 'troubles.php?id=' . ($t['id'] ?? ''),
            'status' => $t['status'] ?? ''
        ];
    }
}

// 3. 未対応トラブル（上位5件、古い順）
$pendingTroubles = array_filter($data['troubles'] ?? [], function($t) {
    return ($t['status'] ?? '') === '未対応';
});
usort($pendingTroubles, function($a, $b) {
    return strcmp($a['occurrence_date'] ?? $a['created_at'] ?? '', $b['occurrence_date'] ?? $b['created_at'] ?? '');
});
$pendingTroubles = array_slice($pendingTroubles, 0, 5);
foreach ($pendingTroubles as $t) {
    $tDate = $t['occurrence_date'] ?? $t['created_at'] ?? '';
    $daysSince = '';
    if ($tDate) {
        $diff = (int)((strtotime($todayDate) - strtotime($tDate)) / 86400);
        $daysSince = $diff > 0 ? "（{$diff}日経過）" : '';
    }
    // 既にdeadline関連で追加済みのものは除く
    $exists = false;
    foreach ($todayTasks as $task) {
        if (strpos($task['link'], 'id=' . ($t['id'] ?? 'none')) !== false) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $todayTasks[] = [
            'type' => 'trouble_pending',
            'priority' => 2,
            'icon' => 'pending',
            'title' => '未対応: ' . ($t['pj_number'] ?? '') . $daysSince,
            'description' => mb_substr($t['trouble_content'] ?? '', 0, 30),
            'link' => 'troubles.php?id=' . ($t['id'] ?? ''),
            'status' => '未対応'
        ];
    }
}

// 4. 設置予定日が近い案件
foreach ($data['projects'] ?? [] as $pj) {
    if (!empty($pj['install_schedule_date']) && empty($pj['install_complete_date'])) {
        $schedDate = $pj['install_schedule_date'];
        if ($schedDate === $todayDate) {
            $todayTasks[] = [
                'type' => 'project_install',
                'priority' => 1,
                'icon' => 'install',
                'title' => '本日設置: ' . ($pj['name'] ?? ''),
                'description' => 'P-' . ($pj['id'] ?? ''),
                'link' => 'master.php',
                'status' => 'today'
            ];
        } elseif ($schedDate < $todayDate) {
            $daysOver = (strtotime($todayDate) - strtotime($schedDate)) / 86400;
            $todayTasks[] = [
                'type' => 'project_overdue',
                'priority' => 0,
                'icon' => 'danger',
                'title' => '【設置' . (int)$daysOver . '日超過】' . ($pj['name'] ?? ''),
                'description' => 'P-' . ($pj['id'] ?? ''),
                'link' => 'master.php',
                'status' => 'overdue'
            ];
        }
    }
}

// 5. 本日の未提出アルコールチェック
// その日に同期で取得できた従業員のみを対象とする
$employees = $data['employees'] ?? [];
$missingAlcoholEmployees = [];
if (!empty($employees)) {
    $uploadStatus = getUploadStatusForDate($todayDate);
    $noCarUsage = getNoCarUsageForDate($todayDate);
    $targetEmployeeIds = getAlcoholCheckTargetEmployeesForDate($todayDate); // その日の同期で取得できた従業員のみ

    foreach ($employees as $emp) {
        $empId = (string)($emp['id'] ?? '');
        if (empty($empId)) continue;

        // その日の同期実績がない従業員はスキップ（型を文字列で比較）
        if (!in_array($empId, $targetEmployeeIds, true)) continue;

        $hasUpload = isset($uploadStatus[$empId]);
        $hasNoCar = in_array($empId, $noCarUsage);
        if (!$hasUpload && !$hasNoCar) {
            $missingAlcoholEmployees[] = $emp['name'] ?? ('ID:' . $empId);
        }
    }
    if (!empty($missingAlcoholEmployees)) {
        $todayTasks[] = [
            'type' => 'alcohol_check',
            'priority' => 2,
            'icon' => 'alcohol',
            'title' => 'アルコールチェック未提出',
            'description' => count($missingAlcoholEmployees) . '名: ' . implode('、', array_slice($missingAlcoholEmployees, 0, 3)) . (count($missingAlcoholEmployees) > 3 ? ' 他' : ''),
            'link' => 'photo-attendance.php',
            'status' => 'pending'
        ];
    }
}

// タスクを優先度順にソート
usort($todayTasks, function($a, $b) {
    return $a['priority'] - $b['priority'];
});

// ===== アラート通知データ（既存のコード維持） =====
$alerts = [];

// 1. 期限切れ・期限間近の案件
foreach ($data['projects'] ?? [] as $pj) {
    $pjName = $pj['name'] ?? ('P-' . ($pj['id'] ?? '?'));
    if (!empty($pj['install_schedule_date']) && empty($pj['install_complete_date'])) {
        $schedDate = $pj['install_schedule_date'];
        if ($schedDate < $todayDate) {
            $alerts[] = [
                'type' => 'danger',
                'category' => 'project',
                'title' => '設置予定日超過',
                'message' => $pjName . ' - 設置予定日: ' . date('Y/m/d', strtotime($schedDate)),
                'date' => $schedDate,
                'link' => 'master.php'
            ];
        } elseif ($schedDate <= $soonDate) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'project',
                'title' => '設置予定日間近',
                'message' => $pjName . ' - 設置予定日: ' . date('Y/m/d', strtotime($schedDate)),
                'date' => $schedDate,
                'link' => 'master.php'
            ];
        }
    }
    if (!empty($pj['warranty_end_date'])) {
        $warrantyDate = $pj['warranty_end_date'];
        if ($warrantyDate < $todayDate) {
            $alerts[] = [
                'type' => 'danger',
                'category' => 'project',
                'title' => '保証期限切れ',
                'message' => $pjName . ' - 保証終了: ' . date('Y/m/d', strtotime($warrantyDate)),
                'date' => $warrantyDate,
                'link' => 'master.php'
            ];
        } elseif ($warrantyDate <= $soonDate) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'project',
                'title' => '保証期限間近',
                'message' => $pjName . ' - 保証終了: ' . date('Y/m/d', strtotime($warrantyDate)),
                'date' => $warrantyDate,
                'link' => 'master.php'
            ];
        }
    }
}

// 2. 未対応トラブル（古い順で上位10件）
$oldPendingTroubles = array_filter($data['troubles'] ?? [], function($t) {
    return ($t['status'] ?? '') === '未対応';
});
usort($oldPendingTroubles, function($a, $b) {
    return strcmp($a['occurrence_date'] ?? $a['created_at'] ?? '', $b['occurrence_date'] ?? $b['created_at'] ?? '');
});
$oldPendingTroubles = array_slice($oldPendingTroubles, 0, 10);
foreach ($oldPendingTroubles as $t) {
    $daysSince = '';
    $tDate = $t['occurrence_date'] ?? $t['created_at'] ?? '';
    if ($tDate) {
        $diff = (strtotime($todayDate) - strtotime($tDate)) / 86400;
        $daysSince = '（' . (int)$diff . '日経過）';
    }
    $alerts[] = [
        'type' => 'danger',
        'category' => 'trouble',
        'title' => '未対応トラブル' . $daysSince,
        'message' => ($t['pj_number'] ?? '') . ' - ' . mb_substr($t['description'] ?? '', 0, 40),
        'date' => $tDate,
        'link' => 'troubles.php?status=未対応'
    ];
}

// 3. 本日の未提出アルコールチェック
if (!empty($missingAlcoholEmployees)) {
    $alerts[] = [
        'type' => 'warning',
        'category' => 'alcohol',
        'title' => '本日の未提出アルコールチェック',
        'message' => count($missingAlcoholEmployees) . '名未提出: ' . implode('、', array_slice($missingAlcoholEmployees, 0, 5)) . (count($missingAlcoholEmployees) > 5 ? ' 他' . (count($missingAlcoholEmployees) - 5) . '名' : ''),
        'date' => $todayDate,
        'link' => 'photo-attendance.php'
    ];
}

// 4. 期限超過トラブル
$overdueTroubles = array_filter($data['troubles'] ?? [], function($t) {
    if (($t['status'] ?? '') === '完了') return false;
    $dl = $t['deadline'] ?? '';
    return !empty($dl) && strtotime($dl) < strtotime('today');
});
foreach ($overdueTroubles as $t) {
    $dl = $t['deadline'];
    $diff = (strtotime($todayDate) - strtotime($dl)) / 86400;
    $alerts[] = [
        'type' => 'danger',
        'category' => 'trouble',
        'title' => '期限超過トラブル（' . (int)$diff . '日超過）',
        'message' => ($t['pj_number'] ?? '') . ' - ' . mb_substr($t['trouble_content'] ?? '', 0, 40),
        'date' => $dl,
        'link' => 'troubles.php?sort=deadline&dir=asc'
    ];
}

// アラートをタイプ別にソート（danger優先）
usort($alerts, function($a, $b) {
    $typePriority = ['danger' => 0, 'warning' => 1, 'info' => 2];
    return ($typePriority[$a['type']] ?? 9) - ($typePriority[$b['type']] ?? 9);
});

$alertCount = count($alerts);

// ===== 月次サマリーデータ =====
$projectStatusCounts = [];
$statusList = ['案件発生', '成約', '製品手配中', '設置予定', '設置済', '完了'];
foreach ($statusList as $s) {
    $projectStatusCounts[$s] = 0;
}
foreach ($data['projects'] ?? [] as $pj) {
    $s = $pj['status'] ?? '案件発生';
    if (isset($projectStatusCounts[$s])) {
        $projectStatusCounts[$s]++;
    }
}
$activeProjects = count($data['projects'] ?? []) - ($projectStatusCounts['完了'] ?? 0);

// 今月の新規トラブル数
$currentMonthTroubles = 0;
foreach ($data['troubles'] ?? [] as $t) {
    $tDate = $t['date'] ?? $t['created_at'] ?? '';
    if ($tDate && date('Y-m', strtotime($tDate)) === $currentMonth) {
        $currentMonthTroubles++;
    }
}

// 期限超過トラブル数
$overdueIssues = 0;
foreach ($data['troubles'] ?? [] as $t) {
    if (($t['status'] ?? '') === '完了') continue;
    $dl = $t['deadline'] ?? '';
    if (!empty($dl) && strtotime($dl) < strtotime('today')) {
        $overdueIssues++;
    }
}

require_once '../functions/header.php';
?>

<style>
/* ========== 新ダッシュボード専用スタイル ========== */

/* 設定ボタン */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.dashboard-header h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-800);
}
.dashboard-settings-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--gray-100);
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    cursor: pointer;
    color: var(--gray-600);
    transition: all 0.15s;
}
.dashboard-settings-btn:hover {
    background: var(--gray-200);
    color: var(--gray-800);
}

/* ウィジェットコンテナ */
.widget-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* 共通ウィジェットスタイル */
.widget {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: all 0.3s ease;
}
.widget.hidden {
    display: none;
}
.widget-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--gray-100);
    background: var(--gray-50);
}
.widget-header h3 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--gray-800);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.widget-toggle {
    background: none;
    border: none;
    color: var(--gray-400);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.15s;
}
.widget-toggle:hover {
    background: var(--gray-200);
    color: var(--gray-600);
}
.widget-body {
    padding: 1rem 1.25rem;
}
.widget.collapsed .widget-body {
    display: none;
}
.widget.collapsed .widget-toggle svg {
    transform: rotate(-90deg);
}

/* ========== 今日やることウィジェット ========== */
.task-widget {
    border-left: 4px solid #3b82f6;
}
.task-widget .widget-header {
    background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%);
}
.task-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.task-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    text-decoration: none;
    color: inherit;
    transition: all 0.15s;
    border: 1px solid var(--gray-100);
}
.task-item:last-child { margin-bottom: 0; }
.task-item:hover {
    background: var(--gray-50);
    border-color: var(--primary);
    transform: translateX(4px);
}
.task-icon {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
.task-icon-danger { background: #fef2f2; color: #ef4444; }
.task-icon-warning { background: #fffbeb; color: #f59e0b; }
.task-icon-pending { background: #f3f4f6; color: #6b7280; }
.task-icon-install { background: #eff6ff; color: #3b82f6; }
.task-icon-alcohol { background: #faf5ff; color: #8b5cf6; }
.task-content {
    flex: 1;
    min-width: 0;
}
.task-title {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--gray-800);
    margin-bottom: 0.15rem;
}
.task-description {
    font-size: 0.75rem;
    color: var(--gray-500);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.task-status {
    flex-shrink: 0;
    font-size: 0.65rem;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-weight: 500;
}
.task-status.pending { background: #fef2f2; color: #dc2626; }
.task-status.in-progress { background: #fffbeb; color: #d97706; }
.task-status.today { background: #eff6ff; color: #2563eb; }
.task-status.overdue { background: #fef2f2; color: #dc2626; }
.no-tasks {
    text-align: center;
    padding: 2rem;
    color: var(--gray-500);
}
.task-count-badge {
    background: #ef4444;
    color: white;
    font-size: 0.7rem;
    padding: 0.15rem 0.5rem;
    border-radius: 10px;
    font-weight: 700;
    margin-left: 0.5rem;
}

/* ========== サマリーカード ========== */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
}
.summary-card {
    display: block;
    color: white;
    border-radius: 10px;
    padding: 1rem;
    text-decoration: none;
    transition: all 0.2s;
}
.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.15);
}
.summary-card.blue { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
.summary-card.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.summary-card.red { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
.summary-card.purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
.summary-card.amber { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.summary-card h4 {
    margin: 0 0 0.5rem 0;
    font-size: 0.7rem;
    font-weight: 500;
    opacity: 0.9;
}
.summary-card .value {
    font-size: 1.5rem;
    font-weight: 700;
}
.summary-card .sub {
    font-size: 0.65rem;
    opacity: 0.8;
    margin-top: 0.25rem;
}
.summary-card .badge {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    padding: 0.15rem 0.4rem;
    border-radius: 6px;
    font-size: 0.6rem;
    margin-top: 0.25rem;
}

/* ========== カレンダーウィジェット ========== */
.calendar-widget {
    border-left: 4px solid #ef4444;
}
.calendar-widget .widget-header {
    background: linear-gradient(135deg, #fef2f2 0%, #f8fafc 100%);
}
.event-list {
    max-height: 200px;
    overflow-y: auto;
}
.event-item {
    display: flex;
    align-items: center;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    margin-bottom: 0.35rem;
    background: var(--gray-50);
    font-size: 0.8rem;
}
.event-item:last-child { margin-bottom: 0; }
.event-time {
    min-width: 85px;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--primary);
}
.event-time.all-day { color: #10b981; }
.event-title {
    flex: 1;
    font-weight: 500;
    color: var(--gray-800);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.no-events {
    text-align: center;
    padding: 1.5rem;
    color: var(--gray-500);
    font-size: 0.8rem;
}
.today-date {
    font-size: 0.7rem;
    color: var(--gray-500);
    font-weight: normal;
    margin-left: auto;
}

/* ========== アラートウィジェット ========== */
.alert-widget {
    border-left: 4px solid #f59e0b;
}
.alert-widget .widget-header {
    background: linear-gradient(135deg, #fffbeb 0%, #f8fafc 100%);
}
.alert-badge {
    background: #ef4444;
    color: white;
    font-size: 0.7rem;
    padding: 0.15rem 0.5rem;
    border-radius: 10px;
    font-weight: 700;
}
.alert-filters {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}
.alert-filter-btn {
    background: var(--gray-100);
    border: none;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.7rem;
    cursor: pointer;
    color: var(--gray-600);
    transition: all 0.15s;
}
.alert-filter-btn:hover { background: var(--gray-200); }
.alert-filter-btn.active {
    background: var(--primary);
    color: white;
}
.alert-list {
    max-height: 250px;
    overflow-y: auto;
}
.alert-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.6rem;
    border-radius: 6px;
    text-decoration: none;
    color: inherit;
    transition: background 0.15s;
    margin-bottom: 0.25rem;
}
.alert-item:last-child { margin-bottom: 0; }
.alert-item:hover { background: var(--gray-50); }
.alert-icon {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.alert-danger .alert-icon { background: #fef2f2; color: #ef4444; }
.alert-warning .alert-icon { background: #fffbeb; color: #f59e0b; }
.alert-content { flex: 1; min-width: 0; }
.alert-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--gray-800);
}
.alert-message {
    font-size: 0.7rem;
    color: var(--gray-500);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ========== トラブル統計ウィジェット ========== */
.trouble-widget {
    border-left: 4px solid #10b981;
}
.trouble-widget .widget-header {
    background: linear-gradient(135deg, #ecfdf5 0%, #f8fafc 100%);
}
.trouble-stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.5rem;
    text-align: center;
    margin-bottom: 1rem;
}
.trouble-stat-item {
    padding: 0.6rem 0.4rem;
    border-radius: 8px;
    background: var(--gray-50);
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
    cursor: pointer;
    border: 2px solid transparent;
}
.trouble-stat-item:hover {
    background: var(--gray-100);
    border-color: var(--primary);
    transform: translateY(-2px);
}
.trouble-stat-item .value {
    font-size: 1.25rem;
    font-weight: bold;
}
.trouble-stat-item .label {
    font-size: 0.65rem;
    color: var(--gray-600);
    margin-top: 0.15rem;
}
.trouble-stat-item.pending .value { color: #ef4444; }
.trouble-stat-item.in-progress .value { color: #f59e0b; }
.trouble-stat-item.on-hold .value { color: #6b7280; }
.trouble-stat-item.completed .value { color: #10b981; }
.completion-bar { margin-top: 0.5rem; }
.completion-bar .header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.35rem;
    font-size: 0.8rem;
}
.completion-bar .header .rate {
    font-weight: bold;
    color: #10b981;
}
.completion-bar .bar {
    height: 6px;
    background: var(--gray-200);
    border-radius: 3px;
    overflow: hidden;
}
.completion-bar .bar .fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    border-radius: 3px;
    transition: width 0.5s ease;
}

/* ========== 設定モーダル ========== */
.settings-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.settings-modal.active { display: flex; }
.settings-modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    max-height: 80vh;
    overflow-y: auto;
}
.settings-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--gray-200);
}
.settings-modal-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}
.settings-modal-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    color: var(--gray-500);
    padding: 0.25rem;
}
.settings-modal-body {
    padding: 1.25rem;
}
.widget-toggle-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--gray-100);
}
.widget-toggle-item:last-child { border-bottom: none; }
.widget-toggle-label {
    font-size: 0.875rem;
    color: var(--gray-700);
}
.toggle-switch {
    position: relative;
    width: 44px;
    height: 24px;
}
.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--gray-300);
    transition: 0.3s;
    border-radius: 24px;
}
.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background: white;
    transition: 0.3s;
    border-radius: 50%;
}
.toggle-switch input:checked + .toggle-slider {
    background: var(--primary);
}
.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(20px);
}

/* アルコール同期ボタン */
.alcohol-sync-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}
.alcohol-sync-btn button {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
    padding: 0.4rem 1rem;
    border-radius: 6px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.15s;
}
.alcohol-sync-btn button:hover {
    background: rgba(255,255,255,0.3);
}
.alcohol-sync-status {
    font-size: 0.65rem;
    min-height: 1em;
}
</style>

<div class="page-container">

<!-- ダッシュボードヘッダー -->
<div class="dashboard-header">
    <h2>ダッシュボード</h2>
    <button class="dashboard-settings-btn" onclick="openDashboardSettings()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
        表示設定
    </button>
</div>

<div class="widget-container">

<!-- ========== 今日やることウィジェット ========== -->
<div class="widget task-widget" id="widget-tasks" data-widget="tasks">
    <div class="widget-header">
        <h3>
            今日やること
            <?php if (count($todayTasks) > 0): ?>
            <span class="task-count-badge"><?= count($todayTasks) ?></span>
            <?php endif; ?>
        </h3>
        <button class="widget-toggle" onclick="toggleWidget('tasks')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </button>
    </div>
    <div class="widget-body">
        <?php if (empty($todayTasks)): ?>
        <div class="no-tasks">
            <div>すべて完了しました</div>
        </div>
        <?php else: ?>
        <ul class="task-list">
            <?php foreach ($todayTasks as $task): ?>
            <a href="<?= htmlspecialchars($task['link']) ?>" class="task-item">
                <span class="task-icon task-icon-<?= $task['icon'] ?>">
                    <?php if ($task['icon'] === 'danger'): ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                    <?php elseif ($task['icon'] === 'warning'): ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <?php elseif ($task['icon'] === 'pending'): ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <?php elseif ($task['icon'] === 'install'): ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    <?php elseif ($task['icon'] === 'alcohol'): ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2h8l4 10H4L8 2z"/><path d="M12 12v10"/><path d="M8 22h8"/></svg>
                    <?php endif; ?>
                </span>
                <div class="task-content">
                    <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                    <div class="task-description"><?= htmlspecialchars($task['description']) ?></div>
                </div>
                <span class="task-status <?= $task['status'] === '未対応' ? 'pending' : ($task['status'] === '対応中' ? 'in-progress' : ($task['status'] === 'overdue' ? 'overdue' : 'today')) ?>"><?= htmlspecialchars($task['status']) ?></span>
            </a>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<!-- ========== サマリーウィジェット ========== -->
<div class="widget" id="widget-summary" data-widget="summary">
    <div class="widget-header">
        <h3>
            サマリー
        </h3>
        <button class="widget-toggle" onclick="toggleWidget('summary')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </button>
    </div>
    <div class="widget-body">
        <div class="summary-grid">
            <a href="finance.php" class="summary-card blue">
                <h4>今月の売上</h4>
                <div class="value">¥<?= number_format($currentMonthSales) ?></div>
                <?php if ($lastMonthSales > 0): ?>
                <span class="badge">前月比 <?= $salesChange >= 0 ? '+' : '' ?><?= $salesChange ?>%</span>
                <?php endif; ?>
            </a>
            <a href="master.php" class="summary-card green">
                <h4>進行中案件</h4>
                <div class="value"><?= $activeProjects ?>件</div>
                <div class="sub">全<?= count($data['projects'] ?? []) ?>件</div>
            </a>
            <a href="troubles.php?status=未対応" class="summary-card red">
                <h4>未対応トラブル</h4>
                <div class="value"><?= $pending ?>件</div>
                <?php if ($overdueIssues > 0): ?>
                <span class="badge">期限超過 <?= $overdueIssues ?>件</span>
                <?php endif; ?>
            </a>
            <?php if ($chatConfigured && !empty($alcoholChatConfig['space_id'])): ?>
            <div class="summary-card purple">
                <h4>アルコールチェック</h4>
                <div class="alcohol-sync-btn">
                    <button onclick="syncAlcoholCheck()" id="alcoholSyncBtn">同期する</button>
                    <div class="alcohol-sync-status" id="alcoholSyncStatus"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========== カレンダーウィジェット ========== -->
<?php if ($calendarConfigured): ?>
<div class="widget calendar-widget" id="widget-calendar" data-widget="calendar">
    <div class="widget-header">
        <h3>
            今日の予定
            <span class="today-date"><?= date('n月j日（') . ['日','月','火','水','木','金','土'][date('w')] . '）' ?></span>
        </h3>
        <button class="widget-toggle" onclick="toggleWidget('calendar')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </button>
    </div>
    <div class="widget-body">
        <div id="calendarEvents" class="event-list">
            <div class="no-events">読み込み中...</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========== アラートウィジェット ========== -->
<?php if ($alertCount > 0): ?>
<div class="widget alert-widget" id="widget-alerts" data-widget="alerts">
    <div class="widget-header">
        <h3>
            アラート通知
            <span class="alert-badge"><?= $alertCount ?></span>
        </h3>
        <button class="widget-toggle" onclick="toggleWidget('alerts')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </button>
    </div>
    <div class="widget-body">
        <div class="alert-filters">
            <button class="alert-filter-btn active" onclick="filterAlerts('all')">すべて (<?= $alertCount ?>)</button>
            <?php
            $categoryCount = [];
            foreach ($alerts as $a) {
                $cat = $a['category'];
                $categoryCount[$cat] = ($categoryCount[$cat] ?? 0) + 1;
            }
            $categoryLabels = ['project' => '案件', 'trouble' => 'トラブル', 'alcohol' => 'アルコール'];
            foreach ($categoryCount as $cat => $cnt):
            ?>
            <button class="alert-filter-btn" onclick="filterAlerts('<?= $cat ?>')"><?= $categoryLabels[$cat] ?? $cat ?> (<?= $cnt ?>)</button>
            <?php endforeach; ?>
        </div>
        <div class="alert-list">
            <?php foreach ($alerts as $alert): ?>
            <a href="<?= htmlspecialchars($alert['link']) ?>" class="alert-item alert-<?= $alert['type'] ?>" data-category="<?= $alert['category'] ?>">
                <div class="alert-icon">
                    <?php if ($alert['type'] === 'danger'): ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <?php else: ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <?php endif; ?>
                </div>
                <div class="alert-content">
                    <div class="alert-title"><?= htmlspecialchars($alert['title']) ?></div>
                    <div class="alert-message"><?= htmlspecialchars($alert['message']) ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========== トラブル統計ウィジェット ========== -->
<div class="widget trouble-widget" id="widget-troubles" data-widget="troubles">
    <div class="widget-header">
        <h3>
            トラブル対応状況
        </h3>
        <button class="widget-toggle" onclick="toggleWidget('troubles')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </button>
    </div>
    <div class="widget-body">
        <div class="trouble-stats">
            <a href="troubles.php" class="trouble-stat-item">
                <div class="value"><?= $total ?></div>
                <div class="label">総件数</div>
            </a>
            <a href="troubles.php?status=未対応" class="trouble-stat-item pending">
                <div class="value"><?= $pending ?></div>
                <div class="label">未対応</div>
            </a>
            <a href="troubles.php?status=対応中" class="trouble-stat-item in-progress">
                <div class="value"><?= $inProgress ?></div>
                <div class="label">対応中</div>
            </a>
            <a href="troubles.php?status=保留" class="trouble-stat-item on-hold">
                <div class="value"><?= $onHold ?></div>
                <div class="label">保留</div>
            </a>
            <a href="troubles.php?status=完了" class="trouble-stat-item completed">
                <div class="value"><?= $completed ?></div>
                <div class="label">完了</div>
            </a>
        </div>
        <div class="completion-bar">
            <div class="header">
                <span>完了率</span>
                <span class="rate"><?= $completionRate ?>%</span>
            </div>
            <div class="bar">
                <div class="fill" style="width: <?= $completionRate ?>%"></div>
            </div>
        </div>
    </div>
</div>

</div><!-- /.widget-container -->

</div><!-- /.page-container -->

<!-- 設定モーダル -->
<div class="settings-modal" id="settingsModal">
    <div class="settings-modal-content">
        <div class="settings-modal-header">
            <h3>表示設定</h3>
            <button class="settings-modal-close" onclick="closeDashboardSettings()">&times;</button>
        </div>
        <div class="settings-modal-body">
            <p style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 1rem;">表示するウィジェットを選択してください</p>
            <?php
            $widgetLabels = [
                'tasks' => '今日やること',
                'summary' => 'サマリー',
                'calendar' => '今日の予定',
                'alerts' => 'アラート通知',
                'troubles' => 'トラブル対応状況'
            ];
            foreach ($widgetLabels as $key => $label):
                $enabled = $dashboardSettings['widgets'][$key]['enabled'] ?? true;
            ?>
            <div class="widget-toggle-item">
                <span class="widget-toggle-label"><?= $label ?></span>
                <label class="toggle-switch">
                    <input type="checkbox" data-widget="<?= $key ?>" <?= $enabled ? 'checked' : '' ?> onchange="updateWidgetVisibility('<?= $key ?>', this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// CSRFトークン
const csrfToken = '<?= generateCsrfToken() ?>';

// ウィジェットの折りたたみ
function toggleWidget(widgetId) {
    const widget = document.getElementById('widget-' + widgetId);
    if (widget) {
        widget.classList.toggle('collapsed');
        saveWidgetState();
    }
}

// 設定モーダル
function openDashboardSettings() {
    document.getElementById('settingsModal').classList.add('active');
}
function closeDashboardSettings() {
    document.getElementById('settingsModal').classList.remove('active');
}

// ウィジェット表示切り替え
function updateWidgetVisibility(widgetId, enabled) {
    const widget = document.getElementById('widget-' + widgetId);
    if (widget) {
        widget.classList.toggle('hidden', !enabled);
    }
    saveWidgetSettings();
}

// 設定を保存
function saveWidgetSettings() {
    const widgets = {};
    document.querySelectorAll('.settings-modal-body input[type="checkbox"]').forEach(cb => {
        const widgetId = cb.dataset.widget;
        widgets[widgetId] = { enabled: cb.checked };
    });

    fetch('../api/dashboard-settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ widgets })
    }).catch(err => console.error('設定保存エラー:', err));
}

// 折りたたみ状態を保存
function saveWidgetState() {
    const collapsed = [];
    document.querySelectorAll('.widget.collapsed').forEach(w => {
        collapsed.push(w.dataset.widget);
    });

    fetch('../api/dashboard-settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ collapsed })
    }).catch(err => console.error('状態保存エラー:', err));
}

// 初期状態を適用
(function() {
    const settings = <?= json_encode($dashboardSettings) ?>;

    // ウィジェット表示/非表示
    Object.entries(settings.widgets || {}).forEach(([id, cfg]) => {
        const widget = document.getElementById('widget-' + id);
        if (widget && cfg.enabled === false) {
            widget.classList.add('hidden');
        }
    });

    // 折りたたみ状態
    (settings.collapsed || []).forEach(id => {
        const widget = document.getElementById('widget-' + id);
        if (widget) {
            widget.classList.add('collapsed');
        }
    });
})();

// アラートフィルター
function filterAlerts(category) {
    document.querySelectorAll('.alert-filter-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    document.querySelectorAll('.alert-item').forEach(item => {
        if (category === 'all' || item.dataset.category === category) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

// モーダル外クリックで閉じる
document.getElementById('settingsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDashboardSettings();
    }
});

<?php if ($calendarConfigured): ?>
// Google Calendar イベント読み込み
(function() {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 8000);

    fetch('../api/calendar-events.php', { signal: controller.signal })
        .then(r => r.json())
        .then(data => {
            clearTimeout(timeoutId);
            const container = document.getElementById('calendarEvents');
            if (!container) return;

            if (data.error) {
                container.innerHTML = '<div class="no-events">カレンダー取得エラー</div>';
                return;
            }

            const events = data.events || [];
            if (events.length === 0) {
                container.innerHTML = '<div class="no-events">今日の予定はありません</div>';
                return;
            }

            let html = '';
            events.forEach(ev => {
                let timeStr = '';
                if (ev.isAllDay) {
                    timeStr = '終日';
                } else if (ev.start) {
                    const start = new Date(ev.start);
                    timeStr = start.getHours().toString().padStart(2,'0') + ':' + start.getMinutes().toString().padStart(2,'0');
                    if (ev.end) {
                        const end = new Date(ev.end);
                        timeStr += '〜' + end.getHours().toString().padStart(2,'0') + ':' + end.getMinutes().toString().padStart(2,'0');
                    }
                }

                html += '<div class="event-item">';
                html += '<div class="event-time' + (ev.isAllDay ? ' all-day' : '') + '">' + timeStr + '</div>';
                html += '<div class="event-title">' + (ev.title || '(タイトルなし)').replace(/</g,'&lt;') + '</div>';
                html += '</div>';
            });
            container.innerHTML = html;
        })
        .catch(err => {
            clearTimeout(timeoutId);
            const container = document.getElementById('calendarEvents');
            if (container) {
                container.innerHTML = '<div class="no-events">' + (err.name === 'AbortError' ? 'タイムアウト' : 'カレンダー取得エラー') + '</div>';
            }
        });
})();
<?php endif; ?>

<?php if ($chatConfigured && !empty($alcoholChatConfig['space_id'])): ?>
// アルコールチェック同期
function syncAlcoholCheck() {
    const btn = document.getElementById('alcoholSyncBtn');
    const statusDiv = document.getElementById('alcoholSyncStatus');
    const today = new Date().toISOString().split('T')[0];

    btn.disabled = true;
    btn.textContent = '同期中...';
    statusDiv.style.color = 'rgba(255,255,255,0.8)';
    statusDiv.textContent = '処理中...';

    const formData = new FormData();
    formData.append('action', 'sync_images');
    formData.append('date', today);

    fetch('../api/alcohol-chat-sync.php', {
        method: 'POST',
        headers: {'X-CSRF-Token': csrfToken},
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = '同期する';

        if (data.success) {
            statusDiv.style.color = '#a7f3d0';
            statusDiv.textContent = '✓ ' + (data.imported || 0) + '件取得';
        } else {
            statusDiv.style.color = '#fecaca';
            statusDiv.textContent = '✗ ' + (data.error || 'エラー');
        }

        setTimeout(() => { statusDiv.textContent = ''; }, 5000);
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = '同期する';
        statusDiv.style.color = '#fecaca';
        statusDiv.textContent = '✗ 通信エラー';
        setTimeout(() => { statusDiv.textContent = ''; }, 5000);
    });
}
<?php endif; ?>
</script>

<?php require_once '../functions/footer.php'; ?>
