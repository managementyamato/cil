<?php
/**
 * 価格表ページ
 *
 * 顧客層（ティア）ごとにタブ切り替えで製品価格を一覧表示・編集
 * - 閲覧: 全員 / 編集: 製品技術部+管理部 / 削除: 管理部
 */
require_once '../api/auth.php';
require_once '../functions/header.php';

$canEditPage = canEditCurrentPage() && canEdit();
$canDel = canDelete();
?>

<style<?= nonceAttr() ?>>
    .tier-tabs {
        display: flex;
        gap: 0;
        border-bottom: 2px solid var(--gray-200);
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }
    .tier-tab {
        padding: 0.6rem 1.2rem;
        border: none;
        background: none;
        cursor: pointer;
        font-size: 0.9rem;
        color: var(--gray-600);
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: color 0.15s, border-color 0.15s;
        white-space: nowrap;
    }
    .tier-tab:hover { color: var(--gray-900); }
    .tier-tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
        font-weight: 600;
    }
    .price-table-wrap { overflow-x: auto; }
    .price-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 500px;
    }
    .price-table th,
    .price-table td {
        padding: 0.55rem 0.8rem;
        border: 1px solid var(--gray-200);
        font-size: 0.85rem;
        text-align: left;
    }
    .price-table thead th {
        background: var(--gray-50);
        font-weight: 600;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .price-table tbody tr:hover { background: #f8fafc; }
    .price-input {
        width: 120px;
        padding: 0.3rem 0.5rem;
        border: 1px solid var(--gray-300);
        border-radius: 4px;
        font-size: 0.85rem;
        text-align: right;
    }
    .price-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(52,152,219,0.15);
    }
    .price-display {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .memo-input {
        width: 100%;
        max-width: 200px;
        padding: 0.3rem 0.5rem;
        border: 1px solid var(--gray-300);
        border-radius: 4px;
        font-size: 0.82rem;
    }
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--gray-500);
    }
    .empty-state p { margin: 0.5rem 0; }
    .product-number { color: var(--gray-500); font-size: 0.82rem; }
    .product-category {
        display: inline-block;
        padding: 0.1rem 0.4rem;
        background: var(--gray-100);
        border-radius: 3px;
        font-size: 0.75rem;
        color: var(--gray-600);
    }
    .save-bar {
        position: sticky;
        bottom: 0;
        background: white;
        border-top: 1px solid var(--gray-200);
        padding: 0.75rem 1rem;
        display: none;
        justify-content: flex-end;
        gap: 0.75rem;
        align-items: center;
        box-shadow: 0 -2px 8px rgba(0,0,0,0.06);
        z-index: 10;
    }
    .save-bar.visible { display: flex; }
    .change-count { font-size: 0.85rem; color: var(--gray-600); }
</style>

<div class="page-container">

    <div class="page-header">
        <h2>価格表</h2>
        <div class="page-header-actions">
            <?php if ($canEditPage): ?>
            <button type="button" class="btn btn-outline" data-action="openProductModal">製品管理</button>
            <button type="button" class="btn btn-primary" data-action="openTierModal">顧客層管理</button>
            <?php endif; ?>
        </div>
    </div>

    <div id="alertContainer"></div>

    <!-- 顧客層タブ -->
    <div class="tier-tabs" id="tierTabs"></div>

    <!-- 価格テーブル -->
    <div class="price-table-wrap">
        <table class="price-table" id="priceTable">
            <thead id="priceTableHead"></thead>
            <tbody id="priceTableBody"></tbody>
        </table>
    </div>

    <div id="emptyState" class="empty-state" style="display:none;">
        <p>顧客層が登録されていません</p>
        <?php if ($canEditPage): ?>
        <p>「顧客層管理」から層を追加してください</p>
        <?php endif; ?>
    </div>

    <!-- 保存バー -->
    <?php if ($canEditPage): ?>
    <div class="save-bar" id="saveBar">
        <span class="change-count" id="changeCount"></span>
        <button type="button" class="btn btn-secondary btn-sm" data-action="cancelChanges">取り消し</button>
        <button type="button" class="btn btn-primary btn-sm" id="btnSavePrices" data-action="savePrices">価格を保存</button>
    </div>
    <?php endif; ?>

</div>

