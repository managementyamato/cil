<?php
/**
 * タスク管理API
 *
 * GET  ?action=list           - タスク一覧取得
 * POST action=create          - タスク作成
 * POST action=update          - タスク更新
 * POST action=delete          - タスク削除
 * POST action=move            - タスク移動（ドラッグ&ドロップ）
 * POST action=reorder         - タスク並べ替え
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

// API初期化
initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'rateLimit' => 100,
    'allowedMethods' => ['GET', 'POST']
]);

$data = getData();
if (!isset($data['tasks'])) {
    $data['tasks'] = [];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// GETリクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'list':
            // フィルター
            $status = $_GET['status'] ?? null;
            $assignee = $_GET['assignee_id'] ?? null;
            $priority = $_GET['priority'] ?? null;

            $tasks = $data['tasks'];

            // フィルタリング
            if ($status) {
                $tasks = array_filter($tasks, fn($t) => ($t['status'] ?? 'todo') === $status);
            }
            if ($assignee) {
                $tasks = array_filter($tasks, fn($t) => ($t['assignee_id'] ?? '') === $assignee);
            }
            if ($priority) {
                $tasks = array_filter($tasks, fn($t) => ($t['priority'] ?? '') === $priority);
            }

            // ソート（order順）
            usort($tasks, function($a, $b) {
                $orderA = $a['order'] ?? 999999;
                $orderB = $b['order'] ?? 999999;
                return $orderA - $orderB;
            });

            successResponse([
                'tasks' => array_values($tasks),
                'total' => count($tasks)
            ]);
            break;

        default:
            errorResponse('Invalid action', 400);
    }
}

// POSTリクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'create':
            // バリデーション
            if (empty($input['title'])) {
                errorResponse('タイトルは必須です', 400);
            }

            // 同じステータス内の最大orderを取得
            $status = $input['status'] ?? 'todo';
            $maxOrder = 0;
            foreach ($data['tasks'] as $t) {
                if (($t['status'] ?? 'todo') === $status) {
                    $maxOrder = max($maxOrder, $t['order'] ?? 0);
                }
            }

            $newTask = [
                'id' => 'task_' . uniqid(),
                'title' => trim($input['title']),
                'description' => trim($input['description'] ?? ''),
                'status' => $status,
                'priority' => $input['priority'] ?? 'medium',
                'assignee_id' => $input['assignee_id'] ?? '',
                'assignee_name' => $input['assignee_name'] ?? '',
                'due_date' => $input['due_date'] ?? '',
                'parent_id' => $input['parent_id'] ?? '',
                'order' => $maxOrder + 1,
                'labels' => $input['labels'] ?? [],
                'created_by' => $_SESSION['user_email'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $data['tasks'][] = $newTask;
            saveData($data);

            successResponse(['task' => $newTask], 'タスクを作成しました');
            break;

        case 'update':
            if (empty($input['id'])) {
                errorResponse('IDは必須です', 400);
            }

            $taskId = $input['id'];
            $found = false;
            $updatedTask = null;

            foreach ($data['tasks'] as &$task) {
                if ($task['id'] === $taskId) {
                    // 更新可能フィールド
                    $editableFields = ['title', 'description', 'status', 'priority',
                                       'assignee_id', 'assignee_name', 'due_date',
                                       'parent_id', 'order', 'labels'];

                    foreach ($editableFields as $field) {
                        if (isset($input[$field])) {
                            $task[$field] = $input[$field];
                        }
                    }

                    $task['updated_at'] = date('Y-m-d H:i:s');
                    $updatedTask = $task;
                    $found = true;
                    break;
                }
            }
            unset($task);

            if (!$found) {
                errorResponse('タスクが見つかりません', 404);
            }

            saveData($data);
            successResponse(['task' => $updatedTask], 'タスクを更新しました');
            break;

        case 'delete':
            if (empty($input['id'])) {
                errorResponse('IDは必須です', 400);
            }

            $taskId = $input['id'];
            $beforeCount = count($data['tasks']);

            // サブタスクも削除
            $data['tasks'] = array_filter($data['tasks'], function($t) use ($taskId) {
                return $t['id'] !== $taskId && ($t['parent_id'] ?? '') !== $taskId;
            });
            $data['tasks'] = array_values($data['tasks']);

            if (count($data['tasks']) === $beforeCount) {
                errorResponse('タスクが見つかりません', 404);
            }

            saveData($data);
            successResponse(null, 'タスクを削除しました');
            break;

        case 'move':
            // ドラッグ&ドロップによる移動
            if (empty($input['task_id'])) {
                errorResponse('task_idは必須です', 400);
            }

            $taskId = $input['task_id'];
            $newStatus = $input['new_status'] ?? null;
            $newOrder = $input['new_order'] ?? null;

            $found = false;
            $oldStatus = null;

            // タスクを見つけてステータス更新
            foreach ($data['tasks'] as &$task) {
                if ($task['id'] === $taskId) {
                    $oldStatus = $task['status'] ?? 'todo';
                    if ($newStatus !== null) {
                        $task['status'] = $newStatus;
                    }
                    if ($newOrder !== null) {
                        $task['order'] = (int)$newOrder;
                    }
                    $task['updated_at'] = date('Y-m-d H:i:s');
                    $found = true;
                    break;
                }
            }
            unset($task);

            if (!$found) {
                errorResponse('タスクが見つかりません', 404);
            }

            // 同じステータス内の他タスクのorderを再計算
            $targetStatus = $newStatus ?? $oldStatus;
            $tasksInStatus = array_filter($data['tasks'], fn($t) => ($t['status'] ?? 'todo') === $targetStatus);
            usort($tasksInStatus, fn($a, $b) => ($a['order'] ?? 0) - ($b['order'] ?? 0));

            $order = 1;
            foreach ($tasksInStatus as $t) {
                foreach ($data['tasks'] as &$task) {
                    if ($task['id'] === $t['id']) {
                        $task['order'] = $order++;
                        break;
                    }
                }
                unset($task);
            }

            saveData($data);
            successResponse(null, 'タスクを移動しました');
            break;

        case 'reorder':
            // カラム内の並べ替え
            if (empty($input['task_ids']) || !is_array($input['task_ids'])) {
                errorResponse('task_idsは必須です', 400);
            }

            $taskIds = $input['task_ids'];
            $order = 1;

            foreach ($taskIds as $taskId) {
                foreach ($data['tasks'] as &$task) {
                    if ($task['id'] === $taskId) {
                        $task['order'] = $order++;
                        $task['updated_at'] = date('Y-m-d H:i:s');
                        break;
                    }
                }
                unset($task);
            }

            saveData($data);
            successResponse(null, '並び順を更新しました');
            break;

        default:
            errorResponse('Invalid action', 400);
    }
}
