<?php
/**
 * 開発環境用 MF設定ページ
 */

// 開発用の設定ファイルパス
define('DEV_MF_CONFIG_FILE', __DIR__ . '/mf-config.json');

// mf-api.phpを読み込み（親ディレクトリから）
require_once __DIR__ . '/../mf-api.php';

// 設定の読み込み
function loadDevConfig() {
    if (file_exists(DEV_MF_CONFIG_FILE)) {
        $json = file_get_contents(DEV_MF_CONFIG_FILE);
        return json_decode($json, true) ?: array();
    }
    return array();
}

// 設定の保存
function saveDevConfig($config) {
    return file_put_contents(
        DEV_MF_CONFIG_FILE,
        json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

$config = loadDevConfig();
$message = '';
$messageType = '';

// OAuth認証開始
if (isset($_GET['action']) && $_GET['action'] === 'authorize') {
    $clientId = $config['client_id'] ?? '';
    if (empty($clientId)) {
        $message = 'Client IDが設定されていません';
        $messageType = 'danger';
    } else {
        $authUrl = MFApiClient::getAuthorizationUrl($clientId, 'http://localhost/mf-dev/mf-callback.php');
        header('Location: ' . $authUrl);
        exit;
    }
}

// 認証状態チェック
$isAuthenticated = !empty($config['access_token']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MF設定 - 開発環境</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
        }
        .btn-primary {
            background: #3182ce;
            color: white;
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
        }
        .alert-danger {
            background: #fed7d7;
            color: #742a2a;
        }
        .status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .status-ok {
            background: #c6f6d5;
            color: #22543d;
        }
        .status-error {
            background: #fed7d7;
            color: #742a2a;
        }
    </style>
</head>
<body>
    <h1>🔧 MF設定 - 開発環境</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>認証状態</h2>
        <?php if ($isAuthenticated): ?>
            <p>
                <span class="status status-ok">✓ 認証済み</span>
            </p>
            <p>Access Token: <code><?= substr($config['access_token'], 0, 20) ?>...</code></p>
            <p>更新日時: <?= htmlspecialchars($config['updated_at'] ?? '-') ?></p>
        <?php else: ?>
            <p>
                <span class="status status-error">✗ 未認証</span>
            </p>
            <p>MFクラウド請求書と連携するには、OAuth認証が必要です。</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>OAuth認証</h2>
        <p>開発環境用のOAuth認証を実行します。</p>
        <p><strong>リダイレクトURI:</strong> <code>http://localhost/mf-dev/mf-callback.php</code></p>
        <p style="color: #718096; font-size: 0.875rem;">
            ※MFクラウド請求書の設定で、このリダイレクトURIを登録してください
        </p>
        <a href="?action=authorize" class="btn btn-primary">OAuth認証を開始</a>
    </div>

    <div class="card">
        <h2>設定情報</h2>
        <p><strong>Client ID:</strong> <?= htmlspecialchars($config['client_id'] ?? '-') ?></p>
        <p><strong>設定ファイル:</strong> <code><?= DEV_MF_CONFIG_FILE ?></code></p>
    </div>
</body>
</html>
