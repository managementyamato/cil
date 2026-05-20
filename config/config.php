<?php
// 設定ファイル

// openssl二重読み込み警告を抑制（php.iniで既に読み込まれているため）
error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED & ~E_STRICT);

// 環境変数ファイル読み込み（多段ロード）
// 読み込み順:
//   1. .env.local   （マシン固有の上書き・gitignore対象・本番には置かない）
//   2. .env         （共通デフォルト or 環境固有値）
//
// 仕様: 先に読まれた値が勝つ（後から読んでも getenv() に既存値があればスキップ）
// → ローカル開発では .env.local が優先される
// → 本番には .env.local を置かないので .env だけ読まれる
$envFiles = [
    dirname(__DIR__) . '/.env.local',
    dirname(__DIR__) . '/.env',
];
foreach ($envFiles as $envFile) {
    if (!file_exists($envFile)) continue;
    // UTF-8 で BOM があれば除去
    $raw = file_get_contents($envFile);
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
    }
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // クォート除去
        $value = trim($value, '"\'');
        // 先勝ち: 既にセット済みならスキップ
        if (getenv($key) === false || getenv($key) === '') {
            putenv("$key=$value");
            $_ENV[$key] = $value;
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

// 環境判定ヘルパー
//   APP_ENV: production | staging | local
function isProduction(): bool { return env('APP_ENV', 'production') === 'production'; }
function isStaging():    bool { return env('APP_ENV', 'production') === 'staging'; }
function isLocal():      bool {
    $e = env('APP_ENV', 'production');
    return $e === 'local' || $e === 'development' || $e === 'dev';
}
/** メール送信を抑止すべき環境かどうか（APP_ENV非本番 or MAIL_DISABLED=true） */
function isMailDisabled(): bool {
    if (env('MAIL_DISABLED', 'false') === true || env('MAIL_DISABLED', 'false') === 'true') return true;
    return !isProduction();
}

// タイムゾーンを日本時間に設定
date_default_timezone_set('Asia/Tokyo');

// アプリケーション設定
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', env('APP_DEBUG', false));
define('APP_VERSION', '1.0.0');

// 権限チェック関数
function hasPermission($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }

    // 権限レベル: 営業部 < 製品技術部 < 管理部
    $roleHierarchy = array('sales' => 1, 'product' => 2, 'admin' => 3);
    $userLevel = $roleHierarchy[$_SESSION['user_role']] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 999;

    return $userLevel >= $requiredLevel;
}

// 現在のユーザーが管理者かチェック
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// 現在のユーザーが編集可能かチェック（製品技術部以上）
function canEdit() {
    return hasPermission('product');
}

// 現在のユーザーが削除可能かチェック（管理部のみ）
function canDelete() {
    return isAdmin();
}

// データスキーマ定義を読み込み
require_once dirname(__DIR__) . '/functions/data-schema.php';

// DB 接続アダプター読み込み（クラス定義のみ、接続は遅延）
$dbFile = __DIR__ . '/database.php';
if (file_exists($dbFile)) {
    require_once $dbFile;
}

// 初期データ（スキーマから生成）
function getInitialData() {
    return DataSchema::getInitialData();
}

// データ読み込み（同一リクエスト内キャッシュ付き）
//
// DB_MODE=db 専用。DB 読み込みに失敗した場合は「空データ返却」だと
// 認証チェックが破壊的に失敗するため、必ず例外を投げて停止する。
function getData($forceReload = false) {
    static $cache = null;
    if ($cache !== null && !$forceReload) {
        return $cache;
    }

    if (!class_exists('Database')) {
        throw new Exception('Database クラスがロードされていません。config/database.php を確認してください。');
    }

    try {
        $cache = Database::getAllData();
        return $cache;
    } catch (Exception $e) {
        error_log('DB読み込みエラー: ' . $e->getMessage());
        throw new Exception(
            'データ取得失敗: MySQL から読み込めません。' .
            '原因: ' . $e->getMessage() .
            ' / 対処: .env の DB_HOST/DB_NAME/DB_USER/DB_PASS および MySQL サーバーの稼働状況を確認してください。'
        );
    }
}

/**
 * 単一行を保存する（同時編集衝突を防ぐ・推奨）
 *
 * 用途: 複数ユーザーが並行して別の行を編集するテーブル
 *       例: weekly_reports（各人が自分の週報を編集）、comments（各人がコメント追加）
 *
 * saveData() との違い:
 * - saveData: 全件 DELETE-INSERT → 他人の変更が消える危険あり
 * - saveEntityRow: 1行だけ INSERT...ON DUPLICATE KEY UPDATE → 他行に触れない
 *
 * @param string $entity テーブル名（例: 'weekly_reports'）
 * @param array  $row    保存する完全な行データ（id 必須）
 * @throws Exception 失敗時
 */
function saveEntityRow(string $entity, array $row): void {
    if (!class_exists('Database')) {
        throw new Exception('Database クラスがロードされていません。');
    }
    Database::saveEntityRow($entity, $row);
    // キャッシュ無効化（次回 getData で最新が読まれる）
    getData(true);
}

// データ保存（MySQL のみ）
//
// $entitiesFilter（任意）: 保存対象エンティティのホワイトリスト
//   例: ['discount_approvals'] → discount_approvals のみ DB に保存
//   未指定なら全エンティティを保存（従来動作）
//   weekly_reports など巨大データを含むテーブルへの不要な書き込みを避けて
//   "MySQL server has gone away" を回避するため
//
// ⚠️ 同時編集が発生するテーブル（weekly_reports等）では saveEntityRow() を使うこと。
//    saveData は全件 DELETE-INSERT のため他人の変更が消える危険がある。
function saveData($data, $entitiesFilter = null) {
    if (!class_exists('Database')) {
        throw new Exception('Database クラスがロードされていません。');
    }

    try {
        if (is_array($entitiesFilter) && count($entitiesFilter) > 0) {
            foreach ($entitiesFilter as $entity) {
                if (isset($data[$entity])) {
                    Database::saveEntity($entity, $data[$entity]);
                }
            }
        } else {
            Database::saveAllData($data);
        }
    } catch (Exception $e) {
        error_log('DB保存エラー: ' . $e->getMessage());
        throw $e;
    }

    // staticキャッシュをクリア（次の getData() で MySQL から再読み込み）
    getData(true);
}

// 操作ログ機能を読み込み
require_once dirname(__DIR__) . '/functions/audit-log.php';

// バリデーション機能を読み込み
require_once dirname(__DIR__) . '/functions/validation.php';

// 日付フォーマットヘルパーを読み込み
require_once dirname(__DIR__) . '/functions/date-helpers.php';

// ロガー機能を読み込み
require_once dirname(__DIR__) . '/functions/logger.php';

// 論理削除（ソフトデリート）機能を読み込み
require_once dirname(__DIR__) . '/functions/soft-delete.php';

// セキュリティ機能を読み込み
require_once dirname(__DIR__) . '/functions/security.php';

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

// セキュリティヘッダーを設定（セッション開始後に呼び出す）
setSecurityHeaders();

// CSRF保護関数
function generateCsrfToken() {
    // トークンはセッションごとに1つ（セッションと同じライフタイム）
    // ページ埋め込みのトークンと検証時のトークンが一致するよう、セッション内で固定
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
