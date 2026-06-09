<?php
/**
 * 営業ツール
 *
 * 製品情報・営業資料を一画面に集約。
 * - 製品別: 各製品のサマリーカード(カタログ/スクリプトへの導線)
 * - 顧客: 顧客一覧・詳細
 * - カタログ: 製品PDF・画像
 * - トークスクリプト: 営業トーク
 * - 見積履歴: 過去見積参照
 * - 見積作成: 新規見積生成
 *
 * 価格表タブは 2026-06-05 に廃止 (価格表マスタは master-hub に集約)。
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

// タブ別権限チェック: sales-tools.php#<tab> のサブキーを参照
$allTabs = ['products', 'customers', 'catalogs', 'scripts', 'history', 'leads', 'create'];
$allowedTabs = [];
foreach ($allTabs as $t) {
    $perm = getPageViewPermission('sales-tools.php#' . $t);
    if (hasPermission($perm)) {
        $allowedTabs[] = $t;
    }
}
// 閲覧可能タブがなければダッシュボードへ
if (empty($allowedTabs)) {
    header('Location: /pages/index.php');
    exit;
}

// 現在のタブ（権限がなければ最初の閲覧可能タブにフォールバック）
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : $allowedTabs[0];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = $allowedTabs[0];
}

// 製品定義（config/sales-tools-products.json から読込） — products タブが使用
$ppConfigRaw = @file_get_contents(__DIR__ . '/../config/sales-tools-products.json');
$ppConfig    = $ppConfigRaw ? json_decode($ppConfigRaw, true) : null;
if (!is_array($ppConfig)) $ppConfig = ['products' => [], 'common' => []];
$ppProductsForJs = [
    'products' => $ppConfig['products'] ?? [],
    'common'   => $ppConfig['common']   ?? [],
];

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

    <!-- タブ（権限のあるタブのみ表示） -->
    <nav class="st-tabs" role="tablist">
        <?php if (in_array('products', $allowedTabs)): ?>
        <a href="?tab=products" class="st-tab <?= $activeTab === 'products' ? 'active' : '' ?>" data-tab="products" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2"/>
                <line x1="8" y1="21" x2="16" y2="21"/>
                <line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
            製品別
        </a>
        <?php endif; ?>
        <?php if (in_array('customers', $allowedTabs)): ?>
        <a href="?tab=customers" class="st-tab <?= $activeTab === 'customers' ? 'active' : '' ?>" data-tab="customers" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            アカウントマネジメント
        </a>
        <?php endif; ?>
        <?php if (in_array('catalogs', $allowedTabs)): ?>
        <a href="?tab=catalogs" class="st-tab <?= $activeTab === 'catalogs' ? 'active' : '' ?>" data-tab="catalogs" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
            </svg>
            カタログ
        </a>
        <?php endif; ?>
        <?php if (in_array('scripts', $allowedTabs)): ?>
        <a href="?tab=scripts" class="st-tab <?= $activeTab === 'scripts' ? 'active' : '' ?>" data-tab="scripts" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            トークスクリプト
        </a>
        <?php endif; ?>
        <?php if (in_array('history', $allowedTabs)): ?>
        <a href="?tab=history" class="st-tab <?= $activeTab === 'history' ? 'active' : '' ?>" data-tab="history" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            見積履歴
        </a>
        <?php endif; ?>
        <?php if (in_array('leads', $allowedTabs)): ?>
        <a href="?tab=leads" class="st-tab <?= $activeTab === 'leads' ? 'active' : '' ?>" data-tab="leads" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            リード管理
        </a>
        <?php endif; ?>
        <?php if (in_array('create', $allowedTabs)): ?>
        <a href="?tab=create" class="st-tab cta <?= $activeTab === 'create' ? 'active' : '' ?>" data-tab="create" role="tab">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="9" y1="13" x2="15" y2="13"/>
                <line x1="12" y1="10" x2="12" y2="16"/>
            </svg>
            見積作成
        </a>
        <?php endif; ?>
    </nav>

    <!-- タブコンテンツ（権限のあるタブのみ読み込み） -->
    <?php if (in_array('products', $allowedTabs)): ?>
    <?php include __DIR__ . "/sales-tools/tabs/products.php"; ?>
    <?php endif; ?>

    <?php if (in_array('customers', $allowedTabs)): ?>
    <?php include __DIR__ . "/sales-tools/tabs/customers.php"; ?>
    <?php endif; ?>

    <?php if (in_array('catalogs', $allowedTabs)): ?>
    <?php include __DIR__ . "/sales-tools/tabs/catalogs.php"; ?>
    <?php endif; ?>

    <?php if (in_array('scripts', $allowedTabs)): ?>
    <?php include __DIR__ . "/sales-tools/tabs/scripts.php"; ?>
    <?php endif; ?>

    <?php if (in_array('history', $allowedTabs)): ?>
    <?php include __DIR__ . "/sales-tools/tabs/history.php"; ?>
    <?php endif; ?>

    <?php if (in_array('leads', $allowedTabs)): ?>
    <?php include __DIR__ . "/sales-tools/tabs/leads.php"; ?>
    <?php endif; ?>

    <?php if (in_array('create', $allowedTabs)): ?>
    <?php include __DIR__ . "/sales-tools/tabs/create.php"; ?>
    <?php endif; ?>

</div>

<?php include __DIR__ . "/sales-tools/_scripts.php"; ?>


<?php require_once '../functions/footer.php'; ?>
