<?php
require_once '../api/auth.php';
require_once '../api/google-oauth.php';
require_once '../api/google-calendar.php';

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
        writeAuditLog('delete', 'google_oauth_settings', 'Google OAuth設定を削除');
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

// Googleカレンダー設定
$googleCalendar = new GoogleCalendarClient();
$calendarConfigured = $googleCalendar->isConfigured();

// リダイレクトURI（設定用に表示）
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . '://' . $host . '/api/google-callback.php';

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
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="w-24 h-24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Google OAuth認証 設定
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
            <h3>Googleログイン設定</h3>
            <p>Googleアカウントでのログインを有効にします。Google Cloud Consoleで OAuth 2.0 クライアントを作成してください。</p>
        </div>
        <?php if ($isConfigured): ?>
            <span class="status-badge success">✓ 設定済み</span>
        <?php else: ?>
            <span class="status-badge warning">未設定</span>
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
                
                id="allowed_domains"
                name="allowed_domains"
                rows="3"
                placeholder="例: ad-yamato.co.jp&#10;example.com"
                       class="form-input" class="font-mono"
            ><?= htmlspecialchars(implode("\n", $currentConfig['allowed_domains'] ?? [])) ?></textarea>
            <p    class="mt-1 text-2xs text-gray-600">
                ログインを許可するメールアドレスのドメインを指定します（1行に1ドメイン、またはカンマ区切り）。<br>
                空欄の場合は全てのドメインを許可します。
            </p>
        </div>

        <?php if (!empty($currentConfig['allowed_domains'])): ?>
        <div        class="p-2 mb-2 bg-f0fdf4 rounded">
            <strong     class="text-14 text-166">現在の許可ドメイン:</strong>
            <ul       class="text-14 text-166" class="m-05-15">
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
    <div class="danger-zone">
        <h4>設定を削除</h4>
        <p       class="text-14" class="m-0-1-991">
            Google OAuth設定を削除します。削除後はパスワードログインのみになります。
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
const csrfToken = '<?= generateCsrfToken() ?>';

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

<script<?= nonceAttr() ?>>
// 設定削除フォームの確認
document.getElementById('deleteSettingsForm')?.addEventListener('submit', function(e) {
    if (!confirm('本当に設定を削除しますか？')) {
        e.preventDefault();
    }
});
</script>

<?php require_once '../functions/footer.php'; ?>
