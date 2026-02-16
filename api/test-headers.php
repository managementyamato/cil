<?php
/**
 * ヘッダー確認用テストエンドポイント
 *
 * ブラウザの開発者ツールで Network > Headers を確認してください
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth' => false,
    'requireCsrf' => false,
    'rateLimit' => 1000
]);

successResponse([
    'message' => 'ヘッダーが送信されました。ブラウザの開発者ツール > Network > Headers > Response Headers を確認してください。',
    'expected_headers' => [
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "frame-ancestors 'self'"
    ]
]);
