<?php require_once 'auth.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>現場トラブル管理システム</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>現場トラブル管理</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?>
                <?php
                $roleLabels = array('admin' => '管理者', 'editor' => '編集者', 'viewer' => '閲覧者');
                $roleLabel = $roleLabels[$_SESSION['user_role']] ?? '';
                if ($roleLabel) echo '<span class="role-badge">' . htmlspecialchars($roleLabel) . '</span>';
                ?>
                </span>
                <a href="logout.php" class="logout-btn">ログアウト</a>
            </div>
        </div>
    </header>
    <div class="layout">
        <aside class="sidebar">
            <nav class="sidebar-nav">
                <a href="index.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">分析</a>
                <a href="list.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'list.php' ? 'active' : '' ?>">一覧</a>
                <?php if (canEdit()): ?>
                <a href="report.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : '' ?>">報告</a>
                <a href="master.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'master.php' ? 'active' : '' ?>">プロジェクト管理</a>
                <a href="finance.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'finance.php' ? 'active' : '' ?>">財務管理</a>
                <a href="customers.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : '' ?>">顧客マスタ</a>
                <a href="partners.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'partners.php' ? 'active' : '' ?>">パートナーマスタ</a>
                <a href="employees.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : '' ?>">従業員マスタ</a>
                <a href="products.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>">商品マスタ</a>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                <a href="users.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">ユーザー管理</a>
                <a href="mf-settings.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'mf-settings.php' ? 'active' : '' ?>">MF連携設定</a>
                <?php endif; ?>
            </nav>
        </aside>
        <main class="main-content">
