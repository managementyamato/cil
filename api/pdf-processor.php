<?php
/**
 * PDF非同期処理システム
 * 複数PDFを効率的に処理するためのジョブキューとキャッシュ機能
 */

require_once __DIR__ . '/google-drive.php';

class PdfProcessor {
    private $driveClient;
    private $cacheDir;
    private $jobFile;
    private $resultFile;
    private $cacheTTL = 86400; // 24時間キャッシュ

    public function __construct() {
        $this->driveClient = new GoogleDriveClient();
        $this->cacheDir = __DIR__ . '/../cache/pdf';
        $this->jobFile = __DIR__ . '/../cache/pdf_jobs.json';
        $this->resultFile = __DIR__ . '/../cache/pdf_results.json';

        // キャッシュディレクトリを作成
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * PDFテキスト抽出結果をキャッシュから取得
     */
    public function getCachedResult($fileId, $modifiedTime = null) {
        $cacheFile = $this->cacheDir . '/' . md5($fileId) . '.json';

        if (!file_exists($cacheFile)) {
            return null;
        }

        $cached = json_decode(file_get_contents($cacheFile), true);
        if (!$cached) {
            return null;
        }

        // キャッシュ有効期限チェック
        if (time() > ($cached['cached_at'] ?? 0) + $this->cacheTTL) {
            unlink($cacheFile);
            return null;
        }

        // ファイル更新日時が変わっていたらキャッシュ無効
        if ($modifiedTime && isset($cached['modified_time']) && $cached['modified_time'] !== $modifiedTime) {
            unlink($cacheFile);
            return null;
        }

        return $cached;
    }

    /**
     * PDFテキスト抽出結果をキャッシュに保存
     */
    public function setCachedResult($fileId, $text, $amounts, $modifiedTime = null) {
        $cacheFile = $this->cacheDir . '/' . md5($fileId) . '.json';

        $data = [
            'file_id' => $fileId,
            'text' => $text,
            'amounts' => $amounts,
            'modified_time' => $modifiedTime,
            'cached_at' => time()
        ];

        file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 単一PDFを処理（キャッシュ優先）
     */
    public function processSinglePdf($fileId, $modifiedTime = null) {
        $timing = ['start' => microtime(true)];

        // キャッシュチェック
        $timing['cache_check_start'] = microtime(true);
        $cached = $this->getCachedResult($fileId, $modifiedTime);
        $timing['cache_check_end'] = microtime(true);

        if ($cached) {
            $timing['end'] = microtime(true);
            return [
                'success' => true,
                'text' => $cached['text'],
                'amounts' => $cached['amounts'],
                'from_cache' => true,
                'timing' => [
                    'total_ms' => round(($timing['end'] - $timing['start']) * 1000, 2),
                    'cache_check_ms' => round(($timing['cache_check_end'] - $timing['cache_check_start']) * 1000, 2),
                    'source' => 'cache'
                ]
            ];
        }

        try {
            // PDF→Google Docs変換（OCR）- 詳細タイミング付き
            $timing['ocr_start'] = microtime(true);
            $ocrResult = $this->driveClient->extractTextFromPdf($fileId, true);
            $text = $ocrResult['text'];
            $ocrTiming = $ocrResult['timing'] ?? [];
            $timing['ocr_end'] = microtime(true);

            // テキストから金額抽出
            $timing['extract_start'] = microtime(true);
            $amounts = $this->driveClient->extractAmountsFromText($text);
            $timing['extract_end'] = microtime(true);

            // キャッシュに保存
            $timing['cache_save_start'] = microtime(true);
            $this->setCachedResult($fileId, $text, $amounts, $modifiedTime);
            $timing['cache_save_end'] = microtime(true);

            $timing['end'] = microtime(true);

            return [
                'success' => true,
                'text' => $text,
                'amounts' => $amounts,
                'from_cache' => false,
                'timing' => [
                    'total_ms' => round(($timing['end'] - $timing['start']) * 1000, 2),
                    'cache_check_ms' => round(($timing['cache_check_end'] - $timing['cache_check_start']) * 1000, 2),
                    'ocr_total_ms' => round(($timing['ocr_end'] - $timing['ocr_start']) * 1000, 2),
                    'ocr_detail' => $ocrTiming,
                    'extract_ms' => round(($timing['extract_end'] - $timing['extract_start']) * 1000, 2),
                    'cache_save_ms' => round(($timing['cache_save_end'] - $timing['cache_save_start']) * 1000, 2),
                    'source' => 'google_docs_ocr'
                ]
            ];
        } catch (Exception $e) {
            $timing['end'] = microtime(true);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timing' => [
                    'total_ms' => round(($timing['end'] - $timing['start']) * 1000, 2),
                    'source' => 'error'
                ]
            ];
        }
    }

