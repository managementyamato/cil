<?php
// api/master.php - Project management API for Next.js frontend
// GET: project list + master data | POST: create/update project

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth'    => true,
    'requireCsrf'    => true,
    'allowedMethods' => ['GET', 'POST'],
]);

// GET: project list + master data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!canEdit()) { errorResponse('権限がありません', 403); }
    $data     = getData();
    $projects = array_values(array_filter($data['projects'] ?? [], function ($p) {
        return empty($p['deleted_at']);
    }));
    $statuses = ['案件発生', '成約', '製品手配中', '設置予定', '設置済', '完了'];
    successResponse(['projects' => $projects, 'statuses' => $statuses]);
}

// POST: permission check
if (!canEdit()) { errorResponse('権限がありません', 403); }

$input  = getJsonInput();
$action = $input['action'] ?? $_POST['action'] ?? '';

// Helper: generate next P-number (P1, P2, ...)
function apiGenerateNextPjNumber(array $projects): string {
    $max = 0;
    foreach ($projects as $pj) {
        if (preg_match('/^P(\d+)$/i', $pj['id'] ?? '', $m)) $max = max($max, (int)$m[1]);
    }
    return 'P' . ($max + 1);
}
function apiPjNumberExists(array $projects, string $n): bool {
    foreach ($projects as $pj) { if (($pj['id'] ?? '') === $n) return true; }
    return false;
}
function apiGetConfirmedPjNumber(array $projects, string $req): string {
    return (empty($req) || apiPjNumberExists($projects, $req)) ? apiGenerateNextPjNumber($projects) : $req;
}
function apiField(array $input, string $key, string $postKey = ''): string {
    $v = $input[$key] ?? $_POST[$postKey ?: $key] ?? '';
    return trim((string)$v);
}

// Create action
if ($action === 'create') {
    $data = getData();

    $occDate  = apiField($input, 'occurrence_date');
    $postal   = apiField($input, 'postal_code');

    if (!empty($occDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $occDate))
        errorResponse('発生日は YYYY-MM-DD 形式で入力してください', 400);
    if (!empty($postal) && !preg_match('/^\d{3}-?\d{4}$/', $postal))
        errorResponse('郵便番号は XXX-XXXX または XXXXXXX 形式で入力してください', 400);

    $txType   = apiField($input, 'transaction_type');
    $abbrevs  = ['レンタル' => '【レ】', '販売' => '【売】', '保守' => '【保】'];
    $abbrev   = $abbrevs[$txType] ?? '';
    $siteName = apiField($input, 'name') ?: apiField($input, 'site_name');
    $chatId   = apiField($input, 'chat_space_id');
    // 採番・重複チェックは削除済み（ソフト削除）を除外したアクティブ案件のみ対象
    $activeProjects = filterDeleted($data['projects']);
    $pjNum    = apiGetConfirmedPjNumber($activeProjects, apiField($input, 'custom_pj_number'));

    // 同じP番号のソフト削除済みレコードがあれば物理削除（ID重複防止）
    $data['projects'] = array_values(array_filter($data['projects'], function ($p) use ($pjNum) {
        return !((($p['id'] ?? '') === $pjNum) && !empty($p['deleted_at']));
    }));

    $newProject = [
        'id'               => $pjNum,
        'name'             => $siteName,
        'occurrence_date'  => $occDate,
        'transaction_type' => $txType,
        'sales_assignee'   => apiField($input, 'sales_assignee'),
        'customer_name'    => apiField($input, 'customer_name'),
        'dealer_name'      => apiField($input, 'dealer_name'),
        'general_contractor' => apiField($input, 'general_contractor'),
        'postal_code'      => $postal,
        'prefecture'       => apiField($input, 'prefecture'),
        'address'          => apiField($input, 'address'),
        'shipping_address' => apiField($input, 'shipping_address'),
        'maker'            => apiField($input, 'maker'),
        'product_category' => apiField($input, 'product_category'),
        'product_series'   => apiField($input, 'product_series'),
        'product_name'     => apiField($input, 'product_name'),
        'product_spec'     => apiField($input, 'product_spec'),
        'status'           => apiField($input, 'status') ?: '案件発生',
        'memo'             => apiField($input, 'memo'),
        'chat_space_id'    => $chatId,
        'pending_chat_space' => empty($chatId) ? "{$pjNum}{$abbrev}{$siteName}" : '',
        'created_at'       => date('Y-m-d H:i:s'),
    ];

    $data['projects'][] = $newProject;
    saveData($data);
    writeAuditLog('create', 'project', 'プロジェクト作成: ' . $pjNum);
    successResponse(['project' => $newProject], 'プロジェクト' . $pjNum . ' を作成しました');
}

