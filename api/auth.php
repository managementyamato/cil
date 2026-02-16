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

// セッションタイムアウト
// 環境変数 SESSION_TIMEOUT_HOURS で設定可能（デフォルト: 8時間）
// 本番環境では2時間程度を推奨
$sessionTimeoutHours = function_exists('env') ? env('SESSION_TIMEOUT_HOURS', 8) : 8;
$sessionTimeout = (int)$sessionTimeoutHours * 60 * 60;
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
    'master.php' => ['view' => 'product', 'edit' => 'product'],  // PJ管理
    'finance.php' => ['view' => 'product', 'edit' => 'product'], // 損益
    'customers.php' => ['view' => 'product', 'edit' => 'product'], // 顧客マスタ
    'employees.php' => ['view' => 'product', 'edit' => 'product'], // 従業員マスタ
    'troubles.php' => ['view' => 'sales', 'edit' => 'product'],  // トラブル対応
    'trouble-form.php' => ['view' => 'product', 'edit' => 'product'], // トラブル登録・編集
    'trouble-bulk-form.php' => ['view' => 'product', 'edit' => 'product'], // トラブル一括登録
    'photo-attendance.php' => ['view' => 'product', 'edit' => 'product'], // アルコールチェック管理
    'mf-monthly.php' => ['view' => 'product', 'edit' => 'product'], // MF月次
    'mf-mapping.php' => ['view' => 'product', 'edit' => 'product'], // MF請求書マッピング
    'loans.php' => ['view' => 'product', 'edit' => 'product'],   // 借入金管理
    'payroll-journal.php' => ['view' => 'product', 'edit' => 'product'], // 給与仕訳
    'bulk-pdf-match.php' => ['view' => 'product', 'edit' => 'product'], // PDF一括照合
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
    // マスタ管理
    'masters.php' => ['view' => 'product', 'edit' => 'product'],             // マスタ管理
    // ダウンロードページ
    'download-alcohol-check-csv.php' => ['view' => 'product', 'edit' => 'product'], // アルコールチェックCSV
    'download-invoices-csv.php' => ['view' => 'product', 'edit' => 'product'],      // 請求書CSV
    'download-troubles-csv.php' => ['view' => 'product', 'edit' => 'product'],      // トラブルCSV
    // 管理者専用
    'audit-log.php' => ['view' => 'admin', 'edit' => 'admin'],               // 監査ログ
    'recurring-invoices.php' => ['view' => 'admin', 'edit' => 'admin'],      // 定期請求書作成
    'print-invoice.php' => ['view' => 'product', 'edit' => 'product'],       // 請求書印刷
    'mf-invoice-list.php' => ['view' => 'admin', 'edit' => 'admin'],         // MF請求書一覧（デバッグ）
    'mf-callback.php' => ['view' => 'admin', 'edit' => 'admin'],             // MFコールバック
    // ユーティリティ
    'color-samples.php' => ['view' => 'sales', 'edit' => 'sales'],           // カラーサンプル
    // 横断検索
    'search.php' => ['view' => 'sales', 'edit' => 'sales'],                  // 横断検索（全員アクセス可）
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

/**
 * 担当者名からユニークな色を取得
 * 同じ名前には常に同じ色が割り当てられる
 */
function getAssigneeColor($name) {
    if (empty($name) || $name === '-') {
        return ['bg' => '#f0f0f0', 'text' => '#666'];
    }

    // 担当者カラーパレット（見やすいトーン）
    $colors = [
        ['bg' => '#e8eaf6', 'text' => '#3949ab'],  // インディゴ
        ['bg' => '#e0f2f1', 'text' => '#00897b'],  // ティール
        ['bg' => '#ede7f6', 'text' => '#5e35b1'],  // 紫
        ['bg' => '#fce4ec', 'text' => '#c62828'],  // 赤
        ['bg' => '#e8f5e9', 'text' => '#2e7d32'],  // 緑
        ['bg' => '#fff3e0', 'text' => '#e65100'],  // オレンジ
        ['bg' => '#e3f2fd', 'text' => '#1565c0'],  // 青
        ['bg' => '#fce4ec', 'text' => '#ad1457'],  // ピンク
        ['bg' => '#eceff1', 'text' => '#546e7a'],  // ブルーグレー
        ['bg' => '#efebe9', 'text' => '#5d4037'],  // ブラウン
    ];

    // 名前のハッシュ値から色を決定（同じ名前は常に同じ色）
    $hash = crc32($name);
    $index = abs($hash) % count($colors);

    return $colors[$index];
}
