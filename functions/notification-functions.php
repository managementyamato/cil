<?php
/**
 * 通知機能
 */

define('NOTIFICATION_CONFIG_FILE', __DIR__ . '/../config/notification-config.json');

/**
 * 通知設定を取得
 */
function getNotificationConfig() {
    if (!file_exists(NOTIFICATION_CONFIG_FILE)) {
        return [
            'enabled' => false,
            'email_recipients' => [],
            'notify_on_new_trouble' => true,
            'notify_on_status_change' => false,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_from_email' => '',
            'smtp_from_name' => 'YA管理システム'
        ];
    }
    return json_decode(file_get_contents(NOTIFICATION_CONFIG_FILE), true) ?: [];
}

/**
 * 通知設定を保存
 */
function saveNotificationConfig($config) {
    $config['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents(NOTIFICATION_CONFIG_FILE, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * メール送信（PHPのmail関数使用）
 */
function sendNotificationEmail($to, $subject, $body) {
    $config = getNotificationConfig();

    if (!$config['enabled']) {
        error_log("[MAIL] Notification disabled");
        return false;
    }

    // ローカル開発環境（localhost）ではメール送信をスキップ
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        error_log("[DEV] Mail skipped - To: $to, Subject: $subject");
        return true; // 開発環境では成功として扱う
    }

    // SMTPが設定されている場合はSMTP経由で送信
    if (!empty($config['smtp_host'])) {
        return sendSmtpEmail($to, $subject, $body, $config);
    }

    // Xserver用: mail()関数で送信
    $fromEmail = $config['smtp_from_email'] ?? 'noreply@yamato-mgt.com';
    $fromName = $config['smtp_from_name'] ?? 'YA管理システム';

    // 件名をMIMEエンコード（日本語対応）
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    // ヘッダー設定（Xserver対応）
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . '=?UTF-8?B?' . base64_encode($fromName) . '?=' . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'X-Mailer: PHP/' . phpversion()
    ];

    // 追加パラメータ（envelope sender）- Xserverで必要
    $additionalParams = '-f' . $fromEmail;

    // メール送信
    $result = mail($to, $encodedSubject, $body, implode("\r\n", $headers), $additionalParams);

    if (!$result) {
        error_log("[MAIL] Failed to send - To: $to, Subject: $subject, From: $fromEmail");
    } else {
        error_log("[MAIL] Sent successfully - To: $to, Subject: $subject");
    }

    return $result;
}

/**
 * SMTP経由でメール送信
 * 注意: この実装はTLS/STARTTLSに対応していません。
 * Xserverではmail()関数を使用することを推奨します。
 */
function sendSmtpEmail($to, $subject, $body, $config) {
    $host = $config['smtp_host'];
    $port = $config['smtp_port'] ?? 587;
    $username = $config['smtp_username'];
    $password = $config['smtp_password'];
    $fromEmail = $config['smtp_from_email'];
    $fromName = $config['smtp_from_name'] ?? 'YA管理システム';

    // TLS接続（ポート465）またはSTARTTLS（ポート587）
    $protocol = ($port == 465) ? 'ssl://' : '';
    $socket = @fsockopen($protocol . $host, $port, $errno, $errstr, 30);

    if (!$socket) {
        error_log("[SMTP] Connection failed to $host:$port - $errstr ($errno)");
        return false;
    }

    // レスポンス読み取りヘルパー
    $readResponse = function() use ($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $response;
    };

    // SMTPコマンド送信ヘルパー
    $sendCommand = function($cmd) use ($socket, $readResponse) {
        fputs($socket, $cmd . "\r\n");
        return $readResponse();
    };

    try {
        // 初期応答
        $response = $readResponse();
        if (substr($response, 0, 3) != '220') {
            throw new Exception("Initial response failed: $response");
        }

        // EHLO
        $serverHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $response = $sendCommand("EHLO $serverHost");
        if (substr($response, 0, 3) != '250') {
            throw new Exception("EHLO failed: $response");
        }

        // AUTH LOGIN
        $response = $sendCommand("AUTH LOGIN");
        if (substr($response, 0, 3) != '334') {
            throw new Exception("AUTH LOGIN failed: $response");
        }

        $response = $sendCommand(base64_encode($username));
        if (substr($response, 0, 3) != '334') {
            throw new Exception("Username failed: $response");
        }

        $response = $sendCommand(base64_encode($password));
        if (substr($response, 0, 3) != '235') {
            throw new Exception("Password failed: $response");
        }

        // MAIL FROM
        $response = $sendCommand("MAIL FROM: <$fromEmail>");
        if (substr($response, 0, 3) != '250') {
            throw new Exception("MAIL FROM failed: $response");
        }

        // RCPT TO
        $response = $sendCommand("RCPT TO: <$to>");
        if (substr($response, 0, 3) != '250') {
            throw new Exception("RCPT TO failed: $response");
        }

        // DATA
        $response = $sendCommand("DATA");
        if (substr($response, 0, 3) != '354') {
            throw new Exception("DATA failed: $response");
        }

        // メール本文
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $message = "From: $encodedFromName <$fromEmail>\r\n";
        $message .= "To: $to\r\n";
        $message .= "Subject: $encodedSubject\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "\r\n";
        $message .= $body;

        $response = $sendCommand($message . "\r\n.");
        if (substr($response, 0, 3) != '250') {
            throw new Exception("Message send failed: $response");
        }

        $sendCommand("QUIT");
        fclose($socket);

        error_log("[SMTP] Sent successfully - To: $to, Subject: $subject");
        return true;

    } catch (Exception $e) {
        error_log("[SMTP] Error: " . $e->getMessage());
        if (is_resource($socket)) {
            fclose($socket);
        }
        return false;
    }
}

