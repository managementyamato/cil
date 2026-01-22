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

// ユーザーリストに存在するかチェック
if (!isset($GLOBALS['USERS'][$_SESSION['user_email']])) {
    // ユーザーが削除された場合
    session_destroy();
    header('Location: login.php');
    exit;
}

// ページごとの必要権限を定義
$pagePermissions = array(
    'index.php' => 'viewer',      // ダッシュボード: 閲覧者以上
    'list.php' => 'viewer',       // 一覧: 閲覧者以上（編集は別途チェック）
    'report.php' => 'editor',     // 報告: 編集者以上
    'master.php' => 'editor',     // PJ管理: 編集者以上
    'finance.php' => 'editor',    // 損益: 編集者以上
    'customers.php' => 'editor',  // 顧客マスタ: 編集者以上
    'partners.php' => 'editor',   // パートナーマスタ: 編集者以上
    'employees.php' => 'editor',  // 従業員マスタ: 編集者以上
    'products.php' => 'editor',   // 商品マスタ: 編集者以上
    'troubles.php' => 'viewer',   // トラブル対応: 閲覧者以上（編集は別途チェック）
    'trouble-form.php' => 'editor', // トラブル登録・編集: 編集者以上
    'trouble-bulk-form.php' => 'editor', // トラブル一括登録: 編集者以上
    'photo-attendance.php' => 'editor', // アルコールチェック管理: 編集者以上
    'photo-upload.php' => 'viewer', // 写真アップロード: 閲覧者以上
    'mf-monthly.php' => 'editor', // MF月次: 編集者以上
    'users.php' => 'admin',       // ユーザー管理: 管理者のみ
    'mf-settings.php' => 'admin', // MF連携設定: 管理者のみ
    'mf-sync-settings.php' => 'admin', // MF同期設定: 管理者のみ
    'mf-debug.php' => 'admin',    // MFデバッグ: 管理者のみ
    'notification-settings.php' => 'admin', // 通知設定: 管理者のみ
    'settings.php' => 'admin',    // 設定: 管理者のみ
    'integration-settings.php' => 'admin', // API連携設定: 管理者のみ
);

// 現在のページに必要な権限をチェック
if (isset($pagePermissions[$currentPage])) {
    if (!hasPermission($pagePermissions[$currentPage])) {
        // 権限不足の場合はトップページにリダイレクト
        header('Location: index.php');
        exit;
    }
}
