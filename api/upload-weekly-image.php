<?php
/**
 * 週報 画像・PDFアップロード API
 * Google Driveにアップロード → 共有リンクで表示
 * 認証済みユーザーなら誰でも使用可
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/google-drive.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['POST'],
]);

// image または file パラメータを受け付ける
$fileKey = !empty($_FILES['image']) ? 'image' : (!empty($_FILES['file']) ? 'file' : null);

if (!$fileKey || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES[$fileKey]['error'] ?? -1;
    errorResponse('ファイルアップロードエラー (code: ' . $errCode . ')', 400);
}

$file    = $_FILES[$fileKey];
$allowedImages = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowedPdf    = ['application/pdf'];
$allowed = array_merge($allowedImages, $allowedPdf);

$finfo   = new finfo(FILEINFO_MIME_TYPE);
$mime    = $finfo->file($file['tmp_name']);

if (!in_array($mime, $allowed, true)) {
    errorResponse('JPEG / PNG / GIF / WebP / PDF のみアップロード可能です', 400);
}

$isPdf   = in_array($mime, $allowedPdf, true);
$isImage = in_array($mime, $allowedImages, true);

// ファイルサイズ上限：画像10MB、PDF25MB
$maxSize = $isPdf ? 25 * 1024 * 1024 : 10 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    $label = $isPdf ? '25MB' : '10MB';
    errorResponse("ファイルサイズは{$label}以内にしてください", 400);
}

// 一時ディレクトリに保存
$uploadDir = __DIR__ . '/../uploads/weekly-reports/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$extMap = [
    'image/jpeg' => 'jpg', 'image/png' => 'png',
    'image/gif' => 'gif', 'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];
$ext = $extMap[$mime];

$localFilename = 'wr_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$localPath     = $uploadDir . $localFilename;

if (!move_uploaded_file($file['tmp_name'], $localPath)) {
    errorResponse('ファイルの保存に失敗しました', 500);
}

// 元のファイル名を保持
$originalName = $file['name'] ?? $localFilename;
$originalName = preg_replace('/[^\w\-\.\p{L}\p{N}]+/u', '_', $originalName);

// Google Driveにアップロード
try {
    $drive = new GoogleDriveClient();

    $folder = $drive->getWeeklyReportFolder();
    $folderId = $folder ? $folder['id'] : null;

    $userName = $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'unknown';
    $driveFileName = date('Ymd_His') . '_' . $userName . '_' . $originalName;

    $result = $drive->uploadFile($localPath, $driveFileName, $mime, $folderId);
    $driveFileId = $result['id'] ?? null;

    if (!$driveFileId) {
        throw new Exception('Drive upload did not return file ID');
    }

    // 「リンクを知っている全員が閲覧可」にする
    $drive->makeFilePublicViaLink($driveFileId);

    // 画像: 直接表示用URL / PDF: 閲覧用URL
    if ($isImage) {
        $url = "https://lh3.googleusercontent.com/d/{$driveFileId}";
    } else {
        $url = $result['webViewLink'] ?? "https://drive.google.com/file/d/{$driveFileId}/view";
    }

    // ローカル一時ファイル削除
    @unlink($localPath);

    successResponse([
        'url'           => $url,
        'filename'      => $driveFileName,
        'original_name' => $originalName,
        'type'          => $isPdf ? 'pdf' : 'image',
        'drive_file_id' => $driveFileId,
    ], 'アップロード完了');

} catch (Exception $e) {
    // Drive失敗時はローカルファイルで配信（フォールバック）
    error_log('[WeeklyImageUpload] Drive upload failed: ' . $e->getMessage());

    $url = '/api/serve-weekly-file.php?f=' . urlencode($localFilename);
    successResponse([
        'url'           => $url,
        'filename'      => $localFilename,
        'original_name' => $originalName,
        'type'          => $isPdf ? 'pdf' : 'image',
        'drive_error'   => $e->getMessage(),
    ], 'ローカルに保存しました（Drive連携失敗）');
}
