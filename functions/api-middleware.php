<?php
/**
 * API用ミドルウェア関数
 * API初期化、認証、CSRF検証、レート制限などを提供
 *
 * 注意: このファイルを使用する前に config/config.php を読み込んでください。
 * config.php にセッション開始やCSRF関連の関数が含まれています。
 */

// APIはすべてのエラーをJSONで返す（HTML出力を防止）
ini_set('display_errors', '0');
// 本番環境では致命的エラーのみ、開発環境では全エラーを記録
if (defined('APP_ENV') && APP_ENV !== 'production') {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// エラーハンドラを設定（PHPエラーをJSON形式で返す）
set_error_handler(function($severity, $message, $file, $line) {
    // Content-Typeが設定されていない場合は設定
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Internal server error'
    ];
    // デバッグ情報は開発環境のみ（本番ではファイルパス・行番号を非公開）
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $response['debug'] = [
            'message' => $message,
            'file' => basename($file),
            'line' => $line
        ];
    }
    // エラーは常にログに記録
    error_log("PHP Error [{$severity}]: {$message} in {$file} on line {$line}");
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
});

// 例外ハンドラを設定
set_exception_handler(function($e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Internal server error'
    ];
    // デバッグ情報は開発環境のみ（本番では例外の詳細を非公開）
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $response['debug'] = [
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ];
    }
    // 例外は常にログに記録
    error_log("Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
});

// config.php が既に読み込まれていることを前提とする
// セッションが開始されていない場合は開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * API初期化
 *
 * @param array $options オプション
 *   - requireAuth: 認証必須（デフォルト: true）
 *   - requireCsrf: CSRF検証（デフォルト: true）
 *   - rateLimit: レート制限（デフォルト: 60）
 *   - allowedMethods: 許可するHTTPメソッド（デフォルト: ['GET', 'POST']）
 */
function initApi($options = []) {
    $requireAuth = $options['requireAuth'] ?? true;
    $requireCsrf = $options['requireCsrf'] ?? true;
    $rateLimit = $options['rateLimit'] ?? 60;
    $allowedMethods = $options['allowedMethods'] ?? ['GET', 'POST'];

    // セキュリティヘッダー設定（CSP不要だが X-Frame-Options は送信）
    setSecurityHeaders(['csp' => false]);

    // Content-Type設定（setSecurityHeaders後に設定してヘッダー上書きを防ぐ）
    header('Content-Type: application/json; charset=utf-8');

    // HTTPメソッドチェック
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
        errorResponse('Method not allowed', 405);
    }

    // 認証チェック（user_emailで判定 - auth.phpと同じ）
    if ($requireAuth && !isset($_SESSION['user_email'])) {
        errorResponse('認証が必要です', 401);
    }

    // CSRF検証（POST/PUT/DELETE時）
    if ($requireCsrf && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verifyCsrfTokenValue($token)) {
            errorResponse('CSRFトークンが無効です', 403);
        }
    }
}

/**
 * CSRFトークンを検証（値のみ）
 */
function verifyCsrfTokenValue($token) {
    if (empty($token) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * JSONリクエストボディを取得
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return [];
    }
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

/**
 * 必須パラメータをチェック
 */
function requireParams($data, $params) {
    foreach ($params as $param) {
        if (!isset($data[$param]) || $data[$param] === '') {
            errorResponse("パラメータ {$param} は必須です", 400);
        }
    }
}

/**
 * 入力をサニタイズ
 */
function sanitizeInput($value, $type = 'string') {
    switch ($type) {
        case 'int':
            return (int)$value;
        case 'float':
            return (float)$value;
        case 'bool':
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        case 'email':
            return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
        case 'string':
        default:
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * 成功レスポンス
 */
function successResponse($data, $message = null) {
    $response = [
        'success' => true,
        'data' => $data
    ];
    if ($message !== null) {
        $response['message'] = $message;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * エラーレスポンス
 */
function errorResponse($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * バリデーションエラーレスポンス
 */
if (!function_exists('respondValidationError')) {
    function respondValidationError($validator) {
        errorResponse([
            'type' => 'validation',
            'errors' => $validator->getErrors()
        ], 422);
    }
}
