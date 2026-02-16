<?php
/**
 * バックグラウンドジョブ管理API
 * ジョブの開始・状態確認・完了通知を管理
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';

// 認証チェック
if (!isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['error' => '認証が必要です']);
    exit;
}

$jobFile = __DIR__ . '/../data/background-jobs.json';

// データディレクトリがなければ作成
if (!file_exists(dirname($jobFile))) {
    mkdir(dirname($jobFile), 0755, true);
}

// ジョブデータを読み込み（共有ロック付き）
function loadJobs($jobFile) {
    if (!file_exists($jobFile)) {
        return [];
    }
    $fp = fopen($jobFile, 'r');
    if ($fp && flock($fp, LOCK_SH)) {
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return json_decode($content, true) ?: [];
    }
    if ($fp) fclose($fp);
    return [];
}

// ジョブデータを保存（排他ロック付き）
function saveJobs($jobFile, $jobs) {
    $json = json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $fp = fopen($jobFile, 'c');
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

// 古いジョブをクリーンアップ（24時間以上前のもの）
function cleanupOldJobs(&$jobs) {
    $cutoff = time() - 86400;
    $jobs = array_filter($jobs, function($job) use ($cutoff) {
        return $job['created_at'] > $cutoff;
    });
}

// GETリクエスト：ジョブ状態確認
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'status') {
        // 特定のジョブの状態
        $jobId = $_GET['job_id'] ?? '';
        $jobs = loadJobs($jobFile);

        if (isset($jobs[$jobId])) {
            echo json_encode($jobs[$jobId]);
        } else {
            echo json_encode(['error' => 'Job not found']);
        }
        exit;
    }

    if ($action === 'active') {
        // アクティブなジョブ一覧（処理中または最近完了したもの）
        $jobs = loadJobs($jobFile);
        $activeJobs = [];
        $recentCutoff = time() - 30; // 30秒以内に完了したもの

        foreach ($jobs as $id => $job) {
            if ($job['status'] === 'running' ||
                ($job['status'] === 'completed' && ($job['completed_at'] ?? 0) > $recentCutoff) ||
                ($job['status'] === 'failed' && ($job['completed_at'] ?? 0) > $recentCutoff)) {
                $activeJobs[$id] = $job;
            }
        }

        echo json_encode(['jobs' => $activeJobs]);
        exit;
    }

    if ($action === 'dismiss') {
        // ジョブを非表示にする
        $jobId = $_GET['job_id'] ?? '';
        $jobs = loadJobs($jobFile);

        if (isset($jobs[$jobId])) {
            $jobs[$jobId]['dismissed'] = true;
            saveJobs($jobFile, $jobs);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Job not found']);
        }
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// POSTリクエスト：ジョブ操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
        // 新しいジョブを作成
        $jobs = loadJobs($jobFile);
        cleanupOldJobs($jobs);

        $jobId = uniqid('job_', true);
        $jobs[$jobId] = [
            'id' => $jobId,
            'type' => $input['type'] ?? 'unknown',
            'description' => $input['description'] ?? '',
            'status' => 'running',
            'progress' => 0,
            'total' => $input['total'] ?? 0,
            'created_at' => time(),
            'data' => $input['data'] ?? []
        ];

        saveJobs($jobFile, $jobs);
        echo json_encode(['success' => true, 'job_id' => $jobId]);
        exit;
    }

    if ($action === 'update') {
        // ジョブの進捗更新
        $jobId = $input['job_id'] ?? '';
        $jobs = loadJobs($jobFile);

        if (isset($jobs[$jobId])) {
            if (isset($input['progress'])) {
                $jobs[$jobId]['progress'] = $input['progress'];
            }
            if (isset($input['message'])) {
                $jobs[$jobId]['message'] = $input['message'];
            }
            saveJobs($jobFile, $jobs);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Job not found']);
        }
        exit;
    }

    if ($action === 'complete') {
        // ジョブ完了
        $jobId = $input['job_id'] ?? '';
        $jobs = loadJobs($jobFile);

        if (isset($jobs[$jobId])) {
            $jobs[$jobId]['status'] = 'completed';
            $jobs[$jobId]['completed_at'] = time();
            $jobs[$jobId]['result'] = $input['result'] ?? [];
            $jobs[$jobId]['message'] = $input['message'] ?? '完了しました';
            saveJobs($jobFile, $jobs);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Job not found']);
        }
        exit;
    }

    if ($action === 'fail') {
        // ジョブ失敗
        $jobId = $input['job_id'] ?? '';
        $jobs = loadJobs($jobFile);

        if (isset($jobs[$jobId])) {
            $jobs[$jobId]['status'] = 'failed';
            $jobs[$jobId]['completed_at'] = time();
            $jobs[$jobId]['error'] = $input['error'] ?? 'Unknown error';
            saveJobs($jobFile, $jobs);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Job not found']);
        }
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
