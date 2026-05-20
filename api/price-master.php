<?php
/**
 * 価格表マスタ API（管理部専用）
 *
 *   GET  ?action=get                    全シートの normalized データを返す
 *   POST action=save_variant            バリアント1件を更新 (sheet_title, row_key, attributes, prices)
 *   POST action=add_variant             新規バリアント追加
 *   POST action=delete_variant          バリアント削除
 *
 * 編集対象: data/product-prices.json の sheets[].normalized.rows
 * 表示側 (sales-tools の pricing タブ) はこの JSON を読込（読取専用）。
 *
 * 権限: admin のみ
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false, // POST 内で個別検証
    'rateLimit'   => false,
]);

const PRICE_JSON_PATH = __DIR__ . '/../data/product-prices.json';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if (!isAdmin()) {
    errorResponse('admin のみアクセス可能です', 403);
}

// ----- GET: 全データ取得 -----
if ($method === 'GET' && $action === 'get') {
    $data = loadPriceData();
    successResponse(['data' => $data]);
}

if ($method !== 'POST') {
    errorResponse('不明なリクエストです', 405);
}

// xlsx アップロード (multipart) は JSON 解析せずに処理
if ($action === 'upload_xlsx') {
    verifyCsrfToken();
    handleUploadXlsx();
}

verifyCsrfToken();
$input  = getJsonInput();
$action = $input['action'] ?? $action;

if ($action === 'save_variant')   handleSaveVariant($input);
if ($action === 'add_variant')    handleAddVariant($input);
if ($action === 'delete_variant') handleDeleteVariant($input);

errorResponse('不明なアクションです: ' . $action, 400);

// =============================================================
function loadPriceData(): array {
    if (!file_exists(PRICE_JSON_PATH)) return ['sheets' => []];
    $raw = file_get_contents(PRICE_JSON_PATH);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['sheets' => []];
}

function savePriceData(array $data): void {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) errorResponse('JSON エンコード失敗', 500);
    if (file_put_contents(PRICE_JSON_PATH, $json, LOCK_EX) === false) {
        errorResponse('JSON ファイルの書き込みに失敗', 500);
    }
}

function findSheetIndex(array $data, string $title): int {
    foreach ($data['sheets'] ?? [] as $i => $s) {
        if (($s['title'] ?? '') === $title) return $i;
    }
    return -1;
}

function findRowIndex(array $sheet, string $key): int {
    $rows = $sheet['normalized']['rows'] ?? [];
    foreach ($rows as $i => $r) {
        if (($r['key'] ?? '') === $key) return $i;
    }
    return -1;
}

// =============================================================

function handleSaveVariant(array $input): void {
    $sheetTitle = trim($input['sheet_title'] ?? '');
    $rowKey     = trim($input['row_key'] ?? '');
    if ($sheetTitle === '' || $rowKey === '') errorResponse('sheet_title と row_key 必須', 400);

    $data = loadPriceData();
    $sIdx = findSheetIndex($data, $sheetTitle);
    if ($sIdx < 0) errorResponse("シートが見つかりません: {$sheetTitle}", 404);

    $rIdx = findRowIndex($data['sheets'][$sIdx], $rowKey);
    if ($rIdx < 0) errorResponse("バリアントが見つかりません: {$rowKey}", 404);

    // 値を更新（クライアントから送られた display_name / attributes / prices を信頼）
    if (isset($input['display_name'])) {
        $data['sheets'][$sIdx]['normalized']['rows'][$rIdx]['display_name'] = (string)$input['display_name'];
    }
    if (isset($input['attributes']) && is_array($input['attributes'])) {
        $data['sheets'][$sIdx]['normalized']['rows'][$rIdx]['attributes'] = $input['attributes'];
    }
    if (isset($input['prices']) && is_array($input['prices'])) {
        // 各 price の amount は int に正規化
        $prices = [];
        foreach ($input['prices'] as $p) {
            if (!is_array($p)) continue;
            $amount = $p['amount'] ?? null;
            if ($amount === null || $amount === '') continue;
            $prices[] = [
                'group'  => (string)($p['group'] ?? ''),
                'label'  => (string)($p['label'] ?? ''),
                'amount' => (int)$amount,
            ];
        }
        $data['sheets'][$sIdx]['normalized']['rows'][$rIdx]['prices'] = $prices;
    }

    savePriceData($data);
    if (function_exists('auditUpdate')) {
        auditUpdate('price_master', $sheetTitle . '/' . $rowKey, 'バリアント更新');
    }
    successResponse(['variant' => $data['sheets'][$sIdx]['normalized']['rows'][$rIdx]], '更新しました');
}

function handleAddVariant(array $input): void {
    $sheetTitle = trim($input['sheet_title'] ?? '');
    if ($sheetTitle === '') errorResponse('sheet_title 必須', 400);
    $displayName = trim($input['display_name'] ?? '新規バリアント');
    $attributes  = is_array($input['attributes'] ?? null) ? $input['attributes'] : [];
    $prices      = is_array($input['prices']     ?? null) ? $input['prices']     : [];

    $data = loadPriceData();
    $sIdx = findSheetIndex($data, $sheetTitle);
    if ($sIdx < 0) errorResponse("シートが見つかりません: {$sheetTitle}", 404);

    $newKey = 'new_' . bin2hex(random_bytes(6));
    $newRow = [
        'key'          => $newKey,
        'display_name' => $displayName,
        'attributes'   => $attributes,
        'prices'       => array_map(function($p){
            return [
                'group'  => (string)($p['group'] ?? ''),
                'label'  => (string)($p['label'] ?? ''),
                'amount' => (int)($p['amount'] ?? 0),
            ];
        }, $prices),
    ];

    if (!isset($data['sheets'][$sIdx]['normalized']['rows'])) {
        $data['sheets'][$sIdx]['normalized']['rows'] = [];
    }
    $data['sheets'][$sIdx]['normalized']['rows'][] = $newRow;

    savePriceData($data);
    if (function_exists('auditCreate')) {
        auditCreate('price_master', $newKey, $displayName);
    }
    successResponse(['variant' => $newRow], '追加しました');
}

/**
 * xlsx アップロード → 全シートを解析して product-prices.json を上書き
 *
 * 期待するシート構造（各シート1行目をヘッダとして解析）:
 *   Pattern A (18列): 製品シリーズ|型番|サイズ|寸法|平米数|S層_販売|S層_短期月額|...
 *   Pattern B (7列):  製品名|型番|仕様1|仕様2|仕様3|販売価格|備考
 *   Pattern C (4列):  項目|単価|単位|備考
 *   無視: 凡例・仕様 / 顧客定義 / 設置調整費 (要確認の特殊シート)
 */
