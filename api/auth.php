<?php
// 認証チェック
// このファイルを各ページの先頭で読み込むことで、ログインチェックを行います

require_once '../config/config.php';

// ログインページとセットアップページは認証不要
$currentPage = basename($_SERVER['PHP_SELF']);
if ($currentPage === 'login.php' || $currentPage === 'setup.php') {
    return;
}

// ログインチェック
if (!isset($_SESSION['user_email'])) {
    // 未ログインの場合はログインページにリダイレクト
    header('Location: login.php');
    exit;
}

// セッションタイムアウト（8時間）
$sessionTimeout = 8 * 60 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $sessionTimeout) {
    session_destroy();
    session_start();
    $_SESSION['login_error'] = 'セッションがタイムアウトしました。再度ログインしてください。';
    header('Location: login.php');
    exit;
}
$_SESSION['last_activity'] = time();

// ユーザーリストまたは従業員マスタに存在するかチェック
$userExists = false;
$userRetired = false;
$todayDate = date('Y-m-d');

// まず$USERSをチェック（パスワードログインユーザー）
if (isset($GLOBALS['USERS'][$_SESSION['user_email']])) {
    $userExists = true;
} else {
    // 従業員マスタのemailをチェック（Googleログインユーザー）
    $data = getData();
    foreach ($data['employees'] as $emp) {
        if (isset($emp['email']) && $emp['email'] === $_SESSION['user_email']) {
            $userExists = true;
            // 退職日が設定されていて、今日以前なら退職扱い
            if (!empty($emp['leave_date']) && $emp['leave_date'] <= $todayDate) {
                $userRetired = true;
            }
            break;
        }
    }
}

if (!$userExists) {
    // ユーザーが削除された場合
    $deletedEmail = $_SESSION['user_email'];
    session_destroy();
    session_start();
    $_SESSION['login_error'] = 'アカウント（' . htmlspecialchars($deletedEmail) . '）が削除されたか、存在しません。管理者に連絡してください。';
    header('Location: login.php');
    exit;
}

if ($userRetired) {
    // 退職者の場合
    $retiredEmail = $_SESSION['user_email'];
    // 失敗通知を送信
    require_once __DIR__ . '/../functions/login-security.php';
    if (function_exists('recordFailedLoginAndNotify')) {
        recordFailedLoginAndNotify($retiredEmail, 'retired');
    }
    session_destroy();
    session_start();
    $_SESSION['login_error'] = 'アカウント（' . htmlspecialchars($retiredEmail) . '）は退職済みのためログインできません。';
    header('Location: login.php');
    exit;
}

// ページごとの必要権限を定義（デフォルト値）
// 権限レベル: sales(営業部) < product(製品管理部) < admin(管理部)
// フォーマット: ['view' => '閲覧権限', 'edit' => '編集権限']
$defaultPagePermissions = array(
    'index.php' => ['view' => 'sales', 'edit' => 'sales'],       // ダッシュボード
    'list.php' => ['view' => 'sales', 'edit' => 'product'],      // 一覧
    'report.php' => ['view' => 'product', 'edit' => 'product'],  // 報告
    'master.php' => ['view' => 'product', 'edit' => 'product'],  // PJ管理
    'finance.php' => ['view' => 'product', 'edit' => 'product'], // 損益
    'profit-loss.php' => ['view' => 'product', 'edit' => 'product'], // 損益計算書
    'customers.php' => ['view' => 'product', 'edit' => 'product'], // 顧客マスタ
    'partners.php' => ['view' => 'product', 'edit' => 'product'], // パートナーマスタ
    'employees.php' => ['view' => 'product', 'edit' => 'product'], // 従業員マスタ
    'products.php' => ['view' => 'product', 'edit' => 'product'], // 商品マスタ
    'troubles.php' => ['view' => 'sales', 'edit' => 'product'],  // トラブル対応
    'trouble-form.php' => ['view' => 'product', 'edit' => 'product'], // トラブル登録・編集
    'trouble-bulk-form.php' => ['view' => 'product', 'edit' => 'product'], // トラブル一括登録
    'photo-attendance.php' => ['view' => 'product', 'edit' => 'product'], // アルコールチェック管理
    'photo-upload.php' => ['view' => 'sales', 'edit' => 'sales'], // 写真アップロード
    'mf-monthly.php' => ['view' => 'product', 'edit' => 'product'], // MF月次
    'loans.php' => ['view' => 'product', 'edit' => 'product'],   // 借入金管理
    'payroll-journal.php' => ['view' => 'product', 'edit' => 'product'], // 給与仕訳
    'bulk-pdf-match.php' => ['view' => 'product', 'edit' => 'product'], // PDF一括照合
    'users.php' => ['view' => 'admin', 'edit' => 'admin'],       // ユーザー管理
    'mf-settings.php' => ['view' => 'admin', 'edit' => 'admin'], // MF連携設定
    'mf-sync-settings.php' => ['view' => 'admin', 'edit' => 'admin'], // MF同期設定
    'mf-debug.php' => ['view' => 'admin', 'edit' => 'admin'],    // MFデバッグ
    'notification-settings.php' => ['view' => 'admin', 'edit' => 'admin'], // 通知設定
    'settings.php' => ['view' => 'admin', 'edit' => 'admin'],    // 設定
    'integration-settings.php' => ['view' => 'admin', 'edit' => 'admin'], // API連携設定
    'user-permissions.php' => ['view' => 'admin', 'edit' => 'admin'], // アカウント権限設定
    'google-oauth-settings.php' => ['view' => 'admin', 'edit' => 'admin'], // Google OAuth設定
    // セキュリティ・管理
    'sessions.php' => ['view' => 'sales', 'edit' => 'sales'],                // セッション管理（自分のセッション）
    'change-history.php' => ['view' => 'product', 'edit' => 'admin'],        // 変更履歴
    // マスタ管理
    'masters.php' => ['view' => 'product', 'edit' => 'product'],             // マスタ管理
    // タスク管理
    'tasks.php' => ['view' => 'sales', 'edit' => 'sales'],                   // タスク管理（全員アクセス可）
);

