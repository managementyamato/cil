<?php
/**
 * 営業ツール
 *
 * 製品情報・価格表・営業資料を一画面に集約。
 * - 製品別: 各製品のサマリーカード(価格表/カタログ/スクリプトへの導線)
 * - 価格表: 製品ごと・ランク(S/A/B)ごとの単価
 * - カタログ: 製品PDF・画像
 * - トークスクリプト: 営業トーク
 * - 見積履歴: 過去見積参照
 * - 見積作成: 新規見積生成
 *
 * 閲覧権限: sales(営業部全員に開放、情報非対称性を作らない方針)
 */
require_once '../api/auth.php';
require_once '../functions/header.php';
require_once '../functions/sales-master.php';

$csrfToken      = generateCsrfToken();
$canEditLead    = hasPermission('sales');   // sales 以上で編集可
$canDeleteLead  = isAdmin();                 // 削除は admin のみ
$currentUserName = $_SESSION['user_name'] ?? '';

// 現在のタブ
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'products';
$allowedTabs = ['products', 'pricing', 'catalogs', 'scripts', 'history', 'leads', 'create'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'products';
}

// 製品マスター(暫定: ハードコーディング。M3以降は products テーブル等から取得予定)
$products = [
    [
        'id' => 'monitarou',
        'name_ja' => 'モニたろう',
        'name_en' => 'Monitarou',
        'category' => 'LEDビジョン',
        'description' => '建設現場専用LEDビジョン',
        'has_price' => true,
        'catalog_count' => 5,
        'script_count' => 2,
        'web_url' => 'https://example.com/monitarou',
    ],
    [
        'id' => 'monisuke',
        'name_ja' => 'モニすけ',
        'name_en' => 'Monisuke',
        'category' => '屋外用液晶ディスプレイ',
        'description' => '防塵防水屋外用液晶ディスプレイ',
        'has_price' => true,
        'catalog_count' => 5,
        'script_count' => 1,
        'web_url' => 'https://example.com/monisuke',
    ],
    [
        'id' => 'monimaru',
        'name_ja' => 'モニまる',
        'name_en' => 'Monimaru',
        'category' => '電子黒板',
        'description' => '仮設事務所でも活躍するインタラクティブタッチモニター',
        'has_price' => true,
        'catalog_count' => 5,
        'script_count' => 2,
        'web_url' => 'https://example.com/monimaru',
    ],
];
?>
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
}
.st-product-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    transform: translateY(-2px);
    border-color: var(--primary);
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

