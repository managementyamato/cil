<?php
/**
 * 価格表 v2 API
 *
 * 設計: docs/price-list-design.md
 *
 * パラメータ: action (GET / POST 共通)
 *
 * 閲覧系 (sales 以上):
 *   GET  ?action=list_products
 *   GET  ?action=get_product&product_id=xxx
 *
 * 管理系 (admin のみ):
 *   POST action=create_product / update_product / delete_product
 *   POST action=create_variant / update_variant / delete_variant
 *   POST action=upsert_price_rule / delete_price_rule
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../functions/price-list-repository.php';

function plAssertView() {
    if (!hasPermission(getPageViewPermission('price-list.php'))) {
        errorResponse('閲覧権限がありません', 403);
    }
}
function plAssertEdit() {
    if (!isAdmin() || !hasPermission(getPageEditPermission('price-list.php'))) {
        errorResponse('編集権限がありません (管理部のみ)', 403);
    }
}

// ─── GET ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    initApi(['requireAuth' => true, 'requireCsrf' => false, 'allowedMethods' => ['GET']]);
    plAssertView();

    $action = $_GET['action'] ?? '';
    try {
        switch ($action) {
            case 'list_products':
                $includeInactive = !empty($_GET['include_inactive']);
                $products = PriceListRepository::listProducts($includeInactive);
                successResponse(['items' => $products]);
                break;

            case 'get_product':
                $productId = $_GET['product_id'] ?? '';
                if ($productId === '') errorResponse('product_id が必要です', 400);
                $matrix = PriceListRepository::getProductMatrix($productId);
                if (!$matrix) errorResponse('製品が見つかりません', 404);
                successResponse($matrix);
                break;

            case 'list_variants':
                $productId = $_GET['product_id'] ?? '';
                if ($productId === '') errorResponse('product_id が必要です', 400);
                $includeInactive = !empty($_GET['include_inactive']);
                $variants = PriceListRepository::listVariants($productId, $includeInactive);
                successResponse(['items' => $variants]);
                break;

            case 'list_price_rules':
                $variantId = $_GET['variant_id'] ?? '';
                if ($variantId === '') errorResponse('variant_id が必要です', 400);
                $rules = PriceListRepository::listPriceRules($variantId);
                successResponse(['items' => $rules]);
                break;

            default:
                errorResponse('未知の action: ' . $action, 400);
        }
    } catch (\Throwable $e) {
        error_log('[price-list API GET] ' . $e->getMessage());
        errorResponse('内部エラー: ' . $e->getMessage(), 500);
    }
    exit;
}

// ─── POST (編集系・全て admin) ─────────────────────────────────────
initApi(['requireAuth' => true, 'requireCsrf' => true, 'allowedMethods' => ['POST']]);
plAssertEdit();

$action = $_POST['action'] ?? '';
$currentUser = $_SESSION['user_email'] ?? '';

/**
 * 簡易 ID 生成 (slug 風)
 */
function pl_slugify(string $s): string {
    $s = mb_strtolower($s);
    $s = preg_replace('/[^a-z0-9ぁ-んァ-ン一-龥\-_ ]/u', '', $s);
    $s = preg_replace('/[\s_]+/', '-', $s);
    return trim($s, '-');
}

