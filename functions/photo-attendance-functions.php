<?php
/**
 * アルコールチェック管理 - 関数
 */

define('PHOTO_ATTENDANCE_FILE', __DIR__ . '/../config/photo-attendance-data.json');
define('PHOTO_UPLOAD_DIR', __DIR__ . '/uploads/attendance-photos/');

// アップロードディレクトリの作成
if (!file_exists(PHOTO_UPLOAD_DIR)) {
    mkdir(PHOTO_UPLOAD_DIR, 0755, true);
}

/**
 * 従業員一覧を取得
 */
function getEmployees() {
    $data = getData();
    return $data['employees'] ?? array();
}

/**
 * 従業員のChat User IDを更新
 * @param string|int $employeeId 従業員ID
 * @param string $chatUserId Google Chat User ID (users/xxxxx形式)
 * @return bool 成功したかどうか
 */
function updateEmployeeChatUserId($employeeId, $chatUserId) {
    $data = getData();

    if (!isset($data['employees'])) {
        return false;
    }

    $updated = false;
    foreach ($data['employees'] as &$emp) {
        $empId = $emp['id'] ?? null;
        if ($empId == $employeeId) {
            // 既にchat_user_idが設定されていればスキップ
            if (!empty($emp['chat_user_id'])) {
                return true; // 既に設定済み
            }
            $emp['chat_user_id'] = $chatUserId;
            $updated = true;
            break;
        }
    }
    unset($emp);

    if ($updated) {
        saveData($data);
    }

    return $updated;
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
 * 未紐付けレコードでもsender_user_idから従業員を特定する
 *
 * @param string $date YYYY-MM-DD形式
 * @return array employee_id => ['start' => [...], 'end' => [...]]
 */
function getUploadStatusForDate($date) {
    $allData = getPhotoAttendanceData();
    $result = array();

    // 従業員のchat_user_idマッピングを構築
    $employees = getEmployees();
    $chatUserIdToEmployee = [];
    foreach ($employees as $emp) {
        if (!empty($emp['chat_user_id'])) {
            $chatUserIdToEmployee[$emp['chat_user_id']] = (string)($emp['id'] ?? '');
        }
    }

    foreach ($allData as $record) {
        if ($record['upload_date'] !== $date) {
            continue;
        }

        // 従業員IDを特定
        $employeeId = null;

        // 1. employee_idが設定されている場合はそれを使用
        if (!empty($record['employee_id'])) {
            $employeeId = (string)$record['employee_id'];
        }
        // 2. 未紐付けでもsender_user_idから従業員を特定
        elseif (!empty($record['sender_user_id']) && isset($chatUserIdToEmployee[$record['sender_user_id']])) {
            $employeeId = $chatUserIdToEmployee[$record['sender_user_id']];
        }

        // 従業員が特定できない場合はスキップ
        if (empty($employeeId)) {
            continue;
        }

        $uploadType = $record['upload_type'] ?? '';

        if (!isset($result[$employeeId])) {
            $result[$employeeId] = ['start' => null, 'end' => null];
        }

        // upload_typeが 'start' または 'end' の場合のみ正しく処理
        // 'chat_import' など不正な値の場合は、時間に基づいて自動判定
        if ($uploadType === 'start' || $uploadType === 'end') {
            $result[$employeeId][$uploadType] = $record;
        } else {
            // upload_typeが不正な場合、startが空ならstart、そうでなければend
            if ($result[$employeeId]['start'] === null) {
                $result[$employeeId]['start'] = $record;
            } elseif ($result[$employeeId]['end'] === null) {
                $result[$employeeId]['end'] = $record;
            }
            // 3件目以降は無視
        }
    }

    return $result;
}

/**
 * 指定日の未紐付け写真を取得
 *
 * @param string $date YYYY-MM-DD形式
 * @return array 未紐付けの写真レコード一覧
 */
function getUnassignedPhotosForDate($date) {
    $allData = getPhotoAttendanceData();
    $result = [];

    foreach ($allData as $record) {
        if ($record['upload_date'] === $date) {
            // 未紐付けの条件:
            // 1. employee_idがない
            // 2. upload_typeがchat_import（紐付け待ち状態）
            $isUnassigned = empty($record['employee_id']) ||
                           $record['upload_type'] === 'chat_import';

            if ($isUnassigned) {
                $result[] = $record;
            }
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
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    // 拡張子チェック
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['success' => false, 'message' => '画像ファイル（JPG, PNG, GIF）のみアップロード可能です'];
    }

    // MIMEタイプチェック（fileinfoが利用可能な場合）
    $mimeType = null;
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if ($mimeType && !in_array($mimeType, $allowedTypes)) {
                return ['success' => false, 'message' => '画像ファイルのみアップロード可能です'];
            }
        }
    }

    // MIMEタイプが取得できなかった場合は、拡張子から推測
    if (!$mimeType) {
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        ];
        $mimeType = $mimeMap[$extension] ?? 'image/jpeg';
    }

    // ファイルサイズチェック (50MB)
    if ($file['size'] > 50 * 1024 * 1024) {
        return ['success' => false, 'message' => 'ファイルサイズは50MB以下にしてください'];
    }

    // ファイル名生成（既に$extensionは上で定義済み）
    $today = date('Y-m-d');
    $yearMonth = date('Y-m'); // 月ごとのフォルダ

    // 月別フォルダを作成
    $monthFolder = PHOTO_UPLOAD_DIR . $yearMonth . '/';
    if (!file_exists($monthFolder)) {
        mkdir($monthFolder, 0755, true);
    }

    $filename = sprintf(
        '%d_%s_%s_%s.%s',
        $employeeId,
        $today,
        $uploadType,
        uniqid(),
        $extension
    );

    $uploadPath = $monthFolder . $filename;

    // 画像をリサイズして保存（GDが有効な場合）
    // GDが無効な場合は元ファイルをそのままコピー
    if (extension_loaded('gd') && function_exists('imagecreatefromjpeg')) {
        $resized = resizeImage($file['tmp_name'], $uploadPath, $mimeType);
        if (!$resized) {
            // リサイズ失敗時は元ファイルをコピー
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                return ['success' => false, 'message' => 'ファイルの保存に失敗しました'];
            }
        }
    } else {
        // GD拡張機能が無効の場合は元ファイルをそのまま保存
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return ['success' => false, 'message' => 'ファイルの保存に失敗しました'];
        }
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
    $yearMonth = date('Y-m');
    $newRecord = [
        'id' => uniqid(),
        'employee_id' => $employeeId,
        'upload_date' => $today,
        'upload_type' => $uploadType,
        'photo_path' => 'uploads/attendance-photos/' . $yearMonth . '/' . $filename,
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

/**
 * 特定日付に車を使わない従業員IDのリストを取得
 *
 * @param string $date YYYY-MM-DD形式
 * @return array employee_id のリスト
 */
function getNoCarUsageForDate($date) {
    $data = getData();
    $noCarUsage = $data['no_car_usage'] ?? array();

    $result = array();
    foreach ($noCarUsage as $record) {
        if ($record['date'] === $date) {
            // 文字列にキャストして型の一貫性を保つ
            $result[] = (string)$record['employeeId'];
        }
    }

    return $result;
}

/**
 * 指定日のアルコールチェック対象の従業員IDリストを取得
 * その日にChat同期で取得できた従業員のみを対象とする
 * 未紐付けレコードでもsender_user_idから従業員を特定する
 *
 * @param string $date YYYY-MM-DD形式の日付
 * @return array 対象の employee_id リスト
 */
function getAlcoholCheckTargetEmployeesForDate($date) {
    $allData = getPhotoAttendanceData();
    $targetEmployees = [];

    // 従業員のchat_user_idマッピングを構築
    $employees = getEmployees();
    $chatUserIdToEmployee = [];
    foreach ($employees as $emp) {
        if (!empty($emp['chat_user_id'])) {
            $chatUserIdToEmployee[$emp['chat_user_id']] = (string)($emp['id'] ?? '');
        }
    }

    foreach ($allData as $record) {
        // その日のChat同期レコードのみ
        if (($record['source'] ?? '') !== 'chat' || ($record['upload_date'] ?? '') !== $date) {
            continue;
        }

        $empId = null;

        // 1. employee_idが設定されている場合はそれを使用
        if (!empty($record['employee_id'])) {
            $empId = (string)$record['employee_id'];
        }
        // 2. 未紐付けでもsender_user_idから従業員を特定
        elseif (!empty($record['sender_user_id']) && isset($chatUserIdToEmployee[$record['sender_user_id']])) {
            $empId = $chatUserIdToEmployee[$record['sender_user_id']];
        }

        if ($empId && !in_array($empId, $targetEmployees, true)) {
            $targetEmployees[] = $empId;
        }
    }

    return $targetEmployees;
}

/**
 * アルコールチェック対象の従業員IDリストを取得（後方互換性のため残す）
 * Chat同期で一度でも紐付けられた従業員のみを対象とする
 *
 * @return array 対象の employee_id リスト
 */
function getAlcoholCheckTargetEmployees() {
    $allData = getPhotoAttendanceData();
    $targetEmployees = [];

    foreach ($allData as $record) {
        // Chat同期で紐付けられた従業員のみ
        if (($record['source'] ?? '') === 'chat' && !empty($record['employee_id'])) {
            // 型を文字列に統一して比較
            $empId = (string)$record['employee_id'];
            if (!in_array($empId, $targetEmployees, true)) {
                $targetEmployees[] = $empId;
            }
        }
    }

    return $targetEmployees;
}

/**
 * 画像をリサイズして保存
 *
 * @param string $sourcePath 元ファイルのパス
 * @param string $destPath 保存先パス
 * @param string $mimeType MIMEタイプ
 * @param int $maxWidth 最大幅（デフォルト: 800）
 * @param int $maxHeight 最大高さ（デフォルト: 600）
 * @return bool 成功したかどうか
 */
function resizeImage($sourcePath, $destPath, $mimeType, $maxWidth = 800, $maxHeight = 600) {
    // 元画像を読み込み
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if (!$sourceImage) {
        return false;
    }

    // 元画像のサイズを取得
    $originalWidth = imagesx($sourceImage);
    $originalHeight = imagesy($sourceImage);

    // リサイズ比率を計算
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);

    // 元画像がすでに小さい場合はリサイズしない
    if ($ratio >= 1) {
        $newWidth = $originalWidth;
        $newHeight = $originalHeight;
    } else {
        $newWidth = round($originalWidth * $ratio);
        $newHeight = round($originalHeight * $ratio);
    }

    // 新しい画像を作成
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // 透過処理（PNG/GIF用）
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }

    // リサイズ実行
    imagecopyresampled(
        $newImage, $sourceImage,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        $originalWidth, $originalHeight
    );

    // 保存
    $saved = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $saved = imagejpeg($newImage, $destPath, 85); // 品質85%
            break;
        case 'image/png':
            $saved = imagepng($newImage, $destPath, 6); // 圧縮レベル6
            break;
        case 'image/gif':
            $saved = imagegif($newImage, $destPath);
            break;
    }

    // メモリ解放
    imagedestroy($sourceImage);
    imagedestroy($newImage);

    return $saved;
}
