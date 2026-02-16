<?php
/**
 * アルコールチェック自動同期 - Cron/タスクスケジューラ用スクリプト
 *
 * 当日のGoogle Chat画像を自動で同期し、従業員に割り当てる。
 *
 * 使い方:
 *   php scripts/cron-alcohol-sync.php
 *   php scripts/cron-alcohol-sync.php --date=2026-01-28
 *   php scripts/cron-alcohol-sync.php --secret=YOUR_SECRET_KEY
 *
 * cron設定例（毎日8:00, 10:00, 12:00, 18:00, 20:00に実行）:
 *   0 8,10,12,18,20 * * * php /path/to/scripts/cron-alcohol-sync.php --secret=YOUR_SECRET_KEY
 *
 * Web経由で実行する場合（外部cronサービス等）:
 *   curl "https://example.com/scripts/cron-alcohol-sync.php?secret=YOUR_SECRET_KEY"
 */

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// CLI実行かWeb実行かを判定
$isCli = (php_sapi_name() === 'cli');

// ログ出力関数
function cronLog($message) {
    global $isCli, $logMessages;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$message}";
    $logMessages[] = $line;
    if ($isCli) {
        echo $line . PHP_EOL;
    }
}

$logMessages = [];

// --- 認証チェック ---
$configDir = dirname(__DIR__) . '/config';
$cronConfigFile = $configDir . '/cron-config.json';

// cron設定を読み込み
$cronConfig = [];
if (file_exists($cronConfigFile)) {
    $cronConfig = json_decode(file_get_contents($cronConfigFile), true) ?: [];
}

// シークレットキーの検証（設定されている場合）
$configSecret = $cronConfig['secret_key'] ?? '';
if (!empty($configSecret)) {
    $providedSecret = '';
    if ($isCli) {
        // CLIの場合: --secret=xxx 引数から取得
        foreach ($argv ?? [] as $arg) {
            if (strpos($arg, '--secret=') === 0) {
                $providedSecret = substr($arg, strlen('--secret='));
            }
        }
    } else {
        // Web経由の場合: ?secret=xxx クエリパラメータから取得
        $providedSecret = $_GET['secret'] ?? '';
    }

    if (!hash_equals($configSecret, $providedSecret)) {
        $msg = 'Authentication failed: invalid secret key';
        if ($isCli) {
            echo $msg . PHP_EOL;
            exit(1);
        } else {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => $msg]);
            exit;
        }
    }
}

// --- セッション不要のモック設定 ---
// auth.phpのセッション依存をバイパス
if (!session_id()) {
    session_start();
}
$_SESSION['user_email'] = 'cron@system';
$_SESSION['user_role'] = 'admin';

// --- 必要なファイルを読み込み ---
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/api/google-chat.php';
require_once dirname(__DIR__) . '/functions/photo-attendance-functions.php';

// alcohol-chat-sync.phpの関数を読み込み（switchブロックは実行しない）
// 直接関数を定義する代わりに、必要な関数をインクルード
$syncFile = dirname(__DIR__) . '/api/alcohol-chat-sync.php';

// alcohol-chat-sync.phpの関数を手動で利用するため、関数定義部分のみ抽出
// → 直接HTTPリクエストを内部的に発行する方式を採用
cronLog('=== アルコールチェック自動同期開始 ===');

// 対象日を決定
$targetDate = date('Y-m-d');
if ($isCli) {
    foreach ($argv ?? [] as $arg) {
        if (strpos($arg, '--date=') === 0) {
            $targetDate = substr($arg, strlen('--date='));
        }
    }
} else {
    if (!empty($_GET['date'])) {
        $targetDate = $_GET['date'];
    }
}

cronLog("対象日: {$targetDate}");

// --- Google Chatクライアント初期化 ---
$chat = new GoogleChatClient();
if (!$chat->isConfigured()) {
    cronLog('エラー: Google Chat連携が設定されていません');
    outputResult(false, 'Google Chat連携が設定されていません');
    exit(1);
}

