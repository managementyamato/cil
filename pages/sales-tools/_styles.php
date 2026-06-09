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

/* リード編集モーダル: フォーム内セクションタイトル */
.lead-section-title {
    font-size: 0.8rem; font-weight: 700; color: var(--gray-500);
    text-transform: none; letter-spacing: 0.02em;
    margin: 1rem 0 0.4rem; padding-bottom: 0.25rem;
    border-bottom: 1px solid var(--gray-100);
}
.lead-section-title:first-child { margin-top: 0; }

/* 3カラム グリッド (商談情報: 納期/確度/見積書) */
.qb-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}
@media (max-width: 600px) {
    .qb-grid-3 { grid-template-columns: 1fr; }
}

/* 確度バッジ (一覧テーブル内) */
.lead-confidence {
    display: inline-block; padding: 2px 8px; border-radius: 4px;
    font-size: 0.78rem; font-weight: 700; color: #fff;
    background: var(--gray-300);
}
.lead-confidence.c1 { background: #94a3b8; }
.lead-confidence.c2 { background: #60a5fa; }
.lead-confidence.c3 { background: #fbbf24; color: #713f12; }
.lead-confidence.c4 { background: #fb923c; }
.lead-confidence.c5 { background: #ef4444; }

/* 製品名 (一覧テーブル) */
.lead-product-name { font-weight: 600; }

/* ディーラー名リンク (一覧テーブル) */
.lead-company-link {
    color: var(--primary, #0d9488);
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
}
.lead-company-link:hover {
    text-decoration: underline;
}

/* ディーラー列: 情報 + アイコンを横並び */
.lead-dealer-cell {
    display: flex; align-items: center; justify-content: space-between; gap: .5rem;
}
.lead-dealer-info { flex: 1; min-width: 0; }

/* 電話・メール アイコン (contacts.php の tel-link / mail-link 準拠) */
.lead-contact-icons {
    display: flex; flex-direction: column; gap: .3rem; flex-shrink: 0;
}
.lead-tel-link, .lead-mail-link {
    display: inline-flex; align-items: center; justify-content: center;
    width: 26px; height: 26px;
    border-radius: 6px; text-decoration: none; transition: background .1s;
}
.lead-tel-link {
    color: var(--primary, #0d9488); background: #eff6ff;
}
.lead-tel-link:hover { background: #dbeafe; }
.lead-mail-link {
    color: #059669; background: #ecfdf5;
}
.lead-mail-link:hover { background: #d1fae5; }

/* モーダル内部の 2 カラムレイアウト (名刺画像 + フィールド) */
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

/* ── リード サブタブ (v2 Phase 2: 名刺 / リード) ── */
.lead-subtabs {
    display: flex; gap: 0.25rem;
    border-bottom: 1px solid var(--gray-200);
    margin-bottom: 1.25rem; flex-wrap: nowrap; overflow-x: auto;
}
.lead-subtab {
    padding: 0.55rem 1rem;
    background: transparent; border: none;
    border-bottom: 2px solid transparent;
    color: var(--gray-600); font-size: 0.88rem; font-weight: 500;
    cursor: pointer;
    display: inline-flex; align-items: center; gap: 0.4rem;
    transition: color .15s, border-color .15s, background .15s;
    border-radius: 6px 6px 0 0; white-space: nowrap;
}
.lead-subtab:hover { color: var(--gray-900); background: var(--gray-50); }
.lead-subtab.active {
    color: var(--primary, #2563eb);
    border-bottom-color: var(--primary, #2563eb);
    font-weight: 600;
}
.lead-subtab-count {
    display: inline-block; font-size: 0.7rem; font-weight: 500;
    padding: 1px 7px; border-radius: 9px;
    background: var(--gray-200); color: var(--gray-600);
}
.lead-subtab.active .lead-subtab-count { background: #dbeafe; color: #1e3a8a; }
.lead-subpanel { display: none; }
.lead-subpanel.active { display: block; }
.lead-subpanel[hidden] { display: none !important; }

/* 名刺 状態バッジ */
.lead-card-promoted-pill {
    display: inline-block; font-size: 0.7rem; font-weight: 600;
    padding: 1px 7px; border-radius: 8px;
    background: #dcfce7; color: #14532d;
}
.lead-card-unpromoted-pill {
    display: inline-block; font-size: 0.7rem; font-weight: 600;
    padding: 1px 7px; border-radius: 8px;
    background: var(--gray-100); color: var(--gray-600);
}

/* ── リード タイムライン (v2 Phase 1) ── */
.lead-timeline-section {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
}
.lead-timeline-head {
    display: flex; align-items: baseline; justify-content: space-between;
    gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem;
}
.lead-timeline-title {
    margin: 0; font-size: 0.95rem; color: var(--gray-800); display: flex; align-items: center;
}
.lead-timeline-hint { font-size: 0.75rem; color: var(--gray-500); }
.lead-timeline-add {
    display: grid;
    grid-template-columns: 130px 1fr auto;
    gap: 0.5rem; align-items: flex-start; margin-bottom: 0.85rem;
}
.lead-timeline-type { font-size: 0.85rem; padding: 0.45rem 0.55rem; }
.lead-timeline-body { font-size: 0.85rem; resize: vertical; min-height: 60px; }
@media (max-width: 600px) {
    .lead-timeline-add { grid-template-columns: 1fr; }
}
.lead-timeline-list {
    max-height: 280px; overflow-y: auto;
    padding: 0.25rem 0.5rem 0.25rem 0;
}
.lead-timeline-loading,
.lead-timeline-empty {
    color: var(--gray-400); font-size: 0.85rem; padding: 0.75rem 0; text-align: center;
}
.lead-timeline-item {
    display: grid;
    grid-template-columns: 14px 1fr;
    gap: 0.6rem;
    padding: 0.5rem 0;
    position: relative;
}
.lead-timeline-item::before {
    content: ''; position: absolute;
    left: 6px; top: 18px; bottom: -2px; width: 2px; background: var(--gray-200);
}
.lead-timeline-item:last-child::before { display: none; }
.lead-timeline-dot {
    width: 14px; height: 14px; border-radius: 50%;
    background: var(--gray-300); margin-top: 4px;
    border: 2px solid white; box-shadow: 0 0 0 1px var(--gray-300);
}
.lead-timeline-item[data-type=status_change] .lead-timeline-dot { background: var(--primary, #2563eb); box-shadow: 0 0 0 1px var(--primary, #2563eb); }
.lead-timeline-item[data-type=meeting]       .lead-timeline-dot { background: #16a34a; box-shadow: 0 0 0 1px #16a34a; }
.lead-timeline-item[data-type=quote]         .lead-timeline-dot { background: #f59e0b; box-shadow: 0 0 0 1px #f59e0b; }
.lead-timeline-item[data-type=promotion]     .lead-timeline-dot { background: #7c3aed; box-shadow: 0 0 0 1px #7c3aed; }
.lead-timeline-body-text {
    background: var(--gray-50); border-radius: 8px; padding: 0.5rem 0.7rem;
    font-size: 0.85rem; color: var(--gray-700); line-height: 1.5;
}
.lead-timeline-meta {
    display: flex; align-items: center; gap: 0.5rem; font-size: 0.72rem; color: var(--gray-500);
    margin-top: 0.25rem; flex-wrap: wrap;
}
.lead-timeline-type-pill {
    display: inline-block; font-size: 0.65rem; font-weight: 600; padding: 1px 6px;
    border-radius: 8px; background: var(--gray-200); color: var(--gray-700);
}
.lead-timeline-item[data-type=status_change] .lead-timeline-type-pill { background: #dbeafe; color: #1e3a8a; }
.lead-timeline-item[data-type=meeting]       .lead-timeline-type-pill { background: #dcfce7; color: #14532d; }
.lead-timeline-item[data-type=quote]         .lead-timeline-type-pill { background: #fef3c7; color: #92400e; }
.lead-timeline-item[data-type=promotion]     .lead-timeline-type-pill { background: #ede9fe; color: #5b21b6; }
.lead-timeline-status-arrow {
    font-weight: 600; color: var(--gray-700);
}
.lead-timeline-delete {
    margin-left: auto; background: none; border: none; color: var(--gray-400);
    cursor: pointer; font-size: 0.72rem; padding: 0 4px;
}
.lead-timeline-delete:hover { color: var(--danger, #dc2626); }

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

/* ========== カードリスト共通 (Yamato Compass スタイル) ========== */
/* リード一覧もカード形式で表示 */
.lead-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.lead-card-person {
    font-weight: 400;
    font-size: .8rem;
    color: var(--gray-500, #6b7280);
    margin-left: .35rem;
}
/* リードカードはクリックで詳細を開ける */
.lead-list .bc-card {
    cursor: pointer;
}

/* ========== 名刺カードリスト ========== */
.bc-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.bc-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: .75rem 1rem;
    border-bottom: 1px solid var(--gray-200, #e5e7eb);
    transition: background .12s;
}
.bc-card:last-child { border-bottom: none; }
.bc-card:hover { background: var(--gray-50, #f9fafb); }

.bc-card-left {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex: 1;
    min-width: 0;
}

/* イニシャルアバター */
.bc-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-light, #ccfbf1);
    color: var(--primary, #0d9488);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: .85rem;
    flex-shrink: 0;
    letter-spacing: -.02em;
}

.bc-card-info {
    min-width: 0;
    flex: 1;
}
.bc-card-name {
    font-weight: 600;
    font-size: .9rem;
    color: var(--gray-900, #111827);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bc-card-sub {
    font-size: .78rem;
    color: var(--gray-500, #6b7280);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: .1rem;
}
.bc-card-meta {
    font-size: .72rem;
    color: var(--gray-400, #9ca3af);
    margin-top: .15rem;
}

/* 右側アクション */
.bc-card-actions {
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-shrink: 0;
}

/* リード昇格ボタン */
.bc-action-btn.bc-promote {
    padding: .25rem .65rem;
    font-size: .75rem;
    font-weight: 600;
    border: 1px solid var(--primary, #0d9488);
    color: var(--primary, #0d9488);
    background: white;
    border-radius: 6px;
    cursor: pointer;
    white-space: nowrap;
    transition: background .12s, color .12s;
}
.bc-action-btn.bc-promote:hover {
    background: var(--primary, #0d9488);
    color: white;
}

/* 昇格済みラベル */
.bc-promoted-label {
    padding: .2rem .5rem;
    font-size: .7rem;
    font-weight: 600;
    color: var(--gray-400, #9ca3af);
    background: var(--gray-100, #f3f4f6);
    border-radius: 4px;
    white-space: nowrap;
}

/* アイコンボタン共通 */
.bc-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: none;
    background: var(--gray-100, #f3f4f6);
    color: var(--gray-500, #6b7280);
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    transition: background .12s, color .12s;
    padding: 0;
}
.bc-icon-btn:hover {
    background: var(--gray-200, #e5e7eb);
    color: var(--gray-700, #374151);
}
a.bc-icon-btn:hover {
    color: var(--primary, #0d9488);
    background: var(--primary-light, #ccfbf1);
}
.bc-icon-btn.bc-icon-danger:hover {
    background: #fee2e2;
    color: #dc2626;
}

/* モバイル: アクションを折り返し */
@media (max-width: 640px) {
    .bc-card {
        flex-wrap: wrap;
        gap: .5rem;
    }
    .bc-card-actions {
        width: 100%;
        justify-content: flex-end;
        padding-top: .25rem;
        border-top: 1px solid var(--gray-100, #f3f4f6);
    }
}
</style>
