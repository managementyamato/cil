<?php /* sales-tools CSS (Sprint 1 抽出) */ ?>
<style<?= nonceAttr() ?>>
/* ========== 営業ツール ========== */
.sales-tools-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0.5rem 0 2rem;
}

/* ヘッダー */
.st-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.st-header-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    background: var(--danger-light);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--danger);
    flex-shrink: 0;
}
.st-header-text h2 {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 0.25rem;
    line-height: 1.2;
}
.st-header-text .st-subtitle {
    font-size: 0.875rem;
    color: var(--gray-700);
    margin: 0;
}

/* 検索バー */
.st-search-wrapper {
    position: relative;
    margin-bottom: 1.25rem;
    max-width: 480px;
}
.st-search-wrapper svg.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
    pointer-events: none;
}
.st-search-input {
    width: 100%;
    padding: 0.625rem 0.75rem 0.625rem 2.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 10px;
    font-size: 0.9375rem;
    background: white;
    color: var(--gray-900);
    transition: border-color 0.15s, box-shadow 0.15s;
}
.st-search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
}

/* タブ */
.st-tabs {
    display: flex;
    gap: 0.25rem;
    border-bottom: 1px solid var(--gray-200);
    margin-bottom: 1.5rem;
    overflow-x: auto;
    flex-wrap: nowrap;
}
.st-tab {
    padding: 0.75rem 1.125rem;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--gray-700);
    font-size: 0.9375rem;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    text-decoration: none;
    white-space: nowrap;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
    border-radius: 6px 6px 0 0;
}
.st-tab:hover {
    color: var(--gray-900);
    background: var(--gray-50);
}
.st-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    font-weight: 600;
}
.st-tab.cta {
    background: var(--primary-light);
    color: var(--primary-dark);
}
.st-tab.cta:hover {
    background: var(--primary-light);
    color: var(--primary-dark);
}
.st-tab.cta.active {
    background: var(--primary-light);
    color: var(--primary-dark);
    border-bottom-color: var(--primary);
}
.st-tab svg {
    flex-shrink: 0;
}

/* パネル */
.st-panel {
    display: none;
}
.st-panel.active {
    display: block;
}

/* 製品カードグリッド */
.st-product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
}
.st-product-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1.25rem;
    transition: box-shadow 0.15s, transform 0.15s;
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    /* <a> として描画された場合のリセット */
    text-decoration: none;
    color: inherit;
}
.st-product-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    transform: translateY(-2px);
    border-color: var(--primary);
}
.st-product-card.is-clickable { cursor: pointer; }
.st-product-card.is-clickable:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}
.st-product-web-link {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-500);
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
}
.st-product-web-link:hover {
    background: var(--gray-100);
    color: var(--primary);
}
.st-product-visual {
    width: 84px;
    height: 84px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-light), var(--gray-100));
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    margin-bottom: 0.25rem;
}
.st-product-name {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
    line-height: 1.2;
}
.st-product-name-en {
    font-size: 0.8125rem;
    color: var(--gray-500);
    margin-top: 0.125rem;
}
.st-product-description {
    font-size: 0.875rem;
    color: var(--gray-700);
    line-height: 1.5;
    flex: 1;
    margin: 0;
}
.st-product-tags {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
    margin-top: 0.25rem;
}
.st-tag {
    display: inline-block;
    padding: 0.2rem 0.7rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
}
.st-tag.price {
    background: var(--success-light);
    color: #0e6251;
}
.st-tag.catalog {
    background: #d6eaf8;
    color: #1a5276;
}
.st-tag.script {
    background: var(--purple-light);
    color: #6c3483;
}

/* 空状態 */
.st-empty {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--gray-500);
    font-size: 0.9375rem;
    background: var(--gray-50);
    border-radius: 12px;
    border: 1px dashed var(--gray-300);
}
.st-empty .empty-title {
    font-size: 1rem;
    color: var(--gray-700);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

/* 注記 */
.st-note {
    padding: 0.75rem 1rem;
    background: var(--primary-light);
    border-left: 4px solid var(--primary);
    font-size: 0.8125rem;
    color: var(--gray-900);
    border-radius: 6px;
    margin-bottom: 1rem;
}

@media (max-width: 720px) {
    .st-header { flex-wrap: wrap; }
    .st-product-grid { grid-template-columns: 1fr; }
}

/* ========== 見積作成(qb = quote builder) ========== */
.qb-wrap { display: flex; flex-direction: column; gap: 1rem; }

.qb-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1.25rem 1.5rem 1.5rem;
}
.qb-card-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 0.25rem;
}
.qb-card-sub {
    font-size: 0.8125rem;
    color: var(--gray-700);
    margin: 0 0 1rem;
}

.qb-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.875rem 1rem;
}
.qb-grid-2 .form-group { display: flex; flex-direction: column; gap: 0.25rem; }
.qb-grid-2 label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--gray-700);
}
.qb-rank-hint {
    font-size: 0.75rem;
    color: var(--gray-700);
    margin-top: 0.25rem;
}

