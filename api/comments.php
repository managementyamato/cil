<?php
/**
 * コメント・メモAPI
 * 案件・トラブル・顧客などにコメントを追加・取得・削除する
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'rateLimit' => 100,
    'allowedMethods' => ['GET', 'POST', 'DELETE']
]);

$data = getData();

// コメントが存在しない場合は初期化
if (!isset($data['comments'])) {
    $data['comments'] = [];
}

// GET: コメント一覧取得
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $entityType = trim($_GET['entity_type'] ?? '');
    $entityId = trim($_GET['entity_id'] ?? '');

    if (empty($entityType) || empty($entityId)) {
        errorResponse('entity_type と entity_id は必須です', 400);
    }

    $comments = array_filter($data['comments'], function($c) use ($entityType, $entityId) {
        return ($c['entity_type'] ?? '') === $entityType
            && ($c['entity_id'] ?? '') === $entityId
            && empty($c['deleted_at']);
    });

    // 日時順にソート（新しい順）
    usort($comments, function($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    successResponse(['comments' => array_values($comments)]);
}

// POST: コメント追加
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
    requireParams($input, ['entity_type', 'entity_id', 'body']);

    $entityType = sanitizeInput($input['entity_type'], 'string');
    $entityId = sanitizeInput($input['entity_id'], 'string');
    $body = sanitizeInput($input['body'], 'string');

    if (empty($body)) {
        errorResponse('コメント本文は必須です', 400);
    }

    // 対象エンティティの存在チェック
    $validTypes = ['projects', 'troubles', 'customers', 'employees'];
    if (!in_array($entityType, $validTypes)) {
        errorResponse('無効なエンティティタイプです', 400);
    }

    $newComment = [
        'id' => uniqid('cmt_'),
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'body' => $body,
        'author_email' => $_SESSION['user_email'] ?? '',
        'author_name' => $_SESSION['user_name'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $data['comments'][] = $newComment;
    saveData($data);

    writeAuditLog('create', 'comments', 'コメントを追加: ' . mb_substr($body, 0, 50), [
        'entity_type' => $entityType,
        'entity_id' => $entityId,
    ]);

    successResponse(['comment' => $newComment], 'コメントを追加しました');
}

// DELETE: コメント削除
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = getJsonInput();
    $commentId = sanitizeInput($input['id'] ?? '', 'string');

    if (empty($commentId)) {
        errorResponse('コメントIDは必須です', 400);
    }

    $found = false;
    foreach ($data['comments'] as &$c) {
        if ($c['id'] === $commentId) {
            // 自分のコメントか管理者のみ削除可能
            if (($c['author_email'] ?? '') !== ($_SESSION['user_email'] ?? '') && !isAdmin()) {
                errorResponse('このコメントを削除する権限がありません', 403);
            }
            $c['deleted_at'] = date('Y-m-d H:i:s');
            $c['deleted_by'] = $_SESSION['user_email'] ?? '';
            $found = true;
            break;
        }
    }
    unset($c);

    if (!$found) {
        errorResponse('コメントが見つかりません', 404);
    }

    saveData($data);
    writeAuditLog('delete', 'comments', 'コメントを削除', ['comment_id' => $commentId]);

    successResponse([], 'コメントを削除しました');
}
