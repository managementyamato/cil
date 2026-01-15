<?php
/**
 * マネーフォワード クラウド勤怠 設定画面
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mf-attendance-api.php';

// 認証チェック
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = '';

// 設定保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['api_key']) && !empty($_POST['api_key'])) {
        $apiKey = trim($_POST['api_key']);

        if (MFAttendanceApiClient::saveApiKey($apiKey)) {
            $message = 'API KEYを保存しました。';
            $messageType = 'success';
        } else {
            $message = 'API KEYの保存に失敗しました。';
            $messageType = 'error';
        }
    } else {
        $message = 'API KEYを入力してください。';
        $messageType = 'error';
    }
}

// 現在の設定を取得
$config = MFAttendanceApiClient::getConfig();
$isConfigured = MFAttendanceApiClient::isConfigured();

require_once __DIR__ . '/header.php';
?>

<div class="container">
    <h1>マネーフォワード クラウド勤怠 連携設定</h1>

    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="settings-status">
        <h2>連携状態</h2>
        <p>
            <?php if ($isConfigured): ?>
                <span class="status-badge success">✓ 設定済み</span>
            <?php else: ?>
                <span class="status-badge warning">未設定</span>
            <?php endif; ?>
        </p>
        <?php if ($isConfigured && !empty($config['updated_at'])): ?>
            <p class="last-updated">最終更新: <?php echo htmlspecialchars($config['updated_at']); ?></p>
        <?php endif; ?>
    </div>

    <div class="settings-form">
        <h2>API KEY設定</h2>

        <div class="instruction">
            <h3>API KEYの取得方法</h3>
            <ol>
                <li>マネーフォワード クラウド勤怠にログイン</li>
                <li>「全権管理者メニュー」→「連携」→「外部連携」を開く</li>
                <li>「外部システム連携用識別子」に表示されている<strong>API KEY</strong>をコピー</li>
                <li>下のフォームに貼り付けて保存</li>
            </ol>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="api_key">API KEY <span style="color: red;">*</span></label>
                <input
                    type="text"
                    id="api_key"
                    name="api_key"
                    class="form-control"
                    value="<?php echo htmlspecialchars($config['api_key'] ?? ''); ?>"
                    placeholder="例: afeb99dc395642e16422105eab838cc7"
                    required
                >
                <small>※ マネーフォワード クラウド勤怠の管理画面から取得してください</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存</button>
                <a href="settings.php" class="btn btn-secondary">戻る</a>
            </div>
        </form>
    </div>

    <?php if ($isConfigured): ?>
        <div class="test-section">
            <h2>接続テスト</h2>
            <p>API KEYが正しく設定されているか確認できます。</p>
            <a href="mf-attendance-test.php" class="btn btn-info">接続テスト</a>
        </div>
    <?php endif; ?>
</div>

<style>
.settings-status {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.status-badge {
    display: inline-block;
    padding: 5px 15px;
    border-radius: 4px;
    font-weight: bold;
}

.status-badge.success {
    background: #4CAF50;
    color: white;
}

.status-badge.warning {
    background: #FF9800;
    color: white;
}

.last-updated {
    color: #666;
    font-size: 0.9em;
    margin-top: 10px;
}

.settings-form {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.instruction {
    background: #e3f2fd;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    border-left: 4px solid #2196F3;
}

.instruction h3 {
    margin-top: 0;
    color: #1976D2;
}

.instruction ol {
    margin: 15px 0;
    padding-left: 25px;
}

.instruction li {
    margin: 10px 0;
    line-height: 1.6;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #2196F3;
    box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.1);
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 0.85em;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 30px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: #2196F3;
    color: white;
}

.btn-primary:hover {
    background: #1976D2;
}

.btn-secondary {
    background: #757575;
    color: white;
}

.btn-secondary:hover {
    background: #616161;
}

.btn-info {
    background: #00BCD4;
    color: white;
}

.btn-info:hover {
    background: #0097A7;
}

.test-section {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.message {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.message.success {
    background: #C8E6C9;
    color: #2E7D32;
    border: 1px solid #81C784;
}

.message.error {
    background: #FFCDD2;
    color: #C62828;
    border: 1px solid #E57373;
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
