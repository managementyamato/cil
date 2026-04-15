<?php
/**
 * 値引き申請 PDFアップロード API
 *
 * 処理フロー:
 *   1. $_FILES['pdf'] を検証 (PDF のみ, 最大25MB)
 *   2. 一時的にローカルの uploads/discount-approvals/ に保存
 *   3. Google Drive にアップロード（設定されたフォルダへ）
 *   4. 「リンクを知っている全員が閲覧可」で共有リンク生成
 *   5. ローカル一時ファイルは削除
 *   6. drive_file_id / drive_view_link / drive_download_link を返却
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/google-drive.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['POST'],
]);

if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['pdf']['error'] ?? -1;
    errorResponse('ファイルアップロードエラー (code: ' . $errCode . ')', 400);
}

$file  = $_FILES['pdf'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);

if ($mime !== 'application/pdf') {
    errorResponse('PDF ファイルのみアップロード可能です', 400);
}

// 最大25MB
if ($file['size'] > 25 * 1024 * 1024) {
    errorResponse('ファイルサイズは25MB以内にしてください', 400);
}

// ローカル一時保存
$uploadDir = __DIR__ . '/../uploads/discount-approvals/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        errorResponse('アップロードディレクトリの作成に失敗しました', 500);
    }
}

// 元のファイル名を保持（セキュリティのためサニタイズ）
$originalName = $file['name'] ?? 'discount_approval.pdf';
$originalName = preg_replace('/[^\w\-\.\p{L}\p{N}]+/u', '_', $originalName);
if (!preg_match('/\.pdf$/i', $originalName)) {
    $originalName .= '.pdf';
}

$localFilename = 'da_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
$localPath     = $uploadDir . $localFilename;

if (!move_uploaded_file($file['tmp_name'], $localPath)) {
    errorResponse('ファイルの保存に失敗しました', 500);
}

// Driveにアップロード
try {
    $drive = new GoogleDriveClient();

    // 値引き申請用フォルダ設定を取得
    $folder = $drive->getDiscountApprovalFolder();
    $folderId = $folder ? $folder['id'] : null;

    // Drive上のファイル名: 日時_申請者名_元ファイル名
    $applicantName = $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'unknown';
    $driveFileName = date('Ymd_His') . '_' . $applicantName . '_' . $originalName;

    $result = $drive->uploadFile($localPath, $driveFileName, 'application/pdf', $folderId);
    $driveFileId = $result['id'] ?? null;

    if (!$driveFileId) {
        throw new Exception('Drive upload did not return file ID');
    }

    // 「リンクを知っている全員が閲覧可」にする
    $drive->makeFilePublicViaLink($driveFileId);

    // 閲覧・ダウンロードリンク
    $viewLink     = $result['webViewLink']    ?? "https://drive.google.com/file/d/{$driveFileId}/view";
    $downloadLink = "https://drive.google.com/uc?export=download&id={$driveFileId}";

    // ローカル一時ファイル削除
    @unlink($localPath);

    successResponse([
        'drive_file_id'    => $driveFileId,
        'drive_file_name'  => $driveFileName,
        'drive_view_link'  => $viewLink,
        'drive_download_link' => $downloadLink,
        'original_name'    => $originalName,
        'size'             => $file['size'],
    ], 'PDFをGoogle Driveにアップロードしました');

} catch (Exception $e) {
    // ローカルファイルが残っていれば削除
    if (file_exists($localPath)) {
        @unlink($localPath);
    }
    error_log('[DiscountPDFUpload] ' . $e->getMessage());
    errorResponse('Driveアップロード失敗: ' . $e->getMessage(), 500);
}
