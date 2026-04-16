<?php
/**
 * PJ番号の正規化API
 * 既存データの小文字pj_numberを大文字に統一
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'allowedMethods' => ['POST'],
]);

if (!isAdmin()) {
    errorResponse('管理者権限が必要です', 403);
}

try {
    $data = getData();

    if (!isset($data['troubles']) || empty($data['troubles'])) {
        successResponse(['updated' => 0], 'トラブルデータがありません');
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

    successResponse(['updated' => $updatedCount], "{$updatedCount}件のPJ番号を大文字に変換しました");
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}
