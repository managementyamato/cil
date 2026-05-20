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
require_once '../functions/links.php';

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

// 価格表タブの製品定義（config/sales-tools-products.json から読込）
$ppConfigRaw = @file_get_contents(__DIR__ . '/../config/sales-tools-products.json');
$ppConfig    = $ppConfigRaw ? json_decode($ppConfigRaw, true) : null;
if (!is_array($ppConfig)) $ppConfig = ['products' => [], 'common' => []];
$ppProductsForJs = [
    'products' => $ppConfig['products'] ?? [],
    'common'   => $ppConfig['common']   ?? [],
];

// 各 PP 製品(id)に対応する外部リンク（HP）を集約し JS に注入。
// id ごとに getLink('product.{id}.hp') / getLinkIcon('product.{id}.hp') を引く。
$ppLinksForJs = [];
foreach (array_merge($ppProductsForJs['products'], $ppProductsForJs['common']) as $it) {
    $id = $it['id'] ?? '';
    if ($id === '') continue;
    $key = 'product.' . $id . '.hp';
    $url = getLink($key);
    if (!$url) continue;
    $ppLinksForJs[$id] = [
        'url'  => $url,
        'icon' => getLinkIcon($key),
        'svg'  => (function() use ($key) {
            $lib = getLinkIconLibrary();
            $iconId = getLinkIcon($key);
            return $lib[$iconId]['svg'] ?? $lib['globe']['svg'];
        })(),
    ];
}

// 製品マスター — config/sales-tools-products.json から導出（管理: /pages/product-master.php）
// 外部リンク URL は config/external-links.json（管理: /pages/external-links.php）
$products = [];
foreach ($ppConfig['products'] ?? [] as $p) {
    $id = $p['id'] ?? '';
    if ($id === '') continue;
    $products[] = [
        'id'            => $id,
        'name_ja'       => $p['name'] ?? '',
        'name_en'       => $p['name_en'] ?? '',
        'category'      => $p['sub'] ?? '',
        'description'   => $p['description'] ?? '',
        'has_price'     => true, // 旧来挙動を維持（製品マスタに登録されている＝価格表対象）
        'catalog_count' => (int)($p['catalog_count'] ?? 0),
        'script_count'  => (int)($p['script_count'] ?? 0),
        'web_url'       => getLink('product.' . $id . '.hp'),
        'web_icon'      => getLinkIcon('product.' . $id . '.hp'),
        'icon'          => $p['icon'] ?? '',
        'icon_image'    => $p['icon_image'] ?? '',
    ];
}
?>
<?php include __DIR__ . "/sales-tools/_styles.php"; ?>


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

    <!-- 製品別 -->
    <?php include __DIR__ . "/sales-tools/tabs/products.php"; ?>

    <!-- 価格表 -->
    <?php include __DIR__ . "/sales-tools/tabs/pricing.php"; ?>

    <!-- カタログ -->
    <?php include __DIR__ . "/sales-tools/tabs/catalogs.php"; ?>

    <!-- トークスクリプト -->
    <?php include __DIR__ . "/sales-tools/tabs/scripts.php"; ?>

    <!-- 見積履歴 -->
    <?php include __DIR__ . "/sales-tools/tabs/history.php"; ?>

    <!-- リード管理 -->
    <?php include __DIR__ . "/sales-tools/tabs/leads.php"; ?>

    <!-- 見積作成 -->
    <?php include __DIR__ . "/sales-tools/tabs/create.php"; ?>

</div>

<?php include __DIR__ . "/sales-tools/_scripts.php"; ?>


<?php require_once '../functions/footer.php'; ?>
