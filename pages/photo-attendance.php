<?php
/**
 * アルコールチェック管理 - メイン画面
 */

require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../functions/photo-attendance-functions.php';
require_once __DIR__ . '/../api/google-chat.php';

// タイムゾーンを日本時間に設定
date_default_timezone_set('Asia/Tokyo');

// 管理者・編集者権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

// 日付（GETパラメータがあればその日付、なければ本日）
$today = $_GET['date'] ?? date('Y-m-d');
// 日付のバリデーション
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $today) || !strtotime($today)) {
    $today = date('Y-m-d');
}
$isToday = ($today === date('Y-m-d'));

// Google Chat連携状態を確認
$googleChat = new GoogleChatClient();
$chatConfigured = $googleChat->isConfigured();

// アルコールチェック用Chat設定を取得
$alcoholChatConfigFile = __DIR__ . '/../config/alcohol-chat-config.json';
$alcoholChatConfig = file_exists($alcoholChatConfigFile)
    ? json_decode(file_get_contents($alcoholChatConfigFile), true)
    : [];

// 従業員一覧を取得
$allEmployees = getEmployees();

// アルコールチェック対象者（その日に同期で取得できた従業員のみ）
$targetEmployeeIds = getAlcoholCheckTargetEmployeesForDate($today);
$employees = array_filter($allEmployees, function($emp) use ($targetEmployeeIds) {
    // 型を文字列に統一して比較
    $empId = (string)($emp['id'] ?? '');
    return in_array($empId, $targetEmployeeIds, true);
});
$employees = array_values($employees); // インデックスを振り直し

// 従業員データがない場合
if (empty($allEmployees)) {
    require_once __DIR__ . '/../functions/header.php';
    echo '<div         class="card max-w-800 margin-auto">';
    echo '<div class="card-header"><h2  class="m-0">従業員データが登録されていません</h2></div>';
    echo '<div class="card-body">';
    echo '<p>アルコールチェック管理を使用するには、まず従業員マスタに従業員を登録してください。</p>';
    echo '<a href="employees.php" class="btn btn-primary">従業員マスタへ</a>';
    echo '</div></div>';
    require_once __DIR__ . '/../functions/footer.php';
    exit;
}

// アルコールチェック対象者がいない場合（同期実績がない場合）
$showNoTargetMessage = empty($employees);

// 指定日の写真アップロード状況を取得
$uploadStatus = getUploadStatusForDate($today);

// 指定日の車不使用申請を取得
$noCarUsageIds = getNoCarUsageForDate($today);

// 未紐付けの画像を取得（Chatからインポートしたが従業員に紐付いていないもの）
$unassignedPhotos = getUnassignedPhotosForDate($today);

// 月次統計データ
$selectedMonth = $_GET['report_month'] ?? date('Y-m');
// 月フォーマットのバリデーション
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth) || !strtotime($selectedMonth . '-01')) {
    $selectedMonth = date('Y-m');
}
$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$daysInMonth = (int)date('t', strtotime($monthStart));
$todayOrEnd = (date('Y-m') === $selectedMonth) ? date('Y-m-d') : $monthEnd;
$workingDaysSoFar = 0;
// Count weekdays
for ($d = $monthStart; $d <= $todayOrEnd; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
    $dow = date('N', strtotime($d));
    if ($dow <= 5) $workingDaysSoFar++; // Mon-Fri
}

// Count uploads per employee per day this month
$employeeMonthly = [];
foreach ($employees as $emp) {
    $empId = $emp['id'] ?? '';
    $empName = $emp['name'] ?? '';
    if (empty($empId)) continue;
    $employeeMonthly[$empId] = ['name' => $empName, 'days' => 0, 'dates' => []];
}

// Read attendance data
$attDataFile = dirname(__DIR__) . '/config/photo-attendance-data.json';
$attData = [];
if (file_exists($attDataFile)) {
    $attData = json_decode(file_get_contents($attDataFile), true) ?: [];
}
foreach ($attData as $upload) {
    $uploadDate = $upload['upload_date'] ?? '';
    $empId = $upload['employee_id'] ?? '';
    if ($uploadDate >= $monthStart && $uploadDate <= $monthEnd) {
        if (isset($employeeMonthly[$empId]) && !in_array($uploadDate, $employeeMonthly[$empId]['dates'])) {
            $employeeMonthly[$empId]['days']++;
            $employeeMonthly[$empId]['dates'][] = $uploadDate;
        }
    }
}

$totalEmployees = count($employeeMonthly);
$avgRate = 0;
if ($totalEmployees > 0 && $workingDaysSoFar > 0) {
    $totalDays = 0;
    foreach ($employeeMonthly as $em) {
        $totalDays += $em['days'];
    }
    $avgRate = round(($totalDays / ($totalEmployees * $workingDaysSoFar)) * 100, 1);
}

