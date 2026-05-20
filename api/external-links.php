<?php
/**
 * 外部リンクマスタ API（管理部専用）
 *
 *   GET  ?action=list                  全リンク・カテゴリを返す
 *   POST action=add        body: {key, category, label, url, note}
 *   POST action=update     body: {key, patch: {category?, label?, url?, note?}}
 *   POST action=delete     body: {key}
 *   POST action=bulk_replace body: {search, replace}
 *
 * データ: config/external-links.json
 * 権限: admin のみ
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/links.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false, // POST 内で個別検証
    'rateLimit'   => false,
]);

if (!isAdmin()) {
    errorResponse('admin のみアクセス可能です', 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$me     = $_SESSION['user_name'] ?? ($_SESSION['employee_name'] ?? '');

// ----- GET -----
if ($method === 'GET' && $action === 'list') {
    $data = loadExternalLinks(true);
    // カテゴリは order でソート
    if (!empty($data['categories'])) {
        usort($data['categories'], fn($a, $b) => ($a['order'] ?? 999) <=> ($b['order'] ?? 999));
    }
    successResponse($data);
}

if ($method !== 'POST') errorResponse('不明なリクエストです', 405);

verifyCsrfToken();
$input  = getJsonInput();
$action = $input['action'] ?? $action;

if ($action === 'add') {
    $key      = trim($input['key']      ?? '');
    $category = trim($input['category'] ?? '');
    $label    = trim($input['label']    ?? '');
    $url      = trim($input['url']      ?? '');
    $note     = trim($input['note']     ?? '');
    $icon     = trim($input['icon']     ?? 'globe');
    if ($key === '')   errorResponse('key は必須です', 400);
    if ($label === '') errorResponse('label は必須です', 400);
    // key のフォーマット制約: 英数字 + ドット + アンダースコア + ハイフン
    if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $key)) {
        errorResponse('key は英数字・ドット・ハイフン・アンダースコアのみ使用できます', 400);
    }
    // アイコンIDの妥当性チェック（無効なら globe にフォールバック）
    $lib = getLinkIconLibrary();
    if (!isset($lib[$icon])) $icon = 'globe';
    $ok = addLink([
        'key'      => $key,
        'category' => $category,
        'label'    => $label,
        'url'      => $url,
        'note'     => $note,
        'icon'     => $icon,
    ], $me);
    if (!$ok) errorResponse('追加に失敗しました（key 重複の可能性）', 400);
    successResponse(['key' => $key], 'リンクを追加しました');
}

if ($action === 'update') {
    $key   = trim($input['key'] ?? '');
    $patch = is_array($input['patch'] ?? null) ? $input['patch'] : [];
    if ($key === '') errorResponse('key は必須です', 400);
    if (!getLinkRecord($key)) errorResponse('指定されたキーが見つかりません: ' . $key, 404);
    // アイコンIDの妥当性チェック（無効なら globe にフォールバック）
    if (isset($patch['icon'])) {
        $lib = getLinkIconLibrary();
        if (!isset($lib[$patch['icon']])) $patch['icon'] = 'globe';
    }
    $ok = updateLink($key, $patch, $me);
    if (!$ok) errorResponse('更新に失敗しました', 500);
    successResponse(['key' => $key], 'リンクを更新しました');
}

if ($action === 'delete') {
    $key = trim($input['key'] ?? '');
    if ($key === '') errorResponse('key は必須です', 400);
    $ok = deleteLink($key);
    if (!$ok) errorResponse('削除に失敗しました（既に存在しない可能性）', 404);
    successResponse(['key' => $key], 'リンクを削除しました');
}

if ($action === 'bulk_replace') {
    $search  = (string)($input['search']  ?? '');
    $replace = (string)($input['replace'] ?? '');
    if ($search === '') errorResponse('検索文字列は必須です', 400);
    $count = bulkReplaceLinkUrls($search, $replace, $me);
    successResponse(['count' => $count], $count . ' 件を置換しました');
}

errorResponse('不明なアクションです: ' . $action, 400);
