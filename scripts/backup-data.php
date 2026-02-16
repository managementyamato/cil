<?php
/**
 * 自動バックアップスクリプト（強化版）
 *
 * データファイルとコンフィグファイルを自動バックアップ
 * cronまたはタスクスケジューラから定期実行
 *
 * 使用例（Xserverのcron設定）:
 *   0 3 * * * /usr/bin/php /home/yamato-mgt/yamato-mgt.com/public_html/scripts/backup-data.php
 *
 * ローカル実行:
 *   php scripts/backup-data.php
 *   php scripts/backup-data.php --verify
 *   php scripts/backup-data.php --restore=backup_20260130_120000.zip
 *   php scripts/backup-data.php --list
 */

// CLI実行のみ許可
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// 設定
$baseDir = dirname(__DIR__);
$backupDir = $baseDir . '/backups';
$retentionDays = 30; // バックアップ保持日数
$maxBackups = 30;    // 最大保持数

// バックアップ対象ファイル
$targetFiles = [
    'data.json',
    'config/google-config.json',
    'config/mf-config.json',
    'config/integration-config.json',
    'config/notification-config.json',
    'config/page-permissions.json',
    'config/security-config.json',
    'data/audit-log.json',
    'data/background-jobs.json',
];

// 引数解析
$options = getopt('', ['verify', 'restore:', 'list', 'help', 'zip']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

if (isset($options['list'])) {
    listBackups();
    exit(0);
}

if (isset($options['verify'])) {
    verifyBackup();
    exit(0);
}

if (isset($options['restore'])) {
    restoreBackup($options['restore']);
    exit(0);
}

// デフォルト: バックアップ実行
if (isset($options['zip'])) {
    runZipBackup();
} else {
    runBackup();
}

/**
 * ヘルプ表示
 */
function showHelp() {
    echo <<<HELP
YA Management System バックアップスクリプト

使用方法:
  php scripts/backup-data.php [オプション]

オプション:
  --help              このヘルプを表示
  --list              バックアップ一覧を表示
  --verify            最新のバックアップを検証
  --zip               ZIP形式でバックアップ
  --restore=ファイル名  指定したバックアップからリストア

例:
  php scripts/backup-data.php                     # フォルダ形式でバックアップ
  php scripts/backup-data.php --zip               # ZIP形式でバックアップ
  php scripts/backup-data.php --list              # 一覧表示
  php scripts/backup-data.php --verify            # 検証
  php scripts/backup-data.php --restore=20260130_120000

HELP;
}

/**
 * 通常バックアップ実行（フォルダ形式）
 */
function runBackup() {
    global $baseDir, $backupDir, $targetFiles, $maxBackups;

    echo "=== YA Management System バックアップ ===\n";
    echo "開始時刻: " . date('Y-m-d H:i:s') . "\n\n";

    // バックアップディレクトリ作成
    $timestamp = date('Ymd_His');
    $destDir = $backupDir . '/auto/' . $timestamp;

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $results = [];
    $totalSize = 0;
    $backedUp = 0;

    foreach ($targetFiles as $file) {
        $srcPath = $baseDir . '/' . $file;
        if (file_exists($srcPath)) {
            $destPath = $destDir . '/' . $file;
            $destFileDir = dirname($destPath);
            if (!is_dir($destFileDir)) {
                mkdir($destFileDir, 0755, true);
            }
            if (copy($srcPath, $destPath)) {
                $size = filesize($srcPath);
                $totalSize += $size;
                $results[] = "[OK] $file (" . formatBytes($size) . ")";
                $backedUp++;
            } else {
                $results[] = "[NG] $file (コピー失敗)";
            }
        } else {
            $results[] = "[--] $file (存在しない)";
        }
    }

    // メタデータを保存
    $metadata = [
        'created_at' => date('c'),
        'files_count' => $backedUp,
        'total_size' => $totalSize,
        'php_version' => PHP_VERSION,
        'hostname' => gethostname(),
    ];
    file_put_contents($destDir . '/_backup_metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo implode("\n", $results) . "\n\n";
    echo "保存先: $destDir\n";
    echo "合計サイズ: " . formatBytes($totalSize) . "\n";
    echo "ファイル数: $backedUp\n";

    // 古いバックアップを削除
    cleanOldBackups();

    // ログに記録
    $logFile = $backupDir . '/backup.log';
    $logEntry = "[" . date('Y-m-d H:i:s') . "] バックアップ完了: $timestamp ($backedUp files, " . formatBytes($totalSize) . ")\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    echo "\n終了時刻: " . date('Y-m-d H:i:s') . "\n";
}

/**
 * ZIP形式でバックアップ
 */
function runZipBackup() {
    global $baseDir, $backupDir, $targetFiles, $retentionDays;

    echo "=== YA Management System バックアップ (ZIP) ===\n";
    echo "開始時刻: " . date('Y-m-d H:i:s') . "\n\n";

    // バックアップディレクトリ作成
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    // バックアップファイル名
    $timestamp = date('Ymd_His');
    $backupFile = $backupDir . '/backup_' . $timestamp . '.zip';

    // ZIPアーカイブ作成
    $zip = new ZipArchive();
    if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo "エラー: ZIPファイルを作成できませんでした\n";
        exit(1);
    }

    $backedUp = 0;
    $skipped = 0;

    foreach ($targetFiles as $file) {
        $fullPath = $baseDir . '/' . $file;
        if (file_exists($fullPath)) {
            $zip->addFile($fullPath, $file);
            $size = filesize($fullPath);
            echo "  + $file (" . formatBytes($size) . ")\n";
            $backedUp++;
        } else {
            echo "  - $file (存在しません)\n";
            $skipped++;
        }
    }

    // メタデータを追加
    $metadata = [
        'created_at' => date('c'),
        'files_count' => $backedUp,
        'php_version' => PHP_VERSION,
        'hostname' => gethostname(),
    ];
    $zip->addFromString('_backup_metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $zip->close();

    $backupSize = filesize($backupFile);
    echo "\n完了: $backupFile\n";
    echo "サイズ: " . formatBytes($backupSize) . "\n";
    echo "ファイル数: $backedUp (スキップ: $skipped)\n";

    // 古いZIPバックアップを削除
    $files = glob($backupDir . '/backup_*.zip');
    $cutoff = time() - ($retentionDays * 24 * 60 * 60);
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
            echo "古いバックアップを削除: " . basename($file) . "\n";
        }
    }

    // ログに記録
    $logFile = $backupDir . '/backup.log';
    $logEntry = "[" . date('Y-m-d H:i:s') . "] ZIPバックアップ完了: " . basename($backupFile) . " ($backedUp files, " . formatBytes($backupSize) . ")\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    echo "\n終了時刻: " . date('Y-m-d H:i:s') . "\n";
}

