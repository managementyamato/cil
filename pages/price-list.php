<?php
/**
 * 価格表 v2
 *
 * - 閲覧モード (sales以上): 製品 → バリアント → ランク別価格を一覧表示
 * - 管理モード (admin): 製品/バリアント/価格ルールを CRUD
 *
 * 設計: docs/price-list-design.md
 *
 * master-hub から include される (IN_HUB_PAGE 定数あり) ことを想定。
 * 単独アクセス時は header/footer を自前で読み込む。
 */

$_inHub = defined('IN_HUB_PAGE');
if (!$_inHub) {
    require_once __DIR__ . '/../api/auth.php';
    require_once __DIR__ . '/../functions/header.php';
}
require_once __DIR__ . '/../functions/price-list-repository.php';

// 権限
$canView = hasPermission(getPageViewPermission('price-list.php'));
if (!$canView) {
    echo '<div style="padding:2rem;text-align:center;color:var(--gray-600);">閲覧権限がありません</div>';
    if (!$_inHub) require_once __DIR__ . '/../functions/footer.php';
    return;
}
$canEdit = isAdmin() && hasPermission(getPageEditPermission('price-list.php'));
$csrfToken = generateCsrfToken();

// 初期表示用に製品一覧 (リポジトリ越しに DB から)
$initialProducts = PriceListRepository::listProducts(true);
?>
<style<?= nonceAttr() ?>>
.pl2-page { max-width: 1400px; margin: 0 auto; padding: 0 0 3rem; }

.pl2-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;
}
.pl2-head h2 { margin: 0; color: var(--gray-900); }
.pl2-head .pl2-head-sub { color: var(--gray-600); font-size: 0.86rem; margin-top: 0.25rem; }
.pl2-head-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

