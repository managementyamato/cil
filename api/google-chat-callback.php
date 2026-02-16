<?php
/**
 * Google Chat OAuth コールバック処理
 */
require_once '../config/config.php';
require_once './google-chat.php';

$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;
$state = $_GET['state'] ?? null;

// CSRF対策: stateトークンを検証
if (!$state || !isset($_SESSION['oauth_chat_state']) || !hash_equals($_SESSION['oauth_chat_state'], $state)) {
    $_SESSION['chat_error'] = '不正なリクエストです。再度お試しください。';
    unset($_SESSION['oauth_chat_state']);
    header('Location: /pages/settings.php');
    exit;
}
unset($_SESSION['oauth_chat_state']);

// エラーがあった場合
if ($error) {
    $_SESSION['chat_error'] = 'Google Chat連携がキャンセルされました。';
    header('Location: /pages/settings.php');
    exit;
}

// 認証コードがない場合
if (!$code) {
    $_SESSION['chat_error'] = '認証コードが取得できませんでした。';
    header('Location: /pages/settings.php');
    exit;
}

try {
    $chatClient = new GoogleChatClient();
    $tokenData = $chatClient->exchangeCodeForToken($code);

    if (!isset($tokenData['access_token'])) {
        throw new Exception('アクセストークンが取得できませんでした。');
    }

    $_SESSION['chat_success'] = 'Google Chatと連携しました。';
    header('Location: /pages/settings.php');
    exit;

} catch (Exception $e) {
    // セキュリティ: 内部エラー詳細はログに記録し、ユーザーには汎用メッセージを表示
    if (function_exists('logError')) {
        logError('Google Chat連携エラー', ['error' => $e->getMessage()]);
    }
    $_SESSION['chat_error'] = 'Google Chat連携に失敗しました。しばらくしてから再度お試しください。';
    header('Location: /pages/settings.php');
    exit;
}