// 表示中の日付の未提出者（対象者のみ）
$todayDate = $today;
$todayMissing = [];
// data.json から no_car_usage を取得（getData経由）
$dataJson = getData();
$noCarUsageData = $dataJson['no_car_usage'] ?? [];

foreach ($employees as $emp) {
    $empId = (string)($emp['id'] ?? '');
    if (empty($empId)) continue;
    if (empty($emp['leave_date'])) { // Only active employees
        $found = false;
        // Check attendance uploads
        foreach ($attData as $upload) {
            if (($upload['upload_date'] ?? '') === $todayDate && (string)($upload['employee_id'] ?? '') === $empId) {
                $found = true;
                break;
            }
        }
        // Also check no-car-usage
        if (!$found) {
            foreach ($noCarUsageData as $ncu) {
                if (($ncu['date'] ?? '') === $todayDate && (string)($ncu['employeeId'] ?? '') === $empId) {
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

require_once __DIR__ . '/../functions/header.php';
?>

<style<?= nonceAttr() ?>>
/* アルコールチェック管理固有のスタイル */

/* カードからはみ出さないように */
.card-body {
    overflow-x: auto;
}

.status-grid {
    display: grid;
    gap: 0.5rem;
    margin-top: 20px;
    overflow-x: auto;
    max-width: 100%;
}

.employee-row {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.5rem;
    align-items: center;
    background: white;
    padding: 0.75rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.2s;
}

@media (min-width: 768px) {
    .employee-row {
        grid-template-columns: minmax(120px, 1.2fr) minmax(80px, 0.8fr) repeat(4, minmax(70px, 0.8fr));
        gap: 0.5rem;
        padding: 0.75rem 0.5rem;
    }
}

@media (min-width: 1200px) {
    .employee-row {
        grid-template-columns: 200px 150px 150px 150px 150px 120px;
        gap: 1rem;
        padding: 0.75rem 1rem;
    }
}

.employee-row:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transform: translateY(-1px);
}

.employee-row.complete {
    background: #e8f5e9;
}

.employee-row.partial {
    background: #fff3e0;
}

.employee-row.missing {
    background: #ffebee;
}

.employee-row.no-car {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
}

.employee-name {
    font-weight: bold;
    color: var(--gray-900);
    font-size: 0.9rem;
    word-break: break-word;
}

.vehicle-number {
    font-size: 0.75rem;
    color: var(--gray-600);
    word-break: break-all;
}

/* スマホ表示時のカードレイアウト */
@media (max-width: 767px) {
    .employee-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .employee-row > div:first-child {
        width: 100%;
        margin-bottom: 0.25rem;
    }
    .employee-row .check-status {
        flex: 1;
        min-width: 45%;
        justify-content: flex-start;
    }
    .employee-row .check-status::before {
        font-size: 0.65rem;
        color: var(--gray-500);
        margin-right: 0.25rem;
    }
    .employee-row .check-status:nth-child(2)::before { content: "1回目:"; }
    .employee-row .check-status:nth-child(3)::before { content: "時刻:"; }
    .employee-row .check-status:nth-child(4)::before { content: "2回目:"; }
    .employee-row .check-status:nth-child(5)::before { content: "時刻:"; }
    .employee-row .status-badge {
        width: 100%;
        justify-content: center;
        margin-top: 0.25rem;
    }
}

@media (min-width: 768px) {
    .employee-name {
        font-size: 1rem;
    }

    .vehicle-number {
        font-size: 0.875rem;
    }
}

.check-status {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
}

@media (min-width: 768px) {
    .check-status {
        gap: 0.5rem;
        font-size: 0.875rem;
    }
}

.check-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
}

.check-icon.checked {
    background: #4caf50;
    color: white;
}

.check-icon.unchecked {
    background: #e0e0e0;
    color: #999;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge.complete {
    background: #c8e6c9;
    color: #2e7d32;
}

.status-badge.partial {
    background: #ffe0b2;
    color: #e65100;
}

.status-badge.missing {
    background: #ffcdd2;
    color: #c62828;
}

.header-row {
    display: none;
    grid-template-columns: 1fr;
    gap: 0.5rem;
    font-weight: bold;
    padding: 0.5rem 0.5rem;
    color: var(--gray-600);
    font-size: 0.75rem;
}

@media (min-width: 768px) {
    .header-row {
        display: grid;
        grid-template-columns: minmax(120px, 1.2fr) minmax(80px, 0.8fr) repeat(4, minmax(70px, 0.8fr));
        gap: 0.5rem;
    }
}

@media (min-width: 1200px) {
    .header-row {
        grid-template-columns: 200px 150px 150px 150px 150px 120px;
        gap: 1rem;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
}

/* モーダル */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 1.5rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.modal-close:hover {
    background: #f0f0f0;
}

.photo-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-top: 1rem;
}

.photo-detail-box {
    text-align: center;
}

.photo-detail-box h3 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: var(--gray-700);
}

.photo-detail-preview {
    width: 100%;
    max-width: 350px;
    height: auto;
    border-radius: 8px;
    border: 2px solid #ddd;
    margin-bottom: 0.5rem;
}

.no-photo-detail {
    width: 100%;
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
    border: 2px dashed #ddd;
    border-radius: 8px;
    color: #999;
    font-size: 0.875rem;
}

.photo-time {
    font-size: 0.875rem;
    color: #666;
    margin-top: 0.5rem;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.summary-card {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
}

.summary-number {
    font-size: 1.75rem;
    font-weight: bold;
    margin: 0.5rem 0;
}

.summary-label {
    color: var(--gray-600);
    font-size: 0.75rem;
    line-height: 1.3;
    word-break: keep-all;
}

@media (min-width: 768px) {
    .summary-cards {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .summary-card {
        padding: 1.5rem;
    }

    .summary-number {
        font-size: 2rem;
    }

    .summary-label {
        font-size: 0.875rem;
    }
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .summary-cards {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .summary-card {
        padding: 1rem;
    }

    .summary-number {
        font-size: 1.5rem;
    }

    .header-row {
        display: none;
    }

    .employee-row {
        grid-template-columns: 1fr;
        gap: 0.5rem;
        padding: 1rem;
    }

    .employee-name {
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }

    .vehicle-number {
        margin-bottom: 0.5rem;
    }

    .check-status {
        justify-content: flex-start;
    }

    .employee-row > div {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .employee-row > div:nth-child(3)::before {
        content: '1回目: ';
        font-weight: 500;
        min-width: 80px;
    }

    .employee-row > div:nth-child(4)::before {
        content: '1回目時刻: ';
        color: #666;
        min-width: 80px;
    }

    .employee-row > div:nth-child(5)::before {
        content: '2回目: ';
        font-weight: 500;
        min-width: 80px;
    }

    .employee-row > div:nth-child(6)::before {
        content: '2回目時刻: ';
        color: #666;
        min-width: 80px;
    }

    .photo-detail-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .modal-content {
        width: 95%;
        margin: 10px;
    }

    .modal-header {
        padding: 1rem;
    }

    .modal-body {
        padding: 1rem;
    }

    .photo-detail-preview {
        max-width: 100%;
    }
}
</style>

<div class="page-container">
    <div class="page-header">
        <div    class="d-flex align-center gap-075">
            <h2>アルコールチェック管理</h2>
            <div  class="d-flex align-center gap-05">
                <?php $prevDate = date('Y-m-d', strtotime($today . ' -1 day')); $nextDate = date('Y-m-d', strtotime($today . ' +1 day')); ?>
                <a href="?date=<?= $prevDate ?>" class="btn btn-sm btn-outline">&lt;</a>
                <input type="date" id="dateInput" value="<?= $today ?>" class="p-05 border-gray-300 rounded-6 text-087">
                <?php if ($today < date('Y-m-d')): ?>
                <a href="?date=<?= $nextDate ?>" class="btn btn-sm btn-outline">&gt;</a>
                <?php endif; ?>
                <?php if (!$isToday): ?>
                <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-primary">今日</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="page-header-actions">
            <?php if ($chatConfigured && !empty($alcoholChatConfig['space_id'])): ?>
            <button id="chatSyncBtn" class="btn btn-primary">Chat同期</button>
            <?php endif; ?>
            <button id="downloadBtn" class="btn btn-success">CSVダウンロード</button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- 対象者がいない場合のメッセージ -->
            <?php if ($showNoTargetMessage): ?>
            <div        class="alert-info-blue rounded-lg p-2 mb-2">
                <div    class="d-flex align-start gap-075">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1976d2" stroke-width="2"     class="flex-shrink-0 mt-2px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>
                        <strong     class="text-1565c0">本日のアルコールチェック対象者がいません</strong>
                        <div        class="text-sm mt-05 text-1976d2">
                            「Chat同期」ボタンで本日の画像を取得してください。<br>
                            同期後、紐付けられた従業員が対象者として表示されます。
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 本日の未提出者アラート -->
            <?php if (!empty($todayMissing) && date('N', strtotime($today)) <= 5): // Weekdays only ?>
            <div        class="alert-danger-red rounded-lg mb-2 d-flex align-start gap-075">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2"     class="flex-shrink-0 mt-2px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div>
                    <strong     class="text-e53e3e"><?= $isToday ? '本日' : htmlspecialchars(date('n/j', strtotime($today))) ?>未提出: <?= count($todayMissing) ?>名</strong>
                    <div        class="text-sm mt-05 text-red-900"><?= htmlspecialchars(implode('、', $todayMissing)) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- サマリー -->
            <?php
            $complete = 0;
            $partial = 0;
            $missing = 0;

            foreach ($employees as $emp) {
                $status = $uploadStatus[$emp['id']] ?? ['start' => null, 'end' => null];
                if ($status['start'] && $status['end']) {
                    $complete++;
                } elseif ($status['start'] || $status['end']) {
                    $partial++;
                } else {
                    $missing++;
                }
            }
            ?>

            <!-- 統計サマリー -->
            <div        class="stat-card d-flex flex-wrap align-center rounded-lg gap-20">
                <div    class="text-center min-w-80">
                    <div        class="stat-value-lg"><?= $complete ?></div>
                    <div     class="text-gray-666 text-11">完了</div>
                </div>
                <div    class="text-center min-w-80">
                    <div        class="stat-value-md <?= $partial > 0 ? 'text-c62828' : 'text-999' ?>"><?= $partial ?></div>
                    <div     class="text-gray-666 text-11">部分完了</div>
                </div>
                <div    class="text-center min-w-80">
                    <div        class="stat-value-md <?= $missing > 0 ? 'text-c62828' : 'text-999' ?>"><?= $missing ?></div>
                    <div     class="text-gray-666 text-11">未アップロード</div>
                </div>
            </div>

            <!-- ヘッダー -->
            <div class="header-row">
                <div>従業員名</div>
                <div>ナンバー</div>
                <div>1回目</div>
                <div>1回目時刻</div>
                <div>2回目</div>
                <div>2回目時刻</div>
            </div>

            <!-- 従業員一覧 -->
            <div class="status-grid">
                <?php foreach ($employees as $employee): ?>
                    <?php
                    $isNoCarUsage = in_array($employee['id'], $noCarUsageIds);

                    if ($isNoCarUsage) {
                        // 車不使用の場合
                        $rowClass = 'no-car';
                        ?>
                        <div class="employee-row <?= $rowClass ?>">
                            <div class="employee-name"><?= htmlspecialchars($employee['name']); ?></div>
                            <div class="vehicle-number"><?= htmlspecialchars($employee['vehicle_number'] ?? '-'); ?></div>
                            <div colspan="4"        class="font-bold text-center grid-col-3-7 text-1976d2">
                                🚗 本日は車不使用
                            </div>
                        </div>
                        <?php
                    } else {
                        // 通常の場合
                        $status = $uploadStatus[$employee['id']] ?? ['start' => null, 'end' => null];
                        $rowClass = 'missing';

                        if ($status['start'] && $status['end']) {
                            $rowClass = 'complete';
                        } elseif ($status['start'] || $status['end']) {
                            $rowClass = 'partial';
                        }

                        // JSONエンコードしてデータ属性に設定
                        $statusData = json_encode([
                            'name' => $employee['name'],
                            'employee_id' => $employee['id'],
                            'vehicle_number' => $employee['vehicle_number'] ?? '',
                            'start' => $status['start'] ? [
                                'id' => $status['start']['id'] ?? '',
                                'photo_path' => $status['start']['photo_path'] ?? '',
                                'uploaded_at' => $status['start']['uploaded_at'] ?? ''
                            ] : null,
                            'end' => $status['end'] ? [
                                'id' => $status['end']['id'] ?? '',
                                'photo_path' => $status['end']['photo_path'] ?? '',
                                'uploaded_at' => $status['end']['uploaded_at'] ?? ''
                            ] : null
                        ]);
                        ?>
                        <div class="employee-row <?= $rowClass ?>"
                             data-detail='<?= htmlspecialchars($statusData, ENT_QUOTES) ?>'>
                            <div class="employee-name"><?= htmlspecialchars($employee['name']); ?></div>
                            <div class="vehicle-number"><?= htmlspecialchars($employee['vehicle_number'] ?? '-'); ?></div>

                            <!-- 出勤前チェック -->
                            <div class="check-status">
                                <div class="check-icon <?= $status['start'] ? 'checked' : 'unchecked' ?>">
                                    <?= $status['start'] ? '✓' : '✗' ?>
                                </div>
                            </div>
                            <div   class="text-14">
                                <?= ($status['start'] && !empty($status['start']['uploaded_at'])) ? date('H:i', strtotime($status['start']['uploaded_at'])) : '-' ?>
                            </div>

                            <!-- 退勤前チェック -->
                            <div class="check-status">
                                <div class="check-icon <?= $status['end'] ? 'checked' : 'unchecked' ?>">
                                    <?= $status['end'] ? '✓' : '✗' ?>
                                </div>
                            </div>
                            <div   class="text-14">
                                <?= ($status['end'] && !empty($status['end']['uploaded_at'])) ? date('H:i', strtotime($status['end']['uploaded_at'])) : '-' ?>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                <?php endforeach; ?>
            </div>

            <!-- 未紐付け画像セクション -->
            <?php if (!empty($unassignedPhotos)): ?>
            <div     class="mt-4">
                <h3    class="mb-2 d-flex align-center gap-1 text-gray-700">
                    <span        class="badge-warning rounded text-xs">
                        <?= count($unassignedPhotos) ?>件
                    </span>
                    未紐付けの画像（従業員に割り当ててください）
                </h3>
                <div        class="gap-2 grid grid-auto-200">
                    <?php foreach ($unassignedPhotos as $photo):
                        // photo_pathとfilepathの両方に対応
                        $photoPath = $photo['photo_path'] ?? $photo['filepath'] ?? '';
                        $senderName = $photo['sender_name'] ?? $photo['original_sender'] ?? '不明';
                        $uploadTime = $photo['uploaded_at'] ?? $photo['upload_time'] ?? '';
                    ?>
                    <div         class="unassigned-photo-card rounded-lg bg-white" style="overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1)">
                        <div         class="unassigned-photo-preview cursor-pointer" style="aspect-ratio: 4/3; overflow: hidden" data-photo='<?= htmlspecialchars(json_encode(array_merge($photo, ['display_path' => $photoPath, 'display_sender' => $senderName, 'display_time' => $uploadTime])), ENT_QUOTES) ?>'>
                            <img src="../functions/<?= htmlspecialchars($photoPath) ?>"
                                 alt="未紐付け画像"
                                 style="width: 100%; height: 100%; object-fit: cover;"
                                 onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;color:#999;\'>画像なし</div>';">
                        </div>
                        <div    class="p-075">
                            <div    class="font-medium text-14"><?= htmlspecialchars($senderName) ?></div>
                            <div    class="text-xs text-gray-500"><?= htmlspecialchars($uploadTime) ?></div>
                            <?php if (!empty($photo['source']) && $photo['source'] === 'chat'): ?>
                            <div        class="mt-05" style="font-size: 0.7rem; color: var(--primary)">
                                Chatからインポート
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($photo['sender_user_id'])): ?>
                            <div        class="mt-05" style="font-size: 0.65rem; color: var(--gray-400); word-break: break-all" title="従業員マスタでこのIDを設定すると自動紐付けされます">
                                ID: <?= htmlspecialchars($photo['sender_user_id']) ?>
                            </div>
                            <?php endif; ?>
                            <div  class="mt-1">
                                <select class="form-input assign-photo-select" data-photo-id="<?= htmlspecialchars($photo['id']) ?>" style="width: 100%; font-size: 0.75rem; padding: 0.25rem;">
                                    <option value="">従業員を選択...</option>
                                    <?php foreach ($allEmployees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 未紐付け画像詳細モーダル -->
<div id="unassignedPhotoModal" class="modal">
    <div         class="modal-content max-w-600">
        <div class="modal-header">
            <h3  class="m-0">画像詳細</h3>
            <button type="button" class="modal-close" data-modal="unassignedPhotoModal">&times;</button>
        </div>
        <div class="modal-body">
            <div id="unassignedPhotoImage"  class="text-center mb-2"></div>
            <div id="unassignedPhotoInfo"></div>
        </div>
    </div>
</div>

<!-- 詳細モーダル -->
<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"  class="m-0">詳細情報</h2>
            <button type="button" class="modal-close" id="detailModalClose">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalVehicleNumber"    class="mb-2 text-gray-666"></div>
            <div class="photo-detail-grid">
                <div class="photo-detail-box">
                    <h3>1回目チェック</h3>
                    <div id="startPhotoContainer"></div>
                    <div id="startPhotoTime" class="photo-time"></div>
                    <div id="startReassignBtn"  class="mt-1"></div>
                </div>
                <div class="photo-detail-box">
                    <h3>2回目チェック</h3>
                    <div id="endPhotoContainer"></div>
                    <div id="endPhotoTime" class="photo-time"></div>
                    <div id="endReassignBtn"  class="mt-1"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script<?= nonceAttr() ?>>
const csrfToken = '<?= generateCsrfToken() ?>';

// XSS対策：HTMLエスケープ関数
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// 従業員リスト（紐付け変更用）
const employeeList = <?= json_encode(array_map(fn($e) => ['id' => $e['id'], 'name' => $e['name']], $allEmployees), JSON_UNESCAPED_UNICODE) ?>;

function showDetail(data) {
    const modal = document.getElementById('detailModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalVehicleNumber = document.getElementById('modalVehicleNumber');
    const startPhotoContainer = document.getElementById('startPhotoContainer');
    const startPhotoTime = document.getElementById('startPhotoTime');
    const startReassignBtn = document.getElementById('startReassignBtn');
    const endPhotoContainer = document.getElementById('endPhotoContainer');
    const endPhotoTime = document.getElementById('endPhotoTime');
    const endReassignBtn = document.getElementById('endReassignBtn');

    // タイトル設定
    modalTitle.textContent = data.name + ' - アルコールチェック詳細';
    modalVehicleNumber.textContent = 'ナンバー: ' + (data.vehicle_number || '-');

    // 出勤前チェック写真
    if (data.start) {
        const startPath = data.start.photo_path.startsWith('uploads/') ? '../functions/' + data.start.photo_path : data.start.photo_path;
        const startImg = document.createElement('img');
        startImg.src = startPath;
        startImg.alt = '出勤前チェック';
        startImg.className = 'photo-detail-preview';
        startImg.style.cursor = 'pointer';
        startImg.addEventListener('click', function() {
            window.open(this.src, '_blank');
        });
        startPhotoContainer.innerHTML = '';
        startPhotoContainer.appendChild(startImg);
        const startTime = new Date(data.start.uploaded_at);
        startPhotoTime.textContent = `アップロード時刻: ${startTime.toLocaleString('ja-JP')}`;
        // 紐付け変更ボタン
        if (data.start.id) {
            const btn = document.createElement('button');
            btn.className = 'btn btn-sm btn-outline';
            btn.style.fontSize = '0.75rem';
            btn.textContent = '紐付け変更';
            btn.addEventListener('click', () => showReassignModal(data.start.id, 'start'));
            startReassignBtn.innerHTML = '';
            startReassignBtn.appendChild(btn);
        } else {
            startReassignBtn.innerHTML = '';
        }
    } else {
        startPhotoContainer.innerHTML = '<div class="no-photo-detail">未アップロード</div>';
        startPhotoTime.textContent = '';
        startReassignBtn.innerHTML = '';
    }

    // 2回目チェック写真
    if (data.end) {
        const endPath = data.end.photo_path.startsWith('uploads/') ? '../functions/' + data.end.photo_path : data.end.photo_path;
        const endImg = document.createElement('img');
        endImg.src = endPath;
        endImg.alt = '2回目チェック';
        endImg.className = 'photo-detail-preview';
        endImg.style.cursor = 'pointer';
        endImg.addEventListener('click', function() {
            window.open(this.src, '_blank');
        });
        endPhotoContainer.innerHTML = '';
        endPhotoContainer.appendChild(endImg);
        const endTime = new Date(data.end.uploaded_at);
        endPhotoTime.textContent = `アップロード時刻: ${endTime.toLocaleString('ja-JP')}`;
        // 紐付け変更ボタン
        if (data.end.id) {
            const btn = document.createElement('button');
            btn.className = 'btn btn-sm btn-outline';
            btn.style.fontSize = '0.75rem';
            btn.textContent = '紐付け変更';
            btn.addEventListener('click', () => showReassignModal(data.end.id, 'end'));
            endReassignBtn.innerHTML = '';
            endReassignBtn.appendChild(btn);
        } else {
            endReassignBtn.innerHTML = '';
        }
    } else {
        endPhotoContainer.innerHTML = '<div class="no-photo-detail">未アップロード</div>';
        endPhotoTime.textContent = '';
        endReassignBtn.innerHTML = '';
    }

    // モーダル表示
    modal.classList.add('active');
}

// 紐付け変更モーダル表示
function showReassignModal(photoId, type) {
    const typeName = type === 'start' ? '1回目' : '2回目';
    let options = '<option value="">従業員を選択...</option>';
    employeeList.forEach(emp => {
        options += `<option value="${escapeHtml(emp.id)}">${escapeHtml(emp.name)}</option>`;
    });

    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.id = 'reassignModal';
    modal.innerHTML = `
        <div       class="modal-content max-w-400">
            <div class="modal-header">
                <h3  class="m-0">${escapeHtml(typeName)}チェックの紐付け変更</h3>
                <button type="button" class="modal-close reassign-close">&times;</button>
            </div>
            <div class="modal-body">
                <p    class="mb-2 text-gray-666">この写真を別の従業員に紐付け直します。</p>
                <select id="reassignEmployeeSelect"   class="form-input w-full mb-2">
                    ${options}
                </select>
                <div  class="d-flex gap-1 justify-end">
                    <button type="button" class="btn btn-secondary reassign-cancel">キャンセル</button>
                    <button type="button" class="btn btn-primary reassign-execute" data-photo-id="${escapeHtml(photoId)}">変更する</button>
                </div>
            </div>
        </div>
    `;
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeReassignModal();
    });
    document.body.appendChild(modal);

    // イベントリスナーを追加
    modal.querySelector('.reassign-close').addEventListener('click', closeReassignModal);
    modal.querySelector('.reassign-cancel').addEventListener('click', closeReassignModal);
    modal.querySelector('.reassign-execute').addEventListener('click', function() {
        executeReassign(this.dataset.photoId);
    });
}

function closeReassignModal() {
    const modal = document.getElementById('reassignModal');
    if (modal) modal.remove();
}

function executeReassign(photoId) {
    const select = document.getElementById('reassignEmployeeSelect');
    const newEmployeeId = select.value;

    if (!newEmployeeId) {
        alert('従業員を選択してください');
        return;
    }

    fetch('../api/photo-attendance-api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
        body: JSON.stringify({
            action: 'reassign',
            photo_id: photoId,
            new_employee_id: newEmployeeId
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('紐付けを変更しました');
            location.reload();
        } else {
            alert('エラー: ' + (data.message || '変更に失敗しました'));
        }
    })
    .catch(err => {
        alert('エラーが発生しました');
    });
}

function closeModal() {
    const modal = document.getElementById('detailModal');
    modal.classList.remove('active');
}

// モーダル外クリックで閉じる
const detailModalEl = document.getElementById('detailModal');
if (detailModalEl) {
    detailModalEl.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
}

// ESCキーで閉じる
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeDownloadModal();
        const unassignedModal = document.getElementById('unassignedPhotoModal');
        if (unassignedModal) unassignedModal.classList.remove('active');
    }
});

// イベントリスナーの初期化
document.addEventListener('DOMContentLoaded', function() {
    // 日付入力
    const dateInput = document.getElementById('dateInput');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            location.href = '?date=' + this.value;
        });
    }

    // CSVダウンロードボタン
    const downloadBtn = document.getElementById('downloadBtn');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', showDownloadModal);
    }

    // Chat同期ボタン
    const chatSyncBtn = document.getElementById('chatSyncBtn');
    if (chatSyncBtn) {
        chatSyncBtn.addEventListener('click', syncChatImagesAuto);
    }

    // 従業員行のクリック
    document.querySelectorAll('.employee-row[data-detail]').forEach(row => {
        row.addEventListener('click', function() {
            const data = JSON.parse(this.dataset.detail);
            showDetail(data);
        });
    });

    // 未紐付け画像のクリック
    document.querySelectorAll('.unassigned-photo-preview').forEach(preview => {
        preview.addEventListener('click', function() {
            const photo = JSON.parse(this.dataset.photo);
            showUnassignedPhoto(photo);
        });
    });

    // 未紐付け画像の従業員割り当て
    document.querySelectorAll('.assign-photo-select').forEach(select => {
        select.addEventListener('change', function() {
            const photoId = this.dataset.photoId;
            const employeeId = this.value;
            assignPhotoToEmployee(photoId, employeeId);
        });
    });

    // 詳細モーダルの閉じるボタン
    const detailModalClose = document.getElementById('detailModalClose');
    if (detailModalClose) {
        detailModalClose.addEventListener('click', closeModal);
    }

    // 未紐付け画像モーダルの閉じるボタン
    const unassignedModalCloses = document.querySelectorAll('[data-modal="unassignedPhotoModal"]');
    unassignedModalCloses.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('unassignedPhotoModal').classList.remove('active');
        });
    });

    // ダウンロードモーダルの閉じるボタン
    const downloadModalClose = document.getElementById('downloadModalClose');
    if (downloadModalClose) {
        downloadModalClose.addEventListener('click', closeDownloadModal);
    }

    const downloadModalCancel = document.getElementById('downloadModalCancel');
    if (downloadModalCancel) {
        downloadModalCancel.addEventListener('click', closeDownloadModal);
    }

    // ダウンロード実行ボタン
    const downloadModalExecute = document.getElementById('downloadModalExecute');
    if (downloadModalExecute) {
        downloadModalExecute.addEventListener('click', downloadCSV);
    }
});

