<?php
/**
 * タスク・メモ CRUD API
 * pages/my-workspace.php から呼び出される
 *
 * タスク: 全ユーザー共有（閲覧）、作成者またはadminが編集可
 * メモ:   完全プライベート（自分のメモのみ操作可能）
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

// GETリクエストはCSRF不要（読み取り専用）
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    initApi([
        'requireAuth'    => true,
        'requireCsrf'    => false,
        'allowedMethods' => ['GET'],
    ]);

    $action = $_GET['action'] ?? '';
    $data   = getData();
    $currentUser = $_SESSION['user_email'];

    switch ($action) {

        case 'list_tasks':
            $tasks = filterDeleted($data['tasks'] ?? []);
            // 期日順ソート（期日なしは末尾）、同じ期日は作成日降順
            usort($tasks, function($a, $b) {
                $aDue = $a['due_date'] ?? '';
                $bDue = $b['due_date'] ?? '';
                if ($aDue === '' && $bDue === '') {
                    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
                }
                if ($aDue === '') return 1;
                if ($bDue === '') return -1;
                $cmp = strcmp($aDue, $bDue);
                if ($cmp !== 0) return $cmp;
                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
            });
            successResponse(['tasks' => array_values($tasks)]);
            break;

        case 'list_memos':
            $allMemos = filterDeleted($data['memos'] ?? []);
            // 自分のメモのみ返す（プライバシー保護の核心）
            $myMemos = array_values(array_filter($allMemos, function($m) use ($currentUser) {
                return ($m['user_email'] ?? '') === $currentUser;
            }));
            // ピン留め→更新日降順ソート
            usort($myMemos, function($a, $b) {
                $aPinned = $a['pinned'] ? 1 : 0;
                $bPinned = $b['pinned'] ? 1 : 0;
                if ($aPinned !== $bPinned) return $bPinned - $aPinned;
                return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
            });
            successResponse(['memos' => $myMemos]);
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

// ─── ヘルパー関数 ─────────────────────────────────────────────────────

/**
 * POST送信されたメンション配列をバリデートして返す
 * （未知のメールアドレスは除外してセキュリティを確保）
 */
function validateMentionEmails(string $json): array {
    $submitted = json_decode($json, true);
    if (!is_array($submitted) || empty($submitted)) return [];

    $data   = getData();
    $today  = date('Y-m-d');
    $validEmails = [];
    foreach ($data['employees'] ?? [] as $emp) {
        if (empty($emp['email'])) continue;
        if (!empty($emp['deleted'])) continue;
        if (!empty($emp['leave_date']) && $emp['leave_date'] < $today) continue;
        $validEmails[] = $emp['email'];
    }

    return array_values(array_unique(
        array_filter($submitted, fn($e) => in_array($e, $validEmails, true))
    ));
}

/**
 * タスクの連絡先にメール通知を送信
 */
function notifyTaskMention(array $task, string $toEmail, string $actorEmail): void {
    require_once __DIR__ . '/../functions/notification-functions.php';
    $config = getNotificationConfig();
    if (!($config['enabled'] ?? false)) return;

    $actorName = explode('@', $actorEmail)[0];
    $subject   = '[Yamato Gear] タスクで連絡があります: ' . ($task['title'] ?? '');

    $body  = "<html><body style='font-family:sans-serif;color:#333'>";
    $body .= "<h2 style='color:#3b82f6'>タスクで連絡先に指定されました</h2>";
    $body .= "<table style='border-collapse:collapse;width:100%;max-width:600px'>";
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5;width:90px'>タスク</td>"
           . "<td style='padding:8px;border:1px solid #ddd'>" . htmlspecialchars($task['title'] ?? '') . "</td></tr>";
    if (!empty($task['description'])) {
        $body .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5'>内容</td>"
               . "<td style='padding:8px;border:1px solid #ddd'>" . nl2br(htmlspecialchars($task['description'])) . "</td></tr>";
    }
    if (!empty($task['due_date'])) {
        $body .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5'>期日</td>"
               . "<td style='padding:8px;border:1px solid #ddd'>" . htmlspecialchars($task['due_date']) . "</td></tr>";
    }
    $body .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5'>連絡者</td>"
           . "<td style='padding:8px;border:1px solid #ddd'>" . htmlspecialchars($actorName) . "</td></tr>";
    $body .= "</table>";
    $body .= "<p style='margin-top:20px'><a href='" . getBaseUrl() . "/pages/my-workspace.php' style='color:#3b82f6'>ワークスペースを確認する</a></p>";
    $body .= "</body></html>";

    sendNotificationEmail($toEmail, $subject, $body);
}

