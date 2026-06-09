<?php
/**
 * SSO 検証エンドポイント
 *
 * 外部システム（inventory.yamato-mgt.com 等）から呼び出され、
 * 指定メールアドレスのユーザーがログインを許可されているか判定する。
 *
 * 認証: X-SSO-API-Key ヘッダー
 *   - master 側 .env の INVENTORY_SSO_API_KEY と一致するもののみ許可
 *
 * リクエスト (POST JSON):
 *   {
 *     "email":       "user@example.com",  // 必須
 *     "google_sub":  "1234567890",        // 任意（将来用）
 *     "google_name": "山田太郎"            // 任意（name 不在時のフォールバック）
 *   }
 *
 * レスポンス:
 *   200 + { "allowed": true,  "user": {...} }
 *   200 + { "allowed": false, "reason": "not_registered" | "inactive" }
 *   400  入力不正
 *   401  APIキー不正
 *   500  サーバー内部エラー
 *
 * NOTE: ブラウザから直接呼ばれない（サーバー間通信）ため CORS 設定なし。
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/logger.php';
require_once __DIR__ . '/../functions/encryption.php';

header('Content-Type: application/json; charset=utf-8');

// ============================================================
// 1. メソッド検証
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// 2. APIキー検証（定数時間比較）
// ============================================================
$expectedKey = env('INVENTORY_SSO_API_KEY', '');
$providedKey = $_SERVER['HTTP_X_SSO_API_KEY'] ?? '';

if ($expectedKey === '' || $providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    logWarning('[SSO Verify] Invalid or missing API key', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// 3. 入力パース
// ============================================================
$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE);
    exit;
}

$email      = strtolower(trim($input['email'] ?? ''));
$googleSub  = trim($input['google_sub']  ?? '');
$googleName = trim($input['google_name'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// 4. employees から該当ユーザーを検索
// ============================================================
try {
    $employee = findActiveEmployeeByEmail($email);
} catch (Throwable $e) {
    logError('[SSO Verify] Failed to load employees', [
        'email' => $email,
        'error' => $e->getMessage(),
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// 5. 判定 & レスポンス
// ============================================================
if ($employee === null) {
    logInfo('[SSO Verify] User not registered', ['email' => $email]);
    echo json_encode([
        'allowed' => false,
        'reason'  => 'not_registered',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!empty($employee['deleted_at'])) {
    logInfo('[SSO Verify] User soft-deleted', ['email' => $email]);
    echo json_encode([
        'allowed' => false,
        'reason'  => 'inactive',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$today = date('Y-m-d');
if (!empty($employee['leave_date']) && $employee['leave_date'] <= $today) {
    logInfo('[SSO Verify] User already left', [
        'email'      => $email,
        'leave_date' => $employee['leave_date'],
    ]);
    echo json_encode([
        'allowed' => false,
        'reason'  => 'inactive',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 許可
logInfo('[SSO Verify] User allowed', [
    'email'       => $email,
    'employee_id' => $employee['id'] ?? null,
]);

echo json_encode([
    'allowed' => true,
    'user'    => [
        'employee_id' => $employee['id']         ?? null,
        'email'       => $email,
        'name'        => $employee['name']       ?? $googleName ?: $email,
        'department'  => $employee['department'] ?? null,
        'role'        => $employee['role']       ?? null,
    ],
], JSON_UNESCAPED_UNICODE);

// ============================================================
// ヘルパー関数
// ============================================================

/**
 * employees から指定メアドのアクティブユーザーを検索。
 * - 暗号化メアド（enc: プレフィックス）にも対応
 * - 復号失敗・型不正は安全にスキップ
 *
 * @return array|null  該当ユーザーの配列、見つからなければ null
 */
function findActiveEmployeeByEmail(string $email): ?array {
    $data = getData();
    if (!isset($data['employees']) || !is_array($data['employees'])) {
        return null;
    }

    foreach ($data['employees'] as $emp) {
        $empEmail = $emp['email'] ?? null;
        if (!is_string($empEmail) || $empEmail === '') {
            continue;
        }

        // 暗号化されている場合は復号
        if (str_starts_with($empEmail, 'enc:')) {
            try {
                $empEmail = decryptValue($empEmail);
            } catch (Throwable $e) {
                // 復号失敗は無視（auth.php と同じ挙動）
                error_log('[SSO Verify] email decrypt failed: ' . $e->getMessage());
                continue;
            }
        }

        if (strtolower($empEmail) === $email) {
            return $emp;
        }
    }

    return null;
}
