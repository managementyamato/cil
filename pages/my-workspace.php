<?php
/**
 * マイワークスペース
 * タスク管理（全ユーザー共有）+ メモ（個人専用プライベート）
 */
require_once '../api/auth.php';

$currentUserEmail = $_SESSION['user_email'] ?? '';
$activeTab = in_array($_GET['tab'] ?? '', ['tasks', 'memos']) ? $_GET['tab'] : 'tasks';

// メンション用ユーザー一覧（従業員マスタより）
$_empData = getData();
$mentionUsers = [];
$_today = date('Y-m-d');
foreach ($_empData['employees'] ?? [] as $emp) {
    if (empty($emp['email'])) continue;
    if (!empty($emp['deleted'])) continue;
    if (!empty($emp['leave_date']) && $emp['leave_date'] < $_today) continue;
    $mentionUsers[] = [
        'email' => $emp['email'],
        'name'  => $emp['name'],
    ];
}
unset($_empData, $_today);
?>
<?php require_once '../functions/header.php'; ?>
<style<?= nonceAttr() ?>>
    /* ── ワークスペース共通 ── */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .tab-nav {
        display: flex;
        gap: 0;
        background: var(--gray-100);
        border-radius: 8px;
        padding: 3px;
        border: 1px solid var(--gray-200);
    }
    .tab-btn {
        padding: 0.45rem 1.25rem;
        border: none;
        background: transparent;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--gray-500);
        transition: all 0.15s;
    }
    .tab-btn.active {
        background: white;
        color: var(--gray-800);
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* ── タスク ── */
    .task-toolbar {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    .task-filter-group {
        display: flex;
        gap: 0.375rem;
    }
    .task-filter-btn {
        padding: 0.3rem 0.75rem;
        border: 1px solid var(--gray-200);
        border-radius: 6px;
        background: white;
        cursor: pointer;
        font-size: 0.8125rem;
        color: var(--gray-600);
        transition: all 0.15s;
    }
    .task-filter-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }
    .task-list { display: flex; flex-direction: column; gap: 0.625rem; }
    .task-card {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: 0.875rem 1rem;
        transition: box-shadow 0.15s;
    }
    .task-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .task-card.status-完了 { opacity: 0.7; }
    .task-card-header {
        display: flex;
        align-items: flex-start;
        gap: 0.625rem;
    }
    .task-status-btn {
        flex-shrink: 0;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: 2px solid var(--gray-300);
        background: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
        margin-top: 2px;
    }
    .task-status-btn.status-進行中 { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 15%, white); }
    .task-status-btn.status-完了 { border-color: #22c55e; background: #22c55e; color: white; font-size: 12px; font-weight: 700; }
    .task-main { flex: 1; min-width: 0; }
    .task-title-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .task-title {
        font-weight: 600;
        color: var(--gray-800);
        font-size: 0.9375rem;
        flex: 1;
    }
    .task-card.status-完了 .task-title { text-decoration: line-through; color: var(--gray-400); }
    .task-due-badge {
        font-size: 0.75rem;
        padding: 0.15rem 0.5rem;
        border-radius: 4px;
        background: var(--gray-100);
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        white-space: nowrap;
    }
    .task-due-badge.overdue { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }
    .task-due-badge.today { background: #fef3c7; color: #d97706; border-color: #fcd34d; }
    .task-creator-chip {
        font-size: 0.75rem;
        color: var(--gray-400);
        white-space: nowrap;
    }
    .task-actions { display: flex; gap: 0.375rem; flex-shrink: 0; }
    .task-action-btn {
        padding: 0.2rem 0.5rem;
        border: 1px solid var(--gray-200);
        border-radius: 4px;
        background: white;
        cursor: pointer;
        font-size: 0.75rem;
        color: var(--gray-500);
        transition: all 0.15s;
    }
    .task-action-btn:hover { background: var(--gray-50); color: var(--gray-700); }
    .task-action-btn.danger:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

    /* サブタスク */
    .task-subtasks { margin-top: 0.625rem; margin-left: 2rem; }
    .subtask-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0; }
    .subtask-item input[type="checkbox"] { width: 15px; height: 15px; cursor: pointer; accent-color: var(--primary); }
    .subtask-label { font-size: 0.875rem; color: var(--gray-700); flex: 1; }
    .subtask-label.done { text-decoration: line-through; color: var(--gray-400); }
    .subtask-delete-btn {
        padding: 0 0.25rem; border: none; background: none; cursor: pointer;
        color: var(--gray-300); font-size: 1rem; line-height: 1; opacity: 0; transition: opacity 0.15s;
    }
    .subtask-item:hover .subtask-delete-btn { opacity: 1; }
    .subtask-delete-btn:hover { color: #dc2626; }
    .add-subtask-row { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem; }
    .add-subtask-input {
        flex: 1; padding: 0.25rem 0.5rem; border: 1px solid var(--gray-200);
        border-radius: 4px; font-size: 0.8125rem; outline: none; color: var(--gray-700);
    }
    .add-subtask-input:focus { border-color: var(--primary); }
    .add-subtask-btn {
        padding: 0.25rem 0.625rem; border: 1px solid var(--gray-200); border-radius: 4px;
        background: white; cursor: pointer; font-size: 0.8125rem; color: var(--gray-500); transition: all 0.15s;
    }
    .add-subtask-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
    .subtask-progress { font-size: 0.75rem; color: var(--gray-400); margin-left: 0.375rem; }
    .task-description { font-size: 0.8125rem; color: var(--gray-500); margin-top: 0.25rem; white-space: pre-wrap; }
    .task-empty { text-align: center; padding: 3rem 1rem; color: var(--gray-400); }
    .task-empty svg { margin-bottom: 0.75rem; display: block; margin-left: auto; margin-right: auto; }

    /* ── メモ ── */
    .memo-toolbar { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .memo-search-wrap { position: relative; flex: 1; max-width: 320px; }
    .memo-search-wrap svg { position: absolute; left: 0.625rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); pointer-events: none; }
    .memo-search-input {
        width: 100%; padding: 0.45rem 0.75rem 0.45rem 2rem;
        border: 1px solid var(--gray-200); border-radius: 6px; font-size: 0.875rem;
        outline: none; color: var(--gray-700); background: white; box-sizing: border-box;
    }
    .memo-search-input:focus { border-color: var(--primary); }
    .memo-section-label {
        font-size: 0.8125rem; font-weight: 600; color: var(--gray-400);
        text-transform: uppercase; letter-spacing: 0.04em;
        margin: 1.25rem 0 0.625rem; display: flex; align-items: center; gap: 0.375rem;
    }
    .memo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.875rem; }
    .memo-card {
        background: white; border: 1px solid var(--gray-200); border-radius: 10px;
        padding: 0.875rem; cursor: pointer;
        transition: box-shadow 0.15s, transform 0.1s;
        display: flex; flex-direction: column; gap: 0.5rem; min-height: 130px;
    }
    .memo-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-1px); }
    .memo-card.pinned { border-color: #fcd34d; background: #fffbeb; }
    .memo-card-header { display: flex; align-items: flex-start; gap: 0.375rem; }
    .memo-title {
        flex: 1; font-size: 0.9375rem; font-weight: 600; color: var(--gray-800); margin: 0;
        overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    }
    .memo-pin-icon { color: #d97706; font-size: 0.875rem; flex-shrink: 0; }
    .memo-preview {
        flex: 1; font-size: 0.8125rem; color: var(--gray-500);
        overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; line-height: 1.5;
    }
    .memo-footer { display: flex; align-items: center; justify-content: space-between; gap: 0.375rem; }
    .memo-tags { display: flex; gap: 0.25rem; flex-wrap: wrap; }
    .memo-tag { font-size: 0.6875rem; padding: 0.1rem 0.45rem; background: var(--gray-100); border-radius: 3px; color: var(--gray-500); }
    .memo-date { font-size: 0.6875rem; color: var(--gray-300); white-space: nowrap; }
    .memo-empty { text-align: center; padding: 3rem 1rem; color: var(--gray-400); }
    .memo-empty svg { display: block; margin: 0 auto 0.75rem; }
    .memo-card-actions { display: flex; gap: 0.375rem; margin-top: 0.25rem; }
    .memo-card-action-btn {
        padding: 0.2rem 0.5rem; border: 1px solid var(--gray-200); border-radius: 4px;
        background: white; cursor: pointer; font-size: 0.75rem; color: var(--gray-500); transition: all 0.15s;
    }
    .memo-card-action-btn:hover { background: var(--gray-50); color: var(--gray-700); }
    .memo-card-action-btn.pin-active { color: #d97706; border-color: #fcd34d; }
    .memo-card-action-btn.danger:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

    /* ── モーダル共通 ── */
    .modal {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4);
        z-index: 1000; align-items: center; justify-content: center; padding: 1rem;
    }
    .modal.active { display: flex; }
    .modal-content {
        background: white; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.18);
        width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto;
        animation: ws-modal-in 0.15s ease;
    }
    @keyframes ws-modal-in {
        from { opacity: 0; transform: translateY(-12px) scale(0.98); }
        to   { opacity: 1; transform: none; }
    }
    .modal-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1rem 1.25rem; border-bottom: 1px solid var(--gray-100);
    }
    .modal-header h3 { margin: 0; font-size: 1.0625rem; color: var(--gray-800); }
    .close {
        border: none; background: none; font-size: 1.25rem; cursor: pointer;
        color: var(--gray-400); line-height: 1; padding: 0.25rem;
    }
    .close:hover { color: var(--gray-700); }
    .modal-body { padding: 1.25rem; }
    .modal-footer {
        padding: 0.875rem 1.25rem; border-top: 1px solid var(--gray-100);
        display: flex; justify-content: flex-end; gap: 0.625rem;
    }
    .form-group label { display: block; font-size: 0.8125rem; font-weight: 600; color: var(--gray-600); margin-bottom: 0.375rem; }
    .form-group { margin-bottom: 1rem; }
    .form-control {
        width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--gray-200);
        border-radius: 6px; font-size: 0.875rem; color: var(--gray-700);
        outline: none; box-sizing: border-box; font-family: inherit;
    }
    .form-control:focus { border-color: var(--primary); }
    textarea.form-control { resize: vertical; min-height: 80px; }

    /* 連絡先セレクター */
    .mention-select-wrap {
        display: flex; flex-wrap: wrap; gap: 0.375rem; padding: 0.375rem;
        border: 1px solid var(--gray-200); border-radius: 6px;
        min-height: 38px; align-items: center; cursor: text;
    }
    .mention-select-wrap:focus-within { border-color: var(--primary); }
    .mention-select-input {
        border: none; outline: none; font-size: 0.8125rem;
        min-width: 120px; flex: 1; padding: 0.1rem 0.25rem; color: var(--gray-700);
        font-family: inherit;
    }
    .mention-chip {
        display: flex; align-items: center; gap: 0.25rem;
        padding: 0.15rem 0.5rem; background: #eff6ff; color: var(--primary);
        border-radius: 4px; font-size: 0.75rem; font-weight: 500;
    }
    .mention-chip-remove {
        border: none; background: none; cursor: pointer;
        color: inherit; opacity: 0.7; font-size: 0.875rem; line-height: 1; padding: 0;
    }
    .mention-chip-remove:hover { opacity: 1; }
    .mention-select-dropdown {
        position: fixed; background: white;
        border: 1px solid var(--gray-200); border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.14);
        z-index: 2100; min-width: 200px; max-height: 180px; overflow-y: auto; display: none;
    }
    .mention-select-option {
        padding: 0.45rem 0.875rem; cursor: pointer;
        font-size: 0.875rem; color: var(--gray-700);
    }
    .mention-select-option:hover, .mention-select-option.kbd-active { background: #eff6ff; color: var(--primary); }
    .mention-select-option.disabled { opacity: 0.4; pointer-events: none; }
    .task-mentions-row { display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap; margin-top: 0.375rem; }
    .task-mention-chip {
        font-size: 0.75rem; padding: 0.1rem 0.45rem;
        background: #eff6ff; color: var(--primary); border-radius: 4px; font-weight: 500;
    }
    .tag-input-wrap {
        display: flex; flex-wrap: wrap; gap: 0.375rem; padding: 0.375rem;
        border: 1px solid var(--gray-200); border-radius: 6px;
        min-height: 38px; align-items: center; cursor: text;
    }
    .tag-input-wrap:focus-within { border-color: var(--primary); }
    .tag-chip {
        display: flex; align-items: center; gap: 0.25rem;
        padding: 0.15rem 0.5rem; background: #eff6ff; color: var(--primary);
        border-radius: 4px; font-size: 0.75rem; font-weight: 500;
    }
    .tag-chip-remove { border: none; background: none; cursor: pointer; color: inherit; opacity: 0.7; font-size: 0.875rem; line-height: 1; padding: 0; }
    .tag-chip-remove:hover { opacity: 1; }
    .tag-text-input { border: none; outline: none; font-size: 0.8125rem; min-width: 80px; flex: 1; padding: 0.1rem 0.25rem; color: var(--gray-700); }
</style>

<div style="padding: 1.5rem;">

<div class="page-header">
    <h2>マイワークスペース</h2>
    <div class="tab-nav">
        <button class="tab-btn <?= $activeTab === 'tasks' ? 'active' : '' ?>"
                data-tab="tasks">タスク</button>
        <button class="tab-btn <?= $activeTab === 'memos' ? 'active' : '' ?>"
                data-tab="memos">メモ</button>
    </div>
</div>

<!-- ═══════════════════════════════════ タスクタブ ═══════════════════════════════════ -->
<div id="tab-tasks" class="tab-content <?= $activeTab === 'tasks' ? 'active' : '' ?>">

    <div class="task-toolbar">
        <button class="btn btn-primary btn-sm" id="addTaskBtn">+ タスクを追加</button>
        <div class="task-filter-group">
            <button class="task-filter-btn active" data-filter="all">すべて</button>
            <button class="task-filter-btn" data-filter="未着手">未着手</button>
            <button class="task-filter-btn" data-filter="進行中">進行中</button>
            <button class="task-filter-btn" data-filter="完了">完了</button>
            <button class="task-filter-btn" data-filter="mention">自分への連絡</button>
        </div>
    </div>

    <div class="task-list" id="taskList">
        <div class="task-empty">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 9h18"/><path d="M8 13h2M8 17h6"/></svg>
            <div>読み込み中...</div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════ メモタブ ═══════════════════════════════════ -->
<div id="tab-memos" class="tab-content <?= $activeTab === 'memos' ? 'active' : '' ?>">

    <div class="memo-toolbar">
        <button class="btn btn-primary btn-sm" id="addMemoBtn">+ メモを追加</button>
        <div class="memo-search-wrap">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="memo-search-input" id="memoSearchInput" placeholder="メモを検索...">
        </div>
    </div>

    <div id="memoContainer">
        <div class="memo-empty">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <div>読み込み中...</div>
        </div>
    </div>
</div>

</div><!-- /padding wrapper -->

<!-- ════════════ タスク追加/編集モーダル ════════════ -->
<div class="modal" id="taskModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="taskModalTitle">タスクを追加</h3>
            <button class="close" data-close-modal="taskModal">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editTaskId">
            <div class="form-group">
                <label for="taskTitleInput">タイトル <span style="color:#e53e3e">*</span></label>
                <input type="text" class="form-control" id="taskTitleInput" placeholder="タスク名">
            </div>
            <div class="form-group">
                <label for="taskDescInput">説明</label>
                <textarea class="form-control" id="taskDescInput" placeholder="任意の説明" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="taskStatusInput">ステータス</label>
                <select class="form-control" id="taskStatusInput">
                    <option value="未着手">未着手</option>
                    <option value="進行中">進行中</option>
                    <option value="完了">完了</option>
                </select>
            </div>
            <div class="form-group">
                <label for="taskDueDateInput">期日</label>
                <input type="date" class="form-control" id="taskDueDateInput">
            </div>
            <div class="form-group" style="position:relative">
                <label>連絡先 <span style="font-weight:400;color:var(--gray-400)">（保存時にメール通知が届きます）</span></label>
                <div class="mention-select-wrap" id="mentionSelectWrap">
                    <input type="text" class="mention-select-input" id="mentionSelectInput" placeholder="名前で検索...">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-close-modal="taskModal">キャンセル</button>
            <button class="btn btn-primary btn-sm" id="saveTaskBtn">追加</button>
        </div>
    </div>
</div>

<!-- ════════════ メモ追加/編集モーダル ════════════ -->
<div class="modal" id="memoModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="memoModalTitle">メモを追加</h3>
            <button class="close" data-close-modal="memoModal">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editMemoId">
            <div class="form-group">
                <label for="memoTitleInput">タイトル <span style="color:#e53e3e">*</span></label>
                <input type="text" class="form-control" id="memoTitleInput" placeholder="メモのタイトル">
            </div>
            <div class="form-group">
                <label for="memoContentInput">本文</label>
                <textarea class="form-control" id="memoContentInput"
                          placeholder="メモの内容を入力..." rows="10" style="min-height:200px;white-space:pre-wrap"></textarea>
            </div>
            <div class="form-group">
                <label>タグ <span style="font-weight:400;color:var(--gray-400)">(入力してEnterで追加)</span></label>
                <div class="tag-input-wrap" id="tagInputWrap">
                    <input type="text" class="tag-text-input" id="tagTextInput" placeholder="タグ名を入力...">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-close-modal="memoModal">キャンセル</button>
            <button class="btn btn-primary btn-sm" id="saveMemoBtn">追加</button>
        </div>
    </div>
</div>

<!-- 連絡先ドロップダウン -->
<div id="mentionSelectDropdown" class="mention-select-dropdown"></div>

<script<?= nonceAttr() ?>>
const CSRF_TOKEN    = '<?= htmlspecialchars(generateCsrfToken()) ?>';
const CURRENT_USER  = '<?= htmlspecialchars($currentUserEmail) ?>';
const IS_ADMIN      = <?= isAdmin() ? 'true' : 'false' ?>;
const MENTION_USERS = <?= json_encode(array_values($mentionUsers), JSON_UNESCAPED_UNICODE) ?>;

// ─── API ────────────────────────────────────────────────────────────────

async function apiGet(action) {
    const res = await fetch('/api/tasks-memos.php?action=' + encodeURIComponent(action), {
        method: 'GET', credentials: 'same-origin',
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'API エラー');
    return json.data;
}

async function apiPost(action, params) {
    const body = new FormData();
    body.append('action', action);
    body.append('csrf_token', CSRF_TOKEN);
    for (const [k, v] of Object.entries(params)) {
        if (v !== null && v !== undefined) body.append(k, v);
    }
    const res = await fetch('/api/tasks-memos.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': CSRF_TOKEN },
        credentials: 'same-origin',
        body,
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || '処理に失敗しました');
    return json.data;
}

function showMsg(msg, type) {
    if (typeof showToast === 'function') { showToast(msg, type); return; }
    if (type === 'error') alert('エラー: ' + msg);
}

// ─── タブ ────────────────────────────────────────────────────────────────

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
        history.replaceState(null, '', '?tab=' + tab);
    });
});

