<?php
/**
 * PJ番号の正規化API
 * 既存データの小文字pj_numberを大文字に統一
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';

// 管理者のみ
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '管理者権限が必要です']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

verifyCsrfToken();

try {
    $data = getData();

    if (!isset($data['troubles']) || empty($data['troubles'])) {
        echo json_encode(['success' => true, 'message' => 'トラブルデータがありません', 'updated' => 0]);
        exit;
    }

    $updatedCount = 0;

    foreach ($data['troubles'] as &$trouble) {
        $pj = $trouble['pj_number'] ?? '';

        // p1, p2 などの小文字パターンを大文字に変換
        if (preg_match('/^[Pp]\d+$/', $pj)) {
            $upperPj = strtoupper($pj);
            if ($pj !== $upperPj) {
                $trouble['pj_number'] = $upperPj;
                $updatedCount++;
            }
        }
    }
    unset($trouble);

    if ($updatedCount > 0) {
        saveData($data);
    }

    echo json_encode([
        'success' => true,
        'message' => "{$updatedCount}件のPJ番号を大文字に変換しました",
        'updated' => $updatedCount
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
