<?php
/**
 * 外部リンク管理マスタ（管理部専用）
 *
 * システム全体で参照される外部URL（製品HP・業務システム等）を一元管理する。
 *
 * - 各ページから getLink('key') で参照される。
 * - URLが変わったらここで1箇所編集するだけで全ページに反映。
 * - 「テスト」ボタンで新規タブで開いてリンク切れ確認可能。
 * - 「一括置換」でドメイン変更等の大規模変更にも対応。
 *
 * 権限: admin のみ
 */
$_inHub = defined('IN_HUB_PAGE');
if (!$_inHub) {
    require_once '../api/auth.php';
    require_once '../functions/header.php';
}
require_once '../functions/links.php';

if (!isAdmin()) {
    echo '<div style="padding:2rem;text-align:center;color:var(--gray-600);">管理部のみアクセス可能です</div>';
    if (!$_inHub) require_once '../functions/footer.php';
    return;
}

$csrfToken  = generateCsrfToken();
$data       = loadExternalLinks(true);
$categories = $data['categories'];
usort($categories, fn($a, $b) => ($a['order'] ?? 999) <=> ($b['order'] ?? 999));
$iconLibrary = getLinkIconLibrary();

// カテゴリ別にグルーピング
$byCategory = [];
foreach ($categories as $c) $byCategory[$c['id']] = [];
$byCategory['__uncategorized__'] = [];
foreach ($data['links'] as $l) {
    $cid = $l['category'] ?? '';
    if (!isset($byCategory[$cid])) $cid = '__uncategorized__';
    $byCategory[$cid][] = $l;
}
?>
<style<?= nonceAttr() ?>>
.el-page { max-width: 1100px; margin: 0 auto; padding: 0 0 3rem; }

.el-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.el-head-text h2 { margin: 0; color: var(--gray-900); }
.el-head-text .el-head-sub { color: var(--gray-600); font-size: 0.88rem; margin-top: 0.3rem; }
.el-head-actions { display: flex; gap: 0.5rem; }

.el-toolbar {
    display: flex;
    gap: 0.6rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    align-items: center;
}
.el-search { flex: 1; min-width: 260px; max-width: 400px; }

.el-category {
    margin-bottom: 1.75rem;
}
.el-category-head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.6rem;
    padding-bottom: 0.4rem;
    border-bottom: 1px solid var(--gray-200);
}
.el-category-head h3 {
    margin: 0;
    font-size: 1.0rem;
    color: var(--gray-800);
}
.el-category-head .el-count {
    background: var(--gray-100);
    color: var(--gray-600);
    padding: 0.15rem 0.5rem;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 600;
}

.el-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 9px;
    padding: 0.85rem 1rem;
    margin-bottom: 0.6rem;
    display: grid;
    grid-template-columns: 56px 1fr auto;
    gap: 0.8rem;
    align-items: center;
}
.el-card-body { min-width: 0; }

/* カードの左側アイコンプレビュー */
.el-card-iconwrap {
    width: 48px;
    height: 48px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--gray-50);
    color: var(--primary);
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    position: relative;
}
.el-card-iconwrap:hover {
    border-color: var(--primary);
    background: white;
}
.el-card-iconwrap::after {
    content: "変更";
    position: absolute;
    bottom: -6px; right: -6px;
    background: var(--gray-700);
    color: white;
    font-size: 0.62rem;
    padding: 0.08rem 0.32rem;
    border-radius: 8px;
    opacity: 0;
    transition: opacity 0.15s;
    pointer-events: none;
}
.el-card-iconwrap:hover::after { opacity: 1; }
.el-card-iconwrap svg { width: 26px; height: 26px; }

