<?php
/**
 * MySQL バックアップスクリプト
 *
 * DB_MODE=db 環境用。本番MySQLの全テーブルを SQL ダンプして
 * /backups/mysql/ に gzip 圧縮して保存。
 *
 * 使用例（XServer cron）:
 *   0 3 * * * /usr/bin/php /home/yamato-mgt/yamato-mgt.com/public_html/scripts/backup-mysql.php
 *
 * ローカル実行:
 *   php scripts/backup-mysql.php
 *   php scripts/backup-mysql.php --list
 *   php scripts/backup-mysql.php --keep=60
 */

if (php_sapi_name() !== 'cli') {
    die('CLI only.');
}

require_once dirname(__DIR__) . '/config/config.php';

$baseDir   = dirname(__DIR__);
$backupDir = $baseDir . '/backups/mysql';
$retentionDays = 30;

$options = getopt('', ['list', 'keep:', 'help']);

if (isset($options['help'])) {
    echo "Usage: php scripts/backup-mysql.php [--list] [--keep=N]\n";
    echo "  --list   既存バックアップ一覧表示\n";
    echo "  --keep=N 保持日数（デフォルト30）\n";
    exit(0);
}
if (isset($options['keep']) && ctype_digit((string)$options['keep'])) {
    $retentionDays = (int)$options['keep'];
}

if (!is_dir($backupDir)) {
    if (!@mkdir($backupDir, 0755, true)) {
        fwrite(STDERR, "ERROR: バックアップディレクトリ作成失敗: $backupDir\n");
        exit(1);
    }
}

if (isset($options['list'])) {
    $files = glob($backupDir . '/dump_*.sql.gz') ?: [];
    sort($files);
    if (!$files) { echo "（バックアップなし）\n"; exit(0); }
    foreach ($files as $f) {
        printf("%s  %s\n", date('Y-m-d H:i:s', filemtime($f)), formatBytes(filesize($f)) . "  " . basename($f));
    }
    exit(0);
}

// --- DB接続 ---
$host = env('DB_HOST', 'localhost');
$port = env('DB_PORT', '3306');
$name = env('DB_NAME');
$user = env('DB_USER');
$pass = env('DB_PASS');

if (!$name || !$user) {
    fwrite(STDERR, "ERROR: DB_NAME / DB_USER が .env に未設定\n");
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: DB接続失敗: " . $e->getMessage() . "\n");
    exit(1);
}

// --- 全テーブル取得 ---
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
if (!$tables) {
    fwrite(STDERR, "ERROR: テーブルが見つかりません\n");
    exit(1);
}

echo "[backup-mysql] " . count($tables) . " テーブルをダンプ中...\n";

$timestamp = date('Ymd_His');
$dumpFile  = "$backupDir/dump_{$timestamp}.sql";
$gzFile    = $dumpFile . '.gz';

$fp = fopen($dumpFile, 'w');
if (!$fp) {
    fwrite(STDERR, "ERROR: 出力ファイルを開けません: $dumpFile\n");
    exit(1);
}

fwrite($fp, "-- MySQL Dump\n");
fwrite($fp, "-- Generated: " . date('c') . "\n");
fwrite($fp, "-- Database:  $name\n");
fwrite($fp, "-- Host:      $host\n\n");
fwrite($fp, "SET NAMES utf8mb4;\n");
fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

foreach ($tables as $table) {
    fwrite($fp, "\n-- ----- Table: $table -----\n");
    fwrite($fp, "DROP TABLE IF EXISTS `$table`;\n");

    $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
    $createSql = $create['Create Table'] ?? $create['Create View'] ?? null;
    if (!$createSql) {
        echo "  skip: $table (no CREATE statement)\n";
        continue;
    }
    fwrite($fp, $createSql . ";\n\n");

    $stmt = $pdo->query("SELECT * FROM `$table`");
    $count = 0;
    while ($row = $stmt->fetch()) {
        $cols = array_map(fn($c) => "`$c`", array_keys($row));
        $vals = array_map(function ($v) use ($pdo) {
            if ($v === null) return 'NULL';
            if (is_int($v) || is_float($v)) return $v;
            return $pdo->quote((string)$v);
        }, array_values($row));
        fwrite($fp, "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
        $count++;
    }
    echo "  $table: $count rows\n";
}

fwrite($fp, "\nSET FOREIGN_KEY_CHECKS=1;\n");
fclose($fp);

// --- 圧縮 ---
$src = fopen($dumpFile, 'rb');
$dst = gzopen($gzFile, 'wb9');
if (!$src || !$dst) {
    fwrite(STDERR, "ERROR: gzip 圧縮失敗\n");
    exit(1);
}
while (!feof($src)) {
    gzwrite($dst, fread($src, 65536));
}
fclose($src);
gzclose($dst);
unlink($dumpFile);

$size = filesize($gzFile);
echo "[backup-mysql] 完了: " . basename($gzFile) . " (" . formatBytes($size) . ")\n";

// --- 古いバックアップを削除 ---
$cutoff = time() - ($retentionDays * 86400);
$deleted = 0;
foreach (glob($backupDir . '/dump_*.sql.gz') ?: [] as $old) {
    if (filemtime($old) < $cutoff) {
        if (unlink($old)) $deleted++;
    }
}
if ($deleted > 0) {
    echo "[backup-mysql] $retentionDays 日以前のバックアップ {$deleted}件を削除\n";
}

exit(0);

function formatBytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes/1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes/1048576, 1) . ' MB';
    return round($bytes/1073741824, 2) . ' GB';
}
