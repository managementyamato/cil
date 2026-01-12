<?php
/**
 * マネーフォワード クラウド会計 OAuth2認証
 */
require_once 'config.php';

// 管理者のみアクセス可能
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

// OAuth2エンドポイント（MoneyForward Business API）
define('MF_AUTH_URL', 'https://api.biz.moneyforward.com/authorize');
define('MF_TOKEN_URL', 'https://api.biz.moneyforward.com/token');

/**
 * OAuth2設定を読み込み
 */
function loadOAuthConfig() {
    $configFile = __DIR__ . '/mf-config.json';
    if (file_exists($configFile)) {
        $json = file_get_contents($configFile);
        return json_decode($json, true) ?: array();
    }
    return array();
}

/**
 * OAuth2設定を保存
 */
function saveOAuthConfig($config) {
    $configFile = __DIR__ . '/mf-config.json';
    return file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// コールバック処理
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $config = loadOAuthConfig();

    if (empty($config['client_id']) || empty($config['client_secret'])) {
        die('エラー: Client IDまたはClient Secretが設定されていません');
    }

    // 認証コードをアクセストークンに交換
    $baseDir = dirname($_SERVER['PHP_SELF']);
    $baseDir = ($baseDir === '/' || $baseDir === '\\') ? '' : $baseDir;

    // HTTPS検出（リバースプロキシ対応）
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

    $redirectUri = ($isHttps ? 'https' : 'http')
                   . '://' . $_SERVER['HTTP_HOST']
                   . $baseDir . '/mf-callback.php';

    $postData = array(
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret']
    );

    $ch = curl_init(MF_TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/x-www-form-urlencoded'
    ));
    // SSL証明書の検証を無効化（開発環境のみ）
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $tokenData = json_decode($response, true);

        // トークンを保存
        $config['access_token'] = $tokenData['access_token'];
        $config['refresh_token'] = $tokenData['refresh_token'] ?? null;
        $config['expires_in'] = $tokenData['expires_in'] ?? 3600;
        $config['token_obtained_at'] = time();
        $config['updated_at'] = date('Y-m-d H:i:s');

        saveOAuthConfig($config);

        // 成功メッセージと共にリダイレクト
        header('Location: mf-settings.php?auth=success');
        exit;
    } else {
        $errorData = json_decode($response, true);

        // デバッグ情報
        error_log("MF Token Error - HTTP Code: $httpCode");
        error_log("MF Token Error - Response: $response");

        $errorMsg = isset($errorData['error_description']) ? $errorData['error_description'] : 'トークン取得に失敗しました';
        $errorMsg .= " (HTTPコード: $httpCode)";

        // デバッグ用: エラーの詳細を表示
        if ($errorData) {
            $errorMsg .= " - " . json_encode($errorData, JSON_UNESCAPED_UNICODE);
        }

        header('Location: mf-settings.php?error=' . urlencode($errorMsg));
        exit;
    }
}

// OAuth2認証開始処理
if (isset($_GET['action']) && $_GET['action'] === 'start') {
    $config = loadOAuthConfig();

    if (empty($config['client_id']) || empty($config['client_secret'])) {
        header('Location: mf-settings.php?error=' . urlencode('Client IDとClient Secretを先に設定してください'));
        exit;
    }

    // リダイレクトURI
    $baseDir = dirname($_SERVER['PHP_SELF']);
    $baseDir = ($baseDir === '/' || $baseDir === '\\') ? '' : $baseDir;

    // HTTPS検出（リバースプロキシ対応）
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

    $redirectUri = ($isHttps ? 'https' : 'http')
                   . '://' . $_SERVER['HTTP_HOST']
                   . $baseDir . '/mf-callback.php';

    // 認証URLにリダイレクト
    $authParams = array(
        'client_id' => $config['client_id'],
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'mfc/invoice/data.write'
    );

    $authUrl = MF_AUTH_URL . '?' . http_build_query($authParams);
    header('Location: ' . $authUrl);
    exit;
}

// エラー処理
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
    $errorDescription = isset($_GET['error_description']) ? htmlspecialchars($_GET['error_description']) : '';
    header('Location: mf-settings.php?error=' . urlencode("OAuth2エラー: {$error} - {$errorDescription}"));
    exit;
}

// デフォルト: 設定ページにリダイレクト
header('Location: mf-settings.php');
exit;
