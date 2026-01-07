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