// ─── モーダル ─────────────────────────────────────────────────────────────

function wsOpenModal(id) { document.getElementById(id).classList.add('active'); }
function wsCloseModal(id) { document.getElementById(id).classList.remove('active'); }

document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => wsCloseModal(btn.dataset.closeModal));
});
// 背景クリックでは閉じない（×ボタン・キャンセルのみ）

// ═══════════════════════════════════════════════════════════════════════
// タスク
// ═══════════════════════════════════════════════════════════════════════

let allTasks = [];
let currentTaskFilter = 'all';
let selectedMentions = []; // 連絡先として選択されたメールアドレス配列

async function loadTasks() {
    try {
        const data = await apiGet('list_tasks');
        allTasks = data.tasks || [];
        renderTasks();
    } catch (e) {
        document.getElementById('taskList').innerHTML = '<div class="task-empty">タスクの読み込みに失敗しました</div>';
    }
}

function renderTasks() {
    const list = document.getElementById('taskList');
    let filtered;
    if (currentTaskFilter === 'all') {
        filtered = allTasks;
    } else if (currentTaskFilter === 'mention') {
        filtered = allTasks.filter(t => (t.mentions || []).includes(CURRENT_USER));
    } else {
        filtered = allTasks.filter(t => t.status === currentTaskFilter);
    }

    if (filtered.length === 0) {
        list.innerHTML = `<div class="task-empty">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 9h18"/><path d="M8 13h2M8 17h6"/>
            </svg>
            <div>タスクがありません</div>
        </div>`;
        return;
    }
    list.innerHTML = filtered.map(renderTaskCard).join('');
    bindTaskEvents();
}

