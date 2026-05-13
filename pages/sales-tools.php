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

// 現在のタブ
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'products';
$allowedTabs = ['products', 'pricing', 'catalogs', 'scripts', 'history', 'create'];
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
        <div class="st-note">
            価格表は製品ごと・ランクごと(S層/A層/B層)で管理。M3リリース予定の機能です。
        </div>
        <div class="st-empty">
            <div class="empty-title">価格表(準備中)</div>
            <div>M3で本機能を有効化します。現状は別資料で運用中。</div>
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

    <!-- 見積作成 -->
    <div class="st-panel <?= $activeTab === 'create' ? 'active' : '' ?>" id="panel-create" role="tabpanel">
        <div class="qb-wrap">

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
    var productMaster = [
        { id: 'monitarou', name: 'モニたろう(LEDビジョン)', price: 250000 },
        { id: 'monisuke', name: 'モニすけ(屋外用液晶ディスプレイ)', price: 180000 },
        { id: 'monimaru', name: 'モニまる(電子黒板)', price: 320000 }
    ];

    var customerMaster = [
        { name: 'ヤマト商事(株)', rank: 'S', am: '佐藤 太郎' },
        { name: 'ヤマトロジ(株)', rank: 'A', am: '佐藤 太郎' },
        { name: 'ヤマト食品', rank: 'B', am: '鈴木 花子' },
        { name: 'ヤマト工業', rank: 'C', am: '高橋 次郎' },
        { name: 'ニッケン(株)', rank: 'A', am: '西井' }
    ];

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
})();
</script>

<?php require_once '../functions/footer.php'; ?>
