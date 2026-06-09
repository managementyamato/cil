<?php
/**
 * スプレッドシート閲覧（管理部専用）
 *
 * 指定の Google スプレッドシートの対象タブ（複数）の内容を
 * タブ切替で表形式表示する。データは「同期」ボタンで取得し、
 * data/sheet-viewer-cache.json にキャッシュする。
 *
 * 同期処理・対象タブの定義: api/sheet-viewer-sync.php
 * 権限: admin のみ
 */
require_once '../api/auth.php';
require_once '../functions/header.php';

if (!isAdmin()) {
    echo '<div style="padding:2rem;text-align:center;color:var(--gray-600);">管理部のみアクセス可能です</div>';
    require_once '../functions/footer.php';
    return;
}

$csrfToken = generateCsrfToken();

// --- キャッシュ読み込み ---
$cachePath = __DIR__ . '/../data/sheet-viewer-cache.json';
$cache = null;
if (file_exists($cachePath)) {
    $raw = json_decode((string)file_get_contents($cachePath), true);
    if (is_array($raw)) {
        $cache = $raw;
    }
}

$sheets = (is_array($cache) && is_array($cache['sheets'] ?? null)) ? $cache['sheets'] : [];
$spreadsheetId = (is_array($cache) ? ($cache['spreadsheet_id'] ?? '') : '')
    ?: '16DgKDdAxpPD64jZhM-Vo7CcJOIMXh5uFhnVTCEvuHBQ';
$spreadsheetBaseUrl = 'https://docs.google.com/spreadsheets/d/' . $spreadsheetId . '/edit';

/**
 * 1 シート分の値（2 次元配列）を HTML テーブルとして出力する。
 * 先頭行をヘッダ行として扱う。
 */