function renderTaskCard(task) {
    const status  = task.status || '未着手';
    const canEdit = task.created_by === CURRENT_USER || IS_ADMIN || (task.mentions || []).includes(CURRENT_USER);

    let dueBadge = '';
    if (task.due_date) {
        const today = new Date(); today.setHours(0,0,0,0);
        const due   = new Date(task.due_date + 'T00:00:00');
        let cls = 'task-due-badge';
        if (due < today) cls += ' overdue';
        else if (due.toDateString() === today.toDateString()) cls += ' today';
        dueBadge = `<span class="${cls}">期日: ${escapeHtml(task.due_date.replace(/-/g, '/'))}</span>`;
    }

    const subs   = task.subtasks || [];
    const doneN  = subs.filter(s => s.done).length;
    const progress = subs.length > 0 ? `<span class="subtask-progress">${doneN}/${subs.length}</span>` : '';

    const subtasksHtml = subs.map(sub => `
        <div class="subtask-item">
            <input type="checkbox" ${sub.done ? 'checked' : ''}
                   class="subtask-toggle"
                   data-task-id="${escapeHtml(task.id)}"
                   data-subtask-id="${escapeHtml(sub.id)}">
            <span class="subtask-label ${sub.done ? 'done' : ''}">${escapeHtml(sub.title)}</span>
            ${canEdit ? `<button class="subtask-delete-btn" data-task-id="${escapeHtml(task.id)}" data-subtask-id="${escapeHtml(sub.id)}">&times;</button>` : ''}
        </div>`).join('');

    const addSubtaskRow = canEdit ? `
        <div class="add-subtask-row">
            <input type="text" class="add-subtask-input" placeholder="サブタスクを追加..." data-task-id="${escapeHtml(task.id)}">
            <button class="add-subtask-btn" data-task-id="${escapeHtml(task.id)}">追加</button>
        </div>` : '';

    const actionBtns = canEdit ? `
        <button class="task-action-btn edit-task-btn" data-task-id="${escapeHtml(task.id)}">編集</button>
        ${IS_ADMIN ? `<button class="task-action-btn danger delete-task-btn" data-task-id="${escapeHtml(task.id)}">削除</button>` : ''}` : '';

    const desc    = task.description ? `<div class="task-description">${escapeHtml(task.description)}</div>` : '';
    const creator = escapeHtml((task.created_by || '').split('@')[0]);
    const statusMark = status === '完了' ? '✓' : '';

    const mentionsRow = (task.mentions || []).length > 0
        ? `<div class="task-mentions-row">${(task.mentions).map(email => {
              const u = MENTION_USERS.find(u => u.email === email);
              return `<span class="task-mention-chip">👤 ${escapeHtml(u ? u.name : email.split('@')[0])}</span>`;
          }).join('')}</div>`
        : '';

    return `<div class="task-card status-${escapeHtml(status)}" data-task-id="${escapeHtml(task.id)}">
        <div class="task-card-header">
            <button class="task-status-btn status-${escapeHtml(status)}" data-task-id="${escapeHtml(task.id)}" title="ステータス変更">${statusMark}</button>
            <div class="task-main">
                <div class="task-title-row">
                    <span class="task-title">${escapeHtml(task.title)}</span>
                    ${dueBadge}
                    <span class="task-creator-chip">${creator}</span>
                    ${progress}
                </div>
                ${desc}${mentionsRow}
            </div>
            <div class="task-actions">${actionBtns}</div>
        </div>
        ${(subs.length > 0 || canEdit) ? `<div class="task-subtasks">${subtasksHtml}${addSubtaskRow}</div>` : ''}
    </div>`;
}

