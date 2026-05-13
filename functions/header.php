<?php
require_once '../api/auth.php';
// セキュリティヘッダーは config.php のセッション開始後に自動設定済み
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>YA管理一覧</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">
    <link rel="stylesheet" href="/style.css?v=20260415">
    <link rel="stylesheet" href="/css/components.css?v=20260410">
    <style>.alert.alert-success,.alert.alert-danger,.alert.alert-error,.alert.alert-warning,.alert.alert-info{display:none!important;}</style>
    <script>if(localStorage.getItem('sidebarCollapsed')!=='false')document.documentElement.classList.add('sidebar-pre-collapsed');(function(){var t=localStorage.getItem('pageTheme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
    <script src="/app.js?v=20260410" defer></script>
    <script src="/js/common-utils.js?v=20260415" defer></script>
    <script src="/js/icons.js" defer></script>
    <script src="/js/background-jobs.js" defer></script>
    <script src="/js/notifications.js" defer></script>
    <script<?= nonceAttr() ?>>window.notificationCsrfToken = '<?= generateCsrfToken() ?>';document.addEventListener('DOMContentLoaded',function(){var b=document.getElementById('menuToggle');if(b&&typeof toggleSidebar==='function')b.addEventListener('click',toggleSidebar);(function(){var btn=document.getElementById('themePickerBtn'),dd=document.getElementById('themePickerDropdown');if(!btn||!dd)return;var cur=localStorage.getItem('pageTheme')||'';document.querySelectorAll('.theme-color-btn').forEach(function(b){if(b.getAttribute('data-theme')===cur){b.style.borderColor='var(--gray-900)';b.textContent='\u2713';}b.addEventListener('click',function(){var t=this.getAttribute('data-theme');if(t){document.documentElement.setAttribute('data-theme',t);localStorage.setItem('pageTheme',t);}else{document.documentElement.removeAttribute('data-theme');localStorage.removeItem('pageTheme');}document.querySelectorAll('.theme-color-btn').forEach(function(x){x.style.borderColor='transparent';x.textContent='';});this.style.borderColor='var(--gray-900)';this.textContent='\u2713';dd.style.display='none';});});btn.addEventListener('click',function(e){e.stopPropagation();dd.style.display=dd.style.display==='none'?'block':'none';});document.addEventListener('click',function(e){if(!dd.contains(e.target)&&e.target!==btn)dd.style.display='none';});})();/* インラインalertをトースト通知に自動変換 */document.querySelectorAll('.alert.alert-success,.alert.alert-danger,.alert.alert-error,.alert.alert-warning,.alert.alert-info').forEach(function(el){if(el.closest('#toast-container'))return;var type='info';if(el.classList.contains('alert-success'))type='success';else if(el.classList.contains('alert-danger'))type='danger';else if(el.classList.contains('alert-error'))type='error';else if(el.classList.contains('alert-warning'))type='warning';var msg=el.textContent.trim();if(msg&&typeof showToast==='function'){showToast(msg,type,5000);el.remove();}});});</script>
    <style<?= nonceAttr() ?>>
    /* バックグラウンドジョブ通知 */
    .background-jobs-container {
        position: fixed;
        bottom: 1rem;
        right: 1rem;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        max-width: 350px;
    }
    .background-job-notification {
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        animation: slideIn 0.3s ease-out;
    }
    .background-job-notification.running {
        border-left: 4px solid var(--primary);
    }
    .background-job-notification.completed {
        border-left: 4px solid var(--success);
    }
    .background-job-notification.failed {
        border-left: 4px solid var(--danger);
    }
    .job-spinner {
        width: 20px;
        height: 20px;
        border: 2px solid var(--gray-200);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    .job-icon {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }
    .job-content {
        flex: 1;
        min-width: 0;
    }
    .job-title {
        font-weight: 500;
        font-size: 0.875rem;
        color: #1f2937;
        margin-bottom: 0.25rem;
    }
    .job-message {
        font-size: 0.75rem;
        color: #6b7280;
    }
    .job-dismiss {
        background: none;
        border: none;
        color: #9ca3af;
        cursor: pointer;
        padding: 0.25rem;
        line-height: 1;
    }
    .job-dismiss:hover {
        color: #6b7280;
    }
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* 通知アイコン */
    .notification-icon-wrapper {
        position: relative;
    }
    .notification-btn {
        background: none;
        border: none;
        padding: 0.5rem;
        cursor: pointer;
        color: #6b7280;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.15s;
    }
    .notification-btn:hover {
        background: #f3f4f6;
        color: #374151;
    }
    .notification-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        min-width: 16px;
        height: 16px;
        background: var(--danger);
        color: white;
        font-size: 0.65rem;
        font-weight: 600;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
    }
    .notification-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: 320px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        border: 1px solid #e5e7eb;
        z-index: 10001;
        display: none;
        overflow: hidden;
    }
    .notification-dropdown.show {
        display: block;
        animation: fadeIn 0.15s ease-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .notification-dropdown-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.875rem 1rem;
        border-bottom: 1px solid #e5e7eb;
        font-weight: 600;
        font-size: 0.9rem;
        color: #1f2937;
    }
    .mark-all-read-btn {
        background: none;
        border: none;
        color: #6b7280;
        cursor: pointer;
        font-size: 1rem;
        padding: 0.25rem;
        border-radius: 4px;
    }
    .mark-all-read-btn:hover {
        background: #f3f4f6;
        color: #374151;
    }
    .notification-list {
        max-height: 320px;
        overflow-y: auto;
    }
    .notification-item {
        display: flex;
        gap: 0.75rem;
        padding: 0.875rem 1rem;
        border-bottom: 1px solid #f3f4f6;
        cursor: pointer;
        transition: background 0.15s;
    }
    .notification-item:last-child {
        border-bottom: none;
    }
    .notification-item:hover {
        background: #f9fafb;
    }
    .notification-item.unread {
        background: var(--primary-light);
    }
    .notification-item.unread:hover {
        background: #d0ece7;
    }
    .notification-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
    }
    .notification-icon.info { background: var(--primary-light); }
    .notification-icon.warning { background: var(--warning-light); }
    .notification-icon.danger { background: var(--danger-light); }
    .notification-icon.success { background: var(--success-light); }
    .notification-content {
        flex: 1;
        min-width: 0;
    }
    .notification-title {
        font-size: 0.85rem;
        font-weight: 500;
        color: #1f2937;
        margin-bottom: 0.25rem;
        line-height: 1.3;
    }
    .notification-time {
        font-size: 0.75rem;
        color: #9ca3af;
    }
    .notification-empty {
        padding: 2rem 1rem;
        text-align: center;
        color: #9ca3af;
        font-size: 0.875rem;
    }
    /* グローバル検索ドロップダウン */
    .global-search-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
        margin-top: 4px;
    }
    .search-result-item {
        display: flex;
        align-items: center;
        padding: 0.6rem 0.75rem;
        cursor: pointer;
        text-decoration: none;
        color: var(--gray-900);
        border-bottom: 1px solid var(--gray-100);
        gap: 0.75rem;
    }
    .search-result-item:hover { background: var(--gray-50); }
    .search-result-item:last-child { border-bottom: none; }
    .search-result-type {
        font-size: 0.7rem;
        padding: 0.15rem 0.4rem;
        border-radius: 4px;
        font-weight: 600;
        white-space: nowrap;
    }
    .search-type-project { background: #dbeafe; color: #1d4ed8; }
    .search-type-trouble { background: #fee2e2; color: #dc2626; }
    .search-type-customer { background: #d1fae5; color: #059669; }
    .search-type-employee { background: #fef3c7; color: #d97706; }
    .search-type-task { background: #e0e7ff; color: #4338ca; }
    .search-result-info { flex: 1; min-width: 0; }
    .search-result-title { font-size: 0.875rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .search-result-subtitle { font-size: 0.75rem; color: var(--gray-500); }
    .search-no-results { padding: 1rem; text-align: center; color: var(--gray-400); font-size: 0.875rem; }
    .search-footer { padding: 0.5rem 0.75rem; text-align: center; border-top: 1px solid var(--gray-200); }
    .search-footer a { font-size: 0.8rem; color: var(--primary); text-decoration: none; }
    @media (max-width: 768px) {
        .global-search-wrapper { display: none; }
    }
    </style>
</head>
<body>
    <!-- バックグラウンドジョブ通知エリア -->
    <div class="background-jobs-container" id="backgroundJobsContainer"></div>

    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <h1>YA管理一覧</h1>
            </div>
            <!-- グローバル検索 -->
            <div class="global-search-wrapper" style="flex: 1; max-width: 400px; margin: 0 1rem; position: relative;">
                <div style="position: relative;">
                    <input type="text" id="globalSearchInput" placeholder="検索... (Ctrl+K)" autocomplete="off"
                           style="width: 100%; padding: 0.4rem 0.75rem 0.4rem 2rem; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 0.875rem; background: var(--gray-50);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="2" style="position: absolute; left: 0.6rem; top: 50%; transform: translateY(-50%); pointer-events: none;">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </div>
                <div id="globalSearchResults" class="global-search-dropdown" style="display: none;"></div>
            </div>
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']) ?>
                <?php
                $roleLabels = array('admin' => '管理部', 'product' => '製品管理部', 'sales' => '営業部');
                $roleLabel = $roleLabels[$_SESSION['user_role']] ?? '';
                if ($roleLabel) echo '<span class="role-badge">' . htmlspecialchars($roleLabel) . '</span>';
                ?>
                </span>
                <div class="theme-picker-wrapper" style="position: relative;">
                    <button class="notification-btn" id="themePickerBtn" title="テーマカラー">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="13.5" cy="6.5" r="2.5" fill="#c0392b" stroke="none"/>
                            <circle cx="17.5" cy="10.5" r="2.5" fill="#d68910" stroke="none"/>
                            <circle cx="8.5" cy="5.5" r="2.5" fill="#2980b9" stroke="none"/>
                            <circle cx="6.5" cy="11.5" r="2.5" fill="#8e44ad" stroke="none"/>
                            <circle cx="12" cy="12" r="2.5" fill="#117a65" stroke="none"/>
                            <path d="M12 22c-4.97 0-9-2.69-9-6v-1c0-.55.45-1 1-1h1c.55 0 1 .45 1 1 0 1.1.9 2 2 2s2-.9 2-2V3c0-1.1.9-2 2-2s2 .9 2 2v12c0 1.1.9 2 2 2s2-.9 2-2c0-.55.45-1 1-1h1c.55 0 1 .45 1 1v1c0 3.31-4.03 6-9 6z" opacity="0.15"/>
                        </svg>
                    </button>
                    <div class="theme-picker-dropdown" id="themePickerDropdown" style="display:none; position:absolute; right:0; top:100%; margin-top:0.5rem; background:white; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15); padding:0.75rem; z-index:1001; min-width:180px;">
                        <div style="font-size:0.75rem; color:var(--gray-700); margin-bottom:0.5rem; font-weight:600;">テーマカラー</div>
                        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                            <button class="theme-color-btn" data-theme="" title="ティール" style="width:32px;height:32px;border-radius:50%;border:2px solid transparent;background:#117a65;cursor:pointer;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;"></button>
                            <button class="theme-color-btn" data-theme="blue" title="ブルー" style="width:32px;height:32px;border-radius:50%;border:2px solid transparent;background:#2980b9;cursor:pointer;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;"></button>
                            <button class="theme-color-btn" data-theme="purple" title="パープル" style="width:32px;height:32px;border-radius:50%;border:2px solid transparent;background:#8e44ad;cursor:pointer;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;"></button>
                            <button class="theme-color-btn" data-theme="red" title="レッド" style="width:32px;height:32px;border-radius:50%;border:2px solid transparent;background:#c0392b;cursor:pointer;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;"></button>
                            <button class="theme-color-btn" data-theme="orange" title="オレンジ" style="width:32px;height:32px;border-radius:50%;border:2px solid transparent;background:#d68910;cursor:pointer;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;"></button>
                            <button class="theme-color-btn" data-theme="green" title="グリーン" style="width:32px;height:32px;border-radius:50%;border:2px solid transparent;background:#27ae60;cursor:pointer;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;"></button>
                            <button class="theme-color-btn" data-theme="pink" title="ピンク" style="width:32px;height:32px;border-radius:50%;border:2px solid transparent;background:#e91e63;cursor:pointer;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;"></button>
                            <button class="theme-color-btn" data-theme="indigo" title="インディゴ" style="width:32px;height:32px;border-radius:50%;border:2px solid transparent;background:#3f51b5;cursor:pointer;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;"></button>
                            <button class="theme-color-btn" data-theme="brown" title="ブラウン" style="width:32px;height:32px;border-radius:50%;border:2px solid transparent;background:#795548;cursor:pointer;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;"></button>
                            <button class="theme-color-btn" data-theme="navy" title="ネイビー" style="width:32px;height:32px;border-radius:50%;border:2px solid transparent;background:#1a237e;cursor:pointer;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;"></button>
                        </div>
                    </div>
                </div>
                <div class="notification-icon-wrapper" id="notificationWrapper">
                    <button class="notification-btn" id="notificationBtn" title="通知">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <span class="notification-badge" id="notificationBadge" style="display:none;">0</span>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-dropdown-header">
                            <span>通知</span>
                            <button class="mark-all-read-btn" id="markAllReadBtn" title="すべて既読にする">✓</button>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div class="notification-empty">通知はありません</div>
                        </div>
                    </div>
                </div>
                <a href="/pages/logout.php" class="logout-btn">ログアウト</a>
            </div>
        </div>
    </header>
    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <?php
            $_cp = basename($_SERVER['PHP_SELF']);
            $_ag = '';
            if (in_array($_cp, ['master.php', 'troubles.php', 'trouble-form.php', 'trouble-bulk-form.php', 'sync-troubles.php'])) $_ag = 'business';
            elseif (in_array($_cp, ['sales-tools.php'])) $_ag = 'sales';
            elseif (in_array($_cp, ['finance.php', 'mf-monthly.php', 'mf-mapping.php', 'loans.php', 'payroll-journal.php', 'invoice-confirm.php', 'invoice-requests.php', 'custom-invoice-list.php', 'custom-invoice-create.php'])) $_ag = 'finance';
            elseif (in_array($_cp, ['contacts.php', 'company-rules.php', 'slides.php', 'masters.php', 'customers.php'])) $_ag = 'internal';
            elseif (in_array($_cp, ['photo-attendance.php', 'reports-hub.php'])) $_ag = 'daily';
            ?>
            <nav class="sidebar-nav">
                <a href="/pages/index.php" class="sidebar-link <?= $_cp == 'index.php' ? 'active' : '' ?>" style="border-bottom: 1px solid var(--gray-200); margin-bottom: 0.25rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>ダッシュボード</span>
                </a>

                <!-- 営業ツール -->
                <?php if (hasPermission(getPageViewPermission('sales-tools.php'))): ?>
                <a href="/pages/sales-tools.php" class="sidebar-link <?= $_cp == 'sales-tools.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <span>営業ツール</span>
                </a>
                <?php endif; ?>

                <!-- 業務グループ -->
                <div class="sidebar-flyout-group <?= $_ag === 'business' ? 'open' : '' ?>">
                    <button class="sidebar-flyout-trigger <?= $_ag === 'business' ? 'active' : '' ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                        <span class="group-label">業務</span>
                        <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="sidebar-flyout-menu">
                        <?php if (hasPermission(getPageViewPermission('master.php'))): ?>
                        <a href="/pages/master.php" class="sidebar-link <?= $_cp == 'master.php' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            <span>プロジェクト管理</span>
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission(getPageViewPermission('troubles.php'))): ?>
                        <a href="/pages/troubles.php" class="sidebar-link <?= in_array($_cp, ['troubles.php', 'trouble-form.php', 'trouble-bulk-form.php', 'sync-troubles.php']) ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span>トラブル対応</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 財務グループ -->
                <div class="sidebar-flyout-group <?= $_ag === 'finance' ? 'open' : '' ?>">
                    <button class="sidebar-flyout-trigger <?= $_ag === 'finance' ? 'active' : '' ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <span class="group-label">財務</span>
                        <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="sidebar-flyout-menu">
                        <?php if (hasPermission(getPageViewPermission('finance.php'))): ?>
                        <a href="/pages/finance.php" class="sidebar-link <?= in_array($_cp, ['finance.php', 'mf-monthly.php', 'mf-mapping.php']) ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            <span>損益</span>
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission(getPageViewPermission('loans.php'))): ?>
                        <a href="/pages/loans.php" class="sidebar-link <?= $_cp == 'loans.php' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            <span>借入金</span>
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission(getPageViewPermission('payroll-journal.php'))): ?>
                        <a href="/pages/payroll-journal.php" class="sidebar-link <?= $_cp == 'payroll-journal.php' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <span>給与仕訳</span>
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission(getPageViewPermission('invoice-confirm.php'))): ?>
                        <a href="/pages/invoice-confirm.php" class="sidebar-link <?= $_cp == 'invoice-confirm.php' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            <span>請求書確認</span>
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission(getPageViewPermission('invoice-requests.php'))): ?>
                        <a href="/pages/invoice-requests.php" class="sidebar-link <?= $_cp == 'invoice-requests.php' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="12" y1="10" x2="12" y2="16"/></svg>
                            <span>請求書作成依頼</span>
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission(getPageViewPermission('custom-invoice-list.php'))): ?>
                        <a href="/pages/custom-invoice-list.php" class="sidebar-link <?= in_array($_cp, ['custom-invoice-list.php', 'custom-invoice-create.php']) ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg>
                            <span>指定請求書一覧</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 日常グループ -->
                <div class="sidebar-flyout-group <?= $_ag === 'daily' ? 'open' : '' ?>">
                    <button class="sidebar-flyout-trigger <?= $_ag === 'daily' ? 'active' : '' ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span class="group-label">日常</span>
                        <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="sidebar-flyout-menu">
                        <?php if (hasPermission(getPageViewPermission('photo-attendance.php'))): ?>
                        <a href="/pages/photo-attendance.php" class="sidebar-link <?= $_cp == 'photo-attendance.php' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <span>アルコールチェック</span>
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission(getPageViewPermission('reports-hub.php'))): ?>
                        <a href="/pages/reports-hub.php" class="sidebar-link <?= $_cp == 'reports-hub.php' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            <span>申請・報告</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 社内グループ -->
                <div class="sidebar-flyout-group <?= $_ag === 'internal' ? 'open' : '' ?>">
                    <button class="sidebar-flyout-trigger <?= $_ag === 'internal' ? 'active' : '' ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <span class="group-label">社内</span>
                        <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="sidebar-flyout-menu">
                        <?php if (hasPermission(getPageViewPermission('contacts.php'))): ?>
                        <a href="/pages/contacts.php" class="sidebar-link <?= $_cp == 'contacts.php' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72c.127.84.292 1.67.49 2.49a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.18 6.18l1.27-1.27a2 2 0 0 1 2.11-.45c.82.198 1.65.363 2.49.49A2 2 0 0 1 22 16.92z"/></svg>
                            <span>社内連絡先</span>
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission(getPageViewPermission('company-rules.php'))): ?>
                        <a href="/pages/company-rules.php" class="sidebar-link <?= $_cp == 'company-rules.php' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            <span>社内規則</span>
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission(getPageViewPermission('slides.php'))): ?>
                        <a href="/pages/slides.php" class="sidebar-link <?= $_cp == 'slides.php' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            <span>社内マニュアル</span>
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission(getPageViewPermission('masters.php'))): ?>
                        <a href="/pages/masters.php" class="sidebar-link <?= in_array($_cp, ['masters.php', 'customers.php']) ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <span>マスタ管理</span>
                        </a>
                        <?php endif; ?>
                        <a href="https://inventory.yamato-mgt.com/" target="_blank" class="sidebar-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            <span>デバイス管理</span>
                        </a>
                        <?php if (hasPermission(getPageViewPermission('cms-news.php'))): ?>
                        <a href="/pages/cms-news.php" class="sidebar-link <?= $_cp == 'cms-news.php' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            <span>HP更新</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 設定（adminのみ） -->
                <?php if (isAdmin()): ?>
                <a href="/pages/settings.php" class="sidebar-icon-link <?= in_array($_cp, ['settings.php', 'mf-settings.php', 'mf-debug.php', 'mf-sync-settings.php', 'notification-settings.php', 'employees.php', 'integration-settings.php', 'google-oauth-settings.php', 'user-permissions.php', 'audit-log.php', 'cms-settings.php']) ? 'active' : '' ?>" style="margin-top: auto; border-top: 1px solid var(--gray-200);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <span class="link-label">設定</span>
                    <span class="sidebar-icon-tooltip">設定</span>
                </a>
                <?php endif; ?>
            </nav>
        </aside>
        <main class="main-content">
