<?php
/**
 * 従業員用写真アップロード画面
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
    echo '<p>写真アップロード機能を使用するには、まず従業員マスタに従業員を登録してください。</p>';
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

// アップロード処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $uploadType = $_POST['upload_type'] ?? '';

    $result = uploadPhoto($employee['id'], $uploadType, $_FILES['photo']);

    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// 現在の状況を取得
$uploadStatus = getEmployeeUploadStatus($employee['id']);

require_once __DIR__ . '/header.php';
?>

<style>
.upload-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.upload-card {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.upload-card h3 {
    margin-top: 0;
    color: var(--gray-900);
}

.upload-form {
    margin-top: 1.5rem;
}

.file-input-wrapper {
    position: relative;
    margin: 1rem 0;
}

.file-input {
    display: none;
}

.file-label {
    display: inline-block;
    padding: 12px 24px;
    background: var(--primary-color);
    color: white;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.3s;
}

.file-label:hover {
    background: var(--primary-dark);
}

.file-name {
    margin-left: 1rem;
    color: var(--gray-600);
}

.preview-container {
    margin: 1rem 0;
}

.preview-image {
    max-width: 100%;
    max-height: 400px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-upload {
    background: var(--success-color);
    color: white;
    padding: 12px 32px;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-upload:hover {
    background: #388e3c;
}

.btn-upload:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.status-indicator {
    display: flex;
    gap: 2rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: #f5f5f5;
    border-radius: 8px;
}

.status-item {
    flex: 1;
    text-align: center;
}

.status-icon {
    font-size: 3rem;
    margin-bottom: 0.5rem;
}

.status-text {
    font-weight: bold;
    color: var(--gray-700);
}

.status-uploaded {
    color: var(--success-color);
}

.status-pending {
    color: var(--warning-color);
}

.message {
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1.5rem;
    text-align: center;
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

.instructions {
    background: #e3f2fd;
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid #2196f3;
    margin-bottom: 2rem;
}

.instructions h4 {
    margin-top: 0;
    color: #1976d2;
}

.instructions ul {
    margin: 0.5rem 0;
    padding-left: 1.5rem;
}
</style>

<div class="upload-container">
    <div class="card">
        <div class="card-header">
            <h2 style="margin: 0;">写真アップロード - <?= htmlspecialchars($employee['name']); ?></h2>
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
                        出勤写真<br>
                        <?= $uploadStatus['start'] ? 'アップロード済み' : '未アップロード'; ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-icon <?= $uploadStatus['end'] ? 'status-uploaded' : 'status-pending'; ?>">
                        <?= $uploadStatus['end'] ? '✓' : '○'; ?>
                    </div>
                    <div class="status-text">
                        退勤写真<br>
                        <?= $uploadStatus['end'] ? 'アップロード済み' : '未アップロード'; ?>
                    </div>
                </div>
            </div>

            <!-- 使い方 -->
            <div class="instructions">
                <h4>使い方</h4>
                <ul>
                    <li>出勤時と退勤時にそれぞれ1回ずつ写真をアップロードしてください</li>
                    <li>顔がはっきり写っている写真をアップロードしてください</li>
                    <li>画像ファイル（JPEG、PNG、GIF）のみアップロード可能です</li>
                    <li>ファイルサイズは5MB以下にしてください</li>
                </ul>
            </div>

            <!-- 出勤写真アップロード -->
            <div class="upload-card">
                <h3>📷 出勤写真をアップロード</h3>
                <?php if ($uploadStatus['start']): ?>
                    <p style="color: var(--success-color); font-weight: bold;">✓ 本日の出勤写真はアップロード済みです</p>
                    <p style="font-size: 0.875rem; color: var(--gray-600);">再度アップロードすると上書きされます</p>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="upload-form" id="form-start">
                    <input type="hidden" name="upload_type" value="start">
                    <div class="file-input-wrapper">
                        <input type="file"
                               name="photo"
                               id="photo-start"
                               class="file-input"
                               accept="image/*"
                               capture="user"
                               required
                               onchange="previewImage(this, 'preview-start')">
                        <label for="photo-start" class="file-label">写真を選択</label>
                        <span class="file-name" id="filename-start"></span>
                    </div>
                    <div id="preview-start" class="preview-container"></div>
                    <button type="submit" class="btn-upload">アップロード</button>
                </form>
            </div>

            <!-- 退勤写真アップロード -->
            <div class="upload-card">
                <h3>📷 退勤写真をアップロード</h3>
                <?php if ($uploadStatus['end']): ?>
                    <p style="color: var(--success-color); font-weight: bold;">✓ 本日の退勤写真はアップロード済みです</p>
                    <p style="font-size: 0.875rem; color: var(--gray-600);">再度アップロードすると上書きされます</p>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="upload-form" id="form-end">
                    <input type="hidden" name="upload_type" value="end">
                    <div class="file-input-wrapper">
                        <input type="file"
                               name="photo"
                               id="photo-end"
                               class="file-input"
                               accept="image/*"
                               capture="user"
                               required
                               onchange="previewImage(this, 'preview-end')">
                        <label for="photo-end" class="file-label">写真を選択</label>
                        <span class="file-name" id="filename-end"></span>
                    </div>
                    <div id="preview-end" class="preview-container"></div>
                    <button type="submit" class="btn-upload">アップロード</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const filenameSpan = document.getElementById('filename-' + (previewId === 'preview-start' ? 'start' : 'end'));

    if (input.files && input.files[0]) {
        const reader = new FileReader();

        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" class="preview-image" alt="プレビュー">';
        };

        reader.readAsDataURL(input.files[0]);
        filenameSpan.textContent = input.files[0].name;
    } else {
        preview.innerHTML = '';
        filenameSpan.textContent = '';
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
