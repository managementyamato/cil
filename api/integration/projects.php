<?php
/**
 * 案件データ受信API
 * POST: 案件データを登録・更新
 * GET: 案件データを取得
 */

require_once 'api-auth.php';

// CORS設定
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

// OPTIONSリクエスト（プリフライト）の処理
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 認証チェック
$auth = authenticateApiRequest();
if (!$auth['success']) {
    sendErrorResponse($auth['error'], $auth['code']);
}

$keyInfo = $auth['key_info'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetProjects($keyInfo);
        break;
    case 'POST':
        handlePostProjects($keyInfo);
        break;
    default:
        sendErrorResponse('許可されていないメソッドです', 405);
}

/**
 * 案件データ取得
 */
function handleGetProjects($keyInfo) {
    $data = getData();
    $projects = $data['projects'] ?? array();

    // IDで絞り込み
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        foreach ($projects as $project) {
            if ($project['id'] === $id) {
                logApiRequest('get_project', $keyInfo['name'], array('project_id' => $id));
                sendSuccessResponse($project, '案件を取得しました');
            }
        }
        sendErrorResponse('案件が見つかりません', 404);
    }

    // 外部IDで絞り込み
    if (isset($_GET['external_id'])) {
        $externalId = $_GET['external_id'];
        foreach ($projects as $project) {
            if (isset($project['external_id']) && $project['external_id'] === $externalId) {
                logApiRequest('get_project', $keyInfo['name'], array('external_id' => $externalId));
                sendSuccessResponse($project, '案件を取得しました');
            }
        }
        sendErrorResponse('案件が見つかりません', 404);
    }

    logApiRequest('get_projects', $keyInfo['name'], array('count' => count($projects)));
    sendSuccessResponse($projects, '案件一覧を取得しました');
}

/**
 * 案件データ登録・更新
 */
function handlePostProjects($keyInfo) {
    // JSONデータを取得
    $input = file_get_contents('php://input');
    $requestData = json_decode($input, true);

    if (!$requestData) {
        sendErrorResponse('無効なJSONデータです', 400);
    }

    // 一括登録の場合
    if (isset($requestData['projects']) && is_array($requestData['projects'])) {
        $results = processMultipleProjects($requestData['projects'], $keyInfo);
        logApiRequest('bulk_create_projects', $keyInfo['name'], array('count' => count($requestData['projects'])));
        sendSuccessResponse($results, '案件データを一括処理しました');
    }

    // 単一登録の場合
    $result = processSingleProject($requestData, $keyInfo);
    sendSuccessResponse($result, $result['action'] === 'created' ? '案件を登録しました' : '案件を更新しました');
}

/**
 * 単一案件を処理
 */
