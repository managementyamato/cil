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

// データファイルのパス
define('DATA_FILE', dirname(__DIR__) . '/data.json');

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

// DB接続アダプター読み込み（DB_MODE が json 以外の場合のみ使用）
// ファイル自体は常に読み込む（クラス定義のみ、接続は遅延）
$dbFile = __DIR__ . '/database.php';
if (file_exists($dbFile)) {
    require_once $dbFile;
}

// 初期データ（スキーマから生成）
function getInitialData() {
    return DataSchema::getInitialData();
}

// データ読み込み（排他ロック付き・同一リクエスト内キャッシュ）
// DB_MODE=db|dual の場合は MySQL から読み込み
//
// ⚠️ 重要: DB が読めない場合の挙動
// 過去2回（2026-05-11, 2026-05-12）に「DBエラー → 空データfallback → 全権限消失」
// という cascading failure が発生したため、以下の挙動に変更:
//   1. DB成功 → DB データを返す
//   2. DB失敗 + data.json あり → data.json を返す（旧来の fallback）
//   3. DB失敗 + data.json なし → 例外をスロー（空データを返さない）
//
// 理由: 空データを返すと「全社員消失」「全権限消失」状態になり、
// 認証チェックが破壊的に失敗する。例外で停止する方が安全。
function getData($forceReload = false) {
    static $cache = null;
    if ($cache !== null && !$forceReload) {
        return $cache;
    }

    $dbError = null;

    // DB モード: MySQL から読み込み
    if (class_exists('Database') && Database::getMode() === 'db') {
        try {
            $cache = Database::getAllData();
            return $cache;
        } catch (Exception $e) {
            // DB失敗 → data.json fallback を試す
            $dbError = $e->getMessage();
            error_log('DB読み込みエラー: ' . $dbError);
        }
    }

    // JSON フォールバック（DB失敗時 or json モード時）
    if (file_exists(DATA_FILE)) {
        $fp = fopen(DATA_FILE, 'r');
        if ($fp !== false) {
            if (flock($fp, LOCK_SH)) {
                $json = stream_get_contents($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
                $data = json_decode($json, true);
                if ($data) {
                    if ($dbError) {
                        error_log('DB読み込みエラーのため data.json にフォールバック成功');
                    }
                    $data = DataSchema::ensureSchema($data);
                    $cache = $data;
                    return $cache;
                }
            } else {
                fclose($fp);
            }
        }
    }

    // DB も data.json も読めない → 「空データ返却」だと auth が破壊されるため例外
    if ($dbError) {
        // 本番 DB エラーで JSON フォールバックも無い → cascading failure 防止
        throw new Exception(
            'データ取得失敗: DB および data.json の両方が利用不可。' .
            '原因: ' . $dbError .
            ' / 対処: 本番 .env で DB_SAVE_MODE=full_replace を確認、それでもダメなら管理者へ連絡。'
        );
    }

    // 純粋な json モードで data.json が無い場合（初回起動など）→ 初期データ
    $cache = getInitialData();
    return $cache;
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
// DB_MODE=db の場合は MySQL のみ、dual の場合は両方に書き込み
//
// $entitiesFilter（任意）: 保存対象エンティティのホワイトリスト
//   例: ['discount_approvals'] → discount_approvals のみ DB に保存
//   未指定なら全エンティティを保存（従来動作）
//   weekly_reports など巨大データを含むテーブルへの不要な書き込みを避けて
//   "MySQL server has gone away" を回避するため
function saveData($data, $entitiesFilter = null) {
    $dbMode = class_exists('Database') ? Database::getMode() : 'json';

    // DB モード: MySQL に保存
    if ($dbMode === 'db' || $dbMode === 'dual') {
        try {
            if (is_array($entitiesFilter) && count($entitiesFilter) > 0) {
                // 指定エンティティのみ保存
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
            if ($dbMode === 'db') {
                throw $e;
            }
        }

        if ($dbMode === 'db') {
            getData(true);
            return;
        }
    }

    // JSON モード / dual モード: data.json に保存
    // dual モードの場合、データの整合性チェックを行ってからJSONに書き込む
    // （壊れたDBデータでdata.jsonを上書きする事故を防止）
    if ($dbMode === 'dual' && class_exists('DataSchema')) {
        $integrityOk = true;
        foreach (['employees', 'projects', 'customers'] as $entity) {
            if (!empty($data[$entity]) && is_array($data[$entity])) {
                $firstRow = $data[$entity][0];
                $requiredFields = DataSchema::getRequiredFields($entity);
                foreach ($requiredFields as $field) {
                    if (!array_key_exists($field, $firstRow) ||
                        ($field !== 'id' && ($firstRow[$field] === null || $firstRow[$field] === ''))) {
                        error_log("saveData整合性ガード: {$entity}.{$field} が空/欠落。JSON書き込みをスキップ");
                        $integrityOk = false;
                        break 2;
                    }
                }
            }
        }

        if (!$integrityOk) {
            error_log('CRITICAL: dual モードでデータ破損検出。data.json への書き込みを中止しました。');
            getData(true);
            return;
        }
    }

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

    // staticキャッシュをクリア（次のgetData()でディスクから再読み込み）
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
