<?php
/**
 * 商談パイプライン API
 * pages/pipeline.php から呼び出される
 *
 * 商談: ステージ管理（リード→受注/失注）、金額・確度管理
 * 削除: adminのみ（論理削除）
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

// GETリクエストはCSRF不要
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
            $deals = filterDeleted($data['deals'] ?? []);

            // フィルター: ステージ
            $stageFilter = $_GET['stage'] ?? '';
            if ($stageFilter) {
                $deals = array_values(array_filter($deals, function($d) use ($stageFilter) {
                    return ($d['stage'] ?? '') === $stageFilter;
                }));
            }

            // フィルター: 担当者
            $assigneeFilter = $_GET['assignee'] ?? '';
            if ($assigneeFilter) {
                $deals = array_values(array_filter($deals, function($d) use ($assigneeFilter) {
                    return ($d['assignee'] ?? '') === $assigneeFilter;
                }));
            }

            // 作成日降順
            usort($deals, function($a, $b) {
                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
            });

            // 従業員リストも返す
            $employees = filterDeleted($data['employees'] ?? []);
            $employeeNames = [];
            foreach ($employees as $emp) {
                $employeeNames[] = $emp['name'] ?? '';
            }

            successResponse([
                'deals' => array_values($deals),
                'employees' => $employeeNames,
            ]);
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

$input       = getJsonInput();
$action      = $input['action'] ?? '';
$data        = getData();
$currentUser = $_SESSION['user_email'];
$now         = date('Y-m-d H:i:s');

switch ($action) {

    case 'create':
        if (!canEditCurrentPage()) {
            errorResponse('編集権限がありません', 403);
        }

        $customerName = trim($input['customer_name'] ?? '');
        $title = trim($input['title'] ?? '');
        if (empty($customerName) || empty($title)) {
            errorResponse('顧客名と案件名は必須です', 400);
        }

        $deal = [
            'id'                  => uniqid('deal_'),
            'customer_name'       => $customerName,
            'title'               => $title,
            'amount'              => (int)($input['amount'] ?? 0),
            'probability'         => (int)($input['probability'] ?? 10),
            'stage'               => trim($input['stage'] ?? 'リード'),
            'assignee'            => trim($input['assignee'] ?? ''),
            'expected_close_date' => trim($input['expected_close_date'] ?? ''),
            'memo'                => trim($input['memo'] ?? ''),
            'created_by'          => $currentUser,
            'created_at'          => $now,
            'updated_at'          => $now,
            'deleted_at'          => null,
            'deleted_by'          => null,
        ];

        if (!isset($data['deals'])) {
            $data['deals'] = [];
        }
        $data['deals'][] = $deal;
        saveData($data);

        auditCreate('deals', $deal['id'], '商談を作成: ' . $title, $deal);
        successResponse(['deal' => $deal], '商談を作成しました');
        break;

    case 'update':
        if (!canEditCurrentPage()) {
            errorResponse('編集権限がありません', 403);
        }

        $id = $input['id'] ?? '';
        if (empty($id)) {
            errorResponse('IDが必要です', 400);
        }

        $found = false;
        $oldData = null;
        foreach ($data['deals'] as &$deal) {
            if ($deal['id'] === $id && empty($deal['deleted_at'])) {
                $oldData = $deal;
                $deal['customer_name']       = trim($input['customer_name'] ?? $deal['customer_name']);
                $deal['title']               = trim($input['title'] ?? $deal['title']);
                $deal['amount']              = (int)($input['amount'] ?? $deal['amount']);
                $deal['probability']         = (int)($input['probability'] ?? $deal['probability']);
                $deal['stage']               = trim($input['stage'] ?? $deal['stage']);
                $deal['assignee']            = trim($input['assignee'] ?? $deal['assignee']);
                $deal['expected_close_date'] = trim($input['expected_close_date'] ?? $deal['expected_close_date']);
                $deal['memo']                = trim($input['memo'] ?? $deal['memo']);
                $deal['updated_at']          = $now;
                $found = true;
                $updatedDeal = $deal;
                break;
            }
        }
        unset($deal);

        if (!$found) {
            errorResponse('商談が見つかりません', 404);
        }

        saveData($data);
        auditUpdate('deals', $id, '商談を更新', $oldData, $updatedDeal);
        successResponse(['deal' => $updatedDeal], '商談を更新しました');
        break;

    case 'change_stage':
        if (!canEditCurrentPage()) {
            errorResponse('編集権限がありません', 403);
        }

        $id = $input['id'] ?? '';
        $newStage = trim($input['stage'] ?? '');
        if (empty($id) || empty($newStage)) {
            errorResponse('IDとステージは必須です', 400);
        }

        $found = false;
        $oldData = null;
        foreach ($data['deals'] as &$deal) {
            if ($deal['id'] === $id && empty($deal['deleted_at'])) {
                $oldData = $deal;
                $deal['stage'] = $newStage;
                $deal['updated_at'] = $now;
                $found = true;
                $updatedDeal = $deal;
                break;
            }
        }
        unset($deal);

        if (!$found) {
            errorResponse('商談が見つかりません', 404);
        }

        saveData($data);
        auditUpdate('deals', $id, 'ステージ変更: ' . $newStage, $oldData, $updatedDeal);
        successResponse(['deal' => $updatedDeal], 'ステージを変更しました');
        break;

    case 'delete':
        if (!canDelete()) {
            errorResponse('削除権限がありません', 403);
        }

        $id = $input['id'] ?? '';
        if (empty($id)) {
            errorResponse('IDが必要です', 400);
        }

        $deletedDeal = softDelete($data['deals'], $id);
        if (!$deletedDeal) {
            errorResponse('商談が見つかりません', 404);
        }

        saveData($data);
        auditDelete('deals', $id, '商談を削除: ' . ($deletedDeal['title'] ?? ''), $deletedDeal);
        successResponse([], '商談を削除しました');
        break;

    default:
        errorResponse('不正なアクションです', 400);
}
