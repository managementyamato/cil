<?php
/**
 * カレンダー設定API
 *
 * GET  - 利用可能なカレンダー一覧と選択状態を取得
 * POST - 選択されたカレンダーを保存
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/google-calendar.php';
require_once __DIR__ . '/../functions/api-middleware.php';

// API初期化
initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'rateLimit' => 100,
    'allowedMethods' => ['GET', 'POST']
]);

$calendar = new GoogleCalendarClient();

if (!$calendar->isConfigured()) {
    errorResponse('カレンダーが連携されていません', 400);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // カレンダー一覧と選択状態を取得
    $calendarList = $calendar->getCalendarList();

    if (!empty($calendarList['error'])) {
        errorResponse($calendarList['error'], 500);
    }

    $selectedCalendars = $calendar->getSelectedCalendars();

    // 各カレンダーに選択状態を追加
    $calendarsWithSelection = array_map(function($cal) use ($selectedCalendars) {
        $cal['selected'] = empty($selectedCalendars) || in_array($cal['id'], $selectedCalendars);
        return $cal;
    }, $calendarList['calendars']);

    successResponse([
        'calendars' => $calendarsWithSelection,
        'selected_ids' => $selectedCalendars
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();

    if (!isset($input['calendar_ids']) || !is_array($input['calendar_ids'])) {
        errorResponse('calendar_ids は配列で指定してください', 400);
    }

    $calendar->saveSelectedCalendars($input['calendar_ids']);

    successResponse(null, 'カレンダー設定を保存しました');
}
