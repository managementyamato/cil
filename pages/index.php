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

// 日付
$todayDate = date('Y-m-d');
$currentMonth = date('Y-m');
$soonDate = date('Y-m-d', strtotime('+7 days'));

// トラブル統計
$total = count($data['troubles'] ?? []);
$pending = count(array_filter($data['troubles'] ?? [], function($t) { return ($t['status'] ?? '') === '未対応'; }));
$inProgress = count(array_filter($data['troubles'] ?? [], function($t) { return ($t['status'] ?? '') === '対応中'; }));
$onHold = count(array_filter($data['troubles'] ?? [], function($t) { return ($t['status'] ?? '') === '保留'; }));
$completed = count(array_filter($data['troubles'] ?? [], function($t) { return ($t['status'] ?? '') === '完了'; }));
$completionRate = $total > 0 ? round(($completed / $total) * 100) : 0;

// 案件統計
$projects = $data['projects'] ?? [];
$activeProjects = count(array_filter($projects, function($p) { return ($p['status'] ?? '') !== '完了'; }));
$completedProjects = count(array_filter($projects, function($p) { return ($p['status'] ?? '') === '完了'; }));

// 今月のトラブル件数
$currentMonthTroubles = 0;
$lastMonthTroubles = 0;
foreach ($data['troubles'] ?? [] as $t) {
    $createdAt = $t['occurred_date'] ?? $t['created_at'] ?? '';
    if (!empty($createdAt)) {
        $month = date('Y-m', strtotime(str_replace('/', '-', $createdAt)));
        if ($month === $currentMonth) {
            $currentMonthTroubles++;
        } elseif ($month === date('Y-m', strtotime('-1 month'))) {
            $lastMonthTroubles++;
        }
    }
}
$troubleMonthChange = $lastMonthTroubles > 0 ? round((($currentMonthTroubles - $lastMonthTroubles) / $lastMonthTroubles) * 100) : 0;

// 期限超過
$overdueCount = 0;
foreach ($data['troubles'] ?? [] as $t) {
    if (($t['status'] ?? '') === '完了') continue;
    $dl = $t['deadline'] ?? '';
    if (!empty($dl) && strtotime($dl) < strtotime('today')) {
        $overdueCount++;
    }
}

// 今月売上（finance.phpの閲覧権限がある場合のみ計算）
$currentMonthSales = 0;
$lastMonthSales = 0;
$salesChange = 0;
if (hasPermission(getPageViewPermission('finance.php'))) {
    $lastMonth = date('Y-m', strtotime('-1 month'));
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
    $salesChange = $lastMonthSales > 0 ? round((($currentMonthSales - $lastMonthSales) / $lastMonthSales) * 100, 1) : 0;
}

// 緊急トラブル（期限超過・今日期限）
$urgentTroubles = [];
$todayTroubles = [];
$weekTroubles = [];

foreach ($data['troubles'] ?? [] as $t) {
    if (($t['status'] ?? '') === '完了') continue;
    $deadline = $t['deadline'] ?? '';
    if (empty($deadline)) continue;

    $trouble = [
        'title' => ($t['pj_number'] ?? 'トラブル') . ' - ' . mb_substr($t['trouble_content'] ?? '', 0, 25),
        'deadline' => $deadline,
        'status' => $t['status'] ?? '',
        'link' => 'troubles.php?id=' . ($t['id'] ?? '')
    ];

    if ($deadline < $todayDate) {
        $trouble['days_over'] = (strtotime($todayDate) - strtotime($deadline)) / 86400;
        $urgentTroubles[] = $trouble;
    } elseif ($deadline === $todayDate) {
        $todayTroubles[] = $trouble;
    } elseif ($deadline <= $soonDate) {
        $weekTroubles[] = $trouble;
    }
}