// --- Chat設定を読み込み ---
$alcoholConfigFile = $configDir . '/alcohol-chat-config.json';
if (!file_exists($alcoholConfigFile)) {
    cronLog('エラー: アルコールチェック用Chat設定が見つかりません');
    outputResult(false, 'アルコールチェック用Chat設定が見つかりません');
    exit(1);
}
$alcoholConfig = json_decode(file_get_contents($alcoholConfigFile), true) ?: [];
$spaceId = $alcoholConfig['space_id'] ?? '';

if (empty($spaceId)) {
    cronLog('エラー: 同期するスペースが設定されていません');
    outputResult(false, '同期するスペースが設定されていません');
    exit(1);
}

cronLog("スペース: " . ($alcoholConfig['space_name'] ?? $spaceId));

// --- メッセージ取得 ---
cronLog('Chatメッセージを取得中...');
$messagesResult = $chat->getAllMessagesForDate($spaceId, $targetDate, 100);

if (!empty($messagesResult['error'])) {
    cronLog('エラー: ' . $messagesResult['error']);
    outputResult(false, $messagesResult['error']);
    exit(1);
}

$messages = $messagesResult['messages'] ?? [];
cronLog("メッセージ数: " . count($messages));

// --- 従業員マップ構築 ---
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
cronLog("従業員数: " . count($employees) . " (メール登録: " . count($emailToEmployee) . ", chat_user_id登録: " . count($chatUserIdToEmployee) . ")");

// --- スペースメンバー取得 ---
$membersMap = $chat->getSpaceMembersMap($spaceId);
cronLog("スペースメンバー数: " . count($membersMap));

// --- インポート済みメッセージID ---
$allData = getPhotoAttendanceData();
$existingIds = [];
foreach ($allData as $record) {
    if (!empty($record['chat_message_id'])) {
        $existingIds[] = $record['chat_message_id'];
    }
}

// --- メッセージ処理 ---
$imported = 0;
$skipped = 0;
$errors = [];

foreach ($messages as $message) {
    $messageId = $message['name'] ?? '';

    // 既にインポート済みならスキップ
    if (in_array($messageId, $existingIds)) {
        $skipped++;
        continue;
    }

    // 添付ファイルをチェック
    $attachments = $message['attachment'] ?? $message['attachments'] ?? [];

    if (empty($attachments) && !empty($messageId)) {
        $attachmentResult = $chat->getMessageAttachments($messageId);
        if (empty($attachmentResult['error']) && !empty($attachmentResult['attachments'])) {
            $attachments = $attachmentResult['attachments'];
        }
    }

    if (empty($attachments)) {
        continue;
    }

    // 送信者情報
    $sender = $message['sender'] ?? [];
    $senderUserId = $sender['name'] ?? '';
    $senderName = $sender['displayName'] ?? '';
    $senderEmail = $sender['email'] ?? '';

    // 画像添付のみ処理
    foreach ($attachments as $attachment) {
        $contentType = $attachment['contentType'] ?? '';
        if (strpos($contentType, 'image/') !== 0) {
            continue;
        }

        // ダウンロード
        $downloadResult = null;

        if (isset($attachment['attachmentDataRef']['resourceName'])) {
            $downloadResult = $chat->downloadFromMediaApi($attachment['attachmentDataRef']['resourceName']);
        }

        if ((!$downloadResult || !$downloadResult['success']) && isset($attachment['name'])) {
            $downloadResult = $chat->downloadAttachment($attachment['name']);
        }

        if ((!$downloadResult || !$downloadResult['success']) && !empty($attachment['downloadUri'])) {
            $downloadResult = $chat->downloadFromUrl($attachment['downloadUri']);
        }

        if (!$downloadResult || !$downloadResult['success']) {
            $errors[] = "ダウンロード失敗: " . ($downloadResult['error'] ?? '取得方法なし');
            continue;
        }

        // 画像を保存・従業員照合
        // saveImportedImage関数を直接呼ぶ（alcohol-chat-sync.phpと同じロジック）
        $saveResult = saveImportedImageForCron(
            $downloadResult['data'],
            $downloadResult['contentType'],
            $targetDate,
            $messageId,
            $senderUserId,
            $senderName,
            $senderEmail,
            $employees,
            $emailToEmployee,
            $chatUserIdToEmployee,
            $chat,
            $membersMap
        );

        if ($saveResult['success']) {
            $imported++;
            $record = $saveResult['record'];
            $empInfo = $record['employee_id'] ? "従業員ID:{$record['employee_id']} ({$record['auto_linked_method']})" : '未紐付け';
            cronLog("  インポート: {$empInfo} → {$record['upload_type']}");
        } else {
            $errors[] = $saveResult['error'];
        }
    }
}

