<?php require_once 'auth.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>YA管理一覧</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <h1>YA管理一覧</h1>
            </div>
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
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="javascript:history.back()" class="sidebar-link" style="border-bottom: 1px solid var(--gray-200); margin-bottom: 1rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    <span>戻る</span>
                </a>
                <a href="index.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>分析</span>
                </a>
                <a href="list.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'list.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    <span>一覧</span>
                </a>
                <?php if (canEdit()): ?>
                <a href="master.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'master.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    <span>プロジェクト管理</span>
                </a>
                <a href="finance.php" class="sidebar-link <?= in_array(basename($_SERVER['PHP_SELF']), ['finance.php', 'mf-monthly.php']) ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>損益</span>
                </a>
                <a href="photo-attendance.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'photo-attendance.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <span>アルコールチェック管理</span>
                </a>
                <?php endif; ?>
                <a href="photo-upload.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'photo-upload.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span>アルコールチェック写真</span>
                </a>
                <?php if (isAdmin()): ?>
                <a href="settings.php" class="sidebar-link <?= in_array(basename($_SERVER['PHP_SELF']), ['settings.php', 'mf-settings.php', 'mf-debug.php', 'employees.php', 'users.php']) ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <span>設定</span>
                </a>
                <?php endif; ?>
            </nav>
        </aside>
        <main class="main-content">
