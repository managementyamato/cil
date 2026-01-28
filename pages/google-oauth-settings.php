<?php
require_once '../config/config.php';
require_once '../api/google-oauth.php';

// 管理者のみアクセス可能
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';
$configFile = __DIR__ . '/../config/google-config.json';

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// 設定保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $clientId = trim($_POST['client_id'] ?? '');
    $clientSecret = trim($_POST['client_secret'] ?? '');
    $allowedDomainsInput = trim($_POST['allowed_domains'] ?? '');

    if (empty($clientId) || empty($clientSecret)) {
        $error = 'Client IDとClient Secretを入力してください';
    } else {
        // リダイレクトURIを自動生成
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $redirectUri = $protocol . '://' . $host . '/api/google-callback.php';

        // 許可ドメインをパース（カンマまたは改行区切り）
        $allowedDomains = array();
        if (!empty($allowedDomainsInput)) {
            $domains = preg_split('/[\s,]+/', $allowedDomainsInput);
            foreach ($domains as $domain) {
                $domain = trim($domain);
                // @で始まる場合は除去
                if (strpos($domain, '@') === 0) {
                    $domain = substr($domain, 1);
                }
                if (!empty($domain)) {
                    $allowedDomains[] = strtolower($domain);
                }
            }
            $allowedDomains = array_unique($allowedDomains);
        }

        $config = array(
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'allowed_domains' => array_values($allowedDomains),
            'updated_at' => date('Y-m-d H:i:s')
        );

        if (file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
            $message = '設定を保存しました';
        } else {
            $error = '設定の保存に失敗しました';
        }
    }
}

// 設定削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_settings'])) {
    if (file_exists($configFile)) {
        unlink($configFile);
        $message = '設定を削除しました';
    }
}

// 現在の設定を読み込み
$currentConfig = array();
if (file_exists($configFile)) {
    $currentConfig = json_decode(file_get_contents($configFile), true) ?: array();
}

$googleOAuth = new GoogleOAuthClient();
$isConfigured = $googleOAuth->isConfigured();

// リダイレクトURI（設定用に表示）
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . '/api/google-callback.php';

require_once '../functions/header.php';
?>

<style>
.oauth-settings-container {
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

.info-box h3 {
    margin: 0 0 1rem 0;
}

.info-box ol {
    margin: 0.5rem 0 0 1.5rem;
    padding: 0;
}

.info-box li {
    margin-bottom: 0.5rem;
}

.redirect-uri-box {
    background: #f3f4f6;
    padding: 1rem;
    border-radius: 6px;
    font-family: monospace;
    font-size: 0.875rem;
    margin: 1rem 0;
    word-break: break-all;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.redirect-uri-box button {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    cursor: pointer;
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

.test-section {
    background: #f0fdf4;
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 2rem;
}
</style>

<div class="oauth-settings-container">
    <h2>Google OAuth認証 設定</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="status-badge <?= $isConfigured ? 'status-connected' : 'status-disconnected' ?>">
                <?= $isConfigured ? '✓ 設定済み' : '✗ 未設定' ?>
            </div>

            <?php if ($isConfigured && !empty($currentConfig['updated_at'])): ?>
                <p style="color: var(--gray-600); font-size: 0.875rem;">
                    最終更新: <?= htmlspecialchars($currentConfig['updated_at']) ?>
                </p>
            <?php endif; ?>

            <div class="info-box">
                <h3>Google Cloud Console での設定手順</h3>
                <ol>
                    <li><a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> にアクセス</li>
                    <li>プロジェクトを選択（または新規作成）</li>
                    <li>「APIとサービス」→「認証情報」を開く</li>
                    <li>「認証情報を作成」→「OAuth クライアント ID」を選択</li>
                    <li>アプリケーションの種類: 「ウェブ アプリケーション」</li>
                    <li>承認済みのリダイレクト URI に以下を追加:</li>
                </ol>

                <div class="redirect-uri-box">
                    <span id="redirectUri"><?= htmlspecialchars($redirectUri) ?></span>
                    <button type="button" onclick="copyRedirectUri()">コピー</button>
                </div>

                <p style="margin: 1rem 0 0 0; padding: 0.75rem; background: #fef3c7; border-left: 3px solid #f59e0b; font-size: 0.875rem;">
                    <strong>重要:</strong> 本番環境ではHTTPSが必要です。ローカル開発ではHTTPでも動作します。
                </p>
            </div>

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
                        placeholder="xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="client_secret">Client Secret *</label>
                    <input
                        type="password"
                        class="form-input"
                        id="client_secret"
                        name="client_secret"
                        value="<?= htmlspecialchars($currentConfig['client_secret'] ?? '') ?>"
                        placeholder="GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxxxx"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="allowed_domains">許可するメールドメイン（任意）</label>
                    <textarea
                        class="form-input"
                        id="allowed_domains"
                        name="allowed_domains"
                        rows="3"
                        placeholder="例: ad-yamato.co.jp&#10;example.com"
                        style="font-family: monospace;"
                    ><?= htmlspecialchars(implode("\n", $currentConfig['allowed_domains'] ?? [])) ?></textarea>
                    <p style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--gray-600);">
                        ログインを許可するメールアドレスのドメインを指定します（1行に1ドメイン、またはカンマ区切り）。<br>
                        空欄の場合は全てのドメインを許可します。
                    </p>
                </div>

                <?php if (!empty($currentConfig['allowed_domains'])): ?>
                <div style="background: #f0fdf4; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                    <strong style="color: #166534;">現在の許可ドメイン:</strong>
                    <ul style="margin: 0.5rem 0 0 1.5rem; color: #166534;">
                        <?php foreach ($currentConfig['allowed_domains'] as $domain): ?>
                            <li>@<?= htmlspecialchars($domain) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <button type="submit" name="save_settings" class="btn btn-primary">
                    設定を保存
                </button>
            </form>

            <?php if ($isConfigured): ?>
            <div class="test-section">
                <h3 style="margin-top: 0;">テスト</h3>
                <p style="margin-bottom: 1rem; color: #166534;">設定が正しいか確認するには、ログアウトしてGoogleログインを試してください。</p>
                <a href="logout.php" class="btn btn-secondary">ログアウトしてテスト</a>
            </div>

            <div class="danger-zone">
                <h3>設定を削除</h3>
                <p style="margin: 0 0 1rem 0; color: #991b1b;">
                    Google OAuth設定を削除します。削除後はパスワードログインのみになります。
                </p>
                <form method="POST" action="" onsubmit="return confirm('本当に設定を削除しますか？')">
                    <?= csrfTokenField() ?>
                    <button type="submit" name="delete_settings" class="btn btn-danger">
                        設定を削除
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyRedirectUri() {
    const uri = document.getElementById('redirectUri').textContent;
    navigator.clipboard.writeText(uri).then(function() {
        alert('コピーしました: ' + uri);
    }).catch(function() {
        // フォールバック
        const textArea = document.createElement('textarea');
        textArea.value = uri;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('コピーしました: ' + uri);
    });
}
</script>

<?php require_once '../functions/footer.php'; ?>
