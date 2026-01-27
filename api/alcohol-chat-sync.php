<?php
/**
 * アルコールチェック - Google Chat画像同期API
 * 指定スペースから画像を取得してアルコールチェックデータとして保存
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/google-chat.php';
require_once __DIR__ . '/../functions/photo-attendance-functions.php';

header('Content-Type: application/json');

// 権限チェック
if (!canEdit()) {
    echo json_encode(['success' => false, 'error' => '権限がありません']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Google Chatクライアント初期化
$chat = new GoogleChatClient();

if (!$chat->isConfigured()) {
    echo json_encode(['success' => false, 'error' => 'Google Chat連携が設定されていません']);
    exit;
}

switch ($action) {
    case 'get_spaces':
        // スペース一覧を取得
        $result = $chat->getSpaces();
        echo json_encode($result);
        break;

    case 'get_config':
        // 現在の設定を取得
        $config = getAlcoholChatConfig();
        echo json_encode(['success' => true, 'config' => $config]);
        break;

    case 'save_config':
        // スペース設定を保存
        $spaceId = $_POST['space_id'] ?? '';
        $spaceName = $_POST['space_name'] ?? '';

        if (empty($spaceId)) {
            echo json_encode(['success' => false, 'error' => 'スペースを選択してください']);
            exit;
        }

        $config = [
            'space_id' => $spaceId,
            'space_name' => $spaceName,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        saveAlcoholChatConfig($config);
        echo json_encode(['success' => true, 'message' => 'スペース設定を保存しました']);
        break;

    case 'sync_images':
        // 指定日の画像をChatから同期
        $date = $_POST['date'] ?? date('Y-m-d');
        $result = syncImagesFromChat($chat, $date);
        echo json_encode($result);
        break;

    case 'get_sync_status':
        // 同期状態を取得
        $date = $_GET['date'] ?? date('Y-m-d');
        $status = getSyncStatus($date);
        echo json_encode(['success' => true, 'status' => $status]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => '不正なアクションです']);
}

/**
 * アルコールチェック用Chat設定を取得
 */
function getAlcoholChatConfig() {
    $configFile = __DIR__ . '/../config/alcohol-chat-config.json';
    if (file_exists($configFile)) {
        return json_decode(file_get_contents($configFile), true) ?: [];
    }
    return [];
}

/**
 * アルコールチェック用Chat設定を保存
 */
