<?php
/**
 * トラブル対応 スプレッドシート同期API (バックグラウンドジョブ版)
 *
 * 設計:
 * - POST ?action=start (旧シグネチャ): スプレッドシート全行を取得 (1〜2秒)、
 *                                       行リストを pending に詰めて即時返答
 * - GET  ?action=process: pending から 100 行 pop して重複判定・追加 (~0.2秒)
 *                         完了時に saveData
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/google-sheets.php';

if (!isAdmin()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => '管理者権限が必要です']);
    exit;
}

const SYNC_TROUBLES_CHUNK_SIZE = 100;

$jobFile = __DIR__ . '/../data/background-jobs.json';

function syncTrLoadJobs() {
    global $jobFile;
    if (!file_exists($jobFile)) return [];
    $fp = @fopen($jobFile, 'r');
    if (!$fp) return [];
    if (flock($fp, LOCK_SH)) {
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return json_decode($content, true) ?: [];
    }
    fclose($fp);
    return [];
}
function syncTrSaveJobs(array $jobs) {
    global $jobFile;
    if (!is_dir(dirname($jobFile))) @mkdir(dirname($jobFile), 0755, true);
    $fp = @fopen($jobFile, 'c');
    if (!$fp) return false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp); flock($fp, LOCK_UN); fclose($fp);
        return true;
    }
    fclose($fp);
    return false;
}
function syncTrUpdateJob($jobId, array $updates) {
    $jobs = syncTrLoadJobs();
    if (!isset($jobs[$jobId])) return false;
    // array_replace_recursive の numeric-array バグ回避: 浅い replace
    $jobs[$jobId] = array_replace($jobs[$jobId], $updates);
    return syncTrSaveJobs($jobs);
}

// ── GET ?action=process ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'process') {
    header('Content-Type: application/json; charset=utf-8');
    $jobs = syncTrLoadJobs();
    $processed = false;

    foreach ($jobs as $jobId => $job) {
        if (($job['type'] ?? '') !== 'troubles_sync') continue;
        if (($job['status'] ?? '') !== 'running') continue;

        $d = $job['data'] ?? [];
        $pending  = $d['pending_rows'] ?? [];
        $added    = $d['added_count']  ?? 0;
        $skipped  = $d['skipped_count']?? 0;
        $total    = $job['total'] ?? (count($pending) + $added + $skipped);

        if (empty($pending)) {
            // 完了: ここで saveData は process 内で既に呼んでいるので最終処理は不要
            $msg = "{$added}件を追加しました";
            if ($skipped > 0) $msg .= " ({$skipped}件は既存のためスキップ)";
            syncTrUpdateJob($jobId, [
                'status' => 'completed', 'completed_at' => time(),
                'progress' => $total, 'message' => $msg,
                'result' => ['added' => $added, 'skipped' => $skipped],
            ]);
            echo json_encode(['success' => true, 'completed' => true, 'job_id' => $jobId, 'added' => $added, 'skipped' => $skipped], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 100行 pop して処理
        $chunk = array_splice($pending, 0, SYNC_TROUBLES_CHUNK_SIZE);

        try {
            $data = getData();
            if (!isset($data['troubles'])) $data['troubles'] = [];
            $existing = $data['troubles'];

            $existingKeys = [];
            $maxId = 0;
            foreach ($existing as $t) {
                $existingKeys[makeMatchKey($t)] = true;
                if (isset($t['id']) && $t['id'] > $maxId) $maxId = $t['id'];
            }
            $now = date('Y-m-d H:i:s');

            foreach ($chunk as $st) {
                $k = makeMatchKey($st);
                if (isset($existingKeys[$k])) { $skipped++; continue; }
                $maxId++;
                $st['id'] = $maxId;
                $st['created_at'] = $now;
                $st['updated_at'] = $now;
                $st['synced_from_sheet'] = true;
                $existing[] = $st;
                $existingKeys[$k] = true;
                $added++;
            }

            $data['troubles'] = $existing;
            saveData($data, ['troubles']);
        } catch (Throwable $e) {
            syncTrUpdateJob($jobId, [
                'status' => 'failed', 'completed_at' => time(),
                'message' => '保存失敗: ' . $e->getMessage(), 'error' => $e->getMessage(),
            ]);
            echo json_encode(['success' => false, 'completed' => true, 'job_id' => $jobId, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $progress = $added + $skipped;
        syncTrUpdateJob($jobId, [
            'progress' => $progress,
            'message'  => "{$progress}/{$total} 行処理 ({$added}追加 / {$skipped}スキップ)",
            'data' => [
                'pending_rows'  => $pending,
                'added_count'   => $added,
                'skipped_count' => $skipped,
            ],
        ]);

        echo json_encode([
            'success' => true, 'processed' => true,
            'job_id' => $jobId, 'progress' => $progress, 'total' => $total,
        ], JSON_UNESCAPED_UNICODE);
        $processed = true;
        break;
    }

    if (!$processed) {
        echo json_encode(['success' => true, 'processed' => false, 'message' => 'No pending troubles_sync jobs'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ── POST: ジョブ起動 ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

verifyCsrfToken();
header('Content-Type: application/json; charset=utf-8');

// 同種 running があれば再利用
$jobs = syncTrLoadJobs();
foreach ($jobs as $existing) {
    if (($existing['type'] ?? '') !== 'troubles_sync') continue;
    if (($existing['status'] ?? '') !== 'running') continue;
    echo json_encode([
        'success' => true, 'job_id' => $existing['id'], 'reused' => true,
        'message' => '既にトラブル同期が実行中です',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// スプレッドシート設定
$spreadsheetId = '1aD-VKgboXiYYrkkSp3bGWD3pOT87tctCWZTb16z0V6A';
$sheetName     = '対応記録表';
$dataRange     = 'A3:M';

try {
    $client = new GoogleSheetsClient();
    $token = $client->getAccessToken();
    if (!$token) throw new Exception('Google認証が設定されていません');

    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/"
         . urlencode("{$sheetName}!{$dataRange}");
    $opts = [
        'http' => ['header' => "Authorization: Bearer {$token}\r\n", 'method' => 'GET', 'ignore_errors' => true, 'timeout' => 30],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ];
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) throw new Exception('Google Sheets APIへの接続に失敗');

    $sheetsData = json_decode($response, true);
    if (isset($sheetsData['error'])) throw new Exception('Sheets API: ' . ($sheetsData['error']['message'] ?? json_encode($sheetsData['error'])));

    $rows = $sheetsData['values'] ?? [];
    if (empty($rows)) {
        echo json_encode(['success' => true, 'message' => 'スプレッドシートにデータがありません', 'count' => 0]);
        exit;
    }

    // 行をパース
    $pendingRows = [];
    foreach ($rows as $row) {
        $pj = trim($row[0] ?? '');
        $content = trim($row[1] ?? '');
        if (empty($pj) && empty($content)) continue;

        $rawStatus = trim($row[5] ?? '');
        $status    = mapStatus($rawStatus);
        $rawDate   = trim($row[6] ?? '');
        $date      = normalizeDate($rawDate);
        if (preg_match('/^[Pp]\d+$/', $pj)) $pj = strtoupper($pj);

        $pendingRows[] = [
            'pj_number'        => $pj,
            'trouble_content'  => trim($row[1] ?? ''),
            'response_content' => trim($row[2] ?? ''),
            'reporter'         => trim($row[3] ?? ''),
            'responder'        => trim($row[4] ?? ''),
            'status'           => $status,
            'date'             => $date,
            'call_no'          => trim($row[7] ?? ''),
            'project_contact'  => strtoupper(trim($row[8] ?? '')) === 'TRUE',
            'case_no'          => trim($row[9] ?? ''),
            'company_name'     => trim($row[10] ?? ''),
            'customer_name'    => trim($row[11] ?? ''),
            'honorific'        => trim($row[12] ?? '') ?: '様',
        ];
    }

    if (empty($pendingRows)) {
        echo json_encode(['success' => true, 'message' => '有効なデータがありません', 'count' => 0]);
        exit;
    }

    $cutoff = time() - 86400;
    $jobs = array_filter($jobs, fn($j) => ($j['created_at'] ?? 0) > $cutoff);

    $total = count($pendingRows);
    $jobId = uniqid('troubles_sync_', true);
    $jobs[$jobId] = [
        'id'          => $jobId,
        'type'        => 'troubles_sync',
        'description' => "トラブル対応スプシ同期 ({$total}行)",
        'status'      => 'running',
        'progress'    => 0,
        'total'       => $total,
        'message'     => "0/{$total} 行処理開始...",
        'process_url' => '/api/sync-troubles.php?action=process',
        'created_at'  => time(),
        'data' => [
            'pending_rows'  => $pendingRows,
            'added_count'   => 0,
            'skipped_count' => 0,
        ],
    ];
    syncTrSaveJobs($jobs);

    echo json_encode([
        'success' => true,
        'job_id'  => $jobId,
        'total'   => $total,
        'message' => "トラブル同期を開始 ({$total}行)。別ページに移動しても処理は続行されます。",
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// ────────────────────────────────────────────────────────────
// ヘルパー関数 (旧仕様から流用)
// ────────────────────────────────────────────────────────────
function makeMatchKey($trouble) {
    $pj = trim($trouble['pj_number'] ?? $trouble['project_name'] ?? '');
    if (preg_match('/^[Pp]\d+$/', $pj)) $pj = strtoupper($pj);
    $content = trim($trouble['trouble_content'] ?? '');
    $date = normalizeDate(trim($trouble['date'] ?? ''));
    return $pj . '||' . mb_strtolower($content) . '||' . $date;
}

function mapStatus($rawStatus) {
    $statusMap = [
        '未対応' => '未対応', '対応中' => '対応中', '保留' => '保留',
        '完了' => '完了', '解決' => '完了',
        '1.解決' => '完了', '2.対応中' => '対応中', '3.保留' => '保留', '0.未対応' => '未対応',
    ];
    if (isset($statusMap[$rawStatus])) return $statusMap[$rawStatus];
    foreach ($statusMap as $pattern => $mapped) {
        if (mb_strpos($rawStatus, $pattern) !== false) return $mapped;
    }
    if (mb_strpos($rawStatus, '解決') !== false || mb_strpos($rawStatus, '完了') !== false) return '完了';
    if (mb_strpos($rawStatus, '対応中') !== false) return '対応中';
    if (mb_strpos($rawStatus, '保留')   !== false) return '保留';
    return $rawStatus ?: '未対応';
}

function normalizeDate($dateStr) {
    if (empty($dateStr)) return '';
    $dateStr = str_replace(['年', '月', '日'], ['/', '/', ''], $dateStr);
    $dateStr = preg_replace('#/+#', '/', trim($dateStr, '/'));
    if (preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $dateStr, $m)) {
        return sprintf('%04d/%02d/%02d', $m[1], $m[2], $m[3]);
    }
    return $dateStr;
}
