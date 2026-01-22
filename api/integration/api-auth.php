<?php
/**
 * API連携用認証基盤
 * 外部システムからのAPI呼び出しを認証する
 */

require_once dirname(__DIR__) . '/../config/config.php';

// 連携設定ファイルパス
define('INTEGRATION_CONFIG_FILE', dirname(__DIR__) . '/../config/integration-config.json');
define('INTEGRATION_LOG_FILE', dirname(__DIR__) . '/../data/integration-log.json');

/**
 * 連携設定を取得
 */
function getIntegrationConfig() {
    if (file_exists(INTEGRATION_CONFIG_FILE)) {
        $json = file_get_contents(INTEGRATION_CONFIG_FILE);
        $config = json_decode($json, true);
        if ($config) {
            return $config;
        }
    }
    return array(
        'enabled' => false,
        'api_keys' => array(),
        'allowed_ips' => array(),
        'log_enabled' => true
    );
}

/**
 * 連携設定を保存
 */
function saveIntegrationConfig($config) {
    return file_put_contents(
        INTEGRATION_CONFIG_FILE,
        json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

/**
 * APIキーを生成
 */
function generateApiKey() {
    return bin2hex(random_bytes(32));
}

/**
 * APIキーを検証
 */
function validateApiKey($apiKey) {
    $config = getIntegrationConfig();

    if (!$config['enabled']) {
        return array('valid' => false, 'error' => 'API連携が無効です');
    }

    foreach ($config['api_keys'] as $key) {
        if ($key['key'] === $apiKey && $key['active']) {
            return array('valid' => true, 'key_info' => $key);
        }
    }

    return array('valid' => false, 'error' => '無効なAPIキーです');
}

/**
 * IPアドレスを検証
 */
function validateIpAddress($ip) {
    $config = getIntegrationConfig();

    // 許可IPリストが空の場合は全て許可
    if (empty($config['allowed_ips'])) {
        return true;
    }

    return in_array($ip, $config['allowed_ips']);
}

/**
 * API認証を実行
 */
function authenticateApiRequest() {
    // APIキーをヘッダーから取得
    $apiKey = null;
    $headers = getallheaders();

    if (isset($headers['X-Api-Key'])) {
        $apiKey = $headers['X-Api-Key'];
    } elseif (isset($headers['x-api-key'])) {
        $apiKey = $headers['x-api-key'];
    } elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
        $apiKey = $_SERVER['HTTP_X_API_KEY'];
    }

    if (!$apiKey) {
        return array('success' => false, 'error' => 'APIキーが指定されていません', 'code' => 401);
    }

    // IPアドレスチェック
    $clientIp = $_SERVER['REMOTE_ADDR'];
    if (!validateIpAddress($clientIp)) {
        logApiRequest('auth_failed', null, array('reason' => 'IP not allowed', 'ip' => $clientIp));
        return array('success' => false, 'error' => '許可されていないIPアドレスです', 'code' => 403);
    }

    // APIキー検証
    $validation = validateApiKey($apiKey);
    if (!$validation['valid']) {
        logApiRequest('auth_failed', null, array('reason' => $validation['error']));
        return array('success' => false, 'error' => $validation['error'], 'code' => 401);
    }

    return array('success' => true, 'key_info' => $validation['key_info']);
}

/**
 * APIリクエストをログに記録
 */
function logApiRequest($action, $keyName, $details = array()) {
    $config = getIntegrationConfig();

    if (!$config['log_enabled']) {
        return;
    }

    $log = array();
    if (file_exists(INTEGRATION_LOG_FILE)) {
        $json = file_get_contents(INTEGRATION_LOG_FILE);
        $log = json_decode($json, true) ?: array();
    }

    $logEntry = array(
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'key_name' => $keyName,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'details' => $details
    );

    array_unshift($log, $logEntry);

    // ログは最大1000件まで保持
    $log = array_slice($log, 0, 1000);

    file_put_contents(
        INTEGRATION_LOG_FILE,
        json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

/**
 * APIログを取得
 */
function getApiLogs($limit = 100) {
    if (!file_exists(INTEGRATION_LOG_FILE)) {
        return array();
    }

    $json = file_get_contents(INTEGRATION_LOG_FILE);
    $log = json_decode($json, true) ?: array();

    return array_slice($log, 0, $limit);
}

/**
 * JSONレスポンスを送信
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * エラーレスポンスを送信
 */
function sendErrorResponse($message, $statusCode = 400) {
    sendJsonResponse(array(
        'success' => false,
        'error' => $message
    ), $statusCode);
}

/**
 * 成功レスポンスを送信
 */
function sendSuccessResponse($data = array(), $message = '成功') {
    sendJsonResponse(array(
        'success' => true,
        'message' => $message,
        'data' => $data
    ), 200);
}