// 本日の未提出アルコールチェック
$employees = $data['employees'] ?? [];
$missingAlcoholEmployees = [];
if (!empty($employees)) {
    $uploadStatus = getUploadStatusForDate($todayDate);
    $noCarUsage = getNoCarUsageForDate($todayDate);
    $targetEmployeeIds = getAlcoholCheckTargetEmployeesForDate($todayDate);

    foreach ($employees as $emp) {
        $empId = (string)($emp['id'] ?? '');
        if (empty($empId)) continue;
        if (!in_array($empId, $targetEmployeeIds, true)) continue;

        $hasUpload = isset($uploadStatus[$empId]);
        $hasNoCar = in_array($empId, $noCarUsage);
        if (!$hasUpload && !$hasNoCar) {
            $missingAlcoholEmployees[] = $emp['name'] ?? ('ID:' . $empId);
        }
    }
}

// 最近のアクティビティ
$recentActivities = [];
$allItems = [];

foreach (array_slice($data['troubles'] ?? [], -5) as $t) {
    $allItems[] = [
        'type' => 'trouble',
        'action' => ($t['status'] ?? '') === '完了' ? '完了' : '更新',
        'title' => $t['pj_number'] ?? 'トラブル',
        'date' => $t['updated_at'] ?? $t['created_at'] ?? '',
        'user' => $t['responder'] ?? '不明'
    ];
}
usort($allItems, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});
$recentActivities = array_slice($allItems, 0, 5);

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
/* ========== ダッシュボード - モノトーン配色 ========== */
:root {
    --dash-bg: #f8f9fa;
    --dash-card: #ffffff;
    --dash-border: #e0e0e0;
    --dash-text: #333333;
    --dash-text-light: #666666;
    --dash-text-muted: #999999;
    --dash-primary: #444444;
    --dash-primary-light: #f5f5f5;
    --dash-success: #555555;
    --dash-success-light: #f0f0f0;
    --dash-warning: #666666;
    --dash-warning-light: #f5f5f5;
    --dash-danger: #c62828;
    --dash-danger-light: #ffebee;
    --dash-purple: #555555;
    --dash-purple-light: #f5f5f5;
}

.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 0.5rem;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--dash-border);
}

.dashboard-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dash-text);
    letter-spacing: -0.02em;
}

.dashboard-date {
    font-size: 0.9rem;
    color: var(--dash-text-light);
    font-weight: 500;
}

/* ========== KPIカード ========== */
.kpi-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.kpi-card {
    background: var(--dash-card);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
    border: 1px solid var(--dash-border);
    text-decoration: none;
    display: block;
}

.kpi-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.kpi-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.kpi-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.kpi-icon svg {
    width: 22px;
    height: 22px;
}

.kpi-card.primary .kpi-icon { background: var(--dash-primary-light); color: var(--dash-primary); }
.kpi-card.success .kpi-icon { background: var(--dash-success-light); color: var(--dash-success); }
.kpi-card.warning .kpi-icon { background: var(--dash-warning-light); color: var(--dash-warning); }
.kpi-card.danger .kpi-icon { background: var(--dash-danger-light); color: var(--dash-danger); }
.kpi-card.purple .kpi-icon { background: var(--dash-purple-light); color: var(--dash-purple); }

.kpi-label {
    font-size: 0.85rem;
    color: var(--dash-text-light);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.kpi-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dash-text);
    line-height: 1.1;
}

.kpi-value .unit {
    font-size: 1rem;
    font-weight: 500;
    color: var(--dash-text-light);
    margin-left: 0.25rem;
}

.kpi-change {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    margin-top: 0.75rem;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-weight: 600;
}

.kpi-change.up {
    background: #f0f0f0;
    color: #333;
}
.kpi-change.down {
    background: var(--dash-danger-light);
    color: var(--dash-danger);
}

/* アルコール同期 */
.alcohol-sync-area {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.alcohol-sync-btn {
    background: #444;
    color: white;
    border: none;
    padding: 0.6rem 1.25rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}

.alcohol-sync-btn:hover {
    background: #333;
}

.alcohol-sync-status {
    font-size: 0.8rem;
    font-weight: 500;
}

/* ========== グリッドレイアウト ========== */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 1100px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

/* ========== ウィジェット共通 ========== */
.widget {
    background: var(--dash-card);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid var(--dash-border);
    overflow: hidden;
}

.widget-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--dash-border);
    background: #fafbfc;
}

