<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET']
]);

// 給与仕訳はクライアントサイドでExcel処理を行うページのため、
// APIからはページ表示に必要な基本情報のみ返す
$userRole = $_SESSION['user_role'] ?? 'sales';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'userRole' => $userRole,
        'canEdit' => canEdit(),
    ]
], JSON_UNESCAPED_UNICODE);
