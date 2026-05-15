<?php
/**
 * マニュアル一覧 スプレッドシート取り込み API
 *
 * POST action=preview     スプシURL+列マッピングを受け取り、プレビュー（最初の N 行）を返す
 * POST action=import      取り込み実行（同じ URL があれば上書き更新、なければ新規）
 * POST action=save-config 取り込み設定を保存（再同期用）
 * GET  action=load-config 保存された設定を読み込む
 * POST action=resync      保存された設定で再取り込み
 *
 * 権限: product 以上（マニュアル編集権限と同じ）
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/google-sheets.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'rateLimit'   => false,
]);

if (!canEdit()) {
    errorResponse('権限がありません (product 以上が必要)', 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

$CONFIG_FILE = __DIR__ . '/../config/manuals-import-config.json';

// ============================================================
// ヘルパー
// ============================================================

/** スプシ URL から ID を抽出 */
function extractSheetId($url) {
    if (!is_string($url)) return null;
    if (preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m)) return $m[1];
    return null;
}

/** "A" → 0, "B" → 1, "Z" → 25, "AA" → 26 */
function colLetterToIndex($letter) {
    $letter = strtoupper(trim($letter));
    if ($letter === '' || !preg_match('/^[A-Z]+$/', $letter)) return -1;
    $n = 0;
    for ($i = 0, $len = strlen($letter); $i < $len; $i++) {
        $n = $n * 26 + (ord($letter[$i]) - ord('A') + 1);
    }
    return $n - 1;
}

/** 取り込み行を組み立てる: タイトル分割など */
function buildManualFromRow($row, $mapping) {
    $linkIdx = colLetterToIndex($mapping['link_col'] ?? 'L');
    $descIdx = colLetterToIndex($mapping['desc_col'] ?? '');

    $linkCell = $row[$linkIdx] ?? null;
    $displayText = trim($linkCell['text'] ?? '');
    $url = trim($linkCell['hyperlink'] ?? '');

    if ($displayText === '' && $url === '') return null;

    // タイトル分割: 最初の空白で「カテゴリ + タイトル」
    $category = '';
    $title    = $displayText;
    if ($displayText !== '' && preg_match('/^(\S+)\s+(.+)$/u', $displayText, $m)) {
        $category = $m[1];
        $title    = trim($m[2]);
    }
    if ($title === '') $title = $displayText;
    if ($title === '') $title = $url; // URLしか無い場合の保険

    $description = '';
    if ($descIdx >= 0) {
        $descCell = $row[$descIdx] ?? null;
        $description = trim($descCell['text'] ?? '');
    }

    return [
        'category'    => $category,
        'title'       => $title,
        'url'         => $url,
        'description' => $description,
    ];
}

/** Sheets API でセルを取得 */
function fetchRichCells($spreadsheetId, $sheetName, $maxRow) {
    $client = new GoogleSheetsClient($spreadsheetId);
    $range = ($sheetName ?: '') ;
    if ($range === '') {
        $sheets = $client->getSheets();
        if (!empty($sheets[0]['properties']['title'])) {
            $range = $sheets[0]['properties']['title'];
        }
    }
    $range = $range . '!A1:Z' . max(2, (int)$maxRow);
    return $client->getRichCells($range);
}

/** 公開範囲のラジオ値 → DB 用配列に変換（manuals-api.php と同じロジック） */
function normalizeVisibility($value) {
    if ($value === 'all' || $value === '' || $value === null) return [];
    if ($value === 'product') return ['product', 'admin'];
    if ($value === 'admin')   return ['admin'];
    return [];
}

// ============================================================
// アクション
// ============================================================
$input = getJsonInput();
if (!$action) $action = $input['action'] ?? '';

// ---- 保存済み設定の読み込み ----
if ($method === 'GET' && $action === 'load-config') {
    if (file_exists($CONFIG_FILE)) {
        $cfg = json_decode(file_get_contents($CONFIG_FILE), true);
        successResponse(['config' => $cfg]);
    }
    successResponse(['config' => null]);
}

// 以降は POST 必須
if ($method !== 'POST') errorResponse('POST メソッドを使用してください', 405);
verifyCsrfToken();

// ---- プレビュー: スプシを読んで最初の N 行を返す ----
if ($action === 'preview') {
    $url = trim($input['sheet_url'] ?? '');
    $sheetId = extractSheetId($url);
    if (!$sheetId) errorResponse('正しい Google スプレッドシートの URL を入力してください', 400);

    $mapping = [
        'link_col'  => strtoupper(trim($input['link_col'] ?? 'L')),
        'desc_col'  => strtoupper(trim($input['desc_col'] ?? '')),
    ];
    $sheetName    = trim($input['sheet_name'] ?? '');
    $startRow     = max(1, (int)($input['start_row'] ?? 2)); // 1-indexed (デフォルト: ヘッダー1行スキップして2行目から)
    $previewRows  = 5;

    try {
        $rows = fetchRichCells($sheetId, $sheetName, max(50, $startRow + 50));
    } catch (Exception $e) {
        errorResponse('スプレッドシート読み込み失敗: ' . $e->getMessage(), 400);
    }

    $startIdx = $startRow - 1; // 1-indexed → 0-indexed
    $samples = [];
    $skipped = 0;
    $totalCandidate = 0;
    for ($i = $startIdx; $i < count($rows); $i++) {
        $m = buildManualFromRow($rows[$i], $mapping);
        if (!$m) { $skipped++; continue; }
        $totalCandidate++;
        if (count($samples) < $previewRows) $samples[] = $m;
    }

    successResponse([
        'samples'         => $samples,
        'mapping'         => $mapping,
        'sheet_id'        => $sheetId,
        'sheet_name'      => $sheetName,
        'start_row'       => $startRow,
        'total_candidate' => $totalCandidate,
        'skipped'         => $skipped,
        'total_rows'      => count($rows),
    ]);
}

