<?php
/**
 * MFクラウド請求書から請求書データを同期するAPI (バックグラウンドジョブ版)
 *
 * 設計:
 * - POST ?action=start (default): ジョブを作成、対象月のリストを準備して即時返答
 * - GET  ?action=process: 1ポーリングで 1ヶ月分を処理 (~1〜3秒)
 *
 * 旧仕様 (同期実行) を直接呼び出していた経路は POST のデフォルト動作として残る (start 扱い)。
 * finance.php からは js/background-jobs.js のポーラーが process を回す。
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/api-middleware.php';
require_once __DIR__ . '/mf-api.php';
require_once __DIR__ . '/../functions/mf-invoice-sync.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => true, // POSTのみ
    'allowedMethods' => ['GET', 'POST'],
]);

if (!canEdit()) {
    errorResponse('編集権限が必要です', 403);
}

$jobFile = __DIR__ . '/../data/background-jobs.json';

function syncInvLoadJobs() {
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
function syncInvSaveJobs(array $jobs) {
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
function syncInvUpdateJob($jobId, array $updates) {
    $jobs = syncInvLoadJobs();
    if (!isset($jobs[$jobId])) return false;
    // array_replace_recursive の numeric-array バグ回避: 浅い replace
    $jobs[$jobId] = array_replace($jobs[$jobId], $updates);
    return syncInvSaveJobs($jobs);
}

// ── GET ?action=process: 1ヶ月分を処理 ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'process') {
    $jobs = syncInvLoadJobs();
    $processed = false;

    foreach ($jobs as $jobId => $job) {
        if (($job['type'] ?? '') !== 'mf_invoice_sync') continue;
        if (($job['status'] ?? '') !== 'running') continue;

        $d = $job['data'] ?? [];
        $pending   = $d['pending_months']   ?? [];
        $completed = $d['completed_months'] ?? [];
        $totals    = $d['totals'] ?? ['new' => 0, 'updated' => 0, 'deleted' => 0, 'synced' => 0];
        $total     = $job['total'] ?? (count($pending) + count($completed));

        if (empty($pending)) {
            $msg = "{$totals['synced']}件の同期完了 (新規{$totals['new']}件, 更新{$totals['updated']}件, 削除{$totals['deleted']}件)";
            syncInvUpdateJob($jobId, [
                'status' => 'completed',
                'completed_at' => time(),
                'message' => $msg,
                'progress' => $total,
                'result' => $totals,
            ]);
            echo json_encode(['success' => true, 'completed' => true, 'job_id' => $jobId, 'totals' => $totals], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 1ヶ月分を処理
        $month = array_shift($pending);
        $from  = date('Y-m-01', strtotime($month . '-01'));
        $to    = date('Y-m-t',  strtotime($month . '-01'));

        try {
            if (!MFApiClient::isConfigured()) {
                throw new Exception('MFクラウド請求書APIが設定されていません');
            }
            $client   = new MFApiClient();
            $invoices = $client->getAllInvoices($from, $to, true);

            $data = getData();
            if (!isset($data['mf_invoices'])) $data['mf_invoices'] = [];

            $r = syncMfInvoices($data, $invoices, $from, $to);
            saveData($r['data'], ['mf_invoices', 'mf_sync_timestamp']);

            $totals['new']     += $r['new'];
            $totals['updated'] += $r['updated'];
            $totals['deleted'] += $r['deleted'];
            $totals['synced']  += count($invoices);
        } catch (Throwable $e) {
            // 1ヶ月の失敗は致命でないので記録して続行
            $totals['errors'] = $totals['errors'] ?? [];
            $totals['errors'][] = "{$month}: " . $e->getMessage();
        }

        $completed[] = $month;
        $progress = count($completed);
        syncInvUpdateJob($jobId, [
            'progress' => $progress,
            'message'  => "{$progress}/{$total} ヶ月処理中 ({$month} 完了)",
            'data'     => [
                'pending_months'   => $pending,
                'completed_months' => $completed,
                'totals'           => $totals,
            ],
        ]);

        echo json_encode([
            'success' => true,
            'processed' => true,
            'job_id' => $jobId,
            'progress' => $progress,
            'total' => $total,
            'last_month' => $month,
        ], JSON_UNESCAPED_UNICODE);
        $processed = true;
        break;
    }

    if (!$processed) {
        echo json_encode(['success' => true, 'processed' => false, 'message' => 'No pending mf_invoice_sync jobs'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ── POST: ジョブ起動 ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

if (!MFApiClient::isConfigured()) {
    echo json_encode(['success' => false, 'error' => 'MFクラウド請求書APIが設定されていません。設定画面から認証してください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 期間指定
$targetMonth = trim($_POST['target_month'] ?? '');
if ($targetMonth === '') $targetMonth = date('Y-m');

// pending_months のリストを生成
if ($targetMonth === 'all') {
    // 全期間: 過去36ヶ月 (新しい順)
    $months = [];
    for ($i = 0; $i < 36; $i++) {
        $months[] = date('Y-m', strtotime('-' . $i . ' months'));
    }
    $label = '全期間 (過去3年)';
} else {
    if (!preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
        echo json_encode(['success' => false, 'error' => '不正な月指定です'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $months = [$targetMonth];
    $label = date('Y年n月', strtotime($targetMonth . '-01'));
}

// 同種の running ジョブがあれば再利用
$jobs = syncInvLoadJobs();
foreach ($jobs as $existing) {
    if (($existing['type'] ?? '') !== 'mf_invoice_sync') continue;
    if (($existing['status'] ?? '') !== 'running') continue;
    echo json_encode([
        'success' => true,
        'job_id'  => $existing['id'],
        'reused'  => true,
        'message' => '既にMF請求書同期が実行中です',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 古いジョブのクリーンアップ
$cutoff = time() - 86400;
$jobs = array_filter($jobs, fn($j) => ($j['created_at'] ?? 0) > $cutoff);

$total = count($months);
$jobId = uniqid('mf_invoice_sync_', true);
$jobs[$jobId] = [
    'id'          => $jobId,
    'type'        => 'mf_invoice_sync',
    'description' => "MF請求書同期 ({$label})",
    'status'      => 'running',
    'progress'    => 0,
    'total'       => $total,
    'message'     => "0/{$total} ヶ月処理開始...",
    'process_url' => '/api/sync-invoices.php?action=process',
    'created_at'  => time(),
    'data' => [
        'pending_months'   => $months,
        'completed_months' => [],
        'totals' => ['new' => 0, 'updated' => 0, 'deleted' => 0, 'synced' => 0],
    ],
];
syncInvSaveJobs($jobs);

echo json_encode([
    'success'  => true,
    'job_id'   => $jobId,
    'total'    => $total,
    'message'  => "MF請求書同期を開始 ({$label}, {$total}ヶ月)。別ページに移動しても処理は続行されます。",
    // 旧フロントの互換 (data.period.from/to)
    'period'   => ['from' => date('Y-m-01', strtotime(end($months) . '-01')), 'to' => date('Y-m-t', strtotime(reset($months) . '-01'))],
], JSON_UNESCAPED_UNICODE);
