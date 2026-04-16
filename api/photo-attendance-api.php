<?php
/**
 * アルコールチェック写真管理 API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/photo-attendance-functions.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'allowedMethods' => ['GET', 'POST'],
]);

if (!canEdit()) {
    errorResponse('権限がありません', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'assign':
        // 写真を従業員に紐付け
        $photoId = $input['photo_id'] ?? '';
        $employeeId = $input['employee_id'] ?? '';
        $uploadType = $input['upload_type'] ?? '';

        if (empty($photoId) || empty($employeeId) || empty($uploadType)) {
            errorResponse('必須パラメータが不足しています', 400);
        }
        if (!in_array($uploadType, ['start', 'end'])) {
            errorResponse('不正なアップロードタイプです', 400);
        }

        $result = assignPhotoToEmployee($photoId, $employeeId, $uploadType);
        if (!$result['success']) {
            errorResponse($result['message'] ?? '紐付けに失敗しました', 400);
        }
        successResponse(null, $result['message'] ?? '紐付けが完了しました');
        break;

    case 'reassign':
        // 既に紐付けられた写真の従業員を変更
        $photoId = $input['photo_id'] ?? '';
        $newEmployeeId = $input['new_employee_id'] ?? '';

        if (empty($photoId) || empty($newEmployeeId)) {
            errorResponse('必須パラメータが不足しています', 400);
        }

        $result = reassignPhotoToEmployee($photoId, $newEmployeeId);
        if (!$result['success']) {
            errorResponse($result['message'] ?? '変更に失敗しました', 400);
        }
        successResponse(
            ['old_employee_id' => $result['old_employee_id'] ?? null],
            $result['message'] ?? '紐付けを変更しました'
        );
        break;

    default:
        errorResponse('不明なアクション', 400);
}

/**
 * 写真を従業員に紐付ける
 */
function assignPhotoToEmployee($photoId, $employeeId, $uploadType) {
    $allData = getPhotoAttendanceData();

    $updated = false;
    foreach ($allData as &$record) {
        if ($record['id'] === $photoId) {
            $record['employee_id'] = $employeeId;
            $record['upload_type'] = $uploadType;
            $record['assigned_at'] = date('Y-m-d H:i:s');
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        return ['success' => false, 'message' => '写真が見つかりません'];
    }

    savePhotoAttendanceData($allData);

    return ['success' => true, 'message' => '紐付けが完了しました'];
}

/**
 * 既に紐付けられた写真の従業員を変更する
 */
function reassignPhotoToEmployee($photoId, $newEmployeeId) {
    $allData = getPhotoAttendanceData();

    $updated = false;
    $oldEmployeeId = null;
    foreach ($allData as &$record) {
        if ($record['id'] === $photoId) {
            $oldEmployeeId = $record['employee_id'] ?? null;
            $record['employee_id'] = $newEmployeeId;
            $record['reassigned_at'] = date('Y-m-d H:i:s');
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        return ['success' => false, 'message' => '写真が見つかりません'];
    }

    savePhotoAttendanceData($allData);

    return ['success' => true, 'message' => '紐付けを変更しました', 'old_employee_id' => $oldEmployeeId];
}
