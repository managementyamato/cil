<?php
/**
 * HP更新 CMS (お知らせ管理) - GitHub Contents API 版
 *
 * ya-corporate-site (Astro) のニュース記事を GitHub Contents API で
 * 直接編集・コミット・push する。サーバー側に git clone や proc_open は不要。
 * 設定は pages/cms-settings.php で行う (config/cms-config.json)。
 */

require_once '../api/auth.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

require_once '../api/cms/cms-config.php';
require_once '../api/cms/github-api.php';

// ── ユーティリティ ────────────────────────────────────────────────
// 注: cmsParseMd / cmsStringifyMd は api/cms/github-api.php に共通定義
function cmsH($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function cmsSafeId($id) { return preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$id); }

function cmsJsonRes($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 設定読み込み（毎リクエスト）
$config = getCmsConfig();
$ready  = cmsConfigIsReady($config);

if ($ready) {
    [$OWNER, $REPO] = array_pad(explode('/', $config['github_repo'], 2), 2, '');
    $BRANCH      = $config['github_branch'];
    $CONTENT_DIR = trim($config['content_dir'], '/');
    $CATEGORIES  = $config['categories'];
    $COMMITTER   = ['name' => $config['committer_name'], 'email' => $config['committer_email']];
} else {
    $OWNER = $REPO = $BRANCH = $CONTENT_DIR = '';
    $CATEGORIES = $config['categories'] ?? cmsDefaultConfig()['categories'];
    $COMMITTER  = null;
}

// ── API アクション処理 ────────────────────────────────────────────
$action = $_GET['action'] ?? '';

if ($action) {
    // 設定未完了なら何も動かない
    if (!$ready) {
        cmsJsonRes(['error' => 'CMS設定が未完了です。設定 > HP更新 設定 で登録してください'], 503);
    }

    // create/update/delete は POST + CSRF
    if (in_array($action, ['create', 'update', 'delete'], true)) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            cmsJsonRes(['error' => 'POST メソッドが必要です'], 405);
        }
        if (empty($_POST['csrf_token']) && empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $raw = file_get_contents('php://input');
            $d   = json_decode($raw, true);
            if (is_array($d) && !empty($d['csrf_token'])) {
                $_POST['csrf_token'] = $d['csrf_token'];
                $GLOBALS['__cms_json_body'] = $d;
            }
        }
        verifyCsrfToken();
    }

    switch ($action) {

        case 'list': {
            // ?fresh=1: 同期実行 (CLI/特殊用途用)。通常UIからは使わない。
            // 通常: キャッシュHITなら即返答、MISS時は空配列+バックグラウンドジョブ自動起動。
            //       これによりページ読み込みがブロックされない。
            if (empty($_GET['fresh'])) {
                $cached = cmsListCacheLoad($OWNER, $REPO, $BRANCH, $CONTENT_DIR);
                if ($cached !== null) {
                    header('X-Cms-Cache: HIT');
                    cmsJsonRes($cached);
                }

                // キャッシュMISS → 既に running ジョブがあれば再利用、なければ起動
                $jobFile = dirname(__DIR__) . '/data/background-jobs.json';
                $jobs = [];
                if (file_exists($jobFile)) {
                    $raw = @file_get_contents($jobFile);
                    if ($raw) $jobs = json_decode($raw, true) ?: [];
                }
                $hasRunning = false;
                foreach ($jobs as $j) {
                    if (($j['type'] ?? '') !== 'cms_news_refresh') continue;
                    if (($j['status'] ?? '') !== 'running') continue;
                    if (!empty($j['dismissed'])) continue; // dismiss されたジョブは処理対象外なので再起動可
                    $d = $j['data'] ?? [];
                    if (($d['owner'] ?? '') === $OWNER
                        && ($d['repo'] ?? '') === $REPO
                        && ($d['branch'] ?? '') === $BRANCH
                        && ($d['content_dir'] ?? '') === $CONTENT_DIR) {
                        $hasRunning = true;
                        break;
                    }
                }
                if (!$hasRunning) {
                    // GraphQL 一括取得方式: ファイル一覧の事前取得は不要 (process で一発)
                    $cutoff = time() - 86400;
                    $jobs = array_filter($jobs, fn($j) => ($j['created_at'] ?? 0) > $cutoff);
                    $jobId = uniqid('cms_refresh_', true);
                    $jobs[$jobId] = [
                        'id'          => $jobId,
                        'type'        => 'cms_news_refresh',
                        'description' => 'HP更新お知らせ一覧の初回読み込み',
                        'status'      => 'running',
                        'progress'    => 0,
                        'total'       => 0, // GraphQL 取得後に確定
                        'message'     => '初回読み込み中... GitHubから一括取得',
                        'process_url' => '/api/cms/cms-news-refresh.php?action=process',
                        'created_at'  => time(),
                        'data' => [
                            'owner' => $OWNER, 'repo' => $REPO, 'branch' => $BRANCH, 'content_dir' => $CONTENT_DIR,
                        ],
                    ];
                    @file_put_contents($jobFile, json_encode($jobs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
                }
                header('X-Cms-Cache: MISS-ASYNC');
                cmsJsonRes([]); // 空配列。フロント側で「初回読み込み中」表示＆ジョブ完了監視
            }

            // ?fresh=1: 同期実行 (フォールバック)
            $r = githubListContents($OWNER, $REPO, $CONTENT_DIR, $BRANCH);
            if (!$r['ok']) cmsJsonRes(['error' => $r['error']], 502);
            $arts = [];
            foreach ($r['items'] as $item) {
                if (($item['type'] ?? '') !== 'file') continue;
                if (!preg_match('/\.md$/', $item['name'])) continue;
                $g = githubGetFile($OWNER, $REPO, $item['path'], $BRANCH);
                if (!$g['ok']) continue;
                $p = cmsParseMd($g['content']);
                $arts[] = array_merge(
                    ['id' => preg_replace('/\.md$/', '', $item['name']), 'sha' => $item['sha'] ?? ''],
                    $p['frontmatter']
                );
            }
            usort($arts, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
            cmsListCacheSave($OWNER, $REPO, $BRANCH, $CONTENT_DIR, $arts);
            header('X-Cms-Cache: MISS-SYNC');
            cmsJsonRes($arts);
        }

        case 'get': {
            $id = cmsSafeId($_GET['id'] ?? '');
            if ($id === '') cmsJsonRes(['error' => '不正なID'], 400);
            $path = $CONTENT_DIR . '/' . $id . '.md';
            $g    = githubGetFile($OWNER, $REPO, $path, $BRANCH);
            if (!$g['ok']) cmsJsonRes(['error' => $g['error']], $g['error'] === 'ファイルが見つかりません' ? 404 : 502);
            $p = cmsParseMd($g['content']);
            cmsJsonRes(array_merge(['id' => $id, 'sha' => $g['sha']], $p['frontmatter'], ['body' => $p['body']]));
        }

        case 'create': {
            $d  = $GLOBALS['__cms_json_body'] ?? (json_decode(file_get_contents('php://input'), true) ?: $_POST);
            $id = cmsSafeId($d['id'] ?? '');
            if (!$id || empty($d['title']) || empty($d['date'])) {
                cmsJsonRes(['error' => 'ID・タイトル・日付は必須です'], 400);
            }
            $path = $CONTENT_DIR . '/' . $id . '.md';

            // 既存チェック
            $existing = githubGetFile($OWNER, $REPO, $path, $BRANCH);
            if ($existing['ok']) cmsJsonRes(['error' => '同じIDの記事が既に存在します'], 409);

            $fm  = [
                'title'    => $d['title'],
                'date'     => $d['date'],
                'category' => $d['category'] ?? $CATEGORIES[0],
            ];
            $content = cmsStringifyMd($fm, $d['body'] ?? '');
            $msg     = 'お知らせ追加: ' . $d['title'];
            $r = githubPutFile($OWNER, $REPO, $path, $BRANCH, $content, $msg, '', $COMMITTER);
            if (!$r['ok']) cmsJsonRes(['error' => 'GitHub保存失敗: ' . $r['error']], 502);

            cmsListCacheInvalidate($OWNER, $REPO, $BRANCH, $CONTENT_DIR);
            cmsJsonRes(['success' => true, 'id' => $id, 'commit' => $r['commit']['sha'] ?? null]);
        }

        case 'update': {
            $id = cmsSafeId($_GET['id'] ?? '');
            if ($id === '') cmsJsonRes(['error' => '不正なID'], 400);
            $d = $GLOBALS['__cms_json_body'] ?? (json_decode(file_get_contents('php://input'), true) ?: $_POST);
            if (empty($d['title']) || empty($d['date'])) {
                cmsJsonRes(['error' => 'タイトル・日付は必須です'], 400);
            }
            $path = $CONTENT_DIR . '/' . $id . '.md';

            // 既存ファイルの sha を取得（更新には必須）
            $existing = githubGetFile($OWNER, $REPO, $path, $BRANCH);
            if (!$existing['ok']) cmsJsonRes(['error' => '記事が見つかりません'], 404);

            $fm = [
                'title'    => $d['title'],
                'date'     => $d['date'],
                'category' => $d['category'] ?? $CATEGORIES[0],
            ];
            $content = cmsStringifyMd($fm, $d['body'] ?? '');
            $msg     = 'お知らせ更新: ' . $d['title'];
            $r = githubPutFile($OWNER, $REPO, $path, $BRANCH, $content, $msg, $existing['sha'], $COMMITTER);
            if (!$r['ok']) cmsJsonRes(['error' => 'GitHub保存失敗: ' . $r['error']], 502);

            cmsListCacheInvalidate($OWNER, $REPO, $BRANCH, $CONTENT_DIR);
            cmsJsonRes(['success' => true, 'commit' => $r['commit']['sha'] ?? null]);
        }

        case 'delete': {
            $id = cmsSafeId($_GET['id'] ?? '');
            if ($id === '') cmsJsonRes(['error' => '不正なID'], 400);
            $path = $CONTENT_DIR . '/' . $id . '.md';

            $existing = githubGetFile($OWNER, $REPO, $path, $BRANCH);
            if (!$existing['ok']) cmsJsonRes(['error' => '記事が見つかりません'], 404);

            $p     = cmsParseMd($existing['content']);
            $title = $p['frontmatter']['title'] ?? $id;
            $msg   = 'お知らせ削除: ' . $title;
            $r = githubDeleteFile($OWNER, $REPO, $path, $BRANCH, $msg, $existing['sha'], $COMMITTER);
            if (!$r['ok']) cmsJsonRes(['error' => 'GitHub削除失敗: ' . $r['error']], 502);

            cmsListCacheInvalidate($OWNER, $REPO, $BRANCH, $CONTENT_DIR);
            cmsJsonRes(['success' => true, 'commit' => $r['commit']['sha'] ?? null]);
        }

        default:
            cmsJsonRes(['error' => '不正なアクション'], 400);
    }
}

// ── 通常のページレンダリング ──────────────────────────────────────
$catOpts = implode('', array_map(fn($c) => '<option value="' . cmsH($c) . '">' . cmsH($c) . '</option>', $CATEGORIES));
// .htaccess の 301リダイレクト (.php → 拡張子なし) で POST が GET に変換される問題回避のため
// 拡張子を取り除いた "きれいなURL" を JS に渡す。Apache 内部リライトで .php に解決される
$self    = cmsH(preg_replace('/\.php$/', '', $_SERVER['PHP_SELF']));
$csrf    = generateCsrfToken();

require_once '../functions/header.php';
?>

<div class="page-container">
    <h1 class="page-title">HP更新（お知らせ管理）</h1>

    <?php if (!$ready): ?>
    <div class="card" style="background:#fff3e0;border:1px solid #ffcc80;margin-bottom:1rem;">
        <h2 style="color:#e65100;margin-bottom:0.5rem;font-size:1rem;">CMS設定が未完了です</h2>
        <p style="font-size:0.9rem;color:#666;margin-bottom:0.75rem;">
            GitHub の PAT・リポジトリ等の登録が必要です。設定画面から登録してください。
        </p>
        <a href="cms-settings.php" class="btn btn-primary">HP更新 設定を開く</a>
    </div>
    <?php else: ?>

    <div id="view-list">
        <div class="top-bar" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <div id="count-label" style="font-size:14px;color:#666;"></div>
            <div style="display:flex;gap:0.5rem;">
                <button type="button" class="btn btn-secondary btn-sm" data-action="cms-refresh" id="cms-refresh-btn" title="GitHubから最新を再取得">最新化</button>
                <a href="cms-templates.php" class="btn btn-secondary btn-sm">テンプレート管理</a>
                <a href="cms-settings.php" class="btn btn-secondary btn-sm">設定</a>
                <button type="button" class="btn btn-primary" data-action="cms-new">新規作成</button>
            </div>
        </div>
        <div id="alert-list" class="alert" style="display:none;"></div>
        <div class="card">
            <h2 style="font-size:1rem;font-weight:600;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:1px solid #eee;color:#1a3a5c;">
                お知らせ一覧 <span style="font-weight:400;font-size:0.85rem;color:#888;">(<?= cmsH($config['github_repo']) ?>@<?= cmsH($config['github_branch']) ?>)</span>
            </h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:110px">日付</th>
                        <th>タイトル</th>
                        <th style="width:120px">カテゴリ</th>
                        <th style="width:180px">ファイルID</th>
                        <th style="width:140px">操作</th>
                    </tr>
                </thead>
                <tbody id="news-table-body">
                    <tr><td colspan="5" style="text-align:center;color:#999;padding:2rem;">読み込み中...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="view-form" style="display:none;">
        <div class="top-bar" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <button type="button" class="btn btn-secondary" data-action="cms-list">一覧に戻る</button>
            <div></div>
        </div>
        <div id="alert-form" class="alert" style="display:none;"></div>
        <div class="card">
            <h2 id="cms-form-title" style="font-size:1rem;font-weight:600;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:1px solid #eee;color:#1a3a5c;">新規作成</h2>
            <form id="cms-news-form">
                <input type="hidden" name="csrf_token" value="<?= cmsH($csrf) ?>">

                <div class="form-group" style="margin-bottom:1rem;padding:0.75rem;background:#f7f9fc;border:1px dashed #cbd5e0;border-radius:6px;">
                    <label class="form-label" style="margin-bottom:0.4rem;">テンプレートから入力</label>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <select class="form-input" id="cms-tpl-select" style="flex:1;">
                            <option value="">(テンプレートを使わない)</option>
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" data-action="cms-apply-tpl">適用</button>
                        <a href="cms-templates.php" class="btn btn-secondary btn-sm">管理</a>
                    </div>
                    <p style="font-size:11px;color:#888;margin-top:6px;">テンプレートを選んで「適用」を押すと本文(と必要に応じてカテゴリ/タイトル)が上書きされます</p>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label">ファイルID <span style="color:#dc3545;">*</span></label>
                        <input type="text" class="form-input" id="cms-f-id" placeholder="例: 2026-natsu" required>
                        <p style="font-size:11px;color:#888;margin-top:4px;">英数字・ハイフン・アンダースコアのみ</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">カテゴリ <span style="color:#dc3545;">*</span></label>
                        <select class="form-input" id="cms-f-category"><?= $catOpts ?></select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">タイトル <span style="color:#dc3545;">*</span></label>
                    <input type="text" class="form-input" id="cms-f-title" placeholder="例: 夏季休業日のお知らせ" required>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label">日付 <span style="color:#dc3545;">*</span></label>
                        <input type="date" class="form-input" id="cms-f-date" required>
                    </div>
                    <div></div>
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">本文（Markdown使用可）</label>
                    <textarea class="form-input" id="cms-f-body" style="min-height:300px;font-family:'Courier New',monospace;font-size:13px;" placeholder="本文を入力してください。&#10;**太字**、*斜体* など Markdown が使えます。"></textarea>
                </div>
                <div style="display:flex;gap:0.5rem;">
                    <button type="submit" class="btn btn-primary" id="cms-submit-btn">保存して公開</button>
                    <button type="button" class="btn btn-secondary" data-action="cms-list">キャンセル</button>
                </div>
            </form>
        </div>
    </div>

    <?php endif; ?>
</div>

<!-- 削除確認モーダル -->
<div class="modal" id="cmsConfirmModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header"><h3>削除の確認</h3></div>
        <div class="modal-body"><p id="cms-confirm-msg"></p></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-action="cms-cancel-delete">キャンセル</button>
            <button type="button" class="btn btn-danger" id="cms-confirm-ok-btn">削除する</button>
        </div>
    </div>
</div>

<!-- 公開処理中モーダル -->
<div class="modal" id="cmsPublishModal">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header"><h3 id="cms-publish-title">公開処理中</h3></div>
        <div class="modal-body">
            <p id="cms-publish-msg" style="font-size:14px;color:#555;">
                <span class="cms-spinner"></span>GitHub に保存しています...
            </p>
            <p id="cms-publish-commit" style="font-size:12px;color:#888;margin-top:0.5rem;font-family:monospace;"></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-action="cms-close-publish" id="cms-publish-ok-btn" style="display:none;">閉じる</button>
        </div>
    </div>
</div>

<style<?= function_exists('nonceAttr') ? nonceAttr() : '' ?>>
.cms-spinner { display: inline-block; width: 12px; height: 12px; border: 2px solid #e8a020; border-top-color: transparent; border-radius: 50%; animation: cms-spin .7s linear infinite; margin-right: 6px; vertical-align: middle; }
@keyframes cms-spin { to { transform: rotate(360deg); } }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px 16px; border-radius: 5px; font-size: 14px; margin-bottom: 16px; }
.alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px 16px; border-radius: 5px; font-size: 14px; margin-bottom: 16px; }
</style>

<?php if ($ready): ?>
<script<?= function_exists('nonceAttr') ? nonceAttr() : '' ?>>
(function() {
    const CMS_API  = <?= json_encode($self, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const TPL_API  = 'cms-templates.php';
    const CMS_CSRF = <?= json_encode($csrf) ?>;
    let cmsEditingId = null;
    let pendingDelete = null;
    let templatesCache = []; // 編集フォーム表示時に1回ロード→キャッシュ

    async function loadTemplates() {
        try {
            const res = await fetch(TPL_API + '?action=list');
            const list = await res.json();
            if (Array.isArray(list)) templatesCache = list;
        } catch (err) {
            templatesCache = [];
        }
        renderTemplateOptions();
    }

    function renderTemplateOptions() {
        const sel = document.getElementById('cms-tpl-select');
        if (!sel) return;
        sel.replaceChildren();
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = templatesCache.length
            ? '(テンプレートを選択...)'
            : '(テンプレート未登録 - 「管理」から作成)';
        sel.appendChild(placeholder);
        for (const t of templatesCache) {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name + (t.description ? ' — ' + t.description : '');
            sel.appendChild(opt);
        }
    }

    function applySelectedTemplate() {
        const sel = document.getElementById('cms-tpl-select');
        const id  = sel.value;
        if (!id) { showAlert('form', 'error', 'テンプレートを選択してください'); return; }
        const t = templatesCache.find(x => x.id === id);
        if (!t) { showAlert('form', 'error', 'テンプレートが見つかりません'); return; }

        // 本文は無条件で上書き
        document.getElementById('cms-f-body').value = t.body || '';
        // カテゴリは既定値が指定されていれば上書き
        if (t.category) {
            const catEl = document.getElementById('cms-f-category');
            const opt = Array.from(catEl.options).find(o => o.value === t.category);
            if (opt) catEl.value = t.category;
        }
        // タイトル雛形はタイトルが空のときだけ埋める
        if (t.title_hint) {
            const titleEl = document.getElementById('cms-f-title');
            if (!titleEl.value.trim()) titleEl.value = t.title_hint;
        }
        showAlert('form', 'success', 'テンプレート「' + t.name + '」を適用しました');
    }

    function showAlert(zone, type, msg) {
        const el = document.getElementById('alert-' + zone);
        if (!el) return;
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 5000);
    }

    // 直近表示中の一覧ハッシュ (差分検出用)。同じデータなら再描画スキップ
    let currentListHash = '';
    function hashList(arts) {
        // 並び順込み・id/sha込みで簡易ハッシュ
        if (!Array.isArray(arts)) return '';
        return arts.map(a => (a.id || '') + ':' + (a.sha || '') + ':' + (a.date || '') + ':' + (a.title || '') + ':' + (a.category || '')).join('|');
    }

    function renderList(arts) {
        const tbody = document.getElementById('news-table-body');
        const countEl = document.getElementById('count-label');

        countEl.textContent = '全 ' + arts.length + ' 件';
        tbody.replaceChildren();
        if (!arts.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 5;
            td.style.cssText = 'text-align:center;color:#999;padding:2rem;';
            td.textContent = '記事がありません';
            tr.appendChild(td);
            tbody.appendChild(tr);
            return;
        }
        for (const a of arts) {
            const tr = document.createElement('tr');

            const tdDate = document.createElement('td');
            tdDate.textContent = a.date || '-';
            tr.appendChild(tdDate);

            const tdTitle = document.createElement('td');
            tdTitle.textContent = a.title || '-';
            tr.appendChild(tdTitle);

            const tdCat = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = 'badge';
            badge.textContent = a.category || '-';
            tdCat.appendChild(badge);
            tr.appendChild(tdCat);

            const tdId = document.createElement('td');
            tdId.style.cssText = 'font-family:monospace;font-size:12px;color:#666;';
            tdId.textContent = a.id + '.md';
            tr.appendChild(tdId);

            const tdOps = document.createElement('td');
            const opsWrap = document.createElement('div');
            opsWrap.style.cssText = 'display:flex;gap:6px;';

            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn btn-sm btn-secondary';
            editBtn.textContent = '編集';
            editBtn.dataset.action = 'cms-edit';
            editBtn.dataset.id = a.id;
            opsWrap.appendChild(editBtn);

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'btn btn-sm btn-danger';
            delBtn.textContent = '削除';
            delBtn.dataset.action = 'cms-delete';
            delBtn.dataset.id = a.id;
            delBtn.dataset.title = a.title || a.id;
            opsWrap.appendChild(delBtn);

            tdOps.appendChild(opsWrap);
            tr.appendChild(tdOps);

            tbody.appendChild(tr);
        }
    }

    function renderError(msg) {
        const tbody = document.getElementById('news-table-body');
        tbody.replaceChildren();
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 5;
        td.style.cssText = 'text-align:center;color:#c00;padding:2rem;';
        td.textContent = msg;
        tr.appendChild(td);
        tbody.appendChild(tr);
    }

    function renderLoadingPlaceholder(message) {
        const tbody = document.getElementById('news-table-body');
        const countEl = document.getElementById('count-label');
        tbody.replaceChildren();
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 5;
        td.style.cssText = 'text-align:center;color:#888;padding:2.5rem 1rem;';
        const spinner = document.createElement('span');
        spinner.className = 'cms-spinner';
        td.appendChild(spinner);
        td.appendChild(document.createTextNode(' ' + message));
        tr.appendChild(td);
        tbody.appendChild(tr);
        countEl.textContent = '';
    }

    function setStatusBadge(state, text) {
        // state: 'loading' | 'success' | 'error' | 'cached' | null
        const countEl = document.getElementById('count-label');
        if (!countEl) return;
        let badge = document.getElementById('cms-status');
        if (!badge) {
            badge = document.createElement('span');
            badge.id = 'cms-status';
            badge.style.cssText = 'margin-left:0.5rem;font-size:11px;padding:2px 6px;border-radius:3px;vertical-align:middle;';
            countEl.appendChild(badge);
        }
        const palette = {
            loading: { bg: '#fff3cd', fg: '#856404' },
            success: { bg: '#d4edda', fg: '#155724' },
            error:   { bg: '#f8d7da', fg: '#721c24' },
            cached:  { bg: '#e2e3e5', fg: '#383d41' },
        };
        if (!state) { badge.style.display = 'none'; return; }
        badge.textContent = text;
        badge.style.background = palette[state].bg;
        badge.style.color = palette[state].fg;
        badge.style.display = '';
    }

    /**
     * 一覧を取得・表示する。
     * - キャッシュHIT: 即表示
     * - キャッシュMISS: サーバ側で自動的にバックグラウンドジョブ起動 (空配列を即返答)
     *                  フロントは「初回読み込み中」を表示しジョブ完了を監視
     */
    async function loadList() {
        try {
            const res  = await fetch(CMS_API + '?action=list');
            const arts = await res.json();
            if (arts && arts.error) {
                renderError(arts.error);
                setStatusBadge('error', 'エラー');
                return;
            }
            const cacheState = res.headers.get('X-Cms-Cache') || '';

            if (cacheState === 'MISS-ASYNC') {
                // 初回ロード: バックグラウンドで一覧取得中。完了監視に切り替え
                renderLoadingPlaceholder('初回読み込み中... 右下に進捗が表示されます');
                setStatusBadge('loading', '初回読み込み中 (バックグラウンド)');
                refreshStartedAt = Math.floor(Date.now() / 1000);
                if (typeof window.checkBackgroundJobs === 'function') {
                    window.checkBackgroundJobs();
                }
                watchRefreshCompletion();
                return;
            }

            currentListHash = hashList(arts);
            renderList(arts);

            if (cacheState === 'HIT') {
                setStatusBadge('cached', 'キャッシュ表示');
                setTimeout(() => setStatusBadge(null), 2400);
            } else {
                setStatusBadge(null);
            }
        } catch (err) {
            renderError('一覧の取得に失敗しました: ' + err.message);
            setStatusBadge('error', 'エラー');
        }
    }

    /**
     * 「最新化」ボタン: バックグラウンドジョブを起動して即時返答。
     * php -S 単スレッドでも詰まらない。完了後は自動でリスト再読み込み。
     */
    let refreshWatchTimer = null;
    let refreshStartedAt = 0;
    async function startRefreshJob() {
        const btn = document.getElementById('cms-refresh-btn');
        try {
            if (btn) btn.disabled = true;
            setStatusBadge('loading', '最新化を開始中...');

            const res = await fetch('/api/cms/cms-news-refresh.php?action=start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CMS_CSRF },
                body: JSON.stringify({ csrf_token: CMS_CSRF }),
            });
            const data = await res.json();
            if (!data.success) {
                setStatusBadge('error', data.error || '起動に失敗');
                return;
            }
            refreshStartedAt = Math.floor(Date.now() / 1000);
            setStatusBadge('loading', '最新化中 (バックグラウンド)');
            // background-jobs.js (floating notification) を即時起動
            if (typeof window.checkBackgroundJobs === 'function') {
                window.checkBackgroundJobs();
            }
            // 進捗監視: js/background-jobs.js が描画する floating banner と並行して
            //   完了を検知したらリスト再読み込み
            watchRefreshCompletion();
        } catch (err) {
            setStatusBadge('error', '起動エラー: ' + err.message);
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    function watchRefreshCompletion() {
        if (refreshWatchTimer) clearInterval(refreshWatchTimer);
        refreshWatchTimer = setInterval(async () => {
            try {
                const res = await fetch('/api/background-job.php?action=active');
                const data = await res.json();
                const jobs = data.jobs || {};
                let recentComplete = null;
                for (const j of Object.values(jobs)) {
                    if (j.type !== 'cms_news_refresh') continue;
                    if (j.status === 'completed' && (j.completed_at || 0) >= refreshStartedAt) {
                        recentComplete = j;
                        break;
                    }
                }
                if (recentComplete) {
                    clearInterval(refreshWatchTimer);
                    refreshWatchTimer = null;
                    setStatusBadge('success', '最新化完了 - 再読み込み中');
                    await loadList();
                    setTimeout(() => setStatusBadge(null), 1500);
                }
            } catch (err) {
                // 失敗時は黙って継続 (ネットワーク瞬断等)
            }
        }, 2000);
    }

    function showList() {
        document.getElementById('view-list').style.display = 'block';
        document.getElementById('view-form').style.display = 'none';
        loadList();
    }

    function showNew() {
        cmsEditingId = null;
        document.getElementById('cms-form-title').textContent = '新規作成';
        const idEl = document.getElementById('cms-f-id');
        idEl.disabled = false;
        idEl.value = '';
        document.getElementById('cms-f-title').value = '';
        document.getElementById('cms-f-date').value = new Date().toISOString().slice(0, 10);
        document.getElementById('cms-f-category').selectedIndex = 0;
        document.getElementById('cms-f-body').value = '';
        document.getElementById('cms-tpl-select').value = '';
        document.getElementById('cms-submit-btn').textContent = '保存して公開';
        document.getElementById('view-list').style.display = 'none';
        document.getElementById('view-form').style.display = 'block';
        loadTemplates();
    }

    async function showEdit(id) {
        try {
            const res = await fetch(CMS_API + '?action=get&id=' + encodeURIComponent(id));
            if (!res.ok) { showAlert('list', 'error', '記事の読み込みに失敗しました'); return; }
            const a = await res.json();
            if (a.error) { showAlert('list', 'error', a.error); return; }
            cmsEditingId = id;
            document.getElementById('cms-form-title').textContent = '編集：' + (a.title || id);
            const idEl = document.getElementById('cms-f-id');
            idEl.value = id;
            idEl.disabled = true;
            document.getElementById('cms-f-title').value = a.title || '';
            document.getElementById('cms-f-date').value  = a.date  || '';
            document.getElementById('cms-f-category').value = a.category || '';
            document.getElementById('cms-f-body').value  = a.body  || '';
            document.getElementById('cms-submit-btn').textContent = '保存して公開';
            document.getElementById('cms-tpl-select').value = '';
            document.getElementById('view-list').style.display = 'none';
            document.getElementById('view-form').style.display = 'block';
            loadTemplates();
        } catch (err) {
            showAlert('list', 'error', '記事の読み込みに失敗しました: ' + err.message);
        }
    }

    function openPublishModal(message) {
        const msgEl    = document.getElementById('cms-publish-msg');
        const okBtn    = document.getElementById('cms-publish-ok-btn');
        const titleEl  = document.getElementById('cms-publish-title');
        const commitEl = document.getElementById('cms-publish-commit');
        titleEl.textContent  = '公開処理中';
        msgEl.replaceChildren();
        const spinner = document.createElement('span');
        spinner.className = 'cms-spinner';
        msgEl.appendChild(spinner);
        msgEl.appendChild(document.createTextNode(message));
        commitEl.textContent = '';
        okBtn.style.display = 'none';
        openModal('cmsPublishModal');
    }

    function finishPublishModal(ok, message, commitSha) {
        const msgEl    = document.getElementById('cms-publish-msg');
        const okBtn    = document.getElementById('cms-publish-ok-btn');
        const titleEl  = document.getElementById('cms-publish-title');
        const commitEl = document.getElementById('cms-publish-commit');
        titleEl.textContent = ok ? '公開完了' : '公開失敗';
        msgEl.textContent = message;
        msgEl.style.color = ok ? '#155724' : '#c00';
        commitEl.textContent = commitSha ? 'commit: ' + commitSha.slice(0, 7) : '';
        okBtn.style.display = '';
    }

    async function submitForm(e) {
        e.preventDefault();
        const payload = {
            csrf_token: CMS_CSRF,
            id:       document.getElementById('cms-f-id').value.trim(),
            title:    document.getElementById('cms-f-title').value.trim(),
            date:     document.getElementById('cms-f-date').value,
            category: document.getElementById('cms-f-category').value,
            body:     document.getElementById('cms-f-body').value,
        };
        const url = cmsEditingId
            ? CMS_API + '?action=update&id=' + encodeURIComponent(cmsEditingId)
            : CMS_API + '?action=create';
        openPublishModal('GitHub に保存しています...');
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CMS_CSRF },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.error) {
                finishPublishModal(false, data.error, null);
                return;
            }
            finishPublishModal(true, '保存しました。Cloudflare Pages がデプロイを開始します。', data.commit);
            showList();
        } catch (err) {
            finishPublishModal(false, '送信に失敗しました: ' + err.message, null);
        }
    }

    function askDelete(id, title) {
        pendingDelete = { id, title };
        document.getElementById('cms-confirm-msg').textContent =
            '「' + title + '」を削除しますか？この操作は元に戻せません。';
        openModal('cmsConfirmModal');
    }

    async function confirmDelete() {
        if (!pendingDelete) return;
        const { id } = pendingDelete;
        pendingDelete = null;
        closeModal('cmsConfirmModal');
        openPublishModal('GitHub から削除しています...');
        try {
            const res = await fetch(CMS_API + '?action=delete&id=' + encodeURIComponent(id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CMS_CSRF },
                body: JSON.stringify({ csrf_token: CMS_CSRF }),
            });
            const data = await res.json();
            if (data.error) {
                finishPublishModal(false, data.error, null);
                return;
            }
            finishPublishModal(true, '削除しました。Cloudflare Pages がデプロイを開始します。', data.commit);
            loadList();
        } catch (err) {
            finishPublishModal(false, '削除に失敗しました: ' + err.message, null);
        }
    }

    document.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action]');
        if (!target) return;
        switch (target.dataset.action) {
            case 'cms-new':           showNew(); break;
            case 'cms-list':          showList(); break;
            case 'cms-edit':          showEdit(target.dataset.id); break;
            case 'cms-delete':        askDelete(target.dataset.id, target.dataset.title || target.dataset.id); break;
            case 'cms-cancel-delete': pendingDelete = null; closeModal('cmsConfirmModal'); break;
            case 'cms-close-publish': closeModal('cmsPublishModal'); break;
            case 'cms-apply-tpl':     applySelectedTemplate(); break;
            case 'cms-refresh':       startRefreshJob(); break;
        }
    });

    document.getElementById('cms-confirm-ok-btn').addEventListener('click', confirmDelete);
    document.getElementById('cms-news-form').addEventListener('submit', submitForm);

    loadList();
})();
</script>
<?php endif; ?>

<?php require_once '../functions/footer.php'; ?>
