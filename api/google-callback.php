<?php
require_once '../config/config.php';
require_once './google-oauth.php';

// すでにログイン済みの場合はトップページにリダイレクト
if (isset($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

// 認証コードを取得
$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;

// エラーがあった場合
if ($error) {
    $_SESSION['login_error'] = 'Google認証がキャンセルされました。';
    header('Location: login.php');
    exit;
}

// 認証コードがない場合
if (!$code) {
    $_SESSION['login_error'] = '認証コードが取得できませんでした。';
    header('Location: login.php');
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
        $_SESSION['login_error'] = 'このGoogleアカウント（' . htmlspecialchars($email) . '）は登録されていません。管理者に連絡してください。';
        header('Location: login.php');
        exit;
    }

    // ログイン成功
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $employee['name'];
    $_SESSION['user_role'] = $employee['role'] ?? 'user';

    // 従業員IDを設定
    if (isset($employee['id'])) {
        $_SESSION['user_id'] = $employee['id'];
    }

    // ログイン方法を記録
    $_SESSION['login_method'] = 'google';

    header('Location: index.php');
    exit;

} catch (Exception $e) {
    $_SESSION['login_error'] = 'Google認証に失敗しました: ' . $e->getMessage();
    header('Location: login.php');
    exit;
}