/**
 * 新規トラブル登録時の通知
 */
function notifyNewTrouble($trouble) {
    $config = getNotificationConfig();

    if (!$config['enabled'] || !$config['notify_on_new_trouble']) {
        return;
    }

    $recipients = $config['email_recipients'] ?? [];
    if (empty($recipients)) {
        return;
    }

    $subject = "[YA管理] 新規トラブル登録: " . ($trouble['pj_number'] ?? '');

    $body = "<html><body style='font-family: sans-serif;'>";
    $body .= "<h2 style='color: #dc2626;'>新規トラブルが登録されました</h2>";
    $body .= "<table style='border-collapse: collapse; width: 100%; max-width: 600px;'>";
    $body .= "<tr><td style='padding: 8px; border: 1px solid #ddd; background: #f5f5f5; width: 120px;'>P番号</td><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($trouble['pj_number'] ?? '-') . "</td></tr>";
    $body .= "<tr><td style='padding: 8px; border: 1px solid #ddd; background: #f5f5f5;'>発生日</td><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($trouble['occurrence_date'] ?? '-') . "</td></tr>";
    $body .= "<tr><td style='padding: 8px; border: 1px solid #ddd; background: #f5f5f5;'>記入者</td><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($trouble['reporter'] ?? '-') . "</td></tr>";
    $body .= "<tr><td style='padding: 8px; border: 1px solid #ddd; background: #f5f5f5;'>対応者</td><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($trouble['responder'] ?? '-') . "</td></tr>";
    $body .= "<tr><td style='padding: 8px; border: 1px solid #ddd; background: #f5f5f5;'>内容</td><td style='padding: 8px; border: 1px solid #ddd;'>" . nl2br(htmlspecialchars($trouble['content'] ?? '-')) . "</td></tr>";
    $body .= "</table>";
    $body .= "<p style='margin-top: 20px;'><a href='" . getBaseUrl() . "/pages/troubles.php' style='color: #3b82f6;'>トラブル一覧を確認する</a></p>";
    $body .= "</body></html>";

    foreach ($recipients as $email) {
        sendNotificationEmail($email, $subject, $body);
    }
}

/**
 * ステータス変更時の通知
 */
function notifyStatusChange($trouble, $oldStatus, $newStatus) {
    $config = getNotificationConfig();

    if (!$config['enabled'] || !$config['notify_on_status_change']) {
        return;
    }

    $recipients = $config['email_recipients'] ?? [];
    if (empty($recipients)) {
        return;
    }

    $subject = "[YA管理] ステータス変更: " . ($trouble['pj_number'] ?? '') . " ({$oldStatus} → {$newStatus})";

    $statusColors = [
        '未対応' => '#ef4444',
        '対応中' => '#f59e0b',
        '保留' => '#6b7280',
        '完了' => '#10b981'
    ];
    $newColor = $statusColors[$newStatus] ?? '#333';

    $body = "<html><body style='font-family: sans-serif;'>";
    $body .= "<h2 style='color: #3b82f6;'>トラブルのステータスが変更されました</h2>";
    $body .= "<p style='font-size: 18px;'><span style='color: #666;'>{$oldStatus}</span> → <span style='color: {$newColor}; font-weight: bold;'>{$newStatus}</span></p>";
    $body .= "<table style='border-collapse: collapse; width: 100%; max-width: 600px;'>";
    $body .= "<tr><td style='padding: 8px; border: 1px solid #ddd; background: #f5f5f5; width: 120px;'>P番号</td><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($trouble['pj_number'] ?? '-') . "</td></tr>";
    $body .= "<tr><td style='padding: 8px; border: 1px solid #ddd; background: #f5f5f5;'>内容</td><td style='padding: 8px; border: 1px solid #ddd;'>" . nl2br(htmlspecialchars($trouble['content'] ?? '-')) . "</td></tr>";
    $body .= "</table>";
    $body .= "<p style='margin-top: 20px;'><a href='" . getBaseUrl() . "/pages/troubles.php' style='color: #3b82f6;'>トラブル一覧を確認する</a></p>";
    $body .= "</body></html>";

    foreach ($recipients as $email) {
        sendNotificationEmail($email, $subject, $body);
    }
}

/**
 * ベースURLを取得
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}
