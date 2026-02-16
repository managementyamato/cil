<?php
require_once '../api/auth.php';
require_once '../api/mf-api.php';

// 管理者のみアクセス可能
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = false;

// OAuth認証コールバック処理
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $state = $_GET['state'] ?? '';

    // セッションから保存したstateと比較（CSRF対策）
    if (isset($_SESSION['oauth_state']) && $_SESSION['oauth_state'] !== $state) {
        $error = 'セキュリティエラー: stateが一致しません';
    } else {
        try {
            // リダイレクトURIを動的に生成
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $redirectUri = $protocol . '://' . $host . '/pages/mf-callback.php';

            // 認証コードからアクセストークンを取得
            $client = new MFApiClient();
            $tokenData = $client->handleCallback($code, $redirectUri);

            if (isset($tokenData['access_token'])) {
                $success = true;
                // デバッグ: 取得したスコープを確認
                $grantedScope = $tokenData['scope'] ?? '不明';
                $_SESSION['granted_scope'] = $grantedScope;
                // セッションのstateをクリア
                unset($_SESSION['oauth_state']);
            } else {
                $error = 'アクセストークンの取得に失敗しました';
            }
        } catch (Exception $e) {
            $error = 'OAuth認証エラー: ' . $e->getMessage();
        }
    }
} elseif (isset($_GET['error'])) {
    $error = 'OAuth認証エラー: ' . htmlspecialchars($_GET['error']);
    if (isset($_GET['error_description'])) {
        $error .= ' - ' . htmlspecialchars($_GET['error_description']);
    }
}

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
.callback-container {
    max-width: 600px;
    margin: 3rem auto;
    text-align: center;
}

.callback-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
}

.callback-success {
    color: var(--success);
}

.callback-error {
    color: var(--danger);
}
</style>

<div class="callback-container">
    <div class="card">
        <?php if ($success): ?>
            <div class="callback-icon callback-success">✓</div>
            <h2        class="mb-2 text-success">認証成功</h2>
            <p  class="mb-3">MF クラウド請求書との連携が完了しました。</p>
            <a href="mf-settings.php" class="btn btn-primary">設定ページに戻る</a>
        <?php elseif ($error): ?>
            <div class="callback-icon callback-error">✗</div>
            <h2      class="mb-2 text-danger">認証失敗</h2>
            <div         class="alert alert-danger text-left whitespace-pre-line">
                <?= htmlspecialchars($error) ?>
            </div>
            <a href="mf-settings.php" class="btn btn-secondary">設定ページに戻る</a>
        <?php else: ?>
            <div class="callback-icon">⏳</div>
            <h2  class="mb-2">認証処理中...</h2>
            <p>しばらくお待ちください</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../functions/footer.php'; ?>
