<?php
/**
 * Gmail OAuth コールバック処理
 */
require_once '../config/config.php';
require_once './google-gmail.php';

$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;
$state = $_GET['state'] ?? null;

// CSRF対策: stateトークンを検証
if (!$state || !isset($_SESSION['oauth_gmail_state']) || !hash_equals($_SESSION['oauth_gmail_state'], $state)) {
    $_SESSION['gmail_error'] = '不正なリクエストです。再度お試しください。';
    unset($_SESSION['oauth_gmail_state']);
    header('Location: /pages/settings.php?tab=gmail');
    exit;
}
unset($_SESSION['oauth_gmail_state']);

// エラーがあった場合
if ($error) {
    $_SESSION['gmail_error'] = 'Gmail連携がキャンセルされました。';
    header('Location: /pages/settings.php?tab=gmail');
    exit;
}

// 認証コードがない場合
if (!$code) {
    $_SESSION['gmail_error'] = '認証コードが取得できませんでした。';
    header('Location: /pages/settings.php?tab=gmail');
    exit;
}

try {
    $gmailClient = new GoogleGmailClient();
    $tokenData = $gmailClient->exchangeCodeForToken($code);

    if (!isset($tokenData['access_token'])) {
        throw new Exception('アクセストークンが取得できませんでした。');
    }

    $_SESSION['gmail_success'] = 'Gmailと連携しました。';
    header('Location: /pages/settings.php?tab=gmail');
    exit;

} catch (Exception $e) {
    if (function_exists('logError')) {
        logError('Gmail連携エラー', ['error' => $e->getMessage()]);
    }
    $_SESSION['gmail_error'] = 'Gmail連携に失敗しました。しばらくしてから再度お試しください。';
    header('Location: /pages/settings.php?tab=gmail');
    exit;
}
