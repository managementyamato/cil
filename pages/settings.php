<?php
require_once '../config/config.php';
require_once '../api/mf-api.php';
require_once '../functions/notification-functions.php';
require_once '../api/integration/api-auth.php';
require_once '../api/google-oauth.php';
require_once '../api/google-calendar.php';
require_once '../api/google-chat.php';

// 管理者権限チェック
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$notificationConfig = getNotificationConfig();
$integrationConfig = getIntegrationConfig();
$googleOAuth = new GoogleOAuthClient();
$googleCalendar = new GoogleCalendarClient();
$googleChat = new GoogleChatClient();

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// Chat連携解除
if (isset($_POST['disconnect_chat'])) {
    $googleChat->disconnect();
    $_SESSION['chat_success'] = 'Google Chatの連携を解除しました';
    header('Location: settings.php');
    exit;
}

// カレンダー連携解除
if (isset($_POST['disconnect_calendar'])) {
    $googleCalendar->disconnect();
    $_SESSION['calendar_success'] = 'Googleカレンダーの連携を解除しました';
    header('Location: settings.php');
    exit;
}

// セッションメッセージ
$calendarSuccess = $_SESSION['calendar_success'] ?? null;
$calendarError = $_SESSION['calendar_error'] ?? null;
$chatSuccess = $_SESSION['chat_success'] ?? null;
$chatError = $_SESSION['chat_error'] ?? null;
unset($_SESSION['calendar_success'], $_SESSION['calendar_error'], $_SESSION['chat_success'], $_SESSION['chat_error']);

require_once '../functions/header.php';
?>

<style>
.settings-grid {
    display: grid;
    gap: 1.5rem;
}

.setting-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.setting-card h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
    color: var(--gray-900);
}

.setting-card p {
    margin: 0 0 1rem 0;
    color: var(--gray-600);
    font-size: 0.875rem;
}

