<?php
/**
 * 価格表シート同期 API
 *
 * Google スプレッドシート（全シート）を取得し、
 * data/product-prices.json に保存する。
 *
 * GET  ?action=info   現状の同期状況（最終同期時刻・シート数 等）を取得（sales以上）
 * POST action=sync    全シート同期（admin のみ）
 *
 * 保存形式（data/product-prices.json）:
 * {
 *   "spreadsheet_id": "...",
 *   "synced_at":      "YYYY-MM-DD HH:MM:SS",
 *   "synced_by":      "user@example.com",
 *   "sheets": [
 *     { "title": "...", "sheet_id": 12345, "values": [["A1","B1"],["A2","B2"]] },
 *     ...
 *   ]
 * }
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/google-sheets.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false, // POST内で個別検証
    'rateLimit'   => false,
]);

const PRICE_SHEET_ID    = '1cJGD2QiaNhOeZsLuLzhbTL83vTcgnMftiv4qTIt_UYc';
const PRICE_JSON_PATH   = __DIR__ . '/../data/product-prices.json';
const PRICE_FETCH_RANGE = 'A1:Z200'; // 1シートあたり最大セル範囲

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ------ GET: 同期状況 ------
if ($method === 'GET' && $action === 'info') {
    $info = [
        'spreadsheet_id'  => PRICE_SHEET_ID,
        'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/' . PRICE_SHEET_ID . '/edit',
        'synced_at'       => null,
        'synced_by'       => null,
        'sheet_count'     => 0,
        'sheet_titles'    => [],
    ];
    if (file_exists(PRICE_JSON_PATH)) {
        $raw = json_decode((string)file_get_contents(PRICE_JSON_PATH), true);
        if (is_array($raw)) {
            $info['synced_at']    = $raw['synced_at'] ?? null;
            $info['synced_by']    = $raw['synced_by'] ?? null;
            $info['sheet_count']  = is_array($raw['sheets'] ?? null) ? count($raw['sheets']) : 0;
            $info['sheet_titles'] = array_map(fn($s) => $s['title'] ?? '', $raw['sheets'] ?? []);
        }
    }
    successResponse($info);
}

// ------ POST: 同期実行 ------
if ($method === 'POST') {
    verifyCsrfToken();
    $input  = getJsonInput();
    $action = $input['action'] ?? $action;

    if ($action !== 'sync') errorResponse('不明なアクションです', 400);
    if (!isAdmin())          errorResponse('admin のみ実行可能です', 403);

    try {
        $client = new GoogleSheetsClient(PRICE_SHEET_ID);
        $sheets = $client->getSheets();

        $result = [
            'spreadsheet_id'  => PRICE_SHEET_ID,
            'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/' . PRICE_SHEET_ID . '/edit',
            'synced_at'       => date('Y-m-d H:i:s'),
            'synced_by'       => $_SESSION['user_email'] ?? '',
            'sheets'          => [],
        ];

        $errors = [];
        foreach ($sheets as $s) {
            $title   = $s['properties']['title']   ?? '';
            $sheetId = $s['properties']['sheetId'] ?? 0;
            if ($title === '') continue;

            try {
                $values = $client->getValues($title . '!' . PRICE_FETCH_RANGE);
                // 全行・全列空のテール行を削除
                $values = price_sync_trim_empty($values);
                $result['sheets'][] = [
                    'title'    => $title,
                    'sheet_id' => $sheetId,
                    'values'   => $values,
                ];
            } catch (Exception $e) {
                $errors[] = "[{$title}] " . $e->getMessage();
                $result['sheets'][] = [
                    'title'    => $title,
                    'sheet_id' => $sheetId,
                    'values'   => [],
                    'error'    => $e->getMessage(),
                ];
            }
        }

        // 保存先ディレクトリ作成
        $dir = dirname(PRICE_JSON_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (file_put_contents(PRICE_JSON_PATH, $json, LOCK_EX) === false) {
            errorResponse('JSONファイルの書き込みに失敗しました', 500);
        }

        if (function_exists('auditUpdate')) {
            auditUpdate('product_prices', 'sheet', '価格表同期: ' . count($result['sheets']) . '件');
        }
        if (function_exists('logInfo')) {
            logInfo('price_list_synced', ['count' => count($result['sheets']), 'errors' => count($errors)]);
        }

        successResponse([
            'sheet_count' => count($result['sheets']),
            'synced_at'   => $result['synced_at'],
            'errors'      => $errors,
        ], '価格表を同期しました');
    } catch (Exception $e) {
        error_log('[price-list-sync] failed: ' . $e->getMessage());
        errorResponse('同期失敗: ' . $e->getMessage(), 502);
    }
}

errorResponse('無効なリクエストです', 405);

// ------ ヘルパー ------
/** 末尾の空行を除去 */
function price_sync_trim_empty(array $values): array {
    while (!empty($values)) {
        $last = end($values);
        $isEmpty = true;
        if (is_array($last)) {
            foreach ($last as $cell) {
                if (trim((string)$cell) !== '') { $isEmpty = false; break; }
            }
        }
        if (!$isEmpty) break;
        array_pop($values);
    }
    return $values;
}
