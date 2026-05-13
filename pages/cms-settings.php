<?php
/**
 * HP更新 (CMS) 設定ページ
 * - GitHub Contents API 用の PAT、リポジトリ、ブランチ等を保存
 * - 「接続テスト」ボタンで PAT/リポジトリの有効性を即時確認
 */
require_once '../api/auth.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

require_once '../api/cms/cms-config.php';
require_once '../api/cms/github-api.php';

$action  = $_GET['action'] ?? '';
$csrf    = generateCsrfToken();
$message = null;
$error   = null;

// ── 接続テスト API（AJAX） ──
if ($action === 'test') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'message' => 'POST が必要です']);
        exit;
    }
    verifyCsrfToken();

    $raw = file_get_contents('php://input');
    $d   = json_decode($raw, true) ?: $_POST;

    // PAT が空なら保存済みのものを使う
    $token = trim((string)($d['github_token'] ?? ''));
    if ($token === '') {
        $cfg   = getCmsConfig();
        $token = $cfg['github_token'] ?? '';
    }
    $repo   = trim((string)($d['github_repo']   ?? ''));
    $branch = trim((string)($d['github_branch'] ?? 'main'));

    $result = githubTestConnection($token, $repo, $branch);
    echo json_encode(['ok' => $result['ok'], 'message' => $result['message']], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 設定保存 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === '') {
    verifyCsrfToken();

    $categoriesRaw = trim((string)($_POST['categories'] ?? ''));
    $categories    = array_filter(array_map('trim', explode("\n", str_replace(',', "\n", $categoriesRaw))), fn($s) => $s !== '');

    try {
        saveCmsConfig([
            'github_token'    => $_POST['github_token']    ?? '',
            'github_repo'     => $_POST['github_repo']     ?? '',
            'github_branch'   => $_POST['github_branch']   ?? '',
            'content_dir'     => $_POST['content_dir']     ?? '',
            'categories'      => $categories,
            'committer_name'  => $_POST['committer_name']  ?? '',
            'committer_email' => $_POST['committer_email'] ?? '',
        ]);
        $_SESSION['cms_settings_msg'] = 'CMS設定を保存しました';
    } catch (Throwable $e) {
        error_log('[cms-settings] save failed: ' . $e->getMessage());
        $_SESSION['cms_settings_err'] = 'CMS設定の保存に失敗: ' . $e->getMessage();
    }
    header('Location: cms-settings');
    exit;
}

$config  = getCmsConfig();
$ready   = cmsConfigIsReady($config);
$message = $_SESSION['cms_settings_msg'] ?? null;
$error   = $_SESSION['cms_settings_err'] ?? null;
unset($_SESSION['cms_settings_msg'], $_SESSION['cms_settings_err']);

$hasToken = !empty($config['github_token']);
$tokenPlaceholder = $hasToken ? '(設定済み — 変更時のみ入力)' : 'ghp_xxxxxxxxxxxxxxxxxxxx';

require_once '../functions/header.php';
?>

