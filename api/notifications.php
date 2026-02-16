<?php
/**
 * 通知API
 *
 * GET  ?action=list         - 通知一覧取得
 * GET  ?action=unread_count - 未読数取得
 * POST ?action=mark_read    - 既読にする
 * POST ?action=mark_all_read - すべて既読にする
 * POST ?action=create       - 通知作成（内部用）
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

// API初期化（GET/POST両方許可、CSRFはPOST時のみ）
initApi([
    'requireAuth' => true,
    'requireCsrf' => ($_SERVER['REQUEST_METHOD'] === 'POST'),
    'allowedMethods' => ['GET', 'POST']
]);

$data = getData();
if (!isset($data['notifications'])) {
    $data['notifications'] = [];
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';

/**
 * ユーザー向けの通知を取得
 */
function getUserNotifications($notifications, $userId, $userEmail, $limit = 20) {
    $userNotifications = [];

    foreach ($notifications as $n) {
        // 対象ユーザーチェック（全員向け or 特定ユーザー向け）
        $targetUser = $n['target_user'] ?? null;
        if ($targetUser !== null && $targetUser !== $userId && $targetUser !== $userEmail) {
            continue;
        }

        $userNotifications[] = $n;
    }

    // 作成日時の降順でソート
    usort($userNotifications, function($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    return array_slice($userNotifications, 0, $limit);
}

/**
 * 未読数を取得
 */
function getUnreadCount($notifications, $userId, $userEmail) {
    $count = 0;
    $readIds = $_SESSION['read_notification_ids'] ?? [];

    foreach ($notifications as $n) {
        // 対象ユーザーチェック
        $targetUser = $n['target_user'] ?? null;
        if ($targetUser !== null && $targetUser !== $userId && $targetUser !== $userEmail) {
            continue;
        }

        // 既読チェック
        if (!in_array($n['id'], $readIds)) {
            $count++;
        }
    }

    return $count;
}

switch ($action) {
    case 'list':
        $notifications = getUserNotifications($data['notifications'], $userId, $userEmail);
        $readIds = $_SESSION['read_notification_ids'] ?? [];

        // is_readフラグを追加
        $result = [];
        foreach ($notifications as $n) {
            $n['is_read'] = in_array($n['id'], $readIds);
            $result[] = $n;
        }

        $unreadCount = getUnreadCount($data['notifications'], $userId, $userEmail);

        successResponse([
            'notifications' => $result,
            'unread_count' => $unreadCount
        ]);
        break;

    case 'unread_count':
        $unreadCount = getUnreadCount($data['notifications'], $userId, $userEmail);
        successResponse([
            'unread_count' => $unreadCount
        ]);
        break;

    case 'mark_read':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $notificationId = $_GET['id'] ?? '';
        if (empty($notificationId)) {
            errorResponse('ID is required', 400);
        }

        // セッションに既読IDを保存
        if (!isset($_SESSION['read_notification_ids'])) {
            $_SESSION['read_notification_ids'] = [];
        }
        if (!in_array($notificationId, $_SESSION['read_notification_ids'])) {
            $_SESSION['read_notification_ids'][] = $notificationId;
        }

        successResponse(null);
        break;

    case 'mark_all_read':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        // 全通知IDを既読に
        $notifications = getUserNotifications($data['notifications'], $userId, $userEmail);
        $_SESSION['read_notification_ids'] = array_column($notifications, 'id');

        successResponse(null);
        break;

    case 'create':
        // 内部用：通知作成
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $input = getJsonInput();

        if (empty($input['message'])) {
            errorResponse('Message is required', 400);
        }

        $newNotification = [
            'id' => 'notif_' . uniqid(),
            'message' => $input['message'],
            'type' => $input['type'] ?? 'info', // info, warning, danger, success
            'link' => $input['link'] ?? '',
            'target_user' => $input['target_user'] ?? null, // null = 全員
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $userEmail
        ];

        $data['notifications'][] = $newNotification;

        // 古い通知を削除（最大100件）
        if (count($data['notifications']) > 100) {
            usort($data['notifications'], function($a, $b) {
                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
            });
            $data['notifications'] = array_slice($data['notifications'], 0, 100);
        }

        saveData($data);

        successResponse(['notification' => $newNotification]);
        break;

    default:
        errorResponse('Invalid action', 400);
}
