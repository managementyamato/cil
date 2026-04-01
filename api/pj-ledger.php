<?php
/**
 * PJ管理台帳 CRUD API
 * 専用JSONファイル (pj-ledger.json) を使用
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/../functions/pj-ledger-data.php';

// ─── GET: 読み取り ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    initApi([
        'requireAuth'    => true,
        'requireCsrf'    => false,
        'allowedMethods' => ['GET'],
    ]);

    $action = $_GET['action'] ?? '';
    $pjData = getPjLedgerData();

    switch ($action) {
        case 'list':
            $projects = filterPjDeleted($pjData['projects'] ?? []);
            // ソート: No 降順
            usort($projects, function($a, $b) {
                return ($b['no'] ?? 0) - ($a['no'] ?? 0);
            });
            successResponse(['projects' => array_values($projects)]);
            break;

        case 'get':
            $id = $_GET['id'] ?? '';
            if (empty($id)) errorResponse('IDは必須です', 400);
            $project = null;
            foreach (filterPjDeleted($pjData['projects'] ?? []) as $item) {
                if (($item['id'] ?? '') === $id) {
                    $project = $item;
                    break;
                }
            }
            if (!$project) errorResponse('見つかりません', 404);
            successResponse($project);
            break;

        case 'monthly_profits':
            $projectId = $_GET['project_id'] ?? '';
            if (empty($projectId)) errorResponse('project_idは必須です', 400);
            $profits = array_values(array_filter($pjData['monthly_profits'] ?? [], function($p) use ($projectId) {
                return ($p['project_id'] ?? '') === $projectId && empty($p['deleted_at']);
            }));
            successResponse(['monthly_profits' => $profits]);
            break;

        default:
            errorResponse('不正なアクションです', 400);
    }
    exit;
}

// ─── POST: 作成・更新・削除 ──────────────────────────
initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['POST'],
]);

$pjData = getPjLedgerData();
$action = $_POST['action'] ?? '';
$currentUser = $_SESSION['user_email'];
$now = date('Y-m-d H:i:s');

switch ($action) {
    case 'create':
        if (!canEdit()) errorResponse('編集権限がありません', 403);

        // 次のNoを自動採番
        $maxNo = 0;
        foreach ($pjData['projects'] as $p) {
            $no = (int)($p['no'] ?? 0);
            if ($no > $maxNo) $maxNo = $no;
        }

        $project = buildProjectFromPost($maxNo + 1, $currentUser, $now);
        $pjData['projects'][] = $project;
        savePjLedgerData($pjData);
        successResponse(['project' => $project], '登録しました');
        break;

    case 'update':
        if (!canEdit()) errorResponse('編集権限がありません', 403);
        $id = trim($_POST['id'] ?? '');
        if (empty($id)) errorResponse('IDは必須です', 400);

        $found = false;
        foreach ($pjData['projects'] as &$project) {
            if (($project['id'] ?? '') !== $id) continue;
            if (!empty($project['deleted_at'])) errorResponse('削除済みです', 400);

            updateProjectFromPost($project, $now);
            $found = true;
            break;
        }
        unset($project);

        if (!$found) errorResponse('見つかりません', 404);
        savePjLedgerData($pjData);
        successResponse([], '更新しました');
        break;

    case 'delete':
        if (!canDelete()) errorResponse('削除権限がありません', 403);
        $id = trim($_POST['id'] ?? '');
        if (empty($id)) errorResponse('IDは必須です', 400);

        $found = false;
        foreach ($pjData['projects'] as &$project) {
            if (($project['id'] ?? '') !== $id) continue;
            $project['deleted_at'] = $now;
            $project['deleted_by'] = $currentUser;
            $found = true;
            break;
        }
        unset($project);

        if (!$found) errorResponse('見つかりません', 404);
        savePjLedgerData($pjData);
        successResponse([], '削除しました');
        break;

    case 'save_monthly_profit':
        if (!canEdit()) errorResponse('編集権限がありません', 403);
        $projectId = trim($_POST['project_id'] ?? '');
        $month = trim($_POST['month'] ?? '');       // e.g. "2024/4"
        $amount = (int)($_POST['amount'] ?? 0);
        if (empty($projectId) || empty($month)) errorResponse('project_idとmonthは必須です', 400);

        // 既存データを更新 or 新規追加
        $found = false;
        foreach ($pjData['monthly_profits'] as &$mp) {
            if (($mp['project_id'] ?? '') === $projectId && ($mp['month'] ?? '') === $month) {
                $mp['amount'] = $amount;
                $mp['updated_at'] = $now;
                $found = true;
                break;
            }
        }
        unset($mp);

        if (!$found) {
            $pjData['monthly_profits'][] = [
                'id'         => uniqid('mp_'),
                'project_id' => $projectId,
                'month'      => $month,
                'amount'     => $amount,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        savePjLedgerData($pjData);
        successResponse([], '月間純利を保存しました');
        break;

    default:
        errorResponse('不正なアクションです', 400);
}

// ─── ヘルパー関数 ────────────────────────────────────

function buildProjectFromPost($no, $currentUser, $now) {
    return [
        'id'                    => uniqid('pj_'),
        'no'                    => $no,
        'pj_number'             => trim($_POST['pj_number'] ?? ''),
        'sales_dept'            => trim($_POST['sales_dept'] ?? ''),
        'ya_person'             => trim($_POST['ya_person'] ?? ''),
        'space'                 => trim($_POST['space'] ?? ''),
        'invoice_number'        => trim($_POST['invoice_number'] ?? ''),
        'project_name'          => trim($_POST['project_name'] ?? ''),
        'dealer'                => trim($_POST['dealer'] ?? ''),
        'branch_name'           => trim($_POST['branch_name'] ?? ''),
        'contact_email'         => trim($_POST['contact_email'] ?? ''),
        'type'                  => trim($_POST['type'] ?? ''),           // レンタル/販売
        'manufacturer'          => trim($_POST['manufacturer'] ?? ''),
        'indoor_outdoor'        => trim($_POST['indoor_outdoor'] ?? ''),
        'pitch'                 => trim($_POST['pitch'] ?? ''),
        'horizontal_panels'     => (int)($_POST['horizontal_panels'] ?? 0),
        'vertical_panels'       => (int)($_POST['vertical_panels'] ?? 0),
        'total_panels'          => (int)($_POST['total_panels'] ?? 0),
        'led_size'              => trim($_POST['led_size'] ?? ''),
        'mic1'                  => trim($_POST['mic1'] ?? ''),
        'mic2'                  => trim($_POST['mic2'] ?? ''),
        'orientation'           => trim($_POST['orientation'] ?? ''),    // 縦横
        'color'                 => trim($_POST['color'] ?? ''),
        'lcd_size'              => trim($_POST['lcd_size'] ?? ''),
        'cms_player'            => trim($_POST['cms_player'] ?? ''),
        'router'                => trim($_POST['router'] ?? ''),
        'construction_date'     => trim($_POST['construction_date'] ?? ''),
        'end_date'              => trim($_POST['end_date'] ?? ''),
        'warranty_end_date'     => trim($_POST['warranty_end_date'] ?? ''),
        'rental_days'           => (int)($_POST['rental_days'] ?? 0),
        'sales_working_days'    => (int)($_POST['sales_working_days'] ?? 0),
        'status'                => trim($_POST['status'] ?? ''),        // 使用中/終了/キャンセル
        'period_months'         => (int)($_POST['period_months'] ?? 0),
        'total_sales_estimate'  => (int)($_POST['total_sales_estimate'] ?? 0),
        'actual_invoice_amount' => (int)($_POST['actual_invoice_amount'] ?? 0),
        'deviation_rate'        => trim($_POST['deviation_rate'] ?? ''),
        'initial_cost'          => (int)($_POST['initial_cost'] ?? 0),
        'discount_amount'       => (int)($_POST['discount_amount'] ?? 0),
        'monthly_rental_sales'  => (int)($_POST['monthly_rental_sales'] ?? 0),
        'additional_sales'      => (int)($_POST['additional_sales'] ?? 0),
        'additional_material_cost' => (int)($_POST['additional_material_cost'] ?? 0),
        'support_material_cost' => (int)($_POST['support_material_cost'] ?? 0),
        'expenses'              => (int)($_POST['expenses'] ?? 0),
        'profit'                => (int)($_POST['profit'] ?? 0),
        'profit_rate'           => trim($_POST['profit_rate'] ?? ''),
        'shipping_cost'         => (int)($_POST['shipping_cost'] ?? 0),
        'new_install_material_cost' => (int)($_POST['new_install_material_cost'] ?? 0),
        'monthly_material_cost' => (int)($_POST['monthly_material_cost'] ?? 0),
        'support_cost'          => (int)($_POST['support_cost'] ?? 0),
        'tech_cost_ratio_estimate' => trim($_POST['tech_cost_ratio_estimate'] ?? ''),
        'tech_cost_ratio_actual'   => trim($_POST['tech_cost_ratio_actual'] ?? ''),
        'used_panel_count'      => (int)($_POST['used_panel_count'] ?? 0),
        'remarks'               => trim($_POST['remarks'] ?? ''),
        'created_by'            => $currentUser,
        'created_at'            => $now,
        'updated_at'            => $now,
    ];
}

function updateProjectFromPost(&$project, $now) {
    $fields = [
        'pj_number', 'sales_dept', 'ya_person', 'space', 'invoice_number',
        'project_name', 'dealer', 'branch_name', 'contact_email',
        'type', 'manufacturer', 'indoor_outdoor', 'pitch',
        'led_size', 'mic1', 'mic2', 'orientation', 'color',
        'lcd_size', 'cms_player', 'router',
        'construction_date', 'end_date', 'warranty_end_date',
        'status', 'deviation_rate', 'profit_rate',
        'tech_cost_ratio_estimate', 'tech_cost_ratio_actual', 'remarks',
    ];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $project[$f] = trim($_POST[$f]);
        }
    }

    $intFields = [
        'horizontal_panels', 'vertical_panels', 'total_panels',
        'rental_days', 'sales_working_days', 'period_months',
        'total_sales_estimate', 'actual_invoice_amount',
        'initial_cost', 'discount_amount', 'monthly_rental_sales',
        'additional_sales', 'additional_material_cost', 'support_material_cost',
        'expenses', 'profit', 'shipping_cost',
        'new_install_material_cost', 'monthly_material_cost', 'support_cost',
        'used_panel_count',
    ];
    foreach ($intFields as $f) {
        if (isset($_POST[$f])) {
            $project[$f] = (int)$_POST[$f];
        }
    }

    $project['updated_at'] = $now;
}
