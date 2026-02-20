<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';

initApi(['requireAuth' => true, 'requireCsrf' => false, 'allowedMethods' => ['GET']]);

// プロジェクトデータの取得は編集権限以上に限定
if (!canEdit()) {
    errorResponse('権限がありません', 403);
}

$data = getData();
$projects = array_values(array_filter($data['projects'] ?? [], function($p) {
    return empty($p['deleted_at']);
}));

$statuses = ['案件発生', '成約', '製品手配中', '設置予定', '設置済', '完了'];

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'projects' => $projects,
        'statuses' => $statuses,
    ],
], JSON_UNESCAPED_UNICODE);
