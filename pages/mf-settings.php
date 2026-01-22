<?php
require_once '../config/config.php';
require_once '../api/mf-api.php';

// 管理者のみアクセス可能
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

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

<style>
.mf-settings-container {
    max-width: 900px;
}

.status-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 600;
    margin-bottom: 1rem;
}

.status-connected {
    background: #d1fae5;
    color: #065f46;
}

.status-disconnected {
    background: #fee2e2;
    color: #991b1b;
}

.info-box {
    background: #dbeafe;
    color: #1e40af;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.danger-zone {
    background: #fef2f2;
    border: 1px solid #fecaca;
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 2rem;
}

.danger-zone h3 {
    color: #991b1b;
    margin: 0 0 1rem 0;
}
</style>

<div class="mf-settings-container">
    <h2>マネーフォワード クラウド 連携設定</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="status-badge <?= $isConfigured ? 'status-connected' : 'status-disconnected' ?>">
                <?= $isConfigured ? '✓ 接続済み' : '✗ 未接続' ?>
            </div>

            <?php if ($isConfigured && !empty($currentConfig['updated_at'])): ?>
                <p style="color: var(--gray-600); font-size: 0.875rem;">
                    最終更新: <?= htmlspecialchars($currentConfig['updated_at']) ?>
                </p>
            <?php endif; ?>

            <div class="info-box">
                <h3>MF API連携について</h3>
                <p style="margin: 0 0 0.5rem 0;">マネーフォワード クラウドのAPIを使用して、以下の機能が利用できます：</p>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <li><strong>請求書API:</strong> トラブル案件から直接請求書を作成、請求書・見積書データの同期</li>
                    <li><strong>会計API:</strong> 勘定科目、取引先、部門、品目、税区分、仕訳データの取得</li>
                </ul>
                <p style="margin: 1rem 0 0 0; padding: 0.75rem; background: #fef3c7; border-left: 3px solid #f59e0b; font-size: 0.875rem;">
                    <strong>重要:</strong> 認証時に請求書APIと会計APIの両方のスコープが付与されます。
                </p>
            </div>

            <form method="POST" action="">
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
                    <div class="help-text" style="font-size: 0.875rem; color: var(--gray-600); margin-top: 0.5rem;">
                        <strong>取得方法:</strong>
                        <ol style="margin: 0.5rem 0 0 1.5rem; padding: 0;">
                            <li><a href="https://invoice.moneyforward.com/" target="_blank">MF クラウド請求書</a>にログイン</li>
                            <li>「各種設定」→「連携サービス設定」を開く</li>
                            <li>「API連携設定」→「OAuth認証アプリケーションを追加」をクリック</li>
                            <li>リダイレクトURIに <code style="background: #f0f0f0; padding: 0.2rem 0.4rem; border-radius: 3px;"><?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/mf-callback.php</code> を入力</li>
                            <li>発行されたClient IDとClient Secretをコピー</li>
                        </ol>
                    </div>
                </div>

                <button type="submit" name="start_oauth" class="btn btn-primary">
                    OAuth認証を開始
                </button>
            </form>

            <?php if ($isConfigured): ?>
                <div class="danger-zone">
                    <h3>危険な操作</h3>
                    <p style="margin: 0 0 1rem 0; color: #991b1b;">
                        API連携設定を削除します。保存されているアクセストークンも削除されます。
                    </p>
                    <form method="POST" action="" onsubmit="return confirm('本当に設定を削除しますか？')">
                        <button type="submit" name="delete_settings" class="btn btn-danger">
                            設定を削除
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../functions/footer.php'; ?>
