<?php
/**
 * 通知設定ページ
 */
require_once '../api/auth.php';
require_once '../functions/notification-functions.php';

// 管理者のみアクセス可能
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// 設定保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $config = [
        'enabled' => isset($_POST['enabled']),
        'email_recipients' => array_filter(array_map('trim', explode("\n", $_POST['email_recipients'] ?? ''))),
        'notify_on_new_trouble' => isset($_POST['notify_on_new_trouble']),
        'notify_on_status_change' => isset($_POST['notify_on_status_change']),
        'smtp_host' => trim($_POST['smtp_host'] ?? ''),
        'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
        'smtp_username' => trim($_POST['smtp_username'] ?? ''),
        'smtp_password' => trim($_POST['smtp_password'] ?? ''),
        'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
        'smtp_from_name' => trim($_POST['smtp_from_name'] ?? 'YA管理システム')
    ];
    saveNotificationConfig($config);
    $message = '設定を保存しました';
}

// テストメール送信
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $testEmail = trim($_POST['test_email'] ?? '');
    if (empty($testEmail)) {
        $error = 'テスト送信先メールアドレスを入力してください';
    } else {
        $subject = '[YA管理] テストメール';
        $body = '<html><body     style="font-family: sans-serif">';
        $body .= '<h2>テストメール</h2>';
        $body .= '<p>これはYA管理システムからのテストメールです。</p>';
        $body .= '<p>送信日時: ' . date('Y-m-d H:i:s') . '</p>';
        $body .= '</body></html>';

        if (sendNotificationEmail($testEmail, $subject, $body)) {
            $message = 'テストメールを送信しました';
        } else {
            $error = 'テストメールの送信に失敗しました。設定を確認してください。';
        }
    }
}

$config = getNotificationConfig();

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
/* 設定詳細ヘッダー */
.settings-detail-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.settings-detail-header h2 {
    margin: 0;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* 設定カード */
.setting-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}
.setting-card h3 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: var(--gray-900);
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--gray-200);
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}
.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}
.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
}
.help-text {
    font-size: 0.8rem;
    color: var(--gray-600);
    margin-top: 0.25rem;
}
.test-section {
    display: flex;
    gap: 0.5rem;
    align-items: end;
}
.test-section .form-group {
    flex: 1;
    margin-bottom: 0;
}
</style>

<div class="page-container">
<div class="settings-detail-header">
    <a href="settings.php" class="btn btn-secondary btn-sm">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        一覧に戻る
    </a>
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="w-24 h-24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        通知設定
    </h2>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="">
    <?= csrfTokenField() ?>
    <!-- 基本設定 -->
    <div class="setting-card">
        <h3>基本設定</h3>
            <div class="checkbox-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="enabled" <?= $config['enabled'] ? 'checked' : '' ?>>
                    <span>メール通知を有効にする</span>
                </label>
        </div>
    </div>

    <!-- 通知タイミング -->
    <div class="setting-card">
        <h3>通知タイミング</h3>
            <div class="checkbox-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="notify_on_new_trouble" <?= ($config['notify_on_new_trouble'] ?? true) ? 'checked' : '' ?>>
                    <span>新規トラブル登録時に通知</span>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="notify_on_status_change" <?= ($config['notify_on_status_change'] ?? false) ? 'checked' : '' ?>>
                    <span>ステータス変更時に通知</span>
                </label>
        </div>
    </div>

    <!-- 通知先メールアドレス -->
    <div class="setting-card">
        <h3>通知先メールアドレス</h3>
            <div class="form-group">
                <label for="email_recipients">通知先（1行に1つのメールアドレス）</label>
                <textarea
                    class="form-input"
                    id="email_recipients"
                    name="email_recipients"
                    rows="4"
                    placeholder="admin@example.com&#10;manager@example.com"
                ><?= htmlspecialchars(implode("\n", $config['email_recipients'] ?? [])) ?></textarea>
        </div>
    </div>

    <!-- SMTP設定 -->
    <div class="setting-card">
        <h3>SMTP設定（オプション）</h3>
            <p   class="help-text mb-2">
                SMTPサーバーを設定しない場合、サーバーのmail()関数が使用されます。
                Xserverなどのレンタルサーバーでは通常設定不要です。
            </p>

            <div class="form-group">
                <label for="smtp_host">SMTPホスト</label>
                <input type="text" class="form-input" id="smtp_host" name="smtp_host"
                    value="<?= htmlspecialchars($config['smtp_host'] ?? '') ?>"
                    placeholder="smtp.example.com">
            </div>

            <div class="form-group">
                <label for="smtp_port">SMTPポート</label>
                <input type="number" class="form-input" id="smtp_port" name="smtp_port"
                    value="<?= htmlspecialchars($config['smtp_port'] ?? 587) ?>"
                    class="w-120">
            </div>

            <div class="form-group">
                <label for="smtp_username">SMTPユーザー名</label>
                <input type="text" class="form-input" id="smtp_username" name="smtp_username"
                    value="<?= htmlspecialchars($config['smtp_username'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="smtp_password">SMTPパスワード</label>
                <input type="password" class="form-input" id="smtp_password" name="smtp_password"
                    value="<?= htmlspecialchars($config['smtp_password'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="smtp_from_email">送信元メールアドレス</label>
                <input type="email" class="form-input" id="smtp_from_email" name="smtp_from_email"
                    value="<?= htmlspecialchars($config['smtp_from_email'] ?? '') ?>"
                    placeholder="noreply@example.com">
            </div>

            <div class="form-group">
                <label for="smtp_from_name">送信元名</label>
                <input type="text" class="form-input" id="smtp_from_name" name="smtp_from_name"
                    value="<?= htmlspecialchars($config['smtp_from_name'] ?? 'YA管理システム') ?>">
        </div>
    </div>

    <button type="submit" name="save_settings" class="btn btn-primary">設定を保存</button>
</form>

<!-- テスト送信 -->
<div   class="setting-card mt-3">
    <h3>テスト送信</h3>
    <form method="POST" action="">
        <?= csrfTokenField() ?>
        <div class="test-section">
            <div class="form-group">
                <label for="test_email">送信先メールアドレス</label>
                <input type="email" class="form-input" id="test_email" name="test_email"
                    placeholder="test@example.com" required>
            </div>
            <button type="submit" name="send_test" class="btn btn-secondary">テスト送信</button>
        </div>
    </form>
</div>
</div><!-- /.page-container -->

<?php require_once '../functions/footer.php'; ?>
