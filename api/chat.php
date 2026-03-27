<?php
/**
 * チャット API
 * グループチャット + DM（1対1）の CRUD
 *
 * GET:  list_rooms / get_messages / get_unread_count / poll
 * POST: send_message / create_room / create_dm / update_message / delete_message / mark_read
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/notification-functions.php';

// =========================================
// GETリクエスト（CSRF不要）
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    initApi([
        'requireAuth'    => true,
        'requireCsrf'    => false,
        'allowedMethods' => ['GET'],
    ]);

    $action      = $_GET['action'] ?? '';
    $data        = getData();
    $currentUser = $_SESSION['user_email'];

    switch ($action) {

        // ルーム一覧（未読数付き）
        case 'list_rooms':
            $rooms = ensureDefaultRoom($data);
            $accessible = array_values(array_filter($rooms, function($r) use ($currentUser) {
                return canAccessRoom($r, $currentUser);
            }));
            // 未読数を計算
            foreach ($accessible as &$room) {
                $room['unread_count'] = calcUnreadCount($data, $room['id'], $currentUser);
                // DMの場合は相手の名前をルーム名にする
                if (($room['type'] ?? '') === 'dm') {
                    $room['display_name'] = getDmDisplayName($room, $currentUser, $data);
                } else {
                    $room['display_name'] = $room['name'] ?? '(no name)';
                }
            }
            unset($room);
            successResponse(['rooms' => $accessible]);
            break;

        // メッセージ取得（降順で最新limit件 → クライアント側で昇順表示）
        case 'get_messages':
            $roomId = $_GET['room_id'] ?? '';
            $limit  = min((int)($_GET['limit'] ?? 50), 200);
            $before = $_GET['before'] ?? '';

            if (!$roomId) errorResponse('room_id は必須です', 400);

            $room = findRoom($data, $roomId);
            if (!$room || !canAccessRoom($room, $currentUser)) {
                errorResponse('ルームが見つかりません', 404);
            }

            $messages = getMessages($data, $roomId, $limit, $before);
            successResponse(['messages' => $messages]);
            break;

        // ポーリング: 指定日時以降の新着メッセージ
        case 'poll':
            $roomId = $_GET['room_id'] ?? '';
            $since  = $_GET['since'] ?? '';

            if (!$roomId) errorResponse('room_id は必須です', 400);

            $room = findRoom($data, $roomId);
            if (!$room || !canAccessRoom($room, $currentUser)) {
                errorResponse('ルームが見つかりません', 404);
            }

            $messages = pollMessages($data, $roomId, $since);
            successResponse(['messages' => $messages]);
            break;

        // 全ルームの未読数合計（バッジ用）
        case 'get_unread_count':
            $rooms = ensureDefaultRoom($data);
            $total = 0;
            foreach ($rooms as $room) {
                if (canAccessRoom($room, $currentUser)) {
                    $total += calcUnreadCount($data, $room['id'], $currentUser);
                }
            }
            successResponse(['total_unread' => $total]);
            break;

        // チャット相手として選べる従業員一覧（DM開始用）
        case 'get_employees':
            $employees = [];
            foreach ($data['employees'] ?? [] as $emp) {
                if (!empty($emp['deleted_at'])) continue;
                if (!empty($emp['leave_date']) && $emp['leave_date'] <= date('Y-m-d')) continue;

                $email = $emp['email'] ?? '';
                if (empty($email)) continue;

                // 暗号化済みemailを復号
                if (str_starts_with($email, 'enc:')) {
                    require_once __DIR__ . '/../functions/encryption.php';
                    try {
                        $email = decryptValue($email);
                    } catch (Exception $e) {
                        continue;
                    }
                }

                if ($email === $currentUser) continue; // 自分は除外

                $employees[] = [
                    'email' => $email,
                    'name'  => $emp['name'] ?? $email,
                ];
            }
            usort($employees, fn($a, $b) => strcmp($a['name'], $b['name']));
            successResponse(['employees' => $employees]);
            break;

        default:
            errorResponse('不明なアクション: ' . htmlspecialchars($action), 400);
    }
    exit;
}

// =========================================
// POSTリクエスト（CSRF必須）
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    initApi([
        'requireAuth'    => true,
        'requireCsrf'    => true,
        'allowedMethods' => ['POST'],
    ]);

    $input       = getJsonInput();
    $action      = $input['action'] ?? '';
    $data        = getData();
    $currentUser = $_SESSION['user_email'];
    $currentName = $_SESSION['user_name'] ?? $currentUser;

    switch ($action) {

        // メッセージ送信
        case 'send_message':
            requireParams($input, ['room_id', 'content']);
            $roomId  = sanitizeInput($input['room_id'], 'string');
            $content = sanitizeInput($input['content'], 'string');
            $content = mb_substr(trim($content), 0, 2000); // 最大2000文字

            if (empty($content)) errorResponse('メッセージ内容を入力してください', 400);

            $room = findRoom($data, $roomId);
            if (!$room || !canAccessRoom($room, $currentUser)) {
                errorResponse('ルームが見つかりません', 404);
            }

            // メンション（メールアドレスの配列）
            $mentions = [];
            if (!empty($input['mentions']) && is_array($input['mentions'])) {
                $mentions = validateMentionEmails($input['mentions'], $data);
            }

            $msgId = 'msg_' . uniqid('', true);
            $now   = date('Y-m-d H:i:s');
            $message = [
                'id'         => $msgId,
                'room_id'    => $roomId,
                'content'    => $content,
                'mentions'   => $mentions,
                'user_email' => $currentUser,
                'user_name'  => $currentName,
                'created_at' => $now,
                'deleted_at' => null,
                'deleted_by' => null,
            ];

            $data['chat_messages'][] = $message;

            // 既読状態を更新（送信者は既読扱い）
            updateReadStatus($data, $roomId, $currentUser, $now);

            saveData($data);

            // メンション通知メール
            foreach ($mentions as $mentionEmail) {
                if ($mentionEmail !== $currentUser) {
                    sendChatMentionNotification($mentionEmail, $currentName, $room['display_name'] ?? $room['name'] ?? 'チャット', $content);
                }
            }

            successResponse(['message' => $message], 'メッセージを送信しました');
            break;

        // グループルーム作成（admin専用）
        case 'create_room':
            if (!isAdmin()) errorResponse('グループルームの作成はadminのみ可能です', 403);
            requireParams($input, ['name']);
            $name        = mb_substr(trim(sanitizeInput($input['name'], 'string')), 0, 100);
            $description = mb_substr(trim(sanitizeInput($input['description'] ?? '', 'string')), 0, 300);
            $members     = [];
            if (!empty($input['members']) && is_array($input['members'])) {
                $members = validateMentionEmails($input['members'], $data);
            }

            if (empty($name)) errorResponse('ルーム名を入力してください', 400);

            $room = [
                'id'          => 'grp_' . uniqid('', true),
                'type'        => 'group',
                'name'        => $name,
                'description' => $description,
                'members'     => $members,
                'is_default'  => false,
                'created_by'  => $currentUser,
                'created_at'  => date('Y-m-d H:i:s'),
                'deleted_at'  => null,
            ];

            $data['chat_rooms'][] = $room;
            saveData($data);
            successResponse(['room' => $room], 'ルームを作成しました');
            break;

        // DM開始（相手ユーザーを指定してDMルームを取得/作成）
        case 'create_dm':
            requireParams($input, ['target_email']);
            $targetEmail = sanitizeInput($input['target_email'], 'string');

            if ($targetEmail === $currentUser) errorResponse('自分自身とはDMできません', 400);

            // DM用ルームID（メールアドレスをソートしてmd5）
            $emails = [$currentUser, $targetEmail];
            sort($emails);
            $dmId = 'dm_' . md5(implode('|', $emails));

            // 既存DM確認
            $existingRoom = findRoom($data, $dmId);
            if ($existingRoom && empty($existingRoom['deleted_at'])) {
                successResponse(['room' => $existingRoom]);
                break;
            }

            // 相手の名前を取得
            $targetName = getEmployeeName($targetEmail, $data);

            $room = [
                'id'          => $dmId,
                'type'        => 'dm',
                'name'        => null,
                'description' => '',
                'members'     => $emails,
                'is_default'  => false,
                'created_by'  => $currentUser,
                'created_at'  => date('Y-m-d H:i:s'),
                'deleted_at'  => null,
            ];

            $data['chat_rooms'][] = $room;
            saveData($data);
            successResponse(['room' => $room], 'DMを開始しました');
            break;

        // メッセージ削除（本人 or admin）
        // メッセージ編集（本人のみ）
        case 'update_message':
            requireParams($input, ['message_id', 'content']);
            $msgId      = sanitizeInput($input['message_id'], 'string');
            $newContent = trim($input['content'] ?? '');

            if ($newContent === '') errorResponse('内容が空です', 400);
            if (mb_strlen($newContent) > 3000) errorResponse('メッセージが長すぎます（3000文字以内）', 400);

            $msgIdx = null;
            foreach ($data['chat_messages'] as $i => $msg) {
                if (($msg['id'] ?? '') === $msgId) { $msgIdx = $i; break; }
            }
            if ($msgIdx === null) errorResponse('メッセージが見つかりません', 404);

            $msg = $data['chat_messages'][$msgIdx];
            if (!empty($msg['deleted_at'])) errorResponse('削除済みのメッセージは編集できません', 400);
            if (($msg['user_email'] ?? '') !== $currentUser && !isAdmin()) {
                errorResponse('このメッセージを編集する権限がありません', 403);
            }

            $data['chat_messages'][$msgIdx]['content']    = $newContent;
            $data['chat_messages'][$msgIdx]['updated_at'] = date('Y-m-d H:i:s');
            saveData($data);
            successResponse(['message' => $data['chat_messages'][$msgIdx]], 'メッセージを編集しました');
            break;

        case 'delete_message':
            requireParams($input, ['message_id']);
            $msgId = sanitizeInput($input['message_id'], 'string');

            $msgIdx = null;
            foreach ($data['chat_messages'] as $i => $msg) {
                if (($msg['id'] ?? '') === $msgId) { $msgIdx = $i; break; }
            }
            if ($msgIdx === null) errorResponse('メッセージが見つかりません', 404);

            $msg = $data['chat_messages'][$msgIdx];
            if (!empty($msg['deleted_at'])) errorResponse('既に削除されています', 400);

            if (($msg['user_email'] ?? '') !== $currentUser && !isAdmin()) {
                errorResponse('このメッセージを削除する権限がありません', 403);
            }

            $data['chat_messages'][$msgIdx]['deleted_at'] = date('Y-m-d H:i:s');
            $data['chat_messages'][$msgIdx]['deleted_by'] = $currentUser;
            saveData($data);
            successResponse([], 'メッセージを削除しました');
            break;

        // 既読更新
        case 'mark_read':
            requireParams($input, ['room_id']);
            $roomId = sanitizeInput($input['room_id'], 'string');

            $room = findRoom($data, $roomId);
            if (!$room || !canAccessRoom($room, $currentUser)) {
                errorResponse('ルームが見つかりません', 404);
            }

            updateReadStatus($data, $roomId, $currentUser, date('Y-m-d H:i:s'));
            saveData($data);
            successResponse([], '既読にしました');
            break;

        default:
            errorResponse('不明なアクション: ' . htmlspecialchars($action), 400);
    }
    exit;
}

errorResponse('許可されていないメソッドです', 405);

// =========================================
// ヘルパー関数
// =========================================

/**
 * デフォルトルーム（全体チャット）を確保して返す
 */
