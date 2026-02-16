<?php
/**
 * 借入金一覧の色付け処理API
 * - start: ジョブを作成して即座にレスポンス（ページ遷移可能）
 * - process: 保留中のジョブを1件処理（ポーリングから呼ばれる）
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/google-sheets.php';

$jobFile = __DIR__ . '/../data/background-jobs.json';

function loadJobs() {
    global $jobFile;
    if (!file_exists($jobFile)) {
        return [];
    }
    $content = @file_get_contents($jobFile);
    return $content ? (json_decode($content, true) ?: []) : [];
}

function saveJobs($jobs) {
    global $jobFile;
    if (!file_exists(dirname($jobFile))) {
        mkdir(dirname($jobFile), 0755, true);
    }
    file_put_contents($jobFile, json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function updateJob($jobId, $updates) {
    $jobs = loadJobs();
    if (isset($jobs[$jobId])) {
        $jobs[$jobId] = array_merge($jobs[$jobId], $updates);
        saveJobs($jobs);
        return true;
    }
    return false;
}

// 認証チェック
if (!isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です']);
    exit;
}

if (!canEdit()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '権限がありません']);
    exit;
}

// GETリクエスト: 保留中のジョブを処理
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'process') {
    $jobs = loadJobs();
    $processed = false;

    foreach ($jobs as $jobId => $job) {
        if ($job['type'] === 'loan_coloring' && $job['status'] === 'running') {
            $data = $job['data'] ?? [];
            $entries = $data['pending_entries'] ?? [];
            $yearMonth = $data['yearMonth'] ?? '';
            $processedCount = $data['processed_count'] ?? 0;
            $successCount = $data['success_count'] ?? 0;
            $failCount = $data['fail_count'] ?? 0;
            $total = $job['total'] ?? count($entries);

            if (empty($entries)) {
                // 全て処理完了
                $message = "{$successCount}件の色付けが完了しました";
                if ($failCount > 0) {
                    $message .= "（{$failCount}件失敗）";
                }
                updateJob($jobId, [
                    'status' => $successCount > 0 ? 'completed' : 'failed',
                    'completed_at' => time(),
                    'message' => $message,
                    'progress' => $total,
                    'result' => ['successCount' => $successCount, 'failCount' => $failCount]
                ]);
                echo json_encode(['success' => true, 'completed' => true, 'job_id' => $jobId]);
                exit;
            }

            // 1件処理
            $entry = array_shift($entries);
            $amount = intval($entry['amount'] ?? 0);

            if ($amount > 0) {
                try {
                    $sheetsClient = new GoogleSheetsClient();
                    $result = $sheetsClient->markMatchingCell($amount, $yearMonth, null);
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                } catch (Exception $e) {
                    $failCount++;
                }
            }
            $processedCount++;

            // ジョブを更新
            updateJob($jobId, [
                'progress' => $processedCount,
                'message' => "{$processedCount}/{$total} 処理中...",
                'data' => [
                    'pending_entries' => $entries,
                    'yearMonth' => $yearMonth,
                    'processed_count' => $processedCount,
                    'success_count' => $successCount,
                    'fail_count' => $failCount
                ]
            ]);

            echo json_encode([
                'success' => true,
                'processed' => true,
                'job_id' => $jobId,
                'remaining' => count($entries)
            ]);
            $processed = true;
            break;
        }
    }

    if (!$processed) {
        echo json_encode(['success' => true, 'processed' => false, 'message' => 'No pending jobs']);
    }
    exit;
}

// POSTリクエスト: ジョブ開始
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF検証
verifyCsrfToken();

$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? 'start';
$entries = $input['entries'] ?? [];
$yearMonth = $input['year_month'] ?? '';

if (empty($entries)) {
    echo json_encode(['success' => false, 'error' => '色付け対象のエントリがありません']);
    exit;
}

if (empty($yearMonth)) {
    echo json_encode(['success' => false, 'error' => '年月が指定されていません']);
    exit;
}

// ジョブを作成
$jobs = loadJobs();

// 古いジョブをクリーンアップ（24時間以上前）
$cutoff = time() - 86400;
$jobs = array_filter($jobs, function($job) use ($cutoff) {
    return ($job['created_at'] ?? 0) > $cutoff;
});

$jobId = uniqid('job_', true);
$jobs[$jobId] = [
    'id' => $jobId,
    'type' => 'loan_coloring',
    'description' => '借入金 色付け処理',
    'status' => 'running',
    'progress' => 0,
    'total' => count($entries),
    'message' => '処理を開始しています...',
    'created_at' => time(),
    'data' => [
        'pending_entries' => $entries,
        'yearMonth' => $yearMonth,
        'processed_count' => 0,
        'success_count' => 0,
        'fail_count' => 0
    ]
];

saveJobs($jobs);

// 即座にレスポンスを返す（ページ遷移可能）
echo json_encode([
    'success' => true,
    'job_id' => $jobId,
    'message' => '処理を開始しました。別のページに移動しても処理は続行されます。'
]);