// 設定ファイルから権限をロード（カスタム設定で上書き）
$pagePermissionsFile = __DIR__ . '/../config/page-permissions.json';
$pagePermissions = $defaultPagePermissions;
if (file_exists($pagePermissionsFile)) {
    $savedPermissions = json_decode(file_get_contents($pagePermissionsFile), true);
    if ($savedPermissions && isset($savedPermissions['permissions'])) {
        foreach ($savedPermissions['permissions'] as $page => $perm) {
            // 新フォーマット（view/edit）の場合はそのまま使用
            if (is_array($perm) && isset($perm['view'])) {
                $pagePermissions[$page] = $perm;
            } else {
                // 旧フォーマット（文字列）の場合は変換
                $pagePermissions[$page] = ['view' => $perm, 'edit' => $perm];
            }
        }
    }
}

// ページの閲覧権限を取得するヘルパー関数
function getPageViewPermission($page) {
    global $pagePermissions;
    if (!isset($pagePermissions[$page])) {
        return 'sales'; // デフォルト
    }
    $perm = $pagePermissions[$page];
    // 配列形式（新フォーマット）
    if (is_array($perm)) {
        return $perm['view'] ?? 'sales';
    }
    // 文字列形式（旧フォーマット）
    return $perm;
}

// ページの編集権限を取得するヘルパー関数
function getPageEditPermission($page) {
    global $pagePermissions;
    if (!isset($pagePermissions[$page])) {
        return 'product'; // デフォルト
    }
    $perm = $pagePermissions[$page];
    // 配列形式（新フォーマット）
    if (is_array($perm)) {
        return $perm['edit'] ?? 'product';
    }
    // 文字列形式（旧フォーマット）
    return $perm;
}

// 現在のページの編集権限があるかチェック
function canEditCurrentPage() {
    $currentPage = basename($_SERVER['PHP_SELF']);
    $editPermission = getPageEditPermission($currentPage);
    return hasPermission($editPermission);
}

// 指定ページの編集権限があるかチェック
function canEditPage($page) {
    $editPermission = getPageEditPermission($page);
    return hasPermission($editPermission);
}

// 現在のページに必要な権限をチェック（閲覧権限）
if (isset($pagePermissions[$currentPage])) {
    $viewPermission = getPageViewPermission($currentPage);
    if (!hasPermission($viewPermission)) {
        // 権限不足の場合
        // index.phpへのアクセスで権限不足ならログインページへ（ループ防止）
        if ($currentPage === 'index.php') {
            $_SESSION['login_error'] = '権限が不足しています。管理者に連絡してください。（現在の権限: ' . ($_SESSION['user_role'] ?? '未設定') . '）';
            session_destroy();
            header('Location: login.php');
            exit;
        }
        // それ以外のページはトップページにリダイレクト
        header('Location: index.php');
        exit;
    }
}
