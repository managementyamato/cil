// サイドバー開閉機能
function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('collapsed');
        // モバイルの場合は open クラスをトグル
        if (window.innerWidth <= 767) {
            sidebar.classList.toggle('open');
        }
        // 状態をローカルストレージに保存
        var isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }
}

// ページ読み込み時にサイドバーの状態を復元
document.addEventListener('DOMContentLoaded', function() {
    var sidebar = document.getElementById('sidebar');
    if (sidebar && window.innerWidth > 767) {
        var wasCollapsed = localStorage.getItem('sidebarCollapsed') !== 'false';
        if (wasCollapsed) {
            sidebar.classList.add('collapsed');
        }
    }
    // 事前適用クラスを削除してトランジションを有効化
    document.documentElement.classList.remove('sidebar-pre-collapsed');

    // サイドバーグループのアコーディオン開閉
    document.querySelectorAll('.sidebar-flyout-trigger').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var group = btn.closest('.sidebar-flyout-group');
            if (!group) return;
            var sidebar = document.getElementById('sidebar');
            if (sidebar && sidebar.classList.contains('collapsed')) return;
            var isOpen = group.classList.contains('open');
            document.querySelectorAll('.sidebar-flyout-group').forEach(function(g) { g.classList.remove('open'); });
            if (!isOpen) group.classList.add('open');
        });
    });
});

// Toast表示
function showToast(message) {
    var toast = document.getElementById('toast');
    if (toast) {
        toast.textContent = message;
        toast.className = 'toast show';
        setTimeout(function() {
            toast.classList.remove('show');
        }, 3000);
    }
}

// アラート表示（showToastのラッパー。type: 'success' | 'error' | 'danger'）
function showAlert(message, type) {
    var toast = document.getElementById('toast');
    if (toast) {
        toast.textContent = message;
        var isError = (type === 'error' || type === 'danger');
        toast.className = 'toast show' + (isError ? ' toast-error' : '');
        setTimeout(function() {
            toast.classList.remove('show');
        }, 4000);
    }
}

// URLパラメータからメッセージ表示
document.addEventListener('DOMContentLoaded', function() {
    var params = new URLSearchParams(window.location.search);

    if (params.get('reported') === '1') {
        showToast('トラブルを報告しました');
    }
    if (params.get('updated') === '1') {
        showToast('更新しました');
    }
    if (params.get('deleted') === '1') {
        showToast('削除しました');
    }
});
