<?php
/**
 * HP更新お知らせ一覧 バックグラウンド最新化API
 *
 * 設計:
 * - POST ?action=start: ジョブを作成して即座にレスポンス (ページ遷移可能)
 *                       data/background-jobs.json に "cms_news_refresh" タイプで登録
 * - GET  ?action=process: 保留中のジョブから 1〜3 ファイルを処理して進捗を更新
 *                         js/background-jobs.js のポーリングから呼ばれる
 * - 全ファイル処理完了時に cache/cms-news-list-*.json を書き出してジョブ完了
 *
 * 単スレッド開発サーバ (php -S) でも、各 process 呼び出しは1〜3ファイル分
 * (約 300ms〜1秒) で完了するため、ナビゲーションは詰まらない。
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';
require_once __DIR__ . '/cms-config.php';
require_once __DIR__ . '/github-api.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => true, // POSTのみ。GETには適用されない
    'allowedMethods' => ['GET', 'POST'],
]);

if (!isAdmin()) {
    errorResponse('管理者権限が必要です', 403);
}

const CHUNK_SIZE = 3; // 1 ポーリング毎に処理するファイル数 (UX と単スレッドブロック時間のバランス)

$jobFile = __DIR__ . '/../../data/background-jobs.json';

// ── ジョブ操作ヘルパー ────────────────────────────────────────────
function refreshLoadJobs() {
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

function refreshSaveJobs(array $jobs) {
    global $jobFile;
    if (!is_dir(dirname($jobFile))) @mkdir(dirname($jobFile), 0755, true);
    $fp = @fopen($jobFile, 'c');
    if (!$fp) return false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }
    fclose($fp);
    return false;
}

function refreshUpdateJob($jobId, array $updates) {
    $jobs = refreshLoadJobs();
    if (!isset($jobs[$jobId])) return false;
    // 注: array_replace_recursive は numeric-indexed array で「短くなった配列を縮めない」バグがある
    //     (旧配列の余分な末尾要素が残るので pending_files が減らず無限ループ)
    //     → 浅い array_replace で各トップキーを丸ごと差し替える
    $jobs[$jobId] = array_replace($jobs[$jobId], $updates);
    return refreshSaveJobs($jobs);
}

// ── GET ?action=process: ジョブ進行（ポーリングから呼ばれる）──────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'process') {
    $jobs = refreshLoadJobs();
    $processed = false;

    foreach ($jobs as $jobId => $job) {
        if (($job['type'] ?? '') !== 'cms_news_refresh') continue;
        if (($job['status'] ?? '') !== 'running') continue;

        $d = $job['data'] ?? [];
        $owner   = $d['owner']    ?? '';
        $repo    = $d['repo']     ?? '';
        $branch  = $d['branch']   ?? '';
        $dir     = $d['content_dir'] ?? '';

        // ===========================================================
        // 戦略 A: GraphQL 一括取得 (件数に依らず ~1〜2秒)
        // ===========================================================
        // ジョブを開始したばかりで pending_files の中身に依存しない場合
        // (= 新方式) は GraphQL で全件一発取得 → 完了。
        // 旧方式のジョブとの後方互換のため、graphql_disabled フラグで明示OFFにもできる。
        if (empty($d['graphql_disabled'])) {
            $r = githubListContentsWithBodies($owner, $repo, $dir, $branch);
            if ($r['ok']) {
                $arts = [];
                foreach ($r['items'] as $item) {
                    $p = cmsParseMd($item['text']);
                    $arts[] = array_merge(
                        ['id' => preg_replace('/\.md$/', '', $item['name']), 'sha' => $item['sha']],
                        $p['frontmatter']
                    );
                }
                usort($arts, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
                cmsListCacheSave($owner, $repo, $branch, $dir, $arts);

                $cnt = count($arts);
                refreshUpdateJob($jobId, [
                    'status'       => 'completed',
                    'completed_at' => time(),
                    'progress'     => $cnt,
                    'total'        => $cnt,
                    'message'      => "{$cnt}件の最新化が完了しました (GraphQL一括)",
                    'result'       => ['count' => $cnt, 'method' => 'graphql'],
                ]);
                echo json_encode([
                    'success' => true, 'completed' => true, 'job_id' => $jobId,
                    'count' => $cnt, 'method' => 'graphql',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            // GraphQL 失敗時はチャンク方式にフォールバック (下に続く)
            // 一度フラグを立てて、以降のpoll では REST 経路を使う
            $d['graphql_disabled'] = true;
            $d['graphql_error'] = $r['error'];

            // pending_files がまだ無ければ REST list を取得して構築
            if (empty($d['pending_files'])) {
                $listR = githubListContents($owner, $repo, $dir, $branch);
                if ($listR['ok']) {
                    $pending = [];
                    foreach ($listR['items'] as $item) {
                        if (($item['type'] ?? '') !== 'file') continue;
                        if (!preg_match('/\.md$/', $item['name'] ?? '')) continue;
                        $pending[] = [
                            'path' => $item['path'] ?? '',
                            'name' => $item['name'] ?? '',
                            'sha'  => $item['sha']  ?? '',
                        ];
                    }
                    $d['pending_files'] = $pending;
                    $d['processed_articles'] = $d['processed_articles'] ?? [];
                }
            }
            // フォールバック準備完了。今回はここで終了、次回 poll でチャンク処理
            refreshUpdateJob($jobId, [
                'data' => $d,
                'message' => 'GraphQL 失敗。REST フォールバックへ切替',
                'total' => count($d['pending_files'] ?? []),
            ]);
            echo json_encode([
                'success' => true, 'processed' => true,
                'job_id' => $jobId, 'fallback' => true, 'error' => $r['error'],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ===========================================================
        // 戦略 B (フォールバック): チャンク式 REST (N+1)
        // ===========================================================
        $pending = $d['pending_files'] ?? [];
        $done    = $d['processed_articles'] ?? [];
        $total   = $job['total'] ?? count($pending) + count($done);

        if (empty($pending)) {
            usort($done, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
            cmsListCacheSave($owner, $repo, $branch, $dir, $done);
            refreshUpdateJob($jobId, [
                'status' => 'completed', 'completed_at' => time(),
                'progress' => $total, 'message' => "{$total}件の最新化が完了しました (REST)",
                'result' => ['count' => $total, 'method' => 'rest'],
            ]);
            echo json_encode(['success' => true, 'completed' => true, 'job_id' => $jobId], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $chunk = array_splice($pending, 0, CHUNK_SIZE);
        foreach ($chunk as $f) {
            $path = $f['path'] ?? '';
            if ($path === '') continue;
            $g = githubGetFile($owner, $repo, $path, $branch);
            if (!$g['ok']) continue;
            $p = cmsParseMd($g['content']);
            $done[] = array_merge(
                ['id' => preg_replace('/\.md$/', '', $f['name']), 'sha' => $f['sha']],
                $p['frontmatter']
            );
        }

        $progress = count($done);
        $d['pending_files']      = $pending;
        $d['processed_articles'] = $done;
        refreshUpdateJob($jobId, [
            'progress' => $progress,
            'message'  => "{$progress}/{$total} 件処理中... (REST)",
            'data'     => $d,
        ]);

        echo json_encode([
            'success' => true, 'processed' => true,
            'job_id' => $jobId, 'progress' => $progress, 'total' => $total,
            'remaining' => count($pending), 'method' => 'rest',
        ], JSON_UNESCAPED_UNICODE);
        $processed = true;
        break;
    }

    if (!$processed) {
        echo json_encode(['success' => true, 'processed' => false, 'message' => 'No pending refresh jobs'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ── POST ?action=start: ジョブ開始 ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$action = $_GET['action'] ?? 'start';
if ($action !== 'start') {
    errorResponse('不正なアクション', 400);
}

// CMS 設定読み込み
$config = getCmsConfig();
if (!cmsConfigIsReady($config)) {
    errorResponse('CMS設定が未完了です。設定 > HP更新 設定 で登録してください', 503);
}

[$owner, $repo] = array_pad(explode('/', $config['github_repo'], 2), 2, '');
$branch = $config['github_branch'];
$dir    = trim($config['content_dir'], '/');

// 同じターゲットに対する running ジョブが既にあれば、それを返す (二重起動防止)
$jobs = refreshLoadJobs();
foreach ($jobs as $existing) {
    if (($existing['type'] ?? '') !== 'cms_news_refresh') continue;
    if (($existing['status'] ?? '') !== 'running') continue;
    $d = $existing['data'] ?? [];
    if (($d['owner'] ?? '') === $owner
        && ($d['repo'] ?? '') === $repo
        && ($d['branch'] ?? '') === $branch
        && ($d['content_dir'] ?? '') === $dir) {
        successResponse(['job_id' => $existing['id'], 'reused' => true], '既に最新化処理が実行中です');
    }
}

// GraphQL 一括取得方式が標準: ファイル一覧の事前取得は不要 (process で一発取得)
// 古いジョブをクリーンアップ (24時間以上前)
$cutoff = time() - 86400;
$jobs = array_filter($jobs, fn($j) => ($j['created_at'] ?? 0) > $cutoff);

$jobId = uniqid('cms_refresh_', true);
$jobs[$jobId] = [
    'id'          => $jobId,
    'type'        => 'cms_news_refresh',
    'description' => 'HP更新お知らせ一覧の最新化',
    'status'      => 'running',
    'progress'    => 0,
    'total'       => 0, // GraphQL 取得後に確定
    'message'     => 'GitHubから一括取得しています...',
    'process_url' => '/api/cms/cms-news-refresh.php?action=process',
    'created_at'  => time(),
    'data' => [
        'owner'       => $owner,
        'repo'        => $repo,
        'branch'      => $branch,
        'content_dir' => $dir,
        // pending_files は不要 (GraphQL一発)。フォールバック時のみ process 内で構築される
    ],
];

refreshSaveJobs($jobs);

successResponse([
    'job_id' => $jobId,
], "最新化を開始しました。別のページに移動しても処理は続行されます。");
