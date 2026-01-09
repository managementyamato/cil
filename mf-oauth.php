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

// OAuth2エンドポイント
define('MF_AUTH_URL', 'https://invoice.moneyforward.com/oauth/authorize');
define('MF_TOKEN_URL', 'https://invoice.moneyforward.com/oauth/token');

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
    $redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                   . '://' . $_SERVER['HTTP_HOST']
                   . dirname($_SERVER['PHP_SELF']) . '/mf-oauth.php';

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
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

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
        $errorMsg = isset($errorData['error_description']) ? $errorData['error_description'] : 'トークン取得に失敗しました';
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
    $redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                   . '://' . $_SERVER['HTTP_HOST']
                   . dirname($_SERVER['PHP_SELF']) . '/mf-oauth.php';

    // 認証URLにリダイレクト
    $authParams = array(
        'client_id' => $config['client_id'],
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'read write'
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
