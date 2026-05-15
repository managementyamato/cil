<?php
/**
 * テスト用ログインバックドア（ローカル開発・E2E テスト専用）
 *
 * 使用方法:
 *   GET /pages/test-login.php?email=managementsupport@yamato-agency.com
 *
 * - 本番環境（APP_ENV=production）では 403 で拒否
 * - APP_ENV=local の時のみ動作
 * - auto-deploy.ps1 の `Remove-Item "pages\test-*.php"` で自動的にデプロイ対象から外れる
 * - users.json または employees の email にマッチするユーザーのみセッション設定
 */

require_once __DIR__ . '/../config/config.php';

// 本番ではアクセス禁止
$appEnv = function_exists('env') ? env('APP_ENV', 'production') : 'production';
if ($appEnv === 'production') {
    http_response_code(403);
    exit('Forbidden in production');
}

// localhost からのみ許可（CIなど localhost 経由でない場合に備えて緩めの判定）
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remoteAddr, ['127.0.0.1', '::1'], true)
        || strpos($remoteAddr, '192.168.') === 0
        || strpos($remoteAddr, '10.') === 0;
if (!$isLocal) {
    http_response_code(403);
    exit('Forbidden: only local network allowed');
}

$email = $_GET['email'] ?? 'managementsupport@yamato-agency.com';

// users.json の管理ユーザーで認証
$usersFile = __DIR__ . '/../config/users.json';
$users = file_exists($usersFile) ? (json_decode(file_get_contents($usersFile), true) ?: []) : [];

$resolvedRole  = null;
$resolvedName  = null;
$resolvedEmpId = null;

if (isset($users[$email])) {
    $resolvedRole  = $users[$email]['role']        ?? 'admin';
    $resolvedName  = $users[$email]['name']        ?? 'テストユーザー';
    $resolvedEmpId = $users[$email]['employee_id'] ?? null;
} else {
    // 従業員マスタから検索
    $data = getData();
    foreach ($data['employees'] ?? [] as $emp) {
        $empEmail = $emp['email'] ?? '';
        if (is_string($empEmail) && str_starts_with($empEmail, 'enc:')) {
            require_once __DIR__ . '/../functions/encryption.php';
            try { $empEmail = decryptValue($empEmail); } catch (Throwable $e) { continue; }
        }
        if ($empEmail === $email) {
            $resolvedRole  = $emp['role'] ?? 'sales';
            $resolvedName  = $emp['name'] ?? 'テストユーザー';
            $resolvedEmpId = $emp['id']   ?? null;
            break;
        }
    }
}

if (!$resolvedRole) {
    http_response_code(404);
    exit("User not found: " . htmlspecialchars($email));
}

$_SESSION['user_email']    = $email;
$_SESSION['user_name']     = $resolvedName;
$_SESSION['user_role']     = $resolvedRole;
$_SESSION['employee_id']   = $resolvedEmpId;
$_SESSION['last_activity'] = time();

// CSRF トークンを発行
generateCsrfToken();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success'  => true,
    'email'    => $email,
    'name'     => $resolvedName,
    'role'     => $resolvedRole,
    'session_id' => session_id(),
], JSON_UNESCAPED_UNICODE);