.pl2-flash {
    padding: 0.7rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.88rem; display: none;
}
.pl2-flash.show    { display: block; }
.pl2-flash.success { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
.pl2-flash.error   { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

.pl2-empty {
    padding: 2.5rem; text-align: center; color: var(--gray-500);
    background: var(--gray-50); border: 1px dashed var(--gray-300); border-radius: 10px;
}

.pl2-toolbar {
    display: flex; gap: 0.6rem; margin-bottom: 1rem; flex-wrap: wrap; align-items: center;
}
.pl2-toolbar .pl2-search { flex: 1; min-width: 240px; max-width: 380px; }
.pl2-toolbar .pl2-rank-filter,
.pl2-toolbar .pl2-txn-filter {
    display: inline-flex; gap: 0.2rem; border: 1px solid var(--gray-300); border-radius: 8px; overflow: hidden;
}
.pl2-toolbar .pl2-rank-filter button,
.pl2-toolbar .pl2-txn-filter button {
    background: white; border: none; padding: 0.4rem 0.9rem; font-size: 0.82rem;
    color: var(--gray-700); cursor: pointer; border-right: 1px solid var(--gray-200);
}
.pl2-toolbar .pl2-rank-filter button:last-child,
.pl2-toolbar .pl2-txn-filter button:last-child { border-right: none; }
.pl2-toolbar .pl2-rank-filter button.active,
.pl2-toolbar .pl2-txn-filter button.active { color: white; font-weight: 600; }
.pl2-toolbar .pl2-rank-filter button[data-rank=S].active { background:#7c3aed; }
.pl2-toolbar .pl2-rank-filter button[data-rank=A].active { background:#2563eb; }
.pl2-toolbar .pl2-rank-filter button[data-rank=B].active { background:#059669; }
.pl2-toolbar .pl2-txn-filter button.active   { background: var(--primary, #2563eb); }

.pl2-product-card {
    background: white; border: 1px solid var(--gray-200); border-radius: 12px;
    margin-bottom: 1rem; overflow: hidden;
}
.pl2-product-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.85rem 1.15rem; background: var(--gray-50); border-bottom: 1px solid var(--gray-200);
    flex-wrap: wrap; gap: 0.5rem;
}
.pl2-product-title {
    display: flex; align-items: baseline; gap: 0.75rem; min-width: 0; flex: 1;
}
.pl2-product-title h3 { margin: 0; font-size: 1.0rem; color: var(--gray-800); }
.pl2-product-title .pl2-product-cat {
    font-size: 0.74rem; color: var(--gray-500);
    background: white; border: 1px solid var(--gray-200); padding: 1px 8px; border-radius: 10px;
}
.pl2-product-title .pl2-product-inactive {
    font-size: 0.74rem; color: #b45309; background: #fef3c7; padding: 1px 8px; border-radius: 10px;
}
.pl2-product-actions { display: flex; gap: 0.35rem; }

.pl2-table-wrap { overflow-x: auto; }
.pl2-table {
    width: 100%; border-collapse: collapse; font-size: 0.86rem;
}
.pl2-table th, .pl2-table td {
    padding: 0.55rem 0.8rem; border-bottom: 1px solid var(--gray-100); text-align: left; vertical-align: middle;
}
.pl2-table th { background: var(--gray-50); color: var(--gray-500); font-size: 0.72rem; font-weight: 600; text-transform: none; }
.pl2-table td.num, .pl2-table th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.pl2-table tr.pl2-no-prices td { color: var(--gray-400); font-style: italic; }
.pl2-rank-pill {
    display: inline-block; font-size: 0.7rem; padding: 1px 7px; border-radius: 10px; font-weight: 600; color: white;
}
.pl2-rank-pill.S { background:#7c3aed; }
.pl2-rank-pill.A { background:#2563eb; }
.pl2-rank-pill.B { background:#059669; }
.pl2-txn-pill {
    display: inline-block; font-size: 0.7rem; padding: 1px 7px; border-radius: 10px; font-weight: 600;
    background: var(--gray-100); color: var(--gray-700);
}

.pl2-variant-actions { display: flex; gap: 0.3rem; justify-content: flex-end; }

/* モーダル (シンプルなオーバーレイ) */
.pl2-modal-back {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    display: none; align-items: center; justify-content: center; z-index: 1000;
}
.pl2-modal-back.show { display: flex; }
.pl2-modal {
    background: white; border-radius: 10px; padding: 1.5rem;
    width: 92%; max-width: 560px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.pl2-modal h3 { margin: 0 0 1rem; font-size: 1.05rem; color: var(--gray-900); }
.pl2-modal .form-group { margin-bottom: 0.85rem; }
.pl2-modal label {
    display: block; font-size: 0.8rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.25rem;
}
.pl2-modal-actions {
    display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.25rem;
}
.pl2-hint { color: var(--gray-500); font-size: 0.76rem; margin-top: 0.2rem; }
.pl2-row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
@media (max-width: 600px) { .pl2-row-grid { grid-template-columns: 1fr; } }
</style>

<div class="pl2-page">

    <div class="pl2-head">
        <div>
            <h2 style="display:flex;align-items:center;gap:0.5rem;">価格表 v2
                <span style="font-size:0.7rem;background:#dbeafe;color:#1e3a8a;padding:2px 8px;border-radius:10px;font-weight:600;">Phase 1</span>
            </h2>
            <div class="pl2-head-sub">製品 → サイズ → 顧客ランク(S/A/B) × 取引形態(販売/レンタル) の単価を一覧。<?php if ($canEdit): ?>管理部は編集可。<?php endif; ?></div>
        </div>
        <?php if ($canEdit): ?>
        <div class="pl2-head-actions">
            <button type="button" class="btn btn-primary" id="pl2BtnAddProduct">+ 製品を追加</button>
        </div>
        <?php endif; ?>
    </div>

    <div class="pl2-flash" id="pl2Flash"></div>

    <div class="pl2-toolbar">
        <input type="text" class="form-input pl2-search" id="pl2Search" placeholder="製品名・サイズで絞り込み...">
        <div class="pl2-rank-filter" role="group" aria-label="顧客ランク絞り込み">
            <button type="button" data-rank="">すべて</button>
            <button type="button" data-rank="S">S</button>
            <button type="button" data-rank="A" class="active">A</button>
            <button type="button" data-rank="B">B</button>
        </div>
        <div class="pl2-txn-filter" role="group" aria-label="取引形態">
            <button type="button" data-txn="" class="active">両方</button>
            <button type="button" data-txn="sale">販売</button>
            <button type="button" data-txn="rental">レンタル</button>
        </div>
    </div>

    <div id="pl2ProductList">
        <?php if (empty($initialProducts)): ?>
            <div class="pl2-empty">
                まだ製品が登録されていません。<?php if ($canEdit): ?>「+ 製品を追加」から最初の製品を登録してください。<?php endif; ?>
            </div>
        <?php else: ?>
            <div class="pl2-empty" id="pl2Loading">読み込み中…</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canEdit): ?>
<!-- 製品 編集モーダル -->
<div class="pl2-modal-back" id="pl2ProductModal">
    <div class="pl2-modal">
        <h3 id="pl2ProductModalTitle">製品を追加</h3>
        <input type="hidden" id="pl2ProductId" value="">
        <div class="pl2-row-grid">
            <div class="form-group">
                <label>製品名 <span style="color:#dc2626">*</span></label>
                <input type="text" class="form-input" id="pl2ProductName" placeholder="例: モニたろう UTM-P4">
            </div>
            <div class="form-group">
                <label>カテゴリ</label>
                <input type="text" class="form-input" id="pl2ProductCategory" placeholder="例: LEDビジョン">
            </div>
        </div>
        <div class="pl2-row-grid">
            <div class="form-group">
                <label>業務コード (任意)</label>
                <input type="text" class="form-input" id="pl2ProductCode" placeholder="任意">
            </div>
            <div class="form-group">
                <label>表示順</label>
                <input type="number" class="form-input" id="pl2ProductOrder" value="0">
            </div>
        </div>
        <div class="form-group">
            <label>説明 (任意)</label>
            <textarea class="form-input" id="pl2ProductDesc" rows="2"></textarea>
        </div>
        <div class="form-group">
            <label><input type="checkbox" id="pl2ProductActive" checked> 表示する (アクティブ)</label>
        </div>
        <div class="pl2-modal-actions">
            <button type="button" class="btn btn-secondary" data-pl2-close-modal>キャンセル</button>
            <button type="button" class="btn btn-primary"   id="pl2ProductSave">保存</button>
        </div>
    </div>
</div>

<!-- バリアント 編集モーダル -->
<div class="pl2-modal-back" id="pl2VariantModal">
    <div class="pl2-modal">
        <h3 id="pl2VariantModalTitle">バリアントを追加</h3>
        <input type="hidden" id="pl2VariantId" value="">
        <input type="hidden" id="pl2VariantProductId" value="">
        <div class="pl2-row-grid">
            <div class="form-group">
                <label>サイズラベル <span style="color:#dc2626">*</span></label>
                <input type="text" class="form-input" id="pl2VariantSizeLabel" placeholder="例: 81インチ">
            </div>
            <div class="form-group">
                <label>インチ数 (数値)</label>
                <input type="number" step="0.01" class="form-input" id="pl2VariantSizeInch" placeholder="例: 81">
            </div>
        </div>
        <div class="pl2-row-grid">
            <div class="form-group">
                <label>解像度</label>
                <input type="text" class="form-input" id="pl2VariantResolution" placeholder="例: 1600x1280">
            </div>
            <div class="form-group">
                <label>画面平米数 (m²)</label>
                <input type="number" step="0.001" class="form-input" id="pl2VariantArea" placeholder="例: 2.048">
            </div>
        </div>
        <div class="form-group">
            <label>表示順</label>
            <input type="number" class="form-input" id="pl2VariantOrder" value="0">
        </div>
        <div class="form-group">
            <label><input type="checkbox" id="pl2VariantActive" checked> アクティブ</label>
        </div>
        <div class="pl2-modal-actions">
            <button type="button" class="btn btn-secondary" data-pl2-close-modal>キャンセル</button>
            <button type="button" class="btn btn-primary"   id="pl2VariantSave">保存</button>
        </div>
    </div>
</div>

<!-- 価格ルール 編集モーダル -->
<div class="pl2-modal-back" id="pl2PriceModal">
    <div class="pl2-modal">
        <h3 id="pl2PriceModalTitle">価格ルールを追加</h3>
        <input type="hidden" id="pl2PriceVariantId" value="">
        <input type="hidden" id="pl2PriceExistingId" value="">
        <div class="pl2-row-grid">
            <div class="form-group">
                <label>顧客ランク <span style="color:#dc2626">*</span></label>
                <select class="form-input" id="pl2PriceRank">
                    <option value="S">S</option>
                    <option value="A" selected>A</option>
                    <option value="B">B</option>
                </select>
            </div>
            <div class="form-group">
                <label>取引形態 <span style="color:#dc2626">*</span></label>
                <select class="form-input" id="pl2PriceTxn">
                    <option value="rental" selected>レンタル</option>
                    <option value="sale">販売</option>
                </select>
            </div>
        </div>
        <div class="pl2-row-grid">
            <div class="form-group">
                <label>価格ラベル <span style="color:#dc2626">*</span></label>
                <input type="text" class="form-input" id="pl2PriceLabel" placeholder="例: 月額 / 販売価格 / 初月">
            </div>
            <div class="form-group">
                <label>金額 (円・税抜) <span style="color:#dc2626">*</span></label>
                <input type="number" min="0" class="form-input" id="pl2PriceAmount" placeholder="0">
            </div>
        </div>
        <div class="form-group">
            <label>メモ (任意)</label>
            <input type="text" class="form-input" id="pl2PriceNotes" placeholder="例: 12ヶ月以上 / 初年度のみ等">
        </div>
        <div class="form-group">
            <label>表示順</label>
            <input type="number" class="form-input" id="pl2PriceOrder" value="0">
        </div>
        <div class="pl2-modal-actions">
            <button type="button" class="btn btn-secondary" data-pl2-close-modal>キャンセル</button>
            <button type="button" class="btn btn-primary"   id="pl2PriceSave">保存</button>
        </div>
    </div>
</div>
<?php endif; /* canEdit */ ?>

<script<?= nonceAttr() ?>>
(function(){
    'use strict';
    const CSRF = <?= json_encode($csrfToken) ?>;
    const API  = '/api/price-list.php';
    const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;

    // ── ユーティリティ ──
    function esc(s){ const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
    function fmtYen(n){ return '¥' + Number(n||0).toLocaleString(); }

    const $flash = document.getElementById('pl2Flash');
    function flash(msg, type) {
        $flash.textContent = msg;
        $flash.classList.remove('success','error');
        $flash.classList.add(type || 'success', 'show');
        setTimeout(()=>{ $flash.classList.remove('show'); }, 4000);
    }

    async function apiGet(action, params) {
        const qs = new URLSearchParams({ action, ...(params || {}) }).toString();
        const r = await fetch(API + '?' + qs, { credentials: 'same-origin' });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const j = await r.json();
        if (j.success === false) throw new Error(j.error || 'API失敗');
        return j.data || j;
    }
    async function apiPost(action, body) {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('csrf_token', CSRF);
        for (const k in (body||{})) {
            if (Object.prototype.hasOwnProperty.call(body, k) && body[k] !== undefined && body[k] !== null) {
                fd.append(k, body[k]);
            }
        }
        const r = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await r.json();
        if (!r.ok || j.success === false) throw new Error(j.error || 'API失敗');
        return j.data || j;
    }

    // ── 状態 ──
    let allProductMatrices = [];   // [{product, variants:[{...,prices:[...]}]}, ...]
    let filterRank = 'A';
    let filterTxn  = '';
    let filterText = '';

    // ── 読込 ──
    async function loadAll() {
        try {
            const list = await apiGet('list_products', { include_inactive: 1 });
            const products = list.items || [];
            // 各製品ごとに matrix を取得
            const matrices = await Promise.all(products.map(p => apiGet('get_product', { product_id: p.id }).catch(()=>null)));
            allProductMatrices = matrices.filter(Boolean);
            render();
        } catch (e) {
            flash('読み込みエラー: ' + e.message, 'error');
        }
    }

    // ── 描画 ──
    function render() {
        const c = document.getElementById('pl2ProductList');
        if (!allProductMatrices.length) {
            c.innerHTML = '<div class="pl2-empty">まだ製品が登録されていません。' + (CAN_EDIT ? '「+ 製品を追加」から最初の製品を登録してください。' : '') + '</div>';
            return;
        }
        const q = filterText.trim().toLowerCase();

        const html = allProductMatrices.map(m => renderProduct(m, q)).filter(Boolean).join('');
        c.innerHTML = html || '<div class="pl2-empty">該当する製品がありません</div>';
    }

    function renderProduct(matrix, q) {
        const p = matrix.product;
        const variants = matrix.variants || [];

        const productMatch = q === '' || (p.name || '').toLowerCase().includes(q) || (p.category || '').toLowerCase().includes(q);
        const matchedVariants = variants.filter(v => {
            if (productMatch) return true;
            return (v.size_label || '').toLowerCase().includes(q) || (v.resolution || '').toLowerCase().includes(q);
        });
        if (q !== '' && matchedVariants.length === 0) return '';

        const showVariants = matchedVariants.length ? matchedVariants : variants;

        const inactiveBadge  = +p.is_active === 0 ? '<span class="pl2-product-inactive">非表示</span>' : '';
        const categoryBadge  = p.category ? '<span class="pl2-product-cat">' + esc(p.category) + '</span>' : '';

        const editButtons = CAN_EDIT ? (
            '<button type="button" class="btn btn-sm btn-secondary" data-pl2-action="edit-product" data-id="' + esc(p.id) + '">編集</button>' +
            '<button type="button" class="btn btn-sm btn-secondary" data-pl2-action="add-variant" data-id="' + esc(p.id) + '">+ サイズ</button>' +
            '<button type="button" class="btn btn-sm btn-danger"    data-pl2-action="delete-product" data-id="' + esc(p.id) + '" data-name="' + esc(p.name) + '">削除</button>'
        ) : '';

        let rows = '';
        if (!showVariants.length) {
            rows = '<tr><td colspan="' + (CAN_EDIT ? 6 : 5) + '" style="color:var(--gray-400);text-align:center;padding:1rem;">バリアント未登録</td></tr>';
        } else {
            rows = showVariants.map(v => renderVariantRow(p, v)).join('');
        }

        const cols = CAN_EDIT
            ? '<th>サイズ</th><th>解像度</th><th>面積(m²)</th><th>価格</th><th></th><th></th>'
            : '<th>サイズ</th><th>解像度</th><th>面積(m²)</th><th>価格</th><th></th>';

        return '<div class="pl2-product-card">' +
            '<div class="pl2-product-head">' +
                '<div class="pl2-product-title">' +
                    '<h3>' + esc(p.name) + '</h3>' + categoryBadge + inactiveBadge +
                '</div>' +
                (editButtons ? '<div class="pl2-product-actions">' + editButtons + '</div>' : '') +
            '</div>' +
            '<div class="pl2-table-wrap"><table class="pl2-table"><thead><tr>' + cols + '</tr></thead><tbody>' +
                rows +
            '</tbody></table></div>' +
        '</div>';
    }

    function renderVariantRow(product, v) {
        const filtered = (v.prices || []).filter(r => {
            if (filterRank && r.customer_rank !== filterRank) return false;
            if (filterTxn  && r.transaction_type !== filterTxn) return false;
            return true;
        });

        const inactiveBadge = +v.is_active === 0
            ? ' <span style="font-size:0.7rem;color:#b45309;background:#fef3c7;padding:1px 6px;border-radius:8px;">非表示</span>'
            : '';

        const priceCell = filtered.length === 0
            ? '<span style="color:var(--gray-400);">価格未登録</span>'
            : filtered.map(r => (
                '<div style="display:flex;align-items:center;gap:0.4rem;margin:1px 0;">' +
                    '<span class="pl2-rank-pill ' + esc(r.customer_rank) + '">' + esc(r.customer_rank) + '</span>' +
                    '<span class="pl2-txn-pill">' + (r.transaction_type === 'sale' ? '販売' : 'レンタル') + '</span>' +
                    '<span style="color:var(--gray-600);font-size:0.8rem;">' + esc(r.price_label) + '</span>' +
                    '<strong style="margin-left:auto;">' + fmtYen(r.amount) + '</strong>' +
                    (r.notes ? '<span style="color:var(--gray-500);font-size:0.78rem;">' + esc(r.notes) + '</span>' : '') +
                    (CAN_EDIT ? '<button type="button" class="btn btn-sm btn-secondary" data-pl2-action="edit-price" data-id="' + r.id + '" data-variant="' + esc(v.id) + '" style="padding:1px 6px;font-size:0.7rem;">編集</button>' : '') +
                    (CAN_EDIT ? '<button type="button" class="btn btn-sm btn-danger" data-pl2-action="delete-price" data-id="' + r.id + '" style="padding:1px 6px;font-size:0.7rem;">×</button>' : '') +
                '</div>'
            )).join('');

        const variantActions = CAN_EDIT ? (
            '<div class="pl2-variant-actions">' +
                '<button type="button" class="btn btn-sm btn-secondary" data-pl2-action="add-price" data-variant="' + esc(v.id) + '">+ 価格</button>' +
                '<button type="button" class="btn btn-sm btn-secondary" data-pl2-action="edit-variant" data-id="' + esc(v.id) + '" data-product="' + esc(product.id) + '">編集</button>' +
                '<button type="button" class="btn btn-sm btn-danger"    data-pl2-action="delete-variant" data-id="' + esc(v.id) + '" data-name="' + esc(v.size_label) + '">削除</button>' +
            '</div>'
        ) : '';

        return '<tr>' +
            '<td><strong>' + esc(v.size_label) + '</strong>' + inactiveBadge + '</td>' +
            '<td>' + esc(v.resolution || '-') + '</td>' +
            '<td class="num">' + (v.screen_area_m2 != null ? Number(v.screen_area_m2).toFixed(3) : '-') + '</td>' +
            '<td>' + priceCell + '</td>' +
            (CAN_EDIT ? '<td>' + variantActions + '</td><td></td>' : '<td></td>') +
        '</tr>';
    }

    // ── フィルタイベント ──
    document.getElementById('pl2Search').addEventListener('input', e => {
        filterText = e.target.value;
        render();
    });
    document.querySelectorAll('.pl2-rank-filter button').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('.pl2-rank-filter button').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            filterRank = b.dataset.rank || '';
            render();
        });
    });
    document.querySelectorAll('.pl2-txn-filter button').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('.pl2-txn-filter button').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            filterTxn = b.dataset.txn || '';
            render();
        });
    });

    <?php if ($canEdit): ?>
    // ─────────────────────────────────────────────
    // 管理モード: CRUD
    // ─────────────────────────────────────────────

    // モーダル開閉
    function openModal(id) { document.getElementById(id).classList.add('show'); }
    function closeModal(id) { document.getElementById(id).classList.remove('show'); }
    document.addEventListener('click', e => {
        if (e.target.hasAttribute('data-pl2-close-modal')) {
            const m = e.target.closest('.pl2-modal-back');
            if (m) m.classList.remove('show');
        }
    });

    // ── 製品: 追加・編集 ──
    function openProductModal(product) {
        document.getElementById('pl2ProductModalTitle').textContent = product ? '製品を編集' : '製品を追加';
        document.getElementById('pl2ProductId').value       = product ? product.id : '';
        document.getElementById('pl2ProductName').value     = product ? (product.name || '') : '';
        document.getElementById('pl2ProductCategory').value = product ? (product.category || '') : '';
        document.getElementById('pl2ProductCode').value     = product ? (product.code || '') : '';
        document.getElementById('pl2ProductOrder').value    = product ? (product.display_order || 0) : 0;
        document.getElementById('pl2ProductDesc').value     = product ? (product.description || '') : '';
        document.getElementById('pl2ProductActive').checked = product ? (+product.is_active === 1) : true;
        openModal('pl2ProductModal');
    }
    document.getElementById('pl2BtnAddProduct').addEventListener('click', () => openProductModal(null));
    document.getElementById('pl2ProductSave').addEventListener('click', async () => {
        const id   = document.getElementById('pl2ProductId').value.trim();
        const name = document.getElementById('pl2ProductName').value.trim();
        if (!name) { flash('製品名は必須です', 'error'); return; }
        const body = {
            name: name,
            category:      document.getElementById('pl2ProductCategory').value.trim(),
            code:          document.getElementById('pl2ProductCode').value.trim(),
            display_order: document.getElementById('pl2ProductOrder').value || 0,
            description:   document.getElementById('pl2ProductDesc').value,
            is_active:     document.getElementById('pl2ProductActive').checked ? 1 : 0,
        };
        try {
            if (id) {
                await apiPost('update_product', { id, ...body });
                flash('製品を更新しました', 'success');
            } else {
                await apiPost('create_product', body);
                flash('製品を追加しました', 'success');
            }
            closeModal('pl2ProductModal');
            await loadAll();
        } catch (e) {
            flash(e.message, 'error');
        }
    });

    // ── バリアント: 追加・編集 ──
    function openVariantModal(productId, variant) {
        document.getElementById('pl2VariantModalTitle').textContent = variant ? 'バリアントを編集' : 'バリアントを追加';
        document.getElementById('pl2VariantId').value          = variant ? variant.id : '';
        document.getElementById('pl2VariantProductId').value   = productId;
        document.getElementById('pl2VariantSizeLabel').value   = variant ? (variant.size_label || '') : '';
        document.getElementById('pl2VariantSizeInch').value    = variant && variant.size_inch != null ? variant.size_inch : '';
        document.getElementById('pl2VariantResolution').value  = variant ? (variant.resolution || '') : '';
        document.getElementById('pl2VariantArea').value        = variant && variant.screen_area_m2 != null ? variant.screen_area_m2 : '';
        document.getElementById('pl2VariantOrder').value       = variant ? (variant.display_order || 0) : 0;
        document.getElementById('pl2VariantActive').checked    = variant ? (+variant.is_active === 1) : true;
        openModal('pl2VariantModal');
    }
    document.getElementById('pl2VariantSave').addEventListener('click', async () => {
        const id        = document.getElementById('pl2VariantId').value.trim();
        const productId = document.getElementById('pl2VariantProductId').value.trim();
        const sizeLabel = document.getElementById('pl2VariantSizeLabel').value.trim();
        if (!sizeLabel) { flash('サイズラベルは必須です', 'error'); return; }
        const body = {
            size_label:     sizeLabel,
            size_inch:      document.getElementById('pl2VariantSizeInch').value,
            resolution:     document.getElementById('pl2VariantResolution').value.trim(),
            screen_area_m2: document.getElementById('pl2VariantArea').value,
            display_order:  document.getElementById('pl2VariantOrder').value || 0,
            is_active:      document.getElementById('pl2VariantActive').checked ? 1 : 0,
        };
        try {
            if (id) {
                await apiPost('update_variant', { id, ...body });
                flash('バリアントを更新しました', 'success');
            } else {
                await apiPost('create_variant', { product_id: productId, ...body });
                flash('バリアントを追加しました', 'success');
            }
            closeModal('pl2VariantModal');
            await loadAll();
        } catch (e) {
            flash(e.message, 'error');
        }
    });

    // ── 価格ルール: 追加・編集 ──
    function openPriceModal(variantId, rule) {
        document.getElementById('pl2PriceModalTitle').textContent = rule ? '価格ルールを編集' : '価格ルールを追加';
        document.getElementById('pl2PriceVariantId').value  = variantId;
        document.getElementById('pl2PriceExistingId').value = rule ? rule.id : '';
        document.getElementById('pl2PriceRank').value       = rule ? rule.customer_rank : 'A';
        document.getElementById('pl2PriceTxn').value        = rule ? rule.transaction_type : 'rental';
        document.getElementById('pl2PriceLabel').value      = rule ? (rule.price_label || '') : '月額';
        document.getElementById('pl2PriceAmount').value     = rule ? rule.amount : '';
        document.getElementById('pl2PriceNotes').value      = rule ? (rule.notes || '') : '';
        document.getElementById('pl2PriceOrder').value      = rule ? (rule.display_order || 0) : 0;
        openModal('pl2PriceModal');
    }
    document.getElementById('pl2PriceSave').addEventListener('click', async () => {
        const variantId  = document.getElementById('pl2PriceVariantId').value.trim();
        const priceLabel = document.getElementById('pl2PriceLabel').value.trim();
        const amount     = document.getElementById('pl2PriceAmount').value;
        if (!variantId || !priceLabel || amount === '') { flash('価格ラベルと金額は必須です', 'error'); return; }
        try {
            await apiPost('upsert_price_rule', {
                variant_id:       variantId,
                customer_rank:    document.getElementById('pl2PriceRank').value,
                transaction_type: document.getElementById('pl2PriceTxn').value,
                price_label:      priceLabel,
                amount:           amount,
                notes:            document.getElementById('pl2PriceNotes').value.trim(),
                display_order:    document.getElementById('pl2PriceOrder').value || 0,
            });
            flash('価格を保存しました', 'success');
            closeModal('pl2PriceModal');
            await loadAll();
        } catch (e) {
            flash(e.message, 'error');
        }
    });

    // ── アクション委譲 (一覧 + テーブル内ボタン) ──
    document.getElementById('pl2ProductList').addEventListener('click', async e => {
        const btn = e.target.closest('[data-pl2-action]');
        if (!btn) return;
        const action = btn.dataset.pl2Action;
        const id     = btn.dataset.id;

        if (action === 'edit-product') {
            const m = allProductMatrices.find(x => x.product.id === id);
            if (m) openProductModal(m.product);
            return;
        }
        if (action === 'delete-product') {
            const name = btn.dataset.name || id;
            if (!confirm('「' + name + '」を削除します。関連するバリアント・価格も論理削除されます。よろしいですか?')) return;
            try { await apiPost('delete_product', { id }); flash('削除しました', 'success'); await loadAll(); }
            catch (e) { flash(e.message, 'error'); }
            return;
        }
        if (action === 'add-variant') {
            openVariantModal(id, null);
            return;
        }
        if (action === 'edit-variant') {
            const productId = btn.dataset.product;
            const m = allProductMatrices.find(x => x.product.id === productId);
            const v = m && (m.variants || []).find(x => x.id === id);
            if (v) openVariantModal(productId, v);
            return;
        }
        if (action === 'delete-variant') {
            const name = btn.dataset.name || id;
            if (!confirm('バリアント「' + name + '」を削除します。関連する価格ルールも論理削除されます。よろしいですか?')) return;
            try { await apiPost('delete_variant', { id }); flash('削除しました', 'success'); await loadAll(); }
            catch (e) { flash(e.message, 'error'); }
            return;
        }
        if (action === 'add-price') {
            openPriceModal(btn.dataset.variant, null);
            return;
        }
        if (action === 'edit-price') {
            const variantId = btn.dataset.variant;
            const priceId   = btn.dataset.id;
            for (const m of allProductMatrices) {
                const v = (m.variants || []).find(x => x.id === variantId);
                if (!v) continue;
                const r = (v.prices || []).find(x => String(x.id) === String(priceId));
                if (r) { openPriceModal(variantId, r); return; }
            }
            return;
        }
        if (action === 'delete-price') {
            if (!confirm('この価格ルールを削除します。よろしいですか?')) return;
            try { await apiPost('delete_price_rule', { id }); flash('削除しました', 'success'); await loadAll(); }
            catch (e) { flash(e.message, 'error'); }
            return;
        }
    });
    <?php endif; /* canEdit */ ?>

    // 初期ロード
    loadAll();
})();
</script>

<?php if (!$_inHub) { require_once __DIR__ . '/../functions/footer.php'; } ?>
