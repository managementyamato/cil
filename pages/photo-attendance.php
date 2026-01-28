<?php
/**
 * アルコールチェック管理 - メイン画面
 */

require_once __DIR__ . '/../config/config.php';
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
$employees = getEmployees();

// 従業員データがない場合
if (empty($employees)) {
    require_once __DIR__ . '/../functions/header.php';
    echo '<div class="card" style="max-width: 800px; margin: 2rem auto;">';
    echo '<div class="card-header"><h2 style="margin:0;">従業員データが登録されていません</h2></div>';
    echo '<div class="card-body">';
    echo '<p>アルコールチェック管理を使用するには、まず従業員マスタに従業員を登録してください。</p>';
    echo '<a href="employees.php" class="btn btn-primary">従業員マスタへ</a>';
    echo '</div></div>';
    require_once __DIR__ . '/../functions/footer.php';
    exit;
}

// 指定日の写真アップロード状況を取得
$uploadStatus = getUploadStatusForDate($today);

// 指定日の車不使用申請を取得
$noCarUsageIds = getNoCarUsageForDate($today);

// 未紐付けの画像を取得（Chatからインポートしたが従業員に紐付いていないもの）
$unassignedPhotos = getUnassignedPhotosForDate($today);

require_once __DIR__ . '/../functions/header.php';
?>

<style>
.photo-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.status-grid {
    display: grid;
    gap: 0.5rem;
    margin-top: 20px;
}

