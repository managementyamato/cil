<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';
require_once __DIR__ . '/../../functions/audit-log.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET']
]);

// Admin only
if (!isAdmin()) {
    errorResponse('管理者権限が必要です', 403);
}

$filters = [
    'action' => $_GET['action'] ?? '',
    'target' => $_GET['target'] ?? '',
    'user' => $_GET['user'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];
$page = max(1, intval($_GET['page'] ?? 1));

$result = getFilteredAuditLogs($filters, $page, 50);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'logs' => $result['logs'],
        'total' => $result['total'],
        'page' => $result['page'],
        'totalPages' => $result['total_pages'],
        'filters' => $filters,
    ]
], JSON_UNESCAPED_UNICODE);