function handleUploadXlsx(): void {
    if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? -1) !== UPLOAD_ERR_OK) {
        errorResponse('xlsx ファイルがアップロードされていません', 400);
    }
    $file = $_FILES['file'];
    if (!preg_match('/\.xlsx$/i', $file['name'])) errorResponse('xlsx ファイルのみ対応', 400);
    if ($file['size'] > 20 * 1024 * 1024) errorResponse('ファイルが大きすぎます (20MB まで)', 400);

    if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    try {
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
    } catch (Exception $e) {
        errorResponse('xlsx 読込失敗: ' . $e->getMessage(), 500);
    }

    $skipSheets = ['凡例・仕様', '顧客定義'];
    $newSheets = [];
    $summary = [];
    foreach ($book->getAllSheets() as $sheet) {
        $title = $sheet->getTitle();
        if (in_array($title, $skipSheets, true)) continue;
        $highest = $sheet->getHighestRow();
        if ($highest < 2) continue; // データなし

        // ヘッダ行を読んでパターン判定
        $pattern = detectPattern($sheet);
        if ($pattern === null) {
            $summary[] = ['title' => $title, 'rows' => 0, 'pattern' => 'unknown(skip)'];
            continue;
        }

        $parsedRows = [];
        if ($pattern === 'A') $parsedRows = parsePatternARows($sheet);
        elseif ($pattern === 'B') $parsedRows = parsePatternBRows($sheet);
        elseif ($pattern === 'C') $parsedRows = parsePatternCRows($sheet);

        if (count($parsedRows) === 0) {
            $summary[] = ['title' => $title, 'rows' => 0, 'pattern' => $pattern];
            continue;
        }

        $newSheets[] = [
            'title'      => $title,
            'sheet_id'   => crc32($title),
            'values'     => [], // raw values は不要（normalized のみ使用）
            'normalized' => [
                'type'       => $pattern === 'A' ? 'rank-pricing' : 'flat-list',
                'rank_order' => $pattern === 'A' ? ['S', 'A', 'B'] : [],
                'rows'       => $parsedRows,
            ],
        ];
        $summary[] = ['title' => $title, 'rows' => count($parsedRows), 'pattern' => $pattern];
    }

    // 既存データバックアップ
    $existing = loadPriceData();
    if (!empty($existing['sheets'])) {
        $backupPath = PRICE_JSON_PATH . '.backup.' . date('Ymd_His');
        @file_put_contents($backupPath, json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    // 新データ保存
    $newData = [
        'spreadsheet_id'  => $existing['spreadsheet_id']  ?? '',
        'spreadsheet_url' => $existing['spreadsheet_url'] ?? '',
        'synced_at'       => date('Y-m-d H:i:s'),
        'synced_by'       => $_SESSION['user_email'] ?? 'xlsx_upload',
        'sheets'          => $newSheets,
    ];
    savePriceData($newData);
    if (function_exists('auditUpdate')) {
        auditUpdate('price_master', 'xlsx_upload', 'xlsx 一括取込 ' . count($newSheets) . 'シート');
    }

    successResponse([
        'sheet_count' => count($newSheets),
        'summary'     => $summary,
    ], $file['name'] . ' を取込みました (' . count($newSheets) . 'シート)');
}

// パターン判定: 1行目のヘッダから A/B/C を判定
function detectPattern($sheet): ?string {
    $headers = [];
    foreach (range('A', 'Z') as $col) {
        $v = trim((string)$sheet->getCell($col . '1')->getCalculatedValue());
        if ($v === '') break;
        $headers[] = $v;
    }
    $joined = implode('|', $headers);
    if (strpos($joined, 'S層_販売') !== false || strpos($joined, 'S層_短期月額') !== false) return 'A';
    if (strpos($joined, '販売価格') !== false && strpos($joined, '製品名') !== false) return 'B';
    if (strpos($joined, '単価') !== false && strpos($joined, '項目') !== false) return 'C';
    return null;
}

// Pattern A 行を normalized rows に変換
function parsePatternARows($sheet): array {
    $rows = [];
    $highest = $sheet->getHighestRow();
    for ($r = 2; $r <= $highest; $r++) {
        $series = pmval($sheet, 'A', $r);
        $model  = pmval($sheet, 'B', $r);
        $size   = pmval($sheet, 'C', $r);
        $dim    = pmval($sheet, 'D', $r);
        $area   = pmval($sheet, 'E', $r);
        $bikou  = pmval($sheet, 'R', $r);
        if ($series === '' && $model === '') continue;

        // attributes
        $attrs = [];
        if ($series !== '') $attrs[] = ['label' => '製品シリーズ', 'value' => $series];
        if ($model !== '' && $model !== $series) $attrs[] = ['label' => '型番', 'value' => $model];
        if ($size !== '')   $attrs[] = ['label' => 'インチ数', 'value' => $size];
        if ($dim !== '')    $attrs[] = ['label' => '画面サイズ', 'value' => $dim];
        if ($area !== '')   $attrs[] = ['label' => '平米数', 'value' => $area];

        // prices: xlsx ラベル → システム表示用ラベル (販売価格/①月額/②月額/③月額)
        $prices = [];
        $cols = [
            ['S', '販売価格',  'F'],
            ['S', '①月額',    'G'],
            ['S', '②月額',    'H'],
            ['S', '③月額',    'I'],
            ['A', '販売価格',  'J'],
            ['A', '①月額',    'K'],
            ['A', '②月額',    'L'],
            ['A', '③月額',    'M'],
            ['B', '販売価格',  'N'],
            ['B', '①月額',    'O'],
            ['B', '②月額',    'P'],
            ['B', '③月額',    'Q'],
        ];
        foreach ($cols as [$tier, $label, $col]) {
            $amount = pmnumv($sheet, $col, $r);
            if ($amount === null || $amount <= 0) continue;
            $prices[] = ['group' => $tier, 'label' => $label, 'amount' => $amount];
        }

        // display_name 構築: "型番 (サイズ / 寸法)" 形式
        $idParts = [];
        if ($model !== '') $idParts[] = $model;
        elseif ($series !== '') $idParts[] = $series;
        $subParts = [];
        if ($size !== '') $subParts[] = $size;
        if ($dim  !== '') $subParts[] = $dim;
        $display = implode('', $idParts) . (count($subParts) > 0 ? ' (' . implode(' / ', $subParts) . ')' : '');
        if ($display === '') $display = '行 ' . $r;

        $rows[] = [
            'key'          => 'r' . $r . '_' . substr(md5($display), 0, 8),
            'display_name' => $display,
            'attributes'   => $attrs,
            'prices'       => $prices,
            'notes'        => $bikou !== '' ? $bikou : null,
        ];
    }
    return $rows;
}

// Pattern B 行を normalized rows に変換
function parsePatternBRows($sheet): array {
    $rows = [];
    $highest = $sheet->getHighestRow();
    for ($r = 2; $r <= $highest; $r++) {
        $name  = pmval($sheet, 'A', $r);
        $model = pmval($sheet, 'B', $r);
        $s1    = pmval($sheet, 'C', $r);
        $s2    = pmval($sheet, 'D', $r);
        $s3    = pmval($sheet, 'E', $r);
        $price = pmnumv($sheet, 'F', $r);
        $bikou = pmval($sheet, 'G', $r);
        if ($name === '' && $model === '') continue;

        $attrs = [];
        if ($name !== '')  $attrs[] = ['label' => '製品名', 'value' => $name];
        if ($model !== '') $attrs[] = ['label' => '型番',   'value' => $model];
        if ($s1 !== '')    $attrs[] = ['label' => '仕様1',  'value' => $s1];
        if ($s2 !== '')    $attrs[] = ['label' => '仕様2',  'value' => $s2];
        if ($s3 !== '')    $attrs[] = ['label' => '仕様3',  'value' => $s3];

        $prices = [];
        if ($price !== null && $price > 0) {
            $prices[] = ['group' => '', 'label' => '販売価格', 'amount' => $price];
        }

        $display = $model !== '' ? $model : ($name !== '' ? $name : '行 ' . $r);
        $rows[] = [
            'key'          => 'r' . $r . '_' . substr(md5($display), 0, 8),
            'display_name' => $display,
            'attributes'   => $attrs,
            'prices'       => $prices,
            'notes'        => $bikou !== '' ? $bikou : null,
        ];
    }
    return $rows;
}

// Pattern C 行を normalized rows に変換
function parsePatternCRows($sheet): array {
    $rows = [];
    $highest = $sheet->getHighestRow();
    for ($r = 2; $r <= $highest; $r++) {
        $item  = pmval($sheet, 'A', $r);
        $price = pmnumv($sheet, 'B', $r);
        $unit  = pmval($sheet, 'C', $r);
        $bikou = pmval($sheet, 'D', $r);
        if ($item === '' && $price === null) continue;

        $attrs = [];
        if ($unit !== '') $attrs[] = ['label' => '単位', 'value' => $unit];
        $prices = [];
        if ($price !== null && $price > 0) {
            $prices[] = ['group' => '', 'label' => '単価', 'amount' => $price];
        }

        $rows[] = [
            'key'          => 'r' . $r . '_' . substr(md5($item), 0, 8),
            'display_name' => $item ?: ('行 ' . $r),
            'attributes'   => $attrs,
            'prices'       => $prices,
            'notes'        => $bikou !== '' ? $bikou : null,
        ];
    }
    return $rows;
}

// セル値ユーティリティ
function pmval($sheet, $col, $r): string {
    $v = $sheet->getCell($col . $r)->getCalculatedValue();
    if ($v === null) return '';
    return trim((string)$v);
}
function pmnumv($sheet, $col, $r): ?int {
    $v = $sheet->getCell($col . $r)->getCalculatedValue();
    if ($v === null || $v === '') return null;
    if (is_string($v) && strpos($v, '#') === 0) return null;
    if (!is_numeric($v)) return null;
    return (int)round((float)$v);
}

function handleDeleteVariant(array $input): void {
    $sheetTitle = trim($input['sheet_title'] ?? '');
    $rowKey     = trim($input['row_key'] ?? '');
    if ($sheetTitle === '' || $rowKey === '') errorResponse('sheet_title と row_key 必須', 400);

    $data = loadPriceData();
    $sIdx = findSheetIndex($data, $sheetTitle);
    if ($sIdx < 0) errorResponse("シートが見つかりません: {$sheetTitle}", 404);

    $rIdx = findRowIndex($data['sheets'][$sIdx], $rowKey);
    if ($rIdx < 0) errorResponse("バリアントが見つかりません: {$rowKey}", 404);

    $deleted = $data['sheets'][$sIdx]['normalized']['rows'][$rIdx];
    array_splice($data['sheets'][$sIdx]['normalized']['rows'], $rIdx, 1);

    savePriceData($data);
    if (function_exists('auditDelete')) {
        auditDelete('price_master', $rowKey, $deleted['display_name'] ?? '');
    }
    successResponse([], '削除しました');
}
