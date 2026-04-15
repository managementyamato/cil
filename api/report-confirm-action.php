<?php
/**
 * 週報確認 メールアクション（ログイン不要・トークン認証）
 *
 * GET  ?token=XXX  → 確認フォームを表示
 * POST ?token=XXX  → 確認処理実行・結果表示
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/notification-functions.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

// ─── トークン検証 ──────────────────────────────────────
function findReportByToken($token) {
    $data = getData();
    foreach ($data['weekly_reports'] ?? [] as $r) {
        if (($r['confirm_token'] ?? '') === $token && empty($r['deleted_at'])) {
            return $r;
        }
    }
    return null;
}

function renderPage($title, $content, $isError = false) {
    $color = $isError ? '#c62828' : '#27ae60';
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . htmlspecialchars($title) . ' - Yamato Gear</title>'
        . '<style>'
        . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f7fa;margin:0;padding:40px 16px;}'
        . '.card{max-width:540px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,.10);padding:2rem;}'
        . 'h2{margin-top:0;color:' . $color . ';}'
        . '.info-table{width:100%;border-collapse:collapse;margin:1rem 0;}'
        . '.info-table th,.info-table td{padding:8px 12px;border:1px solid #e0e0e0;text-align:left;font-size:14px;}'
        . '.info-table th{background:#f5f5f5;width:130px;}'
        . '.btn{display:inline-block;padding:11px 28px;border-radius:6px;font-size:15px;font-weight:600;border:none;cursor:pointer;text-decoration:none;}'
        . '.btn-confirm{background:#27ae60;color:#fff;}'
        . '.btn-secondary{background:#e0e0e0;color:#333;}'
        . '.section-content{font-size:14px;line-height:1.7;}'
        . '.section-content a{color:#2980b9;text-decoration:underline;}'
        . '</style></head><body><div class="card">'
        . $content
        . '</div></body></html>';
    exit;
}

// バリデーション
if (empty($token)) {
    renderPage('無効なリンク', '<h2>無効なリンクです</h2><p>URLが正しくないか、リンクが古くなっています。</p>', true);
}

$report = findReportByToken($token);

if (!$report) {
    renderPage('リンクエラー', '<h2>週報が見つかりません</h2><p>リンクが無効か、週報が削除されています。</p>', true);
}

// 既に確認済み
if (!empty($report['confirmed_at'])) {
    $confirmerName = htmlspecialchars($report['confirmed_by_name'] ?? '');
    $confirmedAt   = htmlspecialchars($report['confirmed_at'] ?? '');
    renderPage('確認済み',
        '<h2>この週報は既に確認済みです</h2>'
        . '<table class="info-table">'
        . '<tr><th>確認者</th><td>' . $confirmerName . '</td></tr>'
        . '<tr><th>確認日時</th><td>' . $confirmedAt . '</td></tr>'
        . '</table>'
    );
}

$ts = function($s) { return htmlspecialchars($s ?? ''); };
$userName  = $ts($report['user_name'] ?? $report['user_email'] ?? '');
$weekStart = $ts($report['week_start'] ?? '');

// セクション内容をHTMLで表示（URLをリンク化）
$sectionLabels = [
    'sec_role'        => '今期の役割',
    'sec_report'      => '今週の報告',
    'sec_issues'      => '現在抱えている課題',
    'sec_next_goals'  => '次週目標・計画',
    'sec_second_area' => 'いま思いつく第二領域活動',
    'sec_misc'        => '報告・連絡・相談事項',
];

function linkifyText($text) {
    return preg_replace(
        '/(https?:\/\/[^\s<>"\']+)/i',
        '<a href="$1" target="_blank" style="color:#2980b9;">$1</a>',
        $text
    );
}

$sectionRows = '';
foreach ($sectionLabels as $key => $label) {
    $content = $report[$key] ?? '';
    if (empty(trim(strip_tags($content)))) continue;
    $content = linkifyText($content);
    $sectionRows .= '<tr><th>' . $ts($label) . '</th><td class="section-content">' . $content . '</td></tr>';
}

// ─── GET: 確認フォーム表示 ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $html = '<h2>週報確認</h2>'
        . '<p>' . $userName . ' さんの週報を確認します。</p>'
        . '<table class="info-table">'
        . '<tr><th>提出者</th><td>' . $userName . '</td></tr>'
        . '<tr><th>提出日</th><td>' . $weekStart . '</td></tr>'
        . '<tr><th>提出日時</th><td>' . $ts($report['submitted_at'] ?? '') . '</td></tr>'
        . $sectionRows
        . '</table>'
        . '<form method="POST" style="margin-top:20px;">'
        . '<input type="hidden" name="token" value="' . htmlspecialchars($token) . '">'
        . '<button type="submit" class="btn btn-confirm">確認済みにする</button>'
        . '</form>';

    renderPage('週報確認', $html);
}

// ─── POST: 確認処理実行 ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getData();
    $now = date('Y-m-d H:i:s');

    $found = false;
    foreach ($data['weekly_reports'] as &$r) {
        if (($r['confirm_token'] ?? '') === $token && empty($r['deleted_at'])) {
            if (!empty($r['confirmed_at'])) {
                renderPage('確認済み', '<h2>この週報は既に確認済みです</h2>');
            }
            $r['confirmed_at']      = $now;
            $r['confirmed_by']      = 'email_action';
            $r['confirmed_by_name'] = 'メール確認';
            $r['updated_at']        = $now;
            $found = true;
            $confirmedReport = $r;
            break;
        }
    }
    unset($r);

    if (!$found) {
        renderPage('エラー', '<h2>週報が見つかりません</h2>', true);
    }

    saveData($data);

    // 提出者に確認通知メール
    $submitterEmail = $confirmedReport['user_email'] ?? '';
    if (!empty($submitterEmail) && filter_var($submitterEmail, FILTER_VALIDATE_EMAIL)) {
        $submitterName = htmlspecialchars($confirmedReport['user_name'] ?? '');
        $notifSubject  = "【週報確認済み】{$weekStart} の週報が確認されました";
        $notifBody     = "<p>{$submitterName} さんの週報（{$weekStart}）が確認されました。</p>"
            . "<p>確認日時: {$now}</p>";
        sendNotificationEmail($submitterEmail, $notifSubject, $notifBody);
    }

    $html = '<h2>確認しました</h2>'
        . '<p>' . $userName . ' さんの週報（' . $weekStart . '）を確認済みにしました。</p>'
        . '<p>提出者にメールで通知されます。</p>';

    renderPage('確認完了', $html);
}
