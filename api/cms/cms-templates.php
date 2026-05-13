<?php
/**
 * CMS お知らせテンプレート ヘルパー
 * - 投稿時に呼び出してフォーム本文に流し込むテンプレートを管理
 * - 保存先: config/cms-templates.json (平文・機密情報なし)
 */

require_once __DIR__ . '/../../config/config.php';

define('CMS_TEMPLATES_FILE', dirname(__DIR__, 2) . '/config/cms-templates.json');

/**
 * テンプレート一覧を取得（更新日時降順）
 * @return array
 */
function cmsGetTemplates() {
    if (!file_exists(CMS_TEMPLATES_FILE)) return [];
    $raw = @file_get_contents(CMS_TEMPLATES_FILE);
    if ($raw === false) return [];
    $list = json_decode($raw, true);
    if (!is_array($list)) return [];

    usort($list, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
    return $list;
}

/**
 * テンプレート単体取得
 */
function cmsGetTemplate($id) {
    foreach (cmsGetTemplates() as $t) {
        if (($t['id'] ?? '') === $id) return $t;
    }
    return null;
}

/**
 * 全テンプレートを保存
 */
function cmsSaveTemplates($list) {
    $dir = dirname(CMS_TEMPLATES_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $json = json_encode(array_values($list), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $tmp  = CMS_TEMPLATES_FILE . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, CMS_TEMPLATES_FILE);
}

/**
 * ID 生成（衝突しないランダムID）
 */
function cmsGenTemplateId() {
    return 'tpl_' . bin2hex(random_bytes(6));
}

/**
 * テンプレート新規作成
 */
function cmsCreateTemplate($input) {
    $list = cmsGetTemplates();
    $now  = date('Y-m-d H:i:s');
    $user = $_SESSION['user_email'] ?? 'unknown';

    $new = [
        'id'           => cmsGenTemplateId(),
        'name'         => trim((string)($input['name'] ?? '')),
        'description'  => trim((string)($input['description'] ?? '')),
        'category'     => trim((string)($input['category'] ?? '')),
        'title_hint'   => trim((string)($input['title_hint'] ?? '')),
        'body'         => (string)($input['body'] ?? ''),
        'created_at'   => $now,
        'created_by'   => $user,
        'updated_at'   => $now,
        'updated_by'   => $user,
    ];
    if ($new['name'] === '') return ['ok' => false, 'error' => 'テンプレート名は必須です'];

    $list[] = $new;
    if (!cmsSaveTemplates($list)) return ['ok' => false, 'error' => '保存に失敗しました'];
    return ['ok' => true, 'template' => $new];
}

/**
 * テンプレート更新
 */
function cmsUpdateTemplate($id, $input) {
    $list = cmsGetTemplates();
    $now  = date('Y-m-d H:i:s');
    $user = $_SESSION['user_email'] ?? 'unknown';
    $found = false;

    foreach ($list as &$t) {
        if (($t['id'] ?? '') !== $id) continue;
        if (isset($input['name']))        $t['name']        = trim((string)$input['name']);
        if (isset($input['description'])) $t['description'] = trim((string)$input['description']);
        if (isset($input['category']))    $t['category']    = trim((string)$input['category']);
        if (isset($input['title_hint']))  $t['title_hint']  = trim((string)$input['title_hint']);
        if (isset($input['body']))        $t['body']        = (string)$input['body'];
        $t['updated_at'] = $now;
        $t['updated_by'] = $user;
        if ($t['name'] === '') return ['ok' => false, 'error' => 'テンプレート名は必須です'];
        $found = true;
        break;
    }
    unset($t);

    if (!$found) return ['ok' => false, 'error' => 'テンプレートが見つかりません'];
    if (!cmsSaveTemplates($list)) return ['ok' => false, 'error' => '保存に失敗しました'];
    return ['ok' => true];
}

/**
 * テンプレート削除
 */
function cmsDeleteTemplate($id) {
    $list   = cmsGetTemplates();
    $before = count($list);
    $list   = array_values(array_filter($list, fn($t) => ($t['id'] ?? '') !== $id));
    if (count($list) === $before) return ['ok' => false, 'error' => 'テンプレートが見つかりません'];
    if (!cmsSaveTemplates($list)) return ['ok' => false, 'error' => '保存に失敗しました'];
    return ['ok' => true];
}
