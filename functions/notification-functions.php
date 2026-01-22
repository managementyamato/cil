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
        return false;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . ($config['smtp_from_name'] ?? 'YA管理システム') . ' <' . ($config['smtp_from_email'] ?? 'noreply@example.com') . '>'
    ];

    // SMTPが設定されている場合はSMTP経由で送信
    if (!empty($config['smtp_host'])) {
        return sendSmtpEmail($to, $subject, $body, $config);
    }

    // PHPのmail関数で送信
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * SMTP経由でメール送信
 */
function sendSmtpEmail($to, $subject, $body, $config) {
    $host = $config['smtp_host'];
    $port = $config['smtp_port'] ?? 587;
    $username = $config['smtp_username'];
    $password = $config['smtp_password'];
    $fromEmail = $config['smtp_from_email'];
    $fromName = $config['smtp_from_name'] ?? 'YA管理システム';

    // fsockopenでSMTP接続
    $socket = @fsockopen($host, $port, $errno, $errstr, 30);
    if (!$socket) {
        error_log("SMTP connection failed: $errstr ($errno)");
        return false;
    }

    // SMTP通信
    $response = fgets($socket, 515);

    fputs($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
    $response = fgets($socket, 515);

    fputs($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket, 515);

    fputs($socket, base64_encode($username) . "\r\n");
    $response = fgets($socket, 515);

    fputs($socket, base64_encode($password) . "\r\n");
    $response = fgets($socket, 515);

    fputs($socket, "MAIL FROM: <$fromEmail>\r\n");
    $response = fgets($socket, 515);

    fputs($socket, "RCPT TO: <$to>\r\n");
    $response = fgets($socket, 515);

    fputs($socket, "DATA\r\n");
    $response = fgets($socket, 515);

    $headers = "From: $fromName <$fromEmail>\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "\r\n";

    fputs($socket, $headers . $body . "\r\n.\r\n");
    $response = fgets($socket, 515);

    fputs($socket, "QUIT\r\n");
    fclose($socket);

    return true;
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