<!-- 顧客層管理モーダル -->
<div id="tierModal" class="modal">
    <div class="modal-content" style="max-width:560px;">
        <div class="modal-header">
            <h3>顧客層管理</h3>
            <button type="button" class="close" data-close-modal="tierModal">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom:1rem;">
                <table class="data-table" style="font-size:0.85rem;">
                    <thead><tr><th>層名</th><th>説明</th><th style="width:100px;">操作</th></tr></thead>
                    <tbody id="tierListBody"></tbody>
                </table>
            </div>
            <hr style="margin:1rem 0;">
            <h4 style="font-size:0.9rem;margin-bottom:0.5rem;">新規追加</h4>
            <form id="tierAddForm">
                <?= csrfTokenField() ?>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;">
                    <div class="form-group" style="flex:1;min-width:120px;margin-bottom:0;">
                        <label for="tierName" style="font-size:0.82rem;">層名 <span class="required">*</span></label>
                        <input type="text" id="tierName" class="form-input" required placeholder="例: A層">
                    </div>
                    <div class="form-group" style="flex:2;min-width:150px;margin-bottom:0;">
                        <label for="tierDesc" style="font-size:0.82rem;">説明</label>
                        <input type="text" id="tierDesc" class="form-input" placeholder="例: 大口顧客向け">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">追加</button>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal="tierModal">閉じる</button>
        </div>
    </div>
</div>

<!-- 顧客層編集モーダル -->
<div id="tierEditModal" class="modal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h3>顧客層編集</h3>
            <button type="button" class="close" data-close-modal="tierEditModal">&times;</button>
        </div>
        <form id="tierEditForm">
            <?= csrfTokenField() ?>
            <input type="hidden" id="tierEditId">
            <div class="modal-body">
                <div class="form-group">
                    <label for="tierEditName">層名 <span class="required">*</span></label>
                    <input type="text" id="tierEditName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="tierEditDesc">説明</label>
                    <input type="text" id="tierEditDesc" class="form-input">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="tierEditModal">キャンセル</button>
                <button type="submit" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 製品管理モーダル -->
<div id="productModal" class="modal">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h3>製品管理</h3>
            <button type="button" class="close" data-close-modal="productModal">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom:1rem;max-height:350px;overflow-y:auto;">
                <table class="data-table" style="font-size:0.85rem;">
                    <thead><tr><th>品番</th><th>品名</th><th>カテゴリ</th><th>単位</th><th style="width:100px;">操作</th></tr></thead>
                    <tbody id="productListBody"></tbody>
                </table>
            </div>
            <hr style="margin:1rem 0;">
            <h4 style="font-size:0.9rem;margin-bottom:0.5rem;">新規追加</h4>
            <form id="productAddForm">
                <?= csrfTokenField() ?>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;">
                    <div class="form-group" style="flex:1;min-width:80px;margin-bottom:0;">
                        <label style="font-size:0.82rem;">品番</label>
                        <input type="text" id="prodNumber" class="form-input" placeholder="例: YG-001">
                    </div>
                    <div class="form-group" style="flex:2;min-width:120px;margin-bottom:0;">
                        <label style="font-size:0.82rem;">品名 <span class="required">*</span></label>
                        <input type="text" id="prodName" class="form-input" required placeholder="品名">
                    </div>
                    <div class="form-group" style="flex:1;min-width:80px;margin-bottom:0;">
                        <label style="font-size:0.82rem;">カテゴリ</label>
                        <input type="text" id="prodCategory" class="form-input" placeholder="例: LED">
                    </div>
                    <div class="form-group" style="flex:0.5;min-width:60px;margin-bottom:0;">
                        <label style="font-size:0.82rem;">単位</label>
                        <input type="text" id="prodUnit" class="form-input" placeholder="例: 台">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">追加</button>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal="productModal">閉じる</button>
        </div>
    </div>
</div>