// 未紐付け画像の詳細表示
function showUnassignedPhoto(photo) {
    const modal = document.getElementById('unassignedPhotoModal');
    const imageDiv = document.getElementById('unassignedPhotoImage');
    const infoDiv = document.getElementById('unassignedPhotoInfo');

    const photoPath = photo.display_path || photo.photo_path || photo.filepath || '';
    const sender = photo.display_sender || photo.sender_name || photo.original_sender || '不明';
    const time = photo.display_time || photo.uploaded_at || photo.upload_time || '';

    imageDiv.innerHTML = `<img src="../functions/${escapeHtml(photoPath)}"        class="rounded-lg" style="max-width: 100%; max-height: 400px" onerror="this.style.display='none';">`;

    const senderUserId = photo.sender_user_id || '';

    infoDiv.innerHTML = `
        <div  class="mt-2">
            <p><strong>送信者:</strong> ${escapeHtml(sender)}</p>
            <p><strong>時刻:</strong> ${escapeHtml(time)}</p>
            ${photo.source === 'chat' ? '<p><strong>ソース:</strong> Google Chat</p>' : ''}
            ${senderUserId ? `<p><strong>Chat User ID:</strong> <code        class="rounded text-2xs" style="background: var(--gray-100); padding: 0.25rem 0.5rem; user-select: all">${escapeHtml(senderUserId)}</code></p>
            <p    class="text-xs text-gray-500">↑ 従業員マスタの「Google Chat User ID」に設定すると自動紐付けされます</p>` : ''}
            ${photo.original_text ? `<p><strong>メッセージ:</strong> ${escapeHtml(photo.original_text)}</p>` : ''}
        </div>
    `;

    modal.classList.add('active');
}

