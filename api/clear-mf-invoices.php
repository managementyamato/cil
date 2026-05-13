<?php
/**
 * MF請求書データをクリアするAPI
 * 管理者専用
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'allowedMethods' => ['POST'],
]);

if (!isAdmin()) {
    errorResponse('管理者権限が必要です', 403);
}

try {
    $data = getData();

    // クリア前の件数を記録
    $beforeCount = count($data['mf_invoices'] ?? []);

    // mf_invoicesをクリア
    $data['mf_invoices'] = [];
    $data['mf_sync_timestamp'] = null;

    // 巨大テーブル(weekly_reports等)への不要な書き込みを避けるため、対象エンティティのみ保存
    saveData($data, ['mf_invoices', 'mf_sync_timestamp']);

    successResponse(['deleted' => $beforeCount], "MF請求書データをクリアしました（{$beforeCount}件削除）");
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}
