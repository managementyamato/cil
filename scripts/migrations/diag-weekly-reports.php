<?php
/**
 * weekly_reports の容量診断
 *
 * 各レコードのコンテンツサイズを確認し、max_allowed_packet 超過候補を特定
 * 実行URL: /scripts/migrations/diag-weekly-reports.php （admin のみ）
 */
require_once __DIR__ . '/../../config/config.php';

if (empty($_SESSION['user_email'])) { http_response_code(401); echo json_encode(['error' => 'login required']); exit; }
if (!isAdmin()) { http_response_code(403); echo json_encode(['error' => 'admin only']); exit; }

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connect();

    // max_allowed_packet 確認
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
    $maxPacket = $stmt->fetch(PDO::FETCH_ASSOC);

    // wait_timeout 確認
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'wait_timeout'");
    $waitTimeout = $stmt->fetch(PDO::FETCH_ASSOC);

    // weekly_reports 各行のサイズ集計
    $stmt = $pdo->query("
        SELECT id, user_name, week_start, week_end,
               LENGTH(content) AS content_len,
               LENGTH(achievements) AS achievements_len,
               LENGTH(issues) AS issues_len,
               LENGTH(next_week_plan) AS plan_len,
               LENGTH(memo) AS memo_len,
               LENGTH(private_recipients) AS private_recip_len,
               (COALESCE(LENGTH(content),0) + COALESCE(LENGTH(achievements),0) + COALESCE(LENGTH(issues),0)
                + COALESCE(LENGTH(next_week_plan),0) + COALESCE(LENGTH(memo),0) + COALESCE(LENGTH(private_recipients),0)) AS total_len
        FROM weekly_reports
        ORDER BY total_len DESC
        LIMIT 20
    ");
    $bigRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // テーブル全体サイズ
    $stmt = $pdo->query("
        SELECT COUNT(*) AS row_count,
               SUM(COALESCE(LENGTH(content),0) + COALESCE(LENGTH(achievements),0) + COALESCE(LENGTH(issues),0)
                  + COALESCE(LENGTH(next_week_plan),0) + COALESCE(LENGTH(memo),0) + COALESCE(LENGTH(private_recipients),0)) AS total_bytes,
               AVG(COALESCE(LENGTH(content),0) + COALESCE(LENGTH(achievements),0) + COALESCE(LENGTH(issues),0)
                  + COALESCE(LENGTH(next_week_plan),0) + COALESCE(LENGTH(memo),0) + COALESCE(LENGTH(private_recipients),0)) AS avg_bytes,
               MAX(COALESCE(LENGTH(content),0) + COALESCE(LENGTH(achievements),0) + COALESCE(LENGTH(issues),0)
                  + COALESCE(LENGTH(next_week_plan),0) + COALESCE(LENGTH(memo),0) + COALESCE(LENGTH(private_recipients),0)) AS max_bytes
        FROM weekly_reports
    ");
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // base64 埋め込み画像が含まれているレコード検出
    $stmt = $pdo->query("
        SELECT id, user_name, week_start,
               (LENGTH(content) - LENGTH(REPLACE(content, 'data:image', ''))) / LENGTH('data:image') AS embedded_image_count,
               LENGTH(content) AS content_len
        FROM weekly_reports
        WHERE content LIKE '%data:image%'
        ORDER BY content_len DESC
        LIMIT 10
    ");
    $imageEmbedded = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'max_allowed_packet' => $maxPacket,
        'wait_timeout' => $waitTimeout,
        'summary' => $summary,
        'biggest_rows_top20' => $bigRows,
        'rows_with_embedded_images' => $imageEmbedded,
        'note' => 'total_bytes が max_allowed_packet を超えそうな行があれば原因。data:image 埋め込みが大量にあれば肥大化要因。',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
