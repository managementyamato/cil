# バックグラウンドジョブ パターン

> 長時間 (1秒超) の処理を、PHP リクエストを詰まらせずに非同期で進めるための設計パターン。
> ローカル開発サーバ (`php -S`) は単スレッドで全リクエストを serialize するため、
> 数秒以上かかる同期処理を入れると別ページへの遷移が詰まる。これを回避するためのパターン。

---

## TL;DR

**「チャンク式ポーリング」** で実現する:
1. **start エンドポイント (POST)**: ジョブを `data/background-jobs.json` に登録、即座に返答
2. **process エンドポイント (GET)**: 保留中ジョブを 1〜数件処理して進捗を更新 (1呼び出し ≤ 1秒)
3. **js/background-jobs.js のグローバルポーラー** が2秒毎に process を叩く
4. **floating notification** が全ページで進捗を表示

各 process は短時間で終わるので、間に別ページへの遷移リクエストが入り込める。

---

## ファイル構造

```
data/background-jobs.json         ← 全ジョブの状態 (job_id をキー)
api/background-job.php             ← GETでアクティブジョブ一覧 / 状態取得
api/<your-feature>-job.php         ← start + process 両方を担当 (例: cms-news-refresh.php)
js/background-jobs.js              ← 全ページ共通のポーラー + floating notification
functions/header.php               ← <div id="backgroundJobsContainer"> をレンダリング (済)
```

---

## ジョブの最小スキーマ

```json
{
  "job_xxx": {
    "id": "job_xxx",
    "type": "cms_news_refresh",
    "description": "HP更新お知らせ一覧の最新化",
    "status": "running" | "completed" | "failed",
    "progress": 12,
    "total": 50,
    "message": "12/50 件処理中...",
    "process_url": "/api/cms/cms-news-refresh.php?action=process",
    "created_at": 1700000000,
    "completed_at": 1700000017,
    "result": { ... },
    "data": {
      "pending_items": [...],
      "processed_items": [...],
      "<context for your worker>": "..."
    }
  }
}
```

**重要なフィールド**:
- `type`: ジョブ識別 (UI 表示・終了検知に使う)
- `status`: `running` のジョブを js/background-jobs.js が拾う
- `process_url`: ポーラーがチャンク処理を呼ぶ URL (新規対応)
- `progress` / `total`: 進捗バー表示
- `data`: ジョブ固有のコンテキスト (pending queue 等)

---

## 実装テンプレ

### 1. start エンドポイント (POST)

```php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => true,
    'allowedMethods' => ['GET', 'POST'],
]);
if (!isAdmin()) errorResponse('権限が必要', 403);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? 'start') === 'start') {
    // 重い前準備は最小限で。ジョブを登録して返す。
    $pendingItems = /* 短時間で取れる初期データ */;

    $jobs = loadJobs();
    // 同じターゲットの running ジョブがあれば再利用 (二重起動防止)
    foreach ($jobs as $j) {
        if ($j['type'] === 'your_job_type' && $j['status'] === 'running' /* + ターゲット一致 */) {
            successResponse(['job_id' => $j['id'], 'reused' => true]);
        }
    }

    $jobId = uniqid('xxx_', true);
    $jobs[$jobId] = [
        'id'          => $jobId,
        'type'        => 'your_job_type',
        'description' => '人間向け説明',
        'status'      => 'running',
        'progress'    => 0,
        'total'       => count($pendingItems),
        'message'     => '処理を開始しています...',
        'process_url' => '/api/your-feature-job.php?action=process',
        'created_at'  => time(),
        'data' => [
            'pending_items'   => $pendingItems,
            'processed_items' => [],
            /* + 必要なコンテキスト */
        ],
    ];
    saveJobs($jobs);
    successResponse(['job_id' => $jobId], '処理を開始しました。別ページへの遷移可能です');
}
```

### 2. process エンドポイント (GET)

```php
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'process') {
    $CHUNK_SIZE = 3;  // 1ポーリングで処理する件数。1〜5 が無難 (UX vs ブロック時間)

    $jobs = loadJobs();
    foreach ($jobs as $jobId => $job) {
        if ($job['type'] !== 'your_job_type') continue;
        if ($job['status'] !== 'running') continue;

        $pending = $job['data']['pending_items'];
        $done    = $job['data']['processed_items'];
        $total   = $job['total'];

        if (empty($pending)) {
            // 完了処理 (キャッシュ書き込み等)
            saveJobs(updateJob($jobs, $jobId, [
                'status'       => 'completed',
                'completed_at' => time(),
                'message'      => '完了しました',
                'progress'     => $total,
                'result'       => /* 完了時データ */,
            ]));
            echo json_encode(['success' => true, 'completed' => true, 'job_id' => $jobId]);
            exit;
        }

        // 1チャンク処理 (重い API 呼び出しは N=CHUNK_SIZE 件分だけ)
        $chunk = array_splice($pending, 0, $CHUNK_SIZE);
        foreach ($chunk as $item) {
            $done[] = /* item を処理した結果 */;
        }

        $progress = count($done);
        saveJobs(updateJob($jobs, $jobId, [
            'progress' => $progress,
            'message'  => "{$progress}/{$total} 処理中...",
            'data'     => [
                'pending_items'   => $pending,
                'processed_items' => $done,
                /* + コンテキスト維持 */
            ],
        ]));

        echo json_encode([
            'success'  => true,
            'processed'=> true,
            'job_id'   => $jobId,
            'progress' => $progress,
            'total'    => $total,
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'processed' => false]);
    exit;
}
```

