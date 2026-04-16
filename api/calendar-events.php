<?php
/**
 * カレンダーイベント取得API（非同期読み込み用）
 *
 * NOTE: レスポンスは `{events: [...]}` の生フォーマット（successResponse でラップしない）。
 *       既存フロントエンド (pages/index.php, frontend/src/app/dashboard/page.tsx) が
 *       `data.events` を直接参照しているため互換性維持が必要。
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/google-calendar.php';

// 出力バッファリングを無効化して即座にレスポンス
if (ob_get_level()) ob_end_clean();

// 実行時間の制限を設定（最大10秒）
set_time_limit(10);

initApi([
    'requireAuth' => true,
    'requireCsrf' => false, // GET専用のため
    'allowedMethods' => ['GET'],
]);

// セッションロックを解放してからGoogleAPI呼び出し（他リクエストのブロック防止）
session_write_close();

try {
    $calendar = new GoogleCalendarClient();

    if (!$calendar->isConfigured()) {
        echo json_encode(['error' => 'Calendar not configured', 'events' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $todayEvents = $calendar->getTodayEvents();
    echo json_encode($todayEvents, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('[Calendar] Error: ' . $e->getMessage());
    echo json_encode(['error' => 'カレンダーの取得に失敗しました', 'events' => []], JSON_UNESCAPED_UNICODE);
}
