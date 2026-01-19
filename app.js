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
        var wasCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (wasCollapsed) {
            sidebar.classList.add('collapsed');
        }
    }
});

// Toast表示
function showToast(message) {
    var toast = document.getElementById('toast');
    if (toast) {
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(function() {
            toast.classList.remove('show');
        }, 3000);
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
