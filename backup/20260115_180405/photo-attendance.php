<?php
/**
 * アルコールチェック管理 - メイン画面
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/photo-attendance-functions.php';

// 管理者・編集者権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

// 本日の日付
$today = date('Y-m-d');

// 従業員一覧を取得
$employees = getEmployees();

// 従業員データがない場合
if (empty($employees)) {
    require_once __DIR__ . '/header.php';
    echo '<div class="card" style="max-width: 800px; margin: 2rem auto;">';
    echo '<div class="card-header"><h2 style="margin:0;">従業員データが登録されていません</h2></div>';
    echo '<div class="card-body">';
    echo '<p>アルコールチェック管理を使用するには、まず従業員マスタに従業員を登録してください。</p>';
    echo '<a href="employees.php" class="btn btn-primary">従業員マスタへ</a>';
    echo '</div></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

// 本日の写真アップロード状況を取得
$uploadStatus = getUploadStatusForDate($today);

require_once __DIR__ . '/header.php';
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
    grid-template-columns: 200px 150px 150px 150px 150px 120px;
    gap: 1rem;
    align-items: center;
    background: white;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.2s;
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

.employee-name {
    font-weight: bold;
    color: var(--gray-900);
}

.vehicle-number {
    font-size: 0.875rem;
    color: var(--gray-600);
}

.check-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
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
    grid-template-columns: 200px 150px 150px 150px 150px 120px;
    gap: 1rem;
    font-weight: bold;
    padding: 0.5rem 1rem;
    color: var(--gray-600);
    font-size: 0.875rem;
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
}

.summary-number {
    font-size: 2rem;
    font-weight: bold;
    margin: 0.5rem 0;
}

.summary-label {
    color: var(--gray-600);
    font-size: 0.875rem;
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
        content: '出勤前: ';
        font-weight: 500;
        min-width: 80px;
    }

    .employee-row > div:nth-child(4)::before {
        content: '出勤時刻: ';
        color: #666;
        min-width: 80px;
    }

    .employee-row > div:nth-child(5)::before {
        content: '退勤前: ';
        font-weight: 500;
        min-width: 80px;
    }

    .employee-row > div:nth-child(6)::before {
        content: '退勤時刻: ';
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
        <div class="card-header">
            <h2 style="margin: 0;">アルコールチェック管理 - <?= date('Y年m月d日', strtotime($today)); ?></h2>
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
                <div>出勤前</div>
                <div>出勤前時刻</div>
                <div>退勤前</div>
                <div>退勤前時刻</div>
            </div>

            <!-- 従業員一覧 -->
            <div class="status-grid">
                <?php foreach ($employees as $employee): ?>
                    <?php
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
                            'photo_path' => $status['start']['photo_path'],
                            'uploaded_at' => $status['start']['uploaded_at']
                        ] : null,
                        'end' => $status['end'] ? [
                            'photo_path' => $status['end']['photo_path'],
                            'uploaded_at' => $status['end']['uploaded_at']
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
                            <?= $status['start'] ? date('H:i', strtotime($status['start']['uploaded_at'])) : '-' ?>
                        </div>

                        <!-- 退勤前チェック -->
                        <div class="check-status">
                            <div class="check-icon <?= $status['end'] ? 'checked' : 'unchecked' ?>">
                                <?= $status['end'] ? '✓' : '✗' ?>
                            </div>
                        </div>
                        <div style="font-size: 0.875rem;">
                            <?= $status['end'] ? date('H:i', strtotime($status['end']['uploaded_at'])) : '-' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
                    <h3>出勤前チェック</h3>
                    <div id="startPhotoContainer"></div>
                    <div id="startPhotoTime" class="photo-time"></div>
                </div>
                <div class="photo-detail-box">
                    <h3>退勤前チェック</h3>
                    <div id="endPhotoContainer"></div>
                    <div id="endPhotoTime" class="photo-time"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
        startPhotoContainer.innerHTML = `<img src="${data.start.photo_path}" alt="出勤前チェック" class="photo-detail-preview" onclick="window.open(this.src, '_blank')" style="cursor: pointer;">`;
        const startTime = new Date(data.start.uploaded_at);
        startPhotoTime.textContent = `アップロード時刻: ${startTime.toLocaleString('ja-JP')}`;
    } else {
        startPhotoContainer.innerHTML = '<div class="no-photo-detail">未アップロード</div>';
        startPhotoTime.textContent = '';
    }

    // 退勤前チェック写真
    if (data.end) {
        endPhotoContainer.innerHTML = `<img src="${data.end.photo_path}" alt="退勤前チェック" class="photo-detail-preview" onclick="window.open(this.src, '_blank')" style="cursor: pointer;">`;
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
document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// ESCキーで閉じる
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