// Update action
if ($action === 'update') {
    $updateId = trim($input['id'] ?? $_POST['update_pj'] ?? '');
    if (empty($updateId)) errorResponse('プロジェクト ID が指定されていません', 400);

    $occDate = apiField($input, 'occurrence_date');
    $postal  = apiField($input, 'postal_code');

    if (!empty($occDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $occDate))
        errorResponse('発生日は YYYY-MM-DD 形式で入力してください', 400);
    if (!empty($postal) && !preg_match('/^\d{3}-?\d{4}$/', $postal))
        errorResponse('郵便番号は XXX-XXXX または XXXXXXX 形式で入力してください', 400);

    $data  = getData();
    $found = false;

    foreach ($data['projects'] as &$pj) {
        if (($pj['id'] ?? '') !== $updateId) continue;
        $found = true;

        $pj['occurrence_date']    = $occDate;
        $pj['transaction_type']   = apiField($input, 'transaction_type')   ?: ($pj['transaction_type']   ?? '');
        $pj['sales_assignee']     = apiField($input, 'sales_assignee')     ?: ($pj['sales_assignee']     ?? '');
        $pj['customer_name']      = apiField($input, 'customer_name')      ?: ($pj['customer_name']      ?? '');
        $pj['dealer_name']        = apiField($input, 'dealer_name')        ?: ($pj['dealer_name']        ?? '');
        $pj['general_contractor'] = apiField($input, 'general_contractor') ?: ($pj['general_contractor'] ?? '');

        $newName = apiField($input, 'name') ?: apiField($input, 'site_name');
        if ($newName !== '') $pj['name'] = $newName;

        $pj['postal_code']        = $postal;
        $pj['prefecture']         = apiField($input, 'prefecture')         ?: ($pj['prefecture']         ?? '');
        $pj['address']            = apiField($input, 'address')            ?: ($pj['address']            ?? '');
        $pj['shipping_address']   = apiField($input, 'shipping_address')   ?: ($pj['shipping_address']   ?? '');
        $pj['maker']              = apiField($input, 'maker')              ?: ($pj['maker']              ?? '');
        $pj['product_category']   = apiField($input, 'product_category')   ?: ($pj['product_category']   ?? '');
        $pj['product_series']     = apiField($input, 'product_series')     ?: ($pj['product_series']     ?? '');
        $pj['product_name']       = apiField($input, 'product_name')       ?: ($pj['product_name']       ?? '');
        $pj['product_spec']       = apiField($input, 'product_spec')       ?: ($pj['product_spec']       ?? '');

        if (array_key_exists('status', $input)) $pj['status'] = trim($input['status']);
        if (array_key_exists('memo',   $input)) $pj['memo']   = trim($input['memo']);

        // Google Chat space handling
        $chatSpaceId  = apiField($input, 'chat_space_id',      'edit_chat_space_id');
        $pendingSpace = apiField($input, 'pending_chat_space', 'edit_pending_chat_space');

        if ($pendingSpace === '__auto__') {
            $sn = $pj['name'] ?? '';
            $pendingSpace = $updateId . ($sn ? ' ' . $sn : '');
        }
        if ($chatSpaceId === '__unlink__') {
            $pj['chat_space_id'] = ''; $pj['pending_chat_space'] = '';
        } elseif (!empty($chatSpaceId) && $chatSpaceId !== ($pj['chat_space_id'] ?? '')) {
            $pj['chat_space_id'] = $chatSpaceId; $pj['pending_chat_space'] = '';
        } elseif (!empty($pendingSpace) && empty($pj['chat_space_id'])) {
            $pj['pending_chat_space'] = $pendingSpace;
        }

        $pj['updated_at'] = date('Y-m-d H:i:s');
        break;
    }
    unset($pj);

    if (!$found) errorResponse('プロジェクト' . $updateId . ' が見つかりません', 404);

    saveData($data);
    writeAuditLog('update', 'project', 'プロジェクト更新: ' . $updateId);

    $updated = null;
    foreach ($data['projects'] as $pj) {
        if (($pj['id'] ?? '') === $updateId) { $updated = $pj; break; }
    }
    successResponse(['project' => $updated], 'プロジェクト' . $updateId . ' を更新しました');
}

// Unknown action
errorResponse('不明なアクション: ' . $action, 400);