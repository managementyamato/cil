<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$company  = trim($_POST['company'] ?? '');
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$plan     = trim($_POST['plan'] ?? '');
$scale    = trim($_POST['scale'] ?? '');
$message  = trim($_POST['message'] ?? '');

if (!$company || !$name || !$email) {
    http_response_code(400);
    echo json_encode(['error' => '必須項目が入力されていません']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'メールアドレスの形式が正しくありません']);
    exit;
}

$to = 'management8010@yamato-agency.com';
$subject = "【現場DX管理システム】お問い合わせ: {$company}";

$body = "現場DX管理システムへのお問い合わせが届きました。\n\n";
$body .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
$body .= "会社名: {$company}\n";
$body .= "お名前: {$name}\n";
$body .= "メール: {$email}\n";
$body .= "電話:   " . ($phone ?: '未入力') . "\n";
$body .= "プラン: {$plan}\n";
$body .= "規模:   {$scale}\n";
$body .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
$body .= "お問い合わせ内容:\n{$message}\n\n";
$body .= "送信日時: " . date('Y-m-d H:i:s') . "\n";

$headers  = "From: noreply@yamato-agency.com\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, $headers);

// 問い合わせログをファイルに保存（メール失敗時のバックアップ）
$logFile = __DIR__ . '/contact-log.json';
$log = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
$log[] = [
    'id'       => uniqid(),
    'company'  => $company,
    'name'     => $name,
    'email'    => $email,
    'phone'    => $phone,
    'plan'     => $plan,
    'scale'    => $scale,
    'message'  => $message,
    'created_at' => date('Y-m-d H:i:s'),
    'mail_sent' => $sent
];
file_put_contents($logFile, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'message' => 'お申し込みを受け付けました。24時間以内にご連絡します。']);
