<?php
/**
 * Google Calendar OAuth コールバック
 */
require_once '../config/config.php';
require_once 'google-calendar.php';

// CSRF対策: stateトークンを検証
$state = $_GET['state'] ?? null;
if (!$state || !isset($_SESSION['oauth_calendar_state']) || !hash_equals($_SESSION['oauth_calendar_state'], $state)) {
    $_SESSION['calendar_error'] = '不正なリクエストです。再度お試しください。';
    unset($_SESSION['oauth_calendar_state']);
    header('Location: ../pages/settings.php');
    exit;
}
unset($_SESSION['oauth_calendar_state']);

// エラーチェック
if (isset($_GET['error'])) {
    $_SESSION['calendar_error'] = 'カレンダー連携がキャンセルされました。';
    header('Location: ../pages/settings.php');
    exit;
}

// 認証コードがない場合
if (!isset($_GET['code'])) {
    $_SESSION['calendar_error'] = '認証コードがありません';
    header('Location: ../pages/settings.php');
    exit;
}

try {
    $calendar = new GoogleCalendarClient();
    $calendar->exchangeCodeForToken($_GET['code']);

    $_SESSION['calendar_success'] = 'Googleカレンダーの連携が完了しました';
} catch (Exception $e) {
    // セキュリティ: 内部エラー詳細はログに記録し、ユーザーには汎用メッセージを表示
    if (function_exists('logError')) {
        logError('カレンダー連携エラー', ['error' => $e->getMessage()]);
    }
    $_SESSION['calendar_error'] = 'カレンダー連携に失敗しました。しばらくしてから再度お試しください。';
}

header('Location: ../pages/settings.php');
exit;