function bindTaskEvents() {
    // ステータスサイクル
    document.querySelectorAll('.task-status-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const task = allTasks.find(t => t.id === btn.dataset.taskId);
            if (!task) return;
            const cycle = ['未着手', '進行中', '完了'];
            const next  = cycle[(cycle.indexOf(task.status || '未着手') + 1) % cycle.length];
            try {
                const res = await apiPost('task_update', { task_id: task.id, status: next });
                updateTaskInList(res.task);
                renderTasks();
            } catch(e) { showMsg(e.message, 'error'); }
        });
    });

    // 編集
    document.querySelectorAll('.edit-task-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const task = allTasks.find(t => t.id === btn.dataset.taskId);
            if (task) openTaskModal(task);
        });
    });

    // 削除
    document.querySelectorAll('.delete-task-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('このタスクを削除しますか？')) return;
            try {
                await apiPost('task_delete', { task_id: btn.dataset.taskId });
                allTasks = allTasks.filter(t => t.id !== btn.dataset.taskId);
                renderTasks();
                showMsg('タスクを削除しました', 'success');
            } catch(e) { showMsg(e.message, 'error'); }
        });
    });

    // サブタスクチェック
    document.querySelectorAll('.subtask-toggle').forEach(cb => {
        cb.addEventListener('change', async () => {
            try {
                const res = await apiPost('task_update_subtask', {
                    task_id: cb.dataset.taskId, subtask_id: cb.dataset.subtaskId, sub_action: 'toggle'
                });
                updateTaskInList(res.task); renderTasks();
            } catch(e) { showMsg(e.message, 'error'); cb.checked = !cb.checked; }
        });
    });

    // サブタスク追加
    document.querySelectorAll('.add-subtask-btn').forEach(btn => {
        btn.addEventListener('click', () => addSubtaskFromInput(btn.dataset.taskId));
    });
    document.querySelectorAll('.add-subtask-input').forEach(inp => {
        inp.addEventListener('keydown', e => { if (e.key === 'Enter') addSubtaskFromInput(inp.dataset.taskId); });
    });

    // サブタスク削除
    document.querySelectorAll('.subtask-delete-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            try {
                const res = await apiPost('task_update_subtask', {
                    task_id: btn.dataset.taskId, subtask_id: btn.dataset.subtaskId, sub_action: 'delete'
                });
                updateTaskInList(res.task); renderTasks();
            } catch(e) { showMsg(e.message, 'error'); }
        });
    });
}