.widget-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--dash-text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.widget-header h3 svg {
    color: var(--dash-text-light);
}

.widget-badge {
    background: var(--dash-danger);
    color: white;
    font-size: 0.75rem;
    padding: 0.25rem 0.625rem;
    border-radius: 6px;
    font-weight: 600;
}

.widget-body {
    padding: 1.25rem;
}

/* ========== タブ ========== */
.tab-buttons {
    display: flex;
    gap: 0.5rem;
    padding: 0.5rem;
    background: #f1f5f9;
    border-radius: 10px;
}

.tab-btn {
    flex: 1;
    padding: 0.625rem 1rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--dash-text-light);
    cursor: pointer;
    transition: all 0.15s;
}

.tab-btn:hover {
    color: var(--dash-text);
    background: rgba(255,255,255,0.5);
}

.tab-btn.active {
    background: white;
    color: #333;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.tab-btn .count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    background: #e2e8f0;
    color: var(--dash-text);
    border-radius: 10px;
    font-size: 0.7rem;
    margin-left: 0.5rem;
    font-weight: 700;
}

.tab-btn.active .count {
    background: #eee;
    color: #333;
}

.tab-btn.danger .count {
    background: var(--dash-danger-light);
    color: var(--dash-danger);
}

/* ========== 進捗リング ========== */
.progress-ring-container {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem;
}

.progress-ring {
    position: relative;
    width: 150px;
    height: 150px;
}

.progress-ring svg {
    transform: rotate(-90deg);
}

.progress-ring-bg {
    fill: none;
    stroke: #e2e8f0;
    stroke-width: 14;
}

.progress-ring-fill {
    fill: none;
    stroke: #555;
    stroke-width: 14;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.6s ease;
}

.progress-ring-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.progress-ring-value {
    font-size: 2.25rem;
    font-weight: 700;
    color: var(--dash-text);
}

.progress-ring-label {
    font-size: 0.85rem;
    color: var(--dash-text-light);
    font-weight: 500;
}

/* ========== 進捗バー ========== */
.progress-bars {
    padding: 0 1.25rem 1.25rem;
}

.progress-item {
    margin-bottom: 1.25rem;
}

