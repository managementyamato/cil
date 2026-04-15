<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'allowedMethods' => ['GET', 'POST']
]);

$input = getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? '';
$currentUser = $_SESSION['user_email'];

switch ($action) {
    case 'list':
        handleList($currentUser);
        break;
    case 'create':
        handleCreate($input, $currentUser);
        break;
    case 'update':
        handleUpdate($input, $currentUser);
        break;
    case 'delete':
        handleDelete($input, $currentUser);
        break;
    case 'complete':
        handleComplete($input, $currentUser);
        break;
    case 'snooze':
        handleSnooze($input, $currentUser);
        break;
    default:
        errorResponse('不正なアクションです', 400);
}

function handleList($currentUser) {
    $data = getData();
    $reminders = filterDeleted($data['reminders'] ?? []);
    $today = date('Y-m-d');

    // Auto-detect overdue
    foreach ($reminders as &$r) {
        if (($r['due_date'] ?? '') < $today && ($r['status'] ?? '') !== '完了') {
            $r['status'] = '期限切れ';
        }
    }
    unset($r);

    // Filter: show reminders targeted at current user or 全体
    if (!isAdmin()) {
        $reminders = array_values(array_filter($reminders, function($r) use ($currentUser) {
            $targetType = $r['target_type'] ?? '個人';
            if ($targetType === '全体') return true;
            if ($targetType === '個人') {
                return ($r['created_by'] ?? '') === $currentUser;
            }
            return ($r['created_by'] ?? '') === $currentUser;
        }));
    }

    // Sort by due_date ascending
    usort($reminders, function($a, $b) {
        $da = $a['due_date'] ?? '9999-12-31';
        $db = $b['due_date'] ?? '9999-12-31';
        return strcmp($da, $db);
    });

    successResponse($reminders);
}

function handleCreate($input, $currentUser) {
    requireParams($input, ['title', 'due_date']);

    $reminder = [
        'id' => uniqid('rem_'),
        'title' => sanitizeInput($input['title'], 'string'),
        'description' => sanitizeInput($input['description'] ?? '', 'string'),
        'due_date' => sanitizeInput($input['due_date'], 'string'),
        'due_time' => sanitizeInput($input['due_time'] ?? '', 'string'),
        'remind_before' => sanitizeInput($input['remind_before'] ?? '当日', 'string'),
        'target_type' => sanitizeInput($input['target_type'] ?? '個人', 'string'),
        'target_value' => sanitizeInput($input['target_value'] ?? $currentUser, 'string'),
        'source_type' => sanitizeInput($input['source_type'] ?? '', 'string'),
        'source_id' => sanitizeInput($input['source_id'] ?? '', 'string'),
        'status' => '未通知',
        'created_by' => $currentUser,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'deleted_at' => null,
        'deleted_by' => null,
    ];

    $data = getData();
    if (!isset($data['reminders'])) {
        $data['reminders'] = [];
    }
    $data['reminders'][] = $reminder;
    saveData($data);

    auditCreate('reminders', $reminder['id'], 'リマインダーを作成: ' . $reminder['title'], $reminder);

    successResponse($reminder, '作成しました');
}

function handleUpdate($input, $currentUser) {
    requireParams($input, ['id']);

    $data = getData();
    $reminders = &$data['reminders'];
    $found = false;

    for ($i = 0; $i < count($reminders); $i++) {
        if ($reminders[$i]['id'] === $input['id']) {
            if ($reminders[$i]['created_by'] !== $currentUser && !isAdmin()) {
                errorResponse('編集権限がありません', 403);
            }

            $oldData = $reminders[$i];

            if (isset($input['title'])) $reminders[$i]['title'] = sanitizeInput($input['title'], 'string');
            if (isset($input['description'])) $reminders[$i]['description'] = sanitizeInput($input['description'], 'string');
            if (isset($input['due_date'])) $reminders[$i]['due_date'] = sanitizeInput($input['due_date'], 'string');
            if (isset($input['due_time'])) $reminders[$i]['due_time'] = sanitizeInput($input['due_time'], 'string');
            if (isset($input['remind_before'])) $reminders[$i]['remind_before'] = sanitizeInput($input['remind_before'], 'string');
            if (isset($input['target_type'])) $reminders[$i]['target_type'] = sanitizeInput($input['target_type'], 'string');
            if (isset($input['target_value'])) $reminders[$i]['target_value'] = sanitizeInput($input['target_value'], 'string');
            if (isset($input['status'])) $reminders[$i]['status'] = sanitizeInput($input['status'], 'string');

            $reminders[$i]['updated_at'] = date('Y-m-d H:i:s');
            $found = true;

            auditUpdate('reminders', $input['id'], 'リマインダーを更新', $oldData, $reminders[$i]);
            break;
        }
    }

    if (!$found) {
        errorResponse('リマインダーが見つかりません', 404);
    }

    saveData($data);
    successResponse(null, '更新しました');
}

function handleDelete($input, $currentUser) {
    requireParams($input, ['id']);

    if (!canDelete()) {
        errorResponse('削除権限がありません', 403);
    }

    $data = getData();
    $reminders = &$data['reminders'];

    for ($i = 0; $i < count($reminders); $i++) {
        if ($reminders[$i]['id'] === $input['id']) {
            $deletedItem = $reminders[$i];
            $reminders[$i]['deleted_at'] = date('Y-m-d H:i:s');
            $reminders[$i]['deleted_by'] = $currentUser;
            saveData($data);
            auditDelete('reminders', $input['id'], 'リマインダーを削除', $deletedItem);
            successResponse(null, '削除しました');
        }
    }

    errorResponse('リマインダーが見つかりません', 404);
}

function handleComplete($input, $currentUser) {
    requireParams($input, ['id']);

    $data = getData();
    $reminders = &$data['reminders'];

    for ($i = 0; $i < count($reminders); $i++) {
        if ($reminders[$i]['id'] === $input['id']) {
            if ($reminders[$i]['created_by'] !== $currentUser && !isAdmin()) {
                errorResponse('権限がありません', 403);
            }

            $reminders[$i]['status'] = '完了';
            $reminders[$i]['updated_at'] = date('Y-m-d H:i:s');
            saveData($data);

            successResponse(null, '完了にしました');
        }
    }

    errorResponse('リマインダーが見つかりません', 404);
}

function handleSnooze($input, $currentUser) {
    requireParams($input, ['id']);

    $data = getData();
    $reminders = &$data['reminders'];

    for ($i = 0; $i < count($reminders); $i++) {
        if ($reminders[$i]['id'] === $input['id']) {
            if ($reminders[$i]['created_by'] !== $currentUser && !isAdmin()) {
                errorResponse('権限がありません', 403);
            }

            $currentDue = $reminders[$i]['due_date'] ?? date('Y-m-d');
            $newDue = date('Y-m-d', strtotime($currentDue . ' +1 day'));
            $reminders[$i]['due_date'] = $newDue;
            $reminders[$i]['status'] = '未通知';
            $reminders[$i]['updated_at'] = date('Y-m-d H:i:s');
            saveData($data);

            successResponse(['new_due_date' => $newDue], '1日延期しました');
        }
    }

    errorResponse('リマインダーが見つかりません', 404);
}