/* ランクバッジ */
.qb-rank-badge {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 700;
    color: white;
}
.qb-rank-badge.s { background: var(--purple); }
.qb-rank-badge.a { background: #2980b9; }
.qb-rank-badge.b { background: var(--primary); }
.qb-rank-badge.c { background: var(--warning); }
.qb-rank-badge.d { background: var(--danger); }

/* 追加ボタン群 */
.qb-add-row {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}
.qb-add-btn {
    background: white;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    padding: 0.5rem 0.875rem;
    font-size: 0.875rem;
    color: var(--gray-900);
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.qb-add-btn:hover {
    border-color: var(--primary);
    background: var(--primary-light);
    color: var(--primary-dark);
}

/* 空状態 */
.qb-empty {
    border: 2px dashed var(--gray-300);
    border-radius: 12px;
    padding: 2.5rem 1rem;
    text-align: center;
    color: var(--gray-500);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}
.qb-empty-title { font-size: 0.9375rem; color: var(--gray-700); font-weight: 600; }
.qb-empty-sub { font-size: 0.8125rem; color: var(--gray-500); }

/* 明細リスト */
#qbItemList { display: flex; flex-direction: column; gap: 0.5rem; }
.qb-item {
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    background: var(--gray-50);
    display: grid;
    grid-template-columns: auto 2fr 1fr 1fr 1fr 1.2fr auto;
    gap: 0.5rem;
    align-items: center;
}
.qb-item-type {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 700;
    color: white;
    white-space: nowrap;
    min-width: 70px;
}
.qb-item-type.product { background: var(--primary); }
.qb-item-type.install { background: var(--warning); }
.qb-item-type.shipping { background: #2980b9; }
.qb-item-type.other { background: var(--gray-500); }

.qb-item input.form-input,
.qb-item select.form-input {
    padding: 0.4rem 0.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 6px;
    font-size: 0.8125rem;
    background: white;
    color: var(--gray-900);
    width: 100%;
}
.qb-item input.form-input:focus,
.qb-item select.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px var(--primary-light);
}
.qb-item input[type="number"] { text-align: right; }
.qb-item .qb-subtotal {
    text-align: right;
    font-weight: 700;
    color: var(--gray-900);
    font-variant-numeric: tabular-nums;
    font-size: 0.9375rem;
}
.qb-item .qb-delete {
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.qb-item .qb-delete:hover { background: var(--danger-light); color: var(--danger); }

@media (max-width: 900px) {
    .qb-grid-2 { grid-template-columns: 1fr; }
    .qb-item {
        grid-template-columns: auto 1fr auto;
        grid-template-areas:
            "type name delete"
            "qty price subtotal";
        row-gap: 0.4rem;
    }
    .qb-item .qb-item-type { grid-area: type; }
    .qb-item .qb-name { grid-area: name; }
    .qb-item .qb-qty { grid-area: qty; }
    .qb-item .qb-price { grid-area: price; }
    .qb-item .qb-subtotal { grid-area: subtotal; }
    .qb-item .qb-delete { grid-area: delete; }
}

/* 合計 */
.qb-totals { padding: 1rem 1.5rem; }
.qb-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.4rem 0;
    font-size: 0.9375rem;
    color: var(--gray-900);
    font-variant-numeric: tabular-nums;
}
.qb-total-grand {
    border-top: 2px solid var(--gray-300);
    margin-top: 0.4rem;
    padding-top: 0.6rem;
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--primary-dark);
}

/* アクション */
.qb-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.qb-action-btn {
    background: white;
    border: 1.5px solid var(--gray-300);
    border-radius: 8px;
    padding: 0.55rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--gray-900);
    cursor: pointer;
    transition: all 0.15s;
}
.qb-action-btn:hover {
    border-color: var(--primary);
    background: var(--primary-light);
    color: var(--primary-dark);
}
.qb-action-btn.primary {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.qb-action-btn.primary:hover {
    background: var(--primary-dark);
    border-color: var(--primary-dark);
    color: white;
}

/* ========== リード管理 ========== */
.lead-wrap { display: flex; flex-direction: column; gap: 1rem; }
.lead-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.lead-toolbar-left { display: flex; gap: 0.5rem; flex: 1; min-width: 280px; }
.lead-toolbar-right { display: flex; gap: 0.5rem; }
.lead-search { flex: 1; max-width: 360px; }
.lead-status-filter { max-width: 200px; }

.lead-status-summary {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    font-size: 0.8125rem;
    color: var(--gray-700);
}
.lead-status-summary .chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    background: var(--gray-100);
    border: 1px solid var(--gray-200);
}
.lead-status-summary .chip b { font-weight: 700; color: var(--gray-900); }

.lead-table-wrap {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    overflow: hidden;
}
.lead-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.lead-table th {
    background: var(--gray-50);
    padding: 0.65rem 0.85rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--gray-500);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
}
.lead-table td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
    overflow: hidden;
    text-overflow: ellipsis;
}
.lead-table tbody tr:last-child td { border-bottom: none; }
.lead-table tbody tr:hover { background: var(--gray-50); }
.lead-company { font-weight: 600; color: var(--gray-900); }
.lead-person { font-size: 0.8125rem; color: var(--gray-700); }
.lead-contact-row { font-size: 0.8125rem; color: var(--gray-700); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lead-source-badge {
    display: inline-block;
    font-size: 0.6875rem;
    padding: 1px 6px;
    border-radius: 4px;
    background: var(--gray-100);
    color: var(--gray-700);
    margin-left: 0.4rem;
}
.lead-source-badge.business_card { background: #fde9d6; color: #b94d00; }
.lead-status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 700;
    color: white;
    white-space: nowrap;
}
.lead-status-badge.s-新規   { background: var(--gray-500); }
.lead-status-badge.s-接触済 { background: #2980b9; }
.lead-status-badge.s-商談中 { background: var(--warning); }
.lead-status-badge.s-成約   { background: #16a085; }
.lead-status-badge.s-失注   { background: var(--danger); }

.lead-row-btns { display: flex; gap: 0.25rem; }
.lead-ibtn {
    background: none;
    border: 1px solid var(--gray-200);
    border-radius: 5px;
    padding: 0.25rem 0.45rem;
    cursor: pointer;
    color: var(--gray-500);
    display: inline-flex;
    align-items: center;
    transition: background 0.1s, color 0.1s;
}
.lead-ibtn:hover { background: var(--gray-100); color: var(--gray-800); }
.lead-ibtn.danger:hover { background: var(--danger-light); color: var(--danger); }

.lead-empty {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--gray-500);
    font-size: 0.9375rem;
}
.lead-empty-title { font-size: 1rem; color: var(--gray-700); font-weight: 600; margin-bottom: 0.5rem; }

/* モーダル */
.lead-modal {
    position: fixed; inset: 0;
    z-index: 9000;
    display: none;
    align-items: center;
    justify-content: center;
}
.lead-modal.open { display: flex; }
.lead-modal-backdrop {
    position: absolute; inset: 0;
    background: rgba(0,0,0,0.5);
}
.lead-modal-dialog {
    position: relative;
    background: white;
    border-radius: 12px;
    width: min(880px, calc(100vw - 2rem));
    max-height: calc(100vh - 2rem);
    display: flex;
    flex-direction: column;
    box-shadow: 0 12px 48px rgba(0,0,0,0.2);
}
.lead-modal-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--gray-200);
}
.lead-modal-head h3 { font-size: 1.0625rem; font-weight: 700; margin: 0; }
.lead-modal-close {
    background: none; border: none; font-size: 1.5rem; cursor: pointer;
    color: var(--gray-500); line-height: 1; padding: 0.2rem 0.4rem;
    border-radius: 4px;
}
.lead-modal-close:hover { background: var(--gray-100); color: var(--gray-900); }
.lead-modal-body {
    padding: 1.25rem;
    overflow-y: auto;
    flex: 1;
}
.lead-modal-grid {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 1.25rem;
    align-items: start;
}
.lead-modal-grid:has(.lead-modal-image[style*="none"]) { grid-template-columns: 1fr; }
.lead-modal-image {
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    overflow: hidden;
    background: var(--gray-50);
    text-align: center;
}
.lead-modal-image img { max-width: 100%; height: auto; display: block; }
.lead-required { color: var(--danger); }
.lead-modal-foot {
    display: flex; justify-content: flex-end; gap: 0.5rem;
    padding: 0.85rem 1.25rem;
    border-top: 1px solid var(--gray-200);
}

