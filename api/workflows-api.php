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
$currentUserName = $_SESSION['user_name'] ?? '';

switch ($action) {
    case 'list':
        handleList($currentUser);
        break;
    case 'create':
        handleCreate($input, $currentUser, $currentUserName);
        break;
    case 'update':
        handleUpdate($input, $currentUser);
        break;
    case 'delete':
        handleDelete($input, $currentUser);
        break;
    case 'submit':
        handleSubmit($input, $currentUser);
        break;
    case 'approve':
        handleApprove($input, $currentUser, $currentUserName);
        break;
    case 'reject':
        handleReject($input, $currentUser, $currentUserName);
        break;
    case 'cancel':
        handleCancel($input, $currentUser);
        break;
    default:
        errorResponse('不正なアクションです', 400);
}

function handleList($currentUser) {
    $data = getData();
    $requests = filterDeleted($data['workflow_requests'] ?? []);

    // 新しい順にソート
    usort($requests, function($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    successResponse($requests);
}

function handleCreate($input, $currentUser, $currentUserName) {
    requireParams($input, ['workflow_type', 'title']);

    $approvers = [];
    if (!empty($input['approvers']) && is_array($input['approvers'])) {
        foreach ($input['approvers'] as $a) {
            $approvers[] = [
                'email' => sanitizeInput($a['email'] ?? '', 'string'),
                'name' => sanitizeInput($a['name'] ?? '', 'string'),
                'status' => '未承認',
                'comment' => '',
                'acted_at' => null,
            ];
        }
    }

    $request = [
        'id' => uniqid('wf_'),
        'workflow_type' => sanitizeInput($input['workflow_type'], 'string'),
        'title' => sanitizeInput($input['title'], 'string'),
        'description' => sanitizeInput($input['description'] ?? '', 'string'),
        'amount' => isset($input['amount']) ? (float)$input['amount'] : null,
        'details' => sanitizeInput($input['details'] ?? '', 'string'),
        'approvers' => $approvers,
        'current_step' => 0,
        'status' => '下書き',
        'submitted_by' => $currentUser,
        'submitted_by_name' => $currentUserName,
        'submitted_at' => null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'deleted_at' => null,
        'deleted_by' => null,
    ];

    $data = getData();
    if (!isset($data['workflow_requests'])) {
        $data['workflow_requests'] = [];
    }
    $data['workflow_requests'][] = $request;
    saveData($data);

    auditCreate('workflow_requests', $request['id'], 'ワークフロー申請を作成: ' . $request['title'], $request);

    successResponse($request, '作成しました');
}

function handleUpdate($input, $currentUser) {
    requireParams($input, ['id']);

    $data = getData();
    $requests = &$data['workflow_requests'];
    $found = false;

    for ($i = 0; $i < count($requests); $i++) {
        if ($requests[$i]['id'] === $input['id']) {
            if ($requests[$i]['submitted_by'] !== $currentUser && !isAdmin()) {
                errorResponse('編集権限がありません', 403);
            }
            if ($requests[$i]['status'] !== '下書き') {
                errorResponse('下書き以外は編集できません', 400);
            }

            $oldData = $requests[$i];

            if (isset($input['workflow_type'])) $requests[$i]['workflow_type'] = sanitizeInput($input['workflow_type'], 'string');
            if (isset($input['title'])) $requests[$i]['title'] = sanitizeInput($input['title'], 'string');
            if (isset($input['description'])) $requests[$i]['description'] = sanitizeInput($input['description'], 'string');
            if (array_key_exists('amount', $input)) $requests[$i]['amount'] = $input['amount'] !== null ? (float)$input['amount'] : null;
            if (isset($input['details'])) $requests[$i]['details'] = sanitizeInput($input['details'], 'string');

            if (isset($input['approvers']) && is_array($input['approvers'])) {
                $approvers = [];
                foreach ($input['approvers'] as $a) {
                    $approvers[] = [
                        'email' => sanitizeInput($a['email'] ?? '', 'string'),
                        'name' => sanitizeInput($a['name'] ?? '', 'string'),
                        'status' => '未承認',
                        'comment' => '',
                        'acted_at' => null,
                    ];
                }
                $requests[$i]['approvers'] = $approvers;
                $requests[$i]['current_step'] = 0;
            }

            $requests[$i]['updated_at'] = date('Y-m-d H:i:s');
            $found = true;

            auditUpdate('workflow_requests', $input['id'], 'ワークフロー申請を更新', $oldData, $requests[$i]);
            break;
        }
    }

    if (!$found) {
        errorResponse('申請が見つかりません', 404);
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
    $requests = &$data['workflow_requests'];

    for ($i = 0; $i < count($requests); $i++) {
        if ($requests[$i]['id'] === $input['id']) {
            $deletedItem = $requests[$i];
            $requests[$i]['deleted_at'] = date('Y-m-d H:i:s');
            $requests[$i]['deleted_by'] = $currentUser;
            saveData($data);
            auditDelete('workflow_requests', $input['id'], 'ワークフロー申請を削除', $deletedItem);
            successResponse(null, '削除しました');
        }
    }

    errorResponse('申請が見つかりません', 404);
}

function handleSubmit($input, $currentUser) {
    requireParams($input, ['id']);

    $data = getData();
    $requests = &$data['workflow_requests'];

    for ($i = 0; $i < count($requests); $i++) {
        if ($requests[$i]['id'] === $input['id']) {
            if ($requests[$i]['submitted_by'] !== $currentUser && !isAdmin()) {
                errorResponse('申請権限がありません', 403);
            }
            if ($requests[$i]['status'] !== '下書き') {
                errorResponse('下書き以外は申請できません', 400);
            }
            if (empty($requests[$i]['approvers'])) {
                errorResponse('承認者を設定してください', 400);
            }

            $requests[$i]['status'] = '申請中';
            $requests[$i]['submitted_at'] = date('Y-m-d H:i:s');
            $requests[$i]['updated_at'] = date('Y-m-d H:i:s');
            saveData($data);

            successResponse(null, '申請しました');
        }
    }

    errorResponse('申請が見つかりません', 404);
}

function handleApprove($input, $currentUser, $currentUserName) {
    requireParams($input, ['id']);

    $comment = sanitizeInput($input['comment'] ?? '', 'string');
    $data = getData();
    $requests = &$data['workflow_requests'];

    for ($i = 0; $i < count($requests); $i++) {
        if ($requests[$i]['id'] === $input['id']) {
            if ($requests[$i]['status'] !== '申請中') {
                errorResponse('申請中のもののみ承認できます', 400);
            }

            $step = $requests[$i]['current_step'];
            if (!isset($requests[$i]['approvers'][$step]) || $requests[$i]['approvers'][$step]['email'] !== $currentUser) {
                errorResponse('現在の承認者ではありません', 403);
            }

            $requests[$i]['approvers'][$step]['status'] = '承認';
            $requests[$i]['approvers'][$step]['comment'] = $comment;
            $requests[$i]['approvers'][$step]['acted_at'] = date('Y-m-d H:i:s');

            // 全承認者が承認済みかチェック
            if ($step + 1 >= count($requests[$i]['approvers'])) {
                $requests[$i]['status'] = '承認済み';
            } else {
                $requests[$i]['current_step'] = $step + 1;
            }

            $requests[$i]['updated_at'] = date('Y-m-d H:i:s');
            saveData($data);

            successResponse(null, '承認しました');
        }
    }

    errorResponse('申請が見つかりません', 404);
}

function handleReject($input, $currentUser, $currentUserName) {
    requireParams($input, ['id']);

    $comment = sanitizeInput($input['comment'] ?? '', 'string');
    $data = getData();
    $requests = &$data['workflow_requests'];

    for ($i = 0; $i < count($requests); $i++) {
        if ($requests[$i]['id'] === $input['id']) {
            if ($requests[$i]['status'] !== '申請中') {
                errorResponse('申請中のもののみ差戻しできます', 400);
            }

            $step = $requests[$i]['current_step'];
            if (!isset($requests[$i]['approvers'][$step]) || $requests[$i]['approvers'][$step]['email'] !== $currentUser) {
                errorResponse('現在の承認者ではありません', 403);
            }

            $requests[$i]['approvers'][$step]['status'] = '差戻し';
            $requests[$i]['approvers'][$step]['comment'] = $comment;
            $requests[$i]['approvers'][$step]['acted_at'] = date('Y-m-d H:i:s');
            $requests[$i]['status'] = '差戻し';
            $requests[$i]['updated_at'] = date('Y-m-d H:i:s');

            saveData($data);

            successResponse(null, '差戻ししました');
        }
    }

    errorResponse('申請が見つかりません', 404);
}

function handleCancel($input, $currentUser) {
    requireParams($input, ['id']);

    $data = getData();
    $requests = &$data['workflow_requests'];

    for ($i = 0; $i < count($requests); $i++) {
        if ($requests[$i]['id'] === $input['id']) {
            if ($requests[$i]['submitted_by'] !== $currentUser && !isAdmin()) {
                errorResponse('取消し権限がありません', 403);
            }
            if ($requests[$i]['status'] !== '申請中') {
                errorResponse('申請中のもののみ取消しできます', 400);
            }

            $requests[$i]['status'] = '取消し';
            $requests[$i]['updated_at'] = date('Y-m-d H:i:s');
            saveData($data);

            successResponse(null, '取消ししました');
        }
    }

    errorResponse('申請が見つかりません', 404);
}