/* アイコンピッカー */
.el-icon-picker-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.el-icon-option {
    border: 1px solid var(--gray-200);
    background: white;
    border-radius: 8px;
    padding: 0.55rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    color: var(--gray-700);
}
.el-icon-option:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-50, #eff6ff);
}
.el-icon-option.selected {
    border-color: var(--primary);
    background: var(--primary-50, #eff6ff);
    color: var(--primary);
    box-shadow: 0 0 0 2px var(--primary) inset;
}
.el-icon-option svg { width: 22px; height: 22px; }
.el-icon-option-label {
    font-size: 0.66rem;
    color: var(--gray-600);
    text-align: center;
    line-height: 1.2;
}
.el-icon-option.selected .el-icon-option-label { color: var(--primary); }
.el-card-row {
    display: grid;
    grid-template-columns: 70px 1fr;
    gap: 0.6rem;
    align-items: center;
    margin-bottom: 0.35rem;
}
.el-card-row:last-child { margin-bottom: 0; }
.el-card-row > label {
    color: var(--gray-500);
    font-size: 0.78rem;
    font-weight: 600;
}
.el-card-row .form-input {
    width: 100%;
    font-size: 0.88rem;
    padding: 0.35rem 0.55rem;
}
/* URL欄 + 新規タブで開くアイコンボタン */
.el-url-wrap {
    display: flex;
    gap: 0.3rem;
    align-items: stretch;
    min-width: 0;
}
.el-url-wrap .form-input { flex: 1; min-width: 0; }
.el-url-open {
    flex: 0 0 auto;
    width: 32px;
    border: 1px solid var(--gray-300);
    background: white;
    border-radius: 6px;
    color: var(--gray-600);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.el-url-open:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-50, #eff6ff);
}
.el-url-open:disabled { opacity: 0.4; cursor: not-allowed; }
.el-key {
    font-family: ui-monospace, "Cascadia Mono", Menlo, Consolas, monospace;
    background: var(--gray-100);
    padding: 0.2rem 0.5rem;
    border-radius: 5px;
    font-size: 0.82rem;
    color: var(--gray-700);
    user-select: all;
}
.el-card-actions {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    align-self: stretch;
    justify-content: center;
}
.el-card-actions .btn { white-space: nowrap; min-width: 70px; }

.el-empty {
    padding: 2rem;
    text-align: center;
    color: var(--gray-500);
    background: var(--gray-50);
    border-radius: 8px;
    border: 1px dashed var(--gray-300);
}

/* モーダル */
.el-modal-back {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.el-modal-back.show { display: flex; }
.el-modal {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    width: 90%;
    max-width: 480px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.el-modal h3 { margin: 0 0 1rem; color: var(--gray-900); font-size: 1.1rem; }
.el-modal .form-group { margin-bottom: 0.85rem; }
.el-modal label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.25rem;
}
.el-modal .hint {
    color: var(--gray-500);
    font-size: 0.76rem;
    margin-top: 0.2rem;
}
.el-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1.25rem;
}

.el-msg {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    font-size: 0.88rem;
    display: none;
}
.el-msg.show { display: block; }
.el-msg.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.el-msg.error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>

<div class="el-page">
    <?php if (!$_inHub) { require_once __DIR__ . '/../functions/hub-tabs.php'; renderHubTabs('master'); } ?>
    <div class="el-head">
        <div class="el-head-text">
            <p class="el-head-sub">管理部のみ編集可能。各ページで <code>getLink('key')</code> で参照されます。URLが変わったらここで1箇所変更するだけで全ページに反映されます。</p>
        </div>
        <div class="el-head-actions">
            <button type="button" class="btn btn-secondary" id="elBulkBtn">一括置換</button>
            <?= uiNewButton('新規登録', ['id' => 'elAddBtn']) ?>
        </div>
    </div>

    <div class="el-msg" id="elMsg"></div>

    <div class="el-toolbar">
        <input type="text" class="form-input el-search" id="elSearch" placeholder="key / ラベル / URL で絞り込み...">
        <span style="color:var(--gray-500);font-size:0.82rem;">合計 <strong id="elTotalCount"><?= count($data['links']) ?></strong> 件</span>
    </div>

    <?php
    $renderCategories = $categories;
    if (!empty($byCategory['__uncategorized__'])) {
        $renderCategories[] = ['id' => '__uncategorized__', 'label' => '未分類'];
    }
    ?>
    <?php foreach ($renderCategories as $c): $links = $byCategory[$c['id']] ?? []; ?>
    <div class="el-category" data-category-id="<?= htmlspecialchars($c['id']) ?>">
        <div class="el-category-head">
            <h3><?= htmlspecialchars($c['label']) ?></h3>
            <span class="el-count"><?= count($links) ?> 件</span>
        </div>
        <?php if (count($links) === 0): ?>
            <div class="el-empty">このカテゴリにはリンクがありません</div>
        <?php else: ?>
            <?php foreach ($links as $l):
                $linkIcon = $l['icon'] ?? 'globe';
                if (!isset($iconLibrary[$linkIcon])) $linkIcon = 'globe';
            ?>
            <div class="el-card"
                 data-key="<?= htmlspecialchars($l['key']) ?>"
                 data-search="<?= htmlspecialchars(mb_strtolower(($l['key'] ?? '') . ' ' . ($l['label'] ?? '') . ' ' . ($l['url'] ?? '') . ' ' . ($l['note'] ?? ''))) ?>">
                <div class="el-card-iconwrap"
                     data-icon-trigger
                     data-icon="<?= htmlspecialchars($linkIcon) ?>"
                     title="クリックでアイコン変更"
                     role="button" tabindex="0" aria-label="アイコンを変更">
                    <?= renderLinkIcon($linkIcon, 26) ?>
                </div>
                <input type="hidden" data-field="icon" value="<?= htmlspecialchars($linkIcon) ?>">
                <div class="el-card-body">
                    <div class="el-card-row">
                        <label>key</label>
                        <span class="el-key"><?= htmlspecialchars($l['key']) ?></span>
                    </div>
                    <div class="el-card-row">
                        <label>ラベル</label>
                        <input type="text" class="form-input el-input-label" value="<?= htmlspecialchars($l['label'] ?? '') ?>" data-field="label">
                    </div>
                    <div class="el-card-row">
                        <label>URL</label>
                        <div class="el-url-wrap">
                            <input type="text" class="form-input el-input-url" value="<?= htmlspecialchars($l['url'] ?? '') ?>" data-field="url" placeholder="https://...">
                            <button type="button" class="el-url-open" data-url-open title="このURLを新規タブで開く" aria-label="新規タブで開く">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                    <polyline points="15 3 21 3 21 9"/>
                                    <line x1="10" y1="14" x2="21" y2="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <?php if (!empty($l['note']) || true): ?>
                    <div class="el-card-row">
                        <label>備考</label>
                        <input type="text" class="form-input el-input-note" value="<?= htmlspecialchars($l['note'] ?? '') ?>" data-field="note">
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($l['updated_at'])): ?>
                    <div class="el-card-row">
                        <label>更新</label>
                        <span style="color:var(--gray-500);font-size:0.78rem;">
                            <?= htmlspecialchars($l['updated_at']) ?>
                            <?= !empty($l['updated_by']) ? ' / ' . htmlspecialchars($l['updated_by']) : '' ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="el-card-actions">
                    <button type="button" class="btn btn-primary el-save-btn">保存</button>
                    <button type="button" class="btn btn-secondary el-test-btn">テスト</button>
                    <button type="button" class="btn btn-danger el-delete-btn">削除</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- 新規追加モーダル -->