/* スキャン中オーバーレイ */
.lead-scan-overlay {
    position: fixed; inset: 0;
    z-index: 9500;
    display: none;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 1rem;
    background: rgba(0,0,0,0.55);
    color: white;
    font-size: 0.95rem;
}
.lead-scan-overlay.open { display: flex; }
.lead-scan-spinner {
    width: 56px; height: 56px;
    border: 4px solid rgba(255,255,255,0.25);
    border-top-color: white;
    border-radius: 50%;
    animation: leadScanSpin 0.9s linear infinite;
}
@keyframes leadScanSpin { to { transform: rotate(360deg); } }

@media (max-width: 760px) {
    .lead-modal-grid { grid-template-columns: 1fr; }
    .lead-table th:nth-child(3), .lead-table td:nth-child(3),
    .lead-table th:nth-child(5), .lead-table td:nth-child(5) { display: none; }
}

/* ========== 価格表 (Product-list pattern) ========== */
.pp-wrap { display: flex; flex-direction: column; gap: 1rem; }

.pp-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1.5rem;
}
.pp-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
}
.pp-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--gray-900);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.pp-card-title svg { color: var(--gray-500); }
.pp-card-sub {
    font-size: 0.825rem;
    color: var(--gray-600);
    margin-top: 0.3rem;
}
.pp-head-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.pp-sync-status {
    font-size: 0.75rem;
    color: var(--gray-500);
}

/* 検索バー（一覧フィルタ用） */
.pp-search-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
}
.pp-search-wrap svg {
    position: absolute;
    left: 0.6rem;
    color: var(--gray-400);
    pointer-events: none;
}
.pp-search-input {
    border: 1px solid var(--gray-300);
    background: white;
    padding: 0.45rem 0.85rem 0.45rem 1.95rem;
    border-radius: 7px;
    font-size: 0.85rem;
    color: var(--gray-900);
    width: 180px;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.pp-search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

/* 再インポートボタン（控えめなセカンダリ） */
.pp-reimport-btn {
    opacity: 0.7;
    transition: opacity 0.15s;
}
.pp-reimport-btn:hover { opacity: 1; }

/* 空状態ヒーロー（初回インポート CTA） */
.pp-empty-hero {
    padding: 3rem 1.5rem;
    text-align: center;
    border: 2px dashed var(--gray-300);
    border-radius: 12px;
    background: linear-gradient(180deg, var(--gray-50) 0%, white 60%);
}
.pp-empty-hero-icon {
    width: 72px; height: 72px;
    margin: 0 auto 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: white;
    color: var(--gray-400);
    border: 1px solid var(--gray-200);
}
.pp-empty-hero-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 0.6rem;
}
.pp-empty-hero-desc {
    font-size: 0.85rem;
    color: var(--gray-600);
    line-height: 1.6;
    margin-bottom: 1.25rem;
}
.pp-empty-hero-cta {
    font-size: 0.9rem !important;
    padding: 0.65rem 1.25rem !important;
}

/* お気に入りピン（item 6） */
.pp-product-row { position: relative; }
.pp-fav-btn {
    border: none;
    background: transparent;
    padding: 0.35rem;
    color: var(--gray-300);
    cursor: pointer;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: color 0.15s, background 0.15s, transform 0.12s;
}
.pp-fav-btn:hover { background: var(--gray-100); color: var(--gray-500); }
.pp-fav-btn.active { color: #f59e0b; }
.pp-fav-btn.active:hover { color: #d97706; }
.pp-fav-btn:active { transform: scale(0.92); }

.pp-divider-fav {
    color: #b45309;
}
.pp-divider-fav::before {
    content: '';
    width: 0;
    height: 0;
}

/* 行コピー機能 (item 5) - 詳細表 */
.pp-table-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 0.5rem;
}
.pp-table-toolbar .pp-search-wrap input { width: 200px; }
.pp-copy-cell {
    width: 36px;
    padding: 0.3rem 0.4rem;
    text-align: center;
    background: white;
    position: sticky;
    left: 0;
    z-index: 1;
}
.pp-data-table tr.pp-header td.pp-copy-cell { background: var(--gray-50); }
.pp-copy-btn {
    border: 1px solid var(--gray-200);
    background: white;
    padding: 0.25rem 0.4rem;
    border-radius: 5px;
    color: var(--gray-400);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}
.pp-copy-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-light);
}
.pp-copy-btn.copied {
    border-color: #10b981;
    background: #d1fae5;
    color: #047857;
}
.pp-data-table tbody tr:not(.pp-header):not(:hover) .pp-copy-btn {
    opacity: 0.4;
}
.pp-data-table tbody tr:hover .pp-copy-btn { opacity: 1; }

/* デスクトップ: カード版を隠す */
.pp-data-table-as-cards { display: none; }