/**
 * 自分のメモを取得（他人のメモはID不一致扱いで404）
 * @param array  $memos       参照渡し
 * @param string $memoId
 * @param string $userEmail   現在のユーザー
 * @return array              &$memo の参照
 */
function &findOwnMemo(array &$memos, string $memoId, string $userEmail): mixed {
    foreach ($memos as &$memo) {
        if (($memo['id'] ?? '') === $memoId) {
            if (($memo['user_email'] ?? '') !== $userEmail) {
                // 他人のメモは404（存在を知らせない）
                errorResponse('メモが見つかりません', 404);
            }
            return $memo;
        }
    }
    unset($memo);
    errorResponse('メモが見つかりません', 404);
    // phpstan 用ダミー（実行されない）
    $dummy = null;
    return $dummy;
}

/**
 * タスクを検索して返す
 */
function &findTask(array &$tasks, string $taskId): mixed {
    foreach ($tasks as &$task) {
        if (($task['id'] ?? '') === $taskId) {
            return $task;
        }
    }
    unset($task);
    errorResponse('タスクが見つかりません', 404);
    $dummy = null;
    return $dummy;
}

// ─── アクション処理 ───────────────────────────────────────────────────

switch ($action) {

    // ================================================================
    // タスク
    // ================================================================

    // ────────────────────────────────────────
    // タスク追加（全員可）
    // ────────────────────────────────────────
    case 'task_add':
        $title = trim($_POST['title'] ?? '');
        if ($title === '') errorResponse('タイトルは必須です', 400);

        $now      = date('Y-m-d H:i:s');
        $desc     = trim($_POST['description'] ?? '');
        $mentions = validateMentionEmails($_POST['mentions'] ?? '[]');
        $task = [
            'id'          => 'task_' . uniqid(),
            'title'       => $title,
            'description' => $desc,
            'status'      => '未着手',
            'due_date'    => trim($_POST['due_date'] ?? ''),
            'subtasks'    => [],
            'mentions'    => $mentions,
            'created_by'  => $currentUser,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];

        if (!isset($data['tasks'])) $data['tasks'] = [];
        $data['tasks'][] = $task;

        try {
            saveData($data);
            writeAuditLog('create', 'task', 'タスク作成: ' . $title, ['id' => $task['id']]);
        } catch (Exception $e) {
            errorResponse('データの保存に失敗しました', 500);
        }

        // 連絡先に通知（自分自身は除く）
        foreach ($mentions as $email) {
            if ($email !== $currentUser) {
                notifyTaskMention($task, $email, $currentUser);
            }
        }

        successResponse(['task' => $task], 'タスクを追加しました');
        break;

    // ────────────────────────────────────────
    // タスク更新（作成者またはadmin）
    // ────────────────────────────────────────
    case 'task_update':
        $taskId = trim($_POST['task_id'] ?? '');
        if ($taskId === '') errorResponse('タスクIDが必要です', 400);

        $task = &findTask($data['tasks'], $taskId);

        // 権限チェック：作成者、admin、またはメンション相手
        $isMentioned = in_array($currentUser, $task['mentions'] ?? [], true);
        if (($task['created_by'] ?? '') !== $currentUser && !isAdmin() && !$isMentioned) {
            errorResponse('このタスクを編集する権限がありません', 403);
        }

        $old = $task;
        $prevMentions = $task['mentions'] ?? [];
        if (isset($_POST['title']))       $task['title']       = trim($_POST['title']);
        if (isset($_POST['description'])) $task['description'] = trim($_POST['description']);
        if (isset($_POST['status']))      $task['status']      = trim($_POST['status']);
        if (isset($_POST['due_date']))    $task['due_date']    = trim($_POST['due_date']);
        if (isset($_POST['mentions']))    $task['mentions']    = validateMentionEmails($_POST['mentions']);
        $task['updated_at'] = date('Y-m-d H:i:s');

        try {
            saveData($data);
            writeAuditLog('update', 'task', 'タスク更新: ' . $task['title'], [
                'id' => $taskId, 'old' => $old, 'new' => $task
            ]);
        } catch (Exception $e) {
            errorResponse('データの保存に失敗しました', 500);
        }

        // 新たに追加された連絡先のみ通知（自分自身は除く）
        $newMentions = array_diff($task['mentions'] ?? [], $prevMentions);
        foreach ($newMentions as $email) {
            if ($email !== $currentUser) {
                notifyTaskMention($task, $email, $currentUser);
            }
        }

        successResponse(['task' => $task], 'タスクを更新しました');
        break;

    // ────────────────────────────────────────
    // タスク削除（adminのみ）
    // ────────────────────────────────────────
    case 'task_delete':
        if (!isAdmin()) errorResponse('削除権限がありません', 403);

        $taskId = trim($_POST['task_id'] ?? '');
        if ($taskId === '') errorResponse('タスクIDが必要です', 400);

        $deleted = null;
        foreach ($data['tasks'] as &$task) {
            if (($task['id'] ?? '') === $taskId) {
                $deleted = $task;
                $task['deleted_at'] = date('Y-m-d H:i:s');
                $task['deleted_by'] = $currentUser;
                break;
            }
        }
        unset($task);

        if (!$deleted) errorResponse('タスクが見つかりません', 404);

        try {
            saveData($data);
            writeAuditLog('delete', 'task', 'タスク削除: ' . ($deleted['title'] ?? ''), ['id' => $taskId]);
        } catch (Exception $e) {
            errorResponse('データの保存に失敗しました', 500);
        }

        successResponse(['id' => $taskId], 'タスクを削除しました');
        break;

    // ────────────────────────────────────────
    // サブタスク操作
    // ────────────────────────────────────────
    case 'task_update_subtask':
        $taskId    = trim($_POST['task_id'] ?? '');
        $subAction = trim($_POST['sub_action'] ?? ''); // toggle | add | delete
        if ($taskId === '') errorResponse('タスクIDが必要です', 400);

        $task = &findTask($data['tasks'], $taskId);

        if ($subAction === 'add') {
            // サブタスク追加（作成者、admin、またはメンション相手）
            $isMentioned = in_array($currentUser, $task['mentions'] ?? [], true);
            if (($task['created_by'] ?? '') !== $currentUser && !isAdmin() && !$isMentioned) {
                errorResponse('このタスクを編集する権限がありません', 403);
            }
            $subTitle = trim($_POST['title'] ?? '');
            if ($subTitle === '') errorResponse('サブタスクのタイトルは必須です', 400);
            $sub = ['id' => 'sub_' . uniqid(), 'title' => $subTitle, 'done' => false];
            if (!isset($task['subtasks'])) $task['subtasks'] = [];
            $task['subtasks'][] = $sub;
            $task['updated_at'] = date('Y-m-d H:i:s');

        } elseif ($subAction === 'toggle') {
            // チェック切り替え（全員可）
            $subtaskId = trim($_POST['subtask_id'] ?? '');
            if ($subtaskId === '') errorResponse('サブタスクIDが必要です', 400);
            $found = false;
            foreach ($task['subtasks'] as &$sub) {
                if (($sub['id'] ?? '') === $subtaskId) {
                    $sub['done'] = !($sub['done'] ?? false);
                    $found = true;
                    break;
                }
            }
            unset($sub);
            if (!$found) errorResponse('サブタスクが見つかりません', 404);
            $task['updated_at'] = date('Y-m-d H:i:s');

        } elseif ($subAction === 'delete') {
            // サブタスク削除（作成者、admin、またはメンション相手）
            $isMentioned = in_array($currentUser, $task['mentions'] ?? [], true);
            if (($task['created_by'] ?? '') !== $currentUser && !isAdmin() && !$isMentioned) {
                errorResponse('このタスクを編集する権限がありません', 403);
            }
            $subtaskId = trim($_POST['subtask_id'] ?? '');
            if ($subtaskId === '') errorResponse('サブタスクIDが必要です', 400);
            $task['subtasks'] = array_values(array_filter(
                $task['subtasks'] ?? [],
                fn($s) => ($s['id'] ?? '') !== $subtaskId
            ));
            $task['updated_at'] = date('Y-m-d H:i:s');

        } else {
            errorResponse('不正なsub_actionです', 400);
        }

        try {
            saveData($data);
        } catch (Exception $e) {
            errorResponse('データの保存に失敗しました', 500);
        }

        successResponse(['task' => $task]);
        break;

    // ================================================================
    // メモ
    // ================================================================

    // ────────────────────────────────────────
    // メモ追加（全員可、自分のメモとして登録）
    // ────────────────────────────────────────
    case 'memo_add':
        $title = trim($_POST['title'] ?? '');
        if ($title === '') errorResponse('タイトルは必須です', 400);

        $tagsRaw = $_POST['tags'] ?? '[]';
        $tags = json_decode($tagsRaw, true);
        if (!is_array($tags)) $tags = [];
        // タグはサニタイズ
        $tags = array_values(array_filter(array_map('trim', $tags)));

        $now = date('Y-m-d H:i:s');
        $memo = [
            'id'         => 'memo_' . uniqid(),
            'title'      => $title,
            'content'    => $_POST['content'] ?? '',
            'pinned'     => false,
            'tags'       => $tags,
            'user_email' => $currentUser, // サーバー側で強制設定（クライアントから受け取らない）
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (!isset($data['memos'])) $data['memos'] = [];
        $data['memos'][] = $memo;

        try {
            saveData($data);
            // プライバシーのためcontentは監査ログに記録しない
            writeAuditLog('create', 'memo', 'メモ作成: ' . $title, ['id' => $memo['id']]);
        } catch (Exception $e) {
            errorResponse('データの保存に失敗しました', 500);
        }

        successResponse(['memo' => $memo], 'メモを追加しました');
        break;

    // ────────────────────────────────────────
    // メモ更新（本人のみ）
    // ────────────────────────────────────────
    case 'memo_update':
        $memoId = trim($_POST['memo_id'] ?? '');
        if ($memoId === '') errorResponse('メモIDが必要です', 400);

        $memo = &findOwnMemo($data['memos'], $memoId, $currentUser);

        if (isset($_POST['title']))   $memo['title']   = trim($_POST['title']);
        if (isset($_POST['content'])) $memo['content'] = $_POST['content'];
        if (isset($_POST['tags'])) {
            $tags = json_decode($_POST['tags'], true);
            $memo['tags'] = is_array($tags) ? array_values(array_filter(array_map('trim', $tags))) : [];
        }
        $memo['updated_at'] = date('Y-m-d H:i:s');

        try {
            saveData($data);
            writeAuditLog('update', 'memo', 'メモ更新: ' . $memo['title'], ['id' => $memoId]);
        } catch (Exception $e) {
            errorResponse('データの保存に失敗しました', 500);
        }

        successResponse(['memo' => $memo], 'メモを更新しました');
        break;

    // ────────────────────────────────────────
    // メモ削除（本人またはadmin）
    // ────────────────────────────────────────
    case 'memo_delete':
        $memoId = trim($_POST['memo_id'] ?? '');
        if ($memoId === '') errorResponse('メモIDが必要です', 400);

        $deleted = null;
        foreach ($data['memos'] as &$memo) {
            if (($memo['id'] ?? '') === $memoId) {
                // 本人またはadminのみ
                if (($memo['user_email'] ?? '') !== $currentUser && !isAdmin()) {
                    errorResponse('このメモを削除する権限がありません', 403);
                }
                $deleted = $memo;
                $memo['deleted_at'] = date('Y-m-d H:i:s');
                $memo['deleted_by'] = $currentUser;
                break;
            }
        }
        unset($memo);

        if (!$deleted) errorResponse('メモが見つかりません', 404);

        try {
            saveData($data);
            writeAuditLog('delete', 'memo', 'メモ削除: ' . ($deleted['title'] ?? ''), ['id' => $memoId]);
        } catch (Exception $e) {
            errorResponse('データの保存に失敗しました', 500);
        }

        successResponse(['id' => $memoId], 'メモを削除しました');
        break;

    // ────────────────────────────────────────
    // ピン留めトグル（本人のみ）
    // ────────────────────────────────────────
    case 'memo_toggle_pin':
        $memoId = trim($_POST['memo_id'] ?? '');
        if ($memoId === '') errorResponse('メモIDが必要です', 400);

        $memo = &findOwnMemo($data['memos'], $memoId, $currentUser);
        $memo['pinned']     = !($memo['pinned'] ?? false);
        $memo['updated_at'] = date('Y-m-d H:i:s');

        try {
            saveData($data);
        } catch (Exception $e) {
            errorResponse('データの保存に失敗しました', 500);
        }

        successResponse(['pinned' => $memo['pinned']]);
        break;

    default:
        errorResponse('不正なアクションです', 400);
}
