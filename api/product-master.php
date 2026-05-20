<?php
/**
 * 製品マスタ API（管理部専用）
 *
 *   GET  ?action=list                          全製品 (products + common) を返す
 *   POST action=add        body: {section, product:{id,name,...}}
 *   POST action=update     body: {section, id, patch:{...}}
 *   POST action=delete     body: {section, id}
 *   POST action=reorder    body: {section, ids:[...]}
 *
 * section: 'products' または 'common'
 * データ: config/sales-tools-products.json
 * 権限: admin のみ
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'rateLimit'   => false,
]);

if (!isAdmin()) {
    errorResponse('admin のみアクセス可能です', 403);
}

define('PRODUCTS_CONFIG_FILE', __DIR__ . '/../config/sales-tools-products.json');

function pm_load_config(): array {
    if (!file_exists(PRODUCTS_CONFIG_FILE)) {
        return ['_comment' => '', 'products' => [], 'common' => []];
    }
    $raw = @file_get_contents(PRODUCTS_CONFIG_FILE);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) $data = ['products' => [], 'common' => []];
    if (!isset($data['products']) || !is_array($data['products'])) $data['products'] = [];
    if (!isset($data['common'])   || !is_array($data['common']))   $data['common']   = [];
    return $data;
}

function pm_save_config(array $data): void {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) throw new Exception('JSON エンコードに失敗しました');
    $tmp = PRODUCTS_CONFIG_FILE . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        throw new Exception('一時ファイルへの書き込みに失敗しました');
    }
    if (!@rename($tmp, PRODUCTS_CONFIG_FILE)) {
        @unlink($tmp);
        throw new Exception('設定ファイルの保存に失敗しました');
    }
}

function pm_normalize_section(string $section): string {
    return $section === 'common' ? 'common' : 'products';
}

function pm_sanitize_item(array $in, string $section): array {
    $id = trim((string)($in['id'] ?? ''));
    if (!preg_match('/^[a-z0-9_-]+$/', $id)) {
        throw new Exception('id は半角英小文字・数字・ハイフン・アンダースコアのみ使用できます');
    }
    $item = [
        'id'    => $id,
        'name'  => trim((string)($in['name']  ?? '')),
        'sub'   => trim((string)($in['sub']   ?? '')),
        'color' => trim((string)($in['color'] ?? 'gray')),
        'icon'  => (string)($in['icon']  ?? ''),
        'match' => (string)($in['match'] ?? ''),
    ];
    // 画像アイコン (アップロード済み URL)。設定されていれば SVG path より優先される。
    $iconImage = trim((string)($in['icon_image'] ?? ''));
    if ($iconImage !== '') {
        // 同一オリジン内の相対パスのみ許可
        if (!preg_match('#^/uploads/product-icons/[A-Za-z0-9_\-.]+$#', $iconImage)) {
            throw new Exception('icon_image は /uploads/product-icons/ 配下のファイルのみ指定できます');
        }
        $item['icon_image'] = $iconImage;
    }
    if (isset($in['flags'])) {
        $flags = trim((string)$in['flags']);
        if ($flags !== '') $item['flags'] = $flags;
    }
    if ($section === 'products') {
        $item['name_en']       = trim((string)($in['name_en']     ?? ''));
        $item['description']   = trim((string)($in['description'] ?? ''));
        $item['catalog_count'] = max(0, (int)($in['catalog_count'] ?? 0));
        $item['script_count']  = max(0, (int)($in['script_count']  ?? 0));
    }
    // 正規表現のバリデーション
    if ($item['match'] !== '') {
        $delim = '/' . str_replace('/', '\\/', $item['match']) . '/' . ($item['flags'] ?? '');
        if (@preg_match($delim, '') === false) {
            throw new Exception('match の正規表現が不正です: ' . $item['match']);
        }
    }
    return $item;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ----- GET -----
if ($method === 'GET' && $action === 'list') {
    $data = pm_load_config();
    successResponse([
        'products' => $data['products'],
        'common'   => $data['common'],
    ]);
}

if ($method !== 'POST') errorResponse('不明なリクエストです', 405);

verifyCsrfToken();
$input  = getJsonInput();
$action = $input['action'] ?? $action;
$section = pm_normalize_section((string)($input['section'] ?? 'products'));

try {
    $data = pm_load_config();

    if ($action === 'add') {
        $product = is_array($input['product'] ?? null) ? $input['product'] : [];
        $item = pm_sanitize_item($product, $section);
        // id 重複チェック (両セクション通して)
        foreach (['products', 'common'] as $sec) {
            foreach ($data[$sec] as $existing) {
                if (($existing['id'] ?? '') === $item['id']) {
                    errorResponse('id が既に存在します: ' . $item['id'], 400);
                }
            }
        }
        $data[$section][] = $item;
        pm_save_config($data);
        successResponse(['id' => $item['id'], 'section' => $section], '製品を追加しました');
    }

    if ($action === 'update') {
        $id    = trim((string)($input['id'] ?? ''));
        $patch = is_array($input['patch'] ?? null) ? $input['patch'] : [];
        if ($id === '') errorResponse('id は必須です', 400);

        $idx = -1;
        foreach ($data[$section] as $i => $existing) {
            if (($existing['id'] ?? '') === $id) { $idx = $i; break; }
        }
        if ($idx < 0) errorResponse('製品が見つかりません: ' . $id, 404);

        // 既存値とマージしてサニタイズ
        $merged = array_merge($data[$section][$idx], $patch);
        $merged['id'] = $id; // id は変更不可
        $item = pm_sanitize_item($merged, $section);
        $data[$section][$idx] = $item;
        pm_save_config($data);
        successResponse(['id' => $id, 'section' => $section], '製品を更新しました');
    }

    if ($action === 'delete') {
        $id = trim((string)($input['id'] ?? ''));
        if ($id === '') errorResponse('id は必須です', 400);
        $before = count($data[$section]);
        $data[$section] = array_values(array_filter(
            $data[$section],
            fn($p) => ($p['id'] ?? '') !== $id
        ));
        if (count($data[$section]) === $before) {
            errorResponse('製品が見つかりません: ' . $id, 404);
        }
        pm_save_config($data);
        successResponse(['id' => $id, 'section' => $section], '製品を削除しました');
    }

    if ($action === 'reorder') {
        $ids = is_array($input['ids'] ?? null) ? $input['ids'] : [];
        if (!$ids) errorResponse('ids は必須です', 400);
        $byId = [];
        foreach ($data[$section] as $p) {
            $byId[$p['id'] ?? ''] = $p;
        }
        $reordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $reordered[] = $byId[$id];
                unset($byId[$id]);
            }
        }
        // 並び替えに含まれなかった項目は末尾に維持
        foreach ($byId as $remaining) $reordered[] = $remaining;
        $data[$section] = $reordered;
        pm_save_config($data);
        successResponse(['count' => count($reordered)], '並び順を保存しました');
    }

    errorResponse('不明なアクションです: ' . $action, 400);

} catch (Exception $e) {
    errorResponse($e->getMessage(), 400);
}
