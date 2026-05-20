<?php
/**
 * 外部リンクマスタ ヘルパー
 *
 * 各ページから外部URL（HP・業務システム等）を参照するための共通関数。
 * URLが変わったら /pages/external-links.php から1箇所編集するだけで全ページに反映される。
 *
 * 使い方:
 *   require_once __DIR__ . '/links.php';
 *   $url = getLink('product.monitarou.hp');           // URLだけ取得
 *   $url = getLink('product.foo.hp', '#');            // 未定義時はフォールバック
 *   $link = getLinkRecord('product.monitarou.hp');    // 全フィールド取得
 *   $list = getLinksByCategory('product_hp');         // カテゴリ一覧
 *   $all  = loadExternalLinks();                      // 全データ（管理画面用）
 *   saveExternalLinks($data);                         // 全データ保存（管理画面用）
 */

const EXTERNAL_LINKS_PATH = __DIR__ . '/../config/external-links.json';

/**
 * 外部リンク用アイコンライブラリ（プリセット）。
 *
 * 新しいアイコンを追加したい場合はここに 1 エントリ追加するだけ。
 * SVG は viewBox="0 0 24 24" の <path>/<line>/<circle> 等の中身のみを格納する。
 * 描画は renderLinkIcon($iconId) で行う。
 */
