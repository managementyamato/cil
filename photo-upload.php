<?php
/**
 * 従業員用アルコールチェック写真画面
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/photo-attendance-functions.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// ユーザーIDから従業員IDを取得
$userId = $_SESSION['user_id'] ?? null;
$employees = getEmployees();
$employee = null;

// 従業員データがない場合
if (empty($employees)) {
    require_once __DIR__ . '/header.php';
    echo '<div style="max-width: 800px; margin: 2rem auto; padding: 2rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px;">';
    echo '<h2 style="color: #856404;">従業員データが登録されていません</h2>';
    echo '<p>アルコールチェック写真機能を使用するには、まず従業員マスタに従業員を登録してください。</p>';
    if (isAdmin()) {
        echo '<a href="employees.php" class="btn btn-primary">従業員マスタへ</a>';
    } else {
        echo '<p>管理者に従業員登録を依頼してください。</p>';
    }
    echo '</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

foreach ($employees as $emp) {
    if ($emp['id'] == $userId) {
        $employee = $emp;
        break;
    }
}

if (!$employee) {
    require_once __DIR__ . '/header.php';
    echo '<div style="max-width: 800px; margin: 2rem auto; padding: 2rem; background: #f8d7da; border: 1px solid #f44336; border-radius: 8px;">';
    echo '<h2 style="color: #721c24;">従業員情報が見つかりません</h2>';
    echo '<p>ログインユーザーID: ' . htmlspecialchars($userId ?? 'なし') . '</p>';
    echo '<p>従業員マスタに登録されていません。管理者に登録を依頼してください。</p>';
    if (isAdmin()) {
        echo '<a href="employees.php" class="btn btn-primary">従業員マスタへ</a>';
    }
    echo '</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$message = '';
$messageType = '';

// 通勤時車使用していないチェック処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['no_car_usage'])) {
    // 通勤時車使用していないフラグを保存
    $data = getData();

    // 本日の日付
    $today = date('Y-m-d');

    // 従業員の no_car_usage データを保存
    if (!isset($data['no_car_usage'])) {
        $data['no_car_usage'] = [];
    }

    $data['no_car_usage'][] = [
        'employeeId' => $employee['id'],
        'employeeName' => $employee['name'],
        'date' => $today,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    saveData($data);

    $message = '通勤時車使用していないことを記録しました。本日はアルコールチェック不要です。';
    $messageType = 'success';
}

// アップロード処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    // 時間帯で出勤・退勤を自動判別
    $currentHour = (int)date('H');

    // 12時以前は出勤、12時以降は退勤と判定
    if ($currentHour < 12) {
        $uploadType = 'start';
        $timeLabel = '出勤前';
    } else {
        $uploadType = 'end';
        $timeLabel = '退勤前';
    }

    $result = uploadPhoto($employee['id'], $uploadType, $_FILES['photo']);

    $message = $timeLabel . 'チェックとして' . $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// 本日の通勤時車使用していない記録をチェック
$data = getData();
$today = date('Y-m-d');
$hasNoCarUsage = false;

if (isset($data['no_car_usage'])) {
    foreach ($data['no_car_usage'] as $record) {
        if ($record['employeeId'] == $employee['id'] && $record['date'] == $today) {
            $hasNoCarUsage = true;
            break;
        }
    }
}

// 現在の状況を取得
$uploadStatus = getEmployeeUploadStatus($employee['id']);

require_once __DIR__ . '/header.php';
?>

<style>
.upload-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 10px;
}

.upload-card {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
}

.upload-card h3 {
    margin-top: 0;
    color: var(--gray-900);
    font-size: 1.2rem;
}

.upload-form {
    margin-top: 1rem;
}

/* スマホ対応 */
@media (min-width: 768px) {
    .upload-container {
        padding: 20px;
    }

    .upload-card {
        padding: 2rem;
        margin-bottom: 2rem;
    }

    .upload-card h3 {
        font-size: 1.5rem;
    }

    .upload-form {
        margin-top: 1.5rem;
    }
}

.file-input-wrapper {
    position: relative;
    margin: 1rem 0;
}

.file-input {
    width: 100%;
    padding: 10px;
    border: 2px dashed #ccc;
    border-radius: 4px;
    background: #f9f9f9;
    cursor: pointer;
    font-size: 0.9rem;
    box-sizing: border-box;
}

.file-input:hover {
    border-color: var(--primary-color);
    background: #f0f0f0;
}

.file-label {
    display: block;
    padding: 14px 20px;
    background: var(--primary-color);
    color: white;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.3s;
    text-align: center;
    font-size: 1rem;
    font-weight: bold;
}

.file-label:hover {
    background: var(--primary-dark);
}

.file-name {
    margin-left: 0;
    color: var(--gray-600);
    word-break: break-all;
    font-size: 0.875rem;
}

.preview-container {
    margin: 1rem 0;
    text-align: center;
}

