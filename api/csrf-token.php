<?php
/**
 * CSRFトークン取得API
 * 認証済みユーザーにセッションのCSRFトークンをJSONで返す
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET'],
]);

$token = generateCsrfToken();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['token' => $token], JSON_UNESCAPED_UNICODE);
