<?php
/**
 * Troubles page data API for Next.js frontend
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';
require_once __DIR__ . '/../../functions/soft-delete.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET']
]);

$data = getData();

$troubles = filterDeleted($data['troubles'] ?? []);

// Date format normalization (hyphen to slash)
foreach ($troubles as &$t) {
    if (!empty($t['date'])) {
        $t['date'] = str_replace('-', '/', $t['date']);
    }
}
unset($t);

// Sort: default by date desc
$sortBy = $_GET['sort'] ?? 'date';
$sortDir = $_GET['dir'] ?? 'desc';

usort($troubles, function($a, $b) use ($sortBy, $sortDir) {
    switch ($sortBy) {
        case 'responder':
            $cmp = strcmp($a['responder'] ?? '', $b['responder'] ?? '');
            break;
        case 'reporter':
            $cmp = strcmp($a['reporter'] ?? '', $b['reporter'] ?? '');
            break;
        case 'status':
            $order = ['未対応' => 0, '対応中' => 1, '保留' => 2, '完了' => 3];
            $cmp = ($order[$a['status'] ?? ''] ?? 99) - ($order[$b['status'] ?? ''] ?? 99);
            break;
        case 'pj_number':
            $cmp = strcmp($a['pj_number'] ?? $a['project_name'] ?? '', $b['pj_number'] ?? $b['project_name'] ?? '');
            break;
        case 'date':
        default:
            $cmp = strtotime($a['date'] ?? '1970-01-01') - strtotime($b['date'] ?? '1970-01-01');
            break;
    }
    return $sortDir === 'asc' ? $cmp : -$cmp;
});

// Filter
$filterStatus = $_GET['status'] ?? '';
$filterReporter = $_GET['reporter'] ?? '';
$filterResponder = $_GET['responder'] ?? '';
$filterPjNumber = $_GET['pj_number'] ?? '';
$searchKeyword = $_GET['search'] ?? '';

if (!empty($filterStatus)) {
    $troubles = array_values(array_filter($troubles, fn($t) => ($t['status'] ?? '') === $filterStatus));
}
if (!empty($filterReporter)) {
    $troubles = array_values(array_filter($troubles, fn($t) => ($t['reporter'] ?? '') === $filterReporter));
}
if (!empty($filterResponder)) {
    $troubles = array_values(array_filter($troubles, fn($t) => ($t['responder'] ?? '') === $filterResponder));
}
if (!empty($filterPjNumber)) {
    $troubles = array_values(array_filter($troubles, fn($t) => stripos($t['pj_number'] ?? $t['project_name'] ?? '', $filterPjNumber) !== false));
}
if (!empty($searchKeyword)) {
    $troubles = array_values(array_filter($troubles, function($t) use ($searchKeyword) {
        return stripos($t['trouble_content'] ?? '', $searchKeyword) !== false
            || stripos($t['response_content'] ?? '', $searchKeyword) !== false
            || stripos($t['project_name'] ?? '', $searchKeyword) !== false
            || stripos($t['pj_number'] ?? '', $searchKeyword) !== false
            || stripos($t['company_name'] ?? '', $searchKeyword) !== false;
    }));
}

// Statistics (from all non-deleted troubles, not filtered)
$allTroubles = filterDeleted($data['troubles'] ?? []);
$totalCount = count($allTroubles);
$pendingCount = count(array_filter($allTroubles, fn($t) => ($t['status'] ?? '') === '未対応'));
$inProgressCount = count(array_filter($allTroubles, fn($t) => ($t['status'] ?? '') === '対応中'));
$onHoldCount = count(array_filter($allTroubles, fn($t) => ($t['status'] ?? '') === '保留'));
$completedCount = count(array_filter($allTroubles, fn($t) => ($t['status'] ?? '') === '完了'));
$completionRate = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0;

// Responder breakdown
$ashimotoCount = count(array_filter($allTroubles, fn($t) => ($t['responder'] ?? '') === '足本'));
$sogabeCount = count(array_filter($allTroubles, fn($t) => ($t['responder'] ?? '') === '曽我部'));
$twoTotal = $ashimotoCount + $sogabeCount;
$sogabeRate = $twoTotal > 0 ? round(($sogabeCount / $twoTotal) * 100, 1) : 0;
$ashimotoRate = $twoTotal > 0 ? round(($ashimotoCount / $twoTotal) * 100, 1) : 0;

// Filter options: reporters and responders from data
$reporters = [];
$pjNumbers = [];
foreach ($allTroubles as $t) {
    if (!empty($t['reporter'])) $reporters[] = $t['reporter'];
    $pj = $t['pj_number'] ?? $t['project_name'] ?? '';
    if (!empty($pj)) $pjNumbers[] = $pj;
}
$reporters = array_values(array_unique($reporters));
sort($reporters);
$pjNumbers = array_values(array_unique($pjNumbers));
sort($pjNumbers);

// Responders from master
$troubleRespondersMaster = array_map(fn($r) => $r['name'], $data['troubleResponders'] ?? []);
sort($troubleRespondersMaster);

// Project IDs for validation
$projectIds = array_map(fn($p) => strtolower($p['id'] ?? ''), $data['projects'] ?? []);

// Select only needed fields for frontend
$troubleItems = array_map(function($t) {
    return [
        'id' => $t['id'] ?? '',
        'date' => $t['date'] ?? '',
        'deadline' => $t['deadline'] ?? '',
        'pj_number' => $t['pj_number'] ?? $t['project_name'] ?? '',
        'trouble_content' => $t['trouble_content'] ?? '',
        'response_content' => $t['response_content'] ?? '',
        'reporter' => $t['reporter'] ?? '',
        'responder' => $t['responder'] ?? '',
        'status' => $t['status'] ?? '',
        'case_no' => $t['case_no'] ?? '',
        'company_name' => $t['company_name'] ?? '',
        'customer_name' => $t['customer_name'] ?? '',
        'honorific' => $t['honorific'] ?? '様',
        'updated_at' => $t['updated_at'] ?? '',
    ];
}, $troubles);

// Permissions
$canEdit = canEdit();
$isAdminUser = isAdmin();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'troubles' => $troubleItems,
        'stats' => [
            'total' => $totalCount,
            'pending' => $pendingCount,
            'inProgress' => $inProgressCount,
            'onHold' => $onHoldCount,
            'completed' => $completedCount,
            'completionRate' => $completionRate,
            'ashimotoCount' => $ashimotoCount,
            'sogabeCount' => $sogabeCount,
            'twoTotal' => $twoTotal,
            'ashimotoRate' => $ashimotoRate,
            'sogabeRate' => $sogabeRate,
        ],
        'filters' => [
            'reporters' => $reporters,
            'responders' => $troubleRespondersMaster,
            'pjNumbers' => $pjNumbers,
            'statuses' => ['未対応', '対応中', '保留', '完了'],
        ],
        'projectIds' => $projectIds,
        'permissions' => [
            'canEdit' => $canEdit,
            'isAdmin' => $isAdminUser,
        ],
    ],
], JSON_UNESCAPED_UNICODE);
