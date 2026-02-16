<?php
// 設定ファイル

// openssl二重読み込み警告を抑制（php.iniで既に読み込まれているため）
error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED & ~E_STRICT);

// 環境変数ファイル読み込み
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // コメント行をスキップ
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // クォートを除去
            $value = trim($value, '"\'');
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// 環境変数取得ヘルパー
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    // 型変換
    $lower = strtolower($value);
    if ($lower === 'true' || $lower === '(true)') return true;
    if ($lower === 'false' || $lower === '(false)') return false;
    if ($lower === 'null' || $lower === '(null)') return null;
    if ($lower === 'empty' || $lower === '(empty)') return '';
    return $value;
}

// タイムゾーンを日本時間に設定
date_default_timezone_set('Asia/Tokyo');

// アプリケーション設定
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', env('APP_DEBUG', false));
define('APP_VERSION', '1.0.0');

// データファイルのパス
define('DATA_FILE', dirname(__DIR__) . '/data.json');

// 権限チェック関数
function hasPermission($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }

    // 権限レベル: 営業部 < 製品管理部 < 管理部
    $roleHierarchy = array('sales' => 1, 'product' => 2, 'admin' => 3);
    $userLevel = $roleHierarchy[$_SESSION['user_role']] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 999;

    return $userLevel >= $requiredLevel;
}

// 現在のユーザーが管理者かチェック
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// 現在のユーザーが編集可能かチェック（製品管理部以上）
function canEdit() {
    return hasPermission('product');
}

// 現在のユーザーが削除可能かチェック（管理部のみ）
function canDelete() {
    return isAdmin();
}

// データスキーマ定義を読み込み
require_once dirname(__DIR__) . '/functions/data-schema.php';

// 初期データ（スキーマから生成）
function getInitialData() {
    return DataSchema::getInitialData();
}

// データ読み込み（排他ロック付き）
function getData() {
    if (file_exists(DATA_FILE)) {
        $fp = fopen(DATA_FILE, 'r');
        if ($fp === false) {
            return getInitialData();
        }
        // 共有ロック（読み取り用）
        if (flock($fp, LOCK_SH)) {
            $json = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            $data = json_decode($json, true);
            if ($data) {
                // スキーマに基づいて不足キーを補完
                $data = DataSchema::ensureSchema($data);
                return $data;
            }
        } else {
            fclose($fp);
        }
    }
    return getInitialData();
}

// 保存前の自動スナップショット作成
function createAutoSnapshot() {
    if (!file_exists(DATA_FILE)) {
        return;
    }

    $snapshotDir = dirname(DATA_FILE) . '/snapshots';
    if (!is_dir($snapshotDir)) {
        @mkdir($snapshotDir, 0755, true);
    }

    // 最新スナップショットが5分以内なら作成しない（高頻度保存対策）
    $latestSnapshot = null;
    $files = @glob($snapshotDir . '/data_*.json');
    if ($files) {
        sort($files);
        $latestSnapshot = end($files);
    }

    if ($latestSnapshot) {
        $lastModified = filemtime($latestSnapshot);
        if ($lastModified && (time() - $lastModified) < 300) {
            return; // 5分以内は作成しない
        }
    }

    // スナップショット作成
    $timestamp = date('Ymd_His');
    $snapshotFile = $snapshotDir . '/data_' . $timestamp . '.json';
    @copy(DATA_FILE, $snapshotFile);

    // 最大50世代を超えたら古い順に削除
    $files = @glob($snapshotDir . '/data_*.json');
    if ($files && count($files) > 50) {
        sort($files);
        $deleteCount = count($files) - 50;
        for ($i = 0; $i < $deleteCount; $i++) {
            @unlink($files[$i]);
        }
    }
}

// データ保存（排他ロック + アトミック書き込み）
function saveData($data) {
    // 保存前にスナップショットを作成（万が一のデータ復旧用）
    createAutoSnapshot();
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // JSONエンコードに失敗した場合はエラー
    if ($json === false) {
        throw new Exception('データのJSONエンコードに失敗しました: ' . json_last_error_msg());
    }

    // JSONが空または極端に短い場合は異常とみなす
    if (strlen($json) < 100) {
        throw new Exception('データが不正です（サイズ異常）');
    }

    $tmpFile = DATA_FILE . '.tmp.' . getmypid();

    // 一時ファイルに書き込み
    $written = file_put_contents($tmpFile, $json, LOCK_EX);
    if ($written === false || $written !== strlen($json)) {
        @unlink($tmpFile);
        throw new Exception('データの書き込みに失敗しました');
    }

    // Windowsでは排他ロック中のファイル操作に制限があるため、
    // 一時ファイルの内容を直接本体ファイルに書き込む
    $fp = fopen(DATA_FILE, 'c');
    if ($fp && flock($fp, LOCK_EX)) {
        // ファイルサイズを0にしてから書き込み
        ftruncate($fp, 0);
        rewind($fp);
        $result = fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        @unlink($tmpFile);

        if ($result === false) {
            throw new Exception('データファイルへの書き込みに失敗しました');
        }
    } else {
        @unlink($tmpFile);
        if ($fp) fclose($fp);
        throw new Exception('データファイルのロックに失敗しました');
    }
}

// 操作ログ機能を読み込み
require_once dirname(__DIR__) . '/functions/audit-log.php';

// バリデーション機能を読み込み
require_once dirname(__DIR__) . '/functions/validation.php';

// ロガー機能を読み込み
require_once dirname(__DIR__) . '/functions/logger.php';

// 論理削除（ソフトデリート）機能を読み込み
require_once dirname(__DIR__) . '/functions/soft-delete.php';

// セキュリティ機能を読み込み
require_once dirname(__DIR__) . '/functions/security.php';

// セキュリティヘッダーを設定（HTML出力前に必須）
setSecurityHeaders();

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    // セッションセキュリティ設定
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    // 本番環境ではSecureフラグを強制（HTTPS前提）
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (defined('APP_ENV') && APP_ENV === 'production')) {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// CSRF保護関数
function generateCsrfToken() {
    // トークンが未生成、または有効期限切れ（1時間）の場合に再生成
    $tokenLifetime = 3600; // 1時間
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])
        || (time() - $_SESSION['csrf_token_time']) > $tokenLifetime) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function csrfTokenField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

function verifyCsrfToken() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token validation failed']);
        exit;
    }
}