function processSingleProject($projectData, $keyInfo) {
    $data = getData();

    // 必須フィールドチェック
    if (empty($projectData['name'])) {
        sendErrorResponse('案件名は必須です', 400);
    }

    // 外部IDで既存チェック
    $existingIndex = null;
    if (!empty($projectData['external_id'])) {
        foreach ($data['projects'] as $index => $pj) {
            if (isset($pj['external_id']) && $pj['external_id'] === $projectData['external_id']) {
                $existingIndex = $index;
                break;
            }
        }
    }

    // 更新または新規作成
    if ($existingIndex !== null) {
        // 更新
        $existingProject = $data['projects'][$existingIndex];
        $updatedProject = array_merge($existingProject, array(
            'name' => $projectData['name'],
            'customer' => $projectData['customer'] ?? $existingProject['customer'] ?? '',
            'partner' => $projectData['partner'] ?? $existingProject['partner'] ?? '',
            'employees' => $projectData['employees'] ?? $existingProject['employees'] ?? '',
            'product' => $projectData['product'] ?? $existingProject['product'] ?? '',
            'start_date' => $projectData['start_date'] ?? $existingProject['start_date'] ?? '',
            'end_date' => $projectData['end_date'] ?? $existingProject['end_date'] ?? '',
            'sales' => $projectData['sales'] ?? $existingProject['sales'] ?? '',
            'cost' => $projectData['cost'] ?? $existingProject['cost'] ?? '',
            'memo' => $projectData['memo'] ?? $existingProject['memo'] ?? '',
            'external_id' => $projectData['external_id'],
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'API:' . $keyInfo['name']
        ));

        $data['projects'][$existingIndex] = $updatedProject;
        saveData($data);

        logApiRequest('update_project', $keyInfo['name'], array(
            'project_id' => $existingProject['id'],
            'external_id' => $projectData['external_id']
        ));

        return array('action' => 'updated', 'id' => $existingProject['id'], 'external_id' => $projectData['external_id']);
    } else {
        // 新規作成
        $newId = 'pj_' . date('YmdHis') . '_' . substr(uniqid(), -4);
        $newProject = array(
            'id' => $newId,
            'name' => $projectData['name'],
            'customer' => $projectData['customer'] ?? '',
            'partner' => $projectData['partner'] ?? '',
            'employees' => $projectData['employees'] ?? '',
            'product' => $projectData['product'] ?? '',
            'start_date' => $projectData['start_date'] ?? '',
            'end_date' => $projectData['end_date'] ?? '',
            'sales' => $projectData['sales'] ?? '',
            'cost' => $projectData['cost'] ?? '',
            'memo' => $projectData['memo'] ?? '',
            'external_id' => $projectData['external_id'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 'API:' . $keyInfo['name']
        );

        $data['projects'][] = $newProject;
        saveData($data);

        logApiRequest('create_project', $keyInfo['name'], array(
            'project_id' => $newId,
            'external_id' => $projectData['external_id'] ?? ''
        ));

        return array('action' => 'created', 'id' => $newId, 'external_id' => $projectData['external_id'] ?? '');
    }
}

/**
 * 複数案件を処理
 */
function processMultipleProjects($projectsData, $keyInfo) {
    $results = array(
        'created' => 0,
        'updated' => 0,
        'errors' => array()
    );

    foreach ($projectsData as $index => $projectData) {
        try {
            if (empty($projectData['name'])) {
                $results['errors'][] = array('index' => $index, 'error' => '案件名は必須です');
                continue;
            }
            $result = processSingleProjectInternal($projectData, $keyInfo);
            if ($result['action'] === 'created') {
                $results['created']++;
            } else {
                $results['updated']++;
            }
        } catch (Exception $e) {
            $results['errors'][] = array('index' => $index, 'error' => $e->getMessage());
        }
    }

    return $results;
}

/**
 * 単一案件を処理（内部用・エラーをthrow）
 */
function processSingleProjectInternal($projectData, $keyInfo) {
    $data = getData();

    // 外部IDで既存チェック
    $existingIndex = null;
    if (!empty($projectData['external_id'])) {
        foreach ($data['projects'] as $index => $pj) {
            if (isset($pj['external_id']) && $pj['external_id'] === $projectData['external_id']) {
                $existingIndex = $index;
                break;
            }
        }
    }

    if ($existingIndex !== null) {
        // 更新
        $existingProject = $data['projects'][$existingIndex];
        $updatedProject = array_merge($existingProject, array(
            'name' => $projectData['name'],
            'customer' => $projectData['customer'] ?? $existingProject['customer'] ?? '',
            'partner' => $projectData['partner'] ?? $existingProject['partner'] ?? '',
            'employees' => $projectData['employees'] ?? $existingProject['employees'] ?? '',
            'product' => $projectData['product'] ?? $existingProject['product'] ?? '',
            'start_date' => $projectData['start_date'] ?? $existingProject['start_date'] ?? '',
            'end_date' => $projectData['end_date'] ?? $existingProject['end_date'] ?? '',
            'sales' => $projectData['sales'] ?? $existingProject['sales'] ?? '',
            'cost' => $projectData['cost'] ?? $existingProject['cost'] ?? '',
            'memo' => $projectData['memo'] ?? $existingProject['memo'] ?? '',
            'external_id' => $projectData['external_id'],
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'API:' . $keyInfo['name']
        ));

        $data['projects'][$existingIndex] = $updatedProject;
        saveData($data);

        return array('action' => 'updated', 'id' => $existingProject['id']);
    } else {
        // 新規作成
        $newId = 'pj_' . date('YmdHis') . '_' . substr(uniqid(), -4);
        $newProject = array(
            'id' => $newId,
            'name' => $projectData['name'],
            'customer' => $projectData['customer'] ?? '',
            'partner' => $projectData['partner'] ?? '',
            'employees' => $projectData['employees'] ?? '',
            'product' => $projectData['product'] ?? '',
            'start_date' => $projectData['start_date'] ?? '',
            'end_date' => $projectData['end_date'] ?? '',
            'sales' => $projectData['sales'] ?? '',
            'cost' => $projectData['cost'] ?? '',
            'memo' => $projectData['memo'] ?? '',
            'external_id' => $projectData['external_id'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 'API:' . $keyInfo['name']
        );

        $data['projects'][] = $newProject;
        saveData($data);

        return array('action' => 'created', 'id' => $newId);
    }
}