.preview-image {
    max-width: 100%;
    height: auto;
    max-height: 300px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-upload {
    background: var(--success-color);
    color: white;
    padding: 14px 24px;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.3s;
    width: 100%;
    font-weight: bold;
}

.btn-upload:hover {
    background: #388e3c;
}

.btn-upload:disabled {
    background: #ccc;
    cursor: not-allowed;
}

@media (min-width: 768px) {
    .file-input {
        padding: 12px;
        font-size: 1rem;
    }

    .file-label {
        padding: 12px 24px;
    }

    .preview-image {
        max-height: 400px;
    }

    .btn-upload {
        width: auto;
        padding: 12px 32px;
    }
}

.status-indicator {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f5f5f5;
    border-radius: 8px;
    flex-wrap: wrap;
}

.status-item {
    flex: 1;
    min-width: 120px;
    text-align: center;
}

.status-icon {
    font-size: 2rem;
    margin-bottom: 0.25rem;
}

.status-text {
    font-weight: bold;
    color: var(--gray-700);
    font-size: 0.75rem;
    line-height: 1.3;
}

@media (min-width: 768px) {
    .status-indicator {
        gap: 2rem;
        margin-bottom: 2rem;
        padding: 1.5rem;
        flex-wrap: nowrap;
    }

    .status-item {
        min-width: auto;
    }

    .status-icon {
        font-size: 3rem;
        margin-bottom: 0.5rem;
    }

    .status-text {
        font-size: 1rem;
    }
}

.status-uploaded {
    color: var(--success-color);
}

.status-pending {
    color: var(--warning-color);
}

.message {
    padding: 0.875rem;
    border-radius: 4px;
    margin-bottom: 1rem;
    text-align: center;
    font-size: 0.9rem;
    line-height: 1.4;
}

.message.success {
    background: #c8e6c9;
    color: #2e7d32;
    border: 1px solid #81c784;
}

.message.error {
    background: #ffcdd2;
    color: #c62828;
    border: 1px solid #e57373;
}

@media (min-width: 768px) {
    .message {
        padding: 1rem;
        margin-bottom: 1.5rem;
        font-size: 1rem;
    }
}

.instructions {
    background: #e3f2fd;
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid #2196f3;
    margin-bottom: 1rem;
}

.instructions h4 {
    margin-top: 0;
    color: #1976d2;
    font-size: 1rem;
}

.instructions ul {
    margin: 0.5rem 0;
    padding-left: 1.2rem;
    font-size: 0.875rem;
}

.instructions li {
    margin-bottom: 0.25rem;
}

@media (min-width: 768px) {
    .instructions {
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .instructions h4 {
        font-size: 1.2rem;
    }

    .instructions ul {
        padding-left: 1.5rem;
        font-size: 1rem;
    }

    .instructions li {
        margin-bottom: 0.5rem;
    }
}

.no-car-section {
    background: #fff9e6;
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid #ffa726;
    margin-bottom: 1rem;
}

.no-car-section h4 {
    font-size: 0.95rem;
    line-height: 1.4;
}

.no-car-section p {
    font-size: 0.875rem;
}

.no-car-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin-top: 1rem;
}

.no-car-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    flex-shrink: 0;
    margin-top: 2px;
}

.no-car-checkbox label {
    font-size: 0.9rem;
    font-weight: bold;
    cursor: pointer;
    color: #e65100;
    line-height: 1.4;
}

.btn-no-car {
    background: #ff9800;
    color: white;
    padding: 14px 24px;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.3s;
    margin-top: 1rem;
    width: 100%;
    font-weight: bold;
}

.btn-no-car:hover {
    background: #f57c00;
}

