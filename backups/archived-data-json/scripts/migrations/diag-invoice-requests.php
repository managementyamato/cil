<?php
/**
 * 診断: invoice_requests がどこに保存されているか確認
 */
require_once __DIR__ . '/../../config/config.php';

if (empty($_SESSION['user_email'])) { http_response_code(401); echo json_encode(['error' => 'login required']); exit; }
if (!isAdmin()) { http_response_code(403); echo json_encode(['error' => 'admin only']); exit; }

header('Content-Type: application/json; charset=utf-8');

try {
    $result = [];

    // 1. DB_MODE
    $result['db_mode'] = class_exists('Database') ? Database::getMode() : 'unknown';

    // 2. getData() の結果
    $data = getData();
    $result['getData_invoice_requests_count'] = count($data['invoice_requests'] ?? []);
    $result['getData_first_3_ids'] = array_slice(array_column($data['invoice_requests'] ?? [], 'id'), 0, 3);

    // 3. MySQL を直接見る
    try {
        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT COUNT(*) FROM invoice_requests");
        $result['mysql_count'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT id, source, source_row_id, pj_number, subject FROM invoice_requests LIMIT 3");
        $result['mysql_first_3'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $result['mysql_error'] = $e->getMessage();
    }

    // 4. data.json を直接見る
    if (file_exists(DATA_FILE)) {
        $jsonData = json_decode(file_get_contents(DATA_FILE), true);
        $result['json_invoice_requests_count'] = count($jsonData['invoice_requests'] ?? []);
        $result['json_first_3_ids'] = array_slice(array_column($jsonData['invoice_requests'] ?? [], 'id'), 0, 3);
    } else {
        $result['json_file_exists'] = false;
    }

    // 5. テーブル定義
    try {
        $pdo = Database::connect();
        $stmt = $pdo->query("SHOW CREATE TABLE invoice_requests");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['mysql_table_def'] = $row['Create Table'] ?? null;
    } catch (Exception $e) {
        $result['mysql_table_def_error'] = $e->getMessage();
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
