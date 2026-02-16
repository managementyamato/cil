<?php
/**
 * PHPUnit Bootstrap
 * テスト実行前の初期化処理
 */

// テスト環境フラグ
define('TESTING', true);

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// Composer autoloader
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// テスト用環境変数を先に設定（config.phpの.env読み込みより前）
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';

// セッションを開始（config.phpのsession_start()を安全にスキップするため）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];

// テスト用データディレクトリ
define('TEST_DATA_DIR', __DIR__ . '/fixtures');

// fixturesディレクトリがなければ作成
if (!is_dir(TEST_DATA_DIR)) {
    mkdir(TEST_DATA_DIR, 0755, true);
}

// config.phpを読み込み（権限関数、データ関数、CSRF関数を使えるようにする）
require_once __DIR__ . '/../config/config.php';

// テスト用ヘルパー関数
function createTestSession($email = 'test@example.com', $role = 'admin', $name = 'テストユーザー') {
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = $role;
    $_SESSION['user_name'] = $name;
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function clearTestSession() {
    $_SESSION = [];
}

function getTestDataPath($filename) {
    return TEST_DATA_DIR . '/' . $filename;
}