function saveAlcoholChatConfig($config) {
    $configFile = __DIR__ . '/../config/alcohol-chat-config.json';
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * 同期状態を取得
 */
function getSyncStatus($date) {
    $allData = getPhotoAttendanceData();
    $chatImports = array_filter($allData, function($record) use ($date) {
        return $record['upload_date'] === $date &&
               isset($record['source']) && $record['source'] === 'chat';
    });

    return [
        'date' => $date,
        'imported_count' => count($chatImports),
        'last_sync' => getLastSyncTime($date)
    ];
}

/**
 * 最後の同期時刻を取得
 */
function getLastSyncTime($date) {
    $logFile = __DIR__ . '/../config/alcohol-sync-log.json';
    if (file_exists($logFile)) {
        $log = json_decode(file_get_contents($logFile), true) ?: [];
        return $log[$date] ?? null;
    }
    return null;
}

/**
 * 同期ログを保存
 */
function saveSyncLog($date) {
    $logFile = __DIR__ . '/../config/alcohol-sync-log.json';
    $log = [];
    if (file_exists($logFile)) {
        $log = json_decode(file_get_contents($logFile), true) ?: [];
    }
    $log[$date] = date('Y-m-d H:i:s');
    file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
}

/**
 * Chatから画像を同期
 */
function syncImagesFromChat($chat, $date) {
    $config = getAlcoholChatConfig();

    if (empty($config['space_id'])) {
        return ['success' => false, 'error' => '同期するスペースが設定されていません'];
    }

    $spaceId = $config['space_id'];

    // 対象日のメッセージを取得（ページネーションを使って全ページ走査）
    $messagesResult = $chat->getAllMessagesForDate($spaceId, $date, 100);

    if (!empty($messagesResult['error'])) {
        return ['success' => false, 'error' => $messagesResult['error']];
    }

    $messages = $messagesResult['messages'] ?? [];
    $imported = 0;
    $skipped = 0;
    $errors = [];
    $debugInfo = [];

    // 従業員一覧を取得（メールアドレスおよびChat User IDで照合用）
    $employees = getEmployees();
    $emailToEmployee = [];
    $chatUserIdToEmployee = [];
    foreach ($employees as $emp) {
        if (!empty($emp['email'])) {
            $emailToEmployee[strtolower($emp['email'])] = $emp;
        }
        if (!empty($emp['chat_user_id'])) {
            $chatUserIdToEmployee[$emp['chat_user_id']] = $emp;
        }
    }

    // 既にインポート済みのメッセージIDを取得
    $existingIds = getImportedMessageIds();

    // デバッグ: メッセージ数を記録
    $debugInfo['target_date'] = $date;
    $debugInfo['messages_found_for_date'] = count($messages);
    $debugInfo['pagination_info'] = $messagesResult['debug'] ?? [];
    $messagesOnDate = count($messages);

    // 最初の10件のメッセージの日付を記録（デバッグ用）
    $debugInfo['message_dates'] = [];
    $dateCheckCount = 0;
    foreach ($messages as $msg) {
        if ($dateCheckCount >= 10) break;
        $msgTime = $msg['createTime'] ?? '';
        if ($msgTime) {
            $debugInfo['message_dates'][] = [
                'raw' => $msgTime,
                'parsed' => date('Y-m-d H:i:s', strtotime($msgTime)),
                'date_only' => date('Y-m-d', strtotime($msgTime))
            ];
        }
        $dateCheckCount++;
    }

    $processedCount = 0;
    foreach ($messages as $message) {
        $processedCount++;
        $messageTime = $message['createTime'] ?? '';

        // 既にインポート済みならスキップ
        $messageId = $message['name'] ?? '';
        if (in_array($messageId, $existingIds)) {
            $skipped++;
            continue;
        }

        // デバッグ: メッセージ構造を記録（最初の5件のみ）
        if ($processedCount <= 5) {
            $sampleMsg = [
                'name' => $messageId,
                'createTime' => $messageTime,
                'has_attachment_field' => isset($message['attachment']),
                'has_attachments_field' => isset($message['attachments']),
                'attachment_count' => count($message['attachment'] ?? $message['attachments'] ?? []),
                'message_keys' => array_keys($message),
                'text_preview' => mb_substr($message['text'] ?? '', 0, 100)
            ];

            // 添付ファイルの詳細を記録
            if (!empty($message['attachment'])) {
                $sampleMsg['attachment_details'] = [];
                foreach ($message['attachment'] as $idx => $att) {
                    $sampleMsg['attachment_details'][] = [
                        'keys' => array_keys($att),
                        'name' => $att['name'] ?? 'N/A',
                        'contentType' => $att['contentType'] ?? 'N/A',
                        'contentName' => $att['contentName'] ?? 'N/A'
                    ];
                    if ($idx >= 2) break; // 最大3件
                }
            }

            $debugInfo['sample_messages'][] = $sampleMsg;
        }

        // 添付ファイルをチェック（複数のフィールド名に対応）
        // Google Chat APIでは 'attachment' フィールドに添付ファイル情報が含まれる
        $attachments = $message['attachment'] ?? $message['attachments'] ?? [];

        // attachmentが空の場合、メッセージから添付ファイルを個別に取得試行
        if (empty($attachments) && !empty($messageId)) {
            $attachmentResult = $chat->getMessageAttachments($messageId);
            if (empty($attachmentResult['error']) && !empty($attachmentResult['attachments'])) {
                $attachments = $attachmentResult['attachments'];
                $debugInfo['fetched_attachments_for'][] = $messageId;
            }
        }

        // cardsWithId内の画像もチェック
        if (empty($attachments) && isset($message['cardsV2'])) {
            // カード形式の画像は別途処理が必要
        }

        // slashCommand経由の画像もチェック
        if (empty($attachments) && isset($message['slashCommand'])) {
            continue;
        }

        if (empty($attachments)) {
            continue;
        }

        // 送信者情報を取得
        $sender = $message['sender'] ?? [];
        $senderUserId = '';  // users/123456789 形式
        $senderEmail = '';
        $senderName = '';

        if (isset($sender['name'])) {
            $senderUserId = $sender['name'];  // "users/123456789" 形式
            $senderName = $sender['displayName'] ?? '';
            // senderのemailは直接取れないことが多い
            $senderEmail = $sender['email'] ?? '';
        }

        // デバッグ用に送信者情報を記録
        if (count($debugInfo['sender_samples'] ?? []) < 5) {
            $debugInfo['sender_samples'][] = [
                'user_id' => $senderUserId,
                'display_name' => $senderName,
                'email' => $senderEmail,
                'sender_keys' => array_keys($sender)
            ];
        }

        // 画像添付ファイルのみ処理
        foreach ($attachments as $attachment) {
            // デバッグ: 添付ファイル構造を記録
            if (count($debugInfo['attachment_samples'] ?? []) < 3) {
                $debugInfo['attachment_samples'][] = [
                    'keys' => array_keys($attachment),
                    'contentType' => $attachment['contentType'] ?? 'N/A',
                    'has_attachmentDataRef' => isset($attachment['attachmentDataRef']),
                    'has_name' => isset($attachment['name']),
                    'has_source' => isset($attachment['source']),
                    'has_thumbnailUri' => isset($attachment['thumbnailUri']),
                    'has_downloadUri' => isset($attachment['downloadUri'])
                ];
            }

            $contentType = $attachment['contentType'] ?? '';
            if (strpos($contentType, 'image/') !== 0) {
                continue;
            }

            // 添付ファイルのダウンロード方法を決定
            // 優先順位: attachmentDataRef (Media API) > name > downloadUri
            $downloadResult = null;

            // attachmentDataRef.resourceNameがあればMedia APIでダウンロード（最も確実）
            if (isset($attachment['attachmentDataRef']['resourceName'])) {
                $resourceName = $attachment['attachmentDataRef']['resourceName'];
                $downloadResult = $chat->downloadFromMediaApi($resourceName);
                if (!$downloadResult['success']) {
                    $debugInfo['download_attempts'][] = [
                        'method' => 'attachmentDataRef (Media API)',
                        'resourceName' => $resourceName,
                        'error' => $downloadResult['error'] ?? 'Unknown error'
                    ];
                }
            }

            // 上記で失敗した場合、nameフィールドを試す
            if ((!$downloadResult || !$downloadResult['success']) && isset($attachment['name'])) {
                $downloadResult = $chat->downloadAttachment($attachment['name']);
                if (!$downloadResult['success']) {
                    $debugInfo['download_attempts'][] = [
                        'method' => 'name',
                        'attachmentName' => $attachment['name'],
                        'error' => $downloadResult['error'] ?? 'Unknown error'
                    ];
                }
            }

            // downloadUriがあれば直接ダウンロード（認証なしURL、最後の手段）
            if ((!$downloadResult || !$downloadResult['success']) && isset($attachment['downloadUri']) && !empty($attachment['downloadUri'])) {
                $downloadResult = $chat->downloadFromUrl($attachment['downloadUri']);
                if (!$downloadResult['success']) {
                    $debugInfo['download_attempts'][] = [
                        'method' => 'downloadUri',
                        'error' => $downloadResult['error'] ?? 'Unknown error'
                    ];
                }
            }

            if (!$downloadResult || !$downloadResult['success']) {
                $errors[] = "添付ファイルのダウンロードに失敗: " . ($downloadResult['error'] ?? '取得方法なし');
                continue;
            }
            if (!$downloadResult['success']) {
                $errors[] = "添付ファイルのダウンロードに失敗: {$attachmentName} - " . ($downloadResult['error'] ?? '');
                continue;
            }

            // 画像を保存（従業員リストを渡して自動紐付けを試みる）
            $saveResult = saveImportedImage(
                $downloadResult['data'],
                $downloadResult['contentType'],
                $date,
                $messageId,
                $senderUserId,
                $senderName,
                $senderEmail,
                $employees,
                $emailToEmployee,
                $chatUserIdToEmployee,
                $chat  // Chat APIクライアントを渡してユーザー情報取得に使用
            );

            if ($saveResult['success']) {
                $imported++;
            } else {
                $errors[] = $saveResult['error'];
            }
        }
    }

    // 同期ログを保存
    saveSyncLog($date);

    $debugInfo['messages_on_date'] = $messagesOnDate;

    return [
        'success' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
        'debug' => $debugInfo,
        'message' => "{$imported}件の画像をインポートしました" . ($skipped > 0 ? "（{$skipped}件はスキップ）" : '')
    ];
}

/**
 * インポート済みのメッセージIDを取得
 */
function getImportedMessageIds() {
    $allData = getPhotoAttendanceData();
    $ids = [];
    foreach ($allData as $record) {
        if (!empty($record['chat_message_id'])) {
            $ids[] = $record['chat_message_id'];
        }
    }
    return $ids;
}

/**
 * インポートした画像を保存
 * @param string $senderUserId Google Chat User ID (users/123456789 形式)
 * @param array $emailToEmployee メールアドレス => 従業員のマップ
 * @param array $chatUserIdToEmployee Chat User ID => 従業員のマップ
 * @param GoogleChatClient|null $chat Chat APIクライアント（ユーザー情報取得用）
 */
function saveImportedImage($imageData, $contentType, $date, $messageId, $senderUserId, $senderName, $senderEmail, $employees = [], $emailToEmployee = [], $chatUserIdToEmployee = [], $chat = null) {
    // ファイル拡張子を決定
    $extension = 'jpg';
    if (strpos($contentType, 'png') !== false) {
        $extension = 'png';
    } elseif (strpos($contentType, 'gif') !== false) {
        $extension = 'gif';
    }

    // 保存ディレクトリ
    $yearMonth = date('Y-m', strtotime($date));
    $uploadDir = __DIR__ . '/../functions/uploads/attendance-photos/' . $yearMonth . '/';

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // ファイル名生成
    $filename = sprintf(
        'chat_%s_%s.%s',
        $date,
        uniqid(),
        $extension
    );

    $filePath = $uploadDir . $filename;

    // 画像を保存
    if (file_put_contents($filePath, $imageData) === false) {
        return ['success' => false, 'error' => 'ファイルの保存に失敗しました'];
    }

    $employeeId = null;
    $autoLinkedByChatId = false;
    $autoLinkedByEmail = false;

    // 1. Chat User IDから従業員を照合（既に設定済みの場合）
    if (!empty($senderUserId) && isset($chatUserIdToEmployee[$senderUserId])) {
        $emp = $chatUserIdToEmployee[$senderUserId];
        $employeeId = $emp['id'] ?? null;
        $autoLinkedByChatId = true;
    }

    // 2. Chat User IDで照合できなかった場合、メールアドレスで照合を試みる
    if (!$employeeId && !empty($senderUserId) && $chat !== null) {
        // People APIでメールアドレスを取得
        $userInfo = $chat->getUserInfo($senderUserId);

        if (empty($userInfo['error']) && !empty($userInfo['email'])) {
            $senderEmail = $userInfo['email'];

            // メールアドレスで従業員を照合
            $emailLower = strtolower($senderEmail);
            if (isset($emailToEmployee[$emailLower])) {
                $emp = $emailToEmployee[$emailLower];
                $employeeId = $emp['id'] ?? null;
                $autoLinkedByEmail = true;

                // 従業員マスタにChat User IDを自動設定
                if ($employeeId && !empty($senderUserId)) {
                    updateEmployeeChatUserId($employeeId, $senderUserId);
                }
            }
        }
    }

    // 3. それでも照合できない場合、sender['email']があれば使用
    if (!$employeeId && !empty($senderEmail)) {
        $emailLower = strtolower($senderEmail);
        if (isset($emailToEmployee[$emailLower])) {
            $emp = $emailToEmployee[$emailLower];
            $employeeId = $emp['id'] ?? null;
            $autoLinkedByEmail = true;

            // 従業員マスタにChat User IDを自動設定
            if ($employeeId && !empty($senderUserId)) {
                updateEmployeeChatUserId($employeeId, $senderUserId);
            }
        }
    }

    // データベースに記録
    $allData = getPhotoAttendanceData();

    $newRecord = [
        'id' => uniqid(),
        'employee_id' => $employeeId,
        'upload_date' => $date,
        'upload_type' => $employeeId ? 'start' : 'chat_import', // 自動紐付け成功時は'start'、失敗時は'chat_import'
        'photo_path' => 'uploads/attendance-photos/' . $yearMonth . '/' . $filename,
        'uploaded_at' => date('Y-m-d H:i:s'),
        'source' => 'chat',
        'chat_message_id' => $messageId,
        'sender_user_id' => $senderUserId,
        'sender_name' => $senderName,
        'sender_email' => $senderEmail,
        'auto_assigned' => $employeeId ? true : false,
        'auto_linked_method' => $autoLinkedByChatId ? 'chat_user_id' : ($autoLinkedByEmail ? 'email' : null)
    ];

    $allData[] = $newRecord;
    savePhotoAttendanceData($allData);

    return ['success' => true, 'record' => $newRecord];
}
