<?php
/**
 * ヘルスチェックAPI
 * システムの稼働状態を確認するためのエンドポイント
 *
 * GET: システム状態を返す
 *
 * 認証不要（監視ツール等から呼び出し可能）
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'");
header_remove('X-Powered-By');

// OPTIONSリクエスト
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// GETのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// セキュリティ: 内部構造を公開しない最小限のチェックのみ
$healthy = true;

// DB 接続チェック（MySQL に到達できるか）
try {
    require_once dirname(__DIR__, 2) . '/config/config.php';
    if (!class_exists('Database')) {
        $healthy = false;
    } else {
        $pdo = Database::connect();
        $stmt = $pdo->query('SELECT 1');
        if (!$stmt || (int)$stmt->fetchColumn() !== 1) {
            $healthy = false;
        }
    }
} catch (Throwable $e) {
    $healthy = false;
}

// レスポンス構築（最小限の情報のみ — 内部構造を隠蔽）
$response = [
    'status' => $healthy ? 'healthy' : 'unhealthy',
    'timestamp' => date('c')
];

// HTTPステータスコード
http_response_code($healthy ? 200 : 503);

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
