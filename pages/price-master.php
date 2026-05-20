<?php
/**
 * 価格表マスタ管理（管理部専用）
 *
 * 価格表データを直接アプリ内で編集する管理ページ。
 * - 製品選択 → バリアント一覧 → 1件選択 → フォーム編集
 * - 保存・追加・削除を API 経由で実行
 *
 * 営業部側 (sales-tools の pricing タブ) はこのページが保存したデータを読取専用で表示。
 *
 * 権限: admin のみ
 */
$_inHub = defined('IN_HUB_PAGE');
if (!$_inHub) {
    require_once '../api/auth.php';
    require_once '../functions/header.php';
}

// admin チェック
if (!isAdmin()) {
    echo '<div style="padding:2rem;text-align:center;color:var(--gray-600);">管理部のみアクセス可能です</div>';
    if (!$_inHub) require_once '../functions/footer.php';
    return;
}

$csrfToken = generateCsrfToken();

// 製品定義
$ppConfigRaw = @file_get_contents(__DIR__ . '/../config/sales-tools-products.json');
$ppConfig    = $ppConfigRaw ? json_decode($ppConfigRaw, true) : null;
if (!is_array($ppConfig)) $ppConfig = ['products' => [], 'common' => []];
$allItems = array_merge($ppConfig['products'] ?? [], $ppConfig['common'] ?? []);
?>
<style<?= nonceAttr() ?>>
.pm-page { max-width: 1400px; margin: 0 auto; padding: 0 0 3rem; }

.pm-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.pm-head-text h2 { margin: 0; color: var(--gray-900); }
.pm-head-text .pm-head-sub { color: var(--gray-600); font-size: 0.88rem; margin-top: 0.3rem; }
.pm-head-actions {
    display: flex;
    gap: 0.5rem;
}

.pm-layout {
    display: grid;
    grid-template-columns: 220px 320px 1fr;
    gap: 1rem;
    align-items: start;
}
@media (max-width: 1000px) { .pm-layout { grid-template-columns: 1fr; } }

/* 左: 製品セレクター */
.pm-products {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 0.6rem;
    position: sticky;
    top: 1rem;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
}
.pm-product-btn {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    width: 100%;
    padding: 0.55rem 0.7rem;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 7px;
    cursor: pointer;
    text-align: left;
    color: var(--gray-700);
    font-size: 0.88rem;
    transition: all 0.12s;
    margin-bottom: 0.15rem;
}
.pm-product-btn:hover { background: var(--gray-50); }
.pm-product-btn.active {
    background: var(--primary-light);
    border-color: var(--primary);
    color: var(--primary-dark);
    font-weight: 600;
}
.pm-product-count {
    background: var(--gray-100);
    color: var(--gray-600);
    padding: 0.1rem 0.45rem;
    border-radius: 9px;
    font-size: 0.7rem;
    margin-left: auto;
    font-weight: 700;
}
.pm-product-btn.active .pm-product-count {
    background: var(--primary);
    color: white;
}

/* 中央: バリアント一覧 */
.pm-variants {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 0.6rem;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
}
.pm-variants-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.4rem 0.6rem;
    margin-bottom: 0.5rem;
    border-bottom: 1px solid var(--gray-200);
}
.pm-variants-title { font-size: 0.85rem; font-weight: 700; color: var(--gray-900); }
.pm-variants-list { display: flex; flex-direction: column; gap: 0.3rem; }
.pm-variant-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.55rem 0.7rem;
    background: transparent;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    cursor: pointer;
    text-align: left;
    color: var(--gray-800);
    font-size: 0.82rem;
    transition: all 0.12s;
}
.pm-variant-btn:hover { border-color: var(--primary); }
.pm-variant-btn.active {
    border-color: var(--primary);
    background: var(--primary-light);
    color: var(--primary-dark);
    font-weight: 600;
}