/**
 * バックアップ一覧表示
 */
function listBackups() {
    global $backupDir;

    echo "=== バックアップ一覧 ===\n\n";

    // フォルダ形式
    $autoDir = $backupDir . '/auto';
    if (is_dir($autoDir)) {
        $dirs = glob($autoDir . '/*', GLOB_ONLYDIR);
        if (!empty($dirs)) {
            echo "【フォルダ形式】\n";
            usort($dirs, function($a, $b) { return filemtime($b) - filemtime($a); });
            foreach ($dirs as $dir) {
                $name = basename($dir);
                $date = date('Y-m-d H:i:s', filemtime($dir));
                echo sprintf("  %-20s %s\n", $name, $date);
            }
            echo "\n";
        }
    }

    // ZIP形式
    $zipFiles = glob($backupDir . '/backup_*.zip');
    if (!empty($zipFiles)) {
        echo "【ZIP形式】\n";
        usort($zipFiles, function($a, $b) { return filemtime($b) - filemtime($a); });
        foreach ($zipFiles as $file) {
            $name = basename($file);
            $size = formatBytes(filesize($file));
            $date = date('Y-m-d H:i:s', filemtime($file));
            echo sprintf("  %-35s %10s %s\n", $name, $size, $date);
        }
        echo "\n";
    }

    if (empty($dirs) && empty($zipFiles)) {
        echo "バックアップはありません\n";
    }
}

/**
 * バックアップ検証
 */
function verifyBackup() {
    global $backupDir;

    echo "=== バックアップ検証 ===\n\n";

    // 最新のバックアップを検索
    $latestDir = null;
    $latestZip = null;

    $autoDir = $backupDir . '/auto';
    if (is_dir($autoDir)) {
        $dirs = glob($autoDir . '/*', GLOB_ONLYDIR);
        if (!empty($dirs)) {
            usort($dirs, function($a, $b) { return filemtime($b) - filemtime($a); });
            $latestDir = $dirs[0];
        }
    }

    $zipFiles = glob($backupDir . '/backup_*.zip');
    if (!empty($zipFiles)) {
        usort($zipFiles, function($a, $b) { return filemtime($b) - filemtime($a); });
        $latestZip = $zipFiles[0];
    }

    if (!$latestDir && !$latestZip) {
        echo "バックアップが見つかりません\n";
        exit(1);
    }

    // より新しい方を検証
    if ($latestDir && (!$latestZip || filemtime($latestDir) > filemtime($latestZip))) {
        echo "検証対象（フォルダ）: " . basename($latestDir) . "\n\n";
        $valid = true;
        $files = glob($latestDir . '/*.json') + glob($latestDir . '/*/*.json');
        foreach ($files as $file) {
            $name = str_replace($latestDir . '/', '', $file);
            $content = file_get_contents($file);
            $decoded = json_decode($content, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                echo "  [NG] $name - 無効なJSON\n";
                $valid = false;
            } else {
                echo "  [OK] $name\n";
            }
        }
        echo "\n検証結果: " . ($valid ? "OK" : "失敗") . "\n";
    } else {
        echo "検証対象（ZIP）: " . basename($latestZip) . "\n\n";
        $zip = new ZipArchive();
        if ($zip->open($latestZip) !== true) {
            echo "エラー: ZIPファイルを開けません\n";
            exit(1);
        }
        $valid = true;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (pathinfo($name, PATHINFO_EXTENSION) === 'json') {
                $content = $zip->getFromIndex($i);
                $decoded = json_decode($content, true);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    echo "  [NG] $name - 無効なJSON\n";
                    $valid = false;
                } else {
                    echo "  [OK] $name\n";
                }
            }
        }
        $zip->close();
        echo "\n検証結果: " . ($valid ? "OK" : "失敗") . "\n";
    }
}

