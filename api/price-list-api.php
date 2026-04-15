<?php
/**
 * 価格表API
 *
 * 顧客層（ティア）の管理と、製品×顧客層の価格管理
 *
 * アクション:
 *   tier_list          - 顧客層一覧取得
 *   tier_create        - 顧客層追加
 *   tier_update        - 顧客層更新
 *   tier_delete        - 顧客層削除（ソフトデリート）
 *   tier_reorder       - 顧客層並び替え
 *   price_list         - 価格一覧取得（tier_idで絞込）
 *   price_bulk_save    - 価格一括保存
 *   price_delete       - 価格削除
 *   product_list       - 製品一覧取得（価格表で使用するための独自製品マスタ）
 *   product_create     - 製品追加
 *   product_update     - 製品更新
 *   product_delete     - 製品削除
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'rateLimit'      => 100,
    'allowedMethods' => ['GET', 'POST'],
]);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (empty($action)) {
    $input = getJsonInput();
    $action = $input['action'] ?? '';
}

// JSON入力を取得（POST時）
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? $action;
}

$data = getData();

// 初期化
if (!isset($data['price_tiers']))    $data['price_tiers'] = [];
if (!isset($data['price_products'])) $data['price_products'] = [];
if (!isset($data['price_list']))     $data['price_list'] = [];

switch ($action) {

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  顧客層（ティア）
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'tier_list':
        $tiers = filterDeleted($data['price_tiers']);
        // sort_orderで並び替え
        usort($tiers, function ($a, $b) {
            return ($a['sort_order'] ?? 999) - ($b['sort_order'] ?? 999);
        });
        successResponse($tiers);
        break;

    case 'tier_create':
        if (!canEdit()) errorResponse('編集権限がありません', 403);
        $name = trim($input['name'] ?? '');
        if (empty($name)) errorResponse('層名は必須です', 400);
        $description = trim($input['description'] ?? '');

        // sort_order: 既存の最大値+1
        $maxOrder = 0;
        foreach (filterDeleted($data['price_tiers']) as $t) {
            if (($t['sort_order'] ?? 0) > $maxOrder) $maxOrder = $t['sort_order'];
        }

        $newTier = [
            'id'          => uniqid('tier_'),
            'name'        => $name,
            'description' => $description,
            'sort_order'  => $maxOrder + 1,
            'created_at'  => date('Y-m-d H:i:s'),
            'created_by'  => $_SESSION['user_email'] ?? '',
        ];
        $data['price_tiers'][] = $newTier;
        saveData($data);
        successResponse($newTier, '顧客層を追加しました');
        break;

    case 'tier_update':
        if (!canEdit()) errorResponse('編集権限がありません', 403);
        $id   = $input['id'] ?? '';
        $name = trim($input['name'] ?? '');
        if (empty($id) || empty($name)) errorResponse('IDと層名は必須です', 400);

        $found = false;
        foreach ($data['price_tiers'] as &$t) {
            if ($t['id'] === $id && empty($t['deleted_at'])) {
                $t['name']        = $name;
                $t['description'] = trim($input['description'] ?? '');
                $t['updated_at']  = date('Y-m-d H:i:s');
                $t['updated_by']  = $_SESSION['user_email'] ?? '';
                $found = true;
                break;
            }
        }
        unset($t);
        if (!$found) errorResponse('顧客層が見つかりません', 404);
        saveData($data);
        successResponse(null, '顧客層を更新しました');
        break;

    case 'tier_delete':
        if (!canDelete()) errorResponse('削除権限がありません', 403);
        $id = $input['id'] ?? '';
        if (empty($id)) errorResponse('IDは必須です', 400);

        $found = false;
        $tierName = '';
        foreach ($data['price_tiers'] as &$t) {
            if ($t['id'] === $id && empty($t['deleted_at'])) {
                $tierName = $t['name'];
                $t['deleted_at'] = date('Y-m-d H:i:s');
                $t['deleted_by'] = $_SESSION['user_email'] ?? '';
                $found = true;
                break;
            }
        }
        unset($t);
        if (!$found) errorResponse('顧客層が見つかりません', 404);

        // 関連する価格もソフトデリート
        foreach ($data['price_list'] as &$p) {
            if ($p['tier_id'] === $id && empty($p['deleted_at'])) {
                $p['deleted_at'] = date('Y-m-d H:i:s');
                $p['deleted_by'] = $_SESSION['user_email'] ?? '';
            }
        }
        unset($p);

        auditDelete('price_tiers', $id, "顧客層を削除: {$tierName}", ['name' => $tierName]);
        saveData($data);
        successResponse(null, '顧客層を削除しました');
        break;

    case 'tier_reorder':
        if (!canEdit()) errorResponse('編集権限がありません', 403);
        $order = $input['order'] ?? []; // [{id, sort_order}, ...]
        if (!is_array($order)) errorResponse('並び順データが不正です', 400);

        $orderMap = [];
        foreach ($order as $o) {
            if (isset($o['id'], $o['sort_order'])) {
                $orderMap[$o['id']] = (int)$o['sort_order'];
            }
        }
        foreach ($data['price_tiers'] as &$t) {
            if (isset($orderMap[$t['id']])) {
                $t['sort_order'] = $orderMap[$t['id']];
            }
        }
        unset($t);
        saveData($data);
        successResponse(null, '並び順を更新しました');
        break;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  製品マスタ（価格表用）
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'product_list':
        $products = filterDeleted($data['price_products']);
        usort($products, function ($a, $b) {
            return ($a['sort_order'] ?? 999) - ($b['sort_order'] ?? 999);
        });
        successResponse($products);
        break;

    case 'product_create':
        if (!canEdit()) errorResponse('編集権限がありません', 403);
        $productNumber = trim($input['product_number'] ?? '');
        $productName   = trim($input['product_name'] ?? '');
        if (empty($productName)) errorResponse('品名は必須です', 400);

        $category = trim($input['category'] ?? '');
        $unit     = trim($input['unit'] ?? '');
        $memo     = trim($input['memo'] ?? '');

        $maxOrder = 0;
        foreach (filterDeleted($data['price_products']) as $p) {
            if (($p['sort_order'] ?? 0) > $maxOrder) $maxOrder = $p['sort_order'];
        }

        $newProduct = [
            'id'             => uniqid('pp_'),
            'product_number' => $productNumber,
            'product_name'   => $productName,
            'category'       => $category,
            'unit'           => $unit,
            'memo'           => $memo,
            'sort_order'     => $maxOrder + 1,
            'created_at'     => date('Y-m-d H:i:s'),
            'created_by'     => $_SESSION['user_email'] ?? '',
        ];
        $data['price_products'][] = $newProduct;
        saveData($data);
        successResponse($newProduct, '製品を追加しました');
        break;

    case 'product_update':
        if (!canEdit()) errorResponse('編集権限がありません', 403);
        $id = $input['id'] ?? '';
        if (empty($id)) errorResponse('IDは必須です', 400);

        $found = false;
        foreach ($data['price_products'] as &$p) {
            if ($p['id'] === $id && empty($p['deleted_at'])) {
                $p['product_number'] = trim($input['product_number'] ?? $p['product_number']);
                $p['product_name']   = trim($input['product_name'] ?? $p['product_name']);
                $p['category']       = trim($input['category'] ?? $p['category'] ?? '');
                $p['unit']           = trim($input['unit'] ?? $p['unit'] ?? '');
                $p['memo']           = trim($input['memo'] ?? $p['memo'] ?? '');
                $p['updated_at']     = date('Y-m-d H:i:s');
                $p['updated_by']     = $_SESSION['user_email'] ?? '';
                $found = true;
                break;
            }
        }
        unset($p);
        if (!$found) errorResponse('製品が見つかりません', 404);
        saveData($data);
        successResponse(null, '製品を更新しました');
        break;

    case 'product_delete':
        if (!canDelete()) errorResponse('削除権限がありません', 403);
        $id = $input['id'] ?? '';
        if (empty($id)) errorResponse('IDは必須です', 400);

        $found = false;
        $prodName = '';
        foreach ($data['price_products'] as &$p) {
            if ($p['id'] === $id && empty($p['deleted_at'])) {
                $prodName = $p['product_name'];
                $p['deleted_at'] = date('Y-m-d H:i:s');
                $p['deleted_by'] = $_SESSION['user_email'] ?? '';
                $found = true;
                break;
            }
        }
        unset($p);
        if (!$found) errorResponse('製品が見つかりません', 404);

        // 関連する価格もソフトデリート
        foreach ($data['price_list'] as &$pr) {
            if ($pr['product_id'] === $id && empty($pr['deleted_at'])) {
                $pr['deleted_at'] = date('Y-m-d H:i:s');
                $pr['deleted_by'] = $_SESSION['user_email'] ?? '';
            }
        }
        unset($pr);

        auditDelete('price_products', $id, "製品を削除: {$prodName}", ['product_name' => $prodName]);
        saveData($data);
        successResponse(null, '製品を削除しました');
        break;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  価格データ
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'price_list':
        $tierId = $_GET['tier_id'] ?? $input['tier_id'] ?? '';
        $prices = filterDeleted($data['price_list']);
        if (!empty($tierId)) {
            $prices = array_values(array_filter($prices, function ($p) use ($tierId) {
                return $p['tier_id'] === $tierId;
            }));
        }
        successResponse($prices);
        break;

    case 'price_bulk_save':
        if (!canEdit()) errorResponse('編集権限がありません', 403);
        $tierId = $input['tier_id'] ?? '';
        $prices = $input['prices'] ?? []; // [{product_id, price, memo?}, ...]
        if (empty($tierId)) errorResponse('顧客層IDは必須です', 400);
        if (!is_array($prices)) errorResponse('価格データが不正です', 400);

        // ティア存在チェック
        $tierExists = false;
        foreach ($data['price_tiers'] as $t) {
            if ($t['id'] === $tierId && empty($t['deleted_at'])) { $tierExists = true; break; }
        }
        if (!$tierExists) errorResponse('指定された顧客層が存在しません', 404);

        $now = date('Y-m-d H:i:s');
        $user = $_SESSION['user_email'] ?? '';
        $savedCount = 0;

        foreach ($prices as $priceItem) {
            $productId = $priceItem['product_id'] ?? '';
            $price     = $priceItem['price'] ?? '';
            $memo      = $priceItem['memo'] ?? '';

            if (empty($productId)) continue;

            // 既存レコードを検索
            $existingIdx = null;
            foreach ($data['price_list'] as $idx => $pl) {
                if ($pl['tier_id'] === $tierId && $pl['product_id'] === $productId && empty($pl['deleted_at'])) {
                    $existingIdx = $idx;
                    break;
                }
            }

            if ($price === '' || $price === null) {
                // 価格が空の場合、既存レコードがあれば削除
                if ($existingIdx !== null) {
                    $data['price_list'][$existingIdx]['deleted_at'] = $now;
                    $data['price_list'][$existingIdx]['deleted_by'] = $user;
                }
                continue;
            }

            $priceVal = (float)$price;

            if ($existingIdx !== null) {
                // 既存レコード更新
                $data['price_list'][$existingIdx]['price']      = $priceVal;
                $data['price_list'][$existingIdx]['memo']       = $memo;
                $data['price_list'][$existingIdx]['updated_at'] = $now;
                $data['price_list'][$existingIdx]['updated_by'] = $user;
            } else {
                // 新規レコード追加
                $data['price_list'][] = [
                    'id'         => uniqid('pl_'),
                    'tier_id'    => $tierId,
                    'product_id' => $productId,
                    'price'      => $priceVal,
                    'memo'       => $memo,
                    'created_at' => $now,
                    'created_by' => $user,
                ];
            }
            $savedCount++;
        }

        saveData($data);
        successResponse(['saved_count' => $savedCount], "{$savedCount}件の価格を保存しました");
        break;

    case 'price_delete':
        if (!canDelete()) errorResponse('削除権限がありません', 403);
        $id = $input['id'] ?? '';
        if (empty($id)) errorResponse('IDは必須です', 400);

        $found = false;
        foreach ($data['price_list'] as &$pl) {
            if ($pl['id'] === $id && empty($pl['deleted_at'])) {
                $pl['deleted_at'] = date('Y-m-d H:i:s');
                $pl['deleted_by'] = $_SESSION['user_email'] ?? '';
                $found = true;
                break;
            }
        }
        unset($pl);
        if (!$found) errorResponse('価格データが見つかりません', 404);
        saveData($data);
        successResponse(null, '価格を削除しました');
        break;

    default:
        errorResponse('不明なアクションです: ' . htmlspecialchars($action), 400);
}
