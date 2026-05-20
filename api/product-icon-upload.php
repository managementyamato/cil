<?php
/**
 * 製品アイコン画像アップロード API（管理部専用）
 *
 *   POST multipart/form-data:
 *     icon: ファイル本体 (JPEG / PNG / GIF / WebP / SVG)
 *     id:   製品 ID（保存ファイル名のプレフィックス用）
 *
 *   レスポンス: { url: "/uploads/product-icons/{id}_{rand}.{ext}" }
 *
 * 保存先: uploads/product-icons/
 * 権限: admin のみ
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['POST'],
]);

if (!isAdmin()) errorResponse('admin のみアクセス可能です', 403);

if (empty($_FILES['icon']) || $_FILES['icon']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['icon']['error'] ?? -1;
    errorResponse('ファイルアップロードエラー (code: ' . $errCode . ')', 400);
}

$id = trim((string)($_POST['id'] ?? ''));
if (!preg_match('/^[a-z0-9_-]+$/', $id)) {
    errorResponse('id の形式が不正です', 400);
}

$file = $_FILES['icon'];

// 最大 1MB
if ($file['size'] > 1024 * 1024) {
    errorResponse('ファイルサイズは 1MB 以内にしてください', 400);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);

$extMap = [
    'image/jpeg'    => 'jpg',
    'image/png'     => 'png',
    'image/gif'     => 'gif',
    'image/webp'    => 'webp',
    'image/svg+xml' => 'svg',
];
if (!isset($extMap[$mime])) {
    errorResponse('JPEG / PNG / GIF / WebP / SVG のみアップロード可能です（検出: ' . htmlspecialchars($mime) . '）', 400);
}
$ext = $extMap[$mime];

$uploadDir = __DIR__ . '/../uploads/product-icons/';
if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
        errorResponse('アップロードディレクトリの作成に失敗しました', 500);
    }
}

$filename = $id . '_' . substr(bin2hex(random_bytes(6)), 0, 8) . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    errorResponse('ファイルの保存に失敗しました', 500);
}

// SVG はサーバ側で軽量サニタイズ（<script> 除去）
if ($ext === 'svg') {
    $svg = @file_get_contents($dest);
    if ($svg !== false) {
        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
        $svg = preg_replace('#\son\w+\s*=\s*"[^"]*"#i', '', $svg);
        $svg = preg_replace("#\son\w+\s*=\s*'[^']*'#i", '', $svg);
        @file_put_contents($dest, $svg);
    }
}

successResponse([
    'url' => '/uploads/product-icons/' . $filename,
], 'アップロード完了');
