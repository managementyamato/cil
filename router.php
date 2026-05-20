<?php
/**
 * PHP組み込みWebサーバー用ルーター
 *
 * 全レスポンスにセキュリティヘッダーを付与する。
 * 静的ファイルは通常どおり配信し、PHPファイルはそのまま実行する。
 *
 * 使用方法:
 *   php -S localhost:8000 router.php
 */

// セキュリティヘッダーを全レスポンスに付与
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// 静的ファイルが存在する場合はPHPに配信させる（falseを返すと組み込みサーバーが処理）
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    // PHPファイル以外の静的ファイル（画像・CSS・JSなど）
    if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
        return false; // 組み込みサーバーにデフォルト処理させる
    }
    // PHPファイルはそのまま実行
    return false;
}

// .htaccess 相当: 拡張子なしのURLが .php ファイルに対応する場合は内部リライト
// 例: /pages/troubles → pages/troubles.php
// 同名のディレクトリ (例: pages/sales-tools/) と .php ファイル両方存在しても .php を優先
if ($uri !== '/' && file_exists($file . '.php')) {
    $isFile = file_exists($file) && !is_dir($file);
    if (!$isFile) {
        $target = $file . '.php';
        $_SERVER['SCRIPT_NAME'] = $uri . '.php';
        $_SERVER['SCRIPT_FILENAME'] = $target;
        $_SERVER['PHP_SELF'] = $uri . '.php';
        chdir(dirname($target));
        require $target;
        return true;
    }
}

// ファイルが存在しない場合（404）もヘッダーは付与済み
// デフォルトのルーティングに任せる
return false;
