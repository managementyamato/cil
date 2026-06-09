// サイドバー開閉機能
function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    if (window.innerWidth <= 767) {
        // モバイル: オフキャンバスのドロワー開閉
        var willOpen = !sidebar.classList.contains('open');
        sidebar.classList.toggle('open', willOpen);
        setSidebarBackdrop(willOpen);
    } else {
        // デスクトップ: 折りたたみ
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }
}

// モバイルドロワー用の背景オーバーレイ
function setSidebarBackdrop(show) {
    var bd = document.getElementById('sidebarBackdrop');
    if (show) {
        if (!bd) {
            bd = document.createElement('div');
            bd.id = 'sidebarBackdrop';
            bd.className = 'sidebar-backdrop';
            bd.addEventListener('click', closeSidebarDrawer);
            document.body.appendChild(bd);
        }
        document.body.classList.add('drawer-open');
        // 次フレームで show クラスを付けてフェードイン
        requestAnimationFrame(function () { bd.classList.add('show'); });
    } else if (bd) {
        bd.classList.remove('show');
        document.body.classList.remove('drawer-open');
        window.setTimeout(function () {
            if (bd && bd.parentNode) bd.parentNode.removeChild(bd);
        }, 250);
    } else {
        document.body.classList.remove('drawer-open');
    }
}

// モバイルドロワーを閉じる
function closeSidebarDrawer() {
    var sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.remove('open');
    setSidebarBackdrop(false);
}

// テーブルをスマホでカード型表示にするための準備。
// thead を持つデータテーブルを検出し、.rsp-stack クラスと
// 各 tbody セルの data-label 属性（見出し文言）を付与する。
// CSS (components.css) の @media がこの属性を使ってカード型に描画する。
function setupResponsiveTables(root) {
    var scope = root || document;
    var tables = scope.querySelectorAll('table');
    for (var t = 0; t < tables.length; t++) {
        var table = tables[t];
        if (table.classList.contains('no-stack')) continue;
        if (table.classList.contains('rsp-stack')) continue;
        // 入れ子テーブル（セル内テーブル）は対象外
        if (table.parentElement && table.parentElement.closest('table')) continue;
        // ヘッダー行（最後の thead 行）を見出しソースにする
        var headRow = (table.tHead && table.tHead.rows.length)
            ? table.tHead.rows[table.tHead.rows.length - 1]
            : null;
        if (!headRow) continue;
        // 見出しセル(th)が2つ未満、または本文行が無いテーブルは積み替えない
        var thCount = 0;
        for (var x = 0; x < headRow.cells.length; x++) {
            if (headRow.cells[x].tagName === 'TH') thCount++;
        }
        if (thCount < 2) continue;
        if (!table.tBodies.length || !table.tBodies[0].rows.length) continue;

        var headers = [];
        for (var h = 0; h < headRow.cells.length; h++) {
            var hspan = headRow.cells[h].colSpan || 1;
            var text = (headRow.cells[h].textContent || '').replace(/\s+/g, ' ').trim();
            for (var s = 0; s < hspan; s++) headers.push(s === 0 ? text : '');
        }
        for (var b = 0; b < table.tBodies.length; b++) {
            var rows = table.tBodies[b].rows;
            for (var r = 0; r < rows.length; r++) {
                var cells = rows[r].cells;
                var col = 0;
                for (var c = 0; c < cells.length; c++) {
                    var cell = cells[c];
                    var cspan = cell.colSpan || 1;
                    // 1列ぶんのセルかつ対応する見出しがある時だけラベルを付ける。
                    // colspan セルやチェックボックス列などは空ラベルにする。
                    var label = (cspan === 1 && headers[col]) ? headers[col] : '';
                    cell.setAttribute('data-label', label);
                    col += cspan;
                }
            }
        }
        table.classList.add('rsp-stack');
    }
}
// ページ内で動的に追加されたテーブルにも適用できるよう公開
window.setupResponsiveTables = setupResponsiveTables;

