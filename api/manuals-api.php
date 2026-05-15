<?php
/**
 * マニュアル一覧 API（Google スライド等のリンク集）
 *
 * GET  ?action=list           マニュアル一覧（削除済み除外、検索/フィルタ）
 * GET  ?action=get&id=xxx     1件取得
 * POST action=create          作成（product 以上）
 * POST action=update          更新（product 以上）
 * POST action=delete          削除（admin のみ・論理削除）
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false, // POST内で個別に検証
    'rateLimit'   => false,
]);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// URL は http/https のみ許可
function manuals_valid_url($url) {
    if (!is_string($url) || $url === '') return false;
    if (!preg_match('#^https?://#i', $url)) return false;
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// 公開範囲の正規化と検証
// 受付値: 'all' / 'product' / 'admin'
// 保存値: []  / ['product','admin'] / ['admin']
function manuals_normalize_visibility($value) {
    if ($value === 'all' || $value === '' || $value === null) return [];
    if ($value === 'product') return ['product', 'admin'];
    if ($value === 'admin')   return ['admin'];
    // 配列で渡ってきた場合（前バージョン互換）はホワイトリストで絞る
    if (is_array($value)) {
        $allowed = ['sales', 'product', 'admin'];
        $v = array_values(array_intersect($value, $allowed));
        // sales を含む or 空 → 全員
        if (empty($v) || in_array('sales', $v, true)) return [];
        return $v;
    }
    return [];
}

// マニュアルがユーザーに見えるか
function manuals_user_can_view($manual, $userRole) {
    $visibleTo = $manual['visible_to'] ?? [];
    if (!is_array($visibleTo) || empty($visibleTo)) return true;
    return in_array($userRole, $visibleTo, true);
}

// ==============================
// GET: 一覧（検索付き）
// ==============================
if ($method === 'GET' && $action === 'list') {
    $data    = getData();
    $manuals = $data['manuals'] ?? [];
    $userRole = $_SESSION['user_role'] ?? 'sales';
    $manuals = array_values(array_filter($manuals, fn($m) => empty($m['deleted_at'])));

    // 公開範囲フィルタ（ユーザーが見られないものを除外）
    $manuals = array_values(array_filter($manuals, fn($m) => manuals_user_can_view($m, $userRole)));

    $q        = trim($_GET['q'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $tag      = trim($_GET['tag'] ?? '');

    if ($q !== '') {
        $needle = mb_strtolower($q);
        $manuals = array_values(array_filter($manuals, function($m) use ($needle) {
            $hay = mb_strtolower(
                ($m['title'] ?? '') . ' ' .
                ($m['description'] ?? '') . ' ' .
                ($m['search_keywords'] ?? '') . ' ' .
                ($m['category'] ?? '') . ' ' .
                (is_array($m['tags'] ?? null) ? implode(' ', $m['tags']) : '')
            );
            return mb_strpos($hay, $needle) !== false;
        }));
    }
    if ($category !== '') {
        $manuals = array_values(array_filter($manuals, fn($m) => ($m['category'] ?? '') === $category));
    }
    if ($tag !== '') {
        $manuals = array_values(array_filter($manuals, function($m) use ($tag) {
            $tags = $m['tags'] ?? [];
            return is_array($tags) && in_array($tag, $tags, true);
        }));
    }

    usort($manuals, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));

    // 集計（カテゴリ・タグ）— ユーザーが見られるものだけ集計
    $allActive = array_values(array_filter($data['manuals'] ?? [],
        fn($m) => empty($m['deleted_at']) && manuals_user_can_view($m, $userRole)));
    $categoryCounts = [];
    $tagCounts = [];
    foreach ($allActive as $m) {
        $c = $m['category'] ?? '';
        if ($c !== '') $categoryCounts[$c] = ($categoryCounts[$c] ?? 0) + 1;
        $tags = $m['tags'] ?? [];
        if (is_array($tags)) {
            foreach ($tags as $t) {
                if (is_string($t) && $t !== '') {
                    $tagCounts[$t] = ($tagCounts[$t] ?? 0) + 1;
                }
            }
        }
    }
    ksort($categoryCounts);
    ksort($tagCounts);

    successResponse([
        'manuals'    => $manuals,
        'total'      => count($manuals),
        'categories' => $categoryCounts,
        'tags'       => $tagCounts,
    ]);
}

// ==============================
// GET: 1件取得
// ==============================
if ($method === 'GET' && $action === 'get') {
    $id = trim($_GET['id'] ?? '');
    if ($id === '') errorResponse('id が必要です', 400);

    $userRole = $_SESSION['user_role'] ?? 'sales';
    $data = getData();
    foreach ($data['manuals'] ?? [] as $m) {
        if (($m['id'] ?? '') === $id && empty($m['deleted_at'])) {
            if (!manuals_user_can_view($m, $userRole)) {
                errorResponse('権限がありません', 403);
            }
            successResponse(['manual' => $m]);
        }
    }
    errorResponse('マニュアルが見つかりません', 404);
}

// ==============================
// POST: CSRF必須
// ==============================
if ($method === 'POST') {
    verifyCsrfToken();
    $input = getJsonInput();
    $action = $input['action'] ?? $action;

    // ---- 作成（product 以上） ----
    if ($action === 'create') {
        if (!canEdit()) errorResponse('権限がありません', 403);

        $title       = trim($input['title'] ?? '');
        $url         = trim($input['url'] ?? '');
        $description = trim($input['description'] ?? '');
        $keywords    = trim($input['search_keywords'] ?? '');
        $category    = trim($input['category'] ?? '');
        $tags        = $input['tags'] ?? [];

        if ($title === '') errorResponse('タイトルは必須です', 400);
        if ($url === '')   errorResponse('URL は必須です', 400);
        if (!manuals_valid_url($url)) errorResponse('正しい URL を入力してください (http:// または https:// で始まる必要があります)', 400);

        if (!is_array($tags)) $tags = [];
        $tags = array_values(array_filter(array_map('strval', $tags), fn($t) => trim($t) !== ''));

        $visibility = manuals_normalize_visibility($input['visibility'] ?? ($input['visible_to'] ?? 'all'));

        $data = getData();
        $manual = [
            'id'              => 'man_' . uniqid('', true),
            'title'           => $title,
            'url'             => $url,
            'description'     => $description,
            'search_keywords' => $keywords,
            'category'        => $category,
            'tags'            => $tags,
            'visible_to'      => $visibility,
            'created_by'      => $_SESSION['user_email'] ?? '',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
            'deleted_at'      => null,
            'deleted_by'      => null,
        ];
        $data['manuals'][] = $manual;
        saveData($data, ['manuals']);

        if (function_exists('auditCreate')) {
            auditCreate('manuals', $manual['id'], 'マニュアル登録: ' . $title);
        }
        logInfo('manual_created', ['id' => $manual['id'], 'title' => $title]);

        successResponse(['manual' => $manual], 'マニュアルを登録しました');
    }

    // ---- 更新（product 以上） ----
    if ($action === 'update') {
        if (!canEdit()) errorResponse('権限がありません', 403);

        $id = trim($input['id'] ?? '');
        if ($id === '') errorResponse('id が必要です', 400);

        $title = trim($input['title'] ?? '');
        $url   = trim($input['url'] ?? '');
        if ($title === '') errorResponse('タイトルは必須です', 400);
        if ($url === '')   errorResponse('URL は必須です', 400);
        if (!manuals_valid_url($url)) errorResponse('正しい URL を入力してください (http:// または https:// で始まる必要があります)', 400);

        $tags = $input['tags'] ?? [];
        if (!is_array($tags)) $tags = [];
        $tags = array_values(array_filter(array_map('strval', $tags), fn($t) => trim($t) !== ''));

        $visibility = manuals_normalize_visibility($input['visibility'] ?? ($input['visible_to'] ?? 'all'));

        $data = getData();
        $found = false;
        foreach ($data['manuals'] as &$m) {
            if (($m['id'] ?? '') === $id && empty($m['deleted_at'])) {
                $m['title']           = $title;
                $m['url']             = $url;
                $m['description']     = trim($input['description'] ?? '');
                $m['search_keywords'] = trim($input['search_keywords'] ?? '');
                $m['category']        = trim($input['category'] ?? '');
                $m['tags']            = $tags;
                $m['visible_to']      = $visibility;
                $m['updated_at']      = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        unset($m);

        if (!$found) errorResponse('マニュアルが見つかりません', 404);

        saveData($data, ['manuals']);

        if (function_exists('auditUpdate')) {
            auditUpdate('manuals', $id, 'マニュアル更新: ' . $title);
        }
        logInfo('manual_updated', ['id' => $id]);

        successResponse([], 'マニュアルを更新しました');
    }

    // ---- 削除（admin のみ・論理削除） ----
    if ($action === 'delete') {
        if (!canDelete()) errorResponse('権限がありません', 403);

        $id = trim($input['id'] ?? '');
        if ($id === '') errorResponse('id が必要です', 400);

        $data = getData();
        $found = false;
        foreach ($data['manuals'] as &$m) {
            if (($m['id'] ?? '') === $id && empty($m['deleted_at'])) {
                $m['deleted_at'] = date('Y-m-d H:i:s');
                $m['deleted_by'] = $_SESSION['user_email'] ?? '';
                $found = true;
                break;
            }
        }
        unset($m);

        if (!$found) errorResponse('マニュアルが見つかりません', 404);

        saveData($data, ['manuals']);

        if (function_exists('auditDelete')) {
            auditDelete('manuals', $id, 'マニュアル削除');
        }
        logInfo('manual_deleted', ['id' => $id]);

        successResponse([], 'マニュアルを削除しました');
    }

    errorResponse('不明なアクションです', 400);
}

errorResponse('無効なリクエストです', 405);