/**
 * バックアップからリストア
 */
function restoreBackup($backupName) {
    global $baseDir, $backupDir;

    echo "=== バックアップからリストア ===\n";
    echo "対象: $backupName\n\n";

    // バックアップを探す
    $sourcePath = null;
    $isZip = false;

    // フォルダ形式をチェック
    $folderPath = $backupDir . '/auto/' . $backupName;
    if (is_dir($folderPath)) {
        $sourcePath = $folderPath;
    }

    // ZIP形式をチェック
    $zipPath = $backupDir . '/' . $backupName;
    if (!$sourcePath && file_exists($zipPath) && pathinfo($zipPath, PATHINFO_EXTENSION) === 'zip') {
        $sourcePath = $zipPath;
        $isZip = true;
    }

    // .zip拡張子なしでもチェック
    if (!$sourcePath) {
        $zipPath = $backupDir . '/backup_' . $backupName . '.zip';
        if (file_exists($zipPath)) {
            $sourcePath = $zipPath;
            $isZip = true;
        }
    }

    if (!$sourcePath) {
        echo "エラー: バックアップが見つかりません: $backupName\n";
        exit(1);
    }

    // 確認
    echo "警告: 現在のデータが上書きされます。続行しますか？ (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    if (trim($line) !== 'yes') {
        echo "中止しました\n";
        exit(0);
    }

    // 現在のデータをバックアップ
    echo "\n現在のデータをバックアップ中...\n";
    $preRestoreDir = $backupDir . '/pre_restore_' . date('Ymd_His');
    mkdir($preRestoreDir, 0755, true);
    if (file_exists($baseDir . '/data.json')) {
        copy($baseDir . '/data.json', $preRestoreDir . '/data.json');
    }
    echo "保存先: $preRestoreDir\n";

    // リストア実行
    echo "\nリストア実行中...\n";
    $restored = 0;

    if ($isZip) {
        $zip = new ZipArchive();
        if ($zip->open($sourcePath) !== true) {
            echo "エラー: ZIPファイルを開けません\n";
            exit(1);
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === '_backup_metadata.json') continue;
            $targetPath = $baseDir . '/' . $name;
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $content = $zip->getFromIndex($i);
            if (file_put_contents($targetPath, $content) !== false) {
                echo "  + $name\n";
                $restored++;
            } else {
                echo "  エラー: $name\n";
            }
        }
        $zip->close();
    } else {
        $files = glob($sourcePath . '/*.json') + glob($sourcePath . '/*/*.json');
        foreach ($files as $file) {
            $relativePath = str_replace($sourcePath . '/', '', $file);
            if ($relativePath === '_backup_metadata.json') continue;
            $targetPath = $baseDir . '/' . $relativePath;
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            if (copy($file, $targetPath)) {
                echo "  + $relativePath\n";
                $restored++;
            } else {
                echo "  エラー: $relativePath\n";
            }
        }
    }

    echo "\n完了: $restored ファイルを復元しました\n";
}

/**
 * 古いバックアップを削除
 */
function cleanOldBackups() {
    global $backupDir, $maxBackups;

    $autoDir = $backupDir . '/auto';
    if (!is_dir($autoDir)) return;

    $dirs = glob($autoDir . '/*', GLOB_ONLYDIR);
    if (!$dirs || count($dirs) <= $maxBackups) return;

    echo "\n古いバックアップを削除中...\n";
    sort($dirs);
    $toDelete = array_slice($dirs, 0, count($dirs) - $maxBackups);

    foreach ($toDelete as $dir) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getRealPath());
            } else {
                unlink($fileinfo->getRealPath());
            }
        }
        rmdir($dir);
        echo "  削除: " . basename($dir) . "\n";
    }
}

/**
 * バイト数をフォーマット
 */
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
