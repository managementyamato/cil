<?php
/**
 * メール送信 API
 * Gmail API 経由でメールを作成・送信する
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/validation.php';
require_once __DIR__ . '/google-gmail.php';

// GET: 送信履歴取得 / POST: メール送信
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    initApi([
        'requireAuth' => true,
        'requireCsrf' => false,
        'allowedMethods' => ['GET']
    ]);

    $action = $_GET['action'] ?? '';

    switch ($action) {
        // 送信履歴一覧
        case 'logs':
            $pdo = Database::connect();
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 20;
            $offset = ($page - 1) * $perPage;

            $total = $pdo->query("SELECT COUNT(*) FROM email_logs")->fetchColumn();
            $stmt = $pdo->prepare(
                "SELECT id, from_address, to_address, subject, sent_by, sent_at
                 FROM email_logs ORDER BY sent_at DESC LIMIT ? OFFSET ?"
            );
            $stmt->execute([$perPage, $offset]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            successResponse([
                'logs'     => $logs,
                'total'    => (int)$total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => ceil($total / $perPage),
            ]);
            break;

        // アドレス帳（従業員 + 社内連絡先マスタ）
        case 'address_book':
            $data = getData();
            $addresses = [];

            // 従業員
            foreach ($data['employees'] ?? [] as $emp) {
                if (empty($emp['email']) || !empty($emp['leave_date']) || !empty($emp['deleted_at'])) continue;
                $addresses[] = [
                    'name'  => $emp['name'] ?? '',
                    'email' => $emp['email'],
                    'dept'  => $emp['department'] ?? '',
                    'type'  => 'employee',
                ];
            }

            // 社内連絡先マスタ
            foreach ($data['contact_masters'] ?? [] as $cm) {
                if (empty($cm['email'])) continue;
                // 従業員と重複していたらスキップ
                $exists = false;
                foreach ($addresses as $a) {
                    if ($a['email'] === $cm['email']) { $exists = true; break; }
                }
                if ($exists) continue;
                $addresses[] = [
                    'name'  => $cm['name'] ?? '',
                    'email' => $cm['email'],
                    'dept'  => $cm['department'] ?? '',
                    'type'  => 'contact',
                ];
            }

            usort($addresses, fn($a, $b) => strcmp($a['name'], $b['name']));
            successResponse(['addresses' => $addresses]);
            break;

        default:
            errorResponse('不正なアクションです', 400);
    }
    exit;
}

// POST: メール送信
initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'allowedMethods' => ['POST']
]);

if (!canEdit()) errorResponse('権限がありません', 403);

$input = getJsonInput();
$action = $input['action'] ?? '';

switch ($action) {
    case 'send':
        $to      = trim($input['to'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $body    = trim($input['body'] ?? '');

        // バリデーション
        $errors = [];
        if (empty($to))      $errors[] = '宛先を入力してください';
        if (empty($subject))  $errors[] = '件名を入力してください';

        // カンマ区切りの複数アドレスを検証
        $recipients = array_filter(array_map('trim', explode(',', $to)));
        foreach ($recipients as $addr) {
            if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'メールアドレスが不正です: ' . $addr;
            }
        }

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $toAddress = implode(', ', $recipients);

        $gmail = new GoogleGmailClient();
        if (!$gmail->isConfigured()) {
            errorResponse('Gmail連携が設定されていません。設定画面からGmail連携を行ってください。', 400);
        }

        try {
            $result = $gmail->sendEmail($toAddress, $subject, $body);

            // DB に送信ログを保存
            $logId = 'eml_' . uniqid();
            $now = date('Y-m-d H:i:s');
            try {
                $pdo = Database::connect();
                // テーブルが無ければ作成
                $pdo->exec("CREATE TABLE IF NOT EXISTS `email_logs` (
                    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
                    `from_address` VARCHAR(255) NOT NULL,
                    `to_address` TEXT NOT NULL,
                    `subject` VARCHAR(500) NOT NULL DEFAULT '',
                    `body` TEXT,
                    `gmail_message_id` VARCHAR(255) DEFAULT NULL,
                    `sent_by` VARCHAR(255) DEFAULT NULL,
                    `sent_at` DATETIME NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $stmt = $pdo->prepare(
                    "INSERT INTO email_logs (id, from_address, to_address, subject, body, gmail_message_id, sent_by, sent_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $logId,
                    $_SESSION['user_email'] ?? '',
                    $toAddress,
                    $subject,
                    $body,
                    $result['id'] ?? null,
                    $_SESSION['user_email'] ?? '',
                    $now,
                ]);
            } catch (\Throwable $e) {
                error_log('[email-api] log save failed: ' . $e->getMessage());
                // ログ保存失敗はメール送信成功を妨げない
            }

            writeAuditLog('send', 'email', "メール送信: To={$toAddress} Subject={$subject}");
            successResponse(['id' => $logId], '送信しました');
        } catch (Exception $e) {
            error_log('[email-api] Gmail send error: ' . $e->getMessage());
            errorResponse('メール送信に失敗しました: ' . $e->getMessage(), 500);
        }
        break;

    default:
        errorResponse('不正なアクションです', 400);
}