// --- 同期ログを保存 ---
$logFile = $configDir . '/alcohol-sync-log.json';
$syncLog = [];
if (file_exists($logFile)) {
    $syncLog = json_decode(file_get_contents($logFile), true) ?: [];
}
$syncLog[$targetDate] = date('Y-m-d H:i:s');
file_put_contents($logFile, json_encode($syncLog, JSON_PRETTY_PRINT));

// --- 結果出力 ---
$summary = "インポート: {$imported}件, スキップ: {$skipped}件";
if (!empty($errors)) {
    $summary .= ", エラー: " . count($errors) . "件";
}
cronLog($summary);
cronLog('=== 自動同期完了 ===');

outputResult(true, $summary, [
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => $errors
]);

// --- ヘルパー関数 ---

function outputResult($success, $message, $data = []) {
    global $isCli, $logMessages;
    if (!$isCli) {
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
            'log' => $logMessages
        ], $data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

/**
 * 画像を保存し従業員に自動紐付け（cron用・alcohol-chat-sync.phpと同等のロジック）
 */
function saveImportedImageForCron($imageData, $contentType, $date, $messageId, $senderUserId, $senderName, $senderEmail, $employees, $emailToEmployee, $chatUserIdToEmployee, $chat, $membersMap) {
    // ファイル拡張子
    $extension = 'jpg';
    if (strpos($contentType, 'png') !== false) {
        $extension = 'png';
    } elseif (strpos($contentType, 'gif') !== false) {
        $extension = 'gif';
    }

    // 保存先
    $yearMonth = date('Y-m', strtotime($date));
    $uploadDir = dirname(__DIR__) . '/functions/uploads/attendance-photos/' . $yearMonth . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = sprintf('chat_%s_%s.%s', $date, uniqid(), $extension);
    $filePath = $uploadDir . $filename;

    if (file_put_contents($filePath, $imageData) === false) {
        return ['success' => false, 'error' => 'ファイルの保存に失敗しました'];
    }

    $employeeId = null;
    $autoLinkedMethod = null;

    // 0. chat_user_idで照合（最優先）
    if (!$employeeId && !empty($senderUserId) && isset($chatUserIdToEmployee[$senderUserId])) {
        $emp = $chatUserIdToEmployee[$senderUserId];
        $employeeId = $emp['id'] ?? null;
        $autoLinkedMethod = 'chat_user_id';
    }

    // 1. スペースメンバーAPIでメール照合
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

    // 1b. People APIフォールバック
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

    // 2. sender.email直接
    if (!$employeeId && !empty($senderEmail)) {
        $emailLower = strtolower($senderEmail);
        if (isset($emailToEmployee[$emailLower])) {
            $emp = $emailToEmployee[$emailLower];
            $employeeId = $emp['id'] ?? null;
            $autoLinkedMethod = 'email_direct';
        }
    }

    // 3. 表示名で照合
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

    // chat_user_id自動登録
    if ($employeeId && !empty($senderUserId) && $autoLinkedMethod !== 'chat_user_id') {
        updateEmployeeChatUserId($employeeId, $senderUserId);
    }

    // データ保存
    $allData = getPhotoAttendanceData();

    $uploadType = 'chat_import';
    if ($employeeId) {
        $existingCount = 0;
        foreach ($allData as $existing) {
            if (($existing['employee_id'] ?? '') == $employeeId &&
                ($existing['upload_date'] ?? '') === $date &&
                ($existing['source'] ?? '') === 'chat') {
                $existingCount++;
            }
        }
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
