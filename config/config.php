<?php
// 設定ファイル

// タイムゾーンを日本時間に設定
date_default_timezone_set('Asia/Tokyo');

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

// 初期データ
function getInitialData() {
    return array(
        'projects' => array(),
        'assignees' => array(),
        'troubles' => array(),
        'customers' => array(),
        'partners' => array(),
        'employees' => array(),
        'productCategories' => array(),
        'settings' => array(
            'spreadsheet_url' => ''
        )
    );
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
                // 設定が存在しない場合は初期化
                if (!isset($data['settings'])) {
                    $data['settings'] = array(
                        'spreadsheet_url' => ''
                    );
                }
                // 新しいマスタが存在しない場合は初期化
                if (!isset($data['customers'])) $data['customers'] = array();
                if (!isset($data['partners'])) $data['partners'] = array();
                if (!isset($data['employees'])) $data['employees'] = array();
                if (!isset($data['productCategories'])) $data['productCategories'] = array();
                return $data;
            }
        } else {
            fclose($fp);
        }
    }
    return getInitialData();
}

// データ保存（排他ロック + アトミック書き込み）
function saveData($data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $tmpFile = DATA_FILE . '.tmp.' . getmypid();

    // 一時ファイルに書き込み
    if (file_put_contents($tmpFile, $json) === false) {
        @unlink($tmpFile);
        throw new Exception('データの書き込みに失敗しました');
    }

    // 排他ロックでリネーム
    $fp = fopen(DATA_FILE, 'c');
    if ($fp && flock($fp, LOCK_EX)) {
        // tmpファイルを本体にリネーム（アトミック操作）
        if (!rename($tmpFile, DATA_FILE)) {
            // renameが失敗した場合（Windows等）はコピー+削除
            copy($tmpFile, DATA_FILE);
            @unlink($tmpFile);
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    } else {
        @unlink($tmpFile);
        if ($fp) fclose($fp);
        throw new Exception('データファイルのロックに失敗しました');
    }
}

// 操作ログ機能を読み込み
require_once dirname(__DIR__) . '/functions/audit-log.php';

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    // セッションセキュリティ設定
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// CSRF保護関数
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
