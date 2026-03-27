<?php
/**
 * メンテナンスモード管理API
 * GET  → 現在の状態を返す
 * POST → メンテナンスモードを切り替え（admin のみ）
 */
require_once '../config/config.php';
require_once '../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'allowedMethods' => ['GET', 'POST'],
]);

// 管理部のみ
if (!isAdmin()) {
    errorResponse('管理者権限が必要です', 403);
}

$maintenanceFile = __DIR__ . '/../config/maintenance.json';

$defaultMaintenance = [
    'enabled'    => false,
    'message'    => 'システムメンテナンス中です。しばらくお待ちください。',
    'end_time'   => null,
    'updated_by' => null,
    'updated_at' => null,
];

function loadMaintenance(string $file, array $default): array {
    if (!file_exists($file)) return $default;
    $data = @json_decode(@file_get_contents($file), true);
    return is_array($data) ? array_merge($default, $data) : $default;
}

// GET: 現在の状態を返す
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    successResponse(loadMaintenance($maintenanceFile, $defaultMaintenance));
}

// POST: 更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
    $maintenance = loadMaintenance($maintenanceFile, $defaultMaintenance);

    $wasEnabled = $maintenance['enabled'];

    if (array_key_exists('enabled', $input)) {
        $maintenance['enabled'] = (bool)$input['enabled'];
    }
    if (array_key_exists('message', $input)) {
        $msg = sanitizeInput($input['message'], 'string');
        $maintenance['message'] = mb_substr($msg, 0, 200);
    }
    if (array_key_exists('end_time', $input)) {
        $maintenance['end_time'] = $input['end_time'] ? sanitizeInput($input['end_time'], 'string') : null;
    }

    $maintenance['updated_by'] = $_SESSION['user_email'];
    $maintenance['updated_at'] = date('Y-m-d H:i:s');

    $ok = @file_put_contents(
        $maintenanceFile,
        json_encode($maintenance, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    if ($ok === false) {
        errorResponse('設定ファイルの書き込みに失敗しました', 500);
    }

    // 監査ログ
    $action = $maintenance['enabled'] ? 'メンテナンスモード有効化' : 'メンテナンスモード無効化';
    writeAuditLog('maintenance_mode', 'system', $action, $maintenance);

    $msg = $maintenance['enabled'] ? 'メンテナンスモードを有効化しました' : 'メンテナンスモードを無効化しました';
    successResponse($maintenance, $msg);
}