@media (min-width: 768px) {
    .no-car-section {
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .no-car-section h4 {
        font-size: 1.1rem;
    }

    .no-car-section p {
        font-size: 1rem;
    }

    .no-car-checkbox label {
        font-size: 1rem;
    }

    .btn-no-car {
        width: auto;
        padding: 12px 32px;
    }
}
</style>

<div class="upload-container">
    <div class="card">
        <div class="card-header">
            <h2 style="margin: 0;">アルコールチェック写真 - <?= htmlspecialchars($employee['name']); ?></h2>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="message <?= htmlspecialchars($messageType); ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- 現在のステータス -->
            <div class="status-indicator">
                <div class="status-item">
                    <div class="status-icon <?= $uploadStatus['start'] ? 'status-uploaded' : 'status-pending'; ?>">
                        <?= $uploadStatus['start'] ? '✓' : '○'; ?>
                    </div>
                    <div class="status-text">
                        出勤前チェック<br>
                        <?= $uploadStatus['start'] ? 'アップロード済み' : '未アップロード'; ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-icon <?= $uploadStatus['end'] ? 'status-uploaded' : 'status-pending'; ?>">
                        <?= $uploadStatus['end'] ? '✓' : '○'; ?>
                    </div>
                    <div class="status-text">
                        退勤前チェック<br>
                        <?= $uploadStatus['end'] ? 'アップロード済み' : '未アップロード'; ?>
                    </div>
                </div>
            </div>

            <!-- 使い方 -->
            <div class="instructions">
                <h4>使い方</h4>
                <ul>
                    <li>通勤時に車を使用する場合：アルコールチェック写真をアップロードしてください</li>
                    <li>通勤時に車を使用しない場合：下の「通勤時車使用していない」を選択してください</li>
                    <li>時間帯により自動で出勤前/退勤前を判別します（12時前：出勤前、12時以降：退勤前）</li>
                    <li>顔がはっきり写っているチェック写真をアップロードしてください</li>
                    <li>画像ファイル（JPEG、PNG、GIF、HEIC）に対応しています</li>
                    <li>ファイルサイズは50MB以下にしてください</li>
                </ul>
            </div>

            <?php if ($hasNoCarUsage): ?>
                <!-- 通勤時車使用していない記録済み -->
                <div class="no-car-section">
                    <h4 style="margin: 0; color: #e65100;">✓ 本日は通勤時車使用していないことが記録されています</h4>
                    <p style="margin: 0.5rem 0 0 0; color: #666;">アルコールチェックは不要です。お疲れ様です。</p>
                </div>
            <?php else: ?>

            <!-- アルコールチェック写真アップロード（統合版） -->
            <div class="upload-card">
                <h3>📷 アルコールチェック写真をアップロード</h3>

                <?php
                $currentHour = (int)date('H');
                $isStartTime = $currentHour < 12;
                $currentTimeLabel = $isStartTime ? '出勤前' : '退勤前';
                $currentStatus = $isStartTime ? $uploadStatus['start'] : $uploadStatus['end'];
                ?>

                <div style="background: #f0f7ff; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; border-left: 4px solid #2196f3;">
                    <p style="margin: 0; font-weight: bold; color: #1976d2;">
                        現在時刻: <?= date('H:i') ?> → <?= $currentTimeLabel ?>チェックとして記録されます
                    </p>
                </div>

                <?php if ($currentStatus): ?>
                    <p style="color: var(--success-color); font-weight: bold;">✓ 本日の<?= $currentTimeLabel ?>チェックはアップロード済みです</p>
                    <p style="font-size: 0.875rem; color: var(--gray-600);">再度アップロードすると上書きされます</p>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="upload-form" id="form-upload">
                    <div class="file-input-wrapper">
                        <label for="photo-upload" class="file-label">📸 チェック写真を撮影/選択</label>
                        <input type="file"
                               name="photo"
                               id="photo-upload"
                               class="file-input"
                               accept="image/*,.heic"
                               capture="user"
                               required
                               onchange="previewImage(this, 'preview-upload')"
                               style="margin-top: 0.5rem;">
                        <span class="file-name" id="filename-upload" style="display: block; margin-top: 0.5rem; color: #666;"></span>
                    </div>
                    <div id="preview-upload" class="preview-container"></div>
                    <button type="submit" class="btn-upload">アップロード</button>
                </form>
            </div>

            <!-- 通勤時車使用していない -->
            <div class="upload-card" style="border-left: 4px solid #ff9800;">
                <h3>🚶 通勤時車使用していない</h3>
                <p style="color: #666; margin-bottom: 1rem;">
                    本日、通勤時に車を使用していない場合は、こちらを選択してください。<br>
                    選択するとアルコールチェック写真のアップロードは不要になります。
                </p>

                <form method="POST" id="form-no-car">
                    <div class="no-car-checkbox">
                        <input type="checkbox" id="no-car-confirm" required>
                        <label for="no-car-confirm">通勤時に車を使用していないことを確認しました</label>
                    </div>
                    <input type="hidden" name="no_car_usage" value="1">
                    <button type="submit" class="btn-no-car" onclick="return confirm('通勤時に車を使用していないことを記録します。よろしいですか？');">提出する</button>
                </form>
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const filenameSpan = document.getElementById('filename-upload');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileName = file.name.toLowerCase();

        // HEIC形式の場合は警告を表示（プレビューは表示できないがアップロードは可能）
        if (fileName.endsWith('.heic') || fileName.endsWith('.heif')) {
            preview.innerHTML = '<div style="padding: 2rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; text-align: center;">' +
                '<p style="margin: 0; color: #856404;">📱 HEIC形式の画像が選択されました</p>' +
                '<p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: #856404;">プレビューは表示できませんが、アップロードは可能です</p>' +
                '</div>';
            filenameSpan.textContent = file.name;
        } else {
            // 通常の画像形式の場合はプレビューを表示
            const reader = new FileReader();

            reader.onload = function(e) {
                preview.innerHTML = '<img src="' + e.target.result + '" class="preview-image" alt="プレビュー">';
            };

            reader.readAsDataURL(file);
            filenameSpan.textContent = file.name;
        }
    } else {
        preview.innerHTML = '';
        filenameSpan.textContent = '';
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
