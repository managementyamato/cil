<?php
/**
 * リード管理 CRUD API
 * pages/leads.php から呼び出される
 *
 * リード: 名刺情報から登録、ステータス管理（未接触→商談中→受注/失注）
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
            $leads = filterDeleted($data['leads'] ?? []);
            // ステータスフィルター
            $statusFilter = $_GET['status'] ?? '';
            if ($statusFilter) {
                $leads = array_values(array_filter($leads, function($l) use ($statusFilter) {
                    return ($l['status'] ?? '未接触') === $statusFilter;
                }));
            }
            // 作成日降順
            usort($leads, function($a, $b) {
                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
            });
            successResponse(['leads' => array_values($leads)]);
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
        $companyName = trim($_POST['company_name'] ?? '');
        if (empty($companyName)) {
            errorResponse('会社名は必須です', 400);
        }

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
        successResponse(['lead' => $lead]);
        break;

    case 'update':
        $id = trim($_POST['id'] ?? '');
        if (empty($id)) errorResponse('IDは必須です', 400);

        $found = false;
        foreach ($data['leads'] as &$lead) {
            if (($lead['id'] ?? '') !== $id) continue;
            if (!empty($lead['deleted_at'])) errorResponse('削除済みのリードです', 400);

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
            $updatedLead = $lead;
            break;
        }
        unset($lead);

        if (!$found) errorResponse('リードが見つかりません', 404);
        saveData($data);
        successResponse(['lead' => $updatedLead]);
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
