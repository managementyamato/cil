<?php
/**
 * 全体お知らせ掲示板
 * 閲覧: 全ユーザー（sales 以上）
 * 作成・編集・削除: 管理部（admin）のみ
 */
require_once '../api/auth.php';
require_once '../functions/header.php';

$isAdmin = isAdmin();
$userEmail = $_SESSION['user_email'];

$csrfToken = generateCsrfToken();
?>
<style<?= nonceAttr() ?>>
.ann-container { max-width: 860px; margin: 0 auto; padding: 1.5rem 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem; }
.page-header h2 { font-size: 1.4rem; font-weight: 700; }

/* カード */
.ann-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    transition: box-shadow 0.15s;
    position: relative;
}
.ann-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); }
.ann-card.unread { border-left: 4px solid var(--primary); }
.ann-card.unread::before {
    content: 'NEW';
    position: absolute;
    top: 0.75rem; right: 1rem;
    background: var(--primary);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    letter-spacing: 0.05em;
}
.ann-card.priority-warning { border-left-color: #f59e0b; }
.ann-card.priority-urgent  { border-left-color: var(--danger); }

.ann-pin-badge {
    display: inline-flex; align-items: center; gap: 0.25rem;
    font-size: 0.7rem; font-weight: 600;
    color: #6b7280; margin-bottom: 0.5rem;
}
.ann-title {
    font-size: 1.05rem; font-weight: 600;
    color: #111827; margin-bottom: 0.5rem;
    display: flex; align-items: flex-start; gap: 0.5rem;
}
.ann-priority-dot {
    width: 8px; height: 8px; border-radius: 50%;
    margin-top: 7px; flex-shrink: 0;
    background: var(--gray-300);
}
.ann-priority-dot.info    { background: var(--primary); }
.ann-priority-dot.warning { background: #f59e0b; }
.ann-priority-dot.urgent  { background: var(--danger); }

.ann-content {
    font-size: 0.9rem; color: #374151;
    line-height: 1.7; white-space: pre-wrap;
    margin-bottom: 0.75rem;
}
.ann-meta {
    font-size: 0.78rem; color: #9ca3af;
    display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
}
.ann-meta .expires {
    color: #f59e0b; font-weight: 500;
}
.ann-actions { display: flex; gap: 0.5rem; margin-left: auto; }
.ann-empty {
    text-align: center; padding: 4rem 1rem;
    color: #9ca3af; font-size: 0.95rem;
}

/* モーダル */
.modal {
    position: fixed; inset: 0; background: rgba(0,0,0,0.4);
    z-index: 9000; display: none;
    align-items: center; justify-content: center;
}
.modal.active { display: flex; }
.modal-content {
    background: white; border-radius: 16px;
    width: min(560px, 95vw); max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    padding: 1.75rem;
}
.modal-content h2 { font-size: 1.2rem; font-weight: 700; margin-bottom: 1.25rem; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem; }
.form-group input, .form-group textarea, .form-group select {
    width: 100%; padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db; border-radius: 8px;
    font-size: 0.9rem; font-family: inherit;
}
.form-group textarea { resize: vertical; min-height: 120px; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus {
    outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary-light);
}
.form-row-inline { display: flex; gap: 1rem; }
.form-row-inline .form-group { flex: 1; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.25rem; }

/* 優先度バッジ */
.priority-badge {
    display: inline-flex; align-items: center;
    padding: 0.15rem 0.5rem; border-radius: 6px;
    font-size: 0.72rem; font-weight: 600;
}
.priority-badge.info    { background: #dbeafe; color: #1d4ed8; }
.priority-badge.warning { background: #fef3c7; color: #92400e; }
.priority-badge.urgent  { background: #fee2e2; color: #991b1b; }

/* 未読カウントバナー */
.unread-banner {
    background: var(--primary-light);
    border: 1px solid var(--primary);
    border-radius: 8px;
    padding: 0.6rem 1rem;
    font-size: 0.875rem;
    color: var(--primary);
    margin-bottom: 1rem;
    display: flex; align-items: center; gap: 0.5rem;
}
</style>

<div class="ann-container">
    <div class="page-header">
        <h2>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-4px; margin-right:6px; color:var(--primary)">
                <path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"/>
            </svg>
            全体お知らせ
        </h2>
        <?php if ($isAdmin): ?>
        <button class="btn btn-primary" id="createBtn" style="display:flex;align-items:center;gap:0.4rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            新規作成
        </button>
        <?php endif; ?>
    </div>

    <!-- フラッシュメッセージ -->
    <div id="flashMessage" style="display:none;" class="alert mb-1"></div>

    <!-- 未読バナー（JS で制御） -->
    <div class="unread-banner" id="unreadBanner" style="display:none;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="unreadBannerText"></span>
    </div>

    <!-- お知らせ一覧 -->
    <div id="annList">
        <div style="text-align:center;padding:2rem;color:#9ca3af;">読み込み中...</div>
    </div>
</div>

<!-- 作成・編集モーダル -->
<?php if ($isAdmin): ?>
<div class="modal" id="editModal">
    <div class="modal-content">
        <h2 id="modalTitle">お知らせを作成</h2>
        <input type="hidden" id="editId" value="">

        <div class="form-group">
            <label>タイトル <span style="color:var(--danger)">*</span></label>
            <input type="text" id="editTitle" maxlength="100" placeholder="お知らせのタイトル">
        </div>
        <div class="form-group">
            <label>内容 <span style="color:var(--danger)">*</span></label>
            <textarea id="editContent" maxlength="2000" placeholder="お知らせの内容を入力してください"></textarea>
        </div>
        <div class="form-row-inline">
            <div class="form-group">
                <label>重要度</label>
                <select id="editPriority">
                    <option value="info">通常（青）</option>
                    <option value="warning">注意（黄）</option>
                    <option value="urgent">重要（赤）</option>
                </select>
            </div>
            <div class="form-group">
                <label>掲載期限（任意）</label>
                <input type="date" id="editExpiresAt">
            </div>
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                <input type="checkbox" id="editPinned" style="width:auto;">
                ピン留めする（常に上部に表示）
            </label>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="cancelBtn">キャンセル</button>
            <button class="btn btn-primary" id="saveBtn">保存</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script<?= nonceAttr() ?>>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
const IS_ADMIN   = <?= $isAdmin ? 'true' : 'false' ?>;
const USER_EMAIL = <?= json_encode($userEmail) ?>;

const PRIORITY_LABELS = { info: '通常', warning: '注意', urgent: '重要' };

let announcements = [];

// ========== お知らせ読み込み ==========
async function loadAnnouncements() {
    try {
        const res = await fetch('/api/announcements.php?action=list', {
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);
        announcements = json.data || [];
        renderList();
    } catch (e) {
        document.getElementById('annList').innerHTML =
            '<div class="ann-empty">読み込みに失敗しました</div>';
    }
}

// ========== 一覧レンダリング ==========
function renderList() {
    const list    = document.getElementById('annList');
    const banner  = document.getElementById('unreadBanner');
    const bannerT = document.getElementById('unreadBannerText');

    const unread = announcements.filter(a => !a.is_read);
    if (unread.length > 0) {
        banner.style.display = 'flex';
        bannerT.textContent  = `未読のお知らせが ${unread.length} 件あります`;
    } else {
        banner.style.display = 'none';
    }

    if (announcements.length === 0) {
        list.innerHTML = '<div class="ann-empty">お知らせはありません</div>';
        return;
    }

    list.innerHTML = announcements.map(a => cardHtml(a)).join('');

    // カードクリックで既読
    list.querySelectorAll('.ann-card[data-id]').forEach(card => {
        card.addEventListener('click', e => {
            if (e.target.closest('button')) return;
            markRead(card.dataset.id);
        });
    });
    // 編集・削除ボタン
    list.querySelectorAll('[data-edit]').forEach(btn =>
        btn.addEventListener('click', () => openEdit(btn.dataset.edit))
    );
    list.querySelectorAll('[data-delete]').forEach(btn =>
        btn.addEventListener('click', () => deleteAnn(btn.dataset.delete))
    );
}

function cardHtml(a) {
    const unreadClass  = a.is_read ? '' : ' unread';
    const priorityClass = ` priority-${a.priority}`;
    const pinBadge     = a.pinned
        ? '<div class="ann-pin-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24z"/></svg> ピン留め</div>'
        : '';
    const priorityLabel = PRIORITY_LABELS[a.priority] || 'お知らせ';
    const priorityBadge = `<span class="priority-badge ${a.priority}">${priorityLabel}</span>`;
    const expiresText   = a.expires_at ? `<span class="expires">掲載期限: ${a.expires_at}</span>` : '';
    const readCountText = IS_ADMIN ? `<span>既読: ${a.read_count || 0}名</span>` : '';
    const adminActions  = IS_ADMIN
        ? `<div class="ann-actions">
               <button class="btn btn-sm btn-secondary" data-edit="${escapeHtml(a.id)}" title="編集">編集</button>
               <button class="btn btn-sm btn-danger" data-delete="${escapeHtml(a.id)}" title="削除">削除</button>
           </div>`
        : '';

    const d = new Date(a.created_at);
    const dateStr = `${d.getFullYear()}/${String(d.getMonth()+1).padStart(2,'0')}/${String(d.getDate()).padStart(2,'0')}`;

    return `
<div class="ann-card${unreadClass}${priorityClass}" data-id="${escapeHtml(a.id)}" style="cursor:pointer;">
    ${pinBadge}
    <div class="ann-title">
        <div class="ann-priority-dot ${a.priority}"></div>
        ${escapeHtml(a.title)}
    </div>
    <div class="ann-content">${escapeHtml(a.content)}</div>
    <div class="ann-meta">
        ${priorityBadge}
        <span>${dateStr} 投稿</span>
        ${expiresText}
        ${readCountText}
        ${adminActions}
    </div>
</div>`;
}

// ========== 既読マーク ==========
async function markRead(id) {
    const ann = announcements.find(a => a.id === id);
    if (!ann || ann.is_read) return;

    try {
        const res = await fetch('/api/announcements.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ action: 'read', id })
        });
        const json = await res.json();
        if (json.success) {
            ann.is_read = true;
            renderList();
        }
    } catch (e) { /* 無視 */ }
}

// ========== 作成・編集 ==========
<?php if ($isAdmin): ?>
function openCreate() {
    document.getElementById('editId').value       = '';
    document.getElementById('editTitle').value    = '';
    document.getElementById('editContent').value  = '';
    document.getElementById('editPriority').value = 'info';
    document.getElementById('editExpiresAt').value = '';
    document.getElementById('editPinned').checked = false;
    document.getElementById('modalTitle').textContent = 'お知らせを作成';
    document.getElementById('editModal').classList.add('active');
}

function openEdit(id) {
    const ann = announcements.find(a => a.id === id);
    if (!ann) return;
    document.getElementById('editId').value       = ann.id;
    document.getElementById('editTitle').value    = ann.title;
    document.getElementById('editContent').value  = ann.content;
    document.getElementById('editPriority').value = ann.priority;
    document.getElementById('editExpiresAt').value = ann.expires_at || '';
    document.getElementById('editPinned').checked = !!ann.pinned;
    document.getElementById('modalTitle').textContent = 'お知らせを編集';
    document.getElementById('editModal').classList.add('active');
}

function closeModal() {
    document.getElementById('editModal').classList.remove('active');
}

async function saveAnn() {
    const id      = document.getElementById('editId').value;
    const title   = document.getElementById('editTitle').value.trim();
    const content = document.getElementById('editContent').value.trim();

    if (!title || !content) {
        showFlash('タイトルと内容は必須です', 'danger');
        return;
    }

    const payload = {
        action:     id ? 'update' : 'create',
        id,
        title,
        content,
        priority:   document.getElementById('editPriority').value,
        expires_at: document.getElementById('editExpiresAt').value || null,
        pinned:     document.getElementById('editPinned').checked,
    };

    try {
        const res = await fetch('/api/announcements.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);
        closeModal();
        showFlash(json.message, 'success');
        await loadAnnouncements();
    } catch (e) {
        showFlash(e.message || '保存に失敗しました', 'danger');
    }
}

async function deleteAnn(id) {
    if (!confirm('このお知らせを削除しますか？')) return;
    try {
        const res = await fetch('/api/announcements.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ action: 'delete', id })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);
        showFlash(json.message, 'success');
        await loadAnnouncements();
    } catch (e) {
        showFlash(e.message || '削除に失敗しました', 'danger');
    }
}

document.getElementById('createBtn').addEventListener('click', openCreate);
document.getElementById('cancelBtn').addEventListener('click', closeModal);
document.getElementById('saveBtn').addEventListener('click', saveAnn);
// 背景クリックでは閉じない（×ボタン・キャンセルのみ）
<?php endif; ?>

// ========== ユーティリティ ==========
function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function showFlash(msg, type) {
    const el = document.getElementById('flashMessage');
    el.className = `alert alert-${type} mb-1`;
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

// 初回ロード
loadAnnouncements();
</script>

<?php
// フッター
$footerFile = __DIR__ . '/../functions/footer.php';
if (file_exists($footerFile)) require_once $footerFile;
else echo '</main></div></body></html>';
?>
