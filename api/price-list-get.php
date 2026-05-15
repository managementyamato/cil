<?php
/**
 * 価格表データ取得 API
 *
 * 同期済みの data/product-prices.json をそのまま返す。
 * 認証済みの sales 以上が利用可能。
 *
 * GET ?title=xxx  指定シートのみ返す（省略時は全シートメタ＋データ）
 * GET ?meta=1     メタのみ（シート一覧と最終同期）を返す
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/sales-master.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => false,
    'rateLimit'      => false,
    'allowedMethods' => ['GET'],
]);

if (!hasPermission('sales')) errorResponse('権限がありません', 403);

$data = loadProductPrices(); // functions/sales-master.php
if ($data === null) {
    successResponse([
        'available' => false,
        'message'   => 'まだ同期されていません',
    ]);
}

$title = trim($_GET['title'] ?? '');
$meta  = !empty($_GET['meta']);

if ($meta) {
    successResponse([
        'available'      => true,
        'spreadsheet_id' => $data['spreadsheet_id'] ?? '',
        'spreadsheet_url'=> $data['spreadsheet_url'] ?? '',
        'synced_at'      => $data['synced_at'] ?? null,
        'synced_by'      => $data['synced_by'] ?? null,
        'sheets'         => array_map(function($s){
            return [
                'title'    => $s['title']    ?? '',
                'sheet_id' => $s['sheet_id'] ?? 0,
                'rows'     => is_array($s['values'] ?? null) ? count($s['values']) : 0,
            ];
        }, $data['sheets'] ?? []),
    ]);
}

if ($title !== '') {
    foreach (($data['sheets'] ?? []) as $s) {
        if (($s['title'] ?? '') === $title) {
            successResponse([
                'available' => true,
                'sheet'     => $s,
                'synced_at' => $data['synced_at'] ?? null,
            ]);
        }
    }
    errorResponse('該当する価格表が見つかりません: ' . $title, 404);
}

// 全件返却
successResponse([
    'available'      => true,
    'spreadsheet_id' => $data['spreadsheet_id'] ?? '',
    'spreadsheet_url'=> $data['spreadsheet_url'] ?? '',
    'synced_at'      => $data['synced_at'] ?? null,
    'synced_by'      => $data['synced_by'] ?? null,
    'sheets'         => $data['sheets'] ?? [],
]);
