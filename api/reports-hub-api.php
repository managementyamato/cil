<?php
/**
 * 申請・報告 統合API
 *
 * 4機能を統合:
 *   - weekly_reports  (週報)
 *   - discount_approvals (値引き申請)
 *   - deals (商談記録)
 *   - leads (リード管理)
 *
 * パラメータ: type + action
 * 全submit/create時にadminへメール通知（内容付き）
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/notification-functions.php';

// ─── GET ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    initApi(['requireAuth' => true, 'requireCsrf' => false, 'allowedMethods' => ['GET']]);

    $type   = $_GET['type']   ?? '';
    $action = $_GET['action'] ?? '';
    $data   = getData();
    $currentUser = $_SESSION['user_email'];

    switch ($type) {

        // ── 週報 ──
        case 'report':
            if ($action !== 'list') errorResponse('不正なアクションです', 400);
            $reports = filterDeleted($data['weekly_reports'] ?? []);
            if (!isAdmin()) {
                // 一般ユーザー: 自分の週報のみ（下書き含む）
                $reports = array_values(array_filter($reports, fn($r) => ($r['user_email'] ?? '') === $currentUser));
            } else {
                // 管理者: 自分の週報は全て + 他人の週報は提出済みのみ
                $reports = array_values(array_filter($reports, fn($r) =>
                    ($r['user_email'] ?? '') === $currentUser || ($r['status'] ?? '') === 'submitted'
                ));
            }
            usort($reports, fn($a, $b) => strcmp($b['week_start'] ?? '', $a['week_start'] ?? ''));
            successResponse(['items' => array_values($reports)]);
            break;

        // ── 値引き申請 ──
        case 'approval':
            if ($action !== 'list') errorResponse('不正なアクションです', 400);
            $approvals = filterDeleted($data['discount_approvals'] ?? []);
            if (!isAdmin()) {
                $approvals = array_values(array_filter($approvals, fn($a) => ($a['applicant_email'] ?? '') === $currentUser));
            }
            usort($approvals, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
            successResponse(['items' => array_values($approvals)]);
            break;

        // ── 商談記録 ──
        case 'deal':
            if ($action !== 'list') errorResponse('不正なアクションです', 400);
            $deals = filterDeleted($data['deals'] ?? []);
            usort($deals, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
            // 従業員リストも返す（担当者選択用）
            $employees = filterDeleted($data['employees'] ?? []);
            $empNames = [];
            foreach ($employees as $e) { $empNames[] = ['name' => $e['name'] ?? '', 'email' => $e['email'] ?? '']; }
            successResponse(['items' => array_values($deals), 'employees' => $empNames]);
            break;

        // ── リード管理 ──
        case 'lead':
            if ($action !== 'list') errorResponse('不正なアクションです', 400);
            $leads = filterDeleted($data['leads'] ?? []);
            usort($leads, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
            $employees = filterDeleted($data['employees'] ?? []);
            $empNames = [];
            foreach ($employees as $e) { $empNames[] = ['name' => $e['name'] ?? '', 'email' => $e['email'] ?? '']; }
            successResponse(['items' => array_values($leads), 'employees' => $empNames]);
            break;

        default:
            errorResponse('不正なtypeです', 400);
    }
    exit;
}

// ─── POST ────────────────────────────────────────────────────────────
initApi(['requireAuth' => true, 'requireCsrf' => true, 'allowedMethods' => ['POST']]);

$data        = getData();
$type        = $_POST['type']   ?? '';
$action      = $_POST['action'] ?? '';
$currentUser = $_SESSION['user_email'];
$userName    = $_SESSION['user_name'] ?? $currentUser;
$now         = date('Y-m-d H:i:s');

switch ($type) {

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  週報
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'report':
        $allowedTags = '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><h2><h3><img><a>';

        switch ($action) {

            case 'save':
            case 'submit':
                $weekStart = trim($_POST['week_start'] ?? '');
                if (empty($weekStart)) errorResponse('対象週は必須です', 400);

                // week_start = 提出日（金曜）、week_end は同日（後方互換用）
                $weekEnd = $weekStart;

                $sectionKeys = ['sec_role','sec_report','sec_issues','sec_next_goals','sec_second_area','sec_misc'];
                $sections = [];
                foreach ($sectionKeys as $key) {
                    $val = trim($_POST[$key] ?? '');
                    $val = strip_tags($val, $allowedTags);
                    $sections[$key] = sanitizeImgSrc($val);
                }
                $sections['private_message']    = trim($_POST['private_message'] ?? '');
                $sections['private_recipients'] = json_decode($_POST['private_recipients'] ?? '[]', true) ?: [];

                // 既存レポート検索
                if (!isset($data['weekly_reports'])) $data['weekly_reports'] = [];
                $existingIdx = null;
                foreach ($data['weekly_reports'] as $idx => $r) {
                    if (($r['user_email'] ?? '') === $currentUser && ($r['week_start'] ?? '') === $weekStart && empty($r['deleted_at'])) {
                        $existingIdx = $idx;
                        break;
                    }
                }

                $isSubmit = ($action === 'submit');
                if ($existingIdx !== null) {
                    foreach ($sections as $k => $v) $data['weekly_reports'][$existingIdx][$k] = $v;
                    $data['weekly_reports'][$existingIdx]['status']     = $isSubmit ? 'submitted' : 'draft';
                    $data['weekly_reports'][$existingIdx]['updated_at'] = $now;
                    if ($isSubmit) {
                        $data['weekly_reports'][$existingIdx]['submitted_at'] = $now;
                        // 確認用トークン生成（メールからの確認用）
                        $data['weekly_reports'][$existingIdx]['confirm_token'] = bin2hex(random_bytes(24));
                    }
                    $report = $data['weekly_reports'][$existingIdx];
                } else {
                    $report = array_merge([
                        'id'         => uniqid('wr_'),
                        'user_email' => $currentUser,
                        'user_name'  => $userName,
                        'week_start' => $weekStart,
                        'week_end'   => $weekEnd,
                        'status'     => $isSubmit ? 'submitted' : 'draft',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $sections);
                    if ($isSubmit) {
                        $report['submitted_at'] = $now;
                        $report['confirm_token'] = bin2hex(random_bytes(24));
                    }
                    $data['weekly_reports'][] = $report;
                }
                saveData($data);

                if ($isSubmit) {
                    sendHubEmail('report_submit', $report);
                }
                successResponse(['item' => $report]);
                break;

            case 'confirm':
                if (!isAdmin()) errorResponse('確認権限がありません', 403);
                $id = trim($_POST['id'] ?? '');
                if (empty($id)) errorResponse('IDは必須です', 400);

                $found = false;
                $confirmedReport = null;
                foreach ($data['weekly_reports'] as &$r) {
                    if (($r['id'] ?? '') !== $id) continue;
                    if (!empty($r['deleted_at'])) errorResponse('削除済みです', 400);
                    if (($r['status'] ?? '') !== 'submitted') errorResponse('提出済みの週報のみ確認できます', 400);
                    if (!empty($r['confirmed_at'])) errorResponse('既に確認済みです', 400);

                    $r['confirmed_at']      = $now;
                    $r['confirmed_by']      = $currentUser;
                    $r['confirmed_by_name'] = $userName;
                    $r['updated_at']        = $now;
                    $found = true;
                    $confirmedReport = $r;
                    break;
                }
                unset($r);
                if (!$found) errorResponse('週報が見つかりません', 404);
                saveData($data);

                // 提出者に確認通知メール
                sendHubEmail('report_confirmed', $confirmedReport);

                successResponse(['item' => $confirmedReport]);
                break;

            case 'delete':
                $id = trim($_POST['id'] ?? '');
                if (empty($id)) errorResponse('IDは必須です', 400);

                $found = false;
                foreach ($data['weekly_reports'] as &$r) {
                    if (($r['id'] ?? '') !== $id) continue;
                    // 本人 or admin のみ削除可
                    $isOwner = ($r['user_email'] ?? '') === $currentUser;
                    if (!$isOwner && !canDelete()) errorResponse('削除権限がありません', 403);
                    $r['deleted_at'] = $now;
                    $r['deleted_by'] = $currentUser;
                    $found = true;
                    break;
                }
                unset($r);
                if (!$found) errorResponse('週報が見つかりません', 404);
                saveData($data);
                successResponse(['message' => '削除しました']);
                break;

            case 'set_drive_folder':
                if (!isAdmin()) errorResponse('権限がありません', 403);
                $folderId   = trim($_POST['folder_id'] ?? '');
                $folderName = trim($_POST['folder_name'] ?? '');
                if (empty($folderId)) errorResponse('フォルダIDは必須です', 400);

                require_once __DIR__ . '/google-drive.php';
                try {
                    $drive = new GoogleDriveClient();
                    $drive->saveWeeklyReportFolder($folderId, $folderName);
                    successResponse(['folder_id' => $folderId, 'folder_name' => $folderName], '保存先を更新しました');
                } catch (Exception $e) {
                    errorResponse('保存失敗: ' . $e->getMessage(), 500);
                }
                break;

            default:
                errorResponse('不正なアクションです', 400);
        }
        break;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  値引き申請
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'approval':
        switch ($action) {

            case 'create':
                $projectName    = trim($_POST['project_name'] ?? '');
                $rentalPeriod   = trim($_POST['rental_period'] ?? '');
                $salesAmount    = trim($_POST['sales_amount'] ?? '');
                $originalAmount = (int)($_POST['original_amount'] ?? 0);
                $discountAmount = (int)($_POST['discount_amount'] ?? 0);
                $reason         = trim($_POST['reason'] ?? '');

                if (empty($projectName)) errorResponse('案件名は必須です', 400);
                if (empty($rentalPeriod)) errorResponse('レンタル期間は必須です', 400);
                if (empty($salesAmount)) errorResponse('販売額は必須です', 400);
                if ($originalAmount <= 0) errorResponse('値引き前金額を入力してください', 400);
                if ($discountAmount <= 0) errorResponse('値引き額を入力してください', 400);
                if ($discountAmount >= $originalAmount) errorResponse('値引き額が値引き前金額以上です', 400);
                if (empty($reason)) errorResponse('値引き理由は必須です', 400);

                $approval = [
                    'id'              => uniqid('da_'),
                    'project_name'    => $projectName,
                    'rental_period'   => $rentalPeriod,
                    'sales_amount'    => $salesAmount,
                    'original_amount' => $originalAmount,
                    'discount_amount' => $discountAmount,
                    'reason'          => $reason,
                    'status'          => 'pending',
                    'applicant_email' => $currentUser,
                    'applicant_name'  => $userName,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                    'email_action_token'    => bin2hex(random_bytes(32)),
                    'email_token_expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
                    'email_token_used_at'   => null,
                    // PDF添付 (Google Drive)
                    'drive_file_id'       => trim($_POST['drive_file_id'] ?? ''),
                    'drive_view_link'     => trim($_POST['drive_view_link'] ?? ''),
                    'drive_download_link' => trim($_POST['drive_download_link'] ?? ''),
                    'drive_file_name'     => trim($_POST['drive_file_name'] ?? ''),
                    'original_name'       => trim($_POST['original_name'] ?? ''),
                ];

                if (!isset($data['discount_approvals'])) $data['discount_approvals'] = [];
                $data['discount_approvals'][] = $approval;
                saveData($data);

                sendHubEmail('approval_create', $approval);
                successResponse(['item' => $approval]);
                break;

            case 'set_drive_folder':
                if (!isAdmin()) errorResponse('権限がありません', 403);
                $folderId   = trim($_POST['folder_id'] ?? '');
                $folderName = trim($_POST['folder_name'] ?? '');
                if (empty($folderId)) errorResponse('フォルダIDは必須です', 400);

                require_once __DIR__ . '/google-drive.php';
                try {
                    $drive = new GoogleDriveClient();
                    $drive->saveDiscountApprovalFolder($folderId, $folderName);
                    successResponse(['folder_id' => $folderId, 'folder_name' => $folderName], '保存先を更新しました');
                } catch (Exception $e) {
                    errorResponse('保存失敗: ' . $e->getMessage(), 500);
                }
                break;

            case 'approve':
            case 'reject':
                if (!isAdmin()) errorResponse('承認権限がありません', 403);

                $id      = trim($_POST['id'] ?? '');
                $comment = trim($_POST['comment'] ?? '');
                if (empty($id)) errorResponse('IDは必須です', 400);

                $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
                $found = false;
                foreach ($data['discount_approvals'] as &$appr) {
                    if (($appr['id'] ?? '') !== $id) continue;
                    if (!empty($appr['deleted_at'])) errorResponse('削除済みです', 400);
                    if (($appr['status'] ?? '') !== 'pending') errorResponse('この申請は既に審査済みです', 400);

                    $appr['status']         = $newStatus;
                    $appr['reviewed_by']    = $currentUser;
                    $appr['reviewed_at']    = $now;
                    $appr['review_comment'] = $comment;
                    $appr['updated_at']     = $now;
                    $found = true;
                    $updated = $appr;
                    break;
                }
                unset($appr);

                if (!$found) errorResponse('申請が見つかりません', 404);
                saveData($data);

                sendHubEmail('approval_review', $updated);
                successResponse(['item' => $updated]);
                break;

            case 'delete':
                if (!canDelete()) errorResponse('削除権限がありません', 403);
                $id = trim($_POST['id'] ?? '');
                if (empty($id)) errorResponse('IDは必須です', 400);

                $found = false;
                foreach ($data['discount_approvals'] as &$appr) {
                    if (($appr['id'] ?? '') !== $id) continue;
                    $appr['deleted_at'] = $now;
                    $appr['deleted_by'] = $currentUser;
                    $found = true;
                    break;
                }
                unset($appr);
                if (!$found) errorResponse('申請が見つかりません', 404);
                saveData($data);
                successResponse(['message' => '削除しました']);
                break;

            default:
                errorResponse('不正なアクションです', 400);
        }
        break;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  商談記録
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'deal':
        switch ($action) {

            case 'create':
                $customerName = trim($_POST['customer_name'] ?? '');
                $title        = trim($_POST['title'] ?? '');
                if (empty($customerName)) errorResponse('顧客名は必須です', 400);
                if (empty($title)) errorResponse('商談名は必須です', 400);

                $deal = [
                    'id'                  => uniqid('deal_'),
                    'customer_name'       => $customerName,
                    'title'               => $title,
                    'amount'              => (int)($_POST['amount'] ?? 0),
                    'probability'         => (int)($_POST['probability'] ?? 0),
                    'stage'               => trim($_POST['stage'] ?? 'リード'),
                    'assignee'            => trim($_POST['assignee'] ?? ''),
                    'expected_close_date' => trim($_POST['expected_close_date'] ?? ''),
                    'memo'                => trim($_POST['memo'] ?? ''),
                    'created_by'          => $currentUser,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];

                if (!isset($data['deals'])) $data['deals'] = [];
                $data['deals'][] = $deal;
                saveData($data);

                sendHubEmail('deal_create', $deal);
                successResponse(['item' => $deal]);
                break;

            case 'update':
                $id = trim($_POST['id'] ?? '');
                if (empty($id)) errorResponse('IDは必須です', 400);

                $found = false;
                foreach ($data['deals'] as &$deal) {
                    if (($deal['id'] ?? '') !== $id) continue;
                    if (!empty($deal['deleted_at'])) errorResponse('削除済みです', 400);

                    $deal['customer_name']       = trim($_POST['customer_name'] ?? $deal['customer_name']);
                    $deal['title']               = trim($_POST['title'] ?? $deal['title']);
                    $deal['amount']              = (int)($_POST['amount'] ?? $deal['amount'] ?? 0);
                    $deal['probability']         = (int)($_POST['probability'] ?? $deal['probability'] ?? 0);
                    $deal['stage']               = trim($_POST['stage'] ?? $deal['stage'] ?? 'リード');
                    $deal['assignee']            = trim($_POST['assignee'] ?? $deal['assignee'] ?? '');
                    $deal['expected_close_date'] = trim($_POST['expected_close_date'] ?? $deal['expected_close_date'] ?? '');
                    $deal['memo']                = trim($_POST['memo'] ?? $deal['memo'] ?? '');
                    $deal['updated_at']          = $now;
                    $found = true;
                    $updated = $deal;
                    break;
                }
                unset($deal);
                if (!$found) errorResponse('商談が見つかりません', 404);
                saveData($data);
                successResponse(['item' => $updated]);
                break;

            case 'delete':
                if (!canDelete()) errorResponse('削除権限がありません', 403);
                $id = trim($_POST['id'] ?? '');
                if (empty($id)) errorResponse('IDは必須です', 400);

                $found = false;
                foreach ($data['deals'] as &$deal) {
                    if (($deal['id'] ?? '') !== $id) continue;
                    $deal['deleted_at'] = $now;
                    $deal['deleted_by'] = $currentUser;
                    $found = true;
                    break;
                }
                unset($deal);
                if (!$found) errorResponse('商談が見つかりません', 404);
                saveData($data);
                successResponse(['message' => '削除しました']);
                break;

            default:
                errorResponse('不正なアクションです', 400);
        }
        break;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  リード管理
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    case 'lead':
        switch ($action) {

            case 'create':
                $companyName = trim($_POST['company_name'] ?? '');
                if (empty($companyName)) errorResponse('会社名は必須です', 400);

                $lead = [
                    'id'             => uniqid('ld_'),
                    'company_name'   => $companyName,
                    'person_name'    => trim($_POST['person_name'] ?? ''),
                    'title'          => trim($_POST['title'] ?? ''),
                    'phone'          => trim($_POST['phone'] ?? ''),
                    'email'          => trim($_POST['email'] ?? ''),
                    'memo'           => trim($_POST['memo'] ?? ''),
                    'status'         => trim($_POST['status'] ?? '未接触'),
                    'sales_assignee' => trim($_POST['sales_assignee'] ?? ''),
                    'sales_email'    => trim($_POST['sales_email'] ?? ''),
                    'created_by'     => $currentUser,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                if (!isset($data['leads'])) $data['leads'] = [];
                $data['leads'][] = $lead;
                saveData($data);

                sendHubEmail('lead_create', $lead);
                successResponse(['item' => $lead]);
                break;

            case 'update':
                $id = trim($_POST['id'] ?? '');
                if (empty($id)) errorResponse('IDは必須です', 400);

                $found = false;
                foreach ($data['leads'] as &$lead) {
                    if (($lead['id'] ?? '') !== $id) continue;
                    if (!empty($lead['deleted_at'])) errorResponse('削除済みです', 400);

                    $lead['company_name']   = trim($_POST['company_name'] ?? $lead['company_name']);
                    $lead['person_name']    = trim($_POST['person_name'] ?? $lead['person_name'] ?? '');
                    $lead['title']          = trim($_POST['title'] ?? $lead['title'] ?? '');
                    $lead['phone']          = trim($_POST['phone'] ?? $lead['phone'] ?? '');
                    $lead['email']          = trim($_POST['email'] ?? $lead['email'] ?? '');
                    $lead['memo']           = trim($_POST['memo'] ?? $lead['memo'] ?? '');
                    $lead['status']         = trim($_POST['status'] ?? $lead['status'] ?? '未接触');
                    $lead['sales_assignee'] = trim($_POST['sales_assignee'] ?? $lead['sales_assignee'] ?? '');
                    $lead['sales_email']    = trim($_POST['sales_email'] ?? $lead['sales_email'] ?? '');
                    $lead['updated_at']     = $now;
                    $found = true;
                    $updated = $lead;
                    break;
                }
                unset($lead);
                if (!$found) errorResponse('リードが見つかりません', 404);
                saveData($data);
                successResponse(['item' => $updated]);
                break;

            case 'delete':
                if (!canDelete()) errorResponse('削除権限がありません', 403);
                $id = trim($_POST['id'] ?? '');
                if (empty($id)) errorResponse('IDは必須です', 400);

                $found = false;
                foreach ($data['leads'] as &$lead) {
                    if (($lead['id'] ?? '') !== $id) continue;
                    $lead['deleted_at'] = $now;
                    $lead['deleted_by'] = $currentUser;
                    $found = true;
                    break;
                }
                unset($lead);
                if (!$found) errorResponse('リードが見つかりません', 404);
                saveData($data);
                successResponse(['message' => '削除しました']);
                break;

            default:
                errorResponse('不正なアクションです', 400);
        }
        break;

    default:
        errorResponse('不正なtypeです', 400);
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
//  ヘルパー: img src サニタイズ
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
function sanitizeImgSrc($html) {
    // <img> サニタイズ: /uploads/, /api/serve-weekly-file.php, Google Drive画像を許可
    $html = preg_replace_callback('/<img([^>]*)>/i', function($m) {
        $attrs = $m[1];
        if (preg_match('/src=["\']([^"\']*)["\']/', $attrs, $srcMatch)) {
            $src = $srcMatch[1];
            if (!preg_match('#^(/uploads/|/api/serve-weekly-file\.php|https://lh3\.googleusercontent\.com/)#', $src)) return '';
            $alt = '';
            if (preg_match('/alt=["\']([^"\']*)["\']/', $attrs, $altMatch)) {
                $alt = ' alt="' . htmlspecialchars($altMatch[1]) . '"';
            }
            return '<img src="' . htmlspecialchars($src) . '"' . $alt . '>';
        }
        return '';
    }, $html);

    // <a> サニタイズ: /uploads/, /api/serve-weekly-file.php, https:// のみ許可
    $html = preg_replace_callback('/<a([^>]*)>(.*?)<\/a>/is', function($m) {
        $attrs = $m[1];
        $inner = $m[2];
        if (preg_match('/href=["\']([^"\']*)["\']/', $attrs, $hrefMatch)) {
            $href = $hrefMatch[1];
            if (!preg_match('#^(/uploads/|/api/serve-weekly-file\.php|https?://)#', $href)) return strip_tags($inner);
            return '<a href="' . htmlspecialchars($href) . '" target="_blank" rel="noopener">' . $inner . '</a>';
        }
        return strip_tags($inner);
    }, $html);

    return $html;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
//  ヘルパー: adminメールアドレス取得
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
function getAdminEmails() {
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

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
//  統合メール通知
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
function sendHubEmail($eventType, $record) {
    $adminEmails = getAdminEmails();
    if (empty($adminEmails)) return;

    $subject = '';
    $body    = '';
    $ts      = function($s) { return htmlspecialchars($s ?? ''); };
    $tableStyle = 'border-collapse:collapse;width:100%;max-width:680px;';
    $thStyle = 'background:#f5f5f5;padding:8px 12px;text-align:left;white-space:nowrap;font-size:13px;border:1px solid #ddd;';
    $tdStyle = 'padding:8px 12px;font-size:13px;border:1px solid #ddd;line-height:1.6;';

    switch ($eventType) {

        // ── 週報提出 ──
        case 'report_submit':
            $name    = $ts($record['user_name'] ?? $record['user_email'] ?? '');
            $wStart  = $ts($record['week_start'] ?? '');
            $subject = "【週報提出】{$name} さん（{$wStart}）";

            $sectionLabels = [
                'sec_role'        => '今期の役割',
                'sec_report'      => '今週の報告',
                'sec_issues'      => '現在抱えている課題',
                'sec_next_goals'  => '次週目標・計画',
                'sec_second_area' => 'いま思いつく第二領域活動',
                'sec_misc'        => '報告・連絡・相談事項',
            ];
            $rows = '';
            $emailBaseUrl = getBaseUrl();
            foreach ($sectionLabels as $key => $label) {
                $content = $record[$key] ?? '';
                if (empty(trim(strip_tags($content)))) continue;

                // 相対パスの画像を絶対URLに変換 + メール用スタイル追加
                // /api/serve-weekly-file.php?f=xxx 形式
                $content = preg_replace_callback(
                    '/<img\s+src=["\']\/api\/serve-weekly-file\.php\?f=([^"\'&]+)["\']([^>]*)>/i',
                    function($m) use ($emailBaseUrl) {
                        return '<img src="' . $emailBaseUrl . '/api/serve-weekly-file.php?f=' . $m[1] . '" style="max-width:100%;height:auto;border-radius:6px;margin:4px 0;"' . $m[2] . '>';
                    },
                    $content
                );
                // 旧形式（/uploads/）も対応
                $content = preg_replace(
                    '/<img\s+src=["\']\/uploads\/weekly-reports\/([^"\']+)["\']([^>]*)>/i',
                    '<img src="' . $emailBaseUrl . '/api/serve-weekly-file.php?f=$1" style="max-width:100%;height:auto;border-radius:6px;margin:4px 0;"$2>',
                    $content
                );
                // リンクの相対パスを絶対URLに変換
                $content = preg_replace(
                    '/href=["\']\/api\/serve-weekly-file\.php\?f=([^"\'&]+)["\']/i',
                    'href="' . $emailBaseUrl . '/api/serve-weekly-file.php?f=$1"',
                    $content
                );
                $content = preg_replace(
                    '/href=["\']\/uploads\/weekly-reports\/([^"\']+)["\']/i',
                    'href="' . $emailBaseUrl . '/api/serve-weekly-file.php?f=$1"',
                    $content
                );
                // <a>タグにスタイル追加
                $content = preg_replace(
                    '/<a\s+href=/i',
                    '<a style="color:#2980b9;text-decoration:underline;" href=',
                    $content
                );
                // プレーンURLをクリック可能にする（既にリンク化されていないもの）
                $content = preg_replace(
                    '/(?<!["\'>])(https?:\/\/[^\s<>"\']+)/i',
                    '<a href="$1" target="_blank" style="color:#2980b9;text-decoration:underline;">$1</a>',
                    $content
                );
                $rows .= "<tr><td colspan=\"2\" style=\"background:#f5f5f5;padding:6px 12px;font-weight:bold;font-size:13px;border:1px solid #ddd;\">" . $ts($label) . "</td></tr>"
                    . "<tr><td colspan=\"2\" style=\"padding:8px 12px;font-size:13px;border:1px solid #ddd;line-height:1.6;\">{$content}</td></tr>";
            }

            // メールから確認ボタン
            $confirmToken = $record['confirm_token'] ?? '';
            $confirmHtml = '';
            if (!empty($confirmToken)) {
                $baseUrl = getBaseUrl();
                $confirmUrl = $baseUrl . '/api/report-confirm-action.php?token=' . urlencode($confirmToken);
                $confirmHtml = '<p style="margin-top:20px;">内容を確認したら「確認」ボタンを押してください:</p>'
                    . '<table cellpadding="0" cellspacing="0" border="0" style="margin-top:8px;">'
                    . '<tr>'
                    . '<td bgcolor="#ffffff" style="border-radius:12px;border:2px solid #27ae60;background-color:#ffffff;">'
                    .   '<a href="' . htmlspecialchars($confirmUrl) . '" style="display:inline-block;padding:14px 28px;color:#1c2833;text-decoration:none;font-weight:600;font-size:15px;font-family:Arial,sans-serif;">&#10003; 確認</a>'
                    . '</td>'
                    . '</tr>'
                    . '</table>'
                    . '<p style="color:#888;font-size:12px;margin-top:12px;">※ リンクをクリックすると確認画面が表示されます。</p>';
            }

            $body = "<p>{$name} さんが週報を提出しました。</p>"
                . "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"{$tableStyle}\">"
                . "<tr><th style=\"{$thStyle}\">提出者</th><td style=\"{$tdStyle}\">{$name}</td></tr>"
                . "<tr><th style=\"{$thStyle}\">提出日</th><td style=\"{$tdStyle}\">{$wStart}</td></tr>"
                . "<tr><th style=\"{$thStyle}\">提出日時</th><td style=\"{$tdStyle}\">" . $ts($record['submitted_at'] ?? '') . "</td></tr>"
                . $rows . '</table>'
                . $confirmHtml;
            break;

        // ── 週報確認通知（提出者へ） ──
        case 'report_confirmed':
            $submitterEmail = $record['user_email'] ?? '';
            $submitterName  = $ts($record['user_name'] ?? '');
            $confirmerName  = $ts($record['confirmed_by_name'] ?? '');
            $wStart         = $ts($record['week_start'] ?? '');
            $subject = "【週報確認済み】{$wStart} の週報が確認されました";

            $body = "<p>{$submitterName} さんの週報（{$wStart}）が確認されました。</p>"
                . "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"{$tableStyle}\">"
                . "<tr><th style=\"{$thStyle}\">確認者</th><td style=\"{$tdStyle}\">{$confirmerName}</td></tr>"
                . "<tr><th style=\"{$thStyle}\">確認日時</th><td style=\"{$tdStyle}\">" . $ts($record['confirmed_at'] ?? '') . "</td></tr>"
                . '</table>';

            // 管理者ではなく提出者に送信
            if (!empty($submitterEmail) && filter_var($submitterEmail, FILTER_VALIDATE_EMAIL)) {
                sendNotificationEmail($submitterEmail, $subject, $body);
            }
            return; // 管理者全員には送らない


        // ── 値引き申請 ──
        case 'approval_create':
            $subject = '【値引き申請】' . ($record['project_name'] ?? '');
            $afterAmount = $record['original_amount'] - $record['discount_amount'];
            $token = $record['email_action_token'] ?? '';
            $baseUrl = getBaseUrl();
            $approveUrl = $baseUrl . '/api/discount-approval-action.php?token=' . urlencode($token) . '&action=approve';
            $rejectUrl  = $baseUrl . '/api/discount-approval-action.php?token=' . urlencode($token) . '&action=reject';

            // PDF添付情報
            $driveFileId    = $record['drive_file_id']       ?? '';
            $driveViewLink  = $record['drive_view_link']     ?? '';
            $driveDlLink    = $record['drive_download_link'] ?? '';
            $driveFileName  = $record['drive_file_name']     ?? '';
            $originalName   = $record['original_name']       ?? '';

            $pdfRowHtml = '';
            $pdfButtonHtml = '';
            if (!empty($driveViewLink)) {
                $displayName = !empty($originalName) ? $originalName : ($driveFileName ?: '添付PDF');
                $pdfRowHtml = "<tr><th style=\"{$thStyle}\">添付PDF</th><td style=\"{$tdStyle}\">"
                    . '<a href="' . htmlspecialchars($driveViewLink) . '" target="_blank" style="color:#2980b9;text-decoration:underline;">'
                    . $ts($displayName) . '</a>'
                    . '</td></tr>';
                $pdfButtonHtml = '<p style="margin-top:16px;">申請書PDFを確認:</p>'
                    . '<table cellpadding="0" cellspacing="0" border="0" style="margin-top:4px;">'
                    . '<tr>'
                    . '<td bgcolor="#ffffff" style="border-radius:12px;border:2px solid #2980b9;background-color:#ffffff;">'
                    .   '<a href="' . htmlspecialchars($driveViewLink) . '" target="_blank" style="display:inline-block;padding:12px 24px;color:#1c2833;text-decoration:none;font-weight:600;font-size:14px;font-family:Arial,sans-serif;">Driveで申請書PDFを開く</a>'
                    . '</td>'
                    . '</tr>'
                    . '</table>'
                    . '<p style="color:#666;font-size:12px;margin-top:8px;">※ このメールには申請書PDFが添付されています。メールクライアントで直接開いて確認できます。</p>';
            }

            $body = '<p>値引き承認の申請が届きました。</p>'
                . "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"{$tableStyle}\">"
                . "<tr><th style=\"{$thStyle}\">案件名</th><td style=\"{$tdStyle}\">" . $ts($record['project_name']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">申請者</th><td style=\"{$tdStyle}\">" . $ts($record['applicant_name']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">レンタル期間</th><td style=\"{$tdStyle}\">" . $ts($record['rental_period'] ?? '') . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">販売額</th><td style=\"{$tdStyle}\">" . $ts($record['sales_amount'] ?? '') . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">値引き前金額</th><td style=\"{$tdStyle}\">¥" . number_format($record['original_amount']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">値引き額</th><td style=\"{$tdStyle}\">¥" . number_format($record['discount_amount']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">値引き後金額</th><td style=\"{$tdStyle}\">¥" . number_format($afterAmount) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">理由</th><td style=\"{$tdStyle}\">" . nl2br($ts($record['reason'])) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">申請日時</th><td style=\"{$tdStyle}\">" . $ts($record['created_at']) . '</td></tr>'
                . $pdfRowHtml
                . '</table>'
                . $pdfButtonHtml
                . '<p style="margin-top:20px;">メールから直接承認・却下できます（有効期限: 7日間）：</p>'
                . '<table cellpadding="0" cellspacing="0" border="0" style="margin-top:8px;">'
                . '<tr>'
                . '<td bgcolor="#ffffff" style="border-radius:12px;border:2px solid #1abc9c;background-color:#ffffff;">'
                .   '<a href="' . htmlspecialchars($approveUrl) . '" style="display:inline-block;padding:14px 28px;color:#1c2833;text-decoration:none;font-weight:600;font-size:15px;font-family:Arial,sans-serif;">&#10003; 承認</a>'
                . '</td>'
                . '<td style="width:12px;"></td>'
                . '<td bgcolor="#ffffff" style="border-radius:12px;border:2px solid #e74c3c;background-color:#ffffff;">'
                .   '<a href="' . htmlspecialchars($rejectUrl) . '" style="display:inline-block;padding:14px 28px;color:#1c2833;text-decoration:none;font-weight:600;font-size:15px;font-family:Arial,sans-serif;">&#10007; 却下</a>'
                . '</td>'
                . '</tr>'
                . '</table>'
                . '<p style="color:#888;font-size:12px;margin-top:16px;">※ リンクをクリックすると確認画面が表示されます。<br>※ 一度使用したリンクは無効になります。</p>';

            // PDF添付がある場合はDriveからダウンロードして添付
            $approvalAttachments = [];
            if (!empty($driveFileId)) {
                try {
                    require_once __DIR__ . '/google-drive.php';
                    $drive = new GoogleDriveClient();
                    $pdfContent = $drive->getFileContent($driveFileId);
                    if ($pdfContent !== false && strlen($pdfContent) > 0) {
                        $attachName = !empty($originalName) ? $originalName : ($driveFileName ?: 'discount_approval.pdf');
                        $approvalAttachments[] = [
                            'content' => $pdfContent,
                            'name'    => $attachName,
                            'mime'    => 'application/pdf',
                        ];
                    }
                } catch (Exception $e) {
                    error_log('[sendHubEmail] PDF fetch failed: ' . $e->getMessage());
                }
            }

            // 添付がある場合は添付付きで送信
            if (!empty($approvalAttachments)) {
                foreach ($adminEmails as $email) {
                    sendNotificationEmailWithAttachment($email, $subject, $body, $approvalAttachments);
                }
                return;
            }
            break;

        // ── 値引き審査結果 ──
        case 'approval_review':
            $statusLabel = ($record['status'] === 'approved') ? '承認' : '却下';
            $subject = "【値引き{$statusLabel}】" . ($record['project_name'] ?? '');
            $afterAmount = $record['original_amount'] - $record['discount_amount'];
            $body = '<p>値引き申請の審査結果をお知らせします。</p>'
                . "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"{$tableStyle}\">"
                . "<tr><th style=\"{$thStyle}\">案件名</th><td style=\"{$tdStyle}\">" . $ts($record['project_name']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">申請者</th><td style=\"{$tdStyle}\">" . $ts($record['applicant_name']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">レンタル期間</th><td style=\"{$tdStyle}\">" . $ts($record['rental_period'] ?? '') . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">販売額</th><td style=\"{$tdStyle}\">" . $ts($record['sales_amount'] ?? '') . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">値引き前金額</th><td style=\"{$tdStyle}\">¥" . number_format($record['original_amount']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">値引き額</th><td style=\"{$tdStyle}\">¥" . number_format($record['discount_amount']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">値引き後金額</th><td style=\"{$tdStyle}\">¥" . number_format($afterAmount) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">結果</th><td style=\"{$tdStyle}\"><strong>{$statusLabel}</strong></td></tr>"
                . "<tr><th style=\"{$thStyle}\">コメント</th><td style=\"{$tdStyle}\">" . nl2br($ts($record['review_comment'] ?? '')) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">審査日時</th><td style=\"{$tdStyle}\">" . $ts($record['reviewed_at']) . '</td></tr>'
                . '</table>';

            // 申請者にも送信
            $applicantEmail = $record['applicant_email'] ?? '';
            if ($applicantEmail && filter_var($applicantEmail, FILTER_VALIDATE_EMAIL)) {
                sendNotificationEmail($applicantEmail, $subject, $body);
            }
            break;

        // ── 商談登録 ──
        case 'deal_create':
            $subject = '【商談登録】' . ($record['customer_name'] ?? '') . ' - ' . ($record['title'] ?? '');
            $body = '<p>新しい商談が登録されました。</p>'
                . "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"{$tableStyle}\">"
                . "<tr><th style=\"{$thStyle}\">顧客名</th><td style=\"{$tdStyle}\">" . $ts($record['customer_name']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">商談名</th><td style=\"{$tdStyle}\">" . $ts($record['title']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">金額</th><td style=\"{$tdStyle}\">¥" . number_format($record['amount'] ?? 0) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">確度</th><td style=\"{$tdStyle}\">" . ($record['probability'] ?? 0) . '%</td></tr>'
                . "<tr><th style=\"{$thStyle}\">ステージ</th><td style=\"{$tdStyle}\">" . $ts($record['stage']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">担当者</th><td style=\"{$tdStyle}\">" . $ts($record['assignee']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">受注予定日</th><td style=\"{$tdStyle}\">" . $ts($record['expected_close_date']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">メモ</th><td style=\"{$tdStyle}\">" . nl2br($ts($record['memo'])) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">登録者</th><td style=\"{$tdStyle}\">" . $ts($record['created_by']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">登録日時</th><td style=\"{$tdStyle}\">" . $ts($record['created_at']) . '</td></tr>'
                . '</table>';
            break;

        // ── リード登録 ──
        case 'lead_create':
            $subject = '【リード登録】' . ($record['company_name'] ?? '');
            $body = '<p>新しいリードが登録されました。</p>'
                . "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"{$tableStyle}\">"
                . "<tr><th style=\"{$thStyle}\">会社名</th><td style=\"{$tdStyle}\">" . $ts($record['company_name']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">担当者名</th><td style=\"{$tdStyle}\">" . $ts($record['person_name']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">役職</th><td style=\"{$tdStyle}\">" . $ts($record['title']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">電話番号</th><td style=\"{$tdStyle}\">" . $ts($record['phone']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">メール</th><td style=\"{$tdStyle}\">" . $ts($record['email']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">ステータス</th><td style=\"{$tdStyle}\">" . $ts($record['status']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">営業担当</th><td style=\"{$tdStyle}\">" . $ts($record['sales_assignee']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">メモ</th><td style=\"{$tdStyle}\">" . nl2br($ts($record['memo'])) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">登録者</th><td style=\"{$tdStyle}\">" . $ts($record['created_by']) . '</td></tr>'
                . "<tr><th style=\"{$thStyle}\">登録日時</th><td style=\"{$tdStyle}\">" . $ts($record['created_at']) . '</td></tr>'
                . '</table>';
            break;

        default:
            return;
    }

    foreach ($adminEmails as $email) {
        sendNotificationEmail($email, $subject, $body);
    }
}