function ensureDefaultRoom(array &$data): array {
    $rooms = $data['chat_rooms'] ?? [];
    $hasDefault = false;
    foreach ($rooms as $r) {
        if (!empty($r['is_default']) && empty($r['deleted_at'])) {
            $hasDefault = true;
            break;
        }
    }
    if (!$hasDefault) {
        $defaultRoom = [
            'id'          => 'general',
            'type'        => 'group',
            'name'        => '全体チャット',
            'description' => '全員が参加するデフォルトチャンネル',
            'members'     => [],
            'is_default'  => true,
            'created_by'  => 'system',
            'created_at'  => date('Y-m-d H:i:s'),
            'deleted_at'  => null,
        ];
        $data['chat_rooms'][] = $defaultRoom;
        saveData($data);
    }
    return array_values(array_filter($data['chat_rooms'], fn($r) => empty($r['deleted_at'])));
}

/**
 * ルームを検索
 */
function findRoom(array $data, string $roomId): ?array {
    foreach ($data['chat_rooms'] ?? [] as $r) {
        if (($r['id'] ?? '') === $roomId) return $r;
    }
    return null;
}

/**
 * ルームにアクセスできるか
 */
function canAccessRoom(array $room, string $userEmail): bool {
    if (!empty($room['deleted_at'])) return false;
    $members = $room['members'] ?? [];
    if (empty($members)) return true; // 全員参加
    return in_array($userEmail, $members, true);
}

