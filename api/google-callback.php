<?php
require_once '../config/config.php';
require_once './google-oauth.php';
require_once __DIR__ . '/../functions/login-security.php';

// 認証コードを取得
$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;
$state = $_GET['state'] ?? null;

// stateパラメータからトークンと目的を分離
$statePurpose = null;
$stateToken = null;
if ($state) {
    $stateParts = explode('_', $state, 3);
    if (count($stateParts) >= 3) {
        // "drive_connect_<token>" の形式
        $statePurpose = $stateParts[0] . '_' . $stateParts[1];
        $stateToken = $stateParts[2];
    } elseif (count($stateParts) === 2) {
        // "login_<token>" の形式
        $statePurpose = $stateParts[0];
        $stateToken = $stateParts[1];
    }
}

// CSRF対策: stateトークンを検証
if ($stateToken && isset($_SESSION['oauth_state'])) {
    if (!hash_equals($_SESSION['oauth_state'], $stateToken)) {
        // stateトークンが一致しない — CSRF攻撃の可能性
        $_SESSION['login_error'] = '不正なリクエストです。再度ログインしてください。';
        unset($_SESSION['oauth_state'], $_SESSION['oauth_state_purpose']);
        header('Location: /pages/login.php');
        exit;
    }
    // 使用済みのstateトークンを削除（リプレイ攻撃防止）
    unset($_SESSION['oauth_state'], $_SESSION['oauth_state_purpose']);
}