.employee-row {
    display: grid;
    grid-template-columns: minmax(150px, 1fr) minmax(100px, 0.8fr) minmax(100px, 0.8fr) minmax(100px, 0.8fr) minmax(100px, 0.8fr) minmax(80px, 0.6fr);
    gap: 0.5rem;
    align-items: center;
    background: white;
    padding: 0.75rem 0.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.2s;
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
    display: grid;
    grid-template-columns: minmax(150px, 1fr) minmax(100px, 0.8fr) minmax(100px, 0.8fr) minmax(100px, 0.8fr) minmax(100px, 0.8fr) minmax(80px, 0.6fr);
    gap: 0.5rem;
    font-weight: bold;
    padding: 0.5rem 0.5rem;
    color: var(--gray-600);
    font-size: 0.75rem;
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
    .photo-container {
        padding: 10px;
    }

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

<div class="photo-container">
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h2 style="margin: 0;">アルコールチェック管理</h2>
                <div style="display: flex; align-items: center; gap: 0.25rem;">
                    <?php $prevDate = date('Y-m-d', strtotime($today . ' -1 day')); $nextDate = date('Y-m-d', strtotime($today . ' +1 day')); ?>
                    <a href="?date=<?= $prevDate ?>" style="padding: 4px 8px; background: #f5f5f5; border-radius: 4px; text-decoration: none; color: #333; font-size: 1.1rem;">&lt;</a>
                    <input type="date" value="<?= $today ?>" onchange="location.href='?date='+this.value" style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem;">
                    <?php if ($today < date('Y-m-d')): ?>
                    <a href="?date=<?= $nextDate ?>" style="padding: 4px 8px; background: #f5f5f5; border-radius: 4px; text-decoration: none; color: #333; font-size: 1.1rem;">&gt;</a>
                    <?php endif; ?>
                    <?php if (!$isToday): ?>
                    <a href="?date=<?= date('Y-m-d') ?>" style="padding: 4px 10px; background: #3182ce; color: white; border-radius: 4px; text-decoration: none; font-size: 0.8rem;">今日</a>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <?php if ($chatConfigured): ?>
                <button onclick="showChatSyncModal()" class="btn btn-primary" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                    Chat同期
                </button>
                <?php endif; ?>
                <button onclick="showDownloadModal()" class="btn btn-success" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                    CSVダウンロード
                </button>
            </div>
        </div>
        <div class="card-body">
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

            <div class="summary-cards">
                <div class="summary-card" style="border-left: 4px solid #4caf50;">
                    <div class="summary-label">完了（2回アップロード済み）</div>
                    <div class="summary-number" style="color: #4caf50;"><?= $complete ?></div>
                </div>
                <div class="summary-card" style="border-left: 4px solid #ff9800;">
                    <div class="summary-label">部分完了（1回のみ）</div>
                    <div class="summary-number" style="color: #ff9800;"><?= $partial ?></div>
                </div>
                <div class="summary-card" style="border-left: 4px solid #f44336;">
                    <div class="summary-label">未アップロード</div>
                    <div class="summary-number" style="color: #f44336;"><?= $missing ?></div>
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
                            <div colspan="4" style="grid-column: 3 / 7; color: #1976d2; font-weight: bold; text-align: center;">
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
                            'vehicle_number' => $employee['vehicle_number'] ?? '',
                            'start' => $status['start'] ? [
                                'photo_path' => $status['start']['photo_path'] ?? '',
                                'uploaded_at' => $status['start']['uploaded_at'] ?? ''
                            ] : null,
                            'end' => $status['end'] ? [
                                'photo_path' => $status['end']['photo_path'] ?? '',
                                'uploaded_at' => $status['end']['uploaded_at'] ?? ''
                            ] : null
                        ]);
                        ?>
                        <div class="employee-row <?= $rowClass ?>"
                             onclick="showDetail(<?= htmlspecialchars($statusData, ENT_QUOTES) ?>)">
                            <div class="employee-name"><?= htmlspecialchars($employee['name']); ?></div>
                            <div class="vehicle-number"><?= htmlspecialchars($employee['vehicle_number'] ?? '-'); ?></div>

                            <!-- 出勤前チェック -->
                            <div class="check-status">
                                <div class="check-icon <?= $status['start'] ? 'checked' : 'unchecked' ?>">
                                    <?= $status['start'] ? '✓' : '✗' ?>
                                </div>
                            </div>
                            <div style="font-size: 0.875rem;">
                                <?= ($status['start'] && !empty($status['start']['uploaded_at'])) ? date('H:i', strtotime($status['start']['uploaded_at'])) : '-' ?>
                            </div>

                            <!-- 退勤前チェック -->
                            <div class="check-status">
                                <div class="check-icon <?= $status['end'] ? 'checked' : 'unchecked' ?>">
                                    <?= $status['end'] ? '✓' : '✗' ?>
                                </div>
                            </div>
                            <div style="font-size: 0.875rem;">
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
            <div style="margin-top: 2rem;">
                <h3 style="color: var(--gray-700); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="background: var(--warning); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">
                        <?= count($unassignedPhotos) ?>件
                    </span>
                    未紐付けの画像（従業員に割り当ててください）
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                    <?php foreach ($unassignedPhotos as $photo):
                        // photo_pathとfilepathの両方に対応
                        $photoPath = $photo['photo_path'] ?? $photo['filepath'] ?? '';
                        $senderName = $photo['sender_name'] ?? $photo['original_sender'] ?? '不明';
                        $uploadTime = $photo['uploaded_at'] ?? $photo['upload_time'] ?? '';
                    ?>
                    <div class="unassigned-photo-card" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="aspect-ratio: 4/3; overflow: hidden; cursor: pointer;" onclick="showUnassignedPhoto(<?= htmlspecialchars(json_encode(array_merge($photo, ['display_path' => $photoPath, 'display_sender' => $senderName, 'display_time' => $uploadTime])), ENT_QUOTES) ?>)">
                            <img src="../functions/<?= htmlspecialchars($photoPath) ?>"
                                 alt="未紐付け画像"
                                 style="width: 100%; height: 100%; object-fit: cover;"
                                 onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;color:#999;\'>画像なし</div>';">
                        </div>
                        <div style="padding: 0.75rem;">
                            <div style="font-weight: 500; font-size: 0.875rem;"><?= htmlspecialchars($senderName) ?></div>
                            <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($uploadTime) ?></div>
                            <?php if (!empty($photo['source']) && $photo['source'] === 'chat'): ?>
                            <div style="font-size: 0.7rem; color: var(--primary); margin-top: 0.25rem;">
                                Chatからインポート
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($photo['sender_user_id'])): ?>
                            <div style="font-size: 0.65rem; color: var(--gray-400); margin-top: 0.25rem; word-break: break-all;" title="従業員マスタでこのIDを設定すると自動紐付けされます">
                                ID: <?= htmlspecialchars($photo['sender_user_id']) ?>
                            </div>
                            <?php endif; ?>
                            <div style="margin-top: 0.5rem;">
                                <select class="form-input" style="width: 100%; font-size: 0.75rem; padding: 0.25rem;" onchange="assignPhotoToEmployee('<?= $photo['id'] ?>', this.value)">
                                    <option value="">従業員を選択...</option>
                                    <?php foreach ($employees as $emp): ?>
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
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="margin: 0;">画像詳細</h3>
            <button class="modal-close" onclick="document.getElementById('unassignedPhotoModal').classList.remove('active')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="unassignedPhotoImage" style="text-align: center; margin-bottom: 1rem;"></div>
            <div id="unassignedPhotoInfo"></div>
        </div>
    </div>
