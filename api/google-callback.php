<?php
require_once '../config/config.php';
require_once './google-oauth.php';

// 認証コードを取得
$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;
$state = $_GET['state'] ?? null;

// Drive連携の場合
if ($state === 'drive_connect') {
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
        $_SESSION['drive_error'] = 'Google Drive連携に失敗しました: ' . $e->getMessage();
        header('Location: /pages/loans.php');
        exit;
    }
}

// ログイン試行レート制限
$rateLimitFile = __DIR__ . '/../data/login-attempts.json';
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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
        $allowedDomains = $googleOAuth->getAllowedDomains();
        $domainList = implode(', ', $allowedDomains);
        $_SESSION['login_error'] = 'このメールアドレスのドメインは許可されていません。許可されているドメイン: ' . $domainList;
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

    // 従業員が見つからない場合、許可ドメインなら自動登録
    if (!$employee) {
        // ドメイン制限が設定されている場合のみ自動登録
        $allowedDomains = $googleOAuth->getAllowedDomains();
        if (!empty($allowedDomains)) {
            // 新規従業員として自動登録
            $newEmployeeId = 'emp_' . uniqid();
            $employee = [
                'id' => $newEmployeeId,
                'name' => $name ?: $email,
                'email' => $email,
                'role' => 'sales',  // デフォルトは営業部
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => 'google_oauth_auto'
            ];

            // 従業員マスタに追加
            if (!isset($data['employees'])) {
                $data['employees'] = [];
            }
            $data['employees'][] = $employee;
            saveData($data);
        } else {
            // ドメイン制限なしの場合は従来通りエラー
            $_SESSION['login_error'] = 'このGoogleアカウント（' . htmlspecialchars($email) . '）は登録されていません。管理者に連絡してください。';
            header('Location: /pages/login.php');
            exit;
        }
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

    header('Location: /pages/index.php');
    exit;

} catch (Exception $e) {
    $_SESSION['login_error'] = 'Google認証に失敗しました: ' . $e->getMessage();
    header('Location: /pages/login.php');
    exit;
}
