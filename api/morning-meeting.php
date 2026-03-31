<?php
/**
 * 朝礼Todoまとめ CRUD API
 * pages/morning-meeting.php から呼び出される
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    initApi([
        'requireAuth'    => true,
        'requireCsrf'    => false,
        'allowedMethods' => ['GET'],
    ]);

    $action = $_GET['action'] ?? '';
    $data   = getData();

    switch ($action) {

        case 'list':
            $todos = filterDeleted($data['morning_todos'] ?? []);
            $dateFilter = $_GET['date'] ?? '';
            if ($dateFilter) {
                $todos = array_values(array_filter($todos, function($t) use ($dateFilter) {
                    return ($t['meeting_date'] ?? '') === $dateFilter;
                }));
            }
            // 日付降順 → 作成日昇順
            usort($todos, function($a, $b) {
                $cmp = strcmp($b['meeting_date'] ?? '', $a['meeting_date'] ?? '');
                if ($cmp !== 0) return $cmp;
                return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
            });
            successResponse(['todos' => array_values($todos)]);
            break;

        case 'dates':
            // 朝礼が存在する日付一覧
            $todos = filterDeleted($data['morning_todos'] ?? []);
            $dates = array_unique(array_map(fn($t) => $t['meeting_date'] ?? '', $todos));
            rsort($dates);
            successResponse(['dates' => array_values($dates)]);
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
$now         = date('Y-m-d H:i:s');

switch ($action) {

    case 'create':
        $title = trim($_POST['title'] ?? '');
        $date  = trim($_POST['meeting_date'] ?? date('Y-m-d'));
        if (empty($title)) errorResponse('タイトルは必須です', 400);

        $todo = [
            'id'             => uniqid('mt_'),
            'meeting_date'   => $date,
            'title'          => $title,
            'description'    => trim($_POST['description'] ?? ''),
            'assignee'       => trim($_POST['assignee'] ?? ''),
            'assignee_email' => trim($_POST['assignee_email'] ?? ''),
            'due_date'       => trim($_POST['due_date'] ?? ''),
            'status'         => 'open',
            'created_by'     => $currentUser,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        if (!isset($data['morning_todos'])) $data['morning_todos'] = [];
        $data['morning_todos'][] = $todo;
        saveData($data);
        successResponse(['todo' => $todo]);
        break;

    case 'update':
        $id = trim($_POST['id'] ?? '');
        if (empty($id)) errorResponse('IDは必須です', 400);

        $found = false;
        foreach ($data['morning_todos'] as &$todo) {
            if (($todo['id'] ?? '') !== $id) continue;
            if (!empty($todo['deleted_at'])) errorResponse('削除済みです', 400);

            $todo['title']          = trim($_POST['title'] ?? $todo['title']);
            $todo['description']    = trim($_POST['description'] ?? $todo['description'] ?? '');
            $todo['assignee']       = trim($_POST['assignee'] ?? $todo['assignee'] ?? '');
            $todo['assignee_email'] = trim($_POST['assignee_email'] ?? $todo['assignee_email'] ?? '');
            $todo['due_date']       = trim($_POST['due_date'] ?? $todo['due_date'] ?? '');
            $todo['meeting_date']   = trim($_POST['meeting_date'] ?? $todo['meeting_date']);
            $todo['updated_at']     = $now;
            $found = true;
            $updated = $todo;
            break;
        }
        unset($todo);

        if (!$found) errorResponse('TODOが見つかりません', 404);
        saveData($data);
        successResponse(['todo' => $updated]);
        break;

    case 'toggle_status':
        $id = trim($_POST['id'] ?? '');
        if (empty($id)) errorResponse('IDは必須です', 400);

        $found = false;
        foreach ($data['morning_todos'] as &$todo) {
            if (($todo['id'] ?? '') !== $id) continue;
            $todo['status']     = ($todo['status'] ?? 'open') === 'open' ? 'done' : 'open';
            $todo['updated_at'] = $now;
            $found = true;
            $updated = $todo;
            break;
        }
        unset($todo);

        if (!$found) errorResponse('TODOが見つかりません', 404);
        saveData($data);
        successResponse(['todo' => $updated]);
        break;

    case 'delete':
        if (!canDelete()) errorResponse('削除権限がありません', 403);

        $id = trim($_POST['id'] ?? '');
        if (empty($id)) errorResponse('IDは必須です', 400);

        $found = false;
        foreach ($data['morning_todos'] as &$todo) {
            if (($todo['id'] ?? '') !== $id) continue;
            $todo['deleted_at'] = $now;
            $todo['deleted_by'] = $currentUser;
            $found = true;
            break;
        }
        unset($todo);

        if (!$found) errorResponse('TODOが見つかりません', 404);
        saveData($data);
        successResponse(['message' => '削除しました']);
        break;

    default:
        errorResponse('不正なアクションです', 400);
}
