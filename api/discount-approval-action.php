<?php
/**
 * 値引き承認 メールアクション（ログイン不要・トークン認証）
 *
 * GET  ?token=XXX&action=approve|reject  → 確認フォームを表示
 * POST ?token=XXX&action=approve|reject  → 処理実行・結果表示
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/notification-functions.php';

$token  = trim($_GET['token'] ?? $_POST['token'] ?? '');
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$validActions = ['approve', 'reject'];

// ─── トークン検証 ──────────────────────────────────────
function findApprovalByToken($token) {
    $data = getData();
    foreach ($data['discount_approvals'] ?? [] as $a) {
        if (($a['email_action_token'] ?? '') === $token) {
            return $a;
        }
    }
    return null;
}

function renderPage($title, $content, $isError = false) {
    $color = $isError ? '#c62828' : '#1565c0';
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
        . '.btn-approve{background:#2e7d32;color:#fff;}'
        . '.btn-reject{background:#c62828;color:#fff;}'
        . '.btn-secondary{background:#e0e0e0;color:#333;}'
        . 'textarea{width:100%;box-sizing:border-box;padding:8px;border:1px solid #ccc;border-radius:6px;font-size:14px;resize:vertical;}'
        . '.note{color:#888;font-size:12px;margin-top:12px;}'
        . '</style></head><body><div class="card">'
        . $content
        . '</div></body></html>';
    exit;
}

// 基本バリデーション
if (empty($token) || !in_array($action, $validActions, true)) {
    renderPage('無効なリンク', '<h2>無効なリンクです</h2><p>URLが正しくないか、リンクが古くなっています。</p>', true);
}

$approval = findApprovalByToken($token);

if (!$approval) {
    renderPage('リンクエラー', '<h2>申請が見つかりません</h2><p>リンクが無効か、申請が削除されています。</p>', true);
}

// 有効期限チェック
$expiresAt = $approval['email_token_expires_at'] ?? '';
if ($expiresAt && strtotime($expiresAt) < time()) {
    renderPage('リンク期限切れ', '<h2>リンクの有効期限が切れています</h2><p>有効期限（7日間）が過ぎています。システムにログインして操作してください。</p>', true);
}

// 使用済みチェック
if (!empty($approval['email_token_used_at'])) {
    $usedAt = htmlspecialchars($approval['email_token_used_at']);
    renderPage('使用済みリンク', '<h2>このリンクは使用済みです</h2><p>このリンクは ' . $usedAt . ' に使用されました。</p>', true);
}

// ステータスチェック
if (($approval['status'] ?? '') !== 'pending') {
    $statusLabel = ['approved' => '承認済み', 'rejected' => '却下済み'][$approval['status']] ?? $approval['status'];
    renderPage('審査済み', '<h2>この申請は既に審査済みです</h2><p>現在のステータス: <strong>' . htmlspecialchars($statusLabel) . '</strong></p>', true);
}

$actionLabel  = ($action === 'approve') ? '承認' : '却下';
$after        = ($approval['original_amount'] ?? 0) - ($approval['discount_amount'] ?? 0);

// ─── GETリクエスト: 確認フォーム表示 ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $btnClass = ($action === 'approve') ? 'btn-approve' : 'btn-reject';
    $formAction = htmlspecialchars('/api/discount-approval-action.php?token=' . urlencode($token) . '&action=' . urlencode($action));
    $content = '<h2>値引き申請の' . $actionLabel . '</h2>'
        . '<table class="info-table">'
        . '<tr><th>案件名</th><td><strong>' . htmlspecialchars($approval['project_name']) . '</strong></td></tr>'
        . '<tr><th>申請者</th><td>' . htmlspecialchars($approval['applicant_name'] ?? $approval['applicant_email']) . '</td></tr>'
        . '<tr><th>値引き前金額</th><td>¥' . number_format($approval['original_amount'] ?? 0) . '</td></tr>'
        . '<tr><th>値引き額</th><td>¥' . number_format($approval['discount_amount'] ?? 0) . '</td></tr>'
        . '<tr><th>値引き後金額</th><td>¥' . number_format($after) . '</td></tr>'
        . '<tr><th>理由</th><td>' . nl2br(htmlspecialchars($approval['reason'] ?? '')) . '</td></tr>'
        . '<tr><th>申請日時</th><td>' . htmlspecialchars($approval['created_at'] ?? '') . '</td></tr>'
        . '</table>'
        . '<form method="POST" action="' . $formAction . '" style="margin-top:1.25rem;">'
        . '<input type="hidden" name="token" value="' . htmlspecialchars($token) . '">'
        . '<input type="hidden" name="action" value="' . htmlspecialchars($action) . '">'
        . '<div style="margin-bottom:1rem;">'
        . '<label style="display:block;font-size:14px;margin-bottom:4px;">コメント（任意）</label>'
        . '<textarea name="comment" rows="3" placeholder="' . $actionLabel . 'の理由や補足を記載（省略可）"></textarea>'
        . '</div>'
        . '<div style="display:flex;gap:12px;">'
        . '<button type="submit" class="btn ' . $btnClass . '">' . $actionLabel . 'する</button>'
        . '</div>'
        . '</form>'
        . '<p class="note">※ 確定すると申請者にメールで通知されます。</p>';
    renderPage('値引き申請の' . $actionLabel, $content);
}

// ─── POSTリクエスト: 処理実行 ──────────────────────────────────
$comment = trim($_POST['comment'] ?? '');
$now     = date('Y-m-d H:i:s');
$newStatus = ($action === 'approve') ? 'approved' : 'rejected';

$data  = getData();
$found = false;
$updated = null;

foreach ($data['discount_approvals'] as &$a) {
    if (($a['email_action_token'] ?? '') !== $token) continue;

    // 再チェック（競合対策）
    if (!empty($a['deleted_at']))          { renderPage('エラー', '<h2>削除済みです</h2>', true); }
    if (($a['status'] ?? '') !== 'pending') { renderPage('エラー', '<h2>既に審査済みです</h2>', true); }
    if (!empty($a['email_token_used_at'])) { renderPage('エラー', '<h2>このリンクは使用済みです</h2>', true); }

    $a['status']               = $newStatus;
    $a['reviewed_by']          = 'email_action';
    $a['reviewed_at']          = $now;
    $a['review_comment']       = $comment;
    $a['updated_at']           = $now;
    $a['email_token_used_at']  = $now;
    $found   = true;
    $updated = $a;
    break;
}
unset($a);

if (!$found) {
    renderPage('エラー', '<h2>申請が見つかりません</h2>', true);
}

saveData($data);

// 結果メール送信
sendReviewResultEmailAction($updated);

// 完了ページ
$statusColor = ($action === 'approve') ? '#2e7d32' : '#c62828';
$icon        = ($action === 'approve') ? '✔' : '✘';
$content = '<h2 style="color:' . $statusColor . ';">' . $icon . ' ' . $actionLabel . 'しました</h2>'
    . '<table class="info-table">'
    . '<tr><th>案件名</th><td><strong>' . htmlspecialchars($updated['project_name']) . '</strong></td></tr>'
    . '<tr><th>申請者</th><td>' . htmlspecialchars($updated['applicant_name'] ?? $updated['applicant_email']) . '</td></tr>'
    . '<tr><th>結果</th><td><strong style="color:' . $statusColor . ';">' . $actionLabel . '</strong></td></tr>'
    . (!empty($comment) ? '<tr><th>コメント</th><td>' . nl2br(htmlspecialchars($comment)) . '</td></tr>' : '')
    . '<tr><th>処理日時</th><td>' . $now . '</td></tr>'
    . '</table>'
    . '<p style="margin-top:1.25rem;">申請者にメールで通知しました。</p>'
    . '<p><a href="' . htmlspecialchars(getBaseUrl() . '/pages/discount-approvals.php') . '" class="btn btn-secondary">一覧に戻る</a></p>';
renderPage('値引き申請 ' . $actionLabel . '完了', $content);

// ─── メール通知ヘルパー ─────────────────────────────────────────────

function getAdminUserEmailsForAction() {
    $data = getData();
    $emails = [];
    foreach ($data['employees'] ?? [] as $emp) {
        if (!empty($emp['deleted_at'])) continue;
        if (($emp['role'] ?? '') !== 'admin') continue;
        if (empty($emp['email'])) continue;
        $email = $emp['email'];
        if (is_string($email) && str_starts_with($email, 'enc:')) {
            require_once __DIR__ . '/../functions/encryption.php';
            try { $email = decryptValue($email); } catch (Exception $e) { continue; }
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) $emails[] = $email;
    }
    return array_unique($emails);
}

function sendReviewResultEmailAction($approval) {
    require_once __DIR__ . '/../functions/notification-functions.php';
    $adminEmails = getAdminUserEmailsForAction();
    $statusLabel = ($approval['status'] === 'approved') ? '承認' : '却下';

    $subject = '【値引き' . $statusLabel . '】' . $approval['project_name'];
    $body    = '<p>値引き申請の審査結果をお知らせします。</p>'
        . '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">'
        . '<tr><th>案件名</th><td>' . htmlspecialchars($approval['project_name']) . '</td></tr>'
        . '<tr><th>申請者</th><td>' . htmlspecialchars($approval['applicant_name']) . '</td></tr>'
        . '<tr><th>値引き前金額</th><td>¥' . number_format($approval['original_amount']) . '</td></tr>'
        . '<tr><th>値引き額</th><td>¥' . number_format($approval['discount_amount']) . '</td></tr>'
        . '<tr><th>値引き後金額</th><td>¥' . number_format($approval['original_amount'] - $approval['discount_amount']) . '</td></tr>'
        . '<tr><th>結果</th><td><strong>' . $statusLabel . '</strong></td></tr>'
        . '<tr><th>コメント</th><td>' . nl2br(htmlspecialchars($approval['review_comment'] ?? '')) . '</td></tr>'
        . '<tr><th>審査日時</th><td>' . htmlspecialchars($approval['reviewed_at']) . '</td></tr>'
        . '</table>';

    $applicantEmail = $approval['applicant_email'] ?? '';
    if ($applicantEmail) {
        sendNotificationEmail($applicantEmail, $subject, $body);
    }
    foreach ($adminEmails as $email) {
        if ($email !== $applicantEmail) {
            sendNotificationEmail($email, $subject, $body);
        }
    }
}
