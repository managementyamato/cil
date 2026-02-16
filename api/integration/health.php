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

// データファイルの基本チェック
$dataFile = dirname(__DIR__, 2) . '/data.json';
if (!file_exists($dataFile) || !is_readable($dataFile)) {
    $healthy = false;
}

// データの整合性チェック（詳細は非公開）
if ($healthy) {
    $content = @file_get_contents($dataFile);
    if ($content === false || @json_decode($content, true) === null) {
        $healthy = false;
    }
}

// レスポンス構築（最小限の情報のみ — 内部構造を隠蔽）
$response = [
    'status' => $healthy ? 'healthy' : 'unhealthy',
    'timestamp' => date('c')
];

// HTTPステータスコード
http_response_code($healthy ? 200 : 503);

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