<div class="el-modal-back" id="elAddModal">
    <div class="el-modal">
        <h3>外部リンクを新規追加</h3>
        <div class="form-group">
            <label>key（識別子）<span style="color:#dc2626">*</span></label>
            <input type="text" class="form-input" id="elAddKey" placeholder="例: product.foo.hp">
            <p class="hint">英数字・ドット・アンダースコア・ハイフンのみ。コード内で <code>getLink('このkey')</code> で参照されます。</p>
        </div>
        <div class="form-group">
            <label>カテゴリ</label>
            <select class="form-input" id="elAddCategory">
                <?php foreach ($categories as $c): ?>
                <option value="<?= htmlspecialchars($c['id']) ?>"><?= htmlspecialchars($c['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>ラベル <span style="color:#dc2626">*</span></label>
            <input type="text" class="form-input" id="elAddLabel" placeholder="例: 製品名 HP">
        </div>
        <div class="form-group">
            <label>URL</label>
            <input type="text" class="form-input" id="elAddUrl" placeholder="https://...">
        </div>
        <div class="form-group">
            <label>備考</label>
            <input type="text" class="form-input" id="elAddNote">
        </div>
        <div class="form-group">
            <label>アイコン</label>
            <div class="el-icon-picker-grid" id="elAddIconGrid"></div>
            <input type="hidden" id="elAddIcon" value="globe">
        </div>
        <div class="el-modal-actions">
            <button type="button" class="btn btn-secondary" id="elAddCancel">キャンセル</button>
            <button type="button" class="btn btn-primary"   id="elAddSubmit">追加</button>
        </div>
    </div>
</div>

<!-- カード用アイコンピッカーモーダル -->
<div class="el-modal-back" id="elIconModal">
    <div class="el-modal">
        <h3>アイコンを選択</h3>
        <div class="el-icon-picker-grid" id="elIconGrid"></div>
        <div class="el-modal-actions">
            <button type="button" class="btn btn-secondary" id="elIconCancel">キャンセル</button>
        </div>
    </div>
</div>

<!-- 一括置換モーダル -->
<div class="el-modal-back" id="elBulkModal">
    <div class="el-modal">
        <h3>URL 一括置換</h3>
        <p style="color:var(--gray-600);font-size:0.86rem;margin:0 0 1rem;">
            全リンクの URL に対して文字列置換を実行します。ドメイン変更時などに使用してください。
        </p>
        <div class="form-group">
            <label>検索文字列</label>
            <input type="text" class="form-input" id="elBulkSearch" placeholder="例: example.com">
        </div>
        <div class="form-group">
            <label>置換後</label>
            <input type="text" class="form-input" id="elBulkReplace" placeholder="例: yamato-agency.com">
        </div>
        <div class="el-modal-actions">
            <button type="button" class="btn btn-secondary" id="elBulkCancel">キャンセル</button>
            <button type="button" class="btn btn-primary"   id="elBulkSubmit">実行</button>
        </div>
    </div>
</div>

<script<?= nonceAttr() ?>>
(function(){
    'use strict';
    const CSRF = <?= json_encode($csrfToken) ?>;
    const ICON_LIBRARY = <?= json_encode($iconLibrary, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const msg  = document.getElementById('elMsg');

    function buildIconSvg(iconId, size){
        const def = ICON_LIBRARY[iconId] || ICON_LIBRARY['globe'];
        return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + def.svg + '</svg>';
    }

    function renderIconGrid(container, selectedId, onPick){
        container.innerHTML = '';
        Object.entries(ICON_LIBRARY).forEach(([id, def])=>{
            const el = document.createElement('button');
            el.type = 'button';
            el.className = 'el-icon-option' + (id === selectedId ? ' selected' : '');
            el.dataset.iconId = id;
            el.innerHTML = buildIconSvg(id, 22) + '<span class="el-icon-option-label">' + def.label + '</span>';
            el.addEventListener('click', ()=>{
                container.querySelectorAll('.el-icon-option').forEach(o => o.classList.remove('selected'));
                el.classList.add('selected');
                if (typeof onPick === 'function') onPick(id);
            });
            container.appendChild(el);
        });
    }

    function showMsg(text, type){
        msg.textContent = text;
        msg.classList.remove('success','error');
        msg.classList.add(type, 'show');
        setTimeout(()=>{ msg.classList.remove('show'); }, 4000);
    }

    async function api(action, body){
        const res = await fetch('/api/external-links.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(Object.assign({ action, csrf_token: CSRF }, body || {}))
        });
        let json;
        try { json = await res.json(); }
        catch(e){ throw new Error('レスポンス解析エラー'); }
        if (!res.ok || json.success === false) {
            throw new Error(json && json.error ? json.error : ('HTTP ' + res.status));
        }
        return json;
    }

    // 保存
    document.querySelectorAll('.el-save-btn').forEach(btn=>{
        btn.addEventListener('click', async ()=>{
            const card  = btn.closest('.el-card');
            const key   = card.dataset.key;
            const patch = {};
            card.querySelectorAll('input[data-field]').forEach(inp=>{
                patch[inp.dataset.field] = inp.value;
            });
            btn.disabled = true; btn.textContent = '保存中...';
            try {
                await api('update', { key, patch });
                showMsg('「' + key + '」を更新しました', 'success');
                // 検索インデックス更新
                card.dataset.search = ((key + ' ' + (patch.label||'') + ' ' + (patch.url||'') + ' ' + (patch.note||''))).toLowerCase();
            } catch (e) {
                showMsg('保存失敗: ' + e.message, 'error');
            } finally {
                btn.disabled = false; btn.textContent = '保存';
            }
        });
    });

    // テスト（新規タブで開く）
    document.querySelectorAll('.el-test-btn').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const card = btn.closest('.el-card');
            const url  = card.querySelector('.el-input-url').value.trim();
            if (!url) { showMsg('URL が空です', 'error'); return; }
            window.open(url, '_blank', 'noopener');
        });
    });

    // URL 欄の「開く」アイコン（小さい方）
    document.querySelectorAll('[data-url-open]').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const card = btn.closest('.el-card');
            const url  = card.querySelector('.el-input-url').value.trim();
            if (!url) { showMsg('URL が空です', 'error'); return; }
            window.open(url, '_blank', 'noopener');
        });
    });

    // 削除
    document.querySelectorAll('.el-delete-btn').forEach(btn=>{
        btn.addEventListener('click', async ()=>{
            const card = btn.closest('.el-card');
            const key  = card.dataset.key;
            if (!confirm('「' + key + '」を削除します。よろしいですか？\n（このキーを使っているページではリンクが消えます）')) return;
            btn.disabled = true; btn.textContent = '削除中...';
            try {
                await api('delete', { key });
                card.remove();
                showMsg('「' + key + '」を削除しました', 'success');
                updateTotalCount();
            } catch (e) {
                showMsg('削除失敗: ' + e.message, 'error');
                btn.disabled = false; btn.textContent = '削除';
            }
        });
    });

    // 検索 (200ms debounce で統一)
    let elSearchTimer = null;
    document.getElementById('elSearch').addEventListener('input', (e)=>{
        const v = e.target.value;
        clearTimeout(elSearchTimer);
        elSearchTimer = setTimeout(()=>{
            const q = v.trim().toLowerCase();
            document.querySelectorAll('.el-card').forEach(card=>{
                card.style.display = !q || card.dataset.search.includes(q) ? '' : 'none';
            });
        }, 200);
    });

    // 合計件数更新
    function updateTotalCount(){
        document.getElementById('elTotalCount').textContent =
            document.querySelectorAll('.el-card').length;
    }

    // カード用アイコンピッカー
    const iconModal = document.getElementById('elIconModal');
    let currentIconCard = null;
    document.querySelectorAll('[data-icon-trigger]').forEach(trig=>{
        trig.addEventListener('click', ()=>{
            currentIconCard = trig.closest('.el-card');
            const currentId = trig.dataset.icon || 'globe';
            renderIconGrid(document.getElementById('elIconGrid'), currentId, (newId)=>{
                trig.dataset.icon = newId;
                trig.innerHTML = buildIconSvg(newId, 26);
                const hiddenInput = currentIconCard.querySelector('input[data-field="icon"]');
                if (hiddenInput) hiddenInput.value = newId;
                iconModal.classList.remove('show');
            });
            iconModal.classList.add('show');
        });
    });
    document.getElementById('elIconCancel').addEventListener('click', ()=>{
        iconModal.classList.remove('show');
    });

    // 新規追加モーダル
    const addModal = document.getElementById('elAddModal');
    document.getElementById('elAddBtn').addEventListener('click', ()=>{
        ['elAddKey','elAddLabel','elAddUrl','elAddNote'].forEach(id=>document.getElementById(id).value='');
        document.getElementById('elAddIcon').value = 'globe';
        renderIconGrid(document.getElementById('elAddIconGrid'), 'globe', (id)=>{
            document.getElementById('elAddIcon').value = id;
        });
        addModal.classList.add('show');
    });
    document.getElementById('elAddCancel').addEventListener('click', ()=>{
        addModal.classList.remove('show');
    });
    document.getElementById('elAddSubmit').addEventListener('click', async ()=>{
        const body = {
            key:      document.getElementById('elAddKey').value.trim(),
            category: document.getElementById('elAddCategory').value,
            label:    document.getElementById('elAddLabel').value.trim(),
            url:      document.getElementById('elAddUrl').value.trim(),
            note:     document.getElementById('elAddNote').value.trim(),
            icon:     document.getElementById('elAddIcon').value || 'globe',
        };
        if (!body.key || !body.label) { showMsg('key とラベルは必須です', 'error'); return; }
        try {
            await api('add', body);
            showMsg('追加しました。ページを再読込します。', 'success');
            setTimeout(()=>location.reload(), 800);
        } catch (e) {
            showMsg('追加失敗: ' + e.message, 'error');
        }
    });

    // 一括置換モーダル
    const bulkModal = document.getElementById('elBulkModal');
    document.getElementById('elBulkBtn').addEventListener('click', ()=>{
        document.getElementById('elBulkSearch').value = '';
        document.getElementById('elBulkReplace').value = '';
        bulkModal.classList.add('show');
    });
    document.getElementById('elBulkCancel').addEventListener('click', ()=>{
        bulkModal.classList.remove('show');
    });
    document.getElementById('elBulkSubmit').addEventListener('click', async ()=>{
        const search  = document.getElementById('elBulkSearch').value;
        const replace = document.getElementById('elBulkReplace').value;
        if (!search) { showMsg('検索文字列は必須です', 'error'); return; }
        if (!confirm('全リンクの URL の "' + search + '" を "' + replace + '" に置換します。よろしいですか？')) return;
        try {
            const r = await api('bulk_replace', { search, replace });
            showMsg(r.data.count + ' 件を置換しました。ページを再読込します。', 'success');
            setTimeout(()=>location.reload(), 1000);
        } catch (e) {
            showMsg('置換失敗: ' + e.message, 'error');
        }
    });
})();
</script>

<?php if (!$_inHub) { require_once '../functions/footer.php'; } ?>
