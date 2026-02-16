<?php
require_once '../api/auth.php';
require_once '../api/mf-api.php';

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

// OAuth認証を開始
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_oauth'])) {
    $clientId = trim($_POST['client_id'] ?? '');
    $clientSecret = trim($_POST['client_secret'] ?? '');

    if (empty($clientId) || empty($clientSecret)) {
        $error = 'Client IDとClient Secretを入力してください';
    } else {
        // Client ID/Secretを保存
        MFApiClient::saveCredentials($clientId, $clientSecret);

        // OAuth認証フローを開始
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $redirectUri = $protocol . '://' . $host . '/pages/mf-callback.php';

        // CSRF対策用のstateを生成してセッションに保存
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        // 認証URLを生成してリダイレクト
        $client = new MFApiClient();
        $authUrl = $client->getAuthorizationUrl($redirectUri, $state);
        header('Location: ' . $authUrl);
        exit;
    }
}

// 設定削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_settings'])) {
    $configFile = __DIR__ . '/../config/mf-config.json';
    if (file_exists($configFile)) {
        unlink($configFile);
        writeAuditLog('delete', 'mf_settings', 'MF連携設定を削除');
        $message = '設定を削除しました';
    }
}

// 現在の設定を読み込み
$configFile = __DIR__ . '/../config/mf-config.json';
$currentConfig = array();
if (file_exists($configFile)) {
    $currentConfig = json_decode(file_get_contents($configFile), true) ?: array();
}

$isConfigured = MFApiClient::isConfigured();

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
}
.setting-card h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    color: var(--gray-900);
}
.setting-card p {
    margin: 0 0 1rem 0;
    color: var(--gray-600);
    font-size: 0.875rem;
}

/* ステータスバッジ */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
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

.danger-zone {
    background: #fef2f2;
    border: 1px solid #fecaca;
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 1.5rem;
}
.danger-zone h4 {
    color: #991b1b;
    margin: 0 0 0.75rem 0;
    font-size: 1rem;
}
</style>

<div class="page-container">
<div class="settings-detail-header">
    <a href="settings.php" class="btn btn-secondary btn-sm">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        一覧に戻る
    </a>
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="w-24 h-24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        マネーフォワード クラウド 連携設定
    </h2>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="setting-card">
    <div    class="d-flex justify-between mb-2 align-start">
        <div>
            <h3>MFクラウド請求書連携</h3>
            <p>MoneyForward クラウド請求書とのAPI連携を設定します。</p>
        </div>
        <?php if ($isConfigured): ?>
            <span class="status-badge success">✓ 接続済み</span>
        <?php else: ?>
            <span class="status-badge warning">未接続</span>
        <?php endif; ?>
    </div>

    <?php if ($isConfigured && !empty($currentConfig['updated_at'])): ?>
        <p    class="mb-2 text-gray-600 text-2xs">
            最終更新: <?= htmlspecialchars($currentConfig['updated_at']) ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrfTokenField() ?>
        <div class="form-group">
            <label for="client_id">Client ID *</label>
            <input
                type="text"
                class="form-input"
                id="client_id"
                name="client_id"
                value="<?= htmlspecialchars($currentConfig['client_id'] ?? '') ?>"
                placeholder="MFクラウド請求書で発行したClient IDを入力"
                required
            >
        </div>

        <div class="form-group">
            <label for="client_secret">Client Secret *</label>
            <input
                type="text"
                class="form-input"
                id="client_secret"
                name="client_secret"
                value="<?= htmlspecialchars($currentConfig['client_secret'] ?? '') ?>"
                placeholder="MFクラウド請求書で発行したClient Secretを入力"
                required
            >
        </div>

        <button type="submit" name="start_oauth" class="btn btn-primary">
            OAuth認証を開始
        </button>
    </form>

    <?php if ($isConfigured): ?>
    <div class="danger-zone">
        <h4>設定を削除</h4>
        <p       class="text-14" class="m-0-1-991">
            API連携設定を削除します。保存されているアクセストークンも削除されます。
        </p>
        <form method="POST" action="" id="deleteSettingsForm">
            <?= csrfTokenField() ?>
            <button type="submit" name="delete_settings" class="btn btn-danger">
                設定を削除
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>
</div><!-- /.page-container -->

<script<?= nonceAttr() ?>>
// 設定削除フォームの確認
document.getElementById('deleteSettingsForm')?.addEventListener('submit', function(e) {
    if (!confirm('本当に設定を削除しますか？')) {
        e.preventDefault();
    }
});
</script>

<?php require_once '../functions/footer.php'; ?>