<div class="sales-tools-page">

    <!-- ヘッダー -->
    <div class="st-header">
        <div class="st-header-icon" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
            </svg>
        </div>
        <div class="st-header-text">
            <h2>営業ツール</h2>
            <p class="st-subtitle">製品情報・価格表・営業資料</p>
        </div>
    </div>

    <!-- 検索 -->
    <div class="st-search-wrapper">
        <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" class="st-search-input form-input" id="stSearchInput" placeholder="製品名や資料を検索...">
    </div>

    <!-- タブ -->
    <nav class="st-tabs" role="tablist">
        <a href="?tab=products" class="st-tab <?= $activeTab === 'products' ? 'active' : '' ?>" data-tab="products" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2"/>
                <line x1="8" y1="21" x2="16" y2="21"/>
                <line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
            製品別
        </a>
        <a href="?tab=pricing" class="st-tab <?= $activeTab === 'pricing' ? 'active' : '' ?>" data-tab="pricing" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            価格表
        </a>
        <a href="?tab=catalogs" class="st-tab <?= $activeTab === 'catalogs' ? 'active' : '' ?>" data-tab="catalogs" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
            </svg>
            カタログ
        </a>
        <a href="?tab=scripts" class="st-tab <?= $activeTab === 'scripts' ? 'active' : '' ?>" data-tab="scripts" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            トークスクリプト
        </a>
        <a href="?tab=history" class="st-tab <?= $activeTab === 'history' ? 'active' : '' ?>" data-tab="history" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            見積履歴
        </a>
        <a href="?tab=leads" class="st-tab <?= $activeTab === 'leads' ? 'active' : '' ?>" data-tab="leads" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            リード管理
        </a>
        <a href="?tab=create" class="st-tab cta <?= $activeTab === 'create' ? 'active' : '' ?>" data-tab="create" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="9" y1="13" x2="15" y2="13"/>
                <line x1="12" y1="10" x2="12" y2="16"/>
            </svg>
            見積作成
        </a>
    </nav>

    <!-- 製品別 -->
    <div class="st-panel <?= $activeTab === 'products' ? 'active' : '' ?>" id="panel-products" role="tabpanel">
        <div class="st-product-grid" id="stProductGrid">
            <?php foreach ($products as $p): ?>
            <div class="st-product-card" data-search-name="<?= htmlspecialchars(mb_strtolower($p['name_ja'] . ' ' . $p['name_en'] . ' ' . $p['category'] . ' ' . $p['description'])) ?>">
                <?php if (!empty($p['web_url'])): ?>
                <a href="<?= htmlspecialchars($p['web_url']) ?>" class="st-product-web-link" target="_blank" rel="noopener" title="製品Webサイト" aria-label="製品Webサイトを開く">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                </a>
                <?php endif; ?>
                <div class="st-product-visual" aria-hidden="true">
                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2"/>
                        <line x1="8" y1="21" x2="16" y2="21"/>
                        <line x1="12" y1="17" x2="12" y2="21"/>
                    </svg>
                </div>
                <div>
                    <p class="st-product-name"><?= htmlspecialchars($p['name_ja']) ?></p>
                    <div class="st-product-name-en"><?= htmlspecialchars($p['name_en']) ?></div>
                </div>
                <p class="st-product-description"><?= htmlspecialchars($p['category']) ?> - <?= htmlspecialchars($p['description']) ?></p>
                <div class="st-product-tags">
                    <?php if (!empty($p['has_price'])): ?>
                    <span class="st-tag price">価格表</span>
                    <?php endif; ?>
                    <?php if ((int)$p['catalog_count'] > 0): ?>
                    <span class="st-tag catalog">カタログ <?= (int)$p['catalog_count'] ?>件</span>
                    <?php endif; ?>
                    <?php if ((int)$p['script_count'] > 0): ?>
                    <span class="st-tag script">スクリプト <?= (int)$p['script_count'] ?>件</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="st-empty" id="stEmptyState" style="display: none;">
            <div class="empty-title">該当する製品がありません</div>
            <div>検索キーワードを変更してください</div>
        </div>
    </div>

    <!-- 価格表 -->
    <div class="st-panel <?= $activeTab === 'pricing' ? 'active' : '' ?>" id="panel-pricing" role="tabpanel">
        <div class="pp-wrap">

            <!-- 一覧ビュー -->
            <div class="pp-card" id="ppListView">
                <div class="pp-card-head">
                    <div>
                        <div class="pp-card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="9" y1="13" x2="15" y2="13"/>
                                <line x1="9" y1="17" x2="15" y2="17"/>
                            </svg>
                            価格表一覧
                        </div>
                        <div class="pp-card-sub">クリックすると価格表をサイト内で表示します</div>
                    </div>
                    <div class="pp-head-actions">
                        <span id="ppSyncStatus" class="pp-sync-status">—</span>
                        <?php if (isAdmin()): ?>
                        <button type="button" class="qb-action-btn" id="ppSyncBtn" title="Google から価格表データを再取得">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 4 23 10 17 10"/>
                                <polyline points="1 20 1 14 7 14"/>
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                            </svg>
                            同期
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pp-product-list" id="ppProductList">
                    <!-- JS で動的描画 -->
                </div>
            </div>

            <!-- 詳細ビュー（クリック後表示） -->
            <div class="pp-detail" id="ppDetailView" style="display:none;">
                <div class="pp-detail-head">
                    <button type="button" class="pp-back" id="ppBack">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="19" y1="12" x2="5" y2="12"/>
                            <polyline points="12 19 5 12 12 5"/>
                        </svg>
                        価格表一覧に戻る
                    </button>
                    <h3 class="pp-detail-title" id="ppDetailTitle">—</h3>
                </div>
                <div class="pp-detail-body" id="ppDetailBody"></div>
            </div>

        </div>
    </div>

    <!-- カタログ -->
    <div class="st-panel <?= $activeTab === 'catalogs' ? 'active' : '' ?>" id="panel-catalogs" role="tabpanel">
        <div class="st-empty">
            <div class="empty-title">カタログ(準備中)</div>
            <div>M4で製品PDF・サプライヤー画像を一元集約予定。</div>
        </div>
    </div>

    <!-- トークスクリプト -->
    <div class="st-panel <?= $activeTab === 'scripts' ? 'active' : '' ?>" id="panel-scripts" role="tabpanel">
        <div class="st-empty">
            <div class="empty-title">トークスクリプト(準備中)</div>
            <div>製品ごとの営業トーク。M4で実装予定。</div>
        </div>
    </div>

    <!-- 見積履歴 -->
    <div class="st-panel <?= $activeTab === 'history' ? 'active' : '' ?>" id="panel-history" role="tabpanel">
        <div class="st-empty">
            <div class="empty-title">見積履歴(準備中)</div>
            <div>過去の見積・成約価格を検索。M4で実装予定。</div>
        </div>
    </div>

    <!-- リード管理 -->
    <div class="st-panel <?= $activeTab === 'leads' ? 'active' : '' ?>" id="panel-leads" role="tabpanel">
        <div class="lead-wrap">

            <!-- ツールバー -->
            <div class="lead-toolbar">
                <div class="lead-toolbar-left">
                    <input type="text" id="leadSearch" class="form-input lead-search" placeholder="会社名・氏名・電話・メールで検索...">
                    <select id="leadStatusFilter" class="form-input lead-status-filter">
                        <option value="">すべてのステータス</option>
                        <option value="新規">新規</option>
                        <option value="接触済">接触済</option>
                        <option value="商談中">商談中</option>
                        <option value="成約">成約</option>
                        <option value="失注">失注</option>
                    </select>
                </div>
                <div class="lead-toolbar-right">
                    <button type="button" class="qb-action-btn" id="leadAddBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        手動で追加
                    </button>
                    <button type="button" class="qb-action-btn primary" id="leadScanBtn" title="複数枚まとめて選択できます">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                            <circle cx="12" cy="13" r="4"/>
                        </svg>
                        名刺をスキャン
                    </button>
                    <input type="file" id="leadScanInput" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" multiple style="display:none;">
                </div>
            </div>

            <!-- ステータス別件数 -->
            <div class="lead-status-summary" id="leadStatusSummary"></div>

            <!-- 一覧テーブル -->
            <div class="lead-table-wrap">
                <table class="lead-table" id="leadTable">
                    <thead>
                        <tr>
                            <th style="width: 28%;">会社・氏名</th>
                            <th style="width: 22%;">連絡先</th>
                            <th style="width: 18%;">役職・部署</th>
                            <th style="width: 10%;">ステータス</th>
                            <th style="width: 12%;">登録日</th>
                            <th style="width: 10%;">操作</th>
                        </tr>
                    </thead>
                    <tbody id="leadTbody"></tbody>
                </table>
                <div class="lead-empty" id="leadEmpty" style="display:none;">
                    <div class="lead-empty-title">リードがまだ登録されていません</div>
                    <div class="lead-empty-sub">「名刺をスキャン」または「手動で追加」から登録してください</div>
                </div>
            </div>
        </div>
    </div>

    <!-- リード編集モーダル -->
    <div class="lead-modal" id="leadModal" aria-hidden="true">
        <div class="lead-modal-backdrop" data-close-modal></div>
        <div class="lead-modal-dialog">
            <div class="lead-modal-head">
                <h3 id="leadModalTitle">リード登録</h3>
                <button type="button" class="lead-modal-close" data-close-modal aria-label="閉じる">×</button>
            </div>
            <div class="lead-modal-body">
                <div class="lead-modal-grid">
                    <div class="lead-modal-image" id="leadModalImageWrap" style="display:none;">
                        <img id="leadModalImage" alt="名刺画像">
                    </div>
                    <div class="lead-modal-fields">
                        <div class="qb-grid-2">
                            <div class="form-group">
                                <label for="leadFCompany">会社名 <span class="lead-required">*</span></label>
                                <input type="text" id="leadFCompany" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFPerson">氏名</label>
                                <input type="text" id="leadFPerson" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFTitle">役職</label>
                                <input type="text" id="leadFTitle" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFDept">部署</label>
                                <input type="text" id="leadFDept" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFPhone">電話</label>
                                <input type="text" id="leadFPhone" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFMobile">携帯</label>
                                <input type="text" id="leadFMobile" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFEmail">メール</label>
                                <input type="text" id="leadFEmail" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFFax">FAX</label>
                                <input type="text" id="leadFFax" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFWebsite">Webサイト</label>
                                <input type="text" id="leadFWebsite" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFAm">担当営業</label>
                                <input type="text" id="leadFAm" class="form-input">
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="leadFAddress">住所</label>
                                <input type="text" id="leadFAddress" class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="leadFStatus">ステータス</label>
                                <select id="leadFStatus" class="form-input">
                                    <option value="新規">新規</option>
                                    <option value="接触済">接触済</option>
                                    <option value="商談中">商談中</option>
                                    <option value="成約">成約</option>
                                    <option value="失注">失注</option>
                                </select>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="leadFNotes">メモ</label>
                                <textarea id="leadFNotes" class="form-input" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lead-modal-foot">
                <button type="button" class="qb-action-btn" data-close-modal>キャンセル</button>
                <button type="button" class="qb-action-btn primary" id="leadSaveBtn">保存</button>
            </div>
        </div>
    </div>

    <!-- OCR解析中オーバーレイ -->
    <div class="lead-scan-overlay" id="leadScanOverlay" aria-hidden="true">
        <div class="lead-scan-spinner"></div>
        <div class="lead-scan-text" id="leadScanOverlayText">名刺を解析中…</div>
    </div>

    <!-- AI見積入力モーダル -->
    <div class="lead-modal" id="qbAiModal" aria-hidden="true">
        <div class="lead-modal-backdrop" data-close-ai-modal></div>
        <div class="lead-modal-dialog" style="width: min(640px, calc(100vw - 2rem));">
            <div class="lead-modal-head">
                <h3>AIで見積を作成</h3>
                <button type="button" class="lead-modal-close" data-close-ai-modal aria-label="閉じる">×</button>
            </div>
            <div class="lead-modal-body">
                <p class="qb-ai-help">
                    顧客名・商品・数量・施工/配送の有無などを自然な日本語で入力してください。<br>
                    例:<br>
                    ・ <em>ニッケンさんの現場でモニたろう3台、設置工事と配送費込みで</em><br>
                    ・ <em>ヤマト食品 向け 電子黒板2台 + 屋外ディスプレイ1台、一式</em>
                </p>
                <textarea id="qbAiInput" class="form-input" rows="5" placeholder="例: ニッケン(株) の新宿現場 LEDビジョン 3台 設置工事込み" style="resize: vertical;"></textarea>
                <div class="qb-ai-warn" id="qbAiWarn" style="display:none;"></div>
            </div>
            <div class="lead-modal-foot">
                <button type="button" class="qb-action-btn" data-close-ai-modal>キャンセル</button>
                <button type="button" class="qb-action-btn primary" id="qbAiSubmit">生成して反映</button>
            </div>
        </div>
    </div>

    <!-- AI生成中オーバーレイ -->
    <div class="lead-scan-overlay" id="qbAiOverlay" aria-hidden="true">
        <div class="lead-scan-spinner"></div>
        <div class="lead-scan-text">AIが見積を作成中…</div>
    </div>

    <!-- 見積作成 -->
    <div class="st-panel <?= $activeTab === 'create' ? 'active' : '' ?>" id="panel-create" role="tabpanel">
        <div class="qb-wrap">

            <!-- AIで作成 -->
            <section class="qb-ai-row">
                <button type="button" class="qb-ai-btn" id="qbAiOpen">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L9 9l-7 1 5 5-1 7 6-3 6 3-1-7 5-5-7-1z"/>
                    </svg>
                    AIで見積を作成
                </button>
                <span class="qb-ai-hint">自然言語で指示すると、商品マスタと顧客ランクを参照して下のフォームに自動入力します</span>
            </section>

            <!-- 案件情報 -->
            <section class="qb-card">
                <h3 class="qb-card-title">案件情報</h3>
                <div class="qb-grid-2">
                    <div class="form-group">
                        <label for="qbSubject">件名</label>
                        <input type="text" id="qbSubject" class="form-input" placeholder="例: 〇〇現場 LEDビジョン設置一式">
                    </div>
                    <div class="form-group">
                        <label for="qbCustomer">顧客</label>
                        <input type="text" id="qbCustomer" class="form-input" placeholder="顧客名で検索..." autocomplete="off">
                        <div class="qb-rank-hint" id="qbRankHint" style="display:none;">
                            ランク: <span id="qbRankBadge"></span> / 主担当AM: <span id="qbAmName"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="qbIssueDate">見積日</label>
                        <input type="date" id="qbIssueDate" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="qbExpireDate">有効期限</label>
                        <input type="date" id="qbExpireDate" class="form-input">
                    </div>
                </div>
            </section>

            <!-- 見積明細 -->
            <section class="qb-card">
                <h3 class="qb-card-title">見積明細</h3>
                <p class="qb-card-sub">製品、施工費、配送費などを追加してください</p>

                <div class="qb-add-row">
                    <button type="button" class="qb-add-btn" data-add-type="product">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        製品を追加
                    </button>
                    <button type="button" class="qb-add-btn" data-add-type="install">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                        </svg>
                        施工費を追加
                    </button>
                    <button type="button" class="qb-add-btn" data-add-type="shipping">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                        </svg>
                        配送費を追加
                    </button>
                    <button type="button" class="qb-add-btn" data-add-type="other">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        その他を追加
                    </button>
                </div>

                <!-- 明細リスト -->
                <div id="qbItemList"></div>

                <!-- 空状態 -->
                <div class="qb-empty" id="qbEmpty">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="4" y="2" width="16" height="20" rx="2"/>
                        <line x1="8" y1="6" x2="16" y2="6"/>
                        <line x1="8" y1="11" x2="10" y2="11"/>
                        <line x1="12" y1="11" x2="14" y2="11"/>
                        <line x1="14" y1="11" x2="16" y2="11"/>
                        <line x1="8" y1="15" x2="10" y2="15"/>
                        <line x1="12" y1="15" x2="14" y2="15"/>
                        <line x1="14" y1="15" x2="16" y2="15"/>
                        <line x1="8" y1="19" x2="10" y2="19"/>
                        <line x1="12" y1="19" x2="14" y2="19"/>
                    </svg>
                    <div class="qb-empty-title">明細がありません</div>
                    <div class="qb-empty-sub">上のボタンから項目を追加してください</div>
                </div>
            </section>

            <!-- 合計 -->
            <section class="qb-card qb-totals" id="qbTotals" style="display:none;">
                <div class="qb-total-row">
                    <span>小計</span>
                    <span id="qbSubtotal">0 円</span>
                </div>
                <div class="qb-total-row">
                    <span>消費税(10%)</span>
                    <span id="qbTax">0 円</span>
                </div>
                <div class="qb-total-row qb-total-grand">
                    <span>合計</span>
                    <span id="qbGrand">0 円</span>
                </div>
            </section>

            <!-- アクション -->
            <section class="qb-actions">
                <button type="button" class="qb-action-btn" id="qbResetBtn">クリア</button>
                <button type="button" class="qb-action-btn" id="qbPdfBtn">PDFダウンロード</button>
                <button type="button" class="qb-action-btn primary" id="qbSaveBtn">見積を保存</button>
            </section>

        </div>
    </div>

</div>