.progress-item:last-child {
    margin-bottom: 0;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.progress-label span:first-child {
    color: var(--dash-text-light);
    font-weight: 500;
}

.progress-label span:last-child {
    font-weight: 700;
    color: var(--dash-text);
}

.progress-bar {
    height: 10px;
    background: #e2e8f0;
    border-radius: 5px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 5px;
    transition: width 0.5s ease;
}

.progress-fill.blue { background: #666; }
.progress-fill.green { background: #555; }
.progress-fill.yellow { background: #888; }
.progress-fill.red { background: #c62828; }

/* ========== アクティビティ ========== */
.activity-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.activity-item {
    display: flex;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid var(--dash-border);
}

.activity-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.activity-icon {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.activity-content {
    flex: 1;
}

.activity-text {
    font-size: 0.875rem;
    color: var(--dash-text);
    line-height: 1.4;
}

.activity-text strong {
    font-weight: 600;
}

.activity-time {
    font-size: 0.8rem;
    color: var(--dash-text-muted);
    margin-top: 0.25rem;
}

/* ========== クイックアクション ========== */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    padding: 1.25rem;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.625rem;
    padding: 1.25rem 1rem;
    background: #f8fafc;
    border: 1px solid var(--dash-border);
    border-radius: 10px;
    text-decoration: none;
    color: var(--dash-text);
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.15s;
}

.quick-action-btn:hover {
    background: #eee;
    border-color: #999;
    color: #333;
}

.quick-action-btn svg {
    width: 26px;
    height: 26px;
    color: var(--dash-text-light);
}

.quick-action-btn:hover svg {
    color: #333;
}

/* ========== 空状態 ========== */
.empty-state {
    text-align: center;
    padding: 2.5rem 1rem;
    color: var(--dash-text-muted);
}

.empty-state svg {
    width: 48px;
    height: 48px;
    margin-bottom: 1rem;
    opacity: 0.4;
}

.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

/* ========== ステータスグリッド ========== */
.status-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    padding: 1.25rem;
}

.status-item {
    text-align: center;
    padding: 1rem 0.5rem;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid var(--dash-border);
}

.status-count {
    font-size: 1.5rem;
    font-weight: 700;
}

.status-label {
    font-size: 0.75rem;
    color: var(--dash-text-light);
    margin-top: 0.25rem;
    font-weight: 500;
}

/* Tab content */
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}

/* ========== カレンダーイベント ========== */
.event-list {
    max-height: 220px;
    overflow-y: auto;
}

.event-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    background: #f8fafc;
    border: 1px solid var(--dash-border);
}

.event-item:last-child { margin-bottom: 0; }

.event-calendar-color {
    width: 4px;
    height: 28px;
    border-radius: 2px;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.event-time {
    min-width: 90px;
    font-size: 0.8rem;
    font-weight: 700;
    color: #444;
}

.event-time.all-day {
    color: #555;
}

.event-title {
    flex: 1;
    font-weight: 500;
    color: var(--dash-text);
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.no-events {
    text-align: center;
    padding: 2rem;
    color: var(--dash-text-muted);
    font-size: 0.9rem;
}

.today-date {
    font-size: 0.8rem;
    color: var(--dash-text-muted);
    font-weight: 500;
    margin-left: auto;
}

/* ========== カラム ========== */
.left-column, .right-column {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
</style>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>ダッシュボード</h2>
        <span class="dashboard-date"><?= date('Y年n月j日') ?>（<?= ['日','月','火','水','木','金','土'][date('w')] ?>）</span>
    </div>

    <!-- KPIカード -->
    <div class="kpi-row">
        <?php if (hasPermission(getPageViewPermission('finance.php'))): ?>
        <a href="finance.php" class="kpi-card primary">
            <div class="kpi-header">
                <div class="kpi-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="kpi-label">今月売上</div>
            <div class="kpi-value">¥<?= number_format($currentMonthSales / 10000) ?><span class="unit">万</span></div>
            <?php if ($salesChange != 0): ?>
            <div class="kpi-change <?= $salesChange >= 0 ? 'up' : 'down' ?>">
                <?= $salesChange >= 0 ? '↑' : '↓' ?> <?= abs($salesChange) ?>% 前月比
            </div>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if (hasPermission(getPageViewPermission('troubles.php'))): ?>
        <a href="troubles.php" class="kpi-card <?= $pending > 0 ? 'danger' : 'success' ?>">
            <div class="kpi-header">
                <div class="kpi-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
            </div>
            <div class="kpi-label">未対応トラブル</div>
            <div class="kpi-value"><?= $pending ?><span class="unit">件</span></div>
            <?php if ($overdueCount > 0): ?>
            <div class="kpi-change down"><?= $overdueCount ?>件 期限超過</div>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if (hasPermission(getPageViewPermission('troubles.php'))): ?>
        <a href="troubles.php" class="kpi-card warning">
            <div class="kpi-header">
                <div class="kpi-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                </div>
            </div>
            <div class="kpi-label">今月トラブル</div>
            <div class="kpi-value"><?= $currentMonthTroubles ?><span class="unit">件</span></div>
            <?php if ($troubleMonthChange != 0): ?>
            <div class="kpi-change <?= $troubleMonthChange > 0 ? 'up' : 'down' ?>"><?= $troubleMonthChange > 0 ? '+' : '' ?><?= $troubleMonthChange ?>% 前月比</div>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if (hasPermission(getPageViewPermission('troubles.php'))): ?>
        <div class="kpi-card success">
            <div class="kpi-header">
                <div class="kpi-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                    </svg>
                </div>
            </div>
            <div class="kpi-label">対応完了率</div>
            <div class="kpi-value"><?= $completionRate ?><span class="unit">%</span></div>
        </div>
        <?php endif; ?>

        <?php if ($chatConfigured && !empty($alcoholChatConfig['space_id']) && hasPermission(getPageViewPermission('photo-attendance.php'))): ?>
        <div class="kpi-card purple">
            <div class="kpi-header">
                <div class="kpi-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 2h8l4 10H4L8 2z"/><path d="M12 12v10"/><path d="M8 22h8"/>
                    </svg>
                </div>
            </div>
            <div class="kpi-label">アルコールチェック</div>
            <div class="alcohol-sync-area">
                <button class="alcohol-sync-btn" id="alcoholSyncBtn">同期する</button>
                <div class="alcohol-sync-status" id="alcoholSyncStatus">
                    <?php if (!empty($missingAlcoholEmployees)): ?>
                    <span     class="text-dash-danger"><?= count($missingAlcoholEmployees) ?>名未提出</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- メインコンテンツ -->
    <div class="dashboard-grid">
        <!-- 左カラム -->
        <div class="left-column">


            <?php if (hasPermission(getPageViewPermission('troubles.php'))): ?>
            <!-- トラブル対応状況 -->
            <div class="widget">
                <div class="widget-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                        </svg>
                        トラブル対応状況
                    </h3>
                </div>
                <div class="progress-ring-container">
                    <div class="progress-ring">
                        <svg width="150" height="150" viewBox="0 0 150 150">
                            <circle class="progress-ring-bg" cx="75" cy="75" r="60"/>
                            <circle class="progress-ring-fill" cx="75" cy="75" r="60"
                                stroke-dasharray="377"
                                stroke-dashoffset="<?= 377 * (1 - $completionRate / 100) ?>"/>
                        </svg>
                        <div class="progress-ring-text">
                            <div class="progress-ring-value"><?= $completionRate ?>%</div>
                            <div class="progress-ring-label">完了率</div>
                        </div>
                    </div>
                </div>
                <div class="progress-bars">
                    <div class="progress-item">
                        <div class="progress-label">
                            <span>未対応</span>
                            <span><?= $pending ?>件</span>
                        </div>
                        <div class="progress-bar">
                            <div         class="progress-fill red" style="width: <?= $total > 0 ? ($pending / $total * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <div class="progress-item">
                        <div class="progress-label">
                            <span>対応中</span>
                            <span><?= $inProgress ?>件</span>
                        </div>
                        <div class="progress-bar">
                            <div         class="progress-fill yellow" style="width: <?= $total > 0 ? ($inProgress / $total * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <div class="progress-item">
                        <div class="progress-label">
                            <span>完了</span>
                            <span><?= $completed ?>件</span>
                        </div>
                        <div class="progress-bar">
                            <div         class="progress-fill green" style="width: <?= $total > 0 ? ($completed / $total * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- アクティビティ -->
            <div class="widget">
                <div class="widget-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                        最近のアクティビティ
                    </h3>
                </div>
                <div class="widget-body">
                    <?php if (empty($recentActivities)): ?>
                    <div class="empty-state">
                        <p>最近のアクティビティはありません</p>
                    </div>
                    <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($recentActivities as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-icon">
                                <?php if ($activity['type'] === 'trouble'): ?>
                                    <?= $activity['action'] === '完了' ? '✓' : '📝' ?>
                                <?php else: ?>
                                    📁
                                <?php endif; ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <strong><?= htmlspecialchars($activity['user']) ?></strong>が
                                    <?= htmlspecialchars($activity['title']) ?>を<?= htmlspecialchars($activity['action']) ?>
                                </div>
                                <div class="activity-time">
                                    <?php
                                    $date = $activity['date'];
                                    if ($date) {
                                        $diff = time() - strtotime($date);
                                        if ($diff < 3600) {
                                            echo floor($diff / 60) . '分前';
                                        } elseif ($diff < 86400) {
                                            echo floor($diff / 3600) . '時間前';
                                        } else {
                                            echo floor($diff / 86400) . '日前';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 右カラム -->
        <div class="right-column">

            <!-- カレンダー（今日の予定） -->
            <?php if ($calendarConfigured): ?>
            <div class="widget">
                <div class="widget-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        今日の予定
                    </h3>
                    <span class="today-date"><?= date('n月j日') ?>（<?= ['日','月','火','水','木','金','土'][date('w')] ?>）</span>
                </div>
                <div class="widget-body">
                    <div id="calendarEvents" class="event-list">
                        <div class="no-events">読み込み中...</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- クイックアクション -->
            <div class="widget">
                <div class="widget-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                        </svg>
                        クイックアクション
                    </h3>
                </div>
                <div class="quick-actions">
                    <?php if (hasPermission(getPageViewPermission('trouble-form.php'))): ?>
                    <a href="trouble-form.php" class="quick-action-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        トラブル登録
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission(getPageViewPermission('master.php'))): ?>
                    <a href="master.php" class="quick-action-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
                        案件一覧
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission(getPageViewPermission('troubles.php'))): ?>
                    <a href="troubles.php?status=未対応" class="quick-action-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        未対応一覧
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission(getPageViewPermission('photo-attendance.php'))): ?>
                    <a href="photo-attendance.php" class="quick-action-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                        </svg>
                        アルコール確認
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (hasPermission(getPageViewPermission('master.php'))): ?>
            <!-- 案件ステータス -->
            <div class="widget">
                <div class="widget-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        </svg>
                        案件ステータス
                    </h3>
                </div>
                <div class="status-grid">
                    <?php
                    $statusColors = [
                        '案件発生' => '#999',
                        '成約' => '#666',
                        '製品手配中' => '#777',
                        '設置予定' => '#555',
                        '設置済' => '#444',
                        '完了' => '#333'
                    ];
                    $statusList = ['案件発生', '成約', '製品手配中', '設置予定', '設置済', '完了'];
                    foreach ($statusList as $status):
                        $count = count(array_filter($projects, function($p) use ($status) {
                            return ($p['status'] ?? '') === $status;
                        }));
                    ?>
                    <div class="status-item">
                        <div         class="status-count" style="color: <?= $statusColors[$status] ?>"><?= $count ?></div>
                        <div class="status-label"><?= $status ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script<?= nonceAttr() ?>>
const csrfToken = '<?= generateCsrfToken() ?>';

function switchTab(btn, tabId) {
    btn.parentElement.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const widget = btn.closest('.widget');
    widget.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    widget.querySelector('#tab-' + tabId).classList.add('active');
}

<?php if ($calendarConfigured): ?>
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

                const calColor = ev.calendarColor || '#4285f4';
                html += '<div class="event-item">';
                html += '<span         class="event-calendar-color" style="background:' + escapeHtml(calColor) + '"></span>';
                html += '<div class="event-time' + (ev.isAllDay ? ' all-day' : '') + '">' + escapeHtml(timeStr) + '</div>';
                html += '<div class="event-title">' + escapeHtml(ev.title || '(タイトルなし)') + '</div>';
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
function syncAlcoholCheck() {
    const btn = document.getElementById('alcoholSyncBtn');
    const statusDiv = document.getElementById('alcoholSyncStatus');
    const today = new Date().toISOString().split('T')[0];

    btn.disabled = true;
    btn.textContent = '同期中...';
    statusDiv.style.color = 'var(--dash-text-light)';
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
            statusDiv.style.color = 'var(--dash-success)';
            statusDiv.textContent = '✓ ' + (data.imported || 0) + '件取得';
        } else {
            statusDiv.style.color = 'var(--dash-danger)';
            statusDiv.textContent = '✗ ' + (data.error || 'エラー');
        }

        setTimeout(() => { statusDiv.textContent = ''; }, 5000);
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = '同期する';
        statusDiv.style.color = 'var(--dash-danger)';
        statusDiv.textContent = '✗ 通信エラー';
        setTimeout(() => { statusDiv.textContent = ''; }, 5000);
    });
}
<?php endif; ?>

// アルコールチェック同期ボタン
document.getElementById('alcoholSyncBtn')?.addEventListener('click', syncAlcoholCheck);
</script>

<?php require_once '../functions/footer.php'; ?>
