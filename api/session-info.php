<?php
/**
 * セッション情報取得API
 * 現在のログイン状態とユーザー情報をJSONで返す
 * 未認証の場合は { "authenticated": false } を返す（401ではない）
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth' => false,
    'requireCsrf' => false,
    'allowedMethods' => ['GET'],
]);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_email'])) {
    echo json_encode(['authenticated' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'user' => [
        'email' => $_SESSION['user_email'],
        'name'  => $_SESSION['user_name'] ?? '',
        'role'  => $_SESSION['user_role'] ?? 'sales',
    ],
], JSON_UNESCAPED_UNICODE);