async function addSubtaskFromInput(taskId) {
    const inp = document.querySelector(`.add-subtask-input[data-task-id="${taskId}"]`);
    if (!inp) return;
    const title = inp.value.trim();
    if (!title) return;
    try {
        const res = await apiPost('task_update_subtask', { task_id: taskId, sub_action: 'add', title });
        updateTaskInList(res.task);
        inp.value = '';
        renderTasks();
    } catch(e) { showMsg(e.message, 'error'); }
}

function updateTaskInList(updated) {
    const idx = allTasks.findIndex(t => t.id === updated.id);
    if (idx >= 0) allTasks[idx] = updated; else allTasks.push(updated);
}

// フィルター
document.querySelectorAll('.task-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.task-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentTaskFilter = btn.dataset.filter;
        renderTasks();
    });
});

// タスクモーダル
document.getElementById('addTaskBtn').addEventListener('click', () => openTaskModal(null));

function openTaskModal(task) {
    const isEdit = !!task;
    document.getElementById('taskModalTitle').textContent = isEdit ? 'タスクを編集' : 'タスクを追加';
    document.getElementById('saveTaskBtn').textContent    = isEdit ? '保存' : '追加';
    document.getElementById('editTaskId').value           = task?.id || '';
    document.getElementById('taskTitleInput').value       = task?.title || '';
    document.getElementById('taskDescInput').value        = task?.description || '';
    document.getElementById('taskStatusInput').value      = task?.status || '未着手';
    document.getElementById('taskDueDateInput').value     = task?.due_date || '';
    selectedMentions = [...(task?.mentions || [])];
    renderMentionSelectChips();
    wsOpenModal('taskModal');
    document.getElementById('taskTitleInput').focus();
}

