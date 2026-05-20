<?php
/**
 * 製品マスタ管理（管理部専用）
 *
 * config/sales-tools-products.json を UI から編集するページ。
 * 営業ツール「製品別」タブで表示される製品の定義 (表示名・カテゴリ・色・
 * アイコン・価格表シート名マッチ・カタログ/スクリプト件数) を CRUD できる。
 *
 * 編集対象セクション:
 *   - products: 主要製品 (LED・液晶・電子黒板 等)
 *   - common  : 共通項目 (運搬費・設置費・顧客ランク定義 等)
 *
 * 権限: admin のみ
 */
$_inHub = defined('IN_HUB_PAGE');
if (!$_inHub) {
    require_once '../api/auth.php';
    require_once '../functions/header.php';
}

if (!isAdmin()) {
    echo '<div style="padding:2rem;text-align:center;color:var(--gray-600);">管理部のみアクセス可能です</div>';
    if (!$_inHub) require_once '../functions/footer.php';
    return;
}

$csrfToken = generateCsrfToken();

$ppConfigRaw = @file_get_contents(__DIR__ . '/../config/sales-tools-products.json');
$ppConfig    = $ppConfigRaw ? json_decode($ppConfigRaw, true) : null;
if (!is_array($ppConfig)) $ppConfig = ['products' => [], 'common' => []];
?>
<style<?= nonceAttr() ?>>
.pmm-page { max-width: 1400px; margin: 0 auto; padding: 0 0 3rem; }
.pmm-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
.pmm-head h2 { margin: 0; color: var(--gray-900); }
.pmm-head-sub { color: var(--gray-600); font-size: 0.88rem; margin-top: 0.3rem; }

.pmm-tabs { display: inline-flex; background: var(--gray-100); border-radius: 8px; padding: 4px; gap: 4px; margin-bottom: 1rem; }
.pmm-tab { padding: 0.4rem 1rem; border-radius: 6px; cursor: pointer; font-size: 0.88rem; color: var(--gray-700); border: none; background: transparent; }
.pmm-tab.active { background: white; color: var(--primary-dark); font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }

.pmm-layout { display: grid; grid-template-columns: 320px 1fr; gap: 1rem; align-items: start; }
@media (max-width: 900px) { .pmm-layout { grid-template-columns: 1fr; } }

.pmm-list { background: white; border: 1px solid var(--gray-200); border-radius: 10px; padding: 0.6rem; max-height: calc(100vh - 8rem); overflow-y: auto; }
.pmm-list-empty { padding: 1rem; color: var(--gray-500); font-size: 0.85rem; text-align: center; }