<script<?= nonceAttr() ?>>
(function() {
    // タブ切り替え(クライアントサイドでも反応)
    document.querySelectorAll('.st-tab').forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            // URLパラメータでも切り替わるが、SPA的にも動かす
            var target = tab.dataset.tab;
            if (!target) return;
            // モディファイアキーや middleclick はそのまま通す
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
            e.preventDefault();
            document.querySelectorAll('.st-tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.st-panel').forEach(function(p) { p.classList.remove('active'); });
            tab.classList.add('active');
            var panel = document.getElementById('panel-' + target);
            if (panel) panel.classList.add('active');
            // URLパラメータも更新(履歴に残す)
            var url = new URL(window.location.href);
            url.searchParams.set('tab', target);
            window.history.replaceState(null, '', url.toString());
        });
    });

    // 検索フィルタ(製品別タブのカードを絞り込み)
    var searchInput = document.getElementById('stSearchInput');
    var emptyState = document.getElementById('stEmptyState');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var q = (searchInput.value || '').trim().toLowerCase();
            var anyVisible = false;
            document.querySelectorAll('#stProductGrid .st-product-card').forEach(function(card) {
                var name = card.dataset.searchName || '';
                var match = q === '' || name.indexOf(q) !== -1;
                card.style.display = match ? '' : 'none';
                if (match) anyVisible = true;
            });
            if (emptyState) emptyState.style.display = anyVisible ? 'none' : '';
        });
    }

    // ========== 見積作成(Quote Builder) ==========
    // マスターは functions/sales-master.php から PHP 経由で注入
    var productMaster  = <?= json_encode(getDemoProductMaster(),  JSON_UNESCAPED_UNICODE) ?>;
    var customerMaster = <?= json_encode(getDemoCustomerMaster(), JSON_UNESCAPED_UNICODE) ?>;

    var typeLabels = {
        product: '製品',
        install: '施工費',
        shipping: '配送費',
        other: 'その他'
    };

    var itemList = document.getElementById('qbItemList');
    var emptyBox = document.getElementById('qbEmpty');
    var totalsBox = document.getElementById('qbTotals');

    function formatYen(n) {
        if (isNaN(n) || n === null) n = 0;
        return Math.round(n).toLocaleString('ja-JP') + ' 円';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function(c) {
            return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
        });
    }

    function recalcAll() {
        var items = document.querySelectorAll('#qbItemList .qb-item');
        var subtotal = 0;
        items.forEach(function(row) {
            var qty = parseFloat(row.querySelector('.qb-qty').value) || 0;
            var price = parseFloat(row.querySelector('.qb-price').value) || 0;
            var sub = qty * price;
            row.querySelector('.qb-subtotal').textContent = formatYen(sub);
            subtotal += sub;
        });
        var tax = Math.floor(subtotal * 0.1);
        document.getElementById('qbSubtotal').textContent = formatYen(subtotal);
        document.getElementById('qbTax').textContent = formatYen(tax);
        document.getElementById('qbGrand').textContent = formatYen(subtotal + tax);

        var hasItems = items.length > 0;
        if (emptyBox) emptyBox.style.display = hasItems ? 'none' : '';
        if (totalsBox) totalsBox.style.display = hasItems ? '' : 'none';
    }

    function buildItemRow(type) {
        var row = document.createElement('div');
        row.className = 'qb-item';
        row.dataset.type = type;
        var label = typeLabels[type] || 'その他';

        var nameHtml;
        if (type === 'product') {
            var options = '<option value="">製品を選択...</option>';
            productMaster.forEach(function(p) {
                options += '<option value="' + escapeHtml(p.id) + '" data-price="' + p.price + '">' + escapeHtml(p.name) + '</option>';
            });
            nameHtml = '<select class="form-input qb-name qb-name-product">' + options + '</select>';
        } else {
            var ph = type === 'install' ? '例: 取付工事' : type === 'shipping' ? '例: 配送費(東京→大阪)' : '例: 諸経費';
            nameHtml = '<input type="text" class="form-input qb-name" placeholder="' + escapeHtml(ph) + '">';
        }

        row.innerHTML =
            '<span class="qb-item-type ' + type + '">' + escapeHtml(label) + '</span>' +
            nameHtml +
            '<input type="number" class="form-input qb-qty" min="0" step="1" placeholder="数量" value="1">' +
            '<input type="number" class="form-input qb-price" min="0" step="1" placeholder="単価" value="0">' +
            '<span class="qb-subtotal">0 円</span>' +
            '<span></span>' +
            '<button type="button" class="qb-delete" title="削除" aria-label="削除">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                    '<polyline points="3 6 5 6 21 6"/>' +
                    '<path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/>' +
                    '<path d="M10 11v6"/><path d="M14 11v6"/>' +
                '</svg>' +
            '</button>';

        // 製品セレクト → 価格自動セット
        if (type === 'product') {
            row.querySelector('.qb-name-product').addEventListener('change', function(e) {
                var opt = e.target.options[e.target.selectedIndex];
                var p = parseFloat(opt.getAttribute('data-price')) || 0;
                row.querySelector('.qb-price').value = p;
                recalcAll();
            });
        }

        // 数量・単価変更で再計算
        row.querySelector('.qb-qty').addEventListener('input', recalcAll);
        row.querySelector('.qb-price').addEventListener('input', recalcAll);

        // 削除
        row.querySelector('.qb-delete').addEventListener('click', function() {
            row.remove();
            recalcAll();
        });

        return row;
    }

    // 追加ボタン
    document.querySelectorAll('.qb-add-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type = btn.dataset.addType;
            var row = buildItemRow(type);
            if (itemList) {
                itemList.appendChild(row);
                recalcAll();
                var firstInput = row.querySelector('select, input');
                if (firstInput) firstInput.focus();
            }
        });
    });

    // 顧客検索 → ランク・AM表示(簡易)
    var custInput = document.getElementById('qbCustomer');
    var rankHint = document.getElementById('qbRankHint');
    var rankBadge = document.getElementById('qbRankBadge');
    var amName = document.getElementById('qbAmName');
    if (custInput) {
        custInput.addEventListener('input', function() {
            var q = custInput.value.trim();
            if (!q) { rankHint.style.display = 'none'; return; }
            var hit = customerMaster.find(function(c) { return c.name.indexOf(q) !== -1 || q.indexOf(c.name) !== -1; });
            if (hit) {
                rankBadge.innerHTML = '<span class="qb-rank-badge ' + hit.rank.toLowerCase() + '">' + hit.rank + '</span>';
                amName.textContent = hit.am;
                rankHint.style.display = '';
            } else {
                rankHint.style.display = 'none';
            }
        });
    }

    // 日付の初期値(見積日=今日 / 有効期限=30日後)
    var todayStr = new Date().toISOString().slice(0, 10);
    var expire = new Date(); expire.setDate(expire.getDate() + 30);
    var expireStr = expire.toISOString().slice(0, 10);
    var issueDateEl = document.getElementById('qbIssueDate');
    var expireDateEl = document.getElementById('qbExpireDate');
    if (issueDateEl && !issueDateEl.value) issueDateEl.value = todayStr;
    if (expireDateEl && !expireDateEl.value) expireDateEl.value = expireStr;

    // 保存・PDF・クリア(現状はプレースホルダ動作)
    var saveBtn = document.getElementById('qbSaveBtn');
    var pdfBtn = document.getElementById('qbPdfBtn');
    var resetBtn = document.getElementById('qbResetBtn');
    if (saveBtn) saveBtn.addEventListener('click', function() {
        var payload = {
            subject: document.getElementById('qbSubject').value,
            customer: document.getElementById('qbCustomer').value,
            issueDate: issueDateEl ? issueDateEl.value : '',
            expireDate: expireDateEl ? expireDateEl.value : '',
            items: Array.from(document.querySelectorAll('#qbItemList .qb-item')).map(function(r) {
                var nameEl = r.querySelector('.qb-name');
                return {
                    type: r.dataset.type,
                    name: nameEl.tagName === 'SELECT' ? (nameEl.options[nameEl.selectedIndex] || {}).text || '' : nameEl.value,
                    qty: parseFloat(r.querySelector('.qb-qty').value) || 0,
                    price: parseFloat(r.querySelector('.qb-price').value) || 0
                };
            })
        };
        console.log('[QuoteBuilder] save payload:', payload);
        if (typeof showToast === 'function') {
            showToast('見積を保存しました(現状は画面のみ・サーバ未連携)', 'success', 3500);
        } else {
            alert('保存対象(コンソール出力):\n' + JSON.stringify(payload, null, 2));
        }
    });
    if (pdfBtn) pdfBtn.addEventListener('click', function() {
        if (typeof showToast === 'function') {
            showToast('PDF出力は次フェーズで実装予定です', 'info', 3000);
        } else {
            alert('PDF出力は次フェーズで実装予定です');
        }
    });
    if (resetBtn) resetBtn.addEventListener('click', function() {
        if (!confirm('入力をクリアしますか?')) return;
        document.getElementById('qbSubject').value = '';
        document.getElementById('qbCustomer').value = '';
        rankHint.style.display = 'none';
        if (issueDateEl) issueDateEl.value = todayStr;
        if (expireDateEl) expireDateEl.value = expireStr;
        if (itemList) itemList.innerHTML = '';
        recalcAll();
    });

    // 初期状態の空表示
    recalcAll();

    // ========== リード管理 ==========
    var CSRF = <?= json_encode($csrfToken) ?>;
    var CAN_EDIT_LEAD = <?= $canEditLead ? 'true' : 'false' ?>;
    var CAN_DELETE_LEAD = <?= $canDeleteLead ? 'true' : 'false' ?>;
    var CURRENT_USER_NAME = <?= json_encode($currentUserName) ?>;

    var leadsCache = [];
    var leadFilterQuery = '';
    var leadFilterStatus = '';
    var leadEditingId = null;
    var leadPendingImageDataUrl = '';

    // 複数名刺スキャン用キュー
    var leadScanQueue = [];
    var leadScanTotal = 0;
    var leadScanStats = { saved: 0, skipped: 0, errors: 0 };
    var leadInQueueMode = false;

    var $leadSearch = document.getElementById('leadSearch');
    var $leadStatusFilter = document.getElementById('leadStatusFilter');
    var $leadTbody = document.getElementById('leadTbody');
    var $leadEmpty = document.getElementById('leadEmpty');
    var $leadStatusSummary = document.getElementById('leadStatusSummary');
    var $leadTable = document.getElementById('leadTable');
    var $leadModal = document.getElementById('leadModal');
    var $leadModalTitle = document.getElementById('leadModalTitle');
    var $leadModalImageWrap = document.getElementById('leadModalImageWrap');
    var $leadModalImage = document.getElementById('leadModalImage');
    var $leadScanInput = document.getElementById('leadScanInput');
    var $leadScanOverlay = document.getElementById('leadScanOverlay');

    var leadStatusList = ['新規','接触済','商談中','成約','失注'];

    function leadShowToast(msg, type) {
        if (typeof showToast === 'function') showToast(msg, type || 'info', 3500);
        else if (type === 'error') alert(msg);
    }

    function leadOpenModal() {
        $leadModal.classList.add('open');
        $leadModal.setAttribute('aria-hidden', 'false');
        setTimeout(function(){ document.getElementById('leadFCompany').focus(); }, 50);
    }
    function leadCloseModal() {
        $leadModal.classList.remove('open');
        $leadModal.setAttribute('aria-hidden', 'true');
        leadEditingId = null;
        leadPendingImageDataUrl = '';
        $leadModalImage.src = '';
        $leadModalImageWrap.style.display = 'none';
    }
    function leadHandleCancelClose() {
        var wasInQueue = leadInQueueMode;
        leadCloseModal();
        if (wasInQueue) leadQueueAdvance('skipped');
    }
    document.querySelectorAll('[data-close-modal]').forEach(function(el){
        el.addEventListener('click', leadHandleCancelClose);
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && $leadModal.classList.contains('open')) leadHandleCancelClose();
    });

    function leadResetForm() {
        ['leadFCompany','leadFPerson','leadFTitle','leadFDept','leadFPhone','leadFMobile',
         'leadFEmail','leadFFax','leadFWebsite','leadFAm','leadFAddress','leadFNotes']
        .forEach(function(id){ document.getElementById(id).value = ''; });
        document.getElementById('leadFStatus').value = '新規';
        $leadModalImageWrap.style.display = 'none';
        $leadModalImage.src = '';
    }

    function leadFillForm(lead) {
        document.getElementById('leadFCompany').value = lead.company_name || '';
        document.getElementById('leadFPerson').value  = lead.person_name || '';
        document.getElementById('leadFTitle').value   = lead.title || '';
        document.getElementById('leadFDept').value    = lead.department || '';
        document.getElementById('leadFPhone').value   = lead.phone || '';
        document.getElementById('leadFMobile').value  = lead.mobile || '';
        document.getElementById('leadFEmail').value   = lead.email || '';
        document.getElementById('leadFFax').value     = lead.fax || '';
        document.getElementById('leadFWebsite').value = lead.website || '';
        document.getElementById('leadFAm').value      = lead.am || '';
        document.getElementById('leadFAddress').value = lead.address || '';
        document.getElementById('leadFNotes').value   = lead.notes || '';
        document.getElementById('leadFStatus').value  = lead.status || '新規';

        if (lead.business_card_image_path) {
            $leadModalImage.src = '../' + lead.business_card_image_path;
            $leadModalImageWrap.style.display = '';
        } else {
            $leadModalImageWrap.style.display = 'none';
            $leadModalImage.src = '';
        }
    }

    function leadCollectForm() {
        return {
            company_name: document.getElementById('leadFCompany').value.trim(),
            person_name:  document.getElementById('leadFPerson').value.trim(),
            title:        document.getElementById('leadFTitle').value.trim(),
            department:   document.getElementById('leadFDept').value.trim(),
            phone:        document.getElementById('leadFPhone').value.trim(),
            mobile:       document.getElementById('leadFMobile').value.trim(),
            email:        document.getElementById('leadFEmail').value.trim(),
            fax:          document.getElementById('leadFFax').value.trim(),
            website:      document.getElementById('leadFWebsite').value.trim(),
            am:           document.getElementById('leadFAm').value.trim(),
            address:      document.getElementById('leadFAddress').value.trim(),
            notes:        document.getElementById('leadFNotes').value.trim(),
            status:       document.getElementById('leadFStatus').value
        };
    }

    function leadStatusBadge(s) {
        var safe = leadStatusList.indexOf(s) >= 0 ? s : '新規';
        return '<span class="lead-status-badge s-' + safe + '">' + safe + '</span>';
    }

    function leadRender() {
        var q = leadFilterQuery.toLowerCase();
        var rows = leadsCache.filter(function(l){
            if (leadFilterStatus && l.status !== leadFilterStatus) return false;
            if (!q) return true;
            var hay = ((l.company_name||'') + ' ' + (l.person_name||'') + ' ' +
                       (l.email||'') + ' ' + (l.phone||'') + ' ' + (l.mobile||'') + ' ' +
                       (l.notes||'')).toLowerCase();
            return hay.indexOf(q) !== -1;
        });

        if (rows.length === 0) {
            $leadTable.style.display = 'none';
            $leadEmpty.style.display = '';
        } else {
            $leadTable.style.display = '';
            $leadEmpty.style.display = 'none';
        }

        var html = rows.map(function(l){
            var sourceBadge = l.source === 'business_card'
                ? '<span class="lead-source-badge business_card" title="名刺OCR">名刺</span>'
                : '';
            var contact = '';
            if (l.phone)  contact += '<div class="lead-contact-row">TEL: ' + escapeHtml(l.phone) + '</div>';
            if (l.mobile) contact += '<div class="lead-contact-row">携帯: ' + escapeHtml(l.mobile) + '</div>';
            if (l.email)  contact += '<div class="lead-contact-row">' + escapeHtml(l.email) + '</div>';
            var titleDept = [l.title, l.department].filter(Boolean).map(escapeHtml).join(' / ');
            var createdAt = (l.created_at || '').slice(0, 10);

            var actions = '';
            if (CAN_EDIT_LEAD) {
                actions += '<button type="button" class="lead-ibtn" data-edit="' + escapeHtml(l.id) + '" title="編集">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
                    '</button>';
            }
            if (CAN_DELETE_LEAD) {
                actions += '<button type="button" class="lead-ibtn danger" data-delete="' + escapeHtml(l.id) + '" title="削除">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>' +
                    '</button>';
            }

            return '<tr>' +
                '<td>' +
                    '<div class="lead-company">' + escapeHtml(l.company_name || '(無題)') + sourceBadge + '</div>' +
                    (l.person_name ? '<div class="lead-person">' + escapeHtml(l.person_name) + '</div>' : '') +
                '</td>' +
                '<td>' + (contact || '<span style="color: var(--gray-400);">—</span>') + '</td>' +
                '<td>' + (titleDept || '<span style="color: var(--gray-400);">—</span>') + '</td>' +
                '<td>' + leadStatusBadge(l.status) + '</td>' +
                '<td>' + escapeHtml(createdAt) + '</td>' +
                '<td><div class="lead-row-btns">' + actions + '</div></td>' +
            '</tr>';
        }).join('');
        $leadTbody.innerHTML = html;

        // 行の編集・削除イベント
        $leadTbody.querySelectorAll('[data-edit]').forEach(function(btn){
            btn.addEventListener('click', function(){ leadEdit(btn.getAttribute('data-edit')); });
        });
        $leadTbody.querySelectorAll('[data-delete]').forEach(function(btn){
            btn.addEventListener('click', function(){ leadDelete(btn.getAttribute('data-delete')); });
        });
    }

    function leadRenderStatusSummary(counts) {
        if (!counts) { $leadStatusSummary.innerHTML = ''; return; }
        $leadStatusSummary.innerHTML = leadStatusList.map(function(st){
            return '<span class="chip">' + escapeHtml(st) + ' <b>' + (counts[st] || 0) + '</b></span>';
        }).join('');
    }

    function leadFetch() {
        return fetch('../api/leads-api.php?action=list', { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (!j.success) throw new Error(j.error || '取得失敗');
                leadsCache = j.data.leads || [];
                leadRenderStatusSummary(j.data.status_counts);
                leadRender();
            })
            .catch(function(e){ leadShowToast('リード一覧の取得に失敗: ' + e.message, 'error'); });
    }

    function leadEdit(id) {
        var lead = leadsCache.find(function(l){ return l.id === id; });
        if (!lead) return;
        leadEditingId = id;
        $leadModalTitle.textContent = 'リード編集';
        leadResetForm();
        leadFillForm(lead);
        leadOpenModal();
    }

    function leadDelete(id) {
        var lead = leadsCache.find(function(l){ return l.id === id; });
        if (!lead) return;
        if (!confirm('リード「' + (lead.company_name || '') + '」を削除しますか?')) return;
        fetch('../api/leads-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ action: 'delete', csrf_token: CSRF, id: id })
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '削除失敗');
            leadShowToast('リードを削除しました', 'success');
            leadFetch();
        })
        .catch(function(e){ leadShowToast('削除失敗: ' + e.message, 'error'); });
    }

    function leadSave() {
        var payload = leadCollectForm();
        if (!payload.company_name) { leadShowToast('会社名は必須です', 'error'); return; }

        var body = Object.assign({ csrf_token: CSRF }, payload);
        if (leadEditingId) {
            body.action = 'update';
            body.id = leadEditingId;
        } else {
            body.action = 'create';
            body.source = leadPendingImageDataUrl ? 'business_card' : 'manual';
            if (leadPendingImageDataUrl) body.image_data_url = leadPendingImageDataUrl;
            if (!payload.am && CURRENT_USER_NAME) body.am = CURRENT_USER_NAME;
        }

        fetch('../api/leads-api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(body)
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) throw new Error(j.error || '保存失敗');
            // 単発（手動追加・編集）はトースト表示。キュー中は最後にまとめて表示
            if (!leadInQueueMode) {
                leadShowToast(leadEditingId ? 'リードを更新しました' : 'リードを登録しました', 'success');
            }
            var wasInQueue = leadInQueueMode;
            leadCloseModal();
            leadFetch();
            if (wasInQueue) leadQueueAdvance('saved');
        })
        .catch(function(e){
            leadShowToast('保存失敗: ' + e.message, 'error');
            // キュー中の保存失敗は error として次に進める
            if (leadInQueueMode) {
                leadCloseModal();
                leadQueueAdvance('error');
            }
        });
    }

    // 手動追加
    document.getElementById('leadAddBtn').addEventListener('click', function(){
        if (!CAN_EDIT_LEAD) { leadShowToast('権限がありません', 'error'); return; }
        leadEditingId = null;
        leadPendingImageDataUrl = '';
        $leadModalTitle.textContent = 'リード登録';
        leadResetForm();
        if (CURRENT_USER_NAME) document.getElementById('leadFAm').value = CURRENT_USER_NAME;
        leadOpenModal();
    });

    // 名刺スキャン
    document.getElementById('leadScanBtn').addEventListener('click', function(){
        if (!CAN_EDIT_LEAD) { leadShowToast('権限がありません', 'error'); return; }
        $leadScanInput.value = '';
        $leadScanInput.click();
    });

    // --- 複数名刺スキャン: キュー処理 ---
    function leadFinishQueue() {
        if (leadScanTotal > 1) {
            var parts = ['名刺スキャン完了: 登録 ' + leadScanStats.saved + '件'];
            if (leadScanStats.skipped > 0) parts.push('スキップ ' + leadScanStats.skipped + '件');
            if (leadScanStats.errors > 0)  parts.push('失敗 ' + leadScanStats.errors + '件');
            leadShowToast(parts.join(' / '), leadScanStats.errors > 0 ? 'warning' : 'success');
        }
        leadScanQueue = [];
        leadScanTotal = 0;
        leadScanStats = { saved: 0, skipped: 0, errors: 0 };
        leadInQueueMode = false;
    }

    function leadQueueAdvance(status) {
        if (!leadInQueueMode) return;
        if (status === 'saved')        leadScanStats.saved++;
        else if (status === 'skipped') leadScanStats.skipped++;
        else if (status === 'error')   leadScanStats.errors++;

        if (leadScanQueue.length === 0) {
            leadFinishQueue();
            return;
        }
        leadProcessNextFromQueue();
    }

    function leadProcessNextFromQueue() {
        var f = leadScanQueue.shift();
        var idx = leadScanTotal - leadScanQueue.length; // 現在処理中の枚数

        // 10MB 超はスキップ
        if (f.size > 10 * 1024 * 1024) {
            leadShowToast('「' + f.name + '」は10MBを超えるためスキップしました', 'error');
            leadQueueAdvance('error');
            return;
        }

        var overlayText = document.getElementById('leadScanOverlayText');
        if (overlayText) {
            overlayText.textContent = leadScanTotal > 1
                ? ('名刺を解析中… (' + idx + ' / ' + leadScanTotal + ')')
                : '名刺を解析中…';
        }
        $leadScanOverlay.classList.add('open');

        var fd = new FormData();
        fd.append('image', f);
        fd.append('csrf_token', CSRF);

        fetch('../api/business-card-ocr.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF },
            body: fd
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            $leadScanOverlay.classList.remove('open');
            if (!j.success) throw new Error(j.error || 'OCR失敗');
            leadEditingId = null;
            leadPendingImageDataUrl = j.data.image_data_url || '';
            $leadModalTitle.textContent = leadScanTotal > 1
                ? ('名刺の解析結果 (' + idx + ' / ' + leadScanTotal + ')')
                : '名刺の解析結果（内容を確認して保存）';
            leadResetForm();
            leadFillForm(j.data.fields || {});
            if (CURRENT_USER_NAME) document.getElementById('leadFAm').value = CURRENT_USER_NAME;
            if (leadPendingImageDataUrl) {
                $leadModalImage.src = leadPendingImageDataUrl;
                $leadModalImageWrap.style.display = '';
            }
            leadOpenModal();
        })
        .catch(function(e){
            $leadScanOverlay.classList.remove('open');
            leadShowToast('「' + f.name + '」解析失敗: ' + e.message, 'error');
            leadQueueAdvance('error');
        });
    }

    $leadScanInput.addEventListener('change', function(){
        var files = Array.from($leadScanInput.files || []);
        $leadScanInput.value = ''; // 同じファイルを再選択できるようにクリア
        if (files.length === 0) return;

        leadScanQueue  = files.slice();
        leadScanTotal  = files.length;
        leadScanStats  = { saved: 0, skipped: 0, errors: 0 };
        leadInQueueMode = true;
        leadProcessNextFromQueue();
    });

    document.getElementById('leadSaveBtn').addEventListener('click', leadSave);

    // 検索・フィルタ
    var leadSearchTimer;
    $leadSearch.addEventListener('input', function(){
        clearTimeout(leadSearchTimer);
        leadSearchTimer = setTimeout(function(){
            leadFilterQuery = $leadSearch.value.trim();
            leadRender();
        }, 150);
    });
    $leadStatusFilter.addEventListener('change', function(){
        leadFilterStatus = $leadStatusFilter.value;
        leadRender();
    });

    // 初回フェッチ（リードタブがアクティブな時のみ即時実行・他タブからの切替時にも再取得）
    var leadsLoaded = false;
    function leadEnsureLoaded() {
        if (leadsLoaded) return;
        leadsLoaded = true;
        leadFetch();
    }
    if (document.querySelector('#panel-leads.active')) leadEnsureLoaded();
    document.querySelectorAll('.st-tab[data-tab="leads"]').forEach(function(t){
        t.addEventListener('click', function(){ leadEnsureLoaded(); });
    });

    // ========== 価格表（製品リスト → 詳細） ==========
    // 製品グルーピング（タイトル regex で価格表データを各製品にマッピング）
    var PP_PRODUCTS = [
        {
            id: 'monitarou', name: 'モニたろう', sub: 'LEDビジョン', color: 'blue',
            icon: '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
            match: /^(モニたろう|miniモニたろう)/
        },
        {
            id: 'monisuke', name: 'モニすけ', sub: '屋外用液晶ディスプレイ', color: 'orange',
            icon: '<rect x="3" y="4" width="18" height="12" rx="1"/><line x1="2" y1="20" x2="22" y2="20"/><line x1="7" y1="16" x2="7" y2="20"/><line x1="17" y1="16" x2="17" y2="20"/>',
            match: /^モニすけ/
        },
        {
            id: 'monimaru', name: 'モニまる', sub: '電子黒板', color: 'green',
            icon: '<rect x="2" y="3" width="20" height="14" rx="2"/><polyline points="6 10 10 14 18 6"/>',
            match: /^モニまる/
        },
        {
            id: 'monija', name: 'モニんじゃ', sub: 'メッシュビジョン', color: 'purple',
            icon: '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/>',
            match: /メッシュ/
        },
        {
            id: 'genbarujer', name: 'ゲンバルジャー', sub: '現場向け', color: 'red',
            icon: '<path d="M2 18a5 5 0 0 1 10 0"/><path d="M12 18a5 5 0 0 1 10 0"/><line x1="2" y1="18" x2="22" y2="18"/><path d="M7 13V8a5 5 0 0 1 10 0v5"/>',
            match: /ゲンバルジャー/
        },
        {
            id: 'others', name: 'その他製品', sub: '屋内用液晶 / プロジェクター / 中古ほか', color: 'gray',
            icon: '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
            match: /(屋内用液晶|屋外用プロジェクター|中古|新規開拓|小型仮囲)/
        },
    ];

    var PP_COMMON = [
        { id: 'shipping',  name: '運搬費',         sub: '地域別・サイズ別の配送料金',  color: 'orange',
          icon: '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
          match: /運搬/ },
        { id: 'install',   name: '設置・調整費',   sub: '現場設置・調整作業の料金',    color: 'green',
          icon: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
          match: /(設置|調整)/ },
        { id: 'customers', name: '顧客ランク定義', sub: 'A/B/C/D 層の判定基準',         color: 'purple',
          icon: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
          match: /顧客定義/ },
    ];

    var ppData = null;
    var ppLoaded = false;

    function ppEnsureLoaded() {
        if (ppLoaded) return;
        ppLoaded = true;
        Promise.all([
            fetch('../api/price-list-sync.php?action=info', { credentials: 'same-origin' }).then(function(r){ return r.json(); }),
            fetch('../api/price-list-get.php',            { credentials: 'same-origin' }).then(function(r){ return r.json(); })
        ])
        .then(function(results){
            if (results[0].success) {
                var info = results[0].data || {};
                document.getElementById('ppSyncStatus').textContent =
                    info.synced_at ? ('最終同期: ' + info.synced_at) : '未同期';
            }
            if (results[1].success && results[1].data.available) {
                ppData = results[1].data;
            } else {
                ppData = { sheets: [] };
            }
            ppRenderList();
        })
        .catch(function(e){
            document.getElementById('ppProductList').innerHTML =
                '<div class="pp-empty-state">読み込みエラー: ' + escapeHtml(e.message) + '</div>';
        });
    }

    function ppMatchSheets(pattern) {
        if (!ppData || !ppData.sheets) return [];
        return ppData.sheets.filter(function(s){ return pattern.test(s.title || ''); });
    }

    function ppRenderRow(item, count) {
        var disabled = count === 0;
        return '<div class="pp-product-row" data-id="' + escapeHtml(item.id) + '"' + (disabled ? ' style="opacity:0.55;cursor:default;"' : '') + '>' +
            '<div class="pp-product-icon c-' + item.color + '">' +
                '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + item.icon + '</svg>' +
            '</div>' +
            '<div class="pp-product-info">' +
                '<div class="pp-product-name">' + escapeHtml(item.name) + ' 価格表</div>' +
                '<div class="pp-product-sub">' + (disabled ? 'データなし' : escapeHtml(item.sub || 'クリックして表示') + (count > 0 ? '（資料 ' + count + '件）' : '')) + '</div>' +
            '</div>' +
            '<button type="button" class="pp-product-action">' +
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>' +
                '表示' +
            '</button>' +
        '</div>';
    }

    function ppRenderList() {
        var listEl = document.getElementById('ppProductList');
        var html = '';
        PP_PRODUCTS.forEach(function(p){ html += ppRenderRow(p, ppMatchSheets(p.match).length); });
        html += '<div class="pp-divider">共通参照</div>';
        PP_COMMON.forEach(function(c){ html += ppRenderRow(c, ppMatchSheets(c.match).length); });
        listEl.innerHTML = html;
        listEl.querySelectorAll('.pp-product-row').forEach(function(row){
            row.addEventListener('click', function(){
                if (row.style.opacity === '0.55') return;
                ppOpenDetail(row.getAttribute('data-id'));
            });
        });
    }

    // ----- 共通ヘルパー -----
    function ppIsEmptyRow(row) {
        if (!Array.isArray(row)) return true;
        return !row.some(function(c){ return String(c == null ? '' : c).trim() !== ''; });
    }
    function ppIsHeaderRow(row) {
        if (!Array.isArray(row)) return false;
        return row.some(function(c){ return /[SABCD]層/.test(String(c == null ? '' : c)); });
    }
    // セクション行（"81インチ" のような単独セル）の判定
    function ppDetectSection(row) {
        if (!Array.isArray(row)) return null;
        var nonEmpty = row.filter(function(c){ return String(c == null ? '' : c).trim() !== ''; });
        if (nonEmpty.length !== 1) return null;
        var v = String(nonEmpty[0]).trim();
        var m;
        if (m = v.match(/^(\d+(?:\.\d+)?)\s*(インチ|inch)$/i)) return { type: 'インチ', value: m[1] };
        if (m = v.match(/^(\d+(?:\.\d+)?)\s*mm$/i))           return { type: 'mm',     value: m[1] };
        return null;
    }

    // シートを正規化: セクションを列にインライン化 + 重複ヘッダ除去
    function ppNormalizeSheet(values) {
        if (!values || values.length === 0) return values;
        // セクションが存在するか走査
        var hasSection = values.some(function(r){ return ppDetectSection(r) !== null; });
        if (!hasSection) {
            // セクションがないなら空行除去のみして返す
            return values.filter(function(r){ return !ppIsEmptyRow(r); });
        }
        var sec = ppDetectSection(values.find(function(r){ return ppDetectSection(r); }));
        var addedField = sec.type;

        var output = [];
        var currentSection = '';
        var headerInserted = false;
        for (var r = 0; r < values.length; r++) {
            var row = values[r];
            var asSec = ppDetectSection(row);
            if (asSec) { currentSection = asSec.value; continue; }
            if (ppIsEmptyRow(row)) continue;
            if (ppIsHeaderRow(row)) {
                if (!headerInserted) {
                    output.push([addedField].concat(row));
                    headerInserted = true;
                }
                continue;
            }
            output.push([currentSection].concat(row || []));
        }
        return output;
    }

    // 列ヘッダから列タイプを決定
    function ppDetectColumnTypes(headerRow) {
        if (!Array.isArray(headerRow)) return [];
        return headerRow.map(function(h){
            var s = String(h == null ? '' : h).replace(/\s+/g, '');
            if (/(月額|販売価格|定価|仕入|原価|利益|料金|単価|送料|請求|キャンセル)/.test(s)) return 'price';
            if (/(率$|^限界粗利率|%|％|OFF)/.test(s)) return 'pct';
            if (/(平米|サイズ|寸法|タテ|ヨコ|インチ|ピッチ|mm)/.test(s)) return 'measure';
            return 'text';
        });
    }

    // セル値整形（列タイプに従う）
    function ppFormatCell(value, type) {
        var s = String(value == null ? '' : value).trim();
        if (s === '') return { text: '', cls: '' };
        if (type === 'price') {
            var n = parseInt(s.replace(/[¥￥,，円\s]/g, ''), 10);
            if (!isNaN(n) && n > 0) return { text: '¥' + n.toLocaleString('ja-JP'), cls: 'pp-yen' };
            return { text: s, cls: '' };
        }
        if (type === 'pct')     return { text: s, cls: 'pp-pct' };
        if (type === 'measure') return { text: s, cls: 'pp-num' };
        return { text: s, cls: '' };
    }

    // ----- 表レンダラー（正規化済み values を前提） -----
    // badgeCol: バッジ表示する列インデックス（製品シリーズ列）
    function ppRenderTable(values, badgeCol) {
        if (typeof badgeCol === 'undefined') badgeCol = -1;
        if (!values || !values.length) {
            return '<div style="color: var(--gray-500); padding: 1rem; text-align:center;">データなし</div>';
        }
        var rows = values.filter(function(r){ return !ppIsEmptyRow(r); });
        if (rows.length === 0) {
            return '<div style="color: var(--gray-500); padding: 1rem; text-align:center;">データなし</div>';
        }

        var cols = 0;
        rows.forEach(function(r){ if (r.length > cols) cols = r.length; });

        // ヘッダ行: ランクラベルを含む or 1行目が全部文字列ならヘッダ
        var headerIdx = -1;
        for (var i = 0; i < Math.min(2, rows.length); i++) {
            if (ppIsHeaderRow(rows[i])) { headerIdx = i; break; }
        }
        if (headerIdx < 0) {
            var f = rows[0];
            var anyNum = f.some(function(c){
                var s = String(c == null ? '' : c).trim();
                return /^[¥￥]?\d[\d,，.]*$/.test(s);
            });
            if (!anyNum && f.some(function(c){ return String(c || '').trim() !== ''; })) headerIdx = 0;
        }
        var headerRow = headerIdx >= 0 ? rows[headerIdx] : null;
        var colTypes  = headerRow ? ppDetectColumnTypes(headerRow) : [];

        var html = '<div class="pp-data-table-wrap"><table class="pp-data-table"><tbody>';
        rows.forEach(function(row, i){
            var isH = (i === headerIdx);
            html += '<tr' + (isH ? ' class="pp-header"' : '') + '>';
            for (var c = 0; c < cols; c++) {
                var cls = [];
                if (isH) {
                    var hs = String(row[c] == null ? '' : row[c]).replace(/^[SABCD]層[\r\n]*/u, '');
                    html += '<td' + (cls.length ? ' class="' + cls.join(' ') + '"' : '') + '>' +
                            escapeHtml(hs) + '</td>';
                } else {
                    var t = colTypes[c] || 'text';
                    var ff = ppFormatCell(row[c], t);
                    if (ff.cls) cls.push(ff.cls);
                    var content;
                    if (c === badgeCol && ff.text) {
                        content = '<span class="pp-badge">' + escapeHtml(ff.text) + '</span>';
                    } else {
                        content = escapeHtml(ff.text);
                    }
                    html += '<td' + (cls.length ? ' class="' + cls.join(' ') + '"' : '') + '>' +
                            content + '</td>';
                }
            }
            html += '</tr>';
        });
        return html + '</tbody></table></div>';
    }

    // 詳細ビューの状態
    var ppCurrentMatched = [];
    var ppCurrentSubIdx  = 0;
    var ppCurrentRank    = null; // 'S' / 'A' / 'B' / 'C' / 'D'
    var ppCurrentSeries  = null; // null = すべて、それ以外は系列名

    // ヘッダ行の終端を検出（先頭3行までを対象）
    function ppDetectHeaderEnd(values) {
        var rankPattern = /[SABCD]層/;
        var lastHeaderRow = -1;
        for (var r = 0; r < Math.min(3, values.length); r++) {
            var row = values[r] || [];
            for (var c = 0; c < row.length; c++) {
                if (rankPattern.test(String(row[c] == null ? '' : row[c]))) {
                    lastHeaderRow = r;
                    break;
                }
            }
        }
        return lastHeaderRow;
    }

    // シリーズ列（製品グルーピング列）を共通列の中から検出
    // 優先順: ヘッダ名が "製品シリーズ/シリーズ/型式/モデル" → 上記なら採用
    // 次点: distinct 2〜20で繰り返しがあるヒューリスティック
    function ppDetectSeriesCol(values, info) {
        if (!info || !info.commonCols || !info.commonCols.length) return -1;
        var headerEnd = ppDetectHeaderEnd(values);

        // 1) ヘッダ名でマッチ
        if (headerEnd >= 0) {
            var headerRow = values[headerEnd] || [];
            for (var i = 0; i < info.commonCols.length; i++) {
                var c = info.commonCols[i];
                var h = String(headerRow[c] == null ? '' : headerRow[c]).trim();
                if (/(製品シリーズ|シリーズ|型式|モデル)/.test(h)) return c;
            }
        }

        // 2) ヒューリスティック
        var dataStart = headerEnd >= 0 ? headerEnd + 1 : 0;
        var dataRows = values.slice(dataStart).filter(function(r){
            return Array.isArray(r) && r.some(function(c){ return String(c == null ? '' : c).trim() !== ''; });
        });
        if (dataRows.length < 4) return -1;

        // ヘッダ文字列（除外用）
        var headerVals = {};
        if (headerEnd >= 0) {
            (values[headerEnd] || []).forEach(function(c){
                var s = String(c == null ? '' : c).trim();
                if (s) headerVals[s] = true;
            });
        }

        var best = -1, bestScore = 0;
        for (var ii = 0; ii < info.commonCols.length; ii++) {
            var col = info.commonCols[ii];
            var vals = dataRows.map(function(r){ return String(r[col] == null ? '' : r[col]).trim(); })
                                .filter(function(v){ return v && !headerVals[v]; });
            if (vals.length < dataRows.length * 0.5) continue;
            var uniq = {};
            vals.forEach(function(v){ uniq[v] = (uniq[v] || 0) + 1; });
            var distinct = Object.keys(uniq).length;
            if (distinct < 2 || distinct > 20) continue;
            if (vals.length < distinct * 1.5) continue;
            var score = (vals.length / distinct) + (10 - Math.abs(distinct - 5)) * 0.3;
            if (score > bestScore) { bestScore = score; best = col; }
        }
        return best;
    }

    // dataRows + seriesCol → ユニーク値の一覧（出現順）と件数
    function ppCollectSeries(values, seriesCol) {
        var headerEnd = ppDetectHeaderEnd(values);
        var dataStart = headerEnd >= 0 ? headerEnd + 1 : 0;
        var dataRows = values.slice(dataStart);

        // ヘッダ文字列をブラックリスト化（"製品シリーズ" がデータとして混入するのを防ぐ）
        var headerVals = {};
        if (headerEnd >= 0) {
            (values[headerEnd] || []).forEach(function(c){
                var s = String(c == null ? '' : c).trim();
                if (s) headerVals[s] = true;
            });
        }

        var seen = {};
        var order = [];
        dataRows.forEach(function(r){
            if (!Array.isArray(r)) return;
            var v = String(r[seriesCol] == null ? '' : r[seriesCol]).trim();
            if (!v) return;
            if (headerVals[v]) return;
            if (!(v in seen)) { seen[v] = 0; order.push(v); }
            seen[v]++;
        });
        return order.map(function(v){ return { value: v, count: seen[v] }; });
    }

    // values をシリーズで絞り込む。ヘッダ行は残す。
    function ppFilterBySeries(values, seriesCol, seriesValue) {
        if (seriesCol < 0 || !seriesValue) return values;
        var headerEnd = ppDetectHeaderEnd(values);
        return values.filter(function(r, idx){
            if (idx <= headerEnd) return true;
            if (!Array.isArray(r)) return false;
            return String(r[seriesCol] == null ? '' : r[seriesCol]).trim() === seriesValue;
        });
    }

    // 列ごとのランクを検出（複数行ヘッダ対応）
    function ppDetectRanks(values) {
        if (!values || !values.length) return { hasRanks: false };
        var maxCols = 0;
        values.forEach(function(r){ if (Array.isArray(r) && r.length > maxCols) maxCols = r.length; });
        if (maxCols === 0) return { hasRanks: false };

        var colRank = new Array(maxCols).fill(null);
        var rankPattern = /([SABCD])層/;
        var scanRows = Math.min(3, values.length);
        for (var r = 0; r < scanRows; r++) {
            var row = values[r] || [];
            for (var c = 0; c < row.length; c++) {
                if (colRank[c]) continue;
                var m = String(row[c] == null ? '' : row[c]).match(rankPattern);
                if (m) colRank[c] = m[1];
            }
        }

        // ランクが出てきた最初の列の位置
        var firstRankCol = colRank.findIndex(function(v){ return v !== null; });
        if (firstRankCol < 0) return { hasRanks: false };

        // 各ランクの開始位置を順番に拾い、次のランクの直前までをその範囲とする
        var anchors = [];
        for (var c2 = 0; c2 < maxCols; c2++) {
            if (colRank[c2]) anchors.push({ col: c2, rank: colRank[c2] });
        }
        var ranks = []; // [{rank, cols: []}]
        for (var i = 0; i < anchors.length; i++) {
            var start = anchors[i].col;
            var end   = (i + 1 < anchors.length) ? anchors[i+1].col - 1 : maxCols - 1;
            var existing = ranks.find(function(rk){ return rk.rank === anchors[i].rank; });
            if (!existing) { existing = { rank: anchors[i].rank, cols: [] }; ranks.push(existing); }
            for (var c3 = start; c3 <= end; c3++) existing.cols.push(c3);
        }

        // 共通列 = 1番目のランク列より左
        var commonCols = [];
        for (var k = 0; k < firstRankCol; k++) commonCols.push(k);

        return { hasRanks: true, commonCols: commonCols, ranks: ranks, firstRankCol: firstRankCol };
    }

    // ランク絞り込みテーブル
    function ppRenderRankFilteredTable(values, rankFilter, info, badgeCol) {
        if (typeof badgeCol === 'undefined') badgeCol = -1;
        var pickedCols = info.commonCols.concat(
            (info.ranks.find(function(r){ return r.rank === rankFilter; }) || { cols: [] }).cols
        );
        var filtered = values.map(function(row){
            return pickedCols.map(function(c){ return (row && row[c] != null) ? row[c] : ''; });
        });
        // ヘッダから「○層」プレフィックスを削除して見やすく
        for (var h = 0; h < Math.min(2, filtered.length); h++) {
            filtered[h] = filtered[h].map(function(cell, idx){
                if (idx < info.commonCols.length) return cell;
                var s = String(cell == null ? '' : cell);
                return s.replace(/^[SABCD]層[\s\r\n]*/u, '').trim();
            });
        }
        // 元のbadgeColが commonCols 内にあれば、フィルタ後の対応列に変換
        var newBadgeCol = -1;
        if (badgeCol >= 0) {
            newBadgeCol = pickedCols.indexOf(badgeCol);
        }
        return ppRenderTable(filtered, newBadgeCol);
    }

    function ppRenderSubtabs() {
        var html = ppCurrentMatched.map(function(s, i){
            var rows = (s.values || []).filter(function(r){
                return Array.isArray(r) && r.some(function(c){ return String(c == null ? '' : c).trim() !== ''; });
            }).length;
            return '<button type="button" class="pp-subtab' + (i === ppCurrentSubIdx ? ' active' : '') + '" data-idx="' + i + '">' +
                escapeHtml(s.title) +
                '<span class="pp-subtab-count">' + rows + '</span>' +
            '</button>';
        }).join('');
        return html;
    }

    function ppRenderActiveSubsheet() {
        if (!ppCurrentMatched.length) return '';
        var s = ppCurrentMatched[ppCurrentSubIdx] || ppCurrentMatched[0];
        // 元データを正規化: セクション行（"81インチ"）を列にインライン化 + 重複ヘッダ除去
        var values = ppNormalizeSheet(s.values || []);
        var rowCount = values.filter(function(r){
            return Array.isArray(r) && r.some(function(c){ return String(c == null ? '' : c).trim() !== ''; });
        }).length;
        var meta = '<div class="pp-section-meta">' +
            '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>' +
            ' ' + rowCount + ' 行' +
            '</div>';

        // 層検出
        var info = ppDetectRanks(values);
        var seriesCol = ppDetectSeriesCol(values, info.hasRanks ? info : { commonCols: values[0] ? values[0].map(function(_, i){ return i; }) : [] });

        // --- 層あり ---
        if (info.hasRanks) {
            // 現在のランクが今のサブシートの中に無ければ先頭にリセット
            var rankExists = info.ranks.find(function(r){ return r.rank === ppCurrentRank; });
            if (!rankExists) ppCurrentRank = info.ranks[0].rank;

            // 各ランクの該当行数（共通列にデータがある行数）でカウント
            var headerEndForCount = ppDetectHeaderEnd(values);
            var dataStartForCount = headerEndForCount >= 0 ? headerEndForCount + 1 : 0;
            var dataRowCount = values.slice(dataStartForCount).filter(function(r){
                return Array.isArray(r) && r.some(function(c){ return String(c == null ? '' : c).trim() !== ''; });
            }).length;

            var tabs = '<div class="pp-rank-cards">' +
                info.ranks.map(function(r){
                    var isActive = (r.rank === ppCurrentRank);
                    return '<div role="button" tabindex="0" class="pp-rank-card rank-' + r.rank + (isActive ? ' active' : '') + '" data-rank="' + r.rank + '">' +
                        '<div class="pp-rank-card-check">✓</div>' +
                        '<div class="pp-rank-card-letter">' + r.rank + '</div>' +
                        '<div class="pp-rank-card-label">' + r.rank + '層</div>' +
                        '<div class="pp-rank-card-desc">' + dataRowCount + ' 製品</div>' +
                    '</div>';
                }).join('') +
            '</div>';

            // シリーズフィルタ
            var seriesHtml = '';
            var filteredValues = values;
            if (seriesCol >= 0) {
                var seriesList = ppCollectSeries(values, seriesCol);
                if (seriesList.length >= 2) {
                    // currentSeries が一覧に無ければ「すべて」に
                    if (ppCurrentSeries && !seriesList.find(function(s){ return s.value === ppCurrentSeries; })) {
                        ppCurrentSeries = null;
                    }
                    seriesHtml = '<div class="pp-series-row">' +
                        '<span class="pp-series-label">シリーズ</span>' +
                        '<button type="button" class="pp-series-pill' + (ppCurrentSeries === null ? ' active' : '') + '" data-series="">' +
                            'すべて<span class="pp-series-count">' + values.length + '</span>' +
                        '</button>' +
                        seriesList.map(function(s){
                            var safe = s.value.replace(/"/g, '&quot;');
                            return '<button type="button" class="pp-series-pill' + (ppCurrentSeries === s.value ? ' active' : '') + '" data-series="' + escapeHtml(s.value) + '">' +
                                escapeHtml(s.value) +
                                '<span class="pp-series-count">' + s.count + '</span>' +
                            '</button>';
                        }).join('') +
                    '</div>';
                    if (ppCurrentSeries) {
                        filteredValues = ppFilterBySeries(values, seriesCol, ppCurrentSeries);
                    }
                }
            }

            var table = ppRenderRankFilteredTable(filteredValues, ppCurrentRank, info, seriesCol);
            return meta + tabs + seriesHtml + table;
        }

        // --- 層なし: シリーズフィルタだけ適用（あれば） ---
        if (seriesCol >= 0) {
            var seriesListN = ppCollectSeries(values, seriesCol);
            if (seriesListN.length >= 2) {
                if (ppCurrentSeries && !seriesListN.find(function(s){ return s.value === ppCurrentSeries; })) {
                    ppCurrentSeries = null;
                }
                var seriesOnly = '<div class="pp-series-row">' +
                    '<span class="pp-series-label">シリーズ</span>' +
                    '<button type="button" class="pp-series-pill' + (ppCurrentSeries === null ? ' active' : '') + '" data-series="">' +
                        'すべて<span class="pp-series-count">' + values.length + '</span>' +
                    '</button>' +
                    seriesListN.map(function(s){
                        return '<button type="button" class="pp-series-pill' + (ppCurrentSeries === s.value ? ' active' : '') + '" data-series="' + escapeHtml(s.value) + '">' +
                            escapeHtml(s.value) +
                            '<span class="pp-series-count">' + s.count + '</span>' +
                        '</button>';
                    }).join('') +
                '</div>';
                var v2 = ppCurrentSeries ? ppFilterBySeries(values, seriesCol, ppCurrentSeries) : values;
                return meta + seriesOnly + ppRenderTable(v2, seriesCol);
            }
        }
        return meta + ppRenderTable(values);
    }

    function ppShowSub(i) {
        ppCurrentSubIdx = i;
        ppCurrentRank   = null; // サブシート切替時にランク・シリーズもリセット
        ppCurrentSeries = null;
        var tabs = document.getElementById('ppDetailSubtabs');
        if (tabs) {
            tabs.querySelectorAll('.pp-subtab').forEach(function(b, idx){
                if (idx === i) b.classList.add('active'); else b.classList.remove('active');
            });
        }
        var content = document.getElementById('ppDetailContent');
        if (content) {
            content.innerHTML = ppRenderActiveSubsheet();
            ppBindDetailEvents();
        }
    }

    // 層カード + シリーズピルのクリックイベント
    function ppBindDetailEvents() {
        var content = document.getElementById('ppDetailContent');
        if (!content) return;
        content.querySelectorAll('.pp-rank-card').forEach(function(card){
            var trigger = function(){
                ppCurrentRank = card.getAttribute('data-rank');
                // ランク切替時はシリーズも「すべて」に戻す
                ppCurrentSeries = null;
                content.innerHTML = ppRenderActiveSubsheet();
                ppBindDetailEvents();
            };
            card.addEventListener('click', trigger);
            card.addEventListener('keydown', function(e){
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); trigger(); }
            });
        });
        content.querySelectorAll('.pp-series-pill').forEach(function(btn){
            btn.addEventListener('click', function(){
                var v = btn.getAttribute('data-series');
                ppCurrentSeries = (v === '' || v == null) ? null : v;
                content.innerHTML = ppRenderActiveSubsheet();
                ppBindDetailEvents();
            });
        });
    }
    // 互換のため旧名でも呼べるように
    var ppBindRankTabs = ppBindDetailEvents;

    function ppOpenDetail(id) {
        var item = PP_PRODUCTS.find(function(p){ return p.id === id; })
                || PP_COMMON.find(function(c){ return c.id === id; });
        if (!item) return;
        ppCurrentMatched = ppMatchSheets(item.match);
        ppCurrentSubIdx  = 0;

        document.getElementById('ppDetailTitle').textContent = item.name + ' 価格表';
        var body = document.getElementById('ppDetailBody');
        if (ppCurrentMatched.length === 0) {
            body.innerHTML = '<div class="pp-empty-state">この資料はまだありません</div>';
        } else if (ppCurrentMatched.length === 1) {
            // 1件しかない場合はサブタブを出さない
            ppCurrentRank = null;
            ppCurrentSeries = null;
            body.innerHTML =
                '<div id="ppDetailContent">' + ppRenderActiveSubsheet() + '</div>';
            ppBindDetailEvents();
        } else {
            ppCurrentRank = null;
            ppCurrentSeries = null;
            body.innerHTML =
                '<div class="pp-subtabs" id="ppDetailSubtabs">' + ppRenderSubtabs() + '</div>' +
                '<div id="ppDetailContent">' + ppRenderActiveSubsheet() + '</div>';
            document.getElementById('ppDetailSubtabs').querySelectorAll('.pp-subtab').forEach(function(btn){
                btn.addEventListener('click', function(){
                    ppShowSub(parseInt(btn.getAttribute('data-idx'), 10));
                });
            });
            ppBindDetailEvents();
        }
        document.getElementById('ppListView').style.display = 'none';
        document.getElementById('ppDetailView').style.display = '';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function ppCloseDetail() {
        document.getElementById('ppDetailView').style.display = 'none';
        document.getElementById('ppListView').style.display = '';
    }

    var ppBackBtn = document.getElementById('ppBack');
    if (ppBackBtn) ppBackBtn.addEventListener('click', ppCloseDetail);

    // タブ起動時に読み込み
    if (document.querySelector('#panel-pricing.active')) ppEnsureLoaded();
    document.querySelectorAll('.st-tab[data-tab="pricing"]').forEach(function(t){
        t.addEventListener('click', function(){ ppEnsureLoaded(); });
    });

    // 同期ボタン（admin のみ存在）
    var ppSyncBtn = document.getElementById('ppSyncBtn');
    if (ppSyncBtn) {
        ppSyncBtn.addEventListener('click', function(){
            if (!confirm('Google ドライブから価格表を再同期します。よろしいですか?\n（約30項目で30秒〜1分かかります）')) return;
            ppSyncBtn.disabled = true;
            var orig = ppSyncBtn.innerHTML;
            ppSyncBtn.innerHTML = '同期中…';
            fetch('../api/price-list-sync.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ csrf_token: CSRF, action: 'sync' })
            })
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (!j.success) throw new Error(j.error || '同期失敗');
                var msg = '同期完了: ' + j.data.sheet_count + '件';
                if (j.data.errors && j.data.errors.length) msg += ' (' + j.data.errors.length + '件エラー)';
                if (typeof showToast === 'function') showToast(msg, j.data.errors.length ? 'warning' : 'success', 5000);
                ppLoaded = false;
                ppEnsureLoaded();
            })
            .catch(function(e){
                if (typeof showToast === 'function') showToast('同期失敗: ' + e.message, 'error');
                else alert('同期失敗: ' + e.message);
            })
            .then(function(){
                ppSyncBtn.disabled = false;
                ppSyncBtn.innerHTML = orig;
            });
        });
    }

    // ========== AI見積アシスタント ==========
    var $qbAiModal   = document.getElementById('qbAiModal');
    var $qbAiOverlay = document.getElementById('qbAiOverlay');
    var $qbAiInput   = document.getElementById('qbAiInput');
    var $qbAiWarn    = document.getElementById('qbAiWarn');

    function qbAiOpenModal() {
        $qbAiWarn.style.display = 'none';
        $qbAiModal.classList.add('open');
        $qbAiModal.setAttribute('aria-hidden', 'false');
        setTimeout(function(){ $qbAiInput.focus(); }, 50);
    }
    function qbAiCloseModal() {
        $qbAiModal.classList.remove('open');
        $qbAiModal.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('qbAiOpen').addEventListener('click', qbAiOpenModal);
    document.querySelectorAll('[data-close-ai-modal]').forEach(function(el){
        el.addEventListener('click', qbAiCloseModal);
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && $qbAiModal.classList.contains('open')) qbAiCloseModal();
    });

    // AIレスポンス → 既存の見積作成フォームに反映
    function qbAiPopulate(quote) {
        if (!quote) return;
        document.getElementById('qbSubject').value = quote.subject || '';

        var custEl = document.getElementById('qbCustomer');
        custEl.value = quote.customer || '';
        // 顧客検索のランク表示を更新するため input イベントを発火
        custEl.dispatchEvent(new Event('input'));

        if (quote.issue_date)  document.getElementById('qbIssueDate').value  = quote.issue_date;
        if (quote.expire_date) document.getElementById('qbExpireDate').value = quote.expire_date;

        // 既存明細をクリアして AI 提案を流し込む
        if (itemList) itemList.innerHTML = '';

        (quote.items || []).forEach(function(it){
            var allowedTypes = ['product','install','shipping','other'];
            var type = allowedTypes.indexOf(it.type) >= 0 ? it.type : 'other';
            var row = buildItemRow(type);

            if (type === 'product') {
                var sel = row.querySelector('.qb-name-product');
                if (sel && it.product_id) {
                    var hasOpt = Array.prototype.some.call(sel.options, function(o){ return o.value === it.product_id; });
                    if (hasOpt) sel.value = it.product_id;
                }
            } else {
                var inp = row.querySelector('.qb-name');
                if (inp) inp.value = it.name || '';
            }
            row.querySelector('.qb-qty').value   = it.qty   || 1;
            row.querySelector('.qb-price').value = it.price || 0;
            itemList.appendChild(row);
        });
        recalcAll();
    }

    function qbAiSwitchToCreateTab() {
        if (document.querySelector('#panel-create.active')) return;
        var tab = document.querySelector('.st-tab[data-tab="create"]');
        if (tab) tab.click();
    }

    document.getElementById('qbAiSubmit').addEventListener('click', function(){
        var text = $qbAiInput.value.trim();
        if (!text) {
            $qbAiWarn.textContent = '指示文を入力してください';
            $qbAiWarn.style.display = '';
            return;
        }
        $qbAiWarn.style.display = 'none';
        $qbAiOverlay.classList.add('open');

        fetch('../api/quote-ai.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ csrf_token: CSRF, request_text: text })
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            $qbAiOverlay.classList.remove('open');
            if (!j.success) throw new Error(j.error || '生成失敗');
            var quote = j.data && j.data.quote;
            qbAiPopulate(quote);
            qbAiCloseModal();
            qbAiSwitchToCreateTab();

            var notes = (quote && quote.notes) || '';
            var rank  = (quote && quote.customer_rank) || '';
            var msg   = 'AI見積を反映しました' + (rank ? '（ランク' + rank + '適用）' : '');
            if (typeof showToast === 'function') {
                showToast(msg + (notes ? '\n' + notes : ''), 'success', 6000);
            } else if (notes) {
                alert(msg + '\n\n' + notes);
            }
        })
        .catch(function(e){
            $qbAiOverlay.classList.remove('open');
            $qbAiWarn.textContent = '生成失敗: ' + e.message;
            $qbAiWarn.style.display = '';
        });
    });
})();
</script>

<?php require_once '../functions/footer.php'; ?>
