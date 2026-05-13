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

    // テーブルのカラム情報を取得
    $stmt = $pdo->query("SHOW COLUMNS FROM weekly_reports");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 動的に LENGTH() を組み立て（TEXT/BLOB/VARCHAR系カラムのみ）
    $sizeable = [];
    foreach ($columns as $col) {
        $t = strtolower($col['Type'] ?? '');
        if (strpos($t, 'text') !== false || strpos($t, 'blob') !== false
            || strpos($t, 'varchar') !== false || strpos($t, 'char') !== false
            || strpos($t, 'json') !== false) {
            $sizeable[] = $col['Field'];
        }
    }

    if (empty($sizeable)) {
        echo json_encode(['error' => 'サイズ計測可能なカラムが見つかりません', 'columns' => $columns]);
        exit;
    }

    // 各行のテキスト系カラム合計サイズ
    $sumExpr = implode(' + ', array_map(fn($c) => "COALESCE(LENGTH(`{$c}`), 0)", $sizeable));
    $colSelect = implode(', ', array_map(fn($c) => "LENGTH(`{$c}`) AS len_{$c}", $sizeable));

    // テーブル全体サマリ
    $stmt = $pdo->query("
        SELECT COUNT(*) AS row_count,
               SUM({$sumExpr}) AS total_bytes,
               AVG({$sumExpr}) AS avg_bytes,
               MAX({$sumExpr}) AS max_bytes
        FROM weekly_reports
    ");
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // 上位20件（容量が大きい順）
    $stmt = $pdo->query("
        SELECT id, {$colSelect},
               ({$sumExpr}) AS total_len
        FROM weekly_reports
        ORDER BY total_len DESC
        LIMIT 20
    ");
    $bigRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // base64 埋め込み画像を含むレコード検出（テキストカラムをすべて検索）
    $imageDetections = [];
    foreach ($sizeable as $col) {
        $sql = "
            SELECT id, ({$sumExpr}) AS total_len,
                   (LENGTH(`{$col}`) - LENGTH(REPLACE(`{$col}`, 'data:image', ''))) / LENGTH('data:image') AS embedded_count,
                   LENGTH(`{$col}`) AS col_len
            FROM weekly_reports
            WHERE `{$col}` LIKE '%data:image%'
            ORDER BY col_len DESC
            LIMIT 5
        ";
        try {
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $imageDetections[$col] = $rows;
            }
        } catch (Exception $e) {
            $imageDetections[$col . '_error'] = $e->getMessage();
        }
    }

    echo json_encode([
        'max_allowed_packet' => $maxPacket,
        'wait_timeout' => $waitTimeout,
        'sizeable_columns' => $sizeable,
        'summary' => $summary,
        'biggest_rows_top20' => $bigRows,
        'rows_with_embedded_images_per_column' => $imageDetections,
        'note' => 'max_bytes が max_allowed_packet (Value 列) に近い行があれば原因。data:image 埋め込みが大量にあれば肥大化要因。',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], JSON_UNESCAPED_UNICODE);
}