<!-- 製品編集モーダル -->
<div id="productEditModal" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header">
            <h3>製品編集</h3>
            <button type="button" class="close" data-close-modal="productEditModal">&times;</button>
        </div>
        <form id="productEditForm">
            <?= csrfTokenField() ?>
            <input type="hidden" id="prodEditId">
            <div class="modal-body">
                <div class="form-group">
                    <label for="prodEditNumber">品番</label>
                    <input type="text" id="prodEditNumber" class="form-input">
                </div>
                <div class="form-group">
                    <label for="prodEditName">品名 <span class="required">*</span></label>
                    <input type="text" id="prodEditName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="prodEditCategory">カテゴリ</label>
                    <input type="text" id="prodEditCategory" class="form-input">
                </div>
                <div class="form-group">
                    <label for="prodEditUnit">単位</label>
                    <input type="text" id="prodEditUnit" class="form-input">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="productEditModal">キャンセル</button>
                <button type="submit" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 削除確認モーダル -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>削除確認</h3>
            <button type="button" class="close" data-close-modal="deleteModal">&times;</button>
        </div>
        <div class="modal-body">
            <p id="deleteMessage">この項目を削除しますか?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal="deleteModal">キャンセル</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">削除</button>
        </div>
    </div>
</div>

<script<?= nonceAttr() ?>>
(function() {
    'use strict';

    var csrfToken = '<?= generateCsrfToken() ?>';
    var canEdit = <?= $canEditPage ? 'true' : 'false' ?>;
    var canDel  = <?= $canDel ? 'true' : 'false' ?>;

    var tiers = [];
    var products = [];
    var prices = [];
    var activeTierId = null;
    var pendingChanges = {};
    var deleteCallback = null;

    function esc(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function formatPrice(n) {
        if (n == null || n === '') return '';
        return Number(n).toLocaleString('ja-JP');
    }

    function apiPost(endpoint, payload) {
        return fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload)
        }).then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) throw new Error(data.error || '処理に失敗しました');
            return data;
        });
    }

    function apiGet(endpoint) {
        return fetch(endpoint, { headers: { 'X-CSRF-Token': csrfToken } })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) throw new Error(data.error || '取得に失敗しました');
            return data;
        });
    }

    function showAlert(msg, type) {
        var c = document.getElementById('alertContainer');
        if (!c) return;
        var d = document.createElement('div');
        d.className = 'alert alert-' + (type || 'success');
        d.textContent = msg;
        c.appendChild(d);
        setTimeout(function() { d.remove(); }, 4000);
    }

    function setLoading(btn, loading, text) {
        if (!btn) return;
        btn.disabled = loading;
        if (loading) {
            btn.dataset.origText = btn.textContent;
            btn.textContent = text || '処理中...';
        } else {
            btn.textContent = btn.dataset.origText || btn.textContent;
        }
    }

    function loadTiers() {
        return apiGet('/api/price-list-api.php?action=tier_list').then(function(res) {
            tiers = res.data || [];
        });
    }

    function loadProducts() {
        return apiGet('/api/price-list-api.php?action=product_list').then(function(res) {
            products = res.data || [];
        });
    }

    function loadPrices(tierId) {
        return apiGet('/api/price-list-api.php?action=price_list&tier_id=' + encodeURIComponent(tierId)).then(function(res) {
            prices = res.data || [];
        });
    }

    function renderTabs() {
        var tabsEl = document.getElementById('tierTabs');
        var empty = document.getElementById('emptyState');
        var table = document.getElementById('priceTable');

        if (tiers.length === 0) {
            tabsEl.innerHTML = '';
            empty.style.display = 'block';
            table.style.display = 'none';
            return;
        }
        empty.style.display = 'none';
        table.style.display = '';

        if (!activeTierId || !tiers.find(function(t) { return t.id === activeTierId; })) {
            activeTierId = tiers[0].id;
        }

        tabsEl.innerHTML = tiers.map(function(t) {
            return '<button class="tier-tab' + (t.id === activeTierId ? ' active' : '') + '" data-tier-id="' + esc(t.id) + '">'
                + esc(t.name) + '</button>';
        }).join('');
    }

    function renderPriceTable() {
        var head = document.getElementById('priceTableHead');
        var body = document.getElementById('priceTableBody');
        var activeTier = tiers.find(function(t) { return t.id === activeTierId; });
        var tierLabel = activeTier ? activeTier.name : '';

        var headHtml = '<tr><th>品番</th><th>品名</th><th>カテゴリ</th>';
        headHtml += '<th style="text-align:right;">単価 (' + esc(tierLabel) + ')</th>';
        if (canEdit) headHtml += '<th>備考</th>';
        headHtml += '</tr>';
        head.innerHTML = headHtml;

        if (products.length === 0) {
            body.innerHTML = '<tr><td colspan="' + (canEdit ? 5 : 4) + '" class="text-center text-muted" style="padding:2rem;">製品が登録されていません。「製品管理」から追加してください。</td></tr>';
            return;
        }

        var priceMap = {};
        prices.forEach(function(p) { priceMap[p.product_id] = p; });

        var bodyHtml = '';
        products.forEach(function(prod) {
            var pr = priceMap[prod.id] || {};
            var changed = pendingChanges[prod.id];
            var currentPrice = changed ? changed.price : (pr.price != null ? pr.price : '');
            var currentMemo  = changed ? changed.memo : (pr.memo || '');

            bodyHtml += '<tr>';
            bodyHtml += '<td><span class="product-number">' + esc(prod.product_number) + '</span></td>';
            bodyHtml += '<td>' + esc(prod.product_name) + '</td>';
            bodyHtml += '<td>' + (prod.category ? '<span class="product-category">' + esc(prod.category) + '</span>' : '') + '</td>';

            if (canEdit) {
                bodyHtml += '<td><input type="text" class="price-input" data-product-id="' + esc(prod.id) + '" data-field="price" value="' + esc(currentPrice) + '" inputmode="numeric" placeholder="0"></td>';
                bodyHtml += '<td><input type="text" class="memo-input" data-product-id="' + esc(prod.id) + '" data-field="memo" value="' + esc(currentMemo) + '" placeholder="備考"></td>';
            } else {
                bodyHtml += '<td class="price-display">' + (currentPrice !== '' ? '&yen;' + formatPrice(currentPrice) : '-') + '</td>';
            }
            bodyHtml += '</tr>';
        });
        body.innerHTML = bodyHtml;
    }

    function trackChange(productId, field, value) {
        if (!pendingChanges[productId]) {
            var pr = prices.find(function(p) { return p.product_id === productId; }) || {};
            pendingChanges[productId] = {
                price: pr.price != null ? String(pr.price) : '',
                memo: pr.memo || '',
                _origPrice: pr.price != null ? String(pr.price) : '',
                _origMemo: pr.memo || ''
            };
        }
        pendingChanges[productId][field] = value;

        var ch = pendingChanges[productId];
        if (ch.price === ch._origPrice && ch.memo === ch._origMemo) {
            delete pendingChanges[productId];
        }
        updateSaveBar();
    }

    function updateSaveBar() {
        var bar = document.getElementById('saveBar');
        if (!bar) return;
        var count = Object.keys(pendingChanges).length;
        if (count > 0) {
            bar.classList.add('visible');
            document.getElementById('changeCount').textContent = count + '件の変更';
        } else {
            bar.classList.remove('visible');
        }
    }

    function init() {
        Promise.all([loadTiers(), loadProducts()]).then(function() {
            renderTabs();
            if (activeTierId) {
                return loadPrices(activeTierId);
            }
        }).then(function() {
            renderPriceTable();
        }).catch(function(e) {
            showAlert('データの読み込みに失敗しました: ' + e.message, 'danger');
        });
    }

    function switchTier(tierId) {
        if (Object.keys(pendingChanges).length > 0) {
            if (!confirm('未保存の変更があります。切り替えると変更が失われます。よろしいですか?')) {
                return;
            }
        }
        pendingChanges = {};
        updateSaveBar();
        activeTierId = tierId;
        renderTabs();
        loadPrices(tierId).then(function() {
            renderPriceTable();
        }).catch(function() {
            showAlert('価格の読み込みに失敗しました', 'danger');
        });
    }

    // イベントハンドラ
    document.addEventListener('click', function(e) {
        var tab = e.target.closest('.tier-tab');
        if (tab && tab.dataset.tierId) {
            switchTier(tab.dataset.tierId);
            return;
        }

        var btn = e.target.closest('[data-action]');
        if (!btn) return;

        switch (btn.dataset.action) {
            case 'openTierModal':
                renderTierList();
                openModal('tierModal');
                break;
            case 'openProductModal':
                renderProductList();
                openModal('productModal');
                break;
            case 'editTier':
                openEditTier(btn.dataset.id);
                break;
            case 'deleteTier':
                confirmDeleteItem('tier', btn.dataset.id, btn.dataset.name);
                break;
            case 'editProduct':
                openEditProduct(btn.dataset.id);
                break;
            case 'deleteProduct':
                confirmDeleteItem('product', btn.dataset.id, btn.dataset.name);
                break;
            case 'savePrices':
                savePrices(btn);
                break;
            case 'cancelChanges':
                pendingChanges = {};
                updateSaveBar();
                renderPriceTable();
                break;
        }
    });

    document.addEventListener('input', function(e) {
        if (e.target.matches('.price-input') || e.target.matches('.memo-input')) {
            trackChange(e.target.dataset.productId, e.target.dataset.field, e.target.value);
        }
    });

    document.querySelectorAll('[data-close-modal]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            closeModal(btn.dataset.closeModal);
        });
    });

    // 顧客層管理
    function renderTierList() {
        var tbody = document.getElementById('tierListBody');
        if (tiers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted" style="padding:1rem;">顧客層が未登録です</td></tr>';
            return;
        }
        tbody.innerHTML = tiers.map(function(t) {
            var actions = '';
            if (canEdit) actions += '<button class="btn btn-sm btn-outline" data-action="editTier" data-id="' + esc(t.id) + '">編集</button> ';
            if (canDel) actions += '<button class="btn btn-sm btn-danger" data-action="deleteTier" data-id="' + esc(t.id) + '" data-name="' + esc(t.name) + '">削除</button>';
            return '<tr><td>' + esc(t.name) + '</td><td>' + esc(t.description) + '</td><td>' + actions + '</td></tr>';
        }).join('');
    }

    document.getElementById('tierAddForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = this.querySelector('[type="submit"]');
        var form = this;
        setLoading(btn, true, '追加中...');
        apiPost('/api/price-list-api.php', {
            action: 'tier_create',
            name: document.getElementById('tierName').value,
            description: document.getElementById('tierDesc').value,
        }).then(function() {
            showAlert('顧客層を追加しました', 'success');
            form.reset();
            return loadTiers();
        }).then(function() {
            renderTierList();
            renderTabs();
            if (tiers.length === 1) {
                activeTierId = tiers[0].id;
                return loadPrices(activeTierId).then(function() { renderPriceTable(); });
            }
        }).catch(function(err) {
            showAlert(err.message, 'danger');
        }).finally(function() {
            setLoading(btn, false);
        });
    });

    function openEditTier(id) {
        var t = tiers.find(function(tier) { return tier.id === id; });
        if (!t) return;
        document.getElementById('tierEditId').value = t.id;
        document.getElementById('tierEditName').value = t.name;
        document.getElementById('tierEditDesc').value = t.description || '';
        openModal('tierEditModal');
    }

    document.getElementById('tierEditForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = this.querySelector('[type="submit"]');
        setLoading(btn, true, '更新中...');
        apiPost('/api/price-list-api.php', {
            action: 'tier_update',
            id: document.getElementById('tierEditId').value,
            name: document.getElementById('tierEditName').value,
            description: document.getElementById('tierEditDesc').value,
        }).then(function() {
            showAlert('顧客層を更新しました', 'success');
            closeModal('tierEditModal');
            return loadTiers();
        }).then(function() {
            renderTierList();
            renderTabs();
        }).catch(function(err) {
            showAlert(err.message, 'danger');
        }).finally(function() {
            setLoading(btn, false);
        });
    });

    // 製品管理
    function renderProductList() {
        var tbody = document.getElementById('productListBody');
        if (products.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted" style="padding:1rem;">製品が未登録です</td></tr>';
            return;
        }
        tbody.innerHTML = products.map(function(p) {
            var actions = '';
            if (canEdit) actions += '<button class="btn btn-sm btn-outline" data-action="editProduct" data-id="' + esc(p.id) + '">編集</button> ';
            if (canDel) actions += '<button class="btn btn-sm btn-danger" data-action="deleteProduct" data-id="' + esc(p.id) + '" data-name="' + esc(p.product_name) + '">削除</button>';
            return '<tr><td>' + esc(p.product_number) + '</td><td>' + esc(p.product_name) + '</td><td>' + esc(p.category) + '</td><td>' + esc(p.unit) + '</td><td>' + actions + '</td></tr>';
        }).join('');
    }

    document.getElementById('productAddForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = this.querySelector('[type="submit"]');
        var form = this;
        setLoading(btn, true, '追加中...');
        apiPost('/api/price-list-api.php', {
            action: 'product_create',
            product_number: document.getElementById('prodNumber').value,
            product_name: document.getElementById('prodName').value,
            category: document.getElementById('prodCategory').value,
            unit: document.getElementById('prodUnit').value,
        }).then(function() {
            showAlert('製品を追加しました', 'success');
            form.reset();
            return loadProducts();
        }).then(function() {
            renderProductList();
            renderPriceTable();
        }).catch(function(err) {
            showAlert(err.message, 'danger');
        }).finally(function() {
            setLoading(btn, false);
        });
    });

    function openEditProduct(id) {
        var p = products.find(function(prod) { return prod.id === id; });
        if (!p) return;
        document.getElementById('prodEditId').value = p.id;
        document.getElementById('prodEditNumber').value = p.product_number || '';
        document.getElementById('prodEditName').value = p.product_name || '';
        document.getElementById('prodEditCategory').value = p.category || '';
        document.getElementById('prodEditUnit').value = p.unit || '';
        openModal('productEditModal');
    }

    document.getElementById('productEditForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = this.querySelector('[type="submit"]');
        setLoading(btn, true, '更新中...');
        apiPost('/api/price-list-api.php', {
            action: 'product_update',
            id: document.getElementById('prodEditId').value,
            product_number: document.getElementById('prodEditNumber').value,
            product_name: document.getElementById('prodEditName').value,
            category: document.getElementById('prodEditCategory').value,
            unit: document.getElementById('prodEditUnit').value,
        }).then(function() {
            showAlert('製品を更新しました', 'success');
            closeModal('productEditModal');
            return loadProducts();
        }).then(function() {
            renderProductList();
            renderPriceTable();
        }).catch(function(err) {
            showAlert(err.message, 'danger');
        }).finally(function() {
            setLoading(btn, false);
        });
    });

    // 削除
    function confirmDeleteItem(type, id, name) {
        document.getElementById('deleteMessage').textContent = '「' + (name || '') + '」を削除しますか? この操作は取り消せません。';
        deleteCallback = function() {
            var delBtn = document.getElementById('confirmDeleteBtn');
            setLoading(delBtn, true, '削除中...');
            var action = type === 'tier' ? 'tier_delete' : 'product_delete';
            apiPost('/api/price-list-api.php', { action: action, id: id }).then(function() {
                showAlert('削除しました', 'success');
                closeModal('deleteModal');
                if (type === 'tier') {
                    return loadTiers().then(function() {
                        renderTierList();
                        renderTabs();
                        if (activeTierId === id && tiers.length > 0) activeTierId = tiers[0].id;
                        if (tiers.length > 0) {
                            return loadPrices(activeTierId).then(function() { renderPriceTable(); });
                        } else {
                            prices = [];
                            renderPriceTable();
                        }
                    });
                } else {
                    return loadProducts().then(function() {
                        renderProductList();
                        renderPriceTable();
                    });
                }
            }).catch(function(err) {
                showAlert(err.message, 'danger');
            }).finally(function() {
                setLoading(delBtn, false);
                deleteCallback = null;
            });
        };
        openModal('deleteModal');
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (deleteCallback) deleteCallback();
    });

    // 価格一括保存
    function savePrices(btn) {
        var keys = Object.keys(pendingChanges);
        if (keys.length === 0) return;

        setLoading(btn, true, '保存中...');
        var priceData = keys.map(function(pid) {
            return { product_id: pid, price: pendingChanges[pid].price, memo: pendingChanges[pid].memo };
        });
        apiPost('/api/price-list-api.php', {
            action: 'price_bulk_save',
            tier_id: activeTierId,
            prices: priceData,
        }).then(function() {
            showAlert('価格を保存しました', 'success');
            pendingChanges = {};
            updateSaveBar();
            return loadPrices(activeTierId);
        }).then(function() {
            renderPriceTable();
        }).catch(function(err) {
            showAlert(err.message, 'danger');
        }).finally(function() {
            setLoading(btn, false);
        });
    }

    init();
})();
</script>

<?php require_once '../functions/footer.php'; ?>
