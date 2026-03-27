<?php
/**
 * トラブル自動分類 API
 * POST: トラブル内容テキストを受け取りカテゴリ・優先度を返す
 *
 * リクエスト: {"text": "トラブル内容..."}
 * レスポンス: {"category": "映像系", "priority": "高", "confidence": 0.8, "source": "rule|ai|rule_fallback"}
 */
require_once '../config/config.php';
require_once '../functions/api-middleware.php';
require_once '../functions/trouble-classifier.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => false,  // JSON POST なので CSRF ヘッダーで保護
    'rateLimit'      => 60,     // 1 分に 60 回まで
    'allowedMethods' => ['POST'],
]);

$input = getJsonInput();

$text = sanitizeInput($input['text'] ?? '', 'string');
if (empty($text)) {
    errorResponse('テキストが必要です', 400);
}
if (mb_strlen($text) > 3000) {
    errorResponse('テキストが長すぎます（3000文字以内）', 400);
}

$result = TroubleClassifier::classify($text);

successResponse([
    'category'      => $result['category'],
    'priority'      => $result['priority'],
    'confidence'    => $result['confidence'],
    'source'        => $result['source'],
    'api_available' => TroubleClassifier::isApiConfigured(),
]);
