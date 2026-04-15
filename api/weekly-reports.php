<?php
/**
 * 週報 CRUD API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/notification-functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    initApi(['requireAuth' => true, 'requireCsrf' => false, 'allowedMethods' => ['GET']]);

    $action      = $_GET['action'] ?? '';
    $data        = getData();
    $currentUser = $_SESSION['user_email'];

    switch ($action) {
        case 'list':
            $reports = filterDeleted($data['weekly_reports'] ?? []);
            if (!isAdmin()) {
                $reports = array_values(array_filter($reports, fn($r) => ($r['user_email'] ?? '') === $currentUser));
            }
            // 秘匿メッセージはAPI経由では返さない（ページ側でハンドル）
            usort($reports, fn($a, $b) => strcmp($b['week_start'] ?? '', $a['week_start'] ?? ''));
            successResponse(['reports' => array_values($reports)]);
            break;
        default:
            errorResponse('不正なアクションです', 400);
    }
    exit;
}

initApi(['requireAuth' => true, 'requireCsrf' => true, 'allowedMethods' => ['POST']]);

$data        = getData();
$action      = $_POST['action'] ?? '';
$currentUser = $_SESSION['user_email'];
$userName    = $_SESSION['user_name'] ?? $currentUser;
$now         = date('Y-m-d H:i:s');

$allowedTags = '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><h2><h3><img>';

/**
 * <img> タグの src を /uploads/ 配下のみに制限する（XSS対策）
 */
function sanitizeImgSrc($html) {
    return preg_replace_callback('/<img([^>]*)>/i', function($m) {
        $attrs = $m[1];
        // src を取得
        if (preg_match('/src=["\']([^"\']*)["\']/', $attrs, $srcMatch)) {
            $src = $srcMatch[1];
            // /uploads/ 以外の src は除去（外部URL・data:URI等をブロック）
            if (!preg_match('#^/uploads/#', $src)) {
                return ''; // imgタグごと除去
            }
            // src と alt のみ残す（他属性は除去）
            $alt = '';
            if (preg_match('/alt=["\']([^"\']*)["\']/', $attrs, $altMatch)) {
                $alt = ' alt="' . htmlspecialchars($altMatch[1]) . '"';
            }
            return '<img src="' . htmlspecialchars($src) . '"' . $alt . '>';
        }
        return ''; // src がない img は除去
    }, $html);
}

function buildSections() {
    global $allowedTags;
    $keys = ['sec_role', 'sec_report', 'sec_issues', 'sec_next_goals', 'sec_second_area', 'sec_misc'];
    $result = [];
    foreach ($keys as $key) {
        $result[$key] = sanitizeImgSrc(strip_tags(trim($_POST[$key] ?? ''), $allowedTags));
    }
    $result['private_message']    = trim($_POST['private_message'] ?? '');
    $result['private_recipients'] = json_decode($_POST['private_recipients'] ?? '[]', true) ?: [];
    return $result;
}

