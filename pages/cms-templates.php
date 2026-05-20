<?php
/**
 * HP更新 お知らせテンプレート管理
 * - 投稿時にフォーム本文に流し込むテンプレートのCRUD
 * - cms-news.php から呼び出される
 */

require_once '../api/auth.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

require_once '../api/cms/cms-templates.php';
require_once '../api/cms/cms-config.php';

function tplH($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function tplJsonRes($d, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── API アクション処理 ────────────────────────────────────────────
$action = $_GET['action'] ?? '';

if ($action) {
    // create/update/delete は POST + CSRF
    if (in_array($action, ['create', 'update', 'delete'], true)) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') tplJsonRes(['error' => 'POSTメソッドが必要です'], 405);
        if (empty($_POST['csrf_token']) && empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $raw = file_get_contents('php://input');
            $d   = json_decode($raw, true);
            if (is_array($d) && !empty($d['csrf_token'])) {
                $_POST['csrf_token'] = $d['csrf_token'];
                $GLOBALS['__tpl_json_body'] = $d;
            }
        }
        verifyCsrfToken();
    }

    switch ($action) {
        case 'list':
            tplJsonRes(cmsGetTemplates());

        case 'get': {
            $id = (string)($_GET['id'] ?? '');
            $t  = cmsGetTemplate($id);
            if (!$t) tplJsonRes(['error' => 'テンプレートが見つかりません'], 404);
            tplJsonRes($t);
        }

        case 'create': {
            $d = $GLOBALS['__tpl_json_body'] ?? (json_decode(file_get_contents('php://input'), true) ?: $_POST);
            $r = cmsCreateTemplate($d);
            if (!$r['ok']) tplJsonRes(['error' => $r['error']], 400);
            tplJsonRes(['success' => true, 'template' => $r['template']]);
        }

        case 'update': {
            $id = (string)($_GET['id'] ?? '');
            $d  = $GLOBALS['__tpl_json_body'] ?? (json_decode(file_get_contents('php://input'), true) ?: $_POST);
            $r  = cmsUpdateTemplate($id, $d);
            if (!$r['ok']) tplJsonRes(['error' => $r['error']], 400);
            tplJsonRes(['success' => true]);
        }

        case 'delete': {
            $id = (string)($_GET['id'] ?? '');
            $r  = cmsDeleteTemplate($id);
            if (!$r['ok']) tplJsonRes(['error' => $r['error']], 400);
            tplJsonRes(['success' => true]);
        }

        default:
            tplJsonRes(['error' => '不正なアクション'], 400);
    }
}

// ── ページレンダリング ──
$config     = getCmsConfig();
$categories = $config['categories'] ?? cmsDefaultConfig()['categories'];
$catOpts    = implode('', array_map(fn($c) => '<option value="' . tplH($c) . '">' . tplH($c) . '</option>', $categories));
// .htaccess の 301リダイレクト (.php → 拡張子なし) で POST が GET に変換される問題回避のため
// 拡張子を取り除いた "きれいなURL" を JS に渡す
$self       = tplH(preg_replace('/\.php$/', '', $_SERVER['PHP_SELF']));
$csrf       = generateCsrfToken();

require_once '../functions/header.php';
?>

<div class="page-container">
    <h1 class="page-title">お知らせテンプレート管理</h1>

    <p style="color:#666;font-size:13px;margin-bottom:1rem;">
        投稿時に「テンプレートから選択」で本文を流し込めるテンプレートを管理します。
        テンプレートは内部用 (GitHub には push されません)。
    </p>

    <div id="view-list">
        <div class="top-bar" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <div id="count-label" style="font-size:14px;color:#666;"></div>
            <div style="display:flex;gap:0.5rem;">
                <?= uiBackButton('list', ['href' => 'cms-news.php', 'label' => 'お知らせ一覧に戻る']) ?>
                <?= uiNewButton('新規登録', ['attrs' => 'data-action="tpl-new"']) ?>
            </div>
        </div>
        <div id="alert-list" class="alert" style="display:none;"></div>
        <div class="card">
            <h2 style="font-size:1rem;font-weight:600;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:1px solid #eee;color:#1a3a5c;">登録済みテンプレート</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:240px">テンプレート名</th>
                        <th>説明</th>
                        <th style="width:120px">カテゴリ既定</th>
                        <th style="width:150px">最終更新</th>
                        <th style="width:140px">操作</th>
                    </tr>
                </thead>
                <tbody id="tpl-table-body">
                    <tr><td colspan="5" style="text-align:center;color:#999;padding:2rem;">読み込み中...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="view-form" style="display:none;">
        <div class="top-bar" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <?= uiBackButton('list', ['attrs' => 'data-action="tpl-list"']) ?>
            <div></div>
        </div>
        <div id="alert-form" class="alert" style="display:none;"></div>
        <div class="card">
            <h2 id="tpl-form-title" style="font-size:1rem;font-weight:600;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:1px solid #eee;color:#1a3a5c;">新規テンプレート</h2>
            <form id="tpl-form">
                <input type="hidden" name="csrf_token" value="<?= tplH($csrf) ?>">

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">テンプレート名 <span style="color:#dc3545;">*</span></label>
                    <input type="text" class="form-input" id="tpl-f-name" placeholder="例: 夏季休業のお知らせ" required>
                    <p style="font-size:11px;color:#888;margin-top:4px;">投稿時のドロップダウンに表示される名前</p>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">説明 (任意)</label>
                    <input type="text" class="form-input" id="tpl-f-description" placeholder="使いどころのメモ等">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label">カテゴリ既定値 (任意)</label>
                        <select class="form-input" id="tpl-f-category">
                            <option value="">(指定しない)</option>
                            <?= $catOpts ?>
                        </select>
                        <p style="font-size:11px;color:#888;margin-top:4px;">適用時に投稿のカテゴリが自動選択されます</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">タイトル雛形 (任意)</label>
                        <input type="text" class="form-input" id="tpl-f-title-hint" placeholder="例: 2026年 夏季休業日のお知らせ">
                        <p style="font-size:11px;color:#888;margin-top:4px;">適用時にタイトル欄が空ならこの値で埋まります</p>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label">本文テンプレート <span style="color:#dc3545;">*</span></label>
                    <textarea class="form-input" id="tpl-f-body" style="min-height:300px;font-family:'Courier New',monospace;font-size:13px;" placeholder="Markdown で本文の雛形を記入"></textarea>
                    <p style="font-size:11px;color:#888;margin-top:4px;">投稿時、このテキストが本文欄に貼り付けられます</p>
                </div>

                <div style="display:flex;gap:0.5rem;">
                    <button type="submit" class="btn btn-primary" id="tpl-submit-btn">保存</button>
                    <button type="button" class="btn btn-secondary" data-action="tpl-list">キャンセル</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 削除確認モーダル -->
<div class="modal" id="tplConfirmModal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header"><h3>テンプレート削除の確認</h3></div>
        <div class="modal-body"><p id="tpl-confirm-msg"></p></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-action="tpl-cancel-delete">キャンセル</button>
            <button type="button" class="btn btn-danger" id="tpl-confirm-ok-btn">削除する</button>
        </div>
    </div>
</div>

<style<?= function_exists('nonceAttr') ? nonceAttr() : '' ?>>
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px 16px; border-radius: 5px; font-size: 14px; margin-bottom: 16px; }
.alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px 16px; border-radius: 5px; font-size: 14px; margin-bottom: 16px; }
</style>