</div>

<!-- 詳細モーダル -->
<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle" style="margin: 0;">詳細情報</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalVehicleNumber" style="color: #666; margin-bottom: 1rem;"></div>
            <div class="photo-detail-grid">
                <div class="photo-detail-box">
                    <h3>1回目チェック</h3>
                    <div id="startPhotoContainer"></div>
                    <div id="startPhotoTime" class="photo-time"></div>
                </div>
                <div class="photo-detail-box">
                    <h3>2回目チェック</h3>
                    <div id="endPhotoContainer"></div>
                    <div id="endPhotoTime" class="photo-time"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= generateCsrfToken() ?>';

function showDetail(data) {
    const modal = document.getElementById('detailModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalVehicleNumber = document.getElementById('modalVehicleNumber');
    const startPhotoContainer = document.getElementById('startPhotoContainer');
    const startPhotoTime = document.getElementById('startPhotoTime');
    const endPhotoContainer = document.getElementById('endPhotoContainer');
    const endPhotoTime = document.getElementById('endPhotoTime');

    // タイトル設定
    modalTitle.textContent = data.name + ' - アルコールチェック詳細';
    modalVehicleNumber.textContent = 'ナンバー: ' + (data.vehicle_number || '-');

    // 出勤前チェック写真
    if (data.start) {
        const startPath = data.start.photo_path.startsWith('uploads/') ? '../functions/' + data.start.photo_path : data.start.photo_path;
        startPhotoContainer.innerHTML = `<img src="${startPath}" alt="出勤前チェック" class="photo-detail-preview" onclick="window.open(this.src, '_blank')" style="cursor: pointer;">`;
        const startTime = new Date(data.start.uploaded_at);
        startPhotoTime.textContent = `アップロード時刻: ${startTime.toLocaleString('ja-JP')}`;
    } else {
        startPhotoContainer.innerHTML = '<div class="no-photo-detail">未アップロード</div>';
        startPhotoTime.textContent = '';
    }

    // 2回目チェック写真
    if (data.end) {
        const endPath = data.end.photo_path.startsWith('uploads/') ? '../functions/' + data.end.photo_path : data.end.photo_path;
        endPhotoContainer.innerHTML = `<img src="${endPath}" alt="2回目チェック" class="photo-detail-preview" onclick="window.open(this.src, '_blank')" style="cursor: pointer;">`;
        const endTime = new Date(data.end.uploaded_at);
        endPhotoTime.textContent = `アップロード時刻: ${endTime.toLocaleString('ja-JP')}`;
    } else {
        endPhotoContainer.innerHTML = '<div class="no-photo-detail">未アップロード</div>';
        endPhotoTime.textContent = '';
    }

    // モーダル表示
    modal.classList.add('active');
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

// 未紐付け画像の詳細表示
function showUnassignedPhoto(photo) {
    const modal = document.getElementById('unassignedPhotoModal');
    const imageDiv = document.getElementById('unassignedPhotoImage');
    const infoDiv = document.getElementById('unassignedPhotoInfo');

    const photoPath = photo.display_path || photo.photo_path || photo.filepath || '';
    const sender = photo.display_sender || photo.sender_name || photo.original_sender || '不明';
    const time = photo.display_time || photo.uploaded_at || photo.upload_time || '';

    imageDiv.innerHTML = `<img src="../functions/${photoPath}" style="max-width: 100%; max-height: 400px; border-radius: 8px;" onerror="this.style.display='none';">`;

    const senderUserId = photo.sender_user_id || '';

    infoDiv.innerHTML = `
        <div style="margin-top: 1rem;">
            <p><strong>送信者:</strong> ${sender}</p>
            <p><strong>時刻:</strong> ${time}</p>
            ${photo.source === 'chat' ? '<p><strong>ソース:</strong> Google Chat</p>' : ''}
            ${senderUserId ? `<p><strong>Chat User ID:</strong> <code style="background: var(--gray-100); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; user-select: all;">${senderUserId}</code></p>
            <p style="font-size: 0.75rem; color: var(--gray-500);">↑ 従業員マスタの「Google Chat User ID」に設定すると自動紐付けされます</p>` : ''}
            ${photo.original_text ? `<p><strong>メッセージ:</strong> ${photo.original_text}</p>` : ''}
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

<?php if ($chatConfigured): ?>
// Chat同期モーダル表示
function showChatSyncModal() {
    document.getElementById('chatSyncModal').classList.add('active');
    loadChatSpaces();
}

// Chat同期モーダルを閉じる
function closeChatSyncModal() {
    document.getElementById('chatSyncModal').classList.remove('active');
}

// スペース一覧を読み込み
function loadChatSpaces() {
    const select = document.getElementById('chatSpaceSelect');
    select.innerHTML = '<option value="">読み込み中...</option>';

    fetch('../api/alcohol-chat-sync.php?action=get_spaces')
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                select.innerHTML = '<option value="">エラー: ' + data.error + '</option>';
                return;
            }

            const spaces = data.spaces || [];
            if (spaces.length === 0) {
                select.innerHTML = '<option value="">スペースが見つかりません</option>';
                return;
            }

            let html = '<option value="">スペースを選択...</option>';
            spaces.forEach(space => {
                const selected = space.name === '<?= addslashes($alcoholChatConfig['space_id'] ?? '') ?>' ? ' selected' : '';
                html += `<option value="${space.name}"${selected}>${space.displayName}</option>`;
            });
            select.innerHTML = html;
        })
        .catch(err => {
            select.innerHTML = '<option value="">読み込みエラー</option>';
        });
}

// スペース設定を保存
function saveChatSpaceConfig() {
    const select = document.getElementById('chatSpaceSelect');
    const spaceId = select.value;
    const spaceName = select.options[select.selectedIndex]?.text || '';

    if (!spaceId) {
        alert('スペースを選択してください');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'save_config');
    formData.append('space_id', spaceId);
    formData.append('space_name', spaceName);

    fetch('../api/alcohol-chat-sync.php', {
        method: 'POST',
        headers: {'X-CSRF-Token': csrfToken},
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('スペース設定を保存しました: ' + spaceName);
        } else {
            alert('エラー: ' + (data.error || '保存に失敗しました'));
        }
    })
    .catch(err => {
        alert('エラーが発生しました');
    });
}

// Chat画像を同期
function syncChatImages() {
    const date = document.getElementById('sync_date').value;
    const statusDiv = document.getElementById('syncStatus');
    const debugDiv = document.getElementById('debugInfo');
    const syncButton = document.getElementById('syncButton');

    if (!date) {
        alert('対象日を選択してください');
        return;
    }

    // ボタンを無効化
    syncButton.disabled = true;
    syncButton.textContent = '同期中...';

    statusDiv.style.display = 'block';
    statusDiv.style.background = '#e3f2fd';
    statusDiv.style.color = '#1565c0';
    statusDiv.textContent = '画像を取得しています...';
    debugDiv.style.display = 'none';
    debugDiv.innerHTML = '';

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
        syncButton.disabled = false;
        syncButton.textContent = '画像を同期する';

        if (data.success) {
            statusDiv.style.background = '#e8f5e9';
            statusDiv.style.color = '#2e7d32';
            let msg = data.message || `${data.imported}件の画像をインポートしました`;
            if (data.errors && data.errors.length > 0) {
                msg += `\n※ ${data.errors.length}件のエラーがありました`;
            }
            statusDiv.textContent = msg;

            // デバッグ情報を表示
            if (data.debug) {
                debugDiv.style.display = 'block';
                let debugHtml = '<strong>デバッグ情報:</strong><br>';
                debugHtml += `総メッセージ数: ${data.debug.total_messages || 0}<br>`;
                debugHtml += `対象日(${data.debug.target_date})のメッセージ数: ${data.debug.messages_on_date || 0}<br>`;
                debugHtml += `インポート: ${data.imported}, スキップ: ${data.skipped}<br>`;
                if (data.debug.filter_used) {
                    debugHtml += `フィルター: ${data.debug.filter_used}<br>`;
                }

                // メッセージの日付一覧を表示
                if (data.debug.message_dates && data.debug.message_dates.length > 0) {
                    debugHtml += '<br><strong>取得したメッセージの日付(最大10件):</strong><br>';
                    data.debug.message_dates.forEach((d, i) => {
                        debugHtml += `${i+1}. ${d.parsed} (日付: ${d.date_only})<br>`;
                    });
                }

                if (data.debug.sample_messages && data.debug.sample_messages.length > 0) {
                    debugHtml += '<br><strong>対象日のサンプルメッセージ:</strong><br>';
                    data.debug.sample_messages.forEach((msg, i) => {
                        debugHtml += `<div style="margin-bottom: 8px; padding: 4px; background: #fff;">`;
                        debugHtml += `${i+1}. attachmentフィールド: ${msg.has_attachment_field ? 'あり' : 'なし'}, `;
                        debugHtml += `添付数: ${msg.attachment_count}<br>`;
                        debugHtml += `　キー: ${msg.message_keys.join(', ')}<br>`;
                        if (msg.text_preview) {
                            debugHtml += `　テキスト: ${msg.text_preview}<br>`;
                        }
                        // 添付ファイル詳細
                        if (msg.attachment_details && msg.attachment_details.length > 0) {
                            debugHtml += `　<strong>添付ファイル詳細:</strong><br>`;
                            msg.attachment_details.forEach((att, j) => {
                                debugHtml += `　　${j+1}. name: ${att.name}<br>`;
                                debugHtml += `　　　 contentType: ${att.contentType}<br>`;
                                debugHtml += `　　　 contentName: ${att.contentName}<br>`;
                                debugHtml += `　　　 keys: ${att.keys.join(', ')}<br>`;
                            });
                        }
                        debugHtml += `</div>`;
                    });
                }

                if (data.debug.attachment_samples && data.debug.attachment_samples.length > 0) {
                    debugHtml += '<br><strong>添付ファイル構造:</strong><br>';
                    data.debug.attachment_samples.forEach((att, i) => {
                        debugHtml += `${i+1}. キー: ${att.keys.join(', ')}<br>`;
                        debugHtml += `　contentType: ${att.contentType}<br>`;
                    });
                }

                if (data.errors && data.errors.length > 0) {
                    debugHtml += '<br><strong>エラー:</strong><br>';
                    data.errors.forEach(err => {
                        debugHtml += `・${err}<br>`;
                    });
                }

                debugDiv.innerHTML = debugHtml;
            }

            if (data.imported > 0) {
                setTimeout(() => {
                    if (confirm('画像をインポートしました。ページを更新しますか？')) {
                        location.reload();
                    }
                }, 500);
            }
        } else {
            statusDiv.style.background = '#ffebee';
            statusDiv.style.color = '#c62828';
            statusDiv.textContent = 'エラー: ' + (data.error || '同期に失敗しました');
        }
    })
    .catch(err => {
        syncButton.disabled = false;
        syncButton.textContent = '画像を同期する';

        statusDiv.style.background = '#ffebee';
        statusDiv.style.color = '#c62828';
        statusDiv.textContent = 'エラーが発生しました: ' + err.message;
    });
}

// 従業員照合を再実行
function reMatchEmployees() {
    const date = document.getElementById('sync_date').value;
    const statusDiv = document.getElementById('syncStatus');
    const debugDiv = document.getElementById('debugInfo');
    const reMatchBtn = document.getElementById('reMatchButton');

    if (!date) {
        alert('対象日を選択してください');
        return;
    }

    reMatchBtn.disabled = true;
    reMatchBtn.textContent = '照合中...';

    statusDiv.style.display = 'block';
    statusDiv.style.background = '#e3f2fd';
    statusDiv.style.color = '#1565c0';
    statusDiv.textContent = '従業員照合を再実行しています...';
    debugDiv.style.display = 'none';
    debugDiv.innerHTML = '';

    const formData = new FormData();
    formData.append('action', 're_match');
    formData.append('date', date);

    fetch('../api/alcohol-chat-sync.php', {
        method: 'POST',
        headers: {'X-CSRF-Token': csrfToken},
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        reMatchBtn.disabled = false;
        reMatchBtn.textContent = '従業員照合を再実行';

        if (data.success) {
            statusDiv.style.background = '#e8f5e9';
            statusDiv.style.color = '#2e7d32';
            statusDiv.textContent = data.message || '照合完了';

            // 詳細をデバッグ欄に表示
            if (data.details && data.details.length > 0) {
                debugDiv.style.display = 'block';
                let html = '<strong>照合結果詳細:</strong><br>';
                html += `更新: ${data.updated}件 / 既にマッチ済み: ${data.already_matched}件 / 未マッチ: ${data.no_match}件<br><br>`;
                data.details.forEach((d, i) => {
                    const statusColor = d.status === 'updated' ? '#2e7d32' : (d.status === 'already_matched' ? '#1565c0' : '#c62828');
                    const statusLabel = d.status === 'updated' ? '更新' : (d.status === 'already_matched' ? 'マッチ済み' : '未マッチ');
                    html += `<div style="margin-bottom:6px;padding:4px 6px;background:#fff;border-left:3px solid ${statusColor};">`;
                    html += `${i+1}. ${d.sender_name || '(名前なし)'} `;
                    html += `<span style="color:${statusColor};font-weight:bold;">[${statusLabel}]</span>`;
                    if (d.method) html += ` (方式: ${d.method})`;
                    if (d.sender_email) html += `<br>　メール: ${d.sender_email}`;
                    if (d.sender_user_id) html += `<br>　User ID: ${d.sender_user_id}`;
                    if (d.api_debug) {
                        html += `<br>　<span style="color:#666;">API結果: ${JSON.stringify(d.api_debug)}</span>`;
                    }
                    html += `</div>`;
                });
                // メンバー一覧のデバッグ情報
                if (data.debug) {
                    html += `<br><strong>メンバー一覧デバッグ:</strong><br>`;
                    html += `Space ID: ${data.debug.space_id || '(未設定)'}<br>`;
                    html += `メンバー数: ${data.debug.members_map_count || 0}<br>`;
                    if (data.debug.members_map_keys && data.debug.members_map_keys.length > 0) {
                        html += `メンバーUser IDs: ${data.debug.members_map_keys.join(', ')}<br>`;
                    }
                    if (data.debug.members_map_sample) {
                        html += `メンバーサンプル: ${JSON.stringify(data.debug.members_map_sample)}<br>`;
                    }
                    if (data.debug.employee_emails) {
                        html += `従業員メール: ${data.debug.employee_emails.join(', ')}<br>`;
                    }
                }
                debugDiv.innerHTML = html;
            }

            if (data.updated > 0) {
                setTimeout(() => {
                    if (confirm('照合を更新しました。ページを更新しますか？')) {
                        location.reload();
                    }
                }, 500);
            }
        } else {
            statusDiv.style.background = '#ffebee';
            statusDiv.style.color = '#c62828';
            statusDiv.textContent = 'エラー: ' + (data.error || '照合に失敗しました');
        }
    })
    .catch(err => {
        reMatchBtn.disabled = false;
        reMatchBtn.textContent = '従業員照合を再実行';
        statusDiv.style.background = '#ffebee';
        statusDiv.style.color = '#c62828';
        statusDiv.textContent = 'エラーが発生しました: ' + err.message;
    });
}

// Chat同期モーダル外クリックで閉じる
const chatSyncModalEl = document.getElementById('chatSyncModal');
if (chatSyncModalEl) {
    chatSyncModalEl.addEventListener('click', function(e) {
        if (e.target === this) {
            closeChatSyncModal();
        }
    });
}

// --- 自動同期（cron）設定 ---
function loadCronConfig() {
    fetch('../api/alcohol-chat-sync.php?action=get_cron_config')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.config && data.config.secret_key) {
                document.getElementById('cronSecretKey').value = data.config.secret_key;
                updateCronExamples(data.config.secret_key);
            }
        })
        .catch(() => {});
}

function generateCronSecret() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let key = '';
    for (let i = 0; i < 32; i++) {
        key += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('cronSecretKey').value = key;
    updateCronExamples(key);
}

function updateCronExamples(key) {
    const cliExample = document.getElementById('cronExample');
    const webExample = document.getElementById('cronWebExample');
    if (cliExample) {
        cliExample.textContent = '0 8,10,12,18,20 * * * php /path/to/scripts/cron-alcohol-sync.php --secret=' + key;
    }
    if (webExample) {
        const baseUrl = window.location.origin + window.location.pathname.replace(/pages\/.*$/, '');
        webExample.textContent = 'curl "' + baseUrl + 'scripts/cron-alcohol-sync.php?secret=' + key + '"';
    }
}

function saveCronConfig() {
    const key = document.getElementById('cronSecretKey').value;
    const statusDiv = document.getElementById('cronConfigStatus');
    statusDiv.style.display = 'block';
    statusDiv.style.background = '#e3f2fd';
    statusDiv.style.color = '#1565c0';
    statusDiv.textContent = '保存中...';

    const formData = new FormData();
    formData.append('action', 'save_cron_config');
    formData.append('secret_key', key);

    fetch('../api/alcohol-chat-sync.php', {
        method: 'POST',
        headers: {'X-CSRF-Token': csrfToken},
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statusDiv.style.background = '#e8f5e9';
            statusDiv.style.color = '#2e7d32';
            statusDiv.textContent = '保存しました';
            updateCronExamples(key);
        } else {
            statusDiv.style.background = '#ffebee';
            statusDiv.style.color = '#c62828';
            statusDiv.textContent = 'エラー: ' + (data.error || '保存に失敗しました');
        }
        setTimeout(() => { statusDiv.style.display = 'none'; }, 3000);
    })
    .catch(err => {
        statusDiv.style.background = '#ffebee';
        statusDiv.style.color = '#c62828';
        statusDiv.textContent = 'エラー: ' + err.message;
    });
}

// モーダル表示時にcron設定も読み込み
const origShowChatSyncModal = showChatSyncModal;
showChatSyncModal = function() {
    origShowChatSyncModal();
    loadCronConfig();
};
<?php endif; ?>
</script>

<!-- Chat同期モーダル -->
<?php if ($chatConfigured): ?>
<div id="chatSyncModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 style="margin: 0;">Google Chat画像同期</h3>
            <button class="modal-close" onclick="closeChatSyncModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- スペース設定 -->
            <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--gray-50); border-radius: 8px;">
                <h4 style="margin: 0 0 1rem 0; font-size: 0.875rem;">同期元スペース設定</h4>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">スペースを選択</label>
                    <select id="chatSpaceSelect" class="form-input" style="width: 100%;">
                        <option value="">読み込み中...</option>
                    </select>
                </div>
                <?php if (!empty($alcoholChatConfig['space_name'])): ?>
                <p style="margin: 0; font-size: 0.75rem; color: var(--gray-600);">
                    現在の設定: <strong><?= htmlspecialchars($alcoholChatConfig['space_name']) ?></strong>
                </p>
                <?php endif; ?>
                <button onclick="saveChatSpaceConfig()" class="btn btn-secondary" style="margin-top: 0.5rem; font-size: 0.875rem;">
                    スペース設定を保存
                </button>
            </div>

            <!-- 同期実行 -->
            <div style="margin-bottom: 1.5rem;">
                <h4 style="margin: 0 0 1rem 0; font-size: 0.875rem;">画像を同期</h4>
                <div style="margin-bottom: 1rem;">
                    <label for="sync_date" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                        対象日
                    </label>
                    <input
                        type="date"
                        id="sync_date"
                        class="form-input"
                        value="<?= $today ?>"
                        style="width: 100%;"
                    >
                </div>
                <div id="syncStatus" style="display: none; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem;"></div>
                <div id="debugInfo" style="display: none; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; background: #f5f5f5; font-size: 0.75rem; font-family: monospace; max-height: 300px; overflow-y: auto;"></div>
                <div style="display: flex; gap: 0.5rem;">
                    <button onclick="syncChatImages()" id="syncButton" class="btn btn-primary" style="flex: 1;">
                        画像を同期する
                    </button>
                    <button onclick="reMatchEmployees()" id="reMatchButton" class="btn btn-secondary" style="flex: 1;" title="既にインポート済みの画像に対して従業員照合を再実行します">
                        従業員照合を再実行
                    </button>
                </div>
            </div>

            <!-- 自動同期設定 -->
            <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--gray-50); border-radius: 8px; border: 1px solid var(--gray-200);">
                <h4 style="margin: 0 0 1rem 0; font-size: 0.875rem;">自動同期設定（cron/タスクスケジューラ）</h4>
                <div style="margin-bottom: 0.75rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.8125rem;">シークレットキー</label>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="text" id="cronSecretKey" class="form-input" style="flex: 1; font-family: monospace; font-size: 0.8125rem;" placeholder="未設定" readonly>
                        <button onclick="generateCronSecret()" class="btn btn-secondary" style="font-size: 0.75rem; white-space: nowrap;">生成</button>
                        <button onclick="saveCronConfig()" class="btn btn-primary" style="font-size: 0.75rem; white-space: nowrap;">保存</button>
                    </div>
                </div>
                <div id="cronConfigStatus" style="display: none; padding: 0.5rem; border-radius: 4px; margin-bottom: 0.75rem; font-size: 0.75rem;"></div>
                <div style="font-size: 0.75rem; color: var(--gray-500); background: #fff; padding: 0.75rem; border-radius: 4px; border: 1px solid var(--gray-200);">
                    <p style="margin: 0 0 0.5rem 0; font-weight: 600;">cron設定例:</p>
                    <code id="cronExample" style="display: block; word-break: break-all; background: var(--gray-50); padding: 0.5rem; border-radius: 4px; font-size: 0.6875rem;">
                        0 8,10,12,18,20 * * * php /path/to/scripts/cron-alcohol-sync.php --secret=YOUR_KEY
                    </code>
                    <p style="margin: 0.5rem 0 0 0;">Web経由:</p>
                    <code id="cronWebExample" style="display: block; word-break: break-all; background: var(--gray-50); padding: 0.5rem; border-radius: 4px; font-size: 0.6875rem;">
                        curl "<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'example.com') ?>/scripts/cron-alcohol-sync.php?secret=YOUR_KEY"
                    </code>
                </div>
            </div>

            <div style="font-size: 0.75rem; color: var(--gray-500);">
                <p style="margin: 0 0 0.5rem 0;"><strong>注意:</strong></p>
                <ul style="margin: 0; padding-left: 1.5rem;">
                    <li>選択したスペースに投稿された画像を取り込みます</li>
                    <li>取り込んだ画像は「未紐付け」として表示されます</li>
                    <li>画像を従業員に紐付けてください</li>
                    <li>同じ画像は重複して取り込まれません</li>
                    <li>「従業員照合を再実行」は既存レコードのメール照合をやり直します</li>
                    <li>自動同期はcronまたはタスクスケジューラで定期実行できます</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CSVダウンロードモーダル -->
<div id="downloadModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 style="margin: 0;">CSVダウンロード</h3>
            <button class="modal-close" onclick="closeDownloadModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 1.5rem;">
                <label for="csv_start_date" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                    開始日
                </label>
                <input
                    type="date"
                    id="csv_start_date"
                    class="form-input"
                    value="<?= date('Y-m-01') ?>"
                    style="width: 100%;"
                >
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label for="csv_end_date" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                    終了日
                </label>
                <input
                    type="date"
                    id="csv_end_date"
                    class="form-input"
                    value="<?= date('Y-m-d') ?>"
                    style="width: 100%;"
                >
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button onclick="closeDownloadModal()" class="btn btn-secondary">
                    キャンセル
                </button>
                <button onclick="downloadCSV()" class="btn btn-success">
                    ダウンロード
                </button>
            </div>
        </div>
    </div>
</div>


<?php require_once __DIR__ . '/../functions/footer.php'; ?>