/* 右: 編集フォーム */
.pm-editor {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 1.5rem 1.75rem;
}
.pm-empty {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--gray-500);
}
.pm-editor-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 1.5rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid var(--gray-200);
}
.pm-editor-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--gray-900);
}
.pm-section { margin-bottom: 1.5rem; }
.pm-section-title {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--gray-600);
    font-weight: 700;
    margin-bottom: 0.6rem;
}
.pm-form-row {
    display: grid;
    grid-template-columns: 140px 1fr 80px;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.4rem;
}
.pm-form-row label {
    font-size: 0.82rem;
    color: var(--gray-700);
    font-weight: 500;
}
.pm-form-row input {
    padding: 0.45rem 0.7rem;
    border: 1px solid var(--gray-300);
    border-radius: 6px;
    font-size: 0.88rem;
    font-family: inherit;
    width: 100%;
}
.pm-form-row input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.pm-form-row input.dirty {
    border-color: #f59e0b;
    background: #fffbeb;
}
.pm-form-row .pm-row-delete {
    background: transparent;
    border: none;
    color: var(--gray-400);
    cursor: pointer;
    font-size: 0.85rem;
    padding: 0.3rem;
}
.pm-form-row .pm-row-delete:hover { color: #b91c1c; }

.pm-tier-group {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 0.85rem 1rem;
    margin-bottom: 0.65rem;
}
.pm-tier-group-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}
.pm-tier-badge {
    display: inline-block;
    padding: 0.2rem 0.65rem;
    border-radius: 5px;
    font-weight: 700;
    font-size: 0.74rem;
}
.pm-tier-badge.t-S { background: #fef3c7; color: #b45309; }
.pm-tier-badge.t-A { background: #dbeafe; color: #1e40af; }
.pm-tier-badge.t-B { background: #d1fae5; color: #047857; }
.pm-tier-badge.t-noop { background: var(--gray-200); color: var(--gray-600); }

.pm-editor-actions {
    display: flex;
    gap: 0.6rem;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--gray-200);
}
.pm-btn {
    padding: 0.6rem 1.1rem;
    border-radius: 7px;
    border: none;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
}
.pm-btn.primary { background: var(--primary); color: white; }
.pm-btn.primary:hover { background: var(--primary-dark); }
.pm-btn.primary:disabled { opacity: 0.5; cursor: not-allowed; }
.pm-btn.secondary { background: white; color: var(--gray-700); border: 1px solid var(--gray-300); }
.pm-btn.secondary:hover { border-color: var(--primary); color: var(--primary); }
.pm-btn.danger { background: white; color: #b91c1c; border: 1px solid #fecaca; margin-left: auto; }
.pm-btn.danger:hover { background: #fee2e2; }

.pm-flash {
    display: none;
    padding: 0.5rem 0.85rem;
    border-radius: 6px;
    font-size: 0.85rem;
    margin-bottom: 0.85rem;
}
.pm-flash.success { display: block; background: #d1fae5; color: #047857; }
.pm-flash.error   { display: block; background: #fee2e2; color: #b91c1c; }
</style>

<div class="pm-page">
    <?php if (!$_inHub) { require_once __DIR__ . '/../functions/hub-tabs.php'; renderHubTabs('master'); } ?>
    <div class="pm-head">
        <div class="pm-head-text">
            <div class="pm-head-sub">管理部のみ編集可能。営業ツールの価格表（商品ページ）はこのデータを読込表示します。</div>
        </div>
        <div class="pm-head-actions">
            <input type="file" id="pmXlsxFile" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" style="display:none;">
            <button type="button" class="pm-btn primary" id="pmUploadBtn">
                <span style="display:inline-flex;align-items:center;gap:0.4rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    xlsx を取込む
                </span>
            </button>
            <a href="/pages/sales-tools.php?tab=pricing" class="pm-btn secondary" style="text-decoration:none;display:inline-block;">営業ツールで表示を確認 →</a>
        </div>
    </div>

    <div class="pm-flash" id="pmFlash"></div>

    <div class="pm-layout">
        <!-- 左: 製品 -->
        <aside class="pm-products" id="pmProducts">
            <div style="padding:0.6rem; font-size:0.74rem; color:var(--gray-500); font-weight:700; text-transform:uppercase;">製品</div>
        </aside>

        <!-- 中央: バリアント一覧 -->
        <aside class="pm-variants" id="pmVariantPane">
            <div class="pm-empty">製品を選択してください</div>
        </aside>

        <!-- 右: 編集フォーム -->
        <section class="pm-editor" id="pmEditor">
            <div class="pm-empty">バリアントを選択してください</div>
        </section>
    </div>
</div>

<script<?= nonceAttr() ?>>
(function(){
    'use strict';
    var CSRF = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>';
    var PRODUCTS = <?= json_encode($allItems, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    // match を RegExp に変換
    PRODUCTS.forEach(function(p){
        try { p._regex = new RegExp(p.match); } catch(e) { p._regex = /$.^/; }
    });

    var allData = null;            // 全データ
    var currentProductId = null;   // 選択中の製品 id
    var currentSheetTitle = null;  // 選択中のシート
    var currentRowKey = null;      // 選択中の row key
    var dirty = false;             // 編集中フラグ

    function escapeHtml(s){
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function flash(msg, type) {
        var el = document.getElementById('pmFlash');
        el.className = 'pm-flash ' + (type || 'success');
        el.textContent = msg;
        setTimeout(function(){ el.className = 'pm-flash'; }, 3500);
    }

    // ----- データロード -----
    function loadData() {
        return fetch('/api/price-master.php?action=get', { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (!j.success) throw new Error(j.error || '取得失敗');
                allData = j.data && j.data.data;
                renderProducts();
            });
    }

    function findMatchingSheets(item) {
        if (!allData || !allData.sheets) return [];
        return allData.sheets.filter(function(s){ return item._regex.test(s.title || ''); });
    }

    // ----- 製品一覧描画 -----
    function renderProducts() {
        var el = document.getElementById('pmProducts');
        var btns = PRODUCTS.map(function(p){
            var sheets = findMatchingSheets(p);
            var count = sheets.reduce(function(sum, s){ return sum + ((s.normalized && s.normalized.rows && s.normalized.rows.length) || 0); }, 0);
            return '<button type="button" class="pm-product-btn ' + (p.id === currentProductId ? 'active' : '') + '" data-id="' + escapeHtml(p.id) + '">' +
                '<span>' + escapeHtml(p.name) + '</span>' +
                '<span class="pm-product-count">' + count + '</span>' +
            '</button>';
        }).join('');
        el.innerHTML = '<div style="padding:0.6rem; font-size:0.74rem; color:var(--gray-500); font-weight:700; text-transform:uppercase;">製品</div>' + btns;
        el.querySelectorAll('.pm-product-btn').forEach(function(b){
            b.addEventListener('click', function(){
                if (dirty && !confirm('編集中の変更を破棄しますか？')) return;
                currentProductId = b.getAttribute('data-id');
                currentSheetTitle = null;
                currentRowKey = null;
                dirty = false;
                renderProducts();
                renderVariants();
                renderEditor();
            });
        });
    }

    // ----- バリアント一覧描画 -----
    function renderVariants() {
        var pane = document.getElementById('pmVariantPane');
        if (!currentProductId) {
            pane.innerHTML = '<div class="pm-empty">製品を選択してください</div>';
            return;
        }
        var item = PRODUCTS.find(function(p){ return p.id === currentProductId; });
        if (!item) return;
        var sheets = findMatchingSheets(item);
        var html = '<div class="pm-variants-head">' +
            '<div class="pm-variants-title">' + escapeHtml(item.name) + ' のバリアント</div>' +
        '</div>';

        if (sheets.length === 0) {
            html += '<div class="pm-empty">該当シートなし</div>';
        } else {
            sheets.forEach(function(sheet){
                html += '<div style="font-size:0.72rem; color:var(--gray-500); padding:0.6rem 0.6rem 0.3rem;">' + escapeHtml(sheet.title) + '</div>';
                html += '<div class="pm-variants-list">';
                var rows = (sheet.normalized && sheet.normalized.rows) || [];
                rows.forEach(function(r){
                    var isActive = (currentSheetTitle === sheet.title && currentRowKey === r.key);
                    html += '<button type="button" class="pm-variant-btn ' + (isActive ? 'active' : '') + '" data-sheet="' + escapeHtml(sheet.title) + '" data-key="' + escapeHtml(r.key) + '">' +
                        escapeHtml(r.display_name || '(無題)') +
                    '</button>';
                });
                html += '</div>';
                html += '<button type="button" class="pm-btn secondary" style="margin:0.5rem 0.6rem; padding:0.4rem 0.7rem; font-size:0.78rem;" data-add-sheet="' + escapeHtml(sheet.title) + '">+ 新規バリアント</button>';
            });
        }
        pane.innerHTML = html;
        pane.querySelectorAll('.pm-variant-btn').forEach(function(b){
            b.addEventListener('click', function(){
                if (dirty && !confirm('編集中の変更を破棄しますか？')) return;
                currentSheetTitle = b.getAttribute('data-sheet');
                currentRowKey    = b.getAttribute('data-key');
                dirty = false;
                renderVariants();
                renderEditor();
            });
        });
        pane.querySelectorAll('[data-add-sheet]').forEach(function(b){
            b.addEventListener('click', function(){
                addVariant(b.getAttribute('data-add-sheet'));
            });
        });
    }

    function findVariant() {
        if (!allData || !currentSheetTitle || !currentRowKey) return null;
        var sheet = allData.sheets.find(function(s){ return s.title === currentSheetTitle; });
        if (!sheet) return null;
        var rows = (sheet.normalized && sheet.normalized.rows) || [];
        return rows.find(function(r){ return r.key === currentRowKey; }) || null;
    }

    // ----- 編集フォーム描画 -----
    function renderEditor() {
        var el = document.getElementById('pmEditor');
        var variant = findVariant();
        if (!variant) {
            el.innerHTML = '<div class="pm-empty">左でバリアントを選択してください</div>';
            return;
        }

        // 価格を層ごとにグループ化
        var pricesByTier = { 'S': [], 'A': [], 'B': [], 'C': [], 'D': [], '': [] };
        (variant.prices || []).forEach(function(p){
            var g = p.group || '';
            if (!pricesByTier[g]) pricesByTier[g] = [];
            pricesByTier[g].push(p);
        });

        var html =
            '<div class="pm-editor-head">' +
                '<div class="pm-editor-title">' + escapeHtml(variant.display_name || '(無題)') + '</div>' +
                '<div style="font-size:0.78rem;color:var(--gray-500);">' + escapeHtml(currentSheetTitle) + '</div>' +
            '</div>' +

            '<div class="pm-section">' +
                '<div class="pm-section-title">基本情報</div>' +
                '<div class="pm-form-row">' +
                    '<label>表示名</label>' +
                    '<input type="text" id="pmDisplayName" value="' + escapeHtml(variant.display_name || '') + '">' +
                    '<span></span>' +
                '</div>' +
            '</div>' +

            '<div class="pm-section">' +
                '<div class="pm-section-title">仕様（属性）</div>' +
                '<div id="pmAttrRows">' +
                    (variant.attributes || []).map(function(a, i){
                        return '<div class="pm-form-row" data-attr-idx="' + i + '">' +
                            '<input type="text" class="pm-attr-label" value="' + escapeHtml(a.label || '') + '">' +
                            '<input type="text" class="pm-attr-value" value="' + escapeHtml(a.value || '') + '">' +
                            '<button type="button" class="pm-row-delete" data-action="del-attr" data-idx="' + i + '" title="この属性を削除">✕</button>' +
                        '</div>';
                    }).join('') +
                '</div>' +
                '<button type="button" class="pm-btn secondary" style="margin-top:0.4rem; padding:0.4rem 0.7rem; font-size:0.78rem;" id="pmAddAttr">+ 属性追加</button>' +
            '</div>';

        // 価格セクション
        html += '<div class="pm-section"><div class="pm-section-title">価格</div>';
        ['S','A','B','C','D'].forEach(function(g){
            if (!pricesByTier[g] || pricesByTier[g].length === 0) return;
            var tierName = { S:'上位ディーラー', A:'標準ディーラー', B:'新規開拓', C:'C層', D:'D層' }[g];
            html += '<div class="pm-tier-group">' +
                '<div class="pm-tier-group-head">' +
                    '<span class="pm-tier-badge t-' + g + '">' + escapeHtml(g) + '層 / ' + escapeHtml(tierName) + '</span>' +
                '</div>';
            pricesByTier[g].forEach(function(p, i){
                html += '<div class="pm-form-row" data-tier="' + g + '" data-price-label="' + escapeHtml(p.label) + '">' +
                    '<input type="text" class="pm-price-label" value="' + escapeHtml(p.label || '') + '">' +
                    '<input type="number" class="pm-price-amount" value="' + (p.amount || '') + '" step="100">' +
                    '<button type="button" class="pm-row-delete" data-action="del-price" data-tier="' + g + '" data-label="' + escapeHtml(p.label) + '" title="この価格を削除">✕</button>' +
                '</div>';
            });
            html += '</div>';
        });
        // group なし
        if (pricesByTier[''] && pricesByTier[''].length > 0) {
            html += '<div class="pm-tier-group">' +
                '<div class="pm-tier-group-head"><span class="pm-tier-badge t-noop">層なし</span></div>';
            pricesByTier[''].forEach(function(p){
                html += '<div class="pm-form-row" data-tier="" data-price-label="' + escapeHtml(p.label) + '">' +
                    '<input type="text" class="pm-price-label" value="' + escapeHtml(p.label || '') + '">' +
                    '<input type="number" class="pm-price-amount" value="' + (p.amount || '') + '" step="100">' +
                    '<button type="button" class="pm-row-delete" data-action="del-price" data-tier="" data-label="' + escapeHtml(p.label) + '" title="この価格を削除">✕</button>' +
                '</div>';
            });
            html += '</div>';
        }
        html += '</div>';

        // アクション
        html += '<div class="pm-editor-actions">' +
            '<button type="button" class="pm-btn primary" id="pmSave">変更を保存</button>' +
            '<button type="button" class="pm-btn secondary" id="pmReload">変更を破棄して再読込</button>' +
            '<button type="button" class="pm-btn danger" id="pmDelete">このバリアントを削除</button>' +
        '</div>';

        el.innerHTML = html;
        bindEditor();
    }

    function bindEditor() {
        // dirty 検出
        document.querySelectorAll('#pmEditor input').forEach(function(inp){
            inp.addEventListener('input', function(){
                dirty = true;
                inp.classList.add('dirty');
            });
        });

        // 属性削除
        document.querySelectorAll('#pmEditor [data-action="del-attr"]').forEach(function(b){
            b.addEventListener('click', function(){
                var row = b.closest('.pm-form-row');
                if (row) row.remove();
                dirty = true;
            });
        });
        // 価格削除
        document.querySelectorAll('#pmEditor [data-action="del-price"]').forEach(function(b){
            b.addEventListener('click', function(){
                var row = b.closest('.pm-form-row');
                if (row) row.remove();
                dirty = true;
            });
        });
        // 属性追加
        var addAttrBtn = document.getElementById('pmAddAttr');
        if (addAttrBtn) addAttrBtn.addEventListener('click', function(){
            var container = document.getElementById('pmAttrRows');
            var div = document.createElement('div');
            div.className = 'pm-form-row';
            div.innerHTML =
                '<input type="text" class="pm-attr-label" placeholder="ラベル (例: インチ数)">' +
                '<input type="text" class="pm-attr-value" placeholder="値 (例: 81)">' +
                '<button type="button" class="pm-row-delete" data-action="del-attr">✕</button>';
            container.appendChild(div);
            div.querySelector('[data-action="del-attr"]').addEventListener('click', function(){
                div.remove();
                dirty = true;
            });
            div.querySelectorAll('input').forEach(function(inp){
                inp.addEventListener('input', function(){ dirty = true; });
            });
            dirty = true;
        });

        // 保存
        document.getElementById('pmSave').addEventListener('click', saveVariant);
        // 再読込
        document.getElementById('pmReload').addEventListener('click', function(){
            if (dirty && !confirm('編集中の変更を破棄しますか？')) return;
            dirty = false;
            loadData().then(renderEditor);
        });
        // 削除
        document.getElementById('pmDelete').addEventListener('click', deleteVariant);
    }

    function collectFormData() {
        var attributes = [];
        document.querySelectorAll('#pmAttrRows .pm-form-row').forEach(function(row){
            var label = row.querySelector('.pm-attr-label').value.trim();
            var value = row.querySelector('.pm-attr-value').value.trim();
            if (label && value) attributes.push({ label: label, value: value });
        });
        var prices = [];
        document.querySelectorAll('#pmEditor .pm-tier-group').forEach(function(g){
            g.querySelectorAll('.pm-form-row').forEach(function(row){
                var tier = row.getAttribute('data-tier') || '';
                var label = row.querySelector('.pm-price-label').value.trim();
                var amount = parseInt(row.querySelector('.pm-price-amount').value, 10);
                if (label && !isNaN(amount) && amount > 0) {
                    prices.push({ group: tier, label: label, amount: amount });
                }
            });
        });
        return {
            display_name: document.getElementById('pmDisplayName').value.trim(),
            attributes: attributes,
            prices: prices
        };
    }

    function saveVariant() {
        var data = collectFormData();
        var btn = document.getElementById('pmSave');
        btn.disabled = true;
        btn.textContent = '保存中...';
        fetch('/api/price-master.php?action=save_variant', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(Object.assign({
                csrf_token: CSRF,
                action: 'save_variant',
                sheet_title: currentSheetTitle,
                row_key: currentRowKey
            }, data))
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '保存失敗');
            dirty = false;
            flash(j.message || '保存しました', 'success');
            return loadData().then(function(){
                renderVariants();
                renderEditor();
            });
        })
        .catch(function(e){ flash(e.message, 'error'); })
        .finally(function(){
            btn.disabled = false;
            btn.textContent = '変更を保存';
        });
    }

    function deleteVariant() {
        if (!confirm('このバリアントを削除しますか？ この操作は取り消せません。')) return;
        fetch('/api/price-master.php?action=delete_variant', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ csrf_token: CSRF, action: 'delete_variant', sheet_title: currentSheetTitle, row_key: currentRowKey })
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '削除失敗');
            dirty = false;
            currentRowKey = null;
            flash('削除しました', 'success');
            return loadData().then(function(){
                renderProducts();
                renderVariants();
                renderEditor();
            });
        })
        .catch(function(e){ flash(e.message, 'error'); });
    }

    function addVariant(sheetTitle) {
        fetch('/api/price-master.php?action=add_variant', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ csrf_token: CSRF, action: 'add_variant', sheet_title: sheetTitle, display_name: '新規バリアント' })
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '追加失敗');
            currentSheetTitle = sheetTitle;
            currentRowKey = j.data.variant.key;
            flash('追加しました', 'success');
            return loadData().then(function(){
                renderProducts();
                renderVariants();
                renderEditor();
            });
        })
        .catch(function(e){ flash(e.message, 'error'); });
    }

    // ----- xlsx 取込み -----
    var uploadBtn = document.getElementById('pmUploadBtn');
    var fileInput = document.getElementById('pmXlsxFile');
    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', function(){
            if (!confirm('既存の価格表データを全て上書きします。よろしいですか？\n（既存データは backup として保存されます）')) return;
            fileInput.click();
        });
        fileInput.addEventListener('change', function(){
            var file = fileInput.files[0];
            if (!file) return;
            uploadBtn.disabled = true;
            var originalLabel = uploadBtn.innerHTML;
            uploadBtn.innerHTML = '取込み中...';
            var fd = new FormData();
            fd.append('file', file);
            fd.append('csrf_token', CSRF);
            fetch('/api/price-master.php?action=upload_xlsx', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-Token': CSRF },
                body: fd
            })
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (!j.success) throw new Error(j.error || '取込み失敗');
                var sum = (j.data && j.data.summary) ? j.data.summary : [];
                var msg = (j.message || '取込み完了') + '\n\n' +
                    sum.map(function(s){ return ' - ' + s.title + ': ' + s.rows + ' 件 [' + s.pattern + ']'; }).join('\n');
                flash('取込み完了: ' + (j.data.sheet_count || 0) + 'シート', 'success');
                alert(msg);
                dirty = false;
                currentProductId = null;
                currentSheetTitle = null;
                currentRowKey = null;
                return loadData().then(function(){
                    renderVariants();
                    renderEditor();
                });
            })
            .catch(function(e){ flash('取込み失敗: ' + e.message, 'error'); })
            .finally(function(){
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = originalLabel;
                fileInput.value = '';
            });
        });
    }

    // 起動
    loadData();
})();
</script>