.pmm-row { display: flex; align-items: center; gap: 0.55rem; width: 100%; padding: 0.55rem 0.7rem; border: 1px solid transparent; border-radius: 7px; cursor: pointer; background: transparent; text-align: left; color: var(--gray-700); font-size: 0.88rem; transition: all 0.12s; margin-bottom: 0.15rem; }
.pmm-row:hover { background: var(--gray-50); }
.pmm-row.active { background: var(--primary-light); border-color: var(--primary); color: var(--primary-dark); font-weight: 600; }
.pmm-row-icon { width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; color: white; flex-shrink: 0; }
.pmm-row-icon.c-blue   { background: #3498db; }
.pmm-row-icon.c-orange { background: #e67e22; }
.pmm-row-icon.c-green  { background: #27ae60; }
.pmm-row-icon.c-purple { background: #8e44ad; }
.pmm-row-icon.c-red    { background: #e74c3c; }
.pmm-row-icon.c-gray   { background: #7f8c8d; }
.pmm-row-text { flex: 1; min-width: 0; }
.pmm-row-text .pmm-row-name { display: block; font-weight: 600; color: var(--gray-800); }
.pmm-row-text .pmm-row-sub { display: block; font-size: 0.75rem; color: var(--gray-500); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.pmm-form { background: white; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1.25rem; }
.pmm-form-empty { padding: 3rem 1rem; color: var(--gray-500); text-align: center; }
.pmm-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem 1rem; }
.pmm-form-grid .pmm-full { grid-column: 1 / -1; }
.pmm-form-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); flex-wrap: wrap; }
.pmm-form-actions .pmm-spacer { flex: 1; }

.pmm-color-picker { display: flex; gap: 0.4rem; flex-wrap: wrap; }
.pmm-color-swatch { width: 28px; height: 28px; border-radius: 6px; border: 2px solid transparent; cursor: pointer; transition: all 0.12s; }
.pmm-color-swatch:hover { transform: scale(1.1); }
.pmm-color-swatch.selected { border-color: var(--gray-900); transform: scale(1.1); }
.pmm-color-swatch.c-blue   { background: #3498db; }
.pmm-color-swatch.c-orange { background: #e67e22; }
.pmm-color-swatch.c-green  { background: #27ae60; }
.pmm-color-swatch.c-purple { background: #8e44ad; }
.pmm-color-swatch.c-red    { background: #e74c3c; }
.pmm-color-swatch.c-gray   { background: #7f8c8d; }

.pmm-icon-preview { display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; border-radius: 8px; background: var(--gray-100); color: var(--gray-700); margin-right: 0.5rem; vertical-align: middle; }

.pmm-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.82rem; }
</style>

<div class="pmm-page">
    <div class="pmm-head">
        <div>
            <h2>製品マスタ管理</h2>
            <div class="pmm-head-sub">営業ツールの「製品別」タブと「価格表」タブで使用する製品定義を編集します（admin 専用）</div>
        </div>
        <div>
            <button type="button" class="btn btn-primary" id="pmmAddBtn">＋ 製品を追加</button>
        </div>
    </div>

    <div class="pmm-tabs" role="tablist">
        <button type="button" class="pmm-tab active" data-section="products" role="tab">主要製品 (<span id="pmmCountProducts"><?= count($ppConfig['products']) ?></span>)</button>
        <button type="button" class="pmm-tab" data-section="common" role="tab">共通項目 (<span id="pmmCountCommon"><?= count($ppConfig['common']) ?></span>)</button>
    </div>

    <div class="pmm-layout">
        <div class="pmm-list" id="pmmList"></div>
        <div class="pmm-form" id="pmmForm">
            <div class="pmm-form-empty">左の一覧から製品を選択するか、「＋ 製品を追加」してください。</div>
        </div>
    </div>
</div>

<script<?= nonceAttr() ?>>
(function() {
    const csrf = <?= json_encode($csrfToken) ?>;
    let state = {
        section: 'products',
        items: <?= json_encode($ppConfig) ?>,
        editingId: null,
        dirty: false,
    };

    const COLORS = ['blue', 'orange', 'green', 'purple', 'red', 'gray'];

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function setSection(section) {
        state.section = section;
        state.editingId = null;
        document.querySelectorAll('.pmm-tab').forEach(t => t.classList.toggle('active', t.dataset.section === section));
        renderList();
        renderForm();
    }

    function renderList() {
        const list = state.items[state.section] || [];
        const el = document.getElementById('pmmList');
        if (!list.length) {
            el.innerHTML = '<div class="pmm-list-empty">登録された項目はありません</div>';
            return;
        }
        el.innerHTML = list.map(p => `
            <button type="button" class="pmm-row${p.id === state.editingId ? ' active' : ''}" data-id="${escapeHtml(p.id)}">
                <span class="pmm-row-icon c-${escapeHtml(p.color || 'gray')}">
                    ${p.icon_image
                        ? `<img src="${escapeHtml(p.icon_image)}" alt="" style="width:20px;height:20px;object-fit:contain">`
                        : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p.icon || ''}</svg>`
                    }
                </span>
                <span class="pmm-row-text">
                    <span class="pmm-row-name">${escapeHtml(p.name || p.id)}</span>
                    <span class="pmm-row-sub">${escapeHtml(p.sub || '')}</span>
                </span>
            </button>
        `).join('');
        el.querySelectorAll('.pmm-row').forEach(btn => {
            btn.addEventListener('click', () => {
                if (state.dirty && !confirm('未保存の変更があります。破棄して移動しますか？')) return;
                state.editingId = btn.dataset.id;
                state.dirty = false;
                renderList();
                renderForm();
            });
        });
    }

    function findItem(id) {
        return (state.items[state.section] || []).find(p => p.id === id);
    }

    function renderForm() {
        const form = document.getElementById('pmmForm');
        if (!state.editingId) {
            form.innerHTML = '<div class="pmm-form-empty">左の一覧から製品を選択するか、「＋ 製品を追加」してください。</div>';
            return;
        }
        const item = findItem(state.editingId) || {};
        const isProducts = state.section === 'products';
        form.innerHTML = `
            <div class="pmm-form-grid">
                <div>
                    <label class="form-label">id <span style="color:#e74c3c">*</span></label>
                    <input type="text" class="form-input pmm-mono" id="f_id" value="${escapeHtml(item.id)}" readonly style="background:var(--gray-100)">
                    <small style="color:var(--gray-500); font-size:0.75rem">id は変更不可（追加時のみ指定）</small>
                </div>
                <div>
                    <label class="form-label">表示名 <span style="color:#e74c3c">*</span></label>
                    <input type="text" class="form-input" id="f_name" value="${escapeHtml(item.name)}">
                </div>
                ${isProducts ? `
                <div>
                    <label class="form-label">英語名</label>
                    <input type="text" class="form-input" id="f_name_en" value="${escapeHtml(item.name_en)}">
                </div>
                ` : ''}
                <div>
                    <label class="form-label">カテゴリ / サブテキスト</label>
                    <input type="text" class="form-input" id="f_sub" value="${escapeHtml(item.sub)}">
                </div>
                ${isProducts ? `
                <div class="pmm-full">
                    <label class="form-label">説明文（製品カードに表示）</label>
                    <input type="text" class="form-input" id="f_description" value="${escapeHtml(item.description)}">
                </div>
                ` : ''}
                <div>
                    <label class="form-label">色</label>
                    <div class="pmm-color-picker" id="f_color_picker">
                        ${COLORS.map(c => `<button type="button" class="pmm-color-swatch c-${c}${(item.color || 'gray') === c ? ' selected' : ''}" data-color="${c}" title="${c}"></button>`).join('')}
                    </div>
                    <input type="hidden" id="f_color" value="${escapeHtml(item.color || 'gray')}">
                </div>
                <div>
                    <label class="form-label">価格表シート名マッチ（正規表現）</label>
                    <input type="text" class="form-input pmm-mono" id="f_match" value="${escapeHtml(item.match)}" placeholder="例: ^モニたろう">
                </div>
                ${isProducts ? `
                <div>
                    <label class="form-label">カタログ件数</label>
                    <input type="number" class="form-input" id="f_catalog_count" value="${(item.catalog_count|0)}" min="0">
                </div>
                <div>
                    <label class="form-label">スクリプト件数</label>
                    <input type="number" class="form-input" id="f_script_count" value="${(item.script_count|0)}" min="0">
                </div>
                ` : ''}
                <div class="pmm-full">
                    <label class="form-label">アイコン</label>
                    <div style="display:flex; align-items:flex-start; gap:0.75rem; flex-wrap:wrap">
                        <span class="pmm-icon-preview" id="f_icon_preview" style="width:64px; height:64px;">
                            ${item.icon_image
                                ? `<img src="${escapeHtml(item.icon_image)}" alt="" style="width:48px;height:48px;object-fit:contain">`
                                : `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${item.icon || ''}</svg>`
                            }
                        </span>
                        <div style="flex:1; min-width:240px; display:flex; flex-direction:column; gap:0.4rem">
                            <div style="display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap">
                                <input type="file" id="f_icon_file" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" style="font-size:0.85rem">
                                <button type="button" class="btn btn-sm btn-secondary" id="f_icon_clear" ${item.icon_image ? '' : 'style="display:none"'}>画像を解除</button>
                            </div>
                            <small style="color:var(--gray-500); font-size:0.75rem">PNG / JPG / SVG / WebP / GIF。最大 1MB。画像をアップロードすると下の SVG パスより優先表示されます。</small>
                            <input type="hidden" id="f_icon_image" value="${escapeHtml(item.icon_image || '')}">
                        </div>
                    </div>
                </div>
                <div class="pmm-full">
                    <label class="form-label">SVG パス（画像が未設定のときに表示）</label>
                    <textarea class="form-input pmm-mono" id="f_icon" rows="3" placeholder="&lt;rect x=&quot;2&quot; y=&quot;3&quot; ... /&gt;">${escapeHtml(item.icon)}</textarea>
                    <small style="color:var(--gray-500); font-size:0.75rem">外側の &lt;svg&gt; は不要。lucide / feather スタイルの単色 SVG パスを推奨</small>
                </div>
            </div>
            <div class="pmm-form-actions">
                <button type="button" class="btn btn-danger" id="pmmDeleteBtn">削除</button>
                <div class="pmm-spacer"></div>
                <button type="button" class="btn btn-primary" id="pmmSaveBtn">保存</button>
            </div>
        `;

        // 色ピッカー
        form.querySelectorAll('.pmm-color-swatch').forEach(sw => {
            sw.addEventListener('click', () => {
                form.querySelectorAll('.pmm-color-swatch').forEach(s => s.classList.remove('selected'));
                sw.classList.add('selected');
                document.getElementById('f_color').value = sw.dataset.color;
                state.dirty = true;
            });
        });
        // アイコンプレビュー連動 (SVG 側)
        const iconInput = document.getElementById('f_icon');
        const iconImageEl = document.getElementById('f_icon_image');
        const previewEl = document.getElementById('f_icon_preview');
        function refreshIconPreview() {
            const imgUrl = iconImageEl.value.trim();
            if (imgUrl) {
                previewEl.innerHTML = `<img src="${escapeHtml(imgUrl)}" alt="" style="width:48px;height:48px;object-fit:contain">`;
            } else {
                previewEl.innerHTML = `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${iconInput.value}</svg>`;
            }
        }
        iconInput.addEventListener('input', () => {
            refreshIconPreview();
            state.dirty = true;
        });
        // ファイルアップロード
        const iconFileInput = document.getElementById('f_icon_file');
        const iconClearBtn  = document.getElementById('f_icon_clear');
        iconFileInput.addEventListener('change', async () => {
            const file = iconFileInput.files[0];
            if (!file) return;
            if (file.size > 1024 * 1024) { alert('ファイルサイズは 1MB 以内にしてください'); iconFileInput.value = ''; return; }
            const fd = new FormData();
            fd.append('icon', file);
            fd.append('id', state.editingId);
            try {
                const r = await fetch('/api/product-icon-upload.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf },
                    body: fd,
                });
                const j = await r.json();
                if (!r.ok || j.success === false) {
                    alert(j.error || j.message || 'アップロードに失敗しました');
                    return;
                }
                const url = (j.data && j.data.url) || j.url;
                if (!url) { alert('アップロード結果に URL が含まれていませんでした'); return; }
                iconImageEl.value = url;
                iconClearBtn.style.display = '';
                refreshIconPreview();
                state.dirty = true;
                iconFileInput.value = '';
                toast('画像をアップロードしました（保存ボタンで反映）');
            } catch (e) {
                alert('アップロード中にエラーが発生しました: ' + (e.message || e));
            }
        });
        iconClearBtn.addEventListener('click', () => {
            iconImageEl.value = '';
            iconClearBtn.style.display = 'none';
            refreshIconPreview();
            state.dirty = true;
        });
        ['f_name', 'f_name_en', 'f_sub', 'f_description', 'f_match', 'f_catalog_count', 'f_script_count'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', () => { state.dirty = true; });
        });

        document.getElementById('pmmSaveBtn').addEventListener('click', saveCurrent);
        document.getElementById('pmmDeleteBtn').addEventListener('click', deleteCurrent);
    }

    function collectForm() {
        const isProducts = state.section === 'products';
        const obj = {
            id:    document.getElementById('f_id').value.trim(),
            name:  document.getElementById('f_name').value.trim(),
            sub:   document.getElementById('f_sub').value.trim(),
            color: document.getElementById('f_color').value,
            icon:  document.getElementById('f_icon').value,
            icon_image: document.getElementById('f_icon_image').value.trim(),
            match: document.getElementById('f_match').value,
        };
        if (isProducts) {
            obj.name_en       = document.getElementById('f_name_en').value.trim();
            obj.description   = document.getElementById('f_description').value.trim();
            obj.catalog_count = parseInt(document.getElementById('f_catalog_count').value, 10) || 0;
            obj.script_count  = parseInt(document.getElementById('f_script_count').value, 10) || 0;
        }
        return obj;
    }

    function postJson(action, body) {
        return fetch('/api/product-master.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(Object.assign({ action, section: state.section }, body)),
        }).then(r => r.json().then(j => ({ ok: r.ok, j })));
    }

    async function saveCurrent() {
        const patch = collectForm();
        if (!patch.name) { alert('表示名は必須です'); return; }
        const res = await postJson('update', { id: state.editingId, patch });
        if (!res.ok || res.j.success === false) {
            alert(res.j.error || res.j.message || '保存に失敗しました');
            return;
        }
        // 反映
        const list = state.items[state.section];
        const idx = list.findIndex(p => p.id === state.editingId);
        if (idx >= 0) list[idx] = Object.assign(list[idx], patch);
        state.dirty = false;
        renderList();
        renderForm();
        toast('保存しました');
    }

    async function deleteCurrent() {
        if (!confirm('この製品を削除しますか？\n（営業ツールの製品別タブと価格表マッチから消えます）')) return;
        const res = await postJson('delete', { id: state.editingId });
        if (!res.ok || res.j.success === false) {
            alert(res.j.error || res.j.message || '削除に失敗しました');
            return;
        }
        state.items[state.section] = state.items[state.section].filter(p => p.id !== state.editingId);
        state.editingId = null;
        state.dirty = false;
        document.getElementById('pmmCount' + (state.section === 'products' ? 'Products' : 'Common')).textContent = state.items[state.section].length;
        renderList();
        renderForm();
        toast('削除しました');
    }

    async function addNew() {
        const id = prompt('新しい製品の id を入力してください（半角英小文字・数字・ハイフン・アンダースコアのみ）');
        if (!id) return;
        if (!/^[a-z0-9_-]+$/.test(id)) {
            alert('id の形式が不正です'); return;
        }
        // 重複チェック (両セクションで)
        for (const sec of ['products', 'common']) {
            if ((state.items[sec] || []).some(p => p.id === id)) {
                alert('id が既に存在します: ' + id); return;
            }
        }
        const product = { id, name: id, sub: '', color: 'gray', icon: '', match: '' };
        if (state.section === 'products') {
            product.name_en = ''; product.description = '';
            product.catalog_count = 0; product.script_count = 0;
        }
        const res = await postJson('add', { product });
        if (!res.ok || res.j.success === false) {
            alert(res.j.error || res.j.message || '追加に失敗しました');
            return;
        }
        state.items[state.section].push(product);
        state.editingId = id;
        document.getElementById('pmmCount' + (state.section === 'products' ? 'Products' : 'Common')).textContent = state.items[state.section].length;
        renderList();
        renderForm();
        toast('追加しました（フォームから詳細を入力して保存してください）');
    }

    function toast(msg) {
        const t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#117a65;color:white;padding:0.7rem 1.3rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.2);z-index:9999;font-size:0.9rem';
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 2200);
    }

    // 初期化
    document.querySelectorAll('.pmm-tab').forEach(t => {
        t.addEventListener('click', () => setSection(t.dataset.section));
    });
    document.getElementById('pmmAddBtn').addEventListener('click', addNew);
    renderList();
})();
</script>

<?php if (!$_inHub) require_once '../functions/footer.php'; ?>
