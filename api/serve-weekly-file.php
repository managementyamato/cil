<?php
/**
 * 週報添付ファイル配信
 * /api/serve-weekly-file.php?f=wr_XXXXXX.png
 */
require_once __DIR__ . '/../config/config.php';

$filename = basename($_GET['f'] ?? '');
if (empty($filename) || !preg_match('/^wr_\d{8}_[a-z0-9.]+\.(jpg|jpeg|png|gif|webp|pdf)$/i', $filename)) {
    http_response_code(400);
    exit;
}

$path = __DIR__ . '/../uploads/weekly-reports/' . $filename;
if (!file_exists($path)) {
    http_response_code(404);
    exit;
}

$mimeMap = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf',
];
$ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=31536000');
readfile($path);