<script<?= function_exists('nonceAttr') ? nonceAttr() : '' ?>>
(function() {
    const API  = <?= json_encode($self, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const CSRF = <?= json_encode($csrf) ?>;
    let editingId = null;
    let pendingDelete = null;

    function showAlert(zone, type, msg) {
        const el = document.getElementById('alert-' + zone);
        if (!el) return;
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 5000);
    }

    async function loadList() {
        const tbody = document.getElementById('tpl-table-body');
        const countEl = document.getElementById('count-label');
        try {
            const res  = await fetch(API + '?action=list');
            const list = await res.json();
            if (list.error) {
                tbody.replaceChildren();
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 5;
                td.style.cssText = 'text-align:center;color:#c00;padding:2rem;';
                td.textContent = list.error;
                tr.appendChild(td);
                tbody.appendChild(tr);
                return;
            }
            countEl.textContent = '全 ' + list.length + ' 件';
            tbody.replaceChildren();
            if (!list.length) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 5;
                td.style.cssText = 'text-align:center;color:#999;padding:2rem;';
                td.textContent = 'テンプレートがありません';
                tr.appendChild(td);
                tbody.appendChild(tr);
                return;
            }
            for (const t of list) {
                const tr = document.createElement('tr');

                const tdName = document.createElement('td');
                tdName.textContent = t.name || '-';
                tdName.style.fontWeight = '500';
                tr.appendChild(tdName);

                const tdDesc = document.createElement('td');
                tdDesc.textContent = t.description || '';
                tdDesc.style.cssText = 'font-size:12px;color:#666;';
                tr.appendChild(tdDesc);

                const tdCat = document.createElement('td');
                if (t.category) {
                    const badge = document.createElement('span');
                    badge.className = 'badge';
                    badge.textContent = t.category;
                    tdCat.appendChild(badge);
                } else {
                    tdCat.textContent = '-';
                    tdCat.style.color = '#999';
                }
                tr.appendChild(tdCat);

                const tdUpdated = document.createElement('td');
                tdUpdated.style.cssText = 'font-size:12px;color:#666;';
                tdUpdated.textContent = (t.updated_at || '').slice(0, 16);
                tr.appendChild(tdUpdated);

                const tdOps = document.createElement('td');
                const opsWrap = document.createElement('div');
                opsWrap.style.cssText = 'display:flex;gap:6px;';

                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'btn btn-sm btn-secondary';
                editBtn.textContent = '編集';
                editBtn.dataset.action = 'tpl-edit';
                editBtn.dataset.id = t.id;
                opsWrap.appendChild(editBtn);

                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'btn btn-sm btn-danger';
                delBtn.textContent = '削除';
                delBtn.dataset.action = 'tpl-delete';
                delBtn.dataset.id = t.id;
                delBtn.dataset.name = t.name || '';
                opsWrap.appendChild(delBtn);

                tdOps.appendChild(opsWrap);
                tr.appendChild(tdOps);

                tbody.appendChild(tr);
            }
        } catch (err) {
            tbody.replaceChildren();
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 5;
            td.style.cssText = 'text-align:center;color:#c00;padding:2rem;';
            td.textContent = '一覧の取得に失敗しました: ' + err.message;
            tr.appendChild(td);
            tbody.appendChild(tr);
        }
    }

    function showList() {
        document.getElementById('view-list').style.display = 'block';
        document.getElementById('view-form').style.display = 'none';
        loadList();
    }

    function showNew() {
        editingId = null;
        document.getElementById('tpl-form-title').textContent = '新規テンプレート';
        document.getElementById('tpl-f-name').value = '';
        document.getElementById('tpl-f-description').value = '';
        document.getElementById('tpl-f-category').value = '';
        document.getElementById('tpl-f-title-hint').value = '';
        document.getElementById('tpl-f-body').value = '';
        document.getElementById('tpl-submit-btn').textContent = '保存';
        document.getElementById('view-list').style.display = 'none';
        document.getElementById('view-form').style.display = 'block';
    }

    async function showEdit(id) {
        try {
            const res = await fetch(API + '?action=get&id=' + encodeURIComponent(id));
            const t = await res.json();
            if (t.error) { showAlert('list', 'error', t.error); return; }
            editingId = id;
            document.getElementById('tpl-form-title').textContent = '編集：' + (t.name || id);
            document.getElementById('tpl-f-name').value = t.name || '';
            document.getElementById('tpl-f-description').value = t.description || '';
            document.getElementById('tpl-f-category').value = t.category || '';
            document.getElementById('tpl-f-title-hint').value = t.title_hint || '';
            document.getElementById('tpl-f-body').value = t.body || '';
            document.getElementById('tpl-submit-btn').textContent = '更新';
            document.getElementById('view-list').style.display = 'none';
            document.getElementById('view-form').style.display = 'block';
        } catch (err) {
            showAlert('list', 'error', '読み込みに失敗しました: ' + err.message);
        }
    }

    async function submitForm(e) {
        e.preventDefault();
        const payload = {
            csrf_token:  CSRF,
            name:        document.getElementById('tpl-f-name').value.trim(),
            description: document.getElementById('tpl-f-description').value.trim(),
            category:    document.getElementById('tpl-f-category').value,
            title_hint:  document.getElementById('tpl-f-title-hint').value.trim(),
            body:        document.getElementById('tpl-f-body').value,
        };
        const url = editingId
            ? API + '?action=update&id=' + encodeURIComponent(editingId)
            : API + '?action=create';
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.error) { showAlert('form', 'error', data.error); return; }
            showList();
        } catch (err) {
            showAlert('form', 'error', '送信に失敗しました: ' + err.message);
        }
    }

    function askDelete(id, name) {
        pendingDelete = { id, name };
        document.getElementById('tpl-confirm-msg').textContent =
            '「' + name + '」を削除しますか？この操作は元に戻せません。';
        openModal('tplConfirmModal');
    }

    async function confirmDelete() {
        if (!pendingDelete) return;
        const { id } = pendingDelete;
        pendingDelete = null;
        closeModal('tplConfirmModal');
        try {
            const res = await fetch(API + '?action=delete&id=' + encodeURIComponent(id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ csrf_token: CSRF }),
            });
            const data = await res.json();
            if (data.error) { showAlert('list', 'error', data.error); return; }
            loadList();
        } catch (err) {
            showAlert('list', 'error', '削除に失敗しました: ' + err.message);
        }
    }

    document.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action]');
        if (!target) return;
        switch (target.dataset.action) {
            case 'tpl-new':           showNew(); break;
            case 'tpl-list':          showList(); break;
            case 'tpl-edit':          showEdit(target.dataset.id); break;
            case 'tpl-delete':        askDelete(target.dataset.id, target.dataset.name || target.dataset.id); break;
            case 'tpl-cancel-delete': pendingDelete = null; closeModal('tplConfirmModal'); break;
        }
    });

    document.getElementById('tpl-confirm-ok-btn').addEventListener('click', confirmDelete);
    document.getElementById('tpl-form').addEventListener('submit', submitForm);

    loadList();
})();
</script>

<?php require_once '../functions/footer.php'; ?>
