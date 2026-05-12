<?php
/**
 * 週報添付ファイル配信
 * /api/serve-weekly-file.php?f=wr_XXXXXX.png
 */
require_once __DIR__ . '/../config/config.php';

$filename = basename($_GET['f'] ?? '');
// wr_<日付>_<時刻>_<ランダム>.<拡張子> を許可。アンダースコアも許可
if (empty($filename) || !preg_match('/^wr_[\w.-]+\.(jpg|jpeg|png|gif|webp|pdf|doc|docx|xls|xlsx|ppt|pptx)$/i', $filename)) {
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
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];
$ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=31536000');
readfile($path);