function getLinkIconLibrary(): array {
    return [
        'globe'    => ['label' => '地球（HP）',    'svg' => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>'],
        'link'     => ['label' => 'リンク',         'svg' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>'],
        'external' => ['label' => '外部リンク',     'svg' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>'],
        'document' => ['label' => '書類・仕様書',   'svg' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
        'pdf'      => ['label' => 'PDF',            'svg' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><text x="8" y="18" font-size="6" fill="currentColor" stroke="none" font-weight="bold">PDF</text>'],
        'building' => ['label' => '会社・建物',     'svg' => '<rect x="3" y="3" width="18" height="18" rx="1"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/>'],
        'monitor'  => ['label' => 'モニター',       'svg' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'],
        'video'    => ['label' => '動画・配信',     'svg' => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>'],
        'chart'    => ['label' => 'グラフ・分析',   'svg' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6"  y1="20" x2="6"  y2="14"/>'],
        'database' => ['label' => 'データベース',   'svg' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>'],
        'cloud'    => ['label' => 'クラウド',       'svg' => '<path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/>'],
        'mail'     => ['label' => 'メール',         'svg' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'],
        'phone'    => ['label' => '電話',           'svg' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>'],
        'shopping' => ['label' => 'EC・購買',       'svg' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>'],
        'wrench'   => ['label' => 'ツール',         'svg' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>'],
        'folder'   => ['label' => 'フォルダ',       'svg' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>'],
        'book'     => ['label' => 'マニュアル',     'svg' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
        'users'    => ['label' => '顧客・人',       'svg' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
        'pin'      => ['label' => '所在地',         'svg' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>'],
        'calendar' => ['label' => 'カレンダー',     'svg' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
    ];
}

/**
 * アイコン SVG を生成する。$iconId が無効なら globe にフォールバック。
 *
 * @param string $iconId アイコンID（getLinkIconLibrary のキー）
 * @param int    $size   ピクセルサイズ
 * @param string $extraClass 追加クラス名
 */
function renderLinkIcon(string $iconId, int $size = 16, string $extraClass = ''): string {
    $lib = getLinkIconLibrary();
    if (!isset($lib[$iconId])) $iconId = 'globe';
    $svg = $lib[$iconId]['svg'];
    $cls = trim('el-icon ' . $extraClass);
    return '<svg class="' . htmlspecialchars($cls) . '" width="' . (int)$size . '" height="' . (int)$size
         . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
         . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . $svg . '</svg>';
}

/**
 * リンクに紐づくアイコンIDを取得（未設定なら globe）。
 */
function getLinkIcon(string $key, string $fallback = 'globe'): string {
    $rec = getLinkRecord($key);
    if (!$rec || empty($rec['icon'])) return $fallback;
    $lib = getLinkIconLibrary();
    return isset($lib[$rec['icon']]) ? $rec['icon'] : $fallback;
}

/**
 * 全外部リンク設定を読み込む（キャッシュ付き）
 *
 * @param bool $forceReload true ならキャッシュを破棄して再読込
 */
function loadExternalLinks(bool $forceReload = false): array {
    static $cache = null;
    if ($cache !== null && !$forceReload) return $cache;

    if (!file_exists(EXTERNAL_LINKS_PATH)) {
        $cache = ['categories' => [], 'links' => []];
        return $cache;
    }
    $raw  = @file_get_contents(EXTERNAL_LINKS_PATH);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) $data = ['categories' => [], 'links' => []];
    if (!isset($data['categories'])) $data['categories'] = [];
    if (!isset($data['links']))      $data['links']      = [];
    $cache = $data;
    return $cache;
}

/**
 * 外部リンク設定を保存する（バックアップ付き）
 */
function saveExternalLinks(array $data): bool {
    if (!isset($data['links']))      $data['links']      = [];
    if (!isset($data['categories'])) $data['categories'] = [];
    $data['updated_at'] = date('Y-m-d H:i:s');

    // 既存ファイルをタイムスタンプ付きでバックアップ（1日1回まで）
    if (file_exists(EXTERNAL_LINKS_PATH)) {
        $backupDir = __DIR__ . '/../data/backups/external-links';
        if (!is_dir($backupDir)) @mkdir($backupDir, 0775, true);
        $backupFile = $backupDir . '/' . date('Ymd_His') . '.json';
        @copy(EXTERNAL_LINKS_PATH, $backupFile);
    }

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $result = @file_put_contents(EXTERNAL_LINKS_PATH, $json, LOCK_EX);

    // キャッシュ破棄
    loadExternalLinks(true);
    return $result !== false;
}

/**
 * キーを指定して URL だけ取得する。未定義のときは $fallback を返す。
 */
function getLink(string $key, ?string $fallback = null): ?string {
    $rec = getLinkRecord($key);
    if (!$rec || empty($rec['url'])) return $fallback;
    return $rec['url'];
}

/**
 * キーを指定して全フィールドを取得（label/url/category/note 等）。
 */
function getLinkRecord(string $key): ?array {
    $data = loadExternalLinks();
    foreach ($data['links'] as $l) {
        if (($l['key'] ?? '') === $key) return $l;
    }
    return null;
}

/**
 * カテゴリIDに紐付くリンクを全て返す（管理画面・一覧表示用）。
 */
function getLinksByCategory(string $categoryId): array {
    $data = loadExternalLinks();
    $out  = [];
    foreach ($data['links'] as $l) {
        if (($l['category'] ?? '') === $categoryId) $out[] = $l;
    }
    return $out;
}

/**
 * カテゴリ一覧（order 昇順）を返す。
 */
function getLinkCategories(): array {
    $data = loadExternalLinks();
    $cats = $data['categories'];
    usort($cats, fn($a, $b) => ($a['order'] ?? 999) <=> ($b['order'] ?? 999));
    return $cats;
}

/**
 * 既存リンクの URL を更新する（管理 API 用）。
 */
function updateLink(string $key, array $patch, string $updatedBy = ''): bool {
    $data = loadExternalLinks();
    $found = false;
    foreach ($data['links'] as &$l) {
        if (($l['key'] ?? '') === $key) {
            foreach (['category', 'label', 'url', 'note', 'icon'] as $f) {
                if (array_key_exists($f, $patch)) $l[$f] = $patch[$f];
            }
            $l['updated_at'] = date('Y-m-d H:i:s');
            if ($updatedBy !== '') $l['updated_by'] = $updatedBy;
            $found = true;
            break;
        }
    }
    unset($l);
    if (!$found) return false;
    return saveExternalLinks($data);
}

/**
 * 新規リンクを追加する。
 */
function addLink(array $record, string $updatedBy = ''): bool {
    if (empty($record['key'])) return false;
    $data = loadExternalLinks();
    foreach ($data['links'] as $l) {
        if (($l['key'] ?? '') === $record['key']) return false; // 重複
    }
    $record['updated_at'] = date('Y-m-d H:i:s');
    if ($updatedBy !== '') $record['updated_by'] = $updatedBy;
    $data['links'][] = $record;
    return saveExternalLinks($data);
}

/**
 * リンクを削除する。
 */
function deleteLink(string $key): bool {
    $data = loadExternalLinks();
    $before = count($data['links']);
    $data['links'] = array_values(array_filter(
        $data['links'],
        fn($l) => ($l['key'] ?? '') !== $key
    ));
    if (count($data['links']) === $before) return false;
    return saveExternalLinks($data);
}

/**
 * URL の一括置換（ドメイン変更時の救済策）。
 *
 * @param string $search  検索文字列
 * @param string $replace 置換後文字列
 * @return int 置換件数
 */
function bulkReplaceLinkUrls(string $search, string $replace, string $updatedBy = ''): int {
    if ($search === '') return 0;
    $data  = loadExternalLinks();
    $count = 0;
    foreach ($data['links'] as &$l) {
        if (!isset($l['url'])) continue;
        if (strpos($l['url'], $search) === false) continue;
        $l['url'] = str_replace($search, $replace, $l['url']);
        $l['updated_at'] = date('Y-m-d H:i:s');
        if ($updatedBy !== '') $l['updated_by'] = $updatedBy;
        $count++;
    }
    unset($l);
    if ($count > 0) saveExternalLinks($data);
    return $count;
}
