<?php
/**
 * お知らせ掲示板 CRUD API
 *
 * GET    action=list          → お知らせ一覧（全ユーザー）
 * POST   action=create        → 新規作成（admin のみ）
 * POST   action=update        → 更新（admin のみ）
 * POST   action=delete        → 論理削除（admin のみ）
 * POST   action=read          → 既読マーク（全ユーザー）
 */
require_once '../config/config.php';
require_once '../functions/api-middleware.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['GET', 'POST'],
]);

$action = $_GET['action'] ?? ($_POST['action'] ?? (getJsonInput()['action'] ?? 'list'));

// ========== GET: 一覧取得 ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $data          = getData();
    $announcements = $data['announcements'] ?? [];
    $userEmail     = $_SESSION['user_email'];
    $now           = date('Y-m-d');

    // 削除済み・期限切れ除外
    $items = array_values(array_filter($announcements, function ($a) use ($now) {
        if (!empty($a['deleted_at'])) return false;
        if (!empty($a['expires_at']) && $a['expires_at'] < $now) return false;
        return true;
    }));

    // ピン留め優先 → 作成日降順
    usort($items, function ($a, $b) {
        $pinA = (bool)($a['pinned'] ?? false);
        $pinB = (bool)($b['pinned'] ?? false);
        if ($pinA !== $pinB) return $pinA ? -1 : 1;
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // 未読フラグ付与
    foreach ($items as &$item) {
        $item['is_read']    = in_array($userEmail, $item['read_by'] ?? [], true);
        $item['read_count'] = count($item['read_by'] ?? []);
    }
    unset($item);

    successResponse($items);
}

// ========== POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
    $action = $action ?: ($input['action'] ?? '');

    // --- 既読マーク（全ユーザー） ---
    if ($action === 'read') {
        $id        = sanitizeInput($input['id'] ?? '', 'string');
        $userEmail = $_SESSION['user_email'];

        $data = getData();
        $found = false;
        foreach ($data['announcements'] as &$item) {
            if ($item['id'] === $id && empty($item['deleted_at'])) {
                if (!in_array($userEmail, $item['read_by'] ?? [], true)) {
                    $item['read_by'][] = $userEmail;
                }
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) errorResponse('お知らせが見つかりません', 404);

        saveData($data);
        successResponse(null, '既読にしました');
    }

    // 以降は admin 専用
    if (!isAdmin()) {
        errorResponse('管理者権限が必要です', 403);
    }

    // --- 作成 ---
    if ($action === 'create') {
        requireParams($input, ['title', 'content']);

        $title   = mb_substr(sanitizeInput($input['title'], 'string'), 0, 100);
        $content = mb_substr(sanitizeInput($input['content'], 'string'), 0, 2000);
        $priority = in_array($input['priority'] ?? '', ['info', 'warning', 'urgent'])
            ? $input['priority'] : 'info';
        $pinned   = !empty($input['pinned']);
        $expiresAt = (!empty($input['expires_at']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['expires_at']))
            ? $input['expires_at'] : null;

        $newItem = [
            'id'         => uniqid('ann_'),
            'title'      => $title,
            'content'    => $content,
            'priority'   => $priority,
            'pinned'     => $pinned,
            'read_by'    => [],
            'expires_at' => $expiresAt,
            'created_by' => $_SESSION['user_email'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'deleted_at' => null,
            'deleted_by' => null,
        ];

        $data = getData();
        if (!isset($data['announcements'])) $data['announcements'] = [];
        $data['announcements'][] = $newItem;
        saveData($data);

        writeAuditLog('create', 'announcements', 'お知らせ作成: ' . $title, $newItem);
        successResponse($newItem, 'お知らせを作成しました');
    }

    // --- 更新 ---
    if ($action === 'update') {
        requireParams($input, ['id']);

        $id    = sanitizeInput($input['id'], 'string');
        $data  = getData();
        $found = false;

        foreach ($data['announcements'] as &$item) {
            if ($item['id'] !== $id || !empty($item['deleted_at'])) continue;

            if (isset($input['title']))   $item['title']   = mb_substr(sanitizeInput($input['title'], 'string'), 0, 100);
            if (isset($input['content'])) $item['content'] = mb_substr(sanitizeInput($input['content'], 'string'), 0, 2000);
            if (isset($input['priority']) && in_array($input['priority'], ['info', 'warning', 'urgent'])) {
                $item['priority'] = $input['priority'];
            }
            if (isset($input['pinned']))  $item['pinned']  = (bool)$input['pinned'];
            if (array_key_exists('expires_at', $input)) {
                $item['expires_at'] = (!empty($input['expires_at']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['expires_at']))
                    ? $input['expires_at'] : null;
            }
            $item['updated_at'] = date('Y-m-d H:i:s');
            $found = true;
            $updatedItem = $item;
            break;
        }
        unset($item);

        if (!$found) errorResponse('お知らせが見つかりません', 404);

        saveData($data);
        writeAuditLog('update', 'announcements', 'お知らせ更新', $updatedItem);
        successResponse($updatedItem, 'お知らせを更新しました');
    }

    // --- 削除（論理削除） ---
    if ($action === 'delete') {
        requireParams($input, ['id']);

        $id   = sanitizeInput($input['id'], 'string');
        $data = getData();
        $found = false;

        foreach ($data['announcements'] as &$item) {
            if ($item['id'] !== $id || !empty($item['deleted_at'])) continue;
            $deletedItem       = $item;
            $item['deleted_at'] = date('Y-m-d H:i:s');
            $item['deleted_by'] = $_SESSION['user_email'];
            $found = true;
            break;
        }
        unset($item);

        if (!$found) errorResponse('お知らせが見つかりません', 404);

        saveData($data);
        writeAuditLog('delete', 'announcements', 'お知らせ削除: ' . ($deletedItem['title'] ?? ''), $deletedItem);
        successResponse(null, 'お知らせを削除しました');
    }

    errorResponse('不明なアクションです', 400);
}
