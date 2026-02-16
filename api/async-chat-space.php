<?php
/**
 * 非同期でGoogle Chatスペースを作成するAPI
 * 案件登録後にAJAXで呼び出される
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/google-chat.php';

// API初期化
initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'rateLimit' => 30,
    'allowedMethods' => ['POST']
]);

$input = getJsonInput();

// 必須パラメータ
$projectId = $input['project_id'] ?? '';
$spaceName = $input['space_name'] ?? '';
$existingSpaceId = $input['existing_space_id'] ?? '';

if (empty($projectId)) {
    errorResponse('project_id is required', 400);
}

// 従業員データからchat_member=trueのメンバーを取得
$members = [];
$data = getData();
$employees = $data['employees'] ?? [];
foreach ($employees as $emp) {
    // chat_memberフラグがtrueまたは未設定（後方互換性）で、メールアドレスがある、退職していない従業員
    $isChatMember = !isset($emp['chat_member']) || $emp['chat_member'] === true;
    $hasEmail = !empty($emp['email']);
    $isRetired = !empty($emp['leave_date']);
    if ($isChatMember && $hasEmail && !$isRetired) {
        $members[] = $emp['email'];
    }
}

$googleChat = new GoogleChatClient();

if (!$googleChat->isConfigured()) {
    successResponse(['skipped' => true, 'reason' => 'Google Chat not configured']);
}

$result = [
    'project_id' => $projectId,
    'space_created' => false,
    'space_id' => '',
    'members_added' => 0
];

try {
    if (empty($existingSpaceId) && !empty($spaceName)) {
        // 新規スペース作成
        $createResult = $googleChat->createSpaceWithMembers($spaceName, $members);

        if ($createResult['success'] && !empty($createResult['space'])) {
            $spaceId = $createResult['space']['name'] ?? '';
            $result['space_created'] = true;
            $result['space_id'] = $spaceId;
            $result['members_added'] = $createResult['members_result']['success'] ?? 0;

            // data.jsonのプロジェクトを更新
            updateProjectChatSpaceId($projectId, $spaceId);

            logInfo('非同期でChatスペースを作成', [
                'project_id' => $projectId,
                'space_id' => $spaceId,
                'members_added' => $result['members_added']
            ]);
        } else {
            logWarning('非同期Chatスペース作成失敗', [
                'project_id' => $projectId,
                'error' => $createResult['error'] ?? 'Unknown error'
            ]);
            $result['error'] = $createResult['error'] ?? 'Failed to create space';
        }
    } elseif (!empty($existingSpaceId) && !empty($members)) {
        // 既存スペースにメンバー追加
        $addResult = $googleChat->addMembers($existingSpaceId, $members);
        $result['space_id'] = $existingSpaceId;
        $result['members_added'] = $addResult['success'] ?? 0;

        logInfo('非同期でChatメンバーを追加', [
            'project_id' => $projectId,
            'space_id' => $existingSpaceId,
            'success' => $addResult['success'] ?? 0,
            'failed' => $addResult['failed'] ?? 0
        ]);
    }

    successResponse($result);

} catch (Exception $e) {
    logError('非同期Chatスペース処理エラー', [
        'project_id' => $projectId,
        'error' => $e->getMessage()
    ]);
    errorResponse($e->getMessage(), 500);
}

/**
 * プロジェクトのchat_space_idを更新
 */
function updateProjectChatSpaceId($projectId, $spaceId) {
    $data = getData();

    foreach ($data['projects'] as &$project) {
        if ($project['id'] === $projectId) {
            $project['chat_space_id'] = $spaceId;
            break;
        }
    }
    unset($project);

    saveData($data);
    return true;
}
