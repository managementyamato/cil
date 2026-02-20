<?php
/**
 * Search page data API for Next.js frontend
 * Provides initial data needed for the search page
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET']
]);

// The search page primarily uses the existing /api/search.php endpoint
// for actual search. This API provides permission info and export availability.

$canEdit = canEdit();
$isAdminUser = isAdmin();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'permissions' => [
            'canEdit' => $canEdit,
            'isAdmin' => $isAdminUser,
        ],
        'exportEntities' => [
            ['key' => 'projects', 'label' => '案件データ', 'color' => 'primary'],
            ['key' => 'troubles', 'label' => 'トラブルデータ', 'color' => 'danger'],
            ['key' => 'customers', 'label' => '顧客データ', 'color' => 'success'],
            ['key' => 'employees', 'label' => '従業員データ', 'color' => 'warning'],
        ],
    ],
], JSON_UNESCAPED_UNICODE);
