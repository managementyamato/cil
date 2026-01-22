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
    $file = dirname(__DIR__) . '/data.json';
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
    $file = dirname(__DIR__) . '/data.json';
    if (!file_exists($file)) {
        return array();
    }

    $json = file_get_contents($file);
    $data = json_decode($json, true);
    $noCarUsage = $data['no_car_usage'] ?? array();

    $result = array();
    foreach ($noCarUsage as $record) {
        if ($record['date'] === $date) {
            $result[] = $record['employeeId'];
        }
    }

    return $result;
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
