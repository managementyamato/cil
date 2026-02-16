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

// POST時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
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
        // JSONまたはPOSTデータから取得
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $spaceId = $input['space_id'] ?? $_POST['space_id'] ?? '';
        $spaceName = $input['space_name'] ?? $_POST['space_name'] ?? '';

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

    case 're_match':
        // 既にインポート済みのレコードに対して従業員照合を再実行
        // 画像の再ダウンロードは行わず、メールベースの照合ロジックのみ再実行
        $date = $_POST['date'] ?? date('Y-m-d');
        $result = reMatchEmployees($chat, $date);
        echo json_encode($result);
        break;

    case 'get_cron_config':
        // cron設定を取得
        $cronConfig = getCronConfig();
        echo json_encode(['success' => true, 'config' => $cronConfig]);
        break;

    case 'save_cron_config':
        // cron設定を保存（管理者のみ）
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'error' => '管理者権限が必要です']);
            exit;
        }
        $secretKey = $_POST['secret_key'] ?? '';
        $cronConfig = [
            'secret_key' => $secretKey,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        saveCronConfig($cronConfig);
        echo json_encode(['success' => true, 'message' => 'cron設定を保存しました']);
        break;

    case 'lookup_user':
        // メールアドレスからChat User IDを検索
        $email = $_GET['email'] ?? '';
        if (empty($email)) {
            echo json_encode(['success' => false, 'error' => 'メールアドレスを指定してください']);
            exit;
        }

        // アルコールチェック用スペースからメンバー一覧を取得
        $config = getAlcoholChatConfig();
        if (empty($config['space_id'])) {
            echo json_encode(['success' => false, 'error' => 'アルコールチェック用スペースが設定されていません。設定画面で同期元スペースを設定してください。']);
            exit;
        }

        $membersMap = $chat->getSpaceMembersMap($config['space_id']);
        $membersError = $chat->lastMembersError;

        // メールアドレスで検索
        $foundUserId = null;
        $emailLower = strtolower($email);
        foreach ($membersMap as $userId => $memberInfo) {
            if (isset($memberInfo['email']) && strtolower($memberInfo['email']) === $emailLower) {
                $foundUserId = $userId;
                break;
            }
        }

        if ($foundUserId) {
            echo json_encode(['success' => true, 'user_id' => $foundUserId]);
        } else {
            // メンバー一覧に居ない場合、既存のインポートデータから検索
            $allData = getPhotoAttendanceData();
            foreach ($allData as $record) {
                if (!empty($record['sender_email']) && strtolower($record['sender_email']) === $emailLower && !empty($record['sender_user_id'])) {
                    echo json_encode(['success' => true, 'user_id' => $record['sender_user_id']]);
                    exit;
                }
            }

            // エラーメッセージを構築
            $errorMsg = '該当するユーザーが見つかりませんでした。';
            if ($membersError) {
                $errorMsg .= ' (API: ' . $membersError . ')';
            } elseif (empty($membersMap)) {
                $errorMsg .= ' (メンバー一覧が取得できませんでした。Chat連携を再設定してください)';
            } else {
                $errorMsg .= ' アルコールチェック画面でChat同期を実行してから再試行してください。';
            }
            echo json_encode(['success' => false, 'error' => $errorMsg]);
        }
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
 * cron設定を取得
 */
function getCronConfig() {
    $configFile = __DIR__ . '/../config/cron-config.json';
    if (file_exists($configFile)) {
        return json_decode(file_get_contents($configFile), true) ?: [];
    }
    return [];
}

/**
 * cron設定を保存
 */
function saveCronConfig($config) {
    $configFile = __DIR__ . '/../config/cron-config.json';
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

    // スペースメンバー一覧を取得（メールアドレスの自動取得に使用）
    // API権限不足の場合は空配列が返り、他の照合方式にフォールバック
    $membersMap = $chat->getSpaceMembersMap($spaceId);

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
                $chat,  // Chat APIクライアントを渡してユーザー情報取得に使用
                $membersMap  // スペースメンバーマップ
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
 * 既存レコードの従業員照合を再実行（テスト・修正用）
 * Chatからメッセージを再取得してsender.displayNameを取得し、従業員名で照合する
 */
function reMatchEmployees($chat, $date) {
    $allData = getPhotoAttendanceData();
    $config = getAlcoholChatConfig();
    $spaceId = $config['space_id'] ?? '';

    // 従業員一覧を取得
    $employees = getEmployees();
    $nameToEmployee = [];  // 表示名→従業員マッピング
    $emailToEmployee = [];
    $chatUserIdToEmployee = [];
    foreach ($employees as $emp) {
        if (!empty($emp['name'])) {
            $nameToEmployee[$emp['name']] = $emp;
        }
        if (!empty($emp['email'])) {
            $emailToEmployee[strtolower($emp['email'])] = $emp;
        }
        if (!empty($emp['chat_user_id'])) {
            $chatUserIdToEmployee[$emp['chat_user_id']] = $emp;
        }
    }

    // Chatからメッセージを再取得して messageId → sender情報 のマッピングを構築
    $senderMap = [];  // chat_message_id → ['name' => '...', 'userId' => '...', 'email' => '...']
    $msgDebug = [];
    if (!empty($spaceId) && $chat !== null) {
        $messagesResult = $chat->getAllMessagesForDate($spaceId, $date, 100);
        $messages = $messagesResult['messages'] ?? [];
        $msgDebug['messages_fetched'] = count($messages);
        $msgDebug['api_error'] = $messagesResult['error'] ?? null;

        foreach ($messages as $msg) {
            $msgId = $msg['name'] ?? '';
            $sender = $msg['sender'] ?? [];
            $senderMap[$msgId] = [
                'name' => $sender['displayName'] ?? '',
                'userId' => $sender['name'] ?? '',
                'email' => $sender['email'] ?? '',
                'type' => $sender['type'] ?? ''
            ];
        }
        $msgDebug['sender_map_count'] = count($senderMap);
        // デバッグ用サンプル（最初の3件）
        $msgDebug['sender_samples'] = array_slice($senderMap, 0, 3, true);
    }

    $updated = 0;
    $alreadyMatched = 0;
    $noMatch = 0;
    $details = [];

    foreach ($allData as &$record) {
        // 対象日のChatインポートレコードのみ
        if ($record['upload_date'] !== $date || ($record['source'] ?? '') !== 'chat') {
            continue;
        }

        $chatMessageId = $record['chat_message_id'] ?? '';
        $senderUserId = $record['sender_user_id'] ?? '';
        $oldEmployeeId = $record['employee_id'] ?? null;
        $newEmployeeId = null;
        $matchMethod = null;

        // メッセージから最新のsender情報を取得
        $senderInfo = $senderMap[$chatMessageId] ?? null;
        $senderName = $senderInfo['name'] ?? ($record['sender_name'] ?? '');
        $senderEmail = $senderInfo['email'] ?? ($record['sender_email'] ?? '');

        // 0. chat_user_idで照合（最優先）
        if (!$newEmployeeId && !empty($senderUserId) && isset($chatUserIdToEmployee[$senderUserId])) {
            $emp = $chatUserIdToEmployee[$senderUserId];
            $newEmployeeId = $emp['id'] ?? null;
            $matchMethod = 'chat_user_id';
        }

        // 1. メールアドレスで照合
        if (!$newEmployeeId && !empty($senderEmail)) {
            $emailLower = strtolower($senderEmail);
            if (isset($emailToEmployee[$emailLower])) {
                $emp = $emailToEmployee[$emailLower];
                $newEmployeeId = $emp['id'] ?? null;
                $matchMethod = 'email';
            }
        }

        // 2. 表示名で照合（フォールバック）
        if (!$newEmployeeId && !empty($senderName)) {
            if (isset($nameToEmployee[$senderName])) {
                $emp = $nameToEmployee[$senderName];
                $newEmployeeId = $emp['id'] ?? null;
                $matchMethod = 'display_name';
            }
        }

        // chat_user_id自動登録: メール/表示名でマッチした場合、chat_user_idを従業員マスタに保存
        if ($newEmployeeId && !empty($senderUserId) && $matchMethod !== 'chat_user_id') {
            updateEmployeeChatUserId($newEmployeeId, $senderUserId);
        }

        $detail = [
            'chat_message_id' => $chatMessageId,
            'sender_user_id' => $senderUserId,
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'sender_from_api' => $senderInfo !== null,
            'old_employee_id' => $oldEmployeeId,
            'new_employee_id' => $newEmployeeId,
            'method' => $matchMethod
        ];

        if ($newEmployeeId && $newEmployeeId !== $oldEmployeeId) {
            $record['employee_id'] = $newEmployeeId;
            $record['sender_name'] = $senderName;
            $record['sender_email'] = $senderEmail;
            $record['auto_assigned'] = true;
            $record['auto_linked_method'] = $matchMethod;
            $detail['status'] = 'updated';
            $updated++;
        } elseif ($newEmployeeId && $newEmployeeId === $oldEmployeeId) {
            // sender情報を最新に更新
            $record['sender_name'] = $senderName;
            $record['sender_email'] = $senderEmail;
            $detail['status'] = 'already_matched';
            $alreadyMatched++;
        } else {
            $detail['status'] = 'no_match';
            $noMatch++;
        }

        $details[] = $detail;
    }
    unset($record);

    // 1回目/2回目の自動判定: 同じ従業員の対象日レコードを時系列で並べて判定
    $employeeRecords = []; // employee_id => [index1, index2, ...]
    foreach ($allData as $idx => &$rec) {
        if (($rec['upload_date'] ?? '') === $date && ($rec['source'] ?? '') === 'chat' && !empty($rec['employee_id'])) {
            $employeeRecords[$rec['employee_id']][] = $idx;
        }
    }
    unset($rec);

    foreach ($employeeRecords as $empId => $indices) {
        // 時系列順にソート（uploaded_atまたはchat_message_idで）
        usort($indices, function($a, $b) use ($allData) {
            $timeA = $allData[$a]['uploaded_at'] ?? '';
            $timeB = $allData[$b]['uploaded_at'] ?? '';
            return strcmp($timeA, $timeB);
        });
        // 1件目=start, 2件目=end
        foreach ($indices as $order => $idx) {
            $allData[$idx]['upload_type'] = ($order === 0) ? 'start' : (($order === 1) ? 'end' : 'start');
        }
    }

    // 変更があればデータを保存（sender情報の更新も含む）
    if ($updated > 0 || $alreadyMatched > 0) {
        savePhotoAttendanceData($allData);
    }

    return [
        'success' => true,
        'updated' => $updated,
        'already_matched' => $alreadyMatched,
        'no_match' => $noMatch,
        'details' => $details,
        'debug' => [
            'space_id' => $spaceId,
            'messages' => $msgDebug,
            'employee_names' => array_keys($nameToEmployee),
            'employee_emails' => array_keys($emailToEmployee)
        ],
        'message' => "{$updated}件の照合を更新しました（既にマッチ済み: {$alreadyMatched}件、未マッチ: {$noMatch}件）"
    ];
}

/**
 * インポートした画像を保存
 * @param string $senderUserId Google Chat User ID (users/123456789 形式)
 * @param array $emailToEmployee メールアドレス => 従業員のマップ
 * @param array $chatUserIdToEmployee Chat User ID => 従業員のマップ
 * @param GoogleChatClient|null $chat Chat APIクライアント（ユーザー情報取得用）
 */
function saveImportedImage($imageData, $contentType, $date, $messageId, $senderUserId, $senderName, $senderEmail, $employees = [], $emailToEmployee = [], $chatUserIdToEmployee = [], $chat = null, $membersMap = []) {
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
    $autoLinkedMethod = null;

    // ===== 従業員自動照合（優先順位順） =====

    // 0. chat_user_idで照合（最優先・最も確実）
    // 一度メールで紐づいたchat_user_idは従業員マスタに保存されるため、以降は即座にマッチ
    if (!$employeeId && !empty($senderUserId) && isset($chatUserIdToEmployee[$senderUserId])) {
        $emp = $chatUserIdToEmployee[$senderUserId];
        $employeeId = $emp['id'] ?? null;
        $autoLinkedMethod = 'chat_user_id';
    }

    // 1. スペースメンバー一覧からメールアドレスを取得して照合
    if (!$employeeId && !empty($senderUserId) && isset($membersMap[$senderUserId])) {
        $memberInfo = $membersMap[$senderUserId];
        if (!empty($memberInfo['email'])) {
            $senderEmail = $memberInfo['email'];
            $senderName = $memberInfo['name'] ?? $senderName;
            $emailLower = strtolower($senderEmail);
            if (isset($emailToEmployee[$emailLower])) {
                $emp = $emailToEmployee[$emailLower];
                $employeeId = $emp['id'] ?? null;
                $autoLinkedMethod = 'members_api';
            }
        }
    }

    // 1b. People APIフォールバック（スペースメンバーに居ない場合）
    if (!$employeeId && !empty($senderUserId) && $chat !== null && !isset($membersMap[$senderUserId])) {
        $userInfo = $chat->getUserInfo($senderUserId);

        if (empty($userInfo['error']) && !empty($userInfo['email'])) {
            $senderEmail = $userInfo['email'];

            $emailLower = strtolower($senderEmail);
            if (isset($emailToEmployee[$emailLower])) {
                $emp = $emailToEmployee[$emailLower];
                $employeeId = $emp['id'] ?? null;
                $autoLinkedMethod = 'email_api';
            }
        }
    }

    // 2. sender.emailが直接取れた場合（APIフォールバック）
    if (!$employeeId && !empty($senderEmail)) {
        $emailLower = strtolower($senderEmail);
        if (isset($emailToEmployee[$emailLower])) {
            $emp = $emailToEmployee[$emailLower];
            $employeeId = $emp['id'] ?? null;
            $autoLinkedMethod = 'email_direct';
        }
    }

    // 3. 表示名で照合（メールが取れない場合のフォールバック）
    if (!$employeeId && !empty($senderName)) {
        foreach ($employees as $emp) {
            $empName = $emp['name'] ?? '';
            if (!empty($empName) && $empName === $senderName) {
                $employeeId = $emp['id'] ?? null;
                $autoLinkedMethod = 'display_name';
                break;
            }
        }
    }

    // ===== chat_user_id 自動登録 =====
    // メールや表示名でマッチした場合、chat_user_idを従業員マスタに自動保存
    // 次回以降はchat_user_idで即座にマッチ可能になる
    if ($employeeId && !empty($senderUserId) && $autoLinkedMethod !== 'chat_user_id') {
        updateEmployeeChatUserId($employeeId, $senderUserId);
    }

    // データベースに記録
    $allData = getPhotoAttendanceData();

    // 1回目/2回目の自動判定: 同じ従業員・同じ日付の既存レコード数で判定
    $uploadType = 'chat_import'; // デフォルト（従業員未特定時）
    if ($employeeId) {
        $existingCount = 0;
        foreach ($allData as $existing) {
            if (($existing['employee_id'] ?? '') === $employeeId &&
                ($existing['upload_date'] ?? '') === $date &&
                ($existing['source'] ?? '') === 'chat') {
                $existingCount++;
            }
        }
        // 0件=1回目(start), 1件=2回目(end), 2件以上=追加(start)
        $uploadType = ($existingCount === 1) ? 'end' : 'start';
    }

    $newRecord = [
        'id' => uniqid(),
        'employee_id' => $employeeId,
        'upload_date' => $date,
        'upload_type' => $uploadType,
        'photo_path' => 'uploads/attendance-photos/' . $yearMonth . '/' . $filename,
        'uploaded_at' => date('Y-m-d H:i:s'),
        'source' => 'chat',
        'chat_message_id' => $messageId,
        'sender_user_id' => $senderUserId,
        'sender_name' => $senderName,
        'sender_email' => $senderEmail,
        'auto_assigned' => $employeeId ? true : false,
        'auto_linked_method' => $autoLinkedMethod
    ];

    $allData[] = $newRecord;
    savePhotoAttendanceData($allData);

    return ['success' => true, 'record' => $newRecord];
}
