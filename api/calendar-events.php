<?php
/**
 * カレンダーイベント取得API（非同期読み込み用）
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/google-calendar.php';

header('Content-Type: application/json');

// 出力バッファリングを無効化して即座にレスポンス
if (ob_get_level()) ob_end_clean();

// 実行時間の制限を設定（最大10秒）
set_time_limit(10);

try {
    $calendar = new GoogleCalendarClient();

    if (!$calendar->isConfigured()) {
        echo json_encode(['error' => 'Calendar not configured', 'events' => []]);
        exit;
    }

    $todayEvents = $calendar->getTodayEvents();
    echo json_encode($todayEvents);
} catch (Exception $e) {
    error_log('[Calendar] Error: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage(), 'events' => []]);
}
