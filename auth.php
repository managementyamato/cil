<?php
// 認証チェック
// このファイルを各ページの先頭で読み込むことで、ログインチェックを行います

require_once 'config.php';

// ログインページは認証不要
$currentPage = basename($_SERVER['PHP_SELF']);
if ($currentPage === 'login.php') {
    return;
}

// ログインチェック
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // 未ログインの場合はログインページにリダイレクト
    header('Location: login.php');
    exit;
}

// ログイン済みの場合は処理を続行
