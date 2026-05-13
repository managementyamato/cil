<?php
/**
 * 【緊急】 employees / users 等のテーブル状態を確認
 *
 * 通常ログインができない状態用、URLパラメータ ?key=yamato-emergency-12345 で認証バイパス
 * 利用後すぐ削除すること！
 */
require_once __DIR__ . '/../../config/config.php';

// 緊急バイパス（admin認証不要）
$secretKey = 'yamato-emergency-12345';
if (($_GET['key'] ?? '') !== $secretKey) {
    http_response_code(403);
    echo 'forbidden';
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connect();
    $out = ['db_mode' => Database::getMode()];

    // 各重要テーブルの行数
    $tables = ['employees', 'projects', 'customers', 'partners', 'discount_approvals',
               'weekly_reports', 'mf_invoices', 'manufacturers', 'troubles',
               'invoice_requests', 'leads', 'deals'];
    foreach ($tables as $t) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$t`");
            $count = (int)$stmt->fetchColumn();
            $out['counts'][$t] = $count;
        } catch (Exception $e) {
            $out['counts'][$t] = 'ERROR: ' . $e->getMessage();
        }
    }

    // employees の中身（最初の20件、メールのみ表示）
    try {
        $stmt = $pdo->query("SELECT id, name, email, role, deleted_at FROM employees ORDER BY id LIMIT 20");
        $out['employees_sample'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $out['employees_error'] = $e->getMessage();
    }

    // users.json の状態
    $usersFile = dirname(__DIR__) . '/../config/users.json';
    if (file_exists($usersFile)) {
        $users = json_decode(file_get_contents($usersFile), true);
        $out['users_json'] = [
            'count' => is_array($users) ? count($users) : 0,
            'emails' => is_array($users) ? array_keys($users) : [],
        ];
    } else {
        $out['users_json'] = 'file not found';
    }

    // snapshots/ の最新ファイル一覧
    $snapDir = dirname(__DIR__) . '/../snapshots';
    if (is_dir($snapDir)) {
        $files = glob($snapDir . '/data_*.json') ?: [];
        rsort($files);
        $out['snapshots'] = array_slice(array_map('basename', $files), 0, 5);
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
