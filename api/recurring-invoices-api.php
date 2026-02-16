<?php
/**
 * 定期請求書作成API
 * 管理画面から呼び出され、CSVに記載された請求書を一括作成
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/recurring-invoice.php';

// 実行時間制限を延長（定期請求書作成は時間がかかるため）
set_time_limit(300);  // 5分

// API初期化（認証・CSRF検証・レート制限）
initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'rateLimit' => 10,  // 重い処理なので制限を厳しく
    'allowedMethods' => ['POST']
]);

// 管理者のみ実行可能
if (!isAdmin()) {
    errorResponse('この機能は管理者のみ実行できます', 403);
}

try {
    // リクエストから対象月を取得
    $input = getJsonInput();
    $targetMonth = $input['target_month'] ?? null;

    // 定期請求書を一括作成
    $result = createAllRecurringInvoices($targetMonth);

    // 結果を返す（成功・失敗に関わらず）
    successResponse([
        'success_count' => $result['success'],
        'failed_count' => $result['failed'],
        'total' => $result['total'],
        'results' => $result['results']
    ], $result['message']);

} catch (Exception $e) {
    logError('定期請求書API実行エラー', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    errorResponse('定期請求書作成エラー: ' . $e->getMessage(), 500);
}