// Drive連携の場合
if ($statePurpose === 'drive_connect') {
    require_once './google-drive.php';

    if ($error) {
        $_SESSION['drive_error'] = 'Google Drive連携がキャンセルされました。';
        header('Location: /pages/loans.php');
        exit;
    }

    if (!$code) {
        $_SESSION['drive_error'] = '認証コードが取得できませんでした。';
        header('Location: /pages/loans.php');
        exit;
    }

    try {
        $googleOAuth = new GoogleOAuthClient();
        $tokenData = $googleOAuth->getAccessToken($code);

        if (!isset($tokenData['access_token'])) {
            throw new Exception('アクセストークンが取得できませんでした。');
        }

        // Driveトークンを保存
        $driveClient = new GoogleDriveClient();
        $driveClient->saveToken($tokenData);

        $_SESSION['drive_success'] = 'Google Driveとの連携が完了しました。';
        header('Location: /pages/loans.php');
        exit;

    } catch (Exception $e) {
        // セキュリティ: 内部エラー詳細はログに記録し、ユーザーには汎用メッセージを表示
        if (function_exists('logError')) {
            logError('Google Drive連携エラー', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
        $_SESSION['drive_error'] = 'Google Drive連携に失敗しました。しばらくしてから再度お試しください。';
        header('Location: /pages/loans.php');
        exit;
    }
}

// ログイン試行レート制限
require_once __DIR__ . '/../functions/security.php';
$rateLimitFile = __DIR__ . '/../data/login-attempts.json';
$clientIp = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$maxAttempts = 10;  // 15分間に最大10回
$windowMinutes = 15;

if (file_exists($rateLimitFile)) {
    $attempts = json_decode(file_get_contents($rateLimitFile), true) ?: [];
} else {
    $attempts = [];
}

// 古い記録を削除
$cutoff = time() - ($windowMinutes * 60);
$attempts = array_filter($attempts, fn($a) => $a['time'] > $cutoff);

// IPの試行回数をカウント
$ipAttempts = array_filter($attempts, fn($a) => $a['ip'] === $clientIp);
if (count($ipAttempts) >= $maxAttempts) {
    $_SESSION['login_error'] = 'ログイン試行回数が多すぎます。しばらくしてから再度お試しください。';
    header('Location: /pages/login.php');
    exit;
}

// 試行を記録
$attempts[] = ['ip' => $clientIp, 'time' => time()];
$dir = dirname($rateLimitFile);
if (!is_dir($dir)) mkdir($dir, 0755, true);
file_put_contents($rateLimitFile, json_encode(array_values($attempts)), LOCK_EX);

// 通常のログイン処理
// すでにログイン済みの場合はトップページにリダイレクト
if (isset($_SESSION['user_email'])) {
    header('Location: /pages/index.php');
    exit;
}

// エラーがあった場合
if ($error) {
    $_SESSION['login_error'] = 'Google認証がキャンセルされました。';
    header('Location: /pages/login.php');
    exit;
}

// 認証コードがない場合
if (!$code) {
    $_SESSION['login_error'] = '認証コードが取得できませんでした。';
    header('Location: /pages/login.php');
    exit;
}

try {
    $googleOAuth = new GoogleOAuthClient();

    // アクセストークンを取得
    $tokenData = $googleOAuth->getAccessToken($code);

    if (!isset($tokenData['access_token'])) {
        throw new Exception('アクセストークンが取得できませんでした。');
    }

    // ユーザー情報を取得
    $userInfo = $googleOAuth->getUserInfo($tokenData['access_token']);

    if (!isset($userInfo['email'])) {
        throw new Exception('ユーザー情報が取得できませんでした。');
    }

    $email = $userInfo['email'];
    $name = $userInfo['name'] ?? '';

    // ドメイン制限チェック
    if (!$googleOAuth->isEmailDomainAllowed($email)) {
        // 失敗通知を送信
        if (function_exists('recordFailedLoginAndNotify')) {
            recordFailedLoginAndNotify($email, 'domain_blocked');
        }
        $_SESSION['login_error'] = 'このアカウントではログインできません。管理者に連絡してください。';
        header('Location: /pages/login.php');
        exit;
    }

    // 従業員マスタからユーザーを検索
    $data = getData();
    $employee = null;

    foreach ($data['employees'] as $emp) {
        if (isset($emp['email']) && $emp['email'] === $email) {
            $employee = $emp;
            break;
        }
    }

    // 従業員が見つからない場合
    if (!$employee) {
        // セキュリティ強化: 自動登録は無効化
        // 新規ユーザーは管理者が従業員マスタに手動で追加する必要がある
        //
        // 注意: 以前は許可ドメイン設定時に自動登録していたが、
        // ドメイン所有だけでは組織メンバーであることを保証できないため無効化
        // Google Workspaceの組織チェックが必要な場合は別途実装が必要

        // 失敗通知を送信
        if (function_exists('recordFailedLoginAndNotify')) {
            recordFailedLoginAndNotify($email, 'not_found');
        }

        // 監査ログに記録（不正アクセス試行の追跡用）
        writeAuditLog('login_denied', 'auth', "未登録ユーザーのログイン試行: {$email}");

        $_SESSION['login_error'] = 'このアカウントは登録されていません。管理者に連絡してください。';
        header('Location: /pages/login.php');
        exit;
    }

    // ログイン成功 - セッションIDを再生成（セッション固定攻撃防止）
    session_regenerate_id(true);
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $employee['name'];
    $_SESSION['user_role'] = $employee['role'] ?? 'sales';  // デフォルトは営業部
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();

    // 従業員IDを設定
    if (isset($employee['id'])) {
        $_SESSION['user_id'] = $employee['id'];
    }

    // ログイン方法を記録
    $_SESSION['login_method'] = 'google';

    writeAuditLog('login', 'auth', "ログイン: {$employee['name']} ({$email})");

    // ログイン通知・セッション登録
    if (function_exists('recordLoginAndNotify')) {
        recordLoginAndNotify($email, $email, $employee['name']);
    }
    if (function_exists('registerSession')) {
        registerSession($email);
    }

    header('Location: /pages/index.php');
    exit;

} catch (Exception $e) {
    // セキュリティ: 内部エラー詳細はログに記録し、ユーザーには汎用メッセージを表示
    if (function_exists('logError')) {
        logError('Google認証エラー', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }
    $_SESSION['login_error'] = 'Google認証に失敗しました。しばらくしてから再度お試しください。';
    header('Location: /pages/login.php');
    exit;
}
