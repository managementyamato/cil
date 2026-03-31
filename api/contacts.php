<?php
/**
 * 社内連絡先 API
 * GET    → 一覧取得（全ユーザー）
 * POST   action=create|update|delete → 管理部のみ
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['GET', 'POST'],
]);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 管理部向け: メール送信ログ
    if (isset($_GET['email_logs']) && isAdmin()) {
        $data = getData();
        $logs = $data['email_logs'] ?? [];
        // 新しい順
        usort($logs, fn($a, $b) => strcmp($b['sent_at'], $a['sent_at']));
        successResponse($logs);
    }

    // Google Chat URLからスペース名を取得
    if (isset($_GET['resolve_chat_url'])) {
        $url = trim($_GET['resolve_chat_url']);
        // URL から spaceId を抽出
        // 例: https://chat.google.com/room/XXXXX/YYYYY
        //     https://mail.google.com/mail/u/0/#chat/space/XXXXX
        //     https://chat.google.com/room/XXXXX
        if (preg_match('#[/\#](?:room|space|chat/space)/([A-Za-z0-9_-]+)#', $url, $m)) {
            $spaceId = 'spaces/' . $m[1];
        } else {
            errorResponse('Google ChatのURLを認識できません', 400);
        }
        require_once __DIR__ . '/google-chat.php';
        $chat = new GoogleChatClient();
        if (!$chat->isConfigured()) {
            errorResponse('Google Chat連携が設定されていません', 400);
        }
        $result = $chat->getSpaces();
        if ($result['error']) {
            errorResponse('スペース情報の取得に失敗: ' . $result['error'], 500);
        }
        $title = null;
        foreach ($result['spaces'] as $space) {
            if ($space['name'] === $spaceId) {
                $title = $space['displayName'];
                break;
            }
        }
        successResponse(['title' => $title ?? '', 'space_id' => $spaceId]);
    }

    $data  = getData();
    $rows  = array_values(array_filter($data['contacts'] ?? [], fn($r) => empty($r['deleted_at'])));
    usort($rows, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
    successResponse($rows);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = getJsonInput();
    $action = $input['action'] ?? '';

    // メール送信は全ユーザーが可能
    if ($action === 'send_email') {
        $to = trim($input['to'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $body = trim($input['body'] ?? '');
        if (!$to || !$subject) errorResponse('宛先と件名は必須です', 400);

        // カンマ区切りの複数アドレスを検証
        $recipients = array_filter(array_map('trim', explode(',', $to)));
        foreach ($recipients as $addr) {
            if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                errorResponse('メールアドレスが不正です: ' . $addr, 400);
            }
        }
        $toAddress = implode(', ', $recipients);

        require_once __DIR__ . '/google-gmail.php';
        $gmail = new GoogleGmailClient();
        if (!$gmail->isConfigured()) {
            errorResponse('Gmail連携が設定されていません。設定ページからGmail連携を行ってください。', 400);
        }

        try {
            $gmail->sendEmail($toAddress, $subject, $body);

            // 送信ログを記録
            $data = getData();
            if (!isset($data['email_logs'])) $data['email_logs'] = [];
            $data['email_logs'][] = [
                'id'        => uniqid('eml_'),
                'from'      => $_SESSION['user_email'],
                'to'        => $toAddress,
                'subject'   => $subject,
                'body'      => $body,
                'sent_at'   => date('Y-m-d H:i:s'),
            ];
            saveData($data);

            successResponse(null, '送信しました');
        } catch (Exception $e) {
            error_log('Gmail送信エラー: ' . $e->getMessage());
            errorResponse('メール送信に失敗しました: ' . $e->getMessage(), 500);
        }
    }

    // その他の操作は管理部のみ
    if (!isAdmin()) errorResponse('管理部のみ操作できます', 403);

    $data   = getData();
    if (!isset($data['contacts'])) $data['contacts'] = [];

    if ($action === 'create') {
        $fields = sanitize($input);
        if (!$fields['category'] || !$fields['scene'] || !$fields['dept']) {
            errorResponse('カテゴリ・場面・連絡先部署は必須です', 400);
        }
        $item = array_merge($fields, [
            'id'         => uniqid('c_'),
            'sort_order' => count($data['contacts']),
            'created_by' => $_SESSION['user_email'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $data['contacts'][] = $item;
        saveData($data);
        successResponse($item, '追加しました');
    }

    if ($action === 'update') {
        $id = $input['id'] ?? '';
        if (!$id) errorResponse('id が必要です', 400);
        $fields = sanitize($input);
        if (!$fields['category'] || !$fields['scene'] || !$fields['dept']) {
            errorResponse('カテゴリ・場面・連絡先部署は必須です', 400);
        }
        $found = false;
        foreach ($data['contacts'] as &$item) {
            if ($item['id'] === $id && empty($item['deleted_at'])) {
                foreach ($fields as $k => $v) $item[$k] = $v;
                $item['updated_at'] = date('Y-m-d H:i:s');
                $found   = true;
                $updated = $item;
                break;
            }
        }
        unset($item);
        if (!$found) errorResponse('対象が見つかりません', 404);
        saveData($data);
        successResponse($updated, '更新しました');
    }

    if ($action === 'bulk_update') {
        $items = $input['items'] ?? [];
        if (!is_array($items)) errorResponse('items が不正です', 400);
        foreach ($data['contacts'] as &$item) {
            if (empty($item['deleted_at'])) {
                foreach ($items as $upd) {
                    if (($upd['id'] ?? '') === $item['id']) {
                        $item['category'] = trim($upd['category'] ?? $item['category']);
                        $item['scene']    = trim($upd['scene']    ?? $item['scene']);
                        $item['dept']     = trim($upd['dept']     ?? $item['dept']);
                        $item['ext']      = trim($upd['ext']      ?? '');
                        $item['person']   = trim($upd['person']   ?? '');
                        $item['note']     = trim($upd['note']     ?? '');
                        $item['email']        = trim($upd['email']        ?? '');
                        $item['chat_room_id']    = trim($upd['chat_room_id']    ?? $item['chat_room_id']    ?? '');
                        $item['chat_room_title'] = trim($upd['chat_room_title'] ?? $item['chat_room_title'] ?? '');
                        $item['updated_at'] = date('Y-m-d H:i:s');
                        break;
                    }
                }
            }
        }
        unset($item);
        saveData($data);
        successResponse(null, '保存しました');
    }

    if ($action === 'delete') {
        $id = $input['id'] ?? '';
        if (!$id) errorResponse('id が必要です', 400);
        $found = false;
        foreach ($data['contacts'] as &$item) {
            if ($item['id'] === $id && empty($item['deleted_at'])) {
                $item['deleted_at'] = date('Y-m-d H:i:s');
                $item['deleted_by'] = $_SESSION['user_email'];
                $found = true;
                break;
            }
        }
        unset($item);
        if (!$found) errorResponse('対象が見つかりません', 404);
        saveData($data);
        successResponse(null, '削除しました');
    }

    errorResponse('action が不正です', 400);
}

function sanitize(array $input): array {
    return [
        'category'     => trim($input['category'] ?? ''),
        'scene'        => trim($input['scene']    ?? ''),
        'dept'         => trim($input['dept']     ?? ''),
        'ext'          => trim($input['ext']      ?? ''),
        'person'       => trim($input['person']   ?? ''),
        'note'         => trim($input['note']     ?? ''),
        'email'        => trim($input['email']    ?? ''),
        'chat_room_id'    => trim($input['chat_room_id'] ?? ''),
        'chat_room_title' => trim($input['chat_room_title'] ?? ''),
    ];
}