/* ====== さっと価格を調べる: モーダルウィザード ====== */
.pp-quote-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 3rem 1rem 2rem;
    overflow-y: auto;
}
.pp-quote-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(2px);
}
.pp-quote-dialog {
    position: relative;
    background: white;
    border-radius: 14px;
    max-width: 720px;
    width: 100%;
    padding: 1.75rem 1.85rem;
    box-shadow: 0 20px 50px rgba(0,0,0,0.25);
    max-height: calc(100vh - 4rem);
    overflow-y: auto;
}
.pp-quote-close {
    position: absolute;
    top: 0.9rem;
    right: 0.9rem;
    border: none;
    background: var(--gray-100);
    color: var(--gray-700);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.1rem;
    line-height: 1;
    transition: background 0.15s;
}
.pp-quote-close:hover { background: var(--gray-200); }
.pp-quote-title { font-size: 1.15rem; margin: 0 0 0.3rem; color: var(--gray-900); font-weight: 700; }
.pp-quote-sub { color: var(--gray-600); font-size: 0.85rem; margin: 0 0 1.3rem; }

.pp-quote-step {
    margin-bottom: 1.2rem;
    padding: 1rem 1.1rem;
    background: var(--gray-50);
    border-radius: 10px;
}
.pp-quote-step-head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.7rem;
}
.pp-quote-num {
    width: 26px; height: 26px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
}
.pp-quote-step-title { font-weight: 700; color: var(--gray-900); }
.pp-quote-choices {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.5rem;
}
.pp-quote-choice {
    padding: 0.7rem 0.85rem;
    border: 2px solid var(--gray-200);
    background: white;
    border-radius: 9px;
    cursor: pointer;
    text-align: left;
    font-size: 0.85rem;
    transition: all 0.15s;
}
.pp-quote-choice:hover { border-color: var(--primary); }
.pp-quote-choice.active { border-color: var(--primary); background: var(--primary-light); }
.pp-quote-choice-title { font-weight: 700; color: var(--gray-900); }
.pp-quote-choice-sub { font-size: 0.7rem; color: var(--gray-500); margin-top: 0.15rem; }

.pp-quote-row {
    display: grid;
    grid-template-columns: 1fr 1.4fr;
    gap: 0.6rem;
    margin-bottom: 0.4rem;
}
.pp-quote-select { font-size: 0.88rem; }

.pp-quote-result {
    background: linear-gradient(135deg, #1e40af 0%, #4338ca 100%);
    color: white;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-top: 0.5rem;
}
.pp-quote-result-label { font-size: 0.78rem; opacity: 0.9; margin-bottom: 0.3rem; }
.pp-quote-result-price {
    font-size: 2.2rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}
.pp-quote-result-explain {
    margin-top: 0.7rem;
    padding-top: 0.7rem;
    border-top: 1px solid rgba(255,255,255,0.2);
    font-size: 0.8rem;
    opacity: 0.92;
    line-height: 1.5;
}
.pp-quote-result-actions { margin-top: 0.85rem; }
.pp-quote-result-actions .qb-action-btn {
    background: rgba(255,255,255,0.18);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
}
.pp-quote-result-actions .qb-action-btn:hover { background: rgba(255,255,255,0.28); }

/* ====== 商品ページ型ビュー（詳細ビューのデフォルト表示） ====== */
.pp-prod-wrap { display: flex; flex-direction: column; gap: 1rem; }
.pp-prod-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 0.85rem 1rem;
    background: var(--gray-50);
    border-radius: 10px;
}
.pp-prod-controls-label {
    font-size: 0.75rem;
    color: var(--gray-600);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.pp-variant-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    flex: 1;
}
.pp-variant-chip {
    padding: 0.35rem 0.8rem;
    background: white;
    border: 1px solid var(--gray-300);
    border-radius: 999px;
    cursor: pointer;
    font-size: 0.82rem;
    color: var(--gray-700);
    transition: all 0.15s;
}
.pp-variant-chip:hover { border-color: var(--primary); color: var(--primary); }
.pp-variant-chip.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    font-weight: 600;
}