// 画像を従業員に紐付け
function assignPhotoToEmployee(photoId, employeeId) {
    if (!employeeId) return;

    // 1回目か2回目か選択
    const uploadType = prompt('1回目チェックの場合は「1」、2回目チェックの場合は「2」を入力してください:', '1');
    if (!uploadType || (uploadType !== '1' && uploadType !== '2')) {
        alert('正しい値を入力してください');
        return;
    }

    const type = uploadType === '1' ? 'start' : 'end';

    fetch('../api/photo-attendance-api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
        body: JSON.stringify({
            action: 'assign',
            photo_id: photoId,
            employee_id: employeeId,
            upload_type: type
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('紐付けが完了しました');
            location.reload();
        } else {
            alert('エラー: ' + (data.message || '紐付けに失敗しました'));
        }
    })
    .catch(err => {
        alert('エラーが発生しました');
    });
}

// CSVダウンロードモーダル表示
function showDownloadModal() {
    document.getElementById('downloadModal').classList.add('active');
}

// CSVダウンロードモーダルを閉じる
function closeDownloadModal() {
    document.getElementById('downloadModal').classList.remove('active');
}

// CSVダウンロード実行
function downloadCSV() {
    const startDate = document.getElementById('csv_start_date').value;
    const endDate = document.getElementById('csv_end_date').value;

    if (!startDate || !endDate) {
        alert('開始日と終了日を選択してください');
        return;
    }

    if (startDate > endDate) {
        alert('開始日は終了日以前の日付を選択してください');
        return;
    }

    // CSV出力ページにリダイレクト
    window.location.href = `download-alcohol-check-csv.php?start_date=${startDate}&end_date=${endDate}`;
    closeDownloadModal();
}

