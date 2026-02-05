<?php require_once '../api/auth.php'; ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>YA管理一覧</title>
    <link rel="stylesheet" href="/style.css?v=20260130b">
    <script src="/app.js" defer></script>
    <style>
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
        border-left: 4px solid #3b82f6;
    }
    .background-job-notification.completed {
        border-left: 4px solid #22c55e;
    }
    .background-job-notification.failed {
        border-left: 4px solid #ef4444;
    }
    .job-spinner {
        width: 20px;
        height: 20px;
        border: 2px solid #e5e7eb;
        border-top-color: #3b82f6;
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
    </style>
</head>
<body>
    <!-- バックグラウンドジョブ通知エリア -->
    <div class="background-jobs-container" id="backgroundJobsContainer"></div>

    <script>
    // バックグラウンドジョブ監視・処理
    (function() {
        let pollingInterval = null;
        let processInterval = null;
        let knownJobs = {};
        let isProcessing = false;

        // ジョブ状態をチェック
        function checkBackgroundJobs() {
            fetch('/api/background-job.php?action=active')
                .then(r => r.json())
                .then(data => {
                    if (!data.jobs) return;

                    const container = document.getElementById('backgroundJobsContainer');
                    const currentJobs = data.jobs;

                    // 新しいジョブまたは更新されたジョブを表示
                    for (const [id, job] of Object.entries(currentJobs)) {
                        if (job.dismissed) continue;

                        let el = document.getElementById('job-' + id);
                        if (!el) {
                            el = createJobNotification(id, job);
                            container.appendChild(el);
                        } else {
                            updateJobNotification(el, job);
                        }
                        knownJobs[id] = job;
                    }

                    // 完了したジョブは一定時間後に自動で消す
                    for (const [id, job] of Object.entries(currentJobs)) {
                        if ((job.status === 'completed' || job.status === 'failed') && !job.autoDismissScheduled) {
                            job.autoDismissScheduled = true;
                            setTimeout(() => {
                                const el = document.getElementById('job-' + id);
                                if (el) {
                                    el.style.animation = 'slideIn 0.3s ease-out reverse';
                                    setTimeout(() => el.remove(), 300);
                                }
                            }, 10000);
                        }
                    }

                    // 実行中のジョブがあれば処理を開始
                    const hasRunning = Object.values(currentJobs).some(j => j.status === 'running');
                    if (hasRunning) {
                        startProcessing();
                    } else {
                        stopProcessing();
                    }
                })
                .catch(err => console.error('Background job check error:', err));
        }

        // ジョブの処理を進める
        function processJobs() {
            if (isProcessing) return;
            isProcessing = true;

            fetch('/api/loans-color.php?action=process')
                .then(r => r.json())
                .then(data => {
                    isProcessing = false;
                    if (data.processed) {
                        // 処理が進んだら状態を更新
                        checkBackgroundJobs();
                    }
                })
                .catch(err => {
                    isProcessing = false;
                    console.error('Job process error:', err);
                });
        }

        function startProcessing() {
            if (!processInterval) {
                processInterval = setInterval(processJobs, 500); // 0.5秒ごとに1件処理
                processJobs(); // 即座に開始
            }
            if (!pollingInterval) {
                pollingInterval = setInterval(checkBackgroundJobs, 2000);
            }
        }

        function stopProcessing() {
            if (processInterval) {
                clearInterval(processInterval);
                processInterval = null;
            }
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }

        function createJobNotification(id, job) {
            const el = document.createElement('div');
            el.id = 'job-' + id;
            el.className = 'background-job-notification ' + job.status;
            updateJobNotification(el, job);
            return el;
        }

        function updateJobNotification(el, job) {
            el.className = 'background-job-notification ' + job.status;

            let icon = '';
            if (job.status === 'running') {
                icon = '<div class="job-spinner"></div>';
            } else if (job.status === 'completed') {
                icon = '<svg class="job-icon" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
            } else if (job.status === 'failed') {
                icon = '<svg class="job-icon" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
            }

            el.innerHTML = `
                ${icon}
                <div class="job-content">
                    <div class="job-title">${escapeHtml(job.description || job.type)}</div>
                    <div class="job-message">${escapeHtml(job.message || '')}</div>
                </div>
                <button class="job-dismiss" onclick="dismissJob('${job.id}')" title="閉じる">✕</button>
            `;
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        window.dismissJob = function(jobId) {
            fetch('/api/background-job.php?action=dismiss&job_id=' + jobId)
                .then(() => {
                    const el = document.getElementById('job-' + jobId);
                    if (el) {
                        el.style.animation = 'slideIn 0.3s ease-out reverse';
                        setTimeout(() => el.remove(), 300);
                    }
                });
        };

        // 初回チェック
        checkBackgroundJobs();
        // 定期チェック（アクティブなジョブがない場合は10秒おき）
        setInterval(checkBackgroundJobs, 10000);
    })();
    </script>
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
                $roleLabels = array('admin' => '管理部', 'product' => '製品管理部', 'sales' => '営業部');
                $roleLabel = $roleLabels[$_SESSION['user_role']] ?? '';
                if ($roleLabel) echo '<span class="role-badge">' . htmlspecialchars($roleLabel) . '</span>';
                ?>
                </span>
                <a href="/pages/sessions.php" class="logout-btn" style="background: none; color: #6b7280;" title="セッション管理">🔐</a>
                <a href="/pages/logout.php" class="logout-btn">ログアウト</a>
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
                <a href="/pages/index.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>ダッシュボード</span>
                </a>
                <?php if (canEdit()): ?>
                <a href="/pages/master.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'master.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    <span>プロジェクト管理</span>
                </a>
                <a href="/pages/finance.php" class="sidebar-link <?= in_array(basename($_SERVER['PHP_SELF']), ['finance.php', 'mf-monthly.php']) ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>損益</span>
                </a>
                <a href="/pages/profit-loss.php" class="sidebar-link <?= in_array(basename($_SERVER['PHP_SELF']), ['profit-loss.php', 'profit-loss-upload.php']) ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><polyline points="7 13 12 8 16 12 21 7"/></svg>
                    <span>損益計算書</span>
                </a>
                <a href="/pages/loans.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'loans.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>借入金管理</span>
                </a>
                <a href="/pages/payroll-journal.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'payroll-journal.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>給与仕訳</span>
                </a>
                <a href="/pages/photo-attendance.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'photo-attendance.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <span>アルコールチェック</span>
                </a>
                <a href="/pages/troubles.php" class="sidebar-link <?= in_array(basename($_SERVER['PHP_SELF']), ['troubles.php', 'trouble-form.php', 'trouble-bulk-form.php', 'sync-troubles.php']) ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>トラブル対応</span>
                </a>
                <a href="/pages/masters.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'masters.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>マスタ管理</span>
                </a>
                <?php endif; ?>
                <a href="/pages/tasks.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><path d="M9 12l2 2 4-4"/></svg>
                    <span>タスク管理</span>
                </a>
                <?php if (isAdmin()): ?>
                <a href="/pages/settings.php" class="sidebar-link <?= in_array(basename($_SERVER['PHP_SELF']), ['settings.php', 'mf-settings.php', 'mf-debug.php', 'mf-sync-settings.php', 'notification-settings.php', 'employees.php', 'integration-settings.php', 'google-oauth-settings.php', 'user-permissions.php', 'audit-log.php']) ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <span>設定</span>
                </a>
                <?php endif; ?>
            </nav>
        </aside>
        <main class="main-content">