document.getElementById('saveTaskBtn').addEventListener('click', async () => {
    const title = document.getElementById('taskTitleInput').value.trim();
    if (!title) { showMsg('タイトルを入力してください', 'error'); return; }
    const taskId = document.getElementById('editTaskId').value;
    const params = {
        title,
        description: document.getElementById('taskDescInput').value.trim(),
        status:      document.getElementById('taskStatusInput').value,
        due_date:    document.getElementById('taskDueDateInput').value,
        mentions:    JSON.stringify(selectedMentions),
    };
    if (taskId) params.task_id = taskId;
    try {
        const res = await apiPost(taskId ? 'task_update' : 'task_add', params);
        updateTaskInList(res.task);
        renderTasks();
        wsCloseModal('taskModal');
        showMsg(taskId ? 'タスクを更新しました' : 'タスクを追加しました', 'success');
    } catch(e) { showMsg(e.message, 'error'); }
});

// ═══════════════════════════════════════════════════════════════════════
// メモ
// ═══════════════════════════════════════════════════════════════════════

let allMemos = [];
let memoSearchQuery = '';
let memoTags = [];

async function loadMemos() {
    try {
        const data = await apiGet('list_memos');
        allMemos = data.memos || [];
        renderMemos();
    } catch (e) {
        document.getElementById('memoContainer').innerHTML = '<div class="memo-empty">メモの読み込みに失敗しました</div>';
    }
}

function renderMemos() {
    const q = memoSearchQuery.toLowerCase();
    const filtered = q
        ? allMemos.filter(m =>
            (m.title||'').toLowerCase().includes(q) ||
            (m.content||'').toLowerCase().includes(q) ||
            (m.tags||[]).some(t => t.toLowerCase().includes(q)))
        : allMemos;

    const pinned = filtered.filter(m => m.pinned);
    const normal = filtered.filter(m => !m.pinned);
    let html = '';

    if (pinned.length > 0) {
        html += `<div class="memo-section-label">📌 ピン留め</div><div class="memo-grid">${pinned.map(renderMemoCard).join('')}</div>`;
    }
    if (normal.length > 0) {
        if (pinned.length > 0) html += `<div class="memo-section-label">すべてのメモ</div>`;
        html += `<div class="memo-grid">${normal.map(renderMemoCard).join('')}</div>`;
    }
    if (filtered.length === 0) {
        html = `<div class="memo-empty">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
            </svg>
            <div>${q ? '検索結果がありません' : 'メモがありません'}</div>
        </div>`;
    }
    document.getElementById('memoContainer').innerHTML = html;
    bindMemoEvents();
}