// モーダル外クリックで閉じる
const downloadModalEl = document.getElementById('downloadModal');
if (downloadModalEl) {
    downloadModalEl.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDownloadModal();
        }
    });
}

<?php if ($chatConfigured && !empty($alcoholChatConfig['space_id'])): ?>
// Chat画像を自動同期（モーダルなし）
function syncChatImagesAuto() {
    const btn = document.getElementById('chatSyncBtn');
    const date = '<?= $today ?>';

    // ボタンを無効化
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = '同期中...';

    const formData = new FormData();
    formData.append('action', 'sync_images');
    formData.append('date', date);

    fetch('../api/alcohol-chat-sync.php', {
        method: 'POST',
        headers: {'X-CSRF-Token': csrfToken},
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = originalText;

        if (data.success) {
            const imported = data.imported || 0;
            const skipped = data.skipped || 0;

            if (imported > 0) {
                showToast(`${imported}件の画像をインポートしました`, 'success');
                setTimeout(() => location.reload(), 1500);
            } else if (skipped > 0) {
                showToast('新しい画像はありませんでした', 'info');
            } else {
                showToast('対象の画像がありませんでした', 'info');
            }
        } else {
            showToast('エラー: ' + (data.error || '同期に失敗しました'), 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = originalText;
        showToast('通信エラーが発生しました', 'error');
    });
}

// トースト表示
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.textContent = message;
    toast.className = 'toast show';
    if (type === 'success') {
        toast.style.background = '#10b981';
    } else if (type === 'error') {
        toast.style.background = '#ef4444';
    } else {
        toast.style.background = '#3b82f6';
    }

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}
<?php endif; ?>
</script>


<!-- CSVダウンロードモーダル -->
<div id="downloadModal" class="modal">
    <div     class="modal-content max-w-500">
        <div class="modal-header">
            <h3  class="m-0">CSVダウンロード</h3>
            <button type="button" class="modal-close" id="downloadModalClose">&times;</button>
        </div>
        <div class="modal-body">
            <div  class="mb-3">
                <label for="csv_start_date"  class="d-block mb-1 font-medium">
                    開始日
                </label>
                <input
                    type="date"
                    id="csv_start_date"
                    class="form-input"
                    value="<?= date('Y-m-01') ?>"
                    class="w-full"
                >
            </div>
            <div  class="mb-3">
                <label for="csv_end_date"  class="d-block mb-1 font-medium">
                    終了日
                </label>
                <input
                    type="date"
                    id="csv_end_date"
                    class="form-input"
                    value="<?= date('Y-m-d') ?>"
                    class="w-full"
                >
            </div>
            <div  class="d-flex gap-1 justify-end">
                <button type="button" id="downloadModalCancel" class="btn btn-secondary">
                    キャンセル
                </button>
                <button type="button" id="downloadModalExecute" class="btn btn-success">
                    ダウンロード
                </button>
            </div>
        </div>
    </div>
</div>

</div><!-- /.page-container -->

<?php require_once __DIR__ . '/../functions/footer.php'; ?>
