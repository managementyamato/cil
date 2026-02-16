#!/usr/bin/env php
<?php
/**
 * 暗号化キーバックアップスクリプト
 *
 * 使用方法:
 *   php scripts/backup-encryption-key.php --backup   # バックアップ作成
 *   php scripts/backup-encryption-key.php --verify   # バックアップ検証
 *   php scripts/backup-encryption-key.php --restore  # バックアップから復元
 */

require_once __DIR__ . '/../config/config.php';

define('KEY_FILE', __DIR__ . '/../config/encryption.key');
define('BACKUP_DIR', __DIR__ . '/../data/key-backups');
define('MAX_BACKUPS', 10); // 最大保持世代数

/**
 * バックアップディレクトリを作成
 */
function ensureBackupDir() {
    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0700, true);
    }
}

/**
 * 暗号化キーをバックアップ
 */
function backupKey() {
    if (!file_exists(KEY_FILE)) {
        echo "エラー: 暗号化キーファイルが存在しません: " . KEY_FILE . "\n";
        exit(1);
    }

    ensureBackupDir();

    $key = trim(file_get_contents(KEY_FILE));
    if (empty($key)) {
        echo "エラー: 暗号化キーが空です\n";
        exit(1);
    }

    $timestamp = date('YmdHis');
    $backupFile = BACKUP_DIR . "/encryption-key-{$timestamp}.backup";

    // キーをハッシュ値と共に保存（改竄検知用）
    $hash = hash('sha256', $key);
    $backupData = json_encode([
        'key' => $key,
        'hash' => $hash,
        'created_at' => date('Y-m-d H:i:s'),
        'hostname' => gethostname(),
        'php_version' => PHP_VERSION
    ], JSON_PRETTY_PRINT);

    if (file_put_contents($backupFile, $backupData, LOCK_EX) === false) {
        echo "エラー: バックアップファイルの書き込みに失敗しました\n";
        exit(1);
    }

    // ファイル権限を厳しく設定
    chmod($backupFile, 0600);

    echo "✅ 暗号化キーをバックアップしました: {$backupFile}\n";
    echo "   作成日時: " . date('Y-m-d H:i:s') . "\n";
    echo "   ハッシュ値: {$hash}\n";

    // 古いバックアップを削除
    cleanOldBackups();

    return true;
}

/**
 * バックアップを検証
 */
function verifyBackups() {
    if (!is_dir(BACKUP_DIR)) {
        echo "エラー: バックアップディレクトリが存在しません\n";
        exit(1);
    }

    $backups = glob(BACKUP_DIR . '/encryption-key-*.backup');
    if (empty($backups)) {
        echo "警告: バックアップファイルが見つかりません\n";
        exit(0);
    }

    echo "🔍 バックアップ検証中...\n\n";

    $valid = 0;
    $invalid = 0;

    foreach ($backups as $backupFile) {
        $filename = basename($backupFile);
        echo "検証中: {$filename}\n";

        $data = json_decode(file_get_contents($backupFile), true);
        if (!$data) {
            echo "  ❌ JSONパースエラー\n";
            $invalid++;
            continue;
        }

        if (!isset($data['key']) || !isset($data['hash'])) {
            echo "  ❌ 必須フィールドが不足\n";
            $invalid++;
            continue;
        }

        $expectedHash = hash('sha256', $data['key']);
        if ($expectedHash !== $data['hash']) {
            echo "  ❌ ハッシュ値が一致しません（改竄の可能性）\n";
            $invalid++;
            continue;
        }

        echo "  ✅ OK\n";
        echo "     作成日時: {$data['created_at']}\n";
        echo "     ホスト名: {$data['hostname']}\n";
        $valid++;
    }

    echo "\n" . str_repeat('=', 50) . "\n";
    echo "検証結果: 有効={$valid}, 無効={$invalid}\n";

    if ($invalid > 0) {
        exit(1);
    }

    return true;
}

/**
 * バックアップから復元
 */
function restoreKey() {
    if (!is_dir(BACKUP_DIR)) {
        echo "エラー: バックアップディレクトリが存在しません\n";
        exit(1);
    }

    $backups = glob(BACKUP_DIR . '/encryption-key-*.backup');
    if (empty($backups)) {
        echo "エラー: バックアップファイルが見つかりません\n";
        exit(1);
    }

    // 最新のバックアップを取得
    rsort($backups);

    echo "利用可能なバックアップ:\n\n";
    foreach ($backups as $i => $backupFile) {
        $data = json_decode(file_get_contents($backupFile), true);
        $num = $i + 1;
        echo "[{$num}] " . basename($backupFile) . "\n";
        echo "    作成日時: " . ($data['created_at'] ?? 'unknown') . "\n";
    }

    echo "\n復元するバックアップの番号を入力してください（0でキャンセル）: ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $choice = (int)trim($line);
    fclose($handle);

    if ($choice === 0) {
        echo "キャンセルしました\n";
        exit(0);
    }

    if ($choice < 1 || $choice > count($backups)) {
        echo "エラー: 無効な選択です\n";
        exit(1);
    }

    $selectedBackup = $backups[$choice - 1];
    $data = json_decode(file_get_contents($selectedBackup), true);

    if (!$data || !isset($data['key'])) {
        echo "エラー: バックアップデータが不正です\n";
        exit(1);
    }

    // ハッシュ検証
    $expectedHash = hash('sha256', $data['key']);
    if ($expectedHash !== $data['hash']) {
        echo "エラー: ハッシュ値が一致しません（改竄の可能性）\n";
        exit(1);
    }

    // 既存のキーをバックアップ
    if (file_exists(KEY_FILE)) {
        $backupTimestamp = date('YmdHis');
        $oldKeyBackup = KEY_FILE . ".before-restore-{$backupTimestamp}";
        copy(KEY_FILE, $oldKeyBackup);
        echo "既存のキーをバックアップしました: {$oldKeyBackup}\n";
    }

    // キーを復元
    if (file_put_contents(KEY_FILE, $data['key'], LOCK_EX) === false) {
        echo "エラー: キーファイルの書き込みに失敗しました\n";
        exit(1);
    }

    chmod(KEY_FILE, 0600);

    echo "✅ 暗号化キーを復元しました\n";
    echo "   バックアップ: " . basename($selectedBackup) . "\n";
    echo "   作成日時: {$data['created_at']}\n";

    return true;
}

/**
 * 古いバックアップを削除
 */
function cleanOldBackups() {
    $backups = glob(BACKUP_DIR . '/encryption-key-*.backup');
    if (count($backups) <= MAX_BACKUPS) {
        return;
    }

    // 古い順にソート
    sort($backups);

    // 削除対象
    $toDelete = array_slice($backups, 0, count($backups) - MAX_BACKUPS);

    foreach ($toDelete as $file) {
        unlink($file);
        echo "古いバックアップを削除: " . basename($file) . "\n";
    }
}

// メイン処理
$options = getopt('', ['backup', 'verify', 'restore', 'help']);

if (isset($options['help']) || count($options) === 0) {
    echo "使用方法:\n";
    echo "  php scripts/backup-encryption-key.php --backup   # バックアップ作成\n";
    echo "  php scripts/backup-encryption-key.php --verify   # バックアップ検証\n";
    echo "  php scripts/backup-encryption-key.php --restore  # バックアップから復元\n";
    exit(0);
}

if (isset($options['backup'])) {
    backupKey();
} elseif (isset($options['verify'])) {
    verifyBackups();
} elseif (isset($options['restore'])) {
    restoreKey();
}
