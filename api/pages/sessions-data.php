<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';
require_once __DIR__ . '/../../functions/login-security.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET']
]);

$userId = $_SESSION['user_email'];

// Active sessions
$sessions = getActiveSessions($userId);
$currentSessionId = session_id();

$sessionList = [];
foreach ($sessions as $s) {
    $isCurrent = ($s['session_id'] === $currentSessionId);
    $sessionList[] = [
        'session_id' => $s['session_id'],
        'device' => $s['device'] ?? 'Unknown Device',
        'ip' => $s['ip'] ?? 'Unknown',
        'last_activity' => $s['last_activity'] ?? '',
        'is_current' => $isCurrent,
    ];
}

// Login history (last 20)
$loginHistory = getLoginHistory($userId);
$historyList = [];
foreach (array_slice($loginHistory, 0, 20) as $record) {
    $historyList[] = [
        'timestamp' => $record['timestamp'] ?? '',
        'ip' => $record['ip'] ?? 'Unknown',
        'device' => parseUserAgent($record['user_agent'] ?? ''),
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'sessions' => $sessionList,
        'loginHistory' => $historyList,
    ]
], JSON_UNESCAPED_UNICODE);
