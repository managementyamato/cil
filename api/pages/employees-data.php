<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';
require_once __DIR__ . '/../../functions/soft-delete.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET']
]);

// 従業員データは機密情報（給与・個人情報等）を含むため編集権限以上に限定
if (!canEdit()) {
    errorResponse('権限がありません', 403);
}

$data = getData();
$employees = filterDeleted($data['employees'] ?? []);

$today = date('Y-m-d');

// Build response with relevant fields
$result = [];
foreach ($employees as $emp) {
    $isRetired = !empty($emp['leave_date']) && $emp['leave_date'] <= $today;
    $leaveDateFuture = !empty($emp['leave_date']) && $emp['leave_date'] > $today;

    $result[] = [
        'id' => $emp['id'] ?? '',
        'code' => $emp['code'] ?? '',
        'name' => $emp['name'] ?? '',
        'area' => $emp['area'] ?? '',
        'email' => $emp['email'] ?? '',
        'vehicle_number' => $emp['vehicle_number'] ?? '',
        'role' => $emp['role'] ?? '',
        'memo' => $emp['memo'] ?? '',
        'qualifications' => $emp['qualifications'] ?? '',
        'join_date' => $emp['join_date'] ?? '',
        'leave_date' => $emp['leave_date'] ?? '',
        'chat_member' => !isset($emp['chat_member']) || $emp['chat_member'] === true,
        'is_retired' => $isRetired,
        'leave_date_future' => $leaveDateFuture,
    ];
}

// Sort by employee code
usort($result, function($a, $b) {
    if ($a['code'] === '' && $b['code'] === '') return 0;
    if ($a['code'] === '') return 1;
    if ($b['code'] === '') return -1;
    return strcmp($a['code'], $b['code']);
});

$retiredCount = count(array_filter($result, fn($e) => $e['is_retired']));

$userRole = $_SESSION['user_role'] ?? 'sales';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'employees' => $result,
        'totalCount' => count($data['employees'] ?? []),
        'retiredCount' => $retiredCount,
        'canEdit' => canEdit(),
        'canDelete' => canDelete(),
        'userRole' => $userRole,
    ]
], JSON_UNESCAPED_UNICODE);