function renderMemoCard(memo) {
    const tags    = (memo.tags||[]).map(t => `<span class="memo-tag">${escapeHtml(t)}</span>`).join('');
    const date    = (memo.updated_at||'').substring(0, 10);
    const pinIcon = memo.pinned ? '<span class="memo-pin-icon">📌</span>' : '';
    const preview = (memo.content||'').replace(/\n+/g, ' ').substring(0, 150);

    return `<div class="memo-card ${memo.pinned ? 'pinned' : ''}" data-memo-id="${escapeHtml(memo.id)}">
        <div class="memo-card-header">
            <h3 class="memo-title">${escapeHtml(memo.title)}</h3>${pinIcon}
        </div>
        <div class="memo-preview">${escapeHtml(preview)}</div>
        <div class="memo-footer">
            <div class="memo-tags">${tags}</div>
            <span class="memo-date">${escapeHtml(date)}</span>
        </div>
        <div class="memo-card-actions">
            <button class="memo-card-action-btn memo-edit-btn" data-memo-id="${escapeHtml(memo.id)}">編集</button>
            <button class="memo-card-action-btn ${memo.pinned ? 'pin-active' : ''} memo-pin-btn" data-memo-id="${escapeHtml(memo.id)}">${memo.pinned ? '📌 解除' : '📌 留める'}</button>
            <button class="memo-card-action-btn danger memo-delete-btn" data-memo-id="${escapeHtml(memo.id)}">削除</button>
        </div>
    </div>`;
}

function bindMemoEvents() {
    document.querySelectorAll('.memo-edit-btn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const memo = allMemos.find(m => m.id === btn.dataset.memoId);
            if (memo) openMemoModal(memo);
        });
    });

    document.querySelectorAll('.memo-pin-btn').forEach(btn => {
        btn.addEventListener('click', async e => {
            e.stopPropagation();
            try {
                const res = await apiPost('memo_toggle_pin', { memo_id: btn.dataset.memoId });
                const memo = allMemos.find(m => m.id === btn.dataset.memoId);
                if (memo) {
                    memo.pinned = res.pinned;
                    allMemos.sort((a, b) => {
                        if (!!a.pinned !== !!b.pinned) return b.pinned ? 1 : -1;
                        return (b.updated_at||'') > (a.updated_at||'') ? 1 : -1;
                    });
                }
                renderMemos();
            } catch(e) { showMsg(e.message, 'error'); }
        });
    });

    document.querySelectorAll('.memo-delete-btn').forEach(btn => {
        btn.addEventListener('click', async e => {
            e.stopPropagation();
            if (!confirm('このメモを削除しますか？')) return;
            try {
                await apiPost('memo_delete', { memo_id: btn.dataset.memoId });
                allMemos = allMemos.filter(m => m.id !== btn.dataset.memoId);
                renderMemos();
                showMsg('メモを削除しました', 'success');
            } catch(e) { showMsg(e.message, 'error'); }
        });
    });
}

// メモ検索
let searchTimer;
document.getElementById('memoSearchInput').addEventListener('input', e => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { memoSearchQuery = e.target.value.trim(); renderMemos(); }, 300);
});

// メモモーダル
document.getElementById('addMemoBtn').addEventListener('click', () => openMemoModal(null));

function openMemoModal(memo) {
    const isEdit = !!memo;
    document.getElementById('memoModalTitle').textContent = isEdit ? 'メモを編集' : 'メモを追加';
    document.getElementById('saveMemoBtn').textContent    = isEdit ? '保存' : '追加';
    document.getElementById('editMemoId').value           = memo?.id || '';
    document.getElementById('memoTitleInput').value       = memo?.title || '';
    document.getElementById('memoContentInput').value     = memo?.content || '';
    memoTags = [...(memo?.tags || [])];
    renderTagChips();
    wsOpenModal('memoModal');
    document.getElementById('memoTitleInput').focus();
}

// タグ入力
function renderTagChips() {
    const wrap  = document.getElementById('tagInputWrap');
    const input = document.getElementById('tagTextInput');
    wrap.querySelectorAll('.tag-chip').forEach(c => c.remove());
    memoTags.forEach(tag => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.innerHTML = `${escapeHtml(tag)}<button class="tag-chip-remove" data-tag="${escapeHtml(tag)}">&times;</button>`;
        wrap.insertBefore(chip, input);
    });
    wrap.querySelectorAll('.tag-chip-remove').forEach(btn => {
        btn.addEventListener('click', () => { memoTags = memoTags.filter(t => t !== btn.dataset.tag); renderTagChips(); });
    });
}

document.getElementById('tagTextInput').addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = e.target.value.trim().replace(/,/g, '');
        if (val && !memoTags.includes(val)) { memoTags.push(val); renderTagChips(); }
        e.target.value = '';
    }
});
document.getElementById('tagInputWrap').addEventListener('click', () => document.getElementById('tagTextInput').focus());