/* ドロップダウン型バリアントセレクター */
.pp-prod-selector {
    display: flex;
    gap: 0.75rem;
    align-items: flex-end;
    flex-wrap: wrap;
    padding: 0.85rem 1rem;
    background: var(--gray-50);
    border-radius: 10px;
}
.pp-prod-selector-item { flex: 1; min-width: 200px; }
.pp-prod-selector-item label {
    display: block;
    font-size: 0.72rem;
    color: var(--gray-600);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.3rem;
}
.pp-prod-selector-item select {
    width: 100%;
    padding: 0.55rem 0.8rem;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    background: white;
    font-size: 0.92rem;
    color: var(--gray-900);
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.pp-prod-selector-item select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
.pp-prod-selector-item select:hover { border-color: var(--gray-400); }
.pp-prod-selector-meta {
    font-size: 0.78rem;
    color: var(--gray-500);
    align-self: center;
    margin-left: auto;
}
.pp-prod-frame {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 14px;
    overflow: hidden;
}
.pp-prod-grid {
    display: grid;
    grid-template-columns: 5fr 7fr;
    gap: 0;
}
@media (max-width: 900px) { .pp-prod-grid { grid-template-columns: 1fr; } }
.pp-prod-hero {
    background: linear-gradient(135deg, #1e40af 0%, #312e81 100%);
    color: white;
    padding: 2rem 1.75rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    min-height: 280px;
}
.pp-prod-hero.c-blue   { background: linear-gradient(135deg, #1e40af 0%, #312e81 100%); }
.pp-prod-hero.c-orange { background: linear-gradient(135deg, #c2410c 0%, #9a3412 100%); }
.pp-prod-hero.c-green  { background: linear-gradient(135deg, #047857 0%, #064e3b 100%); }
.pp-prod-hero.c-purple { background: linear-gradient(135deg, #6d28d9 0%, #4c1d95 100%); }
.pp-prod-hero.c-red    { background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%); }
.pp-prod-hero.c-gray   { background: linear-gradient(135deg, #4b5563 0%, #1f2937 100%); }
.pp-prod-hero-icon {
    width: 80px; height: 80px;
    margin-bottom: 1rem;
    background: rgba(255,255,255,0.15);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    color: white;
}
.pp-prod-hero-category {
    font-size: 0.74rem; opacity: 0.85; letter-spacing: 0.06em;
    text-transform: uppercase; margin-bottom: 0.3rem;
}
.pp-prod-hero-title {
    font-size: 1.4rem; font-weight: 800; margin: 0 0 0.45rem; line-height: 1.2;
}
.pp-prod-hero-spec { font-size: 0.85rem; opacity: 0.88; line-height: 1.5; }

.pp-prod-info { padding: 1.75rem 1.85rem; }
.pp-prod-info-mode {
    display: inline-flex;
    background: var(--gray-100);
    border-radius: 999px;
    padding: 0.22rem;
    margin-bottom: 1.1rem;
    flex-wrap: wrap;
    gap: 0.1rem;
}
.pp-prod-info-mode button {
    border: none;
    padding: 0.4rem 0.95rem;
    background: transparent;
    border-radius: 999px;
    cursor: pointer;
    font-size: 0.82rem;
    color: var(--gray-600);
    font-weight: 600;
    transition: all 0.15s;
    white-space: nowrap;
}
.pp-prod-info-mode button.active {
    background: white; color: var(--gray-900);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.pp-prod-price-hero { margin-bottom: 1.1rem; }
.pp-prod-price-label {
    font-size: 0.74rem; color: var(--gray-500); font-weight: 600; margin-bottom: 0.25rem;
}
.pp-prod-price-value {
    font-size: 2.6rem; font-weight: 800; color: var(--gray-900);
    line-height: 1.05; font-variant-numeric: tabular-nums;
}
.pp-prod-price-suffix { font-size: 0.92rem; color: var(--gray-500); font-weight: 600; margin-left: 0.4rem; }
.pp-prod-price-empty { color: var(--gray-400); font-weight: 600; font-size: 1.2rem; }

.pp-prod-tier-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.45rem;
    margin-bottom: 1.25rem;
}
.pp-prod-tier-btn {
    padding: 0.6rem 0.4rem;
    background: white;
    border: 2px solid var(--gray-200);
    border-radius: 9px;
    cursor: pointer;
    text-align: center;
    transition: all 0.15s;
}
.pp-prod-tier-btn:hover { border-color: var(--gray-300); }
.pp-prod-tier-btn.active.tier-S { border-color: #f59e0b; background: #fef3c7; }
.pp-prod-tier-btn.active.tier-A { border-color: #3b82f6; background: #dbeafe; }
.pp-prod-tier-btn.active.tier-B { border-color: #10b981; background: #d1fae5; }
.pp-prod-tier-btn-label { font-size: 0.68rem; color: var(--gray-600); font-weight: 600; }
.pp-prod-tier-btn-price {
    font-size: 0.85rem; color: var(--gray-900); font-weight: 700;
    font-variant-numeric: tabular-nums; margin-top: 0.2rem;
}

.pp-prod-specs {
    list-style: none; padding: 0; margin: 0;
    border-top: 1px solid var(--gray-200);
}
.pp-prod-specs li {
    display: flex; justify-content: space-between;
    padding: 0.55rem 0; border-bottom: 1px solid var(--gray-100);
    font-size: 0.85rem;
}
.pp-prod-specs li span:first-child { color: var(--gray-600); }
.pp-prod-specs li span:last-child { color: var(--gray-900); font-weight: 600; }
.pp-prod-cta { display: flex; gap: 0.55rem; margin-top: 1.1rem; }
.pp-prod-cta button {
    flex: 1; padding: 0.75rem 1rem; border-radius: 9px;
    font-size: 0.88rem; font-weight: 700; cursor: pointer;
    transition: all 0.15s;
}
.pp-prod-cta .primary { background: var(--primary); color: white; border: none; }
.pp-prod-cta .primary:hover { background: var(--primary-dark); }
.pp-prod-cta .secondary { background: white; color: var(--gray-700); border: 1px solid var(--gray-300); }
.pp-prod-cta .secondary:hover { border-color: var(--primary); color: var(--primary); }
.pp-prod-cta .pp-prod-hp-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 0.95rem;
    background: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.92rem;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.pp-prod-cta .pp-prod-hp-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-50, #eff6ff);
}

/* ====== 顧客視点ビュー（旧・互換用） ====== */
.pp-cust-list { display: flex; flex-direction: column; gap: 1.25rem; }
.pp-cust-section {
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    overflow: hidden;
    background: white;
}
.pp-cust-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.1rem;
    color: white;
    font-weight: 700;
}
.pp-cust-head.tier-S { background: linear-gradient(90deg, #b45309 0%, #d97706 100%); }
.pp-cust-head.tier-A { background: linear-gradient(90deg, #1e40af 0%, #3b82f6 100%); }
.pp-cust-head.tier-B { background: linear-gradient(90deg, #047857 0%, #10b981 100%); }
.pp-cust-head.tier-C { background: linear-gradient(90deg, #7c3aed 0%, #a855f7 100%); }
.pp-cust-head.tier-D { background: linear-gradient(90deg, #b91c1c 0%, #ef4444 100%); }
.pp-cust-head-tier {
    display: inline-flex;
    width: 28px; height: 28px;
    background: rgba(255,255,255,0.25);
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 800;
    flex-shrink: 0;
}
.pp-cust-head-title { font-size: 0.98rem; }
.pp-cust-head-desc {
    margin-left: auto;
    font-weight: 400;
    font-size: 0.78rem;
    opacity: 0.92;
}
.pp-cust-body { padding: 0; }
.pp-cust-table-head {
    display: grid;
    gap: 0.5rem;
    padding: 0.5rem 1.1rem;
    background: var(--gray-50);
    font-size: 0.7rem;
    color: var(--gray-600);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--gray-200);
}
.pp-cust-row {
    display: grid;
    gap: 0.5rem;
    padding: 0.7rem 1.1rem;
    border-bottom: 1px solid var(--gray-100);
    align-items: center;
}
.pp-cust-row:last-child { border-bottom: none; }
.pp-cust-row:hover { background: var(--gray-50); }
.pp-cust-product { font-weight: 600; color: var(--gray-900); font-size: 0.88rem; }
.pp-cust-spec { font-size: 0.72rem; color: var(--gray-500); margin-top: 0.15rem; }
.pp-cust-cell {
    text-align: right;
    font-variant-numeric: tabular-nums;
    color: var(--gray-900);
    font-size: 0.88rem;
}
.pp-cust-cell.sale { font-weight: 700; }
.pp-cust-cell.empty { color: var(--gray-300); }
.pp-cust-cell-rental { color: var(--gray-700); font-size: 0.85rem; }
.pp-cust-empty {
    padding: 1.5rem;
    text-align: center;
    color: var(--gray-500);
    font-size: 0.85rem;
}

/* ====== 正規化カード表示（フォールバック用） ====== */
.pp-norm-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.pp-norm-card {
    border: 1px solid var(--gray-200);
    background: white;
    border-radius: 10px;
    padding: 0.95rem 1.1rem;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.pp-norm-card:hover {
    border-color: var(--gray-300);
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
.pp-norm-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.7rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid var(--gray-100);
    flex-wrap: wrap;
}
.pp-norm-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.pp-norm-title .pp-badge {
    margin-left: 0.25rem;
}
.pp-norm-attrs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.5rem;
    margin-bottom: 0.7rem;
}
.pp-attr {
    display: inline-flex;
    flex-direction: column;
    gap: 0.1rem;
    font-size: 0.8rem;
    min-width: 0;
}
.pp-attr-label {
    font-size: 0.68rem;
    color: var(--gray-500);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.pp-attr-value {
    color: var(--gray-900);
    font-weight: 500;
    white-space: pre-wrap;
}
.pp-norm-prices {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.pp-norm-rank-group {
    display: grid;
    grid-template-columns: 60px 1fr;
    gap: 0.85rem;
    align-items: start;
    padding: 0.55rem 0.7rem;
    border-radius: 8px;
    background: var(--gray-50);
}
.pp-norm-rank-group.no-rank { grid-template-columns: 1fr; }
.pp-norm-rank-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--gray-700);
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    padding: 0.2rem 0.5rem;
    text-align: center;
    align-self: center;
}
.pp-norm-rank-label.rank-S { color: #b45309; background: #fef3c7; border-color: #fde68a; }
.pp-norm-rank-label.rank-A { color: #1e40af; background: #dbeafe; border-color: #bfdbfe; }
.pp-norm-rank-label.rank-B { color: #047857; background: #d1fae5; border-color: #a7f3d0; }
.pp-norm-rank-label.rank-C { color: #7c3aed; background: #ede9fe; border-color: #ddd6fe; }
.pp-norm-rank-label.rank-D { color: #b91c1c; background: #fee2e2; border-color: #fecaca; }
.pp-norm-price-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.5rem 1rem;
}
.pp-norm-price {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}
.pp-norm-price-label {
    font-size: 0.68rem;
    color: var(--gray-500);
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pp-norm-price-amount {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--gray-900);
    font-variant-numeric: tabular-nums;
}
.pp-norm-card-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--gray-100);
}
.pp-norm-card-actions .pp-copy-btn {
    padding: 0.3rem 0.7rem;
    font-size: 0.78rem;
    gap: 0.3rem;
}
.pp-norm-empty {
    padding: 2rem 1rem;
    text-align: center;
    color: var(--gray-500);
    border: 1px dashed var(--gray-200);
    border-radius: 10px;
}

/* モバイル: rank-group を縦並びに */
@media (max-width: 640px) {
    .pp-norm-rank-group { grid-template-columns: 1fr; }
    .pp-norm-price-list { grid-template-columns: repeat(2, 1fr); }
}

/* モバイル: テーブル → カード表示への切替 (item 8) */
@media (max-width: 640px) {
    .pp-search-input { width: 140px; }
    /* 詳細ビューでテーブルを隠してカードを表示 */
    .pp-detail-body .pp-data-table-wrap { display: none; }
    .pp-data-table-as-cards {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .pp-data-table-as-cards .pp-mcard {
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: 0.85rem 1rem;
        background: white;
    }
    .pp-data-table-as-cards .pp-mcard-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 0.5rem;
        padding-bottom: 0.4rem;
        border-bottom: 1px solid var(--gray-100);
    }
    .pp-data-table-as-cards .pp-mcard-row {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
        font-size: 0.825rem;
        padding: 0.3rem 0;
    }
    .pp-data-table-as-cards .pp-mcard-label {
        color: var(--gray-500);
        font-weight: 600;
    }
    .pp-data-table-as-cards .pp-mcard-value {
        color: var(--gray-900);
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .pp-data-table-as-cards .pp-mcard-actions {
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid var(--gray-100);
        display: flex;
        justify-content: flex-end;
    }
}

.pp-product-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.pp-product-row {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 0.85rem 1rem;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.15s;
    background: white;
}
.pp-product-row:hover {
    border-color: var(--primary);
    background: var(--primary-light);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.pp-product-icon {
    width: 44px; height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.pp-product-icon.c-blue   { background: #dbeafe; color: #1d4ed8; }
.pp-product-icon.c-orange { background: #ffedd5; color: #c2410c; }
.pp-product-icon.c-green  { background: #d1fae5; color: #047857; }
.pp-product-icon.c-purple { background: #ede9fe; color: #6d28d9; }
.pp-product-icon.c-red    { background: #fee2e2; color: #b91c1c; }
.pp-product-icon.c-gray   { background: var(--gray-100); color: var(--gray-600); }

.pp-product-info { flex: 1; min-width: 0; }
.pp-product-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--gray-900);
    line-height: 1.3;
}
.pp-product-sub {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 0.15rem;
}
.pp-product-action {
    border: 1px solid var(--gray-300);
    background: white;
    padding: 0.45rem 0.85rem;
    border-radius: 7px;
    font-size: 0.8rem;
    color: var(--gray-700);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s;
    pointer-events: none;
}
.pp-product-row:hover .pp-product-action {
    border-color: var(--primary);
    background: white;
    color: var(--primary);
}

.pp-divider {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 1rem 0 0.5rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.pp-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--gray-200);
}
.pp-empty-state {
    padding: 2.5rem 1rem;
    text-align: center;
    color: var(--gray-500);
    border: 2px dashed var(--gray-300);
    border-radius: 10px;
}

/* 詳細ビュー（軽く、表を主役に） */
.pp-detail {
    background: transparent;
    border: none;
    padding: 0;
}
.pp-detail-head {
    padding: 0 0 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.pp-back {
    border: none;
    background: transparent;
    padding: 0.3rem 0.5rem 0.3rem 0;
    font-size: 0.85rem;
    color: var(--gray-600);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: color 0.15s;
}
.pp-back:hover { color: var(--primary); }
.pp-detail-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0;
}
.pp-detail-body {
    padding: 0;
}
.pp-section {
    margin-bottom: 1.5rem;
}
.pp-section:last-child { margin-bottom: 0; }
.pp-section-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 0.6rem;
    padding-bottom: 0.4rem;
    border-bottom: 1px solid var(--gray-200);
}

/* サブタブ（製品内の複数資料を切替）— 軽い下線スタイル */
.pp-subtabs {
    display: flex;
    gap: 0;
    flex-wrap: wrap;
    margin-bottom: 0.6rem;
    border-bottom: 1px solid var(--gray-200);
}
.pp-subtab {
    padding: 0.55rem 0.95rem;
    border: none;
    border-bottom: 2px solid transparent;
    background: transparent;
    font-size: 0.85rem;
    color: var(--gray-600);
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.12s;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: -1px;
}
.pp-subtab:hover {
    color: var(--gray-900);
    background: var(--gray-50);
}
.pp-subtab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    font-weight: 700;
}
.pp-subtab .pp-subtab-count {
    font-size: 0.7rem;
    padding: 0 6px;
    background: var(--gray-100);
    color: var(--gray-600);
    border-radius: 10px;
}
.pp-subtab.active .pp-subtab-count {
    background: var(--primary-light);
    color: var(--primary-dark);
}

/* 表（既存リスト系ページの表現に揃える） */
.pp-data-table-wrap {
    overflow: auto;
    max-height: 600px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    background: white;
}
.pp-data-table {
    border-collapse: collapse;
    font-size: 0.875rem;
    width: max-content;
    min-width: 100%;
    background: white;
}
.pp-data-table td {
    padding: 0.7rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
    color: var(--gray-900);
    white-space: nowrap;
    background: white;
}
.pp-data-table tr:last-child td { border-bottom: none; }

/* ヘッダ行（gray-50 + 小さい uppercase） */
.pp-data-table tr.pp-header td {
    background: var(--gray-50);
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    position: sticky;
    top: 0;
    z-index: 2;
    border-bottom: 1px solid var(--gray-200);
    padding: 0.65rem 1rem;
}

/* 行ホバー */
.pp-data-table tbody tr:not(.pp-header):hover td { background: var(--gray-50); }

/* 数値セル: 右寄せ・等幅・色は控えめ */
.pp-data-table td.pp-num,
.pp-data-table td.pp-yen,
.pp-data-table td.pp-pct {
    text-align: right;
    font-variant-numeric: tabular-nums;
    color: var(--gray-900);
}

/* シリーズバッジ（先頭列が「製品シリーズ」のとき適用） */
.pp-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.22rem 0.75rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    white-space: nowrap;
    line-height: 1.3;
}

/* サブセクションのメタ情報（行数など） */
.pp-section-meta {
    font-size: 0.72rem;
    color: var(--gray-400);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* 層カード（B風プラン比較スタイル） */
.pp-rank-cards {
    display: flex;
    gap: 0.85rem;
    flex-wrap: wrap;
    margin: 0.5rem 0 1.5rem;
}
.pp-rank-card {
    flex: 1 1 140px;
    max-width: 200px;
    position: relative;
    padding: 1.1rem 0.85rem 0.95rem;
    border: 2px solid var(--gray-200);
    background: white;
    border-radius: 12px;
    cursor: pointer;
    text-align: center;
    transition: all 0.15s;
    overflow: hidden;
}
.pp-rank-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--gray-300);
}
.pp-rank-card.rank-S::before { background: linear-gradient(90deg, #8b5cf6, #c084fc); }
.pp-rank-card.rank-A::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.pp-rank-card.rank-B::before { background: linear-gradient(90deg, #10b981, #34d399); }
.pp-rank-card.rank-C::before { background: linear-gradient(90deg, #f97316, #fb923c); }
.pp-rank-card.rank-D::before { background: linear-gradient(90deg, #ef4444, #f87171); }
.pp-rank-card:hover {
    border-color: var(--gray-400);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.07);
}
.pp-rank-card.active {
    border-color: var(--gray-900);
    background: var(--gray-50);
}
.pp-rank-card.active.rank-S { border-color: #8b5cf6; }
.pp-rank-card.active.rank-A { border-color: #3b82f6; }
.pp-rank-card.active.rank-B { border-color: #10b981; }
.pp-rank-card.active.rank-C { border-color: #f97316; }
.pp-rank-card.active.rank-D { border-color: #ef4444; }
.pp-rank-card-letter {
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--gray-900);
    line-height: 1;
    letter-spacing: -0.02em;
}
.pp-rank-card-label {
    font-size: 0.8rem;
    color: var(--gray-600);
    margin-top: 0.3rem;
    font-weight: 600;
}
.pp-rank-card-desc {
    font-size: 0.72rem;
    color: var(--gray-500);
    margin-top: 0.5rem;
    line-height: 1.4;
}
.pp-rank-card-check {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 22px; height: 22px;
    background: var(--gray-900);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    opacity: 0;
    transition: opacity 0.15s;
}
.pp-rank-card.active .pp-rank-card-check { opacity: 1; }
.pp-rank-card.active.rank-S .pp-rank-card-check { background: #8b5cf6; }
.pp-rank-card.active.rank-A .pp-rank-card-check { background: #3b82f6; }
.pp-rank-card.active.rank-B .pp-rank-card-check { background: #10b981; }
.pp-rank-card.active.rank-C .pp-rank-card-check { background: #f97316; }
.pp-rank-card.active.rank-D .pp-rank-card-check { background: #ef4444; }
.pp-rank-card-badge {
    position: absolute;
    top: -9px;
    right: 16px;
    background: #ef4444;
    color: white;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.18rem 0.55rem;
    border-radius: 4px;
    box-shadow: 0 2px 5px rgba(239,68,68,0.3);
}

/* シリーズフィルタ（コンテナ枠なし・インライン） */
.pp-series-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin: 0 0 0.9rem;
}
.pp-series-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-right: 0.3rem;
}
.pp-series-pill {
    padding: 0.28rem 0.7rem;
    border: 1px solid var(--gray-200);
    background: white;
    border-radius: 999px;
    font-size: 0.78rem;
    color: var(--gray-700);
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.12s;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.pp-series-pill:hover { border-color: var(--gray-400); }
.pp-series-pill.active {
    background: var(--gray-900);
    border-color: var(--gray-900);
    color: white;
    font-weight: 600;
}
.pp-series-pill .pp-series-count {
    font-size: 0.7rem;
    color: var(--gray-500);
}
.pp-series-pill.active .pp-series-count {
    color: rgba(255,255,255,0.7);
}

/* ========== 旧スタイルは念のため残す（互換性のため） ========== */
.pl-wrap { display: flex; flex-direction: column; gap: 1.25rem; }

/* ヒーロー部（最終同期・操作） — manuals.php の search-hero と揃える */
.pl-hero {
    background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
    border: 1px solid var(--gray-200);
    border-radius: 14px;
    padding: 1.1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.pl-hero-status { display: flex; flex-direction: column; gap: 0.2rem; }
.pl-hero-title {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 0.15rem;
}
.pl-hero-meta {
    display: flex;
    gap: 1.25rem;
    flex-wrap: wrap;
    font-size: 0.875rem;
    color: var(--gray-800);
}
.pl-hero-meta .pl-meta-label { color: var(--gray-500); margin-right: 0.35rem; }
.pl-hero-meta strong { font-weight: 700; color: var(--gray-900); }
.pl-hero-actions { display: flex; gap: 0.5rem; align-items: center; }

/* 空状態 — manuals.php の empty / qb-empty に揃える */
.pl-empty {
    border: 2px dashed var(--gray-300);
    border-radius: 12px;
    padding: 3rem 1rem;
    text-align: center;
    color: var(--gray-500);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}
.pl-empty .empty-title {
    font-size: 1rem;
    color: var(--gray-700);
    font-weight: 600;
    margin-bottom: 0.25rem;
}

/* 本体: サイドバー + テーブル ― qb-card と同じカードトーン */
.pl-content {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 1rem;
    min-height: 560px;
}
.pl-sidebar {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.pl-sidebar-title {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0 0.15rem;
}
.pl-search { font-size: 0.875rem; }
.pl-sheet-list {
    overflow-y: auto;
    max-height: 600px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.pl-sheet-item {
    padding: 0.5rem 0.7rem;
    border-radius: 6px;
    font-size: 0.85rem;
    color: var(--gray-800);
    cursor: pointer;
    border: 1px solid transparent;
    background: transparent;
    text-align: left;
    transition: background 0.12s, color 0.12s, border-color 0.12s;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.4rem;
}
.pl-sheet-item:hover { background: var(--gray-50); }
.pl-sheet-item.active {
    background: var(--primary-light);
    color: var(--primary-dark);
    border-color: var(--primary);
    font-weight: 600;
}
.pl-sheet-item .pl-rows {
    font-size: 0.7rem;
    color: var(--gray-400);
    background: var(--gray-100);
    border-radius: 10px;
    padding: 1px 7px;
    flex-shrink: 0;
}
.pl-sheet-item.active .pl-rows {
    background: white;
    color: var(--primary-dark);
}

.pl-main {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.pl-sheet-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 0.85rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.pl-sheet-title svg { color: var(--gray-400); flex-shrink: 0; }
.pl-sheet-body {
    overflow: auto;
    flex: 1;
    max-height: 640px;
}
.pl-table {
    border-collapse: collapse;
    font-size: 0.8125rem;
    width: max-content;
    min-width: 100%;
    background: white;
}
.pl-table td {
    padding: 0.4rem 0.65rem;
    border: 1px solid var(--gray-200);
    white-space: pre-wrap;
    vertical-align: top;
    color: var(--gray-900);
    min-width: 60px;
    max-width: 240px;
    word-break: break-word;
}
.pl-table tr:first-child td {
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-800);
    position: sticky;
    top: 0;
    z-index: 1;
}

@media (max-width: 900px) {
    .pl-content { grid-template-columns: 1fr; }
    .pl-sheet-list { max-height: 240px; }
    .pl-main { padding: 1rem; }
}

/* ========== AI見積アシスタント ========== */
.qb-ai-row {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: linear-gradient(135deg, #f3e8ff, #e0f2fe);
    border: 1px solid #d8b4fe;
    border-radius: 12px;
    flex-wrap: wrap;
}
.qb-ai-btn {
    background: linear-gradient(135deg, #7c3aed, #2563eb);
    color: white;
    border: none;
    padding: 0.55rem 1.1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.25);
    transition: transform 0.15s, box-shadow 0.15s;
}
.qb-ai-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35);
}
.qb-ai-btn:active { transform: translateY(0); }
.qb-ai-hint {
    font-size: 0.8125rem;
    color: var(--gray-700);
    flex: 1;
    min-width: 200px;
}
.qb-ai-help {
    font-size: 0.8125rem;
    color: var(--gray-700);
    background: var(--gray-50);
    padding: 0.6rem 0.85rem;
    border-radius: 6px;
    margin-bottom: 0.75rem;
    line-height: 1.55;
}
.qb-ai-help em {
    font-style: normal;
    color: var(--primary-dark);
    background: white;
    padding: 0.05rem 0.4rem;
    border-radius: 3px;
}
.qb-ai-warn {
    margin-top: 0.6rem;
    padding: 0.5rem 0.75rem;
    background: #fef3c7;
    border-left: 3px solid var(--warning);
    border-radius: 4px;
    font-size: 0.8125rem;
    color: #92400e;
}
</style>