.setting-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge.success {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.warning {
    background: #fef3c7;
    color: #92400e;
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
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
</style>

<?php if ($calendarSuccess): ?>
<div class="alert alert-success"><?= htmlspecialchars($calendarSuccess) ?></div>
<?php endif; ?>

<?php if ($calendarError): ?>
<div class="alert alert-error"><?= htmlspecialchars($calendarError) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 style="margin: 0;">設定</h2>
    </div>
    <div class="card-body">
        <div class="settings-grid">
            <!-- Google OAuth設定 -->
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>Googleログイン</h3>
                        <p>Googleアカウントでのログインを有効にします</p>
                    </div>
                    <?php if ($googleOAuth->isConfigured()): ?>
                        <span class="status-badge success">✓ 設定済み</span>
                    <?php else: ?>
                        <span class="status-badge warning">未設定</span>
                    <?php endif; ?>
                </div>
                <div class="setting-actions">
                    <a href="google-oauth-settings.php" class="btn btn-primary">
                        <?= $googleOAuth->isConfigured() ? 'OAuth設定編集' : 'OAuth設定' ?>
                    </a>
                </div>
            </div>

            <!-- Googleカレンダー連携 -->
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>Googleカレンダー連携</h3>
                        <p>ダッシュボードに今日の予定を表示します</p>
                    </div>
                    <?php if ($googleCalendar->isConfigured()): ?>
                        <span class="status-badge success">✓ 連携済み</span>
                    <?php else: ?>
                        <span class="status-badge warning">未連携</span>
                    <?php endif; ?>
                </div>
                <div class="setting-actions">
                    <?php if ($googleCalendar->isConfigured()): ?>
                        <form method="POST" style="display: inline;">
                            <?= csrfTokenField() ?>
                            <button type="submit" name="disconnect_calendar" class="btn btn-secondary" onclick="return confirm('カレンダー連携を解除しますか？')">連携解除</button>
                        </form>
                    <?php else: ?>
                        <?php if ($googleOAuth->isConfigured()): ?>
                            <a href="<?= htmlspecialchars($googleCalendar->getAuthUrl()) ?>" class="btn btn-primary">Googleカレンダーを連携</a>
                        <?php else: ?>
                            <span style="color: var(--gray-500); font-size: 0.875rem;">※先にGoogle OAuthを設定してください</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Google Chat連携 -->
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>Google Chat連携</h3>
                        <p>Google Chatからアルコールチェック画像を取り込みます</p>
                    </div>
                    <?php if ($googleChat->isConfigured()): ?>
                        <span class="status-badge success">✓ 連携済み</span>
                    <?php else: ?>
                        <span class="status-badge warning">未連携</span>
                    <?php endif; ?>
                </div>
                <?php if ($chatSuccess): ?>
                    <div class="alert alert-success" style="margin-bottom: 1rem;"><?= htmlspecialchars($chatSuccess) ?></div>
                <?php endif; ?>
                <?php if ($chatError): ?>
                    <div class="alert alert-error" style="margin-bottom: 1rem;"><?= htmlspecialchars($chatError) ?></div>
                <?php endif; ?>
                <div class="setting-actions">
                    <?php if ($googleChat->isConfigured()): ?>
                        <form method="POST" style="display: inline;">
                            <?= csrfTokenField() ?>
                            <button type="submit" name="disconnect_chat" class="btn btn-secondary" onclick="return confirm('Google Chat連携を解除しますか？')">連携解除</button>
                        </form>
                    <?php else: ?>
                        <?php if ($googleOAuth->isConfigured()): ?>
                            <a href="<?= htmlspecialchars($googleChat->getAuthUrl()) ?>" class="btn btn-primary">Google Chatを連携</a>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: var(--gray-500);">※ Google Cloud Consoleで Chat API を有効にしてください</p>
                        <?php else: ?>
                            <span style="color: var(--gray-500); font-size: 0.875rem;">※先にGoogle OAuthを設定してください</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- MF請求書連携設定 -->
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>MF請求書連携</h3>
                        <p>MoneyForward請求書とのAPI連携を設定します</p>
                    </div>
                    <?php if (MFApiClient::isConfigured()): ?>
                        <span class="status-badge success">✓ 設定済み</span>
                    <?php else: ?>
                        <span class="status-badge warning">未設定</span>
                    <?php endif; ?>
                </div>
                <div class="setting-actions">
                    <a href="mf-settings.php" class="btn btn-primary">
                        <?= MFApiClient::isConfigured() ? 'MF設定を編集' : 'MF連携を設定' ?>
                    </a>
                </div>
            </div>

            <!-- 通知設定 -->
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>通知設定</h3>
                        <p>トラブル発生時のメール通知を設定します</p>
                    </div>
                    <?php if ($notificationConfig['enabled']): ?>
                        <span class="status-badge success">✓ 有効</span>
                    <?php else: ?>
                        <span class="status-badge warning">無効</span>
                    <?php endif; ?>
                </div>
                <div class="setting-actions">
                    <a href="notification-settings.php" class="btn btn-primary">通知設定</a>
                </div>
            </div>

            <!-- API連携設定 -->
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>API連携設定</h3>
                        <p>外部システムとのAPI連携を設定します</p>
                    </div>
                    <?php if ($integrationConfig['enabled']): ?>
                        <span class="status-badge success">✓ 有効</span>
                    <?php else: ?>
                        <span class="status-badge warning">無効</span>
                    <?php endif; ?>
                </div>
                <div class="setting-actions">
                    <a href="integration-settings.php" class="btn btn-primary">API連携設定</a>
                </div>
            </div>

            <!-- アカウント権限設定 -->
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>アカウント権限設定</h3>
                        <p>各ユーザーの閲覧・編集権限を設定します</p>
                    </div>
                </div>
                <div class="setting-actions">
                    <a href="user-permissions.php" class="btn btn-primary">権限設定</a>
                </div>
            </div>

            <!-- 従業員マスタ -->
            <?php if (canEdit()): ?>
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>従業員マスタ</h3>
                        <p>従業員情報の管理を行います</p>
                    </div>
                </div>
                <div class="setting-actions">
                    <a href="employees.php" class="btn btn-primary">従業員マスタ</a>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../functions/footer.php'; ?>