try {
    switch ($action) {

        // ── 製品 ────────────────────────────────────────────────────
        case 'create_product': {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') errorResponse('name は必須です', 400);
            $id = trim($_POST['id'] ?? '') ?: pl_slugify($name) . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
            PriceListRepository::createProduct([
                'id'            => $id,
                'code'          => trim($_POST['code']        ?? '') ?: null,
                'name'          => $name,
                'category'      => trim($_POST['category']    ?? '') ?: null,
                'description'   => trim($_POST['description'] ?? '') ?: null,
                'display_order' => (int)($_POST['display_order'] ?? 0),
                'is_active'     => (int)($_POST['is_active']     ?? 1),
            ]);
            successResponse(['id' => $id]);
            break;
        }

        case 'update_product': {
            $id = $_POST['id'] ?? '';
            if ($id === '') errorResponse('id は必須です', 400);
            $name = trim($_POST['name'] ?? '');
            if ($name === '') errorResponse('name は必須です', 400);
            PriceListRepository::updateProduct($id, [
                'code'          => trim($_POST['code']        ?? '') ?: null,
                'name'          => $name,
                'category'      => trim($_POST['category']    ?? '') ?: null,
                'description'   => trim($_POST['description'] ?? '') ?: null,
                'display_order' => (int)($_POST['display_order'] ?? 0),
                'is_active'     => (int)($_POST['is_active']     ?? 1),
            ]);
            successResponse(['id' => $id]);
            break;
        }

        case 'delete_product': {
            $id = $_POST['id'] ?? '';
            if ($id === '') errorResponse('id は必須です', 400);
            PriceListRepository::deleteProduct($id, $currentUser);
            successResponse(['id' => $id]);
            break;
        }

        // ── バリアント ────────────────────────────────────────────
        case 'create_variant': {
            $productId = $_POST['product_id'] ?? '';
            $sizeLabel = trim($_POST['size_label'] ?? '');
            if ($productId === '' || $sizeLabel === '') errorResponse('product_id と size_label は必須です', 400);
            $id = trim($_POST['id'] ?? '') ?: $productId . '-' . pl_slugify($sizeLabel) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            PriceListRepository::createVariant([
                'id'             => $id,
                'product_id'     => $productId,
                'size_label'     => $sizeLabel,
                'size_inch'      => $_POST['size_inch']      ?? null,
                'resolution'     => trim($_POST['resolution']  ?? '') ?: null,
                'screen_area_m2' => $_POST['screen_area_m2'] ?? null,
                'attributes_json'=> isset($_POST['attributes_json']) ? json_decode($_POST['attributes_json'], true) : null,
                'display_order'  => (int)($_POST['display_order'] ?? 0),
                'is_active'      => (int)($_POST['is_active']     ?? 1),
            ]);
            successResponse(['id' => $id]);
            break;
        }

        case 'update_variant': {
            $id = $_POST['id'] ?? '';
            $sizeLabel = trim($_POST['size_label'] ?? '');
            if ($id === '' || $sizeLabel === '') errorResponse('id と size_label は必須です', 400);
            PriceListRepository::updateVariant($id, [
                'size_label'     => $sizeLabel,
                'size_inch'      => $_POST['size_inch']      ?? null,
                'resolution'     => trim($_POST['resolution']  ?? '') ?: null,
                'screen_area_m2' => $_POST['screen_area_m2'] ?? null,
                'attributes_json'=> isset($_POST['attributes_json']) ? json_decode($_POST['attributes_json'], true) : null,
                'display_order'  => (int)($_POST['display_order'] ?? 0),
                'is_active'      => (int)($_POST['is_active']     ?? 1),
            ]);
            successResponse(['id' => $id]);
            break;
        }

        case 'delete_variant': {
            $id = $_POST['id'] ?? '';
            if ($id === '') errorResponse('id は必須です', 400);
            PriceListRepository::deleteVariant($id, $currentUser);
            successResponse(['id' => $id]);
            break;
        }

        // ── 価格ルール ────────────────────────────────────────────
        case 'upsert_price_rule': {
            $variantId       = $_POST['variant_id']       ?? '';
            $rank            = $_POST['customer_rank']    ?? '';
            $txnType         = $_POST['transaction_type'] ?? '';
            $priceLabel      = trim($_POST['price_label'] ?? '');
            $amount          = $_POST['amount']           ?? null;
            if ($variantId === '' || $rank === '' || $txnType === '' || $priceLabel === '' || $amount === null || $amount === '') {
                errorResponse('variant_id / customer_rank / transaction_type / price_label / amount すべて必須です', 400);
            }
            if (!in_array($rank, ['S','A','B'], true))                  errorResponse('customer_rank は S/A/B のいずれか', 400);
            if (!in_array($txnType, ['sale','rental'], true))            errorResponse('transaction_type は sale/rental のいずれか', 400);
            $newId = PriceListRepository::upsertPriceRule([
                'variant_id'       => $variantId,
                'customer_rank'    => $rank,
                'transaction_type' => $txnType,
                'price_label'      => $priceLabel,
                'amount'           => (int)$amount,
                'notes'            => trim($_POST['notes'] ?? '') ?: null,
                'display_order'    => (int)($_POST['display_order'] ?? 0),
            ]);
            successResponse(['id' => $newId]);
            break;
        }

        case 'delete_price_rule': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) errorResponse('id は必須です', 400);
            PriceListRepository::deletePriceRule($id, $currentUser);
            successResponse(['id' => $id]);
            break;
        }

        default:
            errorResponse('未知の action: ' . $action, 400);
    }
} catch (\PDOException $e) {
    error_log('[price-list API POST] PDO: ' . $e->getMessage());
    errorResponse('データベースエラー: ' . $e->getMessage(), 500);
} catch (\Throwable $e) {
    error_log('[price-list API POST] ' . $e->getMessage());
    errorResponse('内部エラー: ' . $e->getMessage(), 500);
}