<div class="page-container">
    <h1 class="page-title">HP更新 設定</h1>

    <p style="color:#666;font-size:13px;margin-bottom:1rem;">
        ヤマト広告コーポレートサイト (ya-corporate-site) のお知らせ記事を、GitHub Contents API 経由で編集します。
        以下に GitHub 個人アクセストークン (PAT) を登録してください。
    </p>

    <?php if ($message): ?>
        <div class="alert alert-success" style="margin-bottom:1rem;"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:1rem;">
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:0.75rem;color:#1a3a5c;">現在の状態</h2>
        <table class="data-table" style="margin-bottom:0;">
            <tbody>
                <tr><th style="width:200px;">設定状況</th>
                    <td><?php if ($ready): ?>
                        <span class="status-badge success">設定済み</span>
                    <?php else: ?>
                        <span class="status-badge warning">未設定 / 不完全</span>
                    <?php endif; ?></td></tr>
                <tr><th>PAT</th>
                    <td><?= $hasToken ? '登録済み (暗号化保存)' : '<span style="color:#c00;">未登録</span>' ?></td></tr>
                <tr><th>リポジトリ</th>
                    <td><?= htmlspecialchars($config['github_repo'] ?: '未設定') ?></td></tr>
                <tr><th>ブランチ</th>
                    <td><?= htmlspecialchars($config['github_branch']) ?></td></tr>
                <tr><th>記事フォルダ</th>
                    <td><?= htmlspecialchars($config['content_dir']) ?></td></tr>
                <tr><th>最終更新</th>
                    <td><?= $config['updated_at'] ? htmlspecialchars($config['updated_at']) . ' / ' . htmlspecialchars($config['updated_by'] ?? '') : '-' ?></td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:1rem;color:#1a3a5c;">設定を編集</h2>

        <form method="POST" action="cms-settings" id="cms-settings-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label">GitHub PAT <span style="color:#dc3545;">*</span></label>
                <input type="password" class="form-input" name="github_token" id="cms-f-token"
                       placeholder="<?= htmlspecialchars($tokenPlaceholder) ?>" autocomplete="off">
                <p style="font-size:11px;color:#888;margin-top:4px;">
                    fine-grained PAT 推奨。権限: <code>Contents: Read &amp; Write</code> のみ。
                    取得方法は下部の「PAT 取得手順」を参照。
                </p>
            </div>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div class="form-group">
                    <label class="form-label">リポジトリ <span style="color:#dc3545;">*</span></label>
                    <input type="text" class="form-input" name="github_repo" id="cms-f-repo"
                           value="<?= htmlspecialchars($config['github_repo']) ?>"
                           placeholder="YA-Naoto/ya-corporate-site" required>
                    <p style="font-size:11px;color:#888;margin-top:4px;">形式: <code>owner/repo</code></p>
                </div>
                <div class="form-group">
                    <label class="form-label">ブランチ <span style="color:#dc3545;">*</span></label>
                    <input type="text" class="form-input" name="github_branch" id="cms-f-branch"
                           value="<?= htmlspecialchars($config['github_branch']) ?>"
                           placeholder="main" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label">記事フォルダ <span style="color:#dc3545;">*</span></label>
                <input type="text" class="form-input" name="content_dir"
                       value="<?= htmlspecialchars($config['content_dir']) ?>"
                       placeholder="src/content/news" required>
                <p style="font-size:11px;color:#888;margin-top:4px;">リポジトリルートからの相対パス</p>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label">カテゴリ <span style="color:#dc3545;">*</span></label>
                <textarea class="form-input" name="categories" rows="4"
                          placeholder="1行ごと または カンマ区切り"><?= htmlspecialchars(implode("\n", $config['categories'])) ?></textarea>
                <p style="font-size:11px;color:#888;margin-top:4px;">新規作成時のカテゴリ選択肢</p>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div class="form-group">
                    <label class="form-label">コミッター名</label>
                    <input type="text" class="form-input" name="committer_name"
                           value="<?= htmlspecialchars($config['committer_name']) ?>" placeholder="Yamato CMS">
                </div>
                <div class="form-group">
                    <label class="form-label">コミッター email</label>
                    <input type="email" class="form-input" name="committer_email"
                           value="<?= htmlspecialchars($config['committer_email']) ?>" placeholder="cms@yamato-mgt.com">
                </div>
            </div>

            <div style="display:flex;gap:0.5rem;align-items:center;">
                <button type="submit" class="btn btn-primary">保存</button>
                <button type="button" class="btn btn-secondary" id="cms-test-btn">接続テスト</button>
                <span id="cms-test-result" style="font-size:13px;margin-left:0.5rem;"></span>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top:1rem;">
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:0.75rem;color:#1a3a5c;">PAT 取得手順 (初回登録)</h2>
        <p style="font-size:13px;color:#555;margin-bottom:0.75rem;">
            ya-corporate-site の <strong>オーナーまたは Write権限を持つコラボレーター</strong> アカウントで作業してください。
            個人アカウントが他人のリポジトリにアクセスする場合は Fine-grained PAT が発行できないため、Classic PAT を使う必要があります。
        </p>

        <h3 style="font-size:0.95rem;margin-top:0.75rem;margin-bottom:0.5rem;color:#1a3a5c;">A. Fine-grained PAT (自分が所有 or Org のリポジトリ)</h3>
        <ol style="font-size:13px;line-height:1.8;padding-left:1.5rem;">
            <li>GitHub にログイン → 右上アイコン → <strong>Settings</strong> (リポジトリの Settings ではなく <strong>アカウントの Settings</strong>)</li>
            <li>左メニュー最下部の <strong>Developer settings</strong></li>
            <li><strong>Personal access tokens</strong> → <strong>Fine-grained tokens</strong> → <strong>Generate new token</strong></li>
            <li>入力内容:
                <ul style="margin-top:0.3rem;">
                    <li><strong>Token name</strong>: <code>yamato-mgt-cms</code> 等</li>
                    <li><strong>Expiration</strong>: 1年程度 (推奨)</li>
                    <li><strong>Resource owner</strong>: リポジトリのオーナー (例: <code>YA-Naoto</code>)</li>
                    <li><strong>Repository access</strong>: <em>Only select repositories</em> → 対象リポジトリを選択</li>
                    <li><strong>Repository permissions</strong>: <code>Contents</code> を <strong>Read and write</strong> に (他は触らない)</li>
                </ul>
            </li>
            <li><strong>Generate token</strong> → 表示された <code>github_pat_...</code> をコピー</li>
        </ol>

        <h3 style="font-size:0.95rem;margin-top:1rem;margin-bottom:0.5rem;color:#1a3a5c;">B. Classic PAT (他人のリポジトリにコラボレーターでアクセス)</h3>
        <ol style="font-size:13px;line-height:1.8;padding-left:1.5rem;">
            <li>右上アイコン → <strong>Settings</strong> → <strong>Developer settings</strong></li>
            <li><strong>Personal access tokens</strong> → <strong>Tokens (classic)</strong> → <strong>Generate new token</strong> → <strong>Generate new token (classic)</strong></li>
            <li>入力内容:
                <ul style="margin-top:0.3rem;">
                    <li><strong>Note</strong>: <code>yamato-mgt-cms</code></li>
                    <li><strong>Expiration</strong>: 1年程度</li>
                    <li><strong>Select scopes</strong>: <code>repo</code> にチェック (子項目が一括で有効化される)</li>
                </ul>
            </li>
            <li><strong>Generate token</strong> → 表示された <code>ghp_...</code> をコピー</li>
        </ol>
        <p style="font-size:12px;color:#888;margin-top:0.5rem;">
            ※ Classic PAT は <code>repo</code> スコープを付けるとアクセス可能な全リポジトリへの read/write 権限を持ちます。期限を短めに設定して定期更新を推奨。
        </p>

        <h3 style="font-size:0.95rem;margin-top:1rem;margin-bottom:0.5rem;color:#1a3a5c;">登録</h3>
        <ol style="font-size:13px;line-height:1.8;padding-left:1.5rem;">
            <li>このページの <strong>GitHub PAT</strong> 欄に貼り付け</li>
            <li><strong>リポジトリ</strong>: <code>owner/repo</code> 形式 (例: <code>YA-Naoto/ya-corporate-site</code>)</li>
            <li><strong>ブランチ</strong>: Cloudflare Pages が watch しているブランチ名 (GitHub のブランチ一覧で確認)</li>
            <li><strong>接続テスト</strong> → <code>[OK] 接続OK: owner/repo@branch</code> が出れば成功</li>
            <li><strong>保存</strong></li>
        </ol>
        <p style="font-size:12px;color:#888;margin-top:0.5rem;">
            ※ 一度離れるとトークンは二度と表示されません。コピーし忘れた場合は再発行してください。<br>
            ※ PAT は本サーバー上で AES-256-GCM 暗号化して保存されます。
        </p>
    </div>

    <div class="card" style="margin-top:1rem;background:#fffdf3;border:1px solid #e8d486;">
        <h2 style="font-size:1rem;font-weight:600;margin-bottom:0.75rem;color:#8a5a00;">PAT 期限切れ・更新マニュアル</h2>
        <p style="font-size:13px;color:#555;margin-bottom:0.75rem;">
            PAT には有効期限があり、期限切れになると <strong>お知らせの投稿・更新・削除が全て失敗</strong> します。
            「接続テストで <code>PAT が無効か期限切れです</code> が出る」「投稿時に <code>HTTP 401</code> エラーが出る」などの兆候が出たら更新が必要です。
        </p>

        <h3 style="font-size:0.95rem;margin-top:0.75rem;margin-bottom:0.5rem;color:#8a5a00;">期限を確認する</h3>
        <ol style="font-size:13px;line-height:1.8;padding-left:1.5rem;">
            <li>GitHub にログイン → 右上アイコン → <strong>Settings</strong> → <strong>Developer settings</strong></li>
            <li><strong>Personal access tokens</strong>
                <ul style="margin-top:0.3rem;">
                    <li>Fine-grained → 該当トークンの「Expires」列を確認</li>
                    <li>Classic → 「Tokens (classic)」のリストで「Expires」を確認</li>
                </ul>
            </li>
            <li>期限が近づいている (1ヶ月以内など) なら早めに更新</li>
        </ol>

        <h3 style="font-size:0.95rem;margin-top:1rem;margin-bottom:0.5rem;color:#8a5a00;">更新手順</h3>
        <ol style="font-size:13px;line-height:1.8;padding-left:1.5rem;">
            <li>GitHub で <strong>新しい PAT を発行</strong>
                <ul style="margin-top:0.3rem;">
                    <li>Fine-grained の場合: 既存トークンの <strong>Regenerate</strong> ボタンで「同じ設定で再発行」も可能</li>
                    <li>Classic の場合: 既存トークンの <strong>Regenerate token</strong> で同設定で再発行</li>
                </ul>
            </li>
            <li>新しいトークン文字列をコピー</li>
            <li>このページ上部の <strong>GitHub PAT</strong> 欄に貼り付け
                <ul style="margin-top:0.3rem;">
                    <li>※ 既存トークンの上書きになるので、Resource owner / リポジトリ / 権限が同じであることを確認</li>
                    <li>※ <strong>空欄のまま保存すると既存トークンが維持</strong> されます (PAT 以外の項目だけ変更する用)</li>
                </ul>
            </li>
            <li><strong>接続テスト</strong> で疎通確認</li>
            <li><strong>保存</strong> ボタン</li>
            <li>古い PAT は GitHub 側で <strong>Revoke (削除)</strong> しておく (流出リスク軽減のため)</li>
        </ol>

        <h3 style="font-size:0.95rem;margin-top:1rem;margin-bottom:0.5rem;color:#8a5a00;">よくあるトラブル</h3>
        <table class="data-table" style="margin-top:0.3rem;font-size:13px;">
            <thead>
                <tr><th style="width:280px;">症状</th><th>原因 / 対処</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>接続テストで <code>PAT が無効か期限切れです</code></td>
                    <td>PAT 期限切れ or 貼り付けミス。新規発行して再登録</td>
                </tr>
                <tr>
                    <td>接続テストで <code>権限不足</code></td>
                    <td>Fine-grained PAT の <code>Contents</code> が Read-only のまま。PAT 編集で Read and write に変更</td>
                </tr>
                <tr>
                    <td>接続テストで <code>リポジトリ or ブランチが見つかりません</code></td>
                    <td>repo名 (owner/repo形式か?) または branch名のスペルミス。GitHub のブランチ一覧で正確な名前を確認</td>
                </tr>
                <tr>
                    <td>投稿時のみ <code>HTTP 403</code></td>
                    <td>PAT は読めるが書き込み権限なし。Contents を Read and write に変更して PAT 再発行</td>
                </tr>
                <tr>
                    <td>「公開完了」したが Cloudflare Pages がデプロイされない</td>
                    <td>commit したブランチが Cloudflare の watch ブランチと違う。ブランチ欄を本番反映ブランチ名に変更</td>
                </tr>
            </tbody>
        </table>

        <h3 style="font-size:0.95rem;margin-top:1rem;margin-bottom:0.5rem;color:#8a5a00;">運用のコツ</h3>
        <ul style="font-size:13px;line-height:1.8;padding-left:1.5rem;">
            <li>カレンダーに「PAT 期限の1ヶ月前」のリマインダを入れておく</li>
            <li>担当者が変わるタイミングでも PAT を発行し直す (退職者のトークンを残さない)</li>
            <li>PAT が漏洩した恐れがある場合は <strong>即座に GitHub で Revoke</strong> → 新規発行 → このページで更新</li>
        </ul>
    </div>
