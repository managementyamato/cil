<?php
/**
 * クリーンアップ: document_summaries テーブルを削除
 * （資料要約機能の取り下げに伴うもの）
 *
 * 実行URL: /scripts/migrations/2026-05-11-drop-document-summaries.php
 */
require_once __DIR__ . '/../../config/config.php';

if (empty($_SESSION['user_email'])) { http_response_code(401); echo json_encode(['error' => 'login required']); exit; }
if (!isAdmin()) { http_response_code(403); echo json_encode(['error' => 'admin only']); exit; }

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connect();

    // 存在チェック
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'document_summaries' LIMIT 1");
    $stmt->execute();
    $exists = $stmt->fetch() !== false;

    $count = 0;
    if ($exists) {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM document_summaries")->fetchColumn();
        $pdo->exec("DROP TABLE document_summaries");
    }

    echo json_encode([
        'success' => true,
        'table_existed' => $exists,
        'records_deleted' => $count,
        'message' => $exists ? "document_summaries テーブルを削除しました（{$count}件の履歴も削除）" : 'テーブルは既に存在しません',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
