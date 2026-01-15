<?php
/**
 * アルコールチェック管理 - 関数
 */

define('PHOTO_ATTENDANCE_FILE', __DIR__ . '/photo-attendance-data.json');
define('PHOTO_UPLOAD_DIR', __DIR__ . '/uploads/attendance-photos/');

// アップロードディレクトリの作成
if (!file_exists(PHOTO_UPLOAD_DIR)) {
    mkdir(PHOTO_UPLOAD_DIR, 0755, true);
}

/**
 * 従業員一覧を取得
 */
function getEmployees() {
    $file = __DIR__ . '/data.json';
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        return $data['employees'] ?? array();
    }
    return array();
}

/**
 * アルコールチェックデータを取得
 */
function getPhotoAttendanceData() {
    if (file_exists(PHOTO_ATTENDANCE_FILE)) {
        $json = file_get_contents(PHOTO_ATTENDANCE_FILE);
        return json_decode($json, true) ?: array();
    }
    return array();
}

/**
 * アルコールチェックデータを保存
 */
function savePhotoAttendanceData($data) {
    return file_put_contents(
        PHOTO_ATTENDANCE_FILE,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

/**
 * 特定日付のアップロード状況を取得
 *
 * @param string $date YYYY-MM-DD形式
 * @return array employee_id => ['start' => [...], 'end' => [...]]
 */
function getUploadStatusForDate($date) {
    $allData = getPhotoAttendanceData();
    $result = array();

    foreach ($allData as $record) {
        if ($record['upload_date'] === $date) {
            $employeeId = $record['employee_id'];
            $uploadType = $record['upload_type'];

            if (!isset($result[$employeeId])) {
                $result[$employeeId] = ['start' => null, 'end' => null];
            }

            $result[$employeeId][$uploadType] = $record;
        }
    }

    return $result;
}

/**
 * 写真をアップロード
 *
 * @param int $employeeId 従業員ID
 * @param string $uploadType 'start' または 'end'
 * @param array $file $_FILES['photo']の内容
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function uploadPhoto($employeeId, $uploadType, $file) {
    // バリデーション
    if (!in_array($uploadType, ['start', 'end'])) {
        return ['success' => false, 'message' => '不正なアップロードタイプです'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'ファイルアップロードエラー'];
    }

    // ファイル形式チェック
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => '画像ファイルのみアップロード可能です'];
    }

    // ファイルサイズチェック (5MB)
    if ($file['size'] > 50 * 1024 * 1024) {
        return ['success' => false, 'message' => 'ファイルサイズは50MB以下にしてください'];
    }

    // ファイル名生成
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $today = date('Y-m-d');

    // 日付別フォルダを作成
    $dateFolder = PHOTO_UPLOAD_DIR . $today . '/';
    if (!file_exists($dateFolder)) {
        mkdir($dateFolder, 0755, true);
    }

    $filename = sprintf(
        '%d_%s_%s.%s',
        $employeeId,
        $uploadType,
        uniqid(),
        $extension
    );

    $uploadPath = $dateFolder . $filename;

    // ファイル移動
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => false, 'message' => 'ファイルの保存に失敗しました'];
    }

    // データ保存
    $allData = getPhotoAttendanceData();

    // 既存の同じ日付・従業員・タイプのレコードを削除
    $allData = array_filter($allData, function($record) use ($employeeId, $today, $uploadType) {
        return !($record['employee_id'] == $employeeId &&
                 $record['upload_date'] === $today &&
                 $record['upload_type'] === $uploadType);
    });

    // 新しいレコードを追加
    $newRecord = [
        'id' => uniqid(),
        'employee_id' => $employeeId,
        'upload_date' => $today,
        'upload_type' => $uploadType,
        'photo_path' => 'uploads/attendance-photos/' . $today . '/' . $filename,
        'uploaded_at' => date('Y-m-d H:i:s')
    ];

    $allData[] = $newRecord;

    // 保存
    savePhotoAttendanceData(array_values($allData));

    return [
        'success' => true,
        'message' => '写真をアップロードしました',
        'data' => $newRecord
    ];
}

/**
 * 特定従業員の本日のアップロード状況を取得
 *
 * @param int $employeeId
 * @return array ['start' => bool, 'end' => bool]
 */
function getEmployeeUploadStatus($employeeId) {
    $today = date('Y-m-d');
    $status = getUploadStatusForDate($today);

    return [
        'start' => isset($status[$employeeId]['start']),
        'end' => isset($status[$employeeId]['end'])
    ];
}