### 3. フロントエンドからの起動

```html
<button type="button" onclick="startJob()">最新化</button>

<script>
async function startJob() {
    const res = await fetch('/api/your-feature-job.php?action=start', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify({ csrf_token: CSRF_TOKEN }),
    });
    const data = await res.json();
    if (!data.success) { alert(data.error); return; }

    // background-jobs.js を即時起動 (10秒待たずに floating notification を表示)
    if (typeof window.checkBackgroundJobs === 'function') {
        window.checkBackgroundJobs();
    }

    // 完了時にローカル状態を更新したい場合は、別途ポーラー
    const startedAt = Math.floor(Date.now() / 1000);
    const watcher = setInterval(async () => {
        const r = await fetch('/api/background-job.php?action=active');
        const j = (await r.json()).jobs || {};
        for (const x of Object.values(j)) {
            if (x.type === 'your_job_type' && x.status === 'completed' && x.completed_at >= startedAt) {
                clearInterval(watcher);
                // 完了時のローカル処理 (e.g. リスト再読み込み)
            }
        }
    }, 2000);
}
</script>
```

### 4. js/background-jobs.js の自動処理

このグローバルポーラーは何もしなくても以下を自動で行う:
- 2秒毎に `process_url` (各ジョブが持つフィールド) を叩いて process を進める
- 3秒毎に状態を再取得して floating notification を更新
- 完了/失敗時に notification を自動で非表示

`process_url` をジョブに含めるだけで、新しいジョブタイプを js 側のコード変更なしに追加できる。

---

## CHUNK_SIZE の選び方

| 重い処理 1件あたりの所要時間 | 推奨 CHUNK_SIZE | 1チャンクの所要時間 |
|---|---|---|
| ~300ms (GitHub API) | 3 | ~1秒 |
| ~800ms (SaaS API) | 1〜2 | ~1秒 |
| ~100ms (DB クエリ) | 5〜10 | ~1秒 |

目安: **1チャンク = 1秒以内**。これを超えると別ページ遷移が体感で詰まる。

---

## 適用候補のエンドポイント (将来作業)

このパターンに置き換えると体感速度が改善する候補:

| 既存エンドポイント | 重さ | 適用優先度 |
|---|---|---|
| `pages/cms-news.php?action=list` (キャッシュMISS時) | N+1 GitHub API | ★★★ (Phase2でGraphQL化が決まっているのでそちら優先) |
| `api/sync-invoices.php` (MF請求書同期) | 月分のMF API呼び出し | ★★★ |
| `api/sync-partners.php` (MF取引先同期) | 数百件のMF API | ★★ |
| `api/sync-troubles.php` (トラブル同期) | 中程度 | ★ |
| `api/clear-mf-invoices.php` | 大きいDB書き込み | ★ |

リファクタの観点では、`api/<sync>` 系はすでに **start+process 2段化** の素地がある (saveDataにエンティティフィルタを渡せる仕組みも入っている)。

---

## 注意事項

### A. 単スレッド開発サーバでの観察ポイント

`php -S` で動作確認する際は、Chrome DevTools の Network タブで:
- 各 process リクエストが ~1秒で完了していること
- process と他リクエスト (画面遷移等) が交互に処理されていること

長い process が見えたら CHUNK_SIZE を下げる。

### B. ジョブの永続化

`data/background-jobs.json` はサーバ再起動でも保持されるので、長時間ジョブの再開も可能 (process が呼ばれれば続きから処理される)。ただし pending_items がオンメモリではなくこのファイルに永続化されているので、巨大化に注意。1万件超のキューになる場合はDB化を検討。

### C. 認証境界

各 process リクエストにもセッションが必要 (`initApi(['requireAuth' => true])`)。
未ログインユーザーが直接 process を叩くことはできない。ただし誰かのセッションが生きてる間に
誰かが別ブラウザから start するシナリオは想定外なので、必要なら `data` に user_email を入れて
照合するロジックを追加する。

### D. 完了時のフォールバック

万一 js/background-jobs.js のポーラーが止まっても、process は **誰かがリクエストすれば進む**
(=次のリクエスト時に進む)。死んだジョブが残らないよう、24時間以上前のジョブは start 時に
自動クリーンアップしている (`loadJobs() / cleanupOldJobs()`)。

---

## 参照実装

- `api/loans-color.php` — 借入金色付け処理 (Google Sheets API)
- `api/cms/cms-news-refresh.php` — HP更新お知らせ一覧の最新化 (GitHub Contents API) ← この設計のリファレンス
