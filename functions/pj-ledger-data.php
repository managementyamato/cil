<?php
/**
 * PJ管理台帳 専用データ管理
 * data.json とは別の pj-ledger.json を使用
 * getData/saveData と同等のロック・スナップショット機能付き
 */

define('PJ_LEDGER_FILE', dirname(__DIR__) . '/pj-ledger.json');

/**
 * PJ台帳データ読み込み（排他ロック付き・同一リクエスト内キャッシュ）
 */
function getPjLedgerData($forceReload = false) {
    static $cache = null;
    if ($cache !== null && !$forceReload) {
        return $cache;
    }

    if (file_exists(PJ_LEDGER_FILE)) {
        $fp = fopen(PJ_LEDGER_FILE, 'r');
        if ($fp === false) {
            $cache = getInitialPjLedgerData();
            return $cache;
        }
        if (flock($fp, LOCK_SH)) {
            $json = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            $data = json_decode($json, true);
            if ($data !== null) {
                // 不足キーを補完
                $data = ensurePjLedgerSchema($data);
                $cache = $data;
                return $cache;
            }
        } else {
            fclose($fp);
        }
    }

    $cache = getInitialPjLedgerData();
    return $cache;
}

/**
 * PJ台帳データ保存（排他ロック + アトミック書き込み）
 */
function savePjLedgerData($data) {
    // スナップショット作成
    createPjLedgerSnapshot();

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new Exception('PJ台帳データのJSONエンコードに失敗しました: ' . json_last_error_msg());
    }

    // アトミック書き込み: 一時ファイルに書いてからリネーム
    $tmpFile = PJ_LEDGER_FILE . '.tmp';
    $fp = fopen($tmpFile, 'w');
    if ($fp === false) {
        throw new Exception('PJ台帳データの一時ファイル作成に失敗しました');
    }

    if (flock($fp, LOCK_EX)) {
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        // Windows ではrename前に既存ファイルを削除
        if (file_exists(PJ_LEDGER_FILE)) {
            @unlink(PJ_LEDGER_FILE);
        }
        if (!rename($tmpFile, PJ_LEDGER_FILE)) {
            throw new Exception('PJ台帳データのファイル書き込みに失敗しました');
        }
    } else {
        fclose($fp);
        @unlink($tmpFile);
        throw new Exception('PJ台帳データのファイルロックに失敗しました');
    }

    // キャッシュをクリア
    getPjLedgerData(true);
}

/**
 * スナップショット作成
 */
function createPjLedgerSnapshot() {
    if (!file_exists(PJ_LEDGER_FILE)) {
        return;
    }

    $snapshotDir = dirname(PJ_LEDGER_FILE) . '/snapshots';
    if (!is_dir($snapshotDir)) {
        @mkdir($snapshotDir, 0755, true);
    }

    // 最新スナップショットが5分以内なら作成しない
    $files = @glob($snapshotDir . '/pj-ledger_*.json');
    if ($files) {
        sort($files);
        $latestSnapshot = end($files);
        $lastModified = filemtime($latestSnapshot);
        if ($lastModified && (time() - $lastModified) < 300) {
            return;
        }
    }

    $timestamp = date('Ymd_His');
    $snapshotFile = $snapshotDir . '/pj-ledger_' . $timestamp . '.json';
    @copy(PJ_LEDGER_FILE, $snapshotFile);

    // 最大20世代
    $files = @glob($snapshotDir . '/pj-ledger_*.json');
    if ($files && count($files) > 20) {
        sort($files);
        $deleteCount = count($files) - 20;
        for ($i = 0; $i < $deleteCount; $i++) {
            @unlink($files[$i]);
        }
    }
}

/**
 * 初期データ構造
 */
function getInitialPjLedgerData() {
    return [
        'projects' => [],
        'monthly_profits' => [],
    ];
}

/**
 * スキーマ補完
 */
function ensurePjLedgerSchema($data) {
    $defaults = getInitialPjLedgerData();
    foreach ($defaults as $key => $default) {
        if (!isset($data[$key])) {
            $data[$key] = $default;
        }
    }
    return $data;
}

/**
 * 削除済み除外（filterDeletedと同等）
 */
function filterPjDeleted($items) {
    return array_values(array_filter($items, function($item) {
        return empty($item['deleted_at']);
    }));
}