function sv_render_table(array $values): void {
    $maxCols = 0;
    foreach ($values as $row) {
        if (is_array($row)) {
            $maxCols = max($maxCols, count($row));
        }
    }
    $header = (isset($values[0]) && is_array($values[0])) ? $values[0] : [];
    ?>
    <div class="sv-table-wrap">
        <table class="sv-table">
            <thead>
                <tr>
                    <th class="sv-rownum"></th>
                    <?php for ($c = 0; $c < $maxCols; $c++): ?>
                    <th><?= htmlspecialchars((string)($header[$c] ?? '')) ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($r = 1, $n = count($values); $r < $n; $r++): ?>
                <?php $row = is_array($values[$r]) ? $values[$r] : []; ?>
                <tr>
                    <td class="sv-rownum"><?= $r + 1 ?></td>
                    <?php for ($c = 0; $c < $maxCols; $c++): ?>
                    <td><?= htmlspecialchars((string)($row[$c] ?? '')) ?></td>
                    <?php endfor; ?>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>
<style<?= nonceAttr() ?>>
.sv-page { max-width: 1400px; margin: 0 auto; padding: 0 0 3rem; }

.sv-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.sv-head h2 { margin: 0; color: var(--gray-900); }
.sv-head-sub { color: var(--gray-600); font-size: 0.88rem; margin-top: 0.3rem; }
.sv-head-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

.sv-meta {
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    padding: 0.6rem 0.9rem;
    font-size: 0.85rem;
    color: var(--gray-700);
    margin-bottom: 1rem;
}

.sv-flash {
    display: none;
    padding: 0.55rem 0.9rem;
    border-radius: 6px;
    font-size: 0.85rem;
    margin-bottom: 1rem;
}
.sv-flash.info    { display: block; background: var(--primary-light); color: var(--primary-dark); }
.sv-flash.success { display: block; background: #d1fae5; color: #047857; }
.sv-flash.error   { display: block; background: #fee2e2; color: #b91c1c; }

/* タブ */
.sv-tabs {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
    border-bottom: 2px solid var(--gray-200);
    margin-bottom: 1rem;
}
.sv-tab {
    padding: 0.5rem 1rem;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    font-size: 0.88rem;
    color: var(--gray-600);
    font-family: inherit;
}
.sv-tab:hover { color: var(--primary-dark); }
.sv-tab.active {
    color: var(--primary-dark);
    font-weight: 700;
    border-bottom-color: var(--primary);
}

.sv-panel { display: none; }
.sv-panel.active { display: block; }

.sv-table-wrap {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    overflow: auto;
    max-height: calc(100vh - 16rem);
}
.sv-table {
    border-collapse: collapse;
    width: 100%;
    font-size: 0.85rem;
    white-space: nowrap;
}
.sv-table th, .sv-table td {
    border: 1px solid var(--gray-200);
    padding: 0.4rem 0.7rem;
    text-align: left;
    vertical-align: top;
}
.sv-table thead th {
    position: sticky;
    top: 0;
    background: var(--gray-100);
    color: var(--gray-900);
    font-weight: 700;
    z-index: 2;
}
.sv-table tbody tr:nth-child(even) { background: var(--gray-50); }
.sv-table tbody tr:hover { background: var(--primary-light); }
.sv-rownum {
    background: var(--gray-100) !important;
    color: var(--gray-500);
    font-weight: 600;
    text-align: right !important;
    position: sticky;
    left: 0;
    z-index: 1;
}
.sv-table thead .sv-rownum { z-index: 3; }

.sv-empty {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 3rem 1rem;
    text-align: center;
    color: var(--gray-500);
}
.sv-empty.error { color: #b91c1c; }
</style>

<div class="sv-page">
    <div class="sv-head">
        <div>
            <h2>スプレッドシート閲覧</h2>
            <div class="sv-head-sub">Google スプレッドシートの内容をタブで表示します（admin 専用）。最新化するには「同期」してください。</div>
        </div>
        <div class="sv-head-actions">
            <a href="<?= htmlspecialchars($spreadsheetBaseUrl) ?>" target="_blank" rel="noopener" class="btn btn-secondary" id="svOpenLink">Google で開く</a>
            <button type="button" class="btn btn-primary" id="svSyncBtn">同期</button>
        </div>
    </div>

    <div class="sv-flash" id="svFlash"></div>

    <div class="sv-meta">
        <?php if (is_array($cache)): ?>
            最終同期: <?= htmlspecialchars($cache['synced_at'] ?? '-') ?>
            <?php if (!empty($cache['synced_by'])): ?>（<?= htmlspecialchars($cache['synced_by']) ?>）<?php endif; ?>
            ／ <?= count($sheets) ?> シート
        <?php else: ?>
            まだ同期されていません。「同期」ボタンを押してデータを取得してください。
        <?php endif; ?>
    </div>

    <?php if (!empty($sheets)): ?>
    <div class="sv-tabs" role="tablist">
        <?php foreach ($sheets as $i => $sheet): ?>
        <button type="button" class="sv-tab <?= $i === 0 ? 'active' : '' ?>"
                data-index="<?= $i ?>"
                data-gid="<?= htmlspecialchars((string)($sheet['gid'] ?? '')) ?>"
                role="tab">
            <?= htmlspecialchars((string)($sheet['title'] ?? '(無題)')) ?>
        </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($sheets as $i => $sheet): ?>
    <?php $values = is_array($sheet['values'] ?? null) ? $sheet['values'] : []; ?>
    <div class="sv-panel <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
        <?php if (!empty($sheet['error'])): ?>
            <div class="sv-empty error">このシートの取得に失敗しました：<?= htmlspecialchars((string)$sheet['error']) ?></div>
        <?php elseif (!empty($values)): ?>
            <?php sv_render_table($values); ?>
        <?php else: ?>
            <div class="sv-empty">このシートには表示できるデータがありません。</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script<?= nonceAttr() ?>>
(function () {
    'use strict';
    var CSRF  = <?= json_encode($csrfToken) ?>;
    var BASE  = <?= json_encode($spreadsheetBaseUrl) ?>;
    var btn   = document.getElementById('svSyncBtn');
    var flash = document.getElementById('svFlash');
    var openLink = document.getElementById('svOpenLink');
    var tabs   = Array.prototype.slice.call(document.querySelectorAll('.sv-tab'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('.sv-panel'));

    function showFlash(msg, type) {
        flash.textContent = msg;
        flash.className = 'sv-flash ' + (type || 'success');
    }

    function activateTab(index, gid) {
        tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.index === String(index)); });
        panels.forEach(function (p) { p.classList.toggle('active', p.dataset.index === String(index)); });
        if (openLink) {
            openLink.href = gid ? (BASE + '#gid=' + gid) : BASE;
        }
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            activateTab(t.dataset.index, t.dataset.gid);
        });
    });
    // 初期タブの「Google で開く」リンクを先頭シートの gid に合わせる
    if (tabs.length) {
        activateTab(tabs[0].dataset.index, tabs[0].dataset.gid);
    }

    btn.addEventListener('click', function () {
        btn.disabled = true;
        var original = btn.textContent;
        btn.textContent = '同期中...';
        showFlash('同期中です。スプレッドシートのサイズによっては数秒かかります...', 'info');

        fetch('/api/sheet-viewer-sync.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ action: 'sync' })
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (!j.success) throw new Error(j.error || '同期に失敗しました');
            var errs = (j.data && j.data.errors) || [];
            if (errs.length) {
                showFlash('一部のシートで取得に失敗しました: ' + errs.join(' / '), 'error');
            } else {
                showFlash((j.message || '同期しました') + ' — 画面を更新します...', 'success');
            }
            setTimeout(function () { location.reload(); }, errs.length ? 2000 : 800);
        })
        .catch(function (e) {
            showFlash(e.message || '同期に失敗しました', 'error');
            btn.disabled = false;
            btn.textContent = original;
        });
    });
})();
</script>

<?php require_once '../functions/footer.php'; ?>
