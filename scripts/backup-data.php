<?php
/**
 * 自動バックアップスクリプト
 * cronで定期実行することでデータの自動バックアップを行う
 *
 * 使用方法（Xserverのcron設定）:
 *   0 3 * * * /usr/bin/php /home/yamato-mgt/yamato-mgt.com/public_html/scripts/backup-data.php
 *
 * ローカル実行:
 *   php scripts/backup-data.php
 */

// 設定
$baseDir = dirname(__DIR__);
$backupBaseDir = $baseDir . '/backups/auto';
$maxBackups = 30; // 最大保持数

// バックアップ対象ファイル
$targetFiles = [
    'data.json',
    'config/users.json',
    'config/google-config.json',
    'config/mf-config.json',
    'config/photo-attendance-data.json',
    'config/integration-config.json',
    'config/notification-config.json',
    'data/audit-log.json',
    'data/loans.json',
];

// バックアップディレクトリ作成
$timestamp = date('Ymd_His');
$backupDir = $backupBaseDir . '/' . $timestamp;

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$results = [];
$totalSize = 0;

foreach ($targetFiles as $file) {
    $srcPath = $baseDir . '/' . $file;
    if (file_exists($srcPath)) {
        $destDir = $backupDir . '/' . dirname($file);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destPath = $backupDir . '/' . $file;
        if (copy($srcPath, $destPath)) {
            $size = filesize($srcPath);
            $totalSize += $size;
            $results[] = "[OK] $file (" . round($size / 1024, 1) . " KB)";
        } else {
            $results[] = "[NG] $file (コピー失敗)";
        }
    } else {
        $results[] = "[--] $file (存在しない)";
    }
}

// 古いバックアップを削除
$dirs = glob($backupBaseDir . '/*', GLOB_ONLYDIR);
if ($dirs && count($dirs) > $maxBackups) {
    // 日付順にソート（古い順）
    sort($dirs);
    $toDelete = array_slice($dirs, 0, count($dirs) - $maxBackups);
    foreach ($toDelete as $dir) {
        // ディレクトリ内のファイルを再帰的に削除
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
        $results[] = "[削除] 古いバックアップ: " . basename($dir);
    }
}

// 結果出力
$summary = "=== バックアップ完了 ===\n";
$summary .= "日時: " . date('Y-m-d H:i:s') . "\n";
$summary .= "保存先: $backupDir\n";
$summary .= "合計サイズ: " . round($totalSize / 1024 / 1024, 2) . " MB\n";
$summary .= "---\n";
$summary .= implode("\n", $results) . "\n";

echo $summary;

// ログファイルに記録
$logFile = $backupBaseDir . '/backup.log';
file_put_contents($logFile, $summary . "\n", FILE_APPEND | LOCK_EX);
