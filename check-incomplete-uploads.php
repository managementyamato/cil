<?php
/**
 * アルコールチェック未完了者の通知スクリプト
 *
 * 前日に1回しかアップロードしていない従業員を検出し、
 * 管理部にメール通知を送信します。
 *
 * 実行方法: php check-incomplete-uploads.php
 * Cronで毎朝実行することを推奨（例: 毎朝8:00）
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/photo-attendance-functions.php';

// メール送信先（管理部）
define('ADMIN_EMAIL', 'admin@yamato-agency.com'); // ここを実際のメールアドレスに変更
define('FROM_EMAIL', 'noreply@yamato-agency.com'); // 送信元アドレス

/**
 * 前日の部分完了者（1回のみアップロード）を取得
 */
function getPartialCompletions($date) {
    $uploadStatus = getUploadStatusForDate($date);
    $employees = getEmployees();
    $partialCompletions = [];

    foreach ($employees as $employee) {
        $status = $uploadStatus[$employee['id']] ?? ['start' => null, 'end' => null];

        // 1回だけアップロードしている場合
        if (($status['start'] && !$status['end']) || (!$status['start'] && $status['end'])) {
            $partialCompletions[] = [
                'employee' => $employee,
                'has_start' => (bool)$status['start'],
                'has_end' => (bool)$status['end'],
                'start_time' => $status['start'] ? $status['start']['uploaded_at'] : null,
                'end_time' => $status['end'] ? $status['end']['uploaded_at'] : null
            ];
        }
    }

    return $partialCompletions;
}

/**
 * メール本文を生成
 */
function generateEmailBody($date, $partialCompletions) {
    $dateFormatted = date('Y年m月d日', strtotime($date));

    $body = "アルコールチェック未完了通知\n";
    $body .= "=====================================\n\n";
    $body .= "対象日: {$dateFormatted}\n\n";
    $body .= "以下の従業員がアルコールチェックを1回しか実施していません。\n\n";

    foreach ($partialCompletions as $item) {
        $employee = $item['employee'];
        $vehicleNumber = $employee['vehicle_number'] ?? '-';

        $body .= "【{$employee['name']}】\n";
        $body .= "  ナンバー: {$vehicleNumber}\n";
        $body .= "  所属: {$employee['area']}\n";

        if ($item['has_start']) {
            $body .= "  出勤前チェック: ✓ " . date('H:i', strtotime($item['start_time'])) . "\n";
            $body .= "  退勤前チェック: ✗ 未実施\n";
        } else {
            $body .= "  出勤前チェック: ✗ 未実施\n";
            $body .= "  退勤前チェック: ✓ " . date('H:i', strtotime($item['end_time'])) . "\n";
        }

        $body .= "\n";
    }

    $body .= "=====================================\n";
    $body .= "このメールは自動送信されています。\n";
    $body .= "YA管理システム\n";

    return $body;
}

/**
 * メール送信（HTML版）
 */
function generateHtmlEmailBody($date, $partialCompletions) {
    $dateFormatted = date('Y年m月d日', strtotime($date));

    $html = '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Hiragino Sans", "Meiryo", sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background: #ff9800; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .content { padding: 20px; }
        .employee-card { background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .employee-name { font-weight: bold; font-size: 1.1rem; margin-bottom: 10px; color: #e65100; }
        .info-row { margin: 5px 0; color: #666; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.875rem; margin-left: 10px; }
        .status.ok { background: #c8e6c9; color: #2e7d32; }
        .status.ng { background: #ffcdd2; color: #c62828; }
        .footer { padding: 20px; text-align: center; color: #999; font-size: 0.875rem; border-top: 1px solid #e0e0e0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ アルコールチェック未完了通知</h1>
        </div>
        <div class="content">
            <p><strong>対象日:</strong> ' . $dateFormatted . '</p>
            <p>以下の従業員がアルコールチェックを1回しか実施していません。</p>';

    foreach ($partialCompletions as $item) {
        $employee = $item['employee'];
        $vehicleNumber = htmlspecialchars($employee['vehicle_number'] ?? '-');
        $name = htmlspecialchars($employee['name']);
        $area = htmlspecialchars($employee['area']);

        $html .= '<div class="employee-card">
            <div class="employee-name">' . $name . '</div>
            <div class="info-row">📍 所属: ' . $area . '</div>
            <div class="info-row">🚗 ナンバー: ' . $vehicleNumber . '</div>
            <div class="info-row">
                出勤前チェック: ';

        if ($item['has_start']) {
            $html .= '<span class="status ok">✓ ' . date('H:i', strtotime($item['start_time'])) . '</span>';
        } else {
            $html .= '<span class="status ng">✗ 未実施</span>';
        }

        $html .= '</div>
            <div class="info-row">
                退勤前チェック: ';

        if ($item['has_end']) {
            $html .= '<span class="status ok">✓ ' . date('H:i', strtotime($item['end_time'])) . '</span>';
        } else {
            $html .= '<span class="status ng">✗ 未実施</span>';
        }

        $html .= '</div>
        </div>';
    }

    $html .= '</div>
        <div class="footer">
            このメールは自動送信されています。<br>
            YA管理システム
        </div>
    </div>
</body>
</html>';

    return $html;
}

/**
 * メール送信
 */
function sendEmail($to, $subject, $textBody, $htmlBody) {
    $headers = "From: " . FROM_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"boundary\"\r\n";

    $message = "--boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $textBody . "\r\n";
    $message .= "--boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $htmlBody . "\r\n";
    $message .= "--boundary--";

    return mail($to, $subject, $message, $headers);
}

// ==========================================
// メイン処理
// ==========================================

echo "アルコールチェック未完了者チェック開始\n";
echo "=====================================\n\n";

// 前日の日付を取得
$yesterday = date('Y-m-d', strtotime('-1 day'));
echo "対象日: {$yesterday}\n\n";

// 部分完了者を取得
$partialCompletions = getPartialCompletions($yesterday);

if (empty($partialCompletions)) {
    echo "✓ 未完了者はいません。\n";
    exit(0);
}

echo "未完了者: " . count($partialCompletions) . "名\n";
foreach ($partialCompletions as $item) {
    echo "  - {$item['employee']['name']} ({$item['employee']['area']})\n";
}
echo "\n";

// メール本文を生成
$subject = "【アルコールチェック】未完了通知 (" . date('Y/m/d', strtotime($yesterday)) . ")";
$textBody = generateEmailBody($yesterday, $partialCompletions);
$htmlBody = generateHtmlEmailBody($yesterday, $partialCompletions);

// メール送信
echo "メール送信中...\n";
$result = sendEmail(ADMIN_EMAIL, $subject, $textBody, $htmlBody);

if ($result) {
    echo "✓ メール送信成功: " . ADMIN_EMAIL . "\n";
} else {
    echo "✗ メール送信失敗\n";
    exit(1);
}

echo "\n処理完了\n";
exit(0);
