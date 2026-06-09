<?php
/**
 * スプレッドシート閲覧ページ 同期 API
 *
 * 指定の Google スプレッドシートの対象タブ（複数）を取得し、
 * data/sheet-viewer-cache.json に保存する。
 *
 * GET  ?action=info  最終同期状況（同期時刻・シート別行数）を取得（admin のみ）
 * POST action=sync   対象タブを同期しキャッシュを更新（admin のみ）
 *
 * 表示対象タブを増減するには SHEET_VIEWER_GIDS を編集する。
 *
 * 保存形式（data/sheet-viewer-cache.json）:
 * {
 *   "spreadsheet_id":  "...",
 *   "spreadsheet_url": "...",
 *   "synced_at":       "YYYY-MM-DD HH:MM:SS",
 *   "synced_by":       "user@example.com",
 *   "sheets": [
 *     { "gid": 123, "title": "...", "values": [["A1","B1"],["A2","B2"]] },
 *     { "gid": 456, "title": "...", "values": [], "error": "..." }
 *   ]
 * }
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/google-sheets.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => false, // POST 内で個別検証
    'rateLimit'      => false,
    'allowedMethods' => ['GET', 'POST'],
]);

const SHEET_VIEWER_SPREADSHEET_ID = '16DgKDdAxpPD64jZhM-Vo7CcJOIMXh5uFhnVTCEvuHBQ';
const SHEET_VIEWER_CACHE_PATH     = __DIR__ . '/../data/sheet-viewer-cache.json';

// 表示対象タブの gid（タブ表示順）。シートを増減する場合はここを編集する。
const SHEET_VIEWER_GIDS = [
    626684044, // 経営指標（管理部編集）
    277501221, // 変動/販管費-支払月（管理部編集）
    700001372, // 資金繰表（管理部編集）
];

if (!isAdmin()) errorResponse('admin のみ利用可能です', 403);

$spreadsheetUrl = 'https://docs.google.com/spreadsheets/d/' . SHEET_VIEWER_SPREADSHEET_ID . '/edit';
$method = $_SERVER['REQUEST_METHOD'];

// ------ GET: 同期状況 ------
if ($method === 'GET') {
    $info = [
        'spreadsheet_id'  => SHEET_VIEWER_SPREADSHEET_ID,
        'spreadsheet_url' => $spreadsheetUrl,
        'synced'          => false,
        'synced_at'       => null,
        'synced_by'       => null,
        'sheets'          => [],
    ];
    if (file_exists(SHEET_VIEWER_CACHE_PATH)) {
        $raw = json_decode((string)file_get_contents(SHEET_VIEWER_CACHE_PATH), true);
        if (is_array($raw)) {
            $info['synced']    = true;
            $info['synced_at'] = $raw['synced_at'] ?? null;
            $info['synced_by'] = $raw['synced_by'] ?? null;
            $info['sheets']    = array_map(function ($s) {
                return [
                    'gid'       => $s['gid']   ?? 0,
                    'title'     => $s['title'] ?? '',
                    'row_count' => is_array($s['values'] ?? null) ? count($s['values']) : 0,
                ];
            }, $raw['sheets'] ?? []);
        }
    }
    successResponse($info);
}

// ------ POST: 同期実行 ------
if ($method === 'POST') {
    verifyCsrfToken();
    $input  = getJsonInput();
    $action = $input['action'] ?? '';
    if ($action !== 'sync') errorResponse('不明なアクションです', 400);

    try {
        $client = new GoogleSheetsClient(SHEET_VIEWER_SPREADSHEET_ID);
        $sheets = $client->getSheets();

        // gid（sheetId）→ タイトルの対応表
        $titleByGid = [];
        foreach ($sheets as $s) {
            $gid = (int)($s['properties']['sheetId'] ?? -1);
            $titleByGid[$gid] = $s['properties']['title'] ?? '';
        }

        $resultSheets = [];
        $errors       = [];
        foreach (SHEET_VIEWER_GIDS as $gid) {
            $title = $titleByGid[$gid] ?? null;
            if ($title === null || $title === '') {
                $msg = '対象タブ (gid=' . $gid . ') が見つかりません';
                $errors[] = $msg;
                $resultSheets[] = ['gid' => $gid, 'title' => '(gid ' . $gid . ')', 'values' => [], 'error' => $msg];
                continue;
            }
            try {
                $values = sheet_viewer_trim_empty($client->getValues($title));
                $resultSheets[] = ['gid' => $gid, 'title' => $title, 'values' => $values];
            } catch (Exception $e) {
                $errors[] = '[' . $title . '] ' . $e->getMessage();
                $resultSheets[] = ['gid' => $gid, 'title' => $title, 'values' => [], 'error' => $e->getMessage()];
            }
        }

        $result = [
            'spreadsheet_id'  => SHEET_VIEWER_SPREADSHEET_ID,
            'spreadsheet_url' => $spreadsheetUrl,
            'synced_at'       => date('Y-m-d H:i:s'),
            'synced_by'       => $_SESSION['user_email'] ?? '',
            'sheets'          => $resultSheets,
        ];

        $dir = dirname(SHEET_VIEWER_CACHE_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (file_put_contents(SHEET_VIEWER_CACHE_PATH, $json, LOCK_EX) === false) {
            errorResponse('キャッシュファイルの書き込みに失敗しました', 500);
        }

        if (function_exists('logInfo')) {
            logInfo('sheet_viewer_synced', ['sheets' => count($resultSheets), 'errors' => count($errors)]);
        }

        successResponse([
            'sheet_count' => count($resultSheets),
            'synced_at'   => $result['synced_at'],
            'errors'      => $errors,
        ], 'スプレッドシートを同期しました');
    } catch (Exception $e) {
        error_log('[sheet-viewer-sync] failed: ' . $e->getMessage());
        errorResponse('同期失敗: ' . $e->getMessage(), 502);
    }
}

errorResponse('無効なリクエストです', 405);

// ------ ヘルパー ------
/** 末尾の全列空行を除去 */
function sheet_viewer_trim_empty(array $values): array {
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