switch ($action) {

    case 'save':
        $weekStart = trim($_POST['week_start'] ?? '');
        if (empty($weekStart)) errorResponse('対象週は必須です', 400);

        $monTs   = strtotime($weekStart);
        $weekEnd = date('Y-m-d', $monTs + 4 * 86400);
        $sections = buildSections();

        $existingIdx = null;
        foreach ($data['weekly_reports'] as $idx => $r) {
            if (($r['user_email'] ?? '') === $currentUser && ($r['week_start'] ?? '') === $weekStart && empty($r['deleted_at'])) {
                $existingIdx = $idx;
                break;
            }
        }

        if ($existingIdx !== null) {
            foreach ($sections as $k => $v) $data['weekly_reports'][$existingIdx][$k] = $v;
            $data['weekly_reports'][$existingIdx]['status']     = 'draft';
            $data['weekly_reports'][$existingIdx]['updated_at'] = $now;
            $report = $data['weekly_reports'][$existingIdx];
        } else {
            $report = array_merge([
                'id'         => uniqid('wr_'),
                'user_email' => $currentUser,
                'user_name'  => $userName,
                'week_start' => $weekStart,
                'week_end'   => $weekEnd,
                'status'     => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ], $sections);
            if (!isset($data['weekly_reports'])) $data['weekly_reports'] = [];
            $data['weekly_reports'][] = $report;
        }
        saveData($data);
        successResponse(['report' => $report]);
        break;

    case 'submit':
        $weekStart = trim($_POST['week_start'] ?? '');
        if (empty($weekStart)) errorResponse('対象週は必須です', 400);

        $sections = buildSections();
        $found = false;

        foreach ($data['weekly_reports'] as &$r) {
            if (($r['user_email'] ?? '') === $currentUser && ($r['week_start'] ?? '') === $weekStart && empty($r['deleted_at'])) {
                foreach ($sections as $k => $v) $r[$k] = $v;
                $r['status']       = 'submitted';
                $r['submitted_at'] = $now;
                $r['updated_at']   = $now;
                $found = true;
                $updated = $r;
                break;
            }
        }
        unset($r);

        if (!$found) {
            $monTs   = strtotime($weekStart);
            $weekEnd = date('Y-m-d', $monTs + 4 * 86400);
            $updated = array_merge([
                'id'           => uniqid('wr_'),
                'user_email'   => $currentUser,
                'user_name'    => $userName,
                'week_start'   => $weekStart,
                'week_end'     => $weekEnd,
                'status'       => 'submitted',
                'submitted_at' => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ], $sections);
            if (!isset($data['weekly_reports'])) $data['weekly_reports'] = [];
            $data['weekly_reports'][] = $updated;
        }
        saveData($data);
        sendWeeklyReportNotification($updated);
        successResponse(['report' => $updated]);
        break;

    case 'delete':
        if (!canDelete()) errorResponse('削除権限がありません', 403);
        $id = trim($_POST['id'] ?? '');
        if (empty($id)) errorResponse('IDは必須です', 400);

        $found = false;
        foreach ($data['weekly_reports'] as &$r) {
            if (($r['id'] ?? '') !== $id) continue;
            if (!isAdmin() && ($r['user_email'] ?? '') !== $currentUser) errorResponse('権限がありません', 403);
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

    default:
        errorResponse('不正なアクションです', 400);
}

// ─── メール通知ヘルパー ─────────────────────────────────────────────────

function sendWeeklyReportNotification($report) {
    $config      = getNotificationConfig();
    $adminEmails = $config['email_recipients'] ?? [];
    if (empty($adminEmails)) return;

    $name      = htmlspecialchars($report['user_name'] ?? $report['user_email'] ?? '');
    $wStart    = htmlspecialchars($report['week_start'] ?? '');
    $wEnd      = htmlspecialchars($report['week_end']   ?? '');
    $subject   = '【週報提出】' . $name . ' さんから週報が届きました（' . $wStart . '〜' . $wEnd . '）';

    $sectionLabels = [
        'sec_role'        => '今期の役割',
        'sec_report'      => '今週の報告',
        'sec_issues'      => '現在抱えている課題',
        'sec_next_goals'  => '次週目標・計画',
        'sec_second_area' => 'いま思いつく第二領域活動',
        'sec_misc'        => '報告・連絡・相談事項',
    ];

    $rows = '';
    foreach ($sectionLabels as $key => $label) {
        $content = $report[$key] ?? '';
        if (empty(trim(strip_tags($content)))) continue;
        $rows .= '<tr>'
            . '<td colspan="2" style="background:#f5f5f5;padding:6px 12px;font-weight:bold;font-size:13px;border:1px solid #ddd;">'
            . htmlspecialchars($label) . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td colspan="2" style="padding:8px 12px;font-size:13px;border:1px solid #ddd;line-height:1.6;">' . $content . '</td>'
            . '</tr>';
    }

    $body = '<p>' . $name . ' さんが週報を提出しました。</p>'
        . '<table border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:680px;">'
        . '<tr><th style="background:#f5f5f5;padding:8px 12px;text-align:left;white-space:nowrap;font-size:13px;border:1px solid #ddd;">提出者</th>'
        . '<td style="padding:8px 12px;font-size:13px;border:1px solid #ddd;">' . $name . '</td></tr>'
        . '<tr><th style="background:#f5f5f5;padding:8px 12px;text-align:left;white-space:nowrap;font-size:13px;border:1px solid #ddd;">対象週</th>'
        . '<td style="padding:8px 12px;font-size:13px;border:1px solid #ddd;">' . $wStart . ' 〜 ' . $wEnd . '</td></tr>'
        . '<tr><th style="background:#f5f5f5;padding:8px 12px;text-align:left;white-space:nowrap;font-size:13px;border:1px solid #ddd;">提出日時</th>'
        . '<td style="padding:8px 12px;font-size:13px;border:1px solid #ddd;">' . htmlspecialchars($report['submitted_at'] ?? '') . '</td></tr>'
        . $rows
        . '</table>'
        . '<p style="margin-top:16px;font-size:12px;color:#666;">システムにログインして内容を確認してください。</p>';

    foreach ($adminEmails as $email) {
        sendNotificationEmail($email, $subject, $body);
    }
}