/**
 * DMルームの表示名（相手の名前）を返す
 */
function getDmDisplayName(array $room, string $currentUser, array $data): string {
    $members = $room['members'] ?? [];
    foreach ($members as $email) {
        if ($email !== $currentUser) {
            return getEmployeeName($email, $data);
        }
    }
    return 'DM';
}

/**
 * 従業員名を取得
 */
function getEmployeeName(string $email, array $data): string {
    foreach ($data['employees'] ?? [] as $emp) {
        $empEmail = $emp['email'] ?? '';
        if (str_starts_with($empEmail, 'enc:')) {
            require_once __DIR__ . '/../functions/encryption.php';
            try { $empEmail = decryptValue($empEmail); } catch (Exception $e) { continue; }
        }
        if ($empEmail === $email) return $emp['name'] ?? $email;
    }
    return $email;
}

/**
 * メッセージ取得（降順でlimit件）
 */
function getMessages(array $data, string $roomId, int $limit, string $before): array {
    $msgs = array_values(array_filter($data['chat_messages'] ?? [], fn($m) => ($m['room_id'] ?? '') === $roomId));
    if ($before) {
        $msgs = array_values(array_filter($msgs, fn($m) => ($m['created_at'] ?? '') < $before));
    }
    // 作成日降順
    usort($msgs, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    $msgs = array_slice($msgs, 0, $limit);
    // 昇順に戻して返す
    return array_reverse($msgs);
}

/**
 * ポーリング: since以降の新着メッセージ（昇順）
 */
function pollMessages(array $data, string $roomId, string $since): array {
    $msgs = array_values(array_filter($data['chat_messages'] ?? [], function($m) use ($roomId, $since) {
        if (($m['room_id'] ?? '') !== $roomId) return false;
        if ($since && ($m['created_at'] ?? '') <= $since) return false;
        return true;
    }));
    usort($msgs, fn($a, $b) => strcmp($a['created_at'] ?? '', $b['created_at'] ?? ''));
    return $msgs;
}

/**
 * 未読数を計算
 */
function calcUnreadCount(array $data, string $roomId, string $userEmail): int {
    $lastRead = null;
    foreach ($data['chat_read_status'] ?? [] as $rs) {
        if (($rs['user_email'] ?? '') === $userEmail && ($rs['room_id'] ?? '') === $roomId) {
            $lastRead = $rs['last_read_at'] ?? null;
            break;
        }
    }
    $count = 0;
    foreach ($data['chat_messages'] ?? [] as $msg) {
        if (($msg['room_id'] ?? '') !== $roomId) continue;
        if (!empty($msg['deleted_at'])) continue;
        if (($msg['user_email'] ?? '') === $userEmail) continue; // 自分のメッセージは未読にしない
        if ($lastRead && ($msg['created_at'] ?? '') <= $lastRead) continue;
        $count++;
    }
    return $count;
}

/**
 * 既読状態を更新
 */
function updateReadStatus(array &$data, string $roomId, string $userEmail, string $timestamp): void {
    foreach ($data['chat_read_status'] as &$rs) {
        if (($rs['user_email'] ?? '') === $userEmail && ($rs['room_id'] ?? '') === $roomId) {
            $rs['last_read_at'] = $timestamp;
            return;
        }
    }
    unset($rs);
    $data['chat_read_status'][] = [
        'user_email'  => $userEmail,
        'room_id'     => $roomId,
        'last_read_at' => $timestamp,
    ];
}

/**
 * メンションメールアドレスをバリデート
 */
function validateMentionEmails(array $submitted, array $data): array {
    $validEmails = [];
    foreach ($data['employees'] ?? [] as $emp) {
        if (!empty($emp['deleted_at'])) continue;
        if (!empty($emp['leave_date']) && $emp['leave_date'] < date('Y-m-d')) continue;
        $email = $emp['email'] ?? '';
        if (empty($email)) continue;
        if (str_starts_with($email, 'enc:')) {
            require_once __DIR__ . '/../functions/encryption.php';
            try { $email = decryptValue($email); } catch (Exception $e) { continue; }
        }
        $validEmails[] = $email;
    }
    return array_values(array_unique(array_filter($submitted, fn($e) => in_array($e, $validEmails, true))));
}

/**
 * メンション通知メールを送信
 */
function sendChatMentionNotification(string $to, string $senderName, string $roomName, string $content): void {
    $subject = '[Yamato Gear] チャットでメンションされました';
    $preview = mb_substr($content, 0, 100) . (mb_strlen($content) > 100 ? '...' : '');
    $body  = '<html><body style="font-family: sans-serif;">';
    $body .= '<p><strong>' . htmlspecialchars($senderName) . '</strong> さんが「' . htmlspecialchars($roomName) . '」でメンションしました:</p>';
    $body .= '<blockquote style="border-left: 3px solid #117a65; padding: 0.5rem 1rem; color: #374151;">';
    $body .= nl2br(htmlspecialchars($preview));
    $body .= '</blockquote>';
    $body .= '<p><a href="https://yamato-mgt.com/pages/chat.php">チャットを開く</a></p>';
    $body .= '</body></html>';
    sendNotificationEmail($to, $subject, $body);
}