// メモ保存
document.getElementById('saveMemoBtn').addEventListener('click', async () => {
    const pending = document.getElementById('tagTextInput').value.trim();
    if (pending && !memoTags.includes(pending)) { memoTags.push(pending); document.getElementById('tagTextInput').value = ''; }

    const title = document.getElementById('memoTitleInput').value.trim();
    if (!title) { showMsg('タイトルを入力してください', 'error'); return; }

    const memoId = document.getElementById('editMemoId').value;
    const params = { title, content: document.getElementById('memoContentInput').value, tags: JSON.stringify(memoTags) };
    if (memoId) params.memo_id = memoId;

    try {
        const res = await apiPost(memoId ? 'memo_update' : 'memo_add', params);
        const updated = res.memo;
        const idx = allMemos.findIndex(m => m.id === updated.id);
        if (idx >= 0) allMemos[idx] = updated; else allMemos.unshift(updated);
        allMemos.sort((a, b) => {
            if (!!a.pinned !== !!b.pinned) return b.pinned ? 1 : -1;
            return (b.updated_at||'') > (a.updated_at||'') ? 1 : -1;
        });
        renderMemos();
        wsCloseModal('memoModal');
        showMsg(memoId ? 'メモを更新しました' : 'メモを追加しました', 'success');
    } catch(e) { showMsg(e.message, 'error'); }
});

// ═══════════════════════════════════════════════════════════════════════
// 連絡先セレクター
// ═══════════════════════════════════════════════════════════════════════

function renderMentionSelectChips() {
    const wrap  = document.getElementById('mentionSelectWrap');
    const input = document.getElementById('mentionSelectInput');
    wrap.querySelectorAll('.mention-chip').forEach(c => c.remove());
    selectedMentions.forEach(email => {
        const u    = MENTION_USERS.find(u => u.email === email);
        const name = u ? u.name : email.split('@')[0];
        const chip = document.createElement('span');
        chip.className = 'mention-chip';
        chip.innerHTML = `${escapeHtml(name)}<button class="mention-chip-remove" data-email="${escapeHtml(email)}">&times;</button>`;
        wrap.insertBefore(chip, input);
    });
    wrap.querySelectorAll('.mention-chip-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            selectedMentions = selectedMentions.filter(e => e !== btn.dataset.email);
            renderMentionSelectChips();
        });
    });
}

function showMentionSelectDropdown(query) {
    const dropdown = document.getElementById('mentionSelectDropdown');
    const input    = document.getElementById('mentionSelectInput');
    const rect     = input.closest('.form-group').getBoundingClientRect();

    const hits = MENTION_USERS.filter(u =>
        !selectedMentions.includes(u.email) &&
        (query === '' || u.name.toLowerCase().includes(query.toLowerCase()))
    );

    if (hits.length === 0) { hideMentionSelectDropdown(); return; }

    dropdown.style.left  = rect.left + 'px';
    dropdown.style.top   = (rect.bottom + 2) + 'px';
    dropdown.style.width = rect.width + 'px';
    dropdown.style.display = 'block';

    dropdown.innerHTML = hits.slice(0, 10).map((u, i) =>
        `<div class="mention-select-option${i === 0 ? ' kbd-active' : ''}" data-email="${escapeHtml(u.email)}">
            ${escapeHtml(u.name)}
         </div>`
    ).join('');

    dropdown.querySelectorAll('.mention-select-option').forEach(item => {
        item.addEventListener('mousedown', e => {
            e.preventDefault();
            selectedMentions.push(item.dataset.email);
            document.getElementById('mentionSelectInput').value = '';
            renderMentionSelectChips();
            hideMentionSelectDropdown();
        });
    });
}

function hideMentionSelectDropdown() {
    document.getElementById('mentionSelectDropdown').style.display = 'none';
}

// 連絡先入力イベント初期化
(function initMentionSelect() {
    const input = document.getElementById('mentionSelectInput');
    const wrap  = document.getElementById('mentionSelectWrap');

    input.addEventListener('focus', () => showMentionSelectDropdown(input.value.trim()));
    input.addEventListener('input', () => showMentionSelectDropdown(input.value.trim()));
    input.addEventListener('blur',  () => setTimeout(hideMentionSelectDropdown, 150));

    input.addEventListener('keydown', e => {
        const dropdown = document.getElementById('mentionSelectDropdown');
        const items    = dropdown.querySelectorAll('.mention-select-option');
        const active   = dropdown.querySelector('.mention-select-option.kbd-active');
        const idx      = Array.from(items).indexOf(active);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const next = idx < items.length - 1 ? idx + 1 : 0;
            items.forEach(i => i.classList.remove('kbd-active'));
            items[next]?.classList.add('kbd-active');
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prev = idx > 0 ? idx - 1 : items.length - 1;
            items.forEach(i => i.classList.remove('kbd-active'));
            items[prev]?.classList.add('kbd-active');
        } else if (e.key === 'Enter' && active) {
            e.preventDefault();
            selectedMentions.push(active.dataset.email);
            input.value = '';
            renderMentionSelectChips();
            hideMentionSelectDropdown();
        } else if (e.key === 'Escape') {
            hideMentionSelectDropdown();
        } else if (e.key === 'Backspace' && input.value === '' && selectedMentions.length > 0) {
            selectedMentions.pop();
            renderMentionSelectChips();
        }
    });

    wrap.addEventListener('click', () => input.focus());
})();

// ─── 初期ロード ────────────────────────────────────────────────────────

loadTasks();
loadMemos();
</script>

</main>
</div>
</body>
</html>