// ---- 取り込み実行 ----
if ($action === 'import' || $action === 'resync') {
    if ($action === 'resync') {
        if (!file_exists($CONFIG_FILE)) errorResponse('保存された設定がありません', 400);
        $saved = json_decode(file_get_contents($CONFIG_FILE), true) ?: [];
        $input = array_merge($saved, $input); // 個別パラメータが優先
    }

    $url = trim($input['sheet_url'] ?? '');
    $sheetId = extractSheetId($url);
    if (!$sheetId) errorResponse('正しい Google スプレッドシートの URL を入力してください', 400);

    $mapping = [
        'link_col'  => strtoupper(trim($input['link_col'] ?? 'L')),
        'desc_col'  => strtoupper(trim($input['desc_col'] ?? '')),
    ];
    $sheetName   = trim($input['sheet_name'] ?? '');
    $startRow    = max(1, (int)($input['start_row'] ?? 2));
    $visibility  = normalizeVisibility($input['visibility'] ?? 'all');

    try {
        $rows = fetchRichCells($sheetId, $sheetName, 2000);
    } catch (Exception $e) {
        errorResponse('スプレッドシート読み込み失敗: ' . $e->getMessage(), 400);
    }

    $startIdx = $startRow - 1;

    $data = getData();
    $manuals = $data['manuals'] ?? [];

    // 既存の URL → index マップ（削除済み除外）
    $urlIndex = [];
    foreach ($manuals as $i => $m) {
        if (!empty($m['deleted_at'])) continue;
        $u = $m['url'] ?? '';
        if ($u !== '') $urlIndex[$u] = $i;
    }

    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errors  = [];
    $now = date('Y-m-d H:i:s');
    $userEmail = $_SESSION['user_email'] ?? '';

    for ($i = $startIdx; $i < count($rows); $i++) {
        $built = buildManualFromRow($rows[$i], $mapping);
        if (!$built) { $skipped++; continue; }
        if ($built['url'] === '') {
            $skipped++;
            $errors[] = '行 ' . ($i + 1) . ': URL が見つからないためスキップ (タイトル: ' . $built['title'] . ')';
            continue;
        }
        if (!preg_match('#^https?://#i', $built['url'])) {
            $skipped++;
            $errors[] = '行 ' . ($i + 1) . ': URL が http(s) で始まっていません (' . $built['url'] . ')';
            continue;
        }

        $existingIdx = $urlIndex[$built['url']] ?? null;
        if ($existingIdx !== null) {
            $manuals[$existingIdx]['title']       = $built['title'];
            $manuals[$existingIdx]['category']    = $built['category'];
            $manuals[$existingIdx]['description'] = $built['description'];
            $manuals[$existingIdx]['visible_to']  = $visibility;
            $manuals[$existingIdx]['updated_at']  = $now;
            $updated++;
        } else {
            $newManual = [
                'id'              => 'man_' . uniqid('', true),
                'title'           => $built['title'],
                'url'             => $built['url'],
                'description'     => $built['description'],
                'search_keywords' => '',
                'category'        => $built['category'],
                'tags'            => [],
                'visible_to'      => $visibility,
                'created_by'      => $userEmail,
                'created_at'      => $now,
                'updated_at'      => $now,
                'deleted_at'      => null,
                'deleted_by'      => null,
            ];
            $manuals[] = $newManual;
            $urlIndex[$built['url']] = count($manuals) - 1;
            $created++;
        }
    }

    $data['manuals'] = $manuals;
    saveData($data, ['manuals']);

    // 取り込み設定を自動保存（再同期用）
    file_put_contents($CONFIG_FILE, json_encode([
        'sheet_url'   => $url,
        'sheet_name'  => $sheetName,
        'link_col'    => $mapping['link_col'],
        'desc_col'    => $mapping['desc_col'],
        'start_row'   => $startRow,
        'visibility'  => $input['visibility'] ?? 'all',
        'last_synced_at' => $now,
        'last_synced_by' => $userEmail,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    if (function_exists('logInfo')) {
        logInfo('manuals_import', ['created' => $created, 'updated' => $updated, 'skipped' => $skipped]);
    }

    successResponse([
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors'  => $errors,
    ], "取り込み完了: 新規 {$created}件 / 更新 {$updated}件 / スキップ {$skipped}件");
}

// ---- 設定保存（取り込み実行前に設定だけ保存したい場合） ----
if ($action === 'save-config') {
    $payload = [
        'sheet_url'   => trim($input['sheet_url'] ?? ''),
        'sheet_name'  => trim($input['sheet_name'] ?? ''),
        'link_col'    => strtoupper(trim($input['link_col'] ?? 'L')),
        'desc_col'    => strtoupper(trim($input['desc_col'] ?? '')),
        'start_row'   => max(1, (int)($input['start_row'] ?? 2)),
        'visibility'  => $input['visibility'] ?? 'all',
        'updated_at'  => date('Y-m-d H:i:s'),
    ];
    file_put_contents($CONFIG_FILE, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    successResponse(['config' => $payload], '設定を保存しました');
}

errorResponse('不明なアクションです', 400);
