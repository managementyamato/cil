<?php
/**
 * 開発環境用 MF OAuthコールバック
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

$message = '';
$messageType = 'danger';

try {
    // 認可コードを取得
    $code = $_GET['code'] ?? '';
    if (empty($code)) {
        throw new Exception('認可コードが取得できませんでした');
    }

    // 設定を読み込み
    $config = loadDevConfig();
    $clientId = $config['client_id'] ?? '';
    $clientSecret = $config['client_secret'] ?? '';

    if (empty($clientId) || empty($clientSecret)) {
        throw new Exception('Client IDまたはClient Secretが設定されていません');
    }

    // MFApiClientを使ってトークンを取得
    // 開発用のコンストラクタで設定ファイルパスを指定
    $client = new MFApiClient(DEV_MF_CONFIG_FILE);
    $client->exchangeCodeForToken($code, 'http://localhost/mf-dev/mf-callback.php');

    $message = 'OAuth認証が成功しました！';
    $messageType = 'success';

} catch (Exception $e) {
    $message = 'エラー: ' . $e->getMessage();
    $messageType = 'danger';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MF OAuth コールバック - 開発環境</title>
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
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            background: #3182ce;
            color: white;
        }
    </style>
</head>
<body>
    <h1>OAuth コールバック - 開発環境</h1>

    <div class="alert alert-<?= $messageType ?>">
        <?= htmlspecialchars($message) ?>
    </div>

    <div class="card">
        <p><a href="mf-settings.php" class="btn">設定ページに戻る</a></p>
    </div>
</body>
</html>
