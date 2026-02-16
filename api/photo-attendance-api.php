<?php
/**
 * アルコールチェック写真管理 API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/photo-attendance-functions.php';

header('Content-Type: application/json; charset=utf-8');

// 編集権限チェック
if (!canEdit()) {
    echo json_encode(['success' => false, 'message' => '権限がありません']);
    exit;
}

// POST時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
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
            echo json_encode(['success' => false, 'message' => '必須パラメータが不足しています']);
            break;
        }

        if (!in_array($uploadType, ['start', 'end'])) {
            echo json_encode(['success' => false, 'message' => '不正なアップロードタイプです']);
            break;
        }

        $result = assignPhotoToEmployee($photoId, $employeeId, $uploadType);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    case 'reassign':
        // 既に紐付けられた写真の従業員を変更
        $photoId = $input['photo_id'] ?? '';
        $newEmployeeId = $input['new_employee_id'] ?? '';

        if (empty($photoId) || empty($newEmployeeId)) {
            echo json_encode(['success' => false, 'message' => '必須パラメータが不足しています']);
            break;
        }

        $result = reassignPhotoToEmployee($photoId, $newEmployeeId);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['success' => false, 'message' => '不明なアクション']);
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