</div>

<script<?= function_exists('nonceAttr') ? nonceAttr() : '' ?>>
(function() {
    const CSRF = <?= json_encode($csrf) ?>;

    document.getElementById('cms-test-btn').addEventListener('click', async () => {
        const resultEl = document.getElementById('cms-test-result');
        resultEl.style.color = '#666';
        resultEl.textContent = '接続テスト中...';

        const payload = {
            csrf_token:    CSRF,
            github_token:  document.getElementById('cms-f-token').value,
            github_repo:   document.getElementById('cms-f-repo').value.trim(),
            github_branch: document.getElementById('cms-f-branch').value.trim(),
        };

        try {
            // 注: 直接 cms-settings.php を呼ぶと .htaccess が拡張子なしへ 301 リダイレクト
            //     し、ブラウザが POST を GET に変換してしまう (405)。
            //     拡張子なしURLを使うことで内部リライト経由で POST を維持。
            const res = await fetch('cms-settings?action=test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            resultEl.style.color = data.ok ? '#0a7' : '#c00';
            resultEl.textContent = (data.ok ? '[OK] ' : '[NG] ') + data.message;
        } catch (err) {
            resultEl.style.color = '#c00';
            resultEl.textContent = '[NG] 通信エラー: ' + err.message;
        }
    });
})();
</script>

<?php require_once '../functions/footer.php'; ?>
