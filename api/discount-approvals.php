<?php
/**
 * 値引き承認 CRUD API
 * pages/discount-approvals.php から呼び出される
 *
 * - 申請: 全ユーザー
 * - 承認/却下: adminのみ
 * - 承認/却下後に申請者+管理部(admin)にメール通知
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/notification-functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    initApi([
        'requireAuth'    => true,
        'requireCsrf'    => false,
        'allowedMethods' => ['GET'],
    ]);

    $action      = $_GET['action'] ?? '';
    $data        = getData();
    $currentUser = $_SESSION['user_email'];

    switch ($action) {

        case 'list':
            $approvals = filterDeleted($data['discount_approvals'] ?? []);
            // admin: 全件, それ以外: 自分の申請のみ
            if (!isAdmin()) {
                $approvals = array_values(array_filter($approvals, function($a) use ($currentUser) {
                    return ($a['applicant_email'] ?? '') === $currentUser;
                }));
            }
            usort($approvals, function($a, $b) {
                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
            });
            successResponse(['approvals' => array_values($approvals)]);
            break;

        default:
            errorResponse('不正なアクションです', 400);
    }
    exit;
}

// POSTリクエスト：CSRF必須
initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['POST'],
]);

$data        = getData();
$action      = $_POST['action'] ?? '';
$currentUser = $_SESSION['user_email'];
$userName    = $_SESSION['user_name'] ?? $currentUser;
$now         = date('Y-m-d H:i:s');

switch ($action) {

    case 'create':
        $projectName    = trim($_POST['project_name'] ?? '');
        $originalAmount = (int)($_POST['original_amount'] ?? 0);
        $discountAmount = (int)($_POST['discount_amount'] ?? 0);
        $reason         = trim($_POST['reason'] ?? '');

        if (empty($projectName)) errorResponse('案件名は必須です', 400);
        if ($originalAmount <= 0) errorResponse('値引き前金額を入力してください', 400);
        if ($discountAmount <= 0) errorResponse('値引き額を入力してください', 400);
        if ($discountAmount >= $originalAmount) errorResponse('値引き額が値引き前金額以上です', 400);
        if (empty($reason)) errorResponse('値引き理由は必須です', 400);

        $approval = [
            'id'                   => uniqid('da_'),
            'project_name'         => $projectName,
            'original_amount'      => $originalAmount,
            'discount_amount'      => $discountAmount,
            'reason'               => $reason,
            'status'               => 'pending',
            'applicant_email'      => $currentUser,
            'applicant_name'       => $userName,
            'created_at'           => $now,
            'updated_at'           => $now,
            'email_action_token'   => bin2hex(random_bytes(32)),
            'email_token_expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'email_token_used_at'  => null,
        ];

        if (!isset($data['discount_approvals'])) $data['discount_approvals'] = [];
        $data['discount_approvals'][] = $approval;
        saveData($data);

        // admin に申請通知メール
        sendApprovalNotificationToAdmin($approval);

        successResponse(['approval' => $approval]);
        break;

    case 'approve':
    case 'reject':
        if (!isAdmin()) errorResponse('承認権限がありません', 403);

        $id      = trim($_POST['id'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        if (empty($id)) errorResponse('IDは必須です', 400);

        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
        $found = false;
        foreach ($data['discount_approvals'] as &$approval) {
            if (($approval['id'] ?? '') !== $id) continue;
            if (!empty($approval['deleted_at'])) errorResponse('削除済みです', 400);
            if (($approval['status'] ?? '') !== 'pending') errorResponse('この申請は既に審査済みです', 400);

            $approval['status']         = $newStatus;
            $approval['reviewed_by']    = $currentUser;
            $approval['reviewed_at']    = $now;
            $approval['review_comment'] = $comment;
            $approval['updated_at']     = $now;
            $found = true;
            $updated = $approval;
            break;
        }
        unset($approval);

        if (!$found) errorResponse('申請が見つかりません', 404);
        saveData($data);

        // 申請者 + admin にメール通知
        sendReviewResultEmail($updated);

        successResponse(['approval' => $updated]);
        break;

    case 'delete':
        if (!canDelete()) errorResponse('削除権限がありません', 403);

        $id = trim($_POST['id'] ?? '');
        if (empty($id)) errorResponse('IDは必須です', 400);

        $found = false;
        foreach ($data['discount_approvals'] as &$approval) {
            if (($approval['id'] ?? '') !== $id) continue;
            $approval['deleted_at'] = $now;
            $approval['deleted_by'] = $currentUser;
            $found = true;
            break;
        }
        unset($approval);

        if (!$found) errorResponse('申請が見つかりません', 404);
        saveData($data);
        successResponse(['message' => '削除しました']);
        break;

    default:
        errorResponse('不正なアクションです', 400);
}

// ─── メール通知ヘルパー ─────────────────────────────────────────────

/**
 * adminロールを持つユーザーのメールアドレス一覧を返す
 */
