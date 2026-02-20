<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';
require_once __DIR__ . '/../../functions/encryption.php';
require_once __DIR__ . '/../../functions/soft-delete.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET'],
]);

// マスタデータ（顧客・パートナー等）は編集権限以上に限定
if (!canEdit()) {
    errorResponse('権限がありません', 403);
}

$data = getData();
decryptCustomerData($data);

// Filter deleted items and sort
$customers = filterDeleted($data['customers'] ?? []);
usort($customers, function($a, $b) {
    return strcmp($a['companyName'] ?? '', $b['companyName'] ?? '');
});

$assignees = $data['assignees'] ?? [];
usort($assignees, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

$partners = filterDeleted($data['partners'] ?? []);
usort($partners, function($a, $b) {
    return strcmp($a['companyName'] ?? '', $b['companyName'] ?? '');
});

$categories = $data['productCategories'] ?? [];
usort($categories, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

$manufacturers = filterDeleted($data['manufacturers'] ?? []);
usort($manufacturers, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

$troubleResponders = $data['troubleResponders'] ?? [];
usort($troubleResponders, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

$prefectures = $data['prefectures'] ?? [];
usort($prefectures, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

$generalContractors = $data['generalContractors'] ?? [];
usort($generalContractors, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

$areas = $data['areas'] ?? [];
usort($areas, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

// Build manufacturer map for categories
$manufacturerMap = [];
foreach ($manufacturers as $m) {
    $manufacturerMap[$m['id']] = $m['name'];
}

// Resolve maker names for categories
$categoriesWithMakers = array_map(function($cat) use ($manufacturerMap) {
    $makerIds = $cat['maker_ids'] ?? (isset($cat['maker_id']) && $cat['maker_id'] ? [$cat['maker_id']] : []);
    $makerNames = array_values(array_filter(array_map(function($id) use ($manufacturerMap) {
        return $manufacturerMap[$id] ?? null;
    }, $makerIds)));
    $cat['maker_names'] = $makerNames;
    return $cat;
}, $categories);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'customers' => array_values($customers),
        'assignees' => array_values($assignees),
        'partners' => array_values($partners),
        'categories' => array_values($categoriesWithMakers),
        'manufacturers' => array_values($manufacturers),
        'troubleResponders' => array_values($troubleResponders),
        'prefectures' => array_values($prefectures),
        'generalContractors' => array_values($generalContractors),
        'areas' => array_values($areas),
        'counts' => [
            'customers' => count($customers),
            'assignees' => count($assignees),
            'partners' => count($partners),
            'categories' => count($categories),
            'manufacturers' => count($manufacturers),
            'troubleResponders' => count($troubleResponders),
            'prefectures' => count($prefectures),
            'generalContractors' => count($generalContractors),
            'areas' => count($areas),
        ],
    ],
    'permissions' => [
        'canEdit' => canEdit(),
        'canDelete' => canDelete(),
        'isAdmin' => isAdmin(),
    ],
], JSON_UNESCAPED_UNICODE);
