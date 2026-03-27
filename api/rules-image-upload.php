<?php
/**
 * 社内規則 画像アップロード API
 * 管理部（admin）のみ
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['POST'],
]);

if (!isAdmin()) errorResponse('権限がありません', 403);

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['image']['error'] ?? -1;
    errorResponse("ファイルアップロードエラー (code: {$errCode})", 400);
}

$file    = $_FILES['image'];
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo   = new finfo(FILEINFO_MIME_TYPE);
$mime    = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed, true)) {
    errorResponse('JPEG / PNG / GIF / WebP のみアップロード可能です', 400);
}

$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$ext    = $extMap[$mime];

$uploadDir = __DIR__ . '/../uploads/rules/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        errorResponse('アップロードディレクトリの作成に失敗しました', 500);
    }
}

$filename = 'rule_' . uniqid('', true) . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    errorResponse('ファイルの保存に失敗しました', 500);
}

successResponse(['filename' => $filename], 'アップロード完了');