function getAdminUserEmails() {
    $data = getData();
    $emails = [];
    foreach ($data['employees'] ?? [] as $emp) {
        if (!empty($emp['deleted_at'])) continue;
        if (($emp['role'] ?? '') !== 'admin') continue;
        if (empty($emp['email'])) continue;

        $email = $emp['email'];
        if (is_string($email) && str_starts_with($email, 'enc:')) {
            require_once __DIR__ . '/../functions/encryption.php';
            try {
                $email = decryptValue($email);
            } catch (Exception $e) {
                error_log('getAdminUserEmails: 復号失敗 - ' . $e->getMessage());
                continue;
            }
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $email;
        }
    }
    return array_unique($emails);
}

function sendApprovalNotificationToAdmin($approval) {
    $adminEmails = getAdminUserEmails();
    if (empty($adminEmails)) return;

    $subject = '【値引き承認申請】' . $approval['project_name'];
    $baseUrl  = getBaseUrl();
    $token    = $approval['email_action_token'] ?? '';
    $approveUrl = $baseUrl . '/api/discount-approval-action.php?token=' . urlencode($token) . '&action=approve';
    $rejectUrl  = $baseUrl . '/api/discount-approval-action.php?token=' . urlencode($token) . '&action=reject';
    $body    = '<p>値引き承認の申請が届きました。</p>'
        . '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">'
        . '<tr><th>案件名</th><td>' . htmlspecialchars($approval['project_name']) . '</td></tr>'
        . '<tr><th>申請者</th><td>' . htmlspecialchars($approval['applicant_name']) . '</td></tr>'
        . '<tr><th>値引き前金額</th><td>¥' . number_format($approval['original_amount']) . '</td></tr>'
        . '<tr><th>値引き額</th><td>¥' . number_format($approval['discount_amount']) . '</td></tr>'
        . '<tr><th>値引き後金額</th><td>¥' . number_format($approval['original_amount'] - $approval['discount_amount']) . '</td></tr>'
        . '<tr><th>理由</th><td>' . nl2br(htmlspecialchars($approval['reason'])) . '</td></tr>'
        . '<tr><th>申請日時</th><td>' . htmlspecialchars($approval['created_at']) . '</td></tr>'
        . '</table>'
        . '<p style="margin-top:20px;">メールから直接承認・却下できます（有効期限: 7日間）：</p>'
        . '<table cellpadding="0" cellspacing="0" border="0" style="margin-top:8px;">'
        . '<tr>'
        . '<td bgcolor="#ffffff" style="border-radius:12px;border:2px solid #1abc9c;background-color:#ffffff;">'
        .   '<a href="' . htmlspecialchars($approveUrl) . '" style="display:inline-block;padding:14px 28px;color:#1c2833;text-decoration:none;font-weight:600;font-size:15px;font-family:Arial,sans-serif;">&#10003; 承認</a>'
        . '</td>'
        . '<td width="12"></td>'
        . '<td bgcolor="#ffffff" style="border-radius:12px;border:2px solid #c0392b;background-color:#ffffff;">'
        .   '<a href="' . htmlspecialchars($rejectUrl) . '" style="display:inline-block;padding:14px 28px;color:#1c2833;text-decoration:none;font-weight:600;font-size:15px;font-family:Arial,sans-serif;">&#10007; 却下</a>'
        . '</td>'
        . '</tr>'
        . '</table>'
        . '<p style="color:#888;font-size:12px;margin-top:16px;">※ リンクをクリックすると確認画面が表示されます。<br>※ 一度使用したリンクは無効になります。</p>';

    foreach ($adminEmails as $email) {
        sendNotificationEmail($email, $subject, $body);
    }
}

function sendReviewResultEmail($approval) {
    $adminEmails = getAdminUserEmails();
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

    // 申請者に通知
    $applicantEmail = $approval['applicant_email'] ?? '';
    if ($applicantEmail) {
        sendNotificationEmail($applicantEmail, $subject, $body);
    }

    // 管理部全員に通知
    foreach ($adminEmails as $email) {
        if ($email !== $applicantEmail) {
            sendNotificationEmail($email, $subject, $body);
        }
    }
}