// ページ読み込み時の初期化
document.addEventListener('DOMContentLoaded', function () {
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
    document.querySelectorAll('.sidebar-flyout-trigger').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = btn.closest('.sidebar-flyout-group');
            if (!group) return;
            var sb = document.getElementById('sidebar');
            // デスクトップの折りたたみ時のみアコーディオンを抑制する
            if (sb && sb.classList.contains('collapsed') && window.innerWidth > 767) return;
            var isOpen = group.classList.contains('open');
            document.querySelectorAll('.sidebar-flyout-group').forEach(function (g) { g.classList.remove('open'); });
            if (!isOpen) group.classList.add('open');
        });
    });

    // モバイル: ナビのリンクをタップしたらドロワーを閉じる
    if (sidebar) {
        sidebar.addEventListener('click', function (e) {
            if (window.innerWidth > 767) return;
            var link = e.target.closest && e.target.closest('a[href]');
            if (link) closeSidebarDrawer();
        });
    }

    // テーブルのカード型表示用ラベルを付与
    setupResponsiveTables();
});

// 画面幅がデスクトップに戻ったらドロワー状態をリセット
window.addEventListener('resize', function () {
    if (window.innerWidth > 767) {
        var sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.classList.remove('open');
        var bd = document.getElementById('sidebarBackdrop');
        if (bd && bd.parentNode) bd.parentNode.removeChild(bd);
        document.body.classList.remove('drawer-open');
    }
});

// showToast / showAlert は common-utils.js で定義（トースト通知として右上に表示）
function showAlert(message, type) {
    if (typeof showToast === 'function') {
        showToast(message, type || 'info');
    }
}

// URLパラメータからメッセージ表示
document.addEventListener('DOMContentLoaded', function () {
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

// 外部URLへのリンクは必ず新規タブで開く (セーフティネット)
// - <a target="_blank"> を付け忘れた箇所、データ由来の URL でも自動適用
// - 同一オリジン (yamato-mgt.com / localhost) と javascript:/mailto:/tel: は除外
// - rel="noopener" を追加し tabnabbing 攻撃も防ぐ
(function () {
    function isExternalUrl(href) {
        if (!href) return false;
        var lower = href.toLowerCase();
        if (lower.indexOf('javascript:') === 0) return false;
        if (lower.indexOf('mailto:') === 0) return false;
        if (lower.indexOf('tel:') === 0) return false;
        if (lower.indexOf('#') === 0) return false;
        try {
            var u = new URL(href, window.location.href);
            if (u.protocol !== 'http:' && u.protocol !== 'https:') return false;
            return u.origin !== window.location.origin;
        } catch (e) {
            return false;
        }
    }

    // ページロード時: 既存の外部リンクすべてに target/rel を補完
    function patchExistingLinks(root) {
        var links = (root || document).querySelectorAll('a[href]');
        for (var i = 0; i < links.length; i++) {
            var a = links[i];
            if (a.dataset.noBlank) continue;
            if (!isExternalUrl(a.getAttribute('href'))) continue;
            if (a.target !== '_blank') a.target = '_blank';
            var rel = (a.getAttribute('rel') || '').toLowerCase();
            if (rel.indexOf('noopener') === -1) {
                a.setAttribute('rel', (rel ? rel + ' ' : '') + 'noopener');
            }
        }
    }
    document.addEventListener('DOMContentLoaded', function () { patchExistingLinks(document); });

    // ハブ AJAX 切替後や JS で動的に追加されたリンクにも適用
    document.addEventListener('hub:contentLoaded', function () { patchExistingLinks(document); });

    // 最後の保険: クリック時に target="_blank" が無くても強制的に新規タブで開く
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented) return;
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a || a.dataset.noBlank) return;
        if (a.target === '_blank') return; // 既に新規タブなのでブラウザ任せ
        var href = a.getAttribute('href');
        if (!isExternalUrl(href)) return;
        e.preventDefault();
        window.open(a.href, '_blank', 'noopener');
    }, true);
})();
