/**
 * 通知機能
 */
(function() {
    'use strict';

    const wrapper = document.getElementById('notificationWrapper');
    const btn = document.getElementById('notificationBtn');
    const dropdown = document.getElementById('notificationDropdown');
    const badge = document.getElementById('notificationBadge');
    const list = document.getElementById('notificationList');
    const markAllBtn = document.getElementById('markAllReadBtn');

    if (!btn || !dropdown) return;

    // ドロップダウン表示/非表示
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('show');
        if (dropdown.classList.contains('show')) {
            loadNotifications();
        }
    });

    // 外側クリックで閉じる
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });

    // すべて既読にする
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
            fetch('/api/notifications.php?action=mark_all_read', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.notificationCsrfToken || '' }
            })
            .then(() => {
                loadNotifications();
                updateBadge(0);
            });
        });
    }

    // 通知を読み込む
    function loadNotifications() {
        fetch('/api/notifications.php?action=list')
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    list.innerHTML = '<div class="notification-empty">通知の取得に失敗しました</div>';
                    return;
                }

                const notifications = data.data ? data.data.notifications : (data.notifications || []);
                const unreadCount = data.data ? data.data.unread_count : (data.unread_count || 0);
                updateBadge(unreadCount);

                if (notifications.length === 0) {
                    list.innerHTML = '<div class="notification-empty">通知はありません</div>';
                    return;
                }

                let html = '';
                notifications.forEach(n => {
                    const iconClass = n.type || 'info';
                    const icon = getNotificationIcon(n.type);
                    const timeAgo = formatTimeAgo(n.created_at);
                    const unreadClass = n.is_read ? '' : 'unread';

                    html += `
                        <div class="notification-item ${unreadClass}" data-id="${n.id}" data-link="${n.link || ''}">
                            <div class="notification-icon ${iconClass}">${icon}</div>
                            <div class="notification-content">
                                <div class="notification-title">${escapeHtml(n.message)}</div>
                                <div class="notification-time">${timeAgo}</div>
                            </div>
                        </div>
                    `;
                });
                list.innerHTML = html;

                // クリックイベント
                list.querySelectorAll('.notification-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const id = this.dataset.id;
                        const link = this.dataset.link;

                        // 既読にする
                        fetch('/api/notifications.php?action=mark_read&id=' + id, {
                            method: 'POST',
                            headers: { 'X-CSRF-Token': window.notificationCsrfToken || '' }
                        });

                        this.classList.remove('unread');

                        if (link) {
                            window.location.href = link;
                        }
                    });
                });
            })
            .catch(() => {
                list.innerHTML = '<div class="notification-empty">通知の取得に失敗しました</div>';
            });
    }

    function updateBadge(count) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }

    function getNotificationIcon(type) {
        switch(type) {
            case 'warning': return '⚠️';
            case 'danger': return '🔴';
            case 'success': return '✅';
            default: return '📢';
        }
    }

    function formatTimeAgo(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'たった今';
        if (diff < 3600) return Math.floor(diff / 60) + '分前';
        if (diff < 86400) return Math.floor(diff / 3600) + '時間前';
        if (diff < 604800) return Math.floor(diff / 86400) + '日前';
        return date.toLocaleDateString('ja-JP');
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // 初回バッジ更新
    fetch('/api/notifications.php?action=unread_count')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const count = data.data ? data.data.unread_count : (data.unread_count || 0);
                updateBadge(count);
            }
        })
        .catch(() => {});

    // 定期的にバッジ更新（60秒ごと）
    setInterval(() => {
        fetch('/api/notifications.php?action=unread_count')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const count = data.data ? data.data.unread_count : (data.unread_count || 0);
                    updateBadge(count);
                }
            })
            .catch(() => {});
    }, 60000);
})();
