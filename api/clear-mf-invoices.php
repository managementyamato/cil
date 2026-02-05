<?php
/**
 * MF請求書データをクリアするAPI
 * 管理者専用
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// 認証チェック
if (!isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です']);
    exit;
}

// 管理者権限チェック
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '管理者権限が必要です']);
    exit;
}

// CSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POSTメソッドのみ許可されています']);
    exit;
}

try {
    $data = getData();

    // クリア前の件数を記録
    $beforeCount = count($data['mf_invoices'] ?? []);

    // mf_invoicesをクリア
    $data['mf_invoices'] = [];
    $data['mf_sync_timestamp'] = null;

    saveData($data);

    echo json_encode([
        'success' => true,
        'message' => "MF請求書データをクリアしました（{$beforeCount}件削除）",
        'deleted' => $beforeCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
