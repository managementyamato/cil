<?php
/**
 * 従業員のchat_memberフラグを更新するAPI
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

// API初期化
initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'rateLimit' => 60,
    'allowedMethods' => ['POST']
]);

$input = getJsonInput();

$employeeId = $input['employee_id'] ?? '';
$chatMember = $input['chat_member'] ?? false;

if (empty($employeeId)) {
    errorResponse('employee_id is required', 400);
}

// data.jsonを更新（getData/saveData経由）
$data = getData();

$updated = false;
foreach ($data['employees'] as &$employee) {
    // idまたはcodeでマッチング
    $matched = false;
    if (isset($employee['id']) && (string)$employee['id'] === (string)$employeeId) {
        $matched = true;
    } elseif (isset($employee['code']) && $employee['code'] === $employeeId) {
        $matched = true;
    }

    if ($matched) {
        $employee['chat_member'] = (bool)$chatMember;
        $updated = true;
        break;
    }
}
unset($employee);

if ($updated) {
    saveData($data);
    successResponse(['updated' => true, 'employee_id' => $employeeId, 'chat_member' => $chatMember]);
} else {
    errorResponse('Employee not found', 404);
}
