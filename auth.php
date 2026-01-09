<?php
// 認証チェック
// このファイルを各ページの先頭で読み込むことで、ログインチェックを行います

require_once 'config.php';

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
    'index.php' => 'viewer',      // 分析: 閲覧者以上
    'list.php' => 'viewer',       // 一覧: 閲覧者以上（編集は別途チェック）
    'report.php' => 'editor',     // 報告: 編集者以上
    'master.php' => 'editor',     // PJ管理: 編集者以上
    'finance.php' => 'editor',    // 財務管理: 編集者以上
    'customers.php' => 'editor',  // 顧客マスタ: 編集者以上
    'partners.php' => 'editor',   // パートナーマスタ: 編集者以上
    'employees.php' => 'editor',  // 従業員マスタ: 編集者以上
    'products.php' => 'editor',   // 商品マスタ: 編集者以上
    'users.php' => 'admin',       // ユーザー管理: 管理者のみ
    'mf-settings.php' => 'admin', // MF連携設定: 管理者のみ
);

// 現在のページに必要な権限をチェック
if (isset($pagePermissions[$currentPage])) {
    if (!hasPermission($pagePermissions[$currentPage])) {
        // 権限不足の場合はトップページにリダイレクト
        header('Location: index.php');
        exit;
    }
}
