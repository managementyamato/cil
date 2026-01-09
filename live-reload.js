// ライブリロード機能
// ファイルが変更されたら自動的にページをリロード
(function() {
    // 開発環境のみで有効化
    if (window.location.hostname !== 'localhost' && !window.location.hostname.startsWith('192.168')) {
        return;
    }

    let lastModified = null;
    const CHECK_INTERVAL = 1000; // 1秒ごとにチェック

    function checkForUpdates() {
        fetch(window.location.href, {
            method: 'HEAD',
            cache: 'no-cache'
        })
        .then(response => {
            const modified = response.headers.get('Last-Modified');

            if (lastModified === null) {
                lastModified = modified;
            } else if (modified !== lastModified) {
                console.log('ファイルが更新されました。ページをリロードします...');
                window.location.reload();
            }
        })
        .catch(error => {
            // エラーは無視（サーバーが一時的に停止している場合など）
        });
    }

    // 定期的にチェック
    setInterval(checkForUpdates, CHECK_INTERVAL);

    console.log('🔄 ライブリロード機能が有効です');
})();