    /**
     * バッチジョブを作成（複数PDFを登録）
     */
    public function createBatchJob($pdfFiles) {
        $jobId = uniqid('batch_', true);

        $jobs = $this->loadJobs();
        $jobs[$jobId] = [
            'id' => $jobId,
            'created_at' => time(),
            'status' => 'pending',
            'total' => count($pdfFiles),
            'processed' => 0,
            'files' => []
        ];

        foreach ($pdfFiles as $pdf) {
            $fileId = $pdf['id'];
            $jobs[$jobId]['files'][$fileId] = [
                'id' => $fileId,
                'name' => $pdf['name'],
                'modified_time' => $pdf['modifiedTime'] ?? null,
                'status' => 'pending',
                'result' => null
            ];
        }

        $this->saveJobs($jobs);
        return $jobId;
    }

    /**
     * ジョブの次のファイルを1つ処理
     */
    public function processNextInJob($jobId) {
        $jobs = $this->loadJobs();

        if (!isset($jobs[$jobId])) {
            return ['error' => 'Job not found'];
        }

        $job = &$jobs[$jobId];

        // 処理中に変更
        if ($job['status'] === 'pending') {
            $job['status'] = 'processing';
        }

        // 次の未処理ファイルを探す
        $nextFile = null;
        foreach ($job['files'] as $fileId => &$file) {
            if ($file['status'] === 'pending') {
                $nextFile = &$file;
                break;
            }
        }

        if (!$nextFile) {
            // すべて処理完了
            $job['status'] = 'completed';
            $job['completed_at'] = time();
            $this->saveJobs($jobs);
            return [
                'done' => true,
                'job' => $job
            ];
        }

        // ファイルを処理
        $nextFile['status'] = 'processing';
        $this->saveJobs($jobs);

        $result = $this->processSinglePdf($nextFile['id'], $nextFile['modified_time']);

        $nextFile['status'] = $result['success'] ? 'completed' : 'error';
        $nextFile['result'] = $result;
        $job['processed']++;

        $this->saveJobs($jobs);

        return [
            'done' => false,
            'file' => $nextFile,
            'progress' => [
                'processed' => $job['processed'],
                'total' => $job['total'],
                'percent' => round(($job['processed'] / $job['total']) * 100)
            ]
        ];
    }

    /**
     * ジョブのステータスを取得
     */
    public function getJobStatus($jobId) {
        $jobs = $this->loadJobs();

        if (!isset($jobs[$jobId])) {
            return null;
        }

        return $jobs[$jobId];
    }

    /**
     * ジョブの全結果を取得
     */
    public function getJobResults($jobId) {
        $job = $this->getJobStatus($jobId);
        if (!$job) {
            return null;
        }

        $results = [];
        foreach ($job['files'] as $fileId => $file) {
            $results[$fileId] = [
                'name' => $file['name'],
                'status' => $file['status'],
                'amounts' => $file['result']['amounts'] ?? [],
                'text' => $file['result']['text'] ?? '',
                'error' => $file['result']['error'] ?? null,
                'from_cache' => $file['result']['from_cache'] ?? false
            ];
        }

        return [
            'job_id' => $jobId,
            'status' => $job['status'],
            'progress' => [
                'processed' => $job['processed'],
                'total' => $job['total']
            ],
            'results' => $results
        ];
    }

    /**
     * 古いジョブをクリーンアップ（1時間以上前のもの）
     */
    public function cleanupOldJobs() {
        $jobs = $this->loadJobs();
        $cutoff = time() - 3600; // 1時間前

        foreach ($jobs as $jobId => $job) {
            if (($job['completed_at'] ?? $job['created_at']) < $cutoff) {
                unset($jobs[$jobId]);
            }
        }

        $this->saveJobs($jobs);
    }

