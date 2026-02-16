<?php
/**
 * 操作ログ（監査ログ）機能
 * 誰がいつ何を変更したかを記録する
 */

define('AUDIT_LOG_FILE', dirname(__DIR__) . '/data/audit-log.json');
define('AUDIT_LOG_MAX_ENTRIES', 5000); // 最大保持件数

/**
 * 監査ログの署名鍵を取得
 * @return string
 */
function getAuditLogSigningKey() {
    // 環境変数から取得、なければ暗号化キーを再利用
    $key = getenv('AUDIT_LOG_SIGNING_KEY');
    if (!$key) {
        $key = getenv('ENCRYPTION_KEY');
    }
    if (!$key) {
        $keyFile = dirname(__DIR__) . '/config/encryption.key';
        if (file_exists($keyFile)) {
            $key = trim(file_get_contents($keyFile));
        }
    }
    if (!$key) {
        throw new Exception('監査ログ署名キーが設定されていません');
    }
    return $key;
}

/**
 * 監査ログエントリに署名を付与
 * @param array $entry ログエントリ
 * @return array 署名付きエントリ
 */
function signAuditLogEntry($entry) {
    $key = getAuditLogSigningKey();

    // 署名対象データ（idから署名以外の全フィールド）
    $dataToSign = json_encode([
        'id' => $entry['id'],
        'timestamp' => $entry['timestamp'],
        'user_email' => $entry['user_email'],
        'user_name' => $entry['user_name'],
        'user_role' => $entry['user_role'],
        'action' => $entry['action'],
        'target' => $entry['target'],
        'description' => $entry['description'],
        'details' => $entry['details'],
        'ip' => $entry['ip']
    ], JSON_UNESCAPED_UNICODE);

    // HMAC-SHA256で署名
    $entry['signature'] = hash_hmac('sha256', $dataToSign, $key);

    return $entry;
}

/**
 * 監査ログエントリの署名を検証
 * @param array $entry ログエントリ
 * @return bool 署名が有効ならtrue
 */
function verifyAuditLogEntry($entry) {
    if (!isset($entry['signature'])) {
        return false; // 署名がない古いエントリ
    }

    $key = getAuditLogSigningKey();
    $signature = $entry['signature'];

    // 署名対象データを再構築
    $dataToSign = json_encode([
        'id' => $entry['id'],
        'timestamp' => $entry['timestamp'],
        'user_email' => $entry['user_email'],
        'user_name' => $entry['user_name'],
        'user_role' => $entry['user_role'],
        'action' => $entry['action'],
        'target' => $entry['target'],
        'description' => $entry['description'],
        'details' => $entry['details'],
        'ip' => $entry['ip']
    ], JSON_UNESCAPED_UNICODE);

    $expectedSignature = hash_hmac('sha256', $dataToSign, $key);

    // タイミング攻撃対策で hash_equals を使用
    return hash_equals($expectedSignature, $signature);
}

/**
 * 操作ログを記録
 * @param string $action アクション種別（create, update, delete, login, etc.）
 * @param string $target 対象（project, trouble, employee, settings, etc.）
 * @param string $description 操作の説明
 * @param array $details 詳細データ（変更前後の値など）
 */
function writeAuditLog($action, $target, $description, $details = []) {
    $logs = readAuditLogs();

    $entry = [
        'id' => uniqid('log_'),
        'timestamp' => date('Y-m-d H:i:s'),
        'user_email' => $_SESSION['user_email'] ?? 'system',
        'user_name' => $_SESSION['user_name'] ?? 'システム',
        'user_role' => $_SESSION['user_role'] ?? '',
        'action' => $action,
        'target' => $target,
        'description' => $description,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    // 署名を付与
    $entry = signAuditLogEntry($entry);

    // 先頭に追加（新しい順）
    array_unshift($logs, $entry);

    // 最大件数を超えたら古いものを削除
    if (count($logs) > AUDIT_LOG_MAX_ENTRIES) {
        $logs = array_slice($logs, 0, AUDIT_LOG_MAX_ENTRIES);
    }

    saveAuditLogs($logs);
}

/**
 * 操作ログを読み込み（共有ロック付き）
 * @return array
 */
function readAuditLogs() {
    if (!file_exists(AUDIT_LOG_FILE)) {
        return [];
    }
    $fp = fopen(AUDIT_LOG_FILE, 'r');
    if ($fp === false) {
        return [];
    }
    if (flock($fp, LOCK_SH)) {
        $json = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return json_decode($json, true) ?: [];
    }
    fclose($fp);
    return [];
}

/**
 * 操作ログを保存（排他ロック付き）
 */
function saveAuditLogs($logs) {
    $dir = dirname(AUDIT_LOG_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $json = json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $fp = fopen(AUDIT_LOG_FILE, 'c');
    if ($fp && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    } else {
        if ($fp) fclose($fp);
    }
}

/**
 * フィルター付きでログを取得
 * @param array $filters ['action' => ..., 'target' => ..., 'user' => ..., 'date_from' => ..., 'date_to' => ...]
 * @param int $page ページ番号（1始まり）
 * @param int $perPage 1ページあたりの件数
 * @return array ['logs' => [...], 'total' => int, 'page' => int, 'total_pages' => int]
 */
function getFilteredAuditLogs($filters = [], $page = 1, $perPage = 50) {
    $allLogs = readAuditLogs();

    // フィルタリング
    $filtered = array_filter($allLogs, function($log) use ($filters) {
        if (!empty($filters['action']) && $log['action'] !== $filters['action']) return false;
        if (!empty($filters['target']) && $log['target'] !== $filters['target']) return false;
        if (!empty($filters['user'])) {
            $search = mb_strtolower($filters['user']);
            $name = mb_strtolower($log['user_name'] ?? '');
            $email = mb_strtolower($log['user_email'] ?? '');
            if (strpos($name, $search) === false && strpos($email, $search) === false) return false;
        }
        if (!empty($filters['date_from'])) {
            $logDate = substr($log['timestamp'], 0, 10);
            if ($logDate < $filters['date_from']) return false;
        }
        if (!empty($filters['date_to'])) {
            $logDate = substr($log['timestamp'], 0, 10);
            if ($logDate > $filters['date_to']) return false;
        }
        return true;
    });

    $filtered = array_values($filtered);
    $total = count($filtered);
    $totalPages = max(1, ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    $logs = array_slice($filtered, $offset, $perPage);

    return [
        'logs' => $logs,
        'total' => $total,
        'page' => $page,
        'total_pages' => $totalPages
    ];
}

/**
 * 監査ログ全体の整合性を検証
 * @return array ['valid' => bool, 'total' => int, 'verified' => int, 'failed' => int, 'unsigned' => int, 'tampered_ids' => array]
 */
function verifyAuditLogIntegrity() {
    $logs = readAuditLogs();
    $total = count($logs);
    $verified = 0;
    $failed = 0;
    $unsigned = 0;
    $tamperedIds = [];

    foreach ($logs as $entry) {
        if (!isset($entry['signature'])) {
            // 署名がない古いエントリ（後方互換性のため許容）
            $unsigned++;
        } elseif (verifyAuditLogEntry($entry)) {
            $verified++;
        } else {
            $failed++;
            $tamperedIds[] = $entry['id'] ?? 'unknown';
        }
    }

    return [
        'valid' => $failed === 0,
        'total' => $total,
        'verified' => $verified,
        'failed' => $failed,
        'unsigned' => $unsigned,
        'tampered_ids' => $tamperedIds
    ];
}