    /**
     * キャッシュをクリア
     */
    public function clearCache($fileId = null) {
        if ($fileId) {
            $cacheFile = $this->cacheDir . '/' . md5($fileId) . '.json';
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        } else {
            // 全キャッシュクリア
            $files = glob($this->cacheDir . '/*.json');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    /**
     * キャッシュ統計を取得
     */
    public function getCacheStats() {
        $files = glob($this->cacheDir . '/*.json');
        $totalSize = 0;
        $validCount = 0;
        $expiredCount = 0;

        foreach ($files as $file) {
            $totalSize += filesize($file);
            $data = json_decode(file_get_contents($file), true);
            if ($data && time() < ($data['cached_at'] ?? 0) + $this->cacheTTL) {
                $validCount++;
            } else {
                $expiredCount++;
            }
        }

        return [
            'total_files' => count($files),
            'valid_files' => $validCount,
            'expired_files' => $expiredCount,
            'total_size_kb' => round($totalSize / 1024, 2)
        ];
    }

    private function loadJobs() {
        if (!file_exists($this->jobFile)) {
            return [];
        }
        return json_decode(file_get_contents($this->jobFile), true) ?: [];
    }

    private function saveJobs($jobs) {
        file_put_contents($this->jobFile, json_encode($jobs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

// API エンドポイント
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');

    require_once __DIR__ . '/../config/config.php';

    // 認証チェック
    if (!isset($_SESSION['user_email']) || !canEdit()) {
        http_response_code(403);
        echo json_encode(['error' => '権限がありません']);
        exit;
    }

    // POST時はCSRF検証
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrfToken();
    }

    $processor = new PdfProcessor();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'process_single':
                // 単一PDFを処理
                $fileId = $_POST['file_id'] ?? '';
                $modifiedTime = $_POST['modified_time'] ?? null;
                if (!$fileId) {
                    throw new Exception('file_id is required');
                }
                $result = $processor->processSinglePdf($fileId, $modifiedTime);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                break;

            case 'timing_test':
                // タイミングテスト（単一PDF）
                $fileId = $_POST['file_id'] ?? '';
                if (!$fileId) {
                    throw new Exception('file_id is required');
                }
                // キャッシュを使わずに処理時間を計測
                $processor->clearCache($fileId);
                $result = $processor->processSinglePdf($fileId, null);
                echo json_encode([
                    'success' => $result['success'],
                    'timing' => $result['timing'] ?? null,
                    'amounts_count' => count($result['amounts'] ?? []),
                    'text_length' => strlen($result['text'] ?? '')
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'create_batch':
                // バッチジョブを作成
                $files = json_decode($_POST['files'] ?? '[]', true);
                if (empty($files)) {
                    throw new Exception('files array is required');
                }
                $jobId = $processor->createBatchJob($files);
                echo json_encode(['success' => true, 'job_id' => $jobId]);
                break;

            case 'process_next':
                // ジョブの次のファイルを処理
                $jobId = $_POST['job_id'] ?? '';
                if (!$jobId) {
                    throw new Exception('job_id is required');
                }
                $result = $processor->processNextInJob($jobId);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                break;

            case 'job_status':
                // ジョブステータスを取得
                $jobId = $_GET['job_id'] ?? '';
                if (!$jobId) {
                    throw new Exception('job_id is required');
                }
                $status = $processor->getJobStatus($jobId);
                echo json_encode($status, JSON_UNESCAPED_UNICODE);
                break;

            case 'job_results':
                // ジョブ結果を取得
                $jobId = $_GET['job_id'] ?? '';
                if (!$jobId) {
                    throw new Exception('job_id is required');
                }
                $results = $processor->getJobResults($jobId);
                echo json_encode($results, JSON_UNESCAPED_UNICODE);
                break;

            case 'cache_stats':
                // キャッシュ統計を取得
                echo json_encode($processor->getCacheStats());
                break;

            case 'clear_cache':
                // キャッシュをクリア
                $fileId = $_POST['file_id'] ?? null;
                $processor->clearCache($fileId);
                echo json_encode(['success' => true]);
                break;

            case 'cleanup':
                // 古いジョブをクリーンアップ
                $processor->cleanupOldJobs();
                echo json_encode(['success' => true]);
                break;

            default:
                throw new Exception('Unknown action: ' . $action);
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
