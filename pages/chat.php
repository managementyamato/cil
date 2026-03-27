<?php
require_once '../api/auth.php';
$csrfToken = generateCsrfToken();
$currentUser = $_SESSION['user_email'];
$currentUserName = $_SESSION['user_name'] ?? $currentUser;
$isAdmin = isAdmin();
require_once '../functions/header.php';
?>
<style<?= nonceAttr() ?>>
/* ============================================================
   チャットページ レイアウト
   ============================================================ */
/* main-contentのパディングをチャットページでは除去 */
body .main-content:has(.chat-page-wrap) {
    padding: 0;
    overflow: hidden;
}
.chat-page-wrap {
    display: flex;
    height: calc(100vh - 64px);
    overflow: hidden;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    background: white;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
}

/* ---------- ルーム一覧 ---------- */
.chat-sidebar {
    width: 240px;
    flex-shrink: 0;
    border-right: 1px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    background: var(--gray-50);
}
.chat-sidebar-header {
    padding: 1rem 0.875rem 0.5rem;
    font-weight: 700;
    font-size: 1rem;
    color: var(--gray-900);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.chat-sidebar-actions {
    display: flex;
    gap: 4px;
}
.chat-sidebar-actions button {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--gray-500);
    padding: 3px 5px;
    border-radius: 4px;
    font-size: 1rem;
    line-height: 1;
}
.chat-sidebar-actions button:hover { background: var(--gray-200); color: var(--gray-900); }
.chat-rooms-list {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem 0;
}
.chat-room-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0.875rem;
    cursor: pointer;
    border-radius: 6px;
    margin: 0 0.375rem;
    font-size: 0.875rem;
    color: var(--gray-700);
    transition: background 0.12s;
    position: relative;
}
.chat-room-item:hover { background: var(--gray-200); }
.chat-room-item.active { background: var(--primary-light); color: var(--primary-dark); font-weight: 600; }
.chat-room-icon { font-size: 0.9rem; flex-shrink: 0; }
.chat-room-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-room-badge {
    background: var(--danger); color: white;
    font-size: 0.65rem; font-weight: 700;
    min-width: 18px; height: 18px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 4px; flex-shrink: 0;
}
.chat-sidebar-section {
    padding: 0.5rem 0.875rem 0.25rem;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--gray-500);
    letter-spacing: 0.05em;
}

/* ---------- メインエリア ---------- */
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.chat-main-header {
    padding: 0.875rem 1rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
    min-height: 56px;
}
.chat-main-title { font-weight: 700; font-size: 1rem; }
.chat-main-desc { font-size: 0.8rem; color: var(--gray-500); margin-left: 0.25rem; }
.chat-messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.chat-messages-area .chat-date-divider {
    text-align: center;
    font-size: 0.75rem;
    color: var(--gray-500);
    margin: 0.75rem 0 0.25rem;
}
.chat-load-more {
    text-align: center;
    padding: 0.5rem;
}
.chat-load-more button {
    background: none;
    border: 1px solid var(--gray-300);
    border-radius: 6px;
    padding: 0.3rem 0.875rem;
    font-size: 0.8rem;
    color: var(--gray-500);
    cursor: pointer;
}
.chat-load-more button:hover { background: var(--gray-100); }

/* ---------- メッセージバブル ---------- */
.chat-msg {
    display: flex;
    align-items: flex-end;
    gap: 0.5rem;
    position: relative;
}
.chat-msg-own { flex-direction: row-reverse; }
.chat-msg-other { flex-direction: row; }
.chat-bubble {
    max-width: 70%;
    padding: 0.6rem 0.875rem;
    border-radius: 14px;
    font-size: 0.9rem;
    line-height: 1.5;
    word-break: break-word;
    position: relative;
}
.chat-bubble-own {
    background: var(--primary-light);
    border-bottom-right-radius: 4px;
}
.chat-bubble-other {
    background: var(--gray-100);
    border-bottom-left-radius: 4px;
}
.chat-sender {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--primary-dark);
    margin-bottom: 0.2rem;
}
.chat-content { color: var(--gray-900); }
.chat-time {
    font-size: 0.7rem;
    color: var(--gray-500);
    margin-top: 0.2rem;
    text-align: right;
}
.chat-deleted { color: var(--gray-500); font-style: italic; font-size: 0.85rem; }
.chat-mention { color: var(--primary); font-weight: 600; }
.chat-msg-delete {
    background: none;
    border: none;
    color: var(--gray-400);
    cursor: pointer;
    font-size: 0.75rem;
    opacity: 0;
    transition: opacity 0.15s;
    padding: 0 0.2rem;
    align-self: center;
}
.chat-msg:hover .chat-msg-delete { opacity: 1; }
.chat-msg-delete:hover { color: var(--danger); }

/* ---------- 入力エリア ---------- */
.chat-input-area {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--gray-200);
    display: flex;
    gap: 0.5rem;
    align-items: flex-end;
    flex-shrink: 0;
}
.chat-input-wrap {
    flex: 1;
    position: relative;
}
.chat-input-text {
    width: 100%;
    padding: 0.6rem 0.875rem;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    font-size: 0.9rem;
    resize: none;
    min-height: 42px;
    max-height: 150px;
    line-height: 1.5;
    font-family: inherit;
    transition: border-color 0.15s;
    box-sizing: border-box;
}
.chat-input-text:focus { outline: none; border-color: var(--primary); }
.chat-send-btn {
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.6rem 1rem;
    font-size: 0.9rem;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.15s;
    height: 42px;
    white-space: nowrap;
}
.chat-send-btn:hover { background: var(--primary-dark); }
.chat-send-btn:disabled { opacity: 0.6; cursor: not-allowed; }

/* ---------- @メンション候補ドロップダウン ---------- */
.mention-dropdown {
    position: fixed;
    background: white;
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,.15);
    min-width: 200px;
    max-width: 300px;
    z-index: 99999;
    display: none;
    overflow: hidden;
}
.mention-item {
    padding: 8px 14px;
    cursor: pointer;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid var(--gray-100, #f3f4f6);
}
.mention-item:last-child { border-bottom: none; }
.mention-item:hover, .mention-item.mention-active {
    background: var(--primary-light, #ede9fe);
}
.mention-item-avatar {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: var(--primary, #4f46e5);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 700;
    flex-shrink: 0;
}
.mention-item-name { font-weight: 600; color: #1e1e2e; }
.mention-hint {
    padding: 4px 14px 6px;
    font-size: 0.75rem;
    color: var(--gray-400, #9ca3af);
    background: var(--gray-50, #f9fafb);
    border-bottom: 1px solid var(--gray-100, #f3f4f6);
}
.chat-input-area-wrap { position: relative; }
.chat-at-hint {
    font-size: 0.72rem;
    color: var(--gray-400, #9ca3af);
    padding: 2px 0 0 2px;
}

/* ---------- @ボタン ---------- */
.chat-mention-btn {
    background: var(--gray-100, #f3f4f6);
    color: var(--primary, #4f46e5);
    border: 1px solid var(--gray-300, #d1d5db);
    border-radius: 8px;
    padding: 0 0.75rem;
    font-size: 1.05rem;
    font-weight: 800;
    cursor: pointer;
    height: 42px;
    flex-shrink: 0;
    transition: background 0.15s, border-color 0.15s;
    letter-spacing: -0.5px;
}
.chat-mention-btn:hover, .chat-mention-btn.active {
    background: var(--primary-light, #ede9fe);
    border-color: var(--primary, #4f46e5);
}

/* ---------- メンション選択パネル ---------- */
.mention-picker {
    position: absolute;
    bottom: calc(100% + 6px);
    left: 0;
    width: 260px;
    background: white;
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: 12px;
    box-shadow: 0 8px 28px rgba(0,0,0,.18);
    z-index: 99998;
    display: none;
    overflow: hidden;
    flex-direction: column;
}
.mention-picker.open { display: flex; }
.mention-picker-header {
    padding: 9px 13px 7px;
    border-bottom: 1px solid var(--gray-100, #f3f4f6);
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--primary, #4f46e5);
    background: var(--primary-light, #ede9fe);
    letter-spacing: 0.02em;
}
.mention-picker-search {
    width: 100%;
    padding: 8px 13px;
    border: none;
    border-bottom: 1px solid var(--gray-100, #f3f4f6);
    font-size: 0.875rem;
    outline: none;
    box-sizing: border-box;
    background: #fafafa;
}
.mention-picker-search::placeholder { color: var(--gray-400, #9ca3af); }
.mention-picker-list {
    max-height: 220px;
    overflow-y: auto;
}
.mention-picker-item {
    padding: 8px 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid var(--gray-100, #f3f4f6);
    transition: background 0.1s;
}
.mention-picker-item:last-child { border-bottom: none; }
.mention-picker-item:hover { background: var(--primary-light, #ede9fe); }
.mention-picker-avatar {
    width: 28px; height: 28px;
    border-radius: 50%;
    background: var(--primary, #4f46e5);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700;
    flex-shrink: 0;
}
.mention-picker-name { font-size: 0.875rem; font-weight: 600; color: #1e1e2e; }
.mention-picker-empty {
    padding: 18px;
    text-align: center;
    color: var(--gray-400, #9ca3af);
    font-size: 0.85rem;
}

/* ---------- 編集バナー ---------- */
.chat-edit-banner {
    display: none;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 1rem;
    background: var(--primary-light, #ede9fe);
    border-top: 1px solid var(--primary, #4f46e5);
    font-size: 0.8rem;
    color: var(--primary-dark, #3730a3);
    flex-shrink: 0;
}
.chat-edit-banner.active { display: flex; }
.chat-edit-banner-icon { font-size: 0.95rem; }
.chat-edit-banner-text { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-edit-banner-cancel {
    background: none; border: none; cursor: pointer;
    color: var(--gray-500); font-size: 1rem; padding: 0 2px;
    line-height: 1; flex-shrink: 0;
}
.chat-edit-banner-cancel:hover { color: var(--danger, #ef4444); }

/* ---------- 編集ボタン ---------- */
.chat-msg-edit {
    background: none; border: none; cursor: pointer;
    color: var(--gray-400); font-size: 0.8rem; padding: 2px 5px;
    border-radius: 4px; opacity: 0; transition: opacity 0.15s, color 0.15s;
    flex-shrink: 0; align-self: flex-start;
}
.chat-msg:hover .chat-msg-edit { opacity: 1; }
.chat-msg-edit:hover { color: var(--primary); background: var(--primary-light); }

/* ---------- 編集済みラベル ---------- */
.chat-edited {
    font-size: 0.7rem; color: var(--gray-400);
    font-style: normal; margin-left: 4px;
}

/* ---------- 空状態 ---------- */
.chat-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--gray-400);
    gap: 0.75rem;
    padding: 2rem;
    text-align: center;
}
.chat-empty-icon { font-size: 3rem; }
.chat-empty-text { font-size: 0.95rem; }

/* ---------- モーダル ---------- */
.chat-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 10000;
    display: flex; align-items: center; justify-content: center;
    display: none;
}
.chat-modal-overlay.open { display: flex; }
.chat-modal {
    background: white;
    border-radius: 14px;
    width: min(480px, 95vw);
    padding: 1.5rem;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.chat-modal-title {
    font-size: 1.05rem;
    font-weight: 700;
    margin-bottom: 1rem;
}
.chat-modal label { font-size: 0.85rem; font-weight: 600; color: var(--gray-700); display: block; margin-bottom: 0.25rem; }
.chat-modal input, .chat-modal textarea, .chat-modal select {
    width: 100%; padding: 0.5rem 0.75rem;
    border: 1px solid var(--gray-300); border-radius: 6px;
    font-size: 0.9rem; box-sizing: border-box; margin-bottom: 0.875rem;
}
.chat-modal input:focus, .chat-modal textarea:focus { outline: none; border-color: var(--primary); }
.chat-modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem; }
.chat-modal-actions button { padding: 0.5rem 1.25rem; border-radius: 7px; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none; }
.chat-modal-cancel { background: var(--gray-100); color: var(--gray-700); }
.chat-modal-cancel:hover { background: var(--gray-200); }
.chat-modal-submit { background: var(--primary); color: white; }
.chat-modal-submit:hover { background: var(--primary-dark); }

@media (max-width: 640px) {
    .chat-sidebar { width: 56px; }
    .chat-sidebar-header span, .chat-room-name, .chat-sidebar-section { display: none; }
    .chat-sidebar-actions { justify-content: center; }
    .chat-room-item { justify-content: center; padding: 0.6rem; }
    .chat-room-icon { font-size: 1.3rem; }
    .chat-room-badge { position: absolute; top: 2px; right: 2px; }
}
</style>

<script<?= nonceAttr() ?>>
window.chatCsrfToken = '<?= $csrfToken ?>';
window.chatCurrentUser = '<?= htmlspecialchars($currentUser) ?>';
window.chatCurrentUserName = '<?= htmlspecialchars($currentUserName) ?>';
window.chatIsAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
</script>

<div class="chat-page-wrap">
    <!-- サイドバー: ルーム一覧 -->
    <aside class="chat-sidebar">
        <div class="chat-sidebar-header">
            <span>チャット</span>
            <div class="chat-sidebar-actions">
                <?php if ($isAdmin): ?>
                <button id="chatCreateRoomBtn" title="グループ作成">＋</button>
                <?php endif; ?>
                <button id="chatStartDmBtn" title="DM開始">✉</button>
            </div>
        </div>
        <div class="chat-rooms-list" id="chatRoomsList">
            <div style="padding: 1rem; color: var(--gray-400); font-size:0.85rem;">読み込み中...</div>
        </div>
    </aside>

    <!-- メインエリア -->
    <div class="chat-main" id="chatMain">
        <!-- ルーム未選択 -->
        <div class="chat-empty" id="chatNoRoom">
            <div class="chat-empty-icon">💬</div>
            <div class="chat-empty-text">左のリストからルームを選んで<br>チャットを始めましょう</div>
        </div>

        <!-- ルーム選択後に表示 -->
        <div id="chatRoomView" style="display:none; flex-direction:column; height:100%;">
            <div class="chat-main-header" id="chatMainHeader">
                <span class="chat-main-title" id="chatMainTitle"></span>
                <span class="chat-main-desc" id="chatMainDesc"></span>
            </div>
            <div class="chat-messages-area" id="chatMessagesArea">
                <div class="chat-load-more" id="chatLoadMore" style="display:none;">
                    <button id="chatLoadMoreBtn">さらに読み込む</button>
                </div>
            </div>
            <!-- 編集バナー -->
            <div class="chat-edit-banner" id="chatEditBanner">
                <span class="chat-edit-banner-icon">✏️</span>
                <span class="chat-edit-banner-text" id="chatEditBannerText">メッセージを編集中...</span>
                <button class="chat-edit-banner-cancel" id="chatEditCancel" title="編集をキャンセル">✕</button>
            </div>
            <div class="chat-input-area chat-input-area-wrap">
                <!-- メンション選択パネル -->
                <div class="mention-picker" id="mentionPicker">
                    <div class="mention-picker-header">@ メンションする相手を選択</div>
                    <input type="text" class="mention-picker-search" id="mentionPickerSearch" placeholder="名前で検索...">
                    <div class="mention-picker-list" id="mentionPickerList">
                        <div class="mention-picker-empty">読み込み中...</div>
                    </div>
                </div>
                <button class="chat-mention-btn" id="chatMentionBtn" title="メンションを追加">@</button>
                <div class="chat-input-wrap">
                    <textarea class="chat-input-text" id="chatInputText"
                              placeholder="メッセージを入力... (Shift+Enter で改行、@ でメンション)" rows="1"></textarea>
                    <div class="chat-at-hint" id="chatAtHint" style="display:none;">↑ / ↓ で選択、Enter で確定、Esc でキャンセル</div>
                </div>
                <button class="chat-send-btn" id="chatSendBtn">送信</button>
            </div>
        </div>
    </div>
</div>

<!-- グループ作成モーダル -->
<?php if ($isAdmin): ?>
<div class="chat-modal-overlay" id="createRoomModal">
    <div class="chat-modal">
        <div class="chat-modal-title">グループルーム作成</div>
        <label>ルーム名 <span style="color:var(--danger)">*</span></label>
        <input type="text" id="newRoomName" placeholder="例: 営業チーム" maxlength="100">
        <label>説明（任意）</label>
        <input type="text" id="newRoomDesc" placeholder="このルームの説明..." maxlength="300">
        <div class="chat-modal-actions">
            <button class="chat-modal-cancel" id="createRoomCancel">キャンセル</button>
            <button class="chat-modal-submit" id="createRoomSubmit">作成</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- DM開始モーダル -->
<div class="chat-modal-overlay" id="startDmModal">
    <div class="chat-modal">
        <div class="chat-modal-title">DM開始</div>
        <label>メッセージを送る相手</label>
        <select id="dmTargetSelect">
            <option value="">選択してください</option>
        </select>
        <div class="chat-modal-actions">
            <button class="chat-modal-cancel" id="startDmCancel">キャンセル</button>
            <button class="chat-modal-submit" id="startDmSubmit">開始</button>
        </div>
    </div>
</div>

<script src="/js/chat.js" defer></script>
<script<?= nonceAttr() ?>>
document.addEventListener('DOMContentLoaded', function () {
    Chat.init(window.chatCsrfToken, window.chatCurrentUser, window.chatCurrentUserName);

    let activeRoomId = null;
    let rooms = [];
    let oldestMsgAt = null;
    let latestMsgAt = null;
    let pollTimer = null;
    let sending = false;
    let editingMsgId = null;

    // =========================================
    // 初期化
    // =========================================
    async function init() {
        await loadRooms();
        // URL の #roomId からルームを開く
        const hash = location.hash.replace('#', '');
        if (hash && rooms.find(r => r.id === hash)) {
            openRoom(hash);
        }
        // 未読数ポーリング（ルーム未選択時）
        setInterval(() => { if (!activeRoomId) loadRooms(); }, 30000);
    }

    // =========================================
    // ルーム一覧読み込み
    // =========================================
    async function loadRooms() {
        try {
            const res = await Chat.listRooms();
            if (!res.success) return;
            rooms = res.data.rooms || [];
            renderRooms();
        } catch (e) {
            console.error('ルーム読み込みエラー:', e);
        }
    }

    function renderRooms() {
        const el = document.getElementById('chatRoomsList');
        if (!rooms.length) {
            el.innerHTML = '<div style="padding:1rem;color:var(--gray-400);font-size:0.85rem;">ルームがありません</div>';
            return;
        }
        const groups = rooms.filter(r => r.type === 'group');
        const dms    = rooms.filter(r => r.type === 'dm');
        let html = '';

        if (groups.length) {
            html += '<div class="chat-sidebar-section">グループ</div>';
            groups.forEach(r => { html += Chat.renderRoomItem(r, activeRoomId); });
        }
        if (dms.length) {
            html += '<div class="chat-sidebar-section">ダイレクトメッセージ</div>';
            dms.forEach(r => { html += Chat.renderRoomItem(r, activeRoomId); });
        }
        el.innerHTML = html;

        el.querySelectorAll('.chat-room-item').forEach(item => {
            item.addEventListener('click', () => openRoom(item.dataset.id));
        });
    }

    // =========================================
    // ルームを開く
    // =========================================
    async function openRoom(roomId) {
        stopPoll();
        activeRoomId = roomId;
        oldestMsgAt = null;
        latestMsgAt = null;
        if (typeof resetMentionState === 'function') resetMentionState();

        location.hash = roomId;

        const room = rooms.find(r => r.id === roomId);
        if (!room) return;

        document.getElementById('chatNoRoom').style.display = 'none';
        const view = document.getElementById('chatRoomView');
        view.style.display = 'flex';

        document.getElementById('chatMainTitle').textContent = room.display_name || room.name || 'チャット';
        document.getElementById('chatMainDesc').textContent  = room.description || '';

        renderRooms(); // アクティブ状態を更新

        const area = document.getElementById('chatMessagesArea');
        area.innerHTML = '<div style="text-align:center;padding:1rem;color:var(--gray-400);">読み込み中...</div>';

        try {
            const res = await Chat.getMessages(roomId, 50);
            if (!res.success) return;
            const messages = res.data.messages || [];

            area.innerHTML = '';
            addLoadMoreButton(area, messages.length >= 50);

            messages.forEach(msg => appendMessage(area, msg));
            if (messages.length) {
                oldestMsgAt = messages[0].created_at;
                latestMsgAt = messages[messages.length - 1].created_at;
                scrollToBottom(area);
            }

            await Chat.markRead(roomId);
            // バッジをクリア
            const roomEl = document.querySelector(`.chat-room-item[data-id="${CSS.escape(roomId)}"] .chat-room-badge`);
            if (roomEl) roomEl.remove();
        } catch (e) {
            area.innerHTML = '<div style="padding:1rem;color:var(--danger);">読み込みに失敗しました</div>';
        }

        startPoll();
    }

    function appendMessage(area, msg, prepend = false) {
        const div = document.createElement('div');
        div.innerHTML = Chat.renderMessage(msg);
        const node = div.firstElementChild;

        // 削除ボタンのイベント
        node.querySelector('.chat-msg-delete')?.addEventListener('click', async (e) => {
            e.stopPropagation();
            if (!confirm('このメッセージを削除しますか？')) return;
            try {
                await Chat.deleteMessage(msg.id);
                const bubble = node.querySelector('.chat-content');
                if (bubble) bubble.innerHTML = '<em class="chat-deleted">このメッセージは削除されました</em>';
                node.querySelector('.chat-msg-delete')?.remove();
                node.querySelector('.chat-msg-edit')?.remove();
                msg.deleted_at = new Date().toISOString();
                if (editingMsgId === msg.id) cancelEdit();
            } catch (e) {
                if (typeof showToast === 'function') showToast('削除に失敗しました', 'danger');
            }
        });

        // 編集ボタンのイベント
        node.querySelector('.chat-msg-edit')?.addEventListener('click', (e) => {
            e.stopPropagation();
            startEditMessage(msg.id, node);
        });

        if (prepend) {
            const loadMore = document.getElementById('chatLoadMore');
            if (loadMore) loadMore.after(node);
            else area.insertBefore(node, area.firstChild);
        } else {
            area.appendChild(node);
        }
    }

    function addLoadMoreButton(area, show) {
        let el = document.getElementById('chatLoadMore');
        if (!el) {
            el = document.createElement('div');
            el.id = 'chatLoadMore';
            el.className = 'chat-load-more';
            el.innerHTML = '<button id="chatLoadMoreBtn">さらに読み込む</button>';
            area.insertBefore(el, area.firstChild);
            el.querySelector('#chatLoadMoreBtn').addEventListener('click', loadMore);
        }
        el.style.display = show ? '' : 'none';
    }

    async function loadMore() {
        if (!activeRoomId || !oldestMsgAt) return;
        try {
            const res = await Chat.getMessages(activeRoomId, 50, oldestMsgAt);
            if (!res.success) return;
            const messages = res.data.messages || [];
            const area = document.getElementById('chatMessagesArea');
            messages.forEach(msg => appendMessage(area, msg, true));
            if (messages.length) oldestMsgAt = messages[0].created_at;
            addLoadMoreButton(area, messages.length >= 50);
        } catch (e) {
            console.error('さらに読み込みエラー:', e);
        }
    }

    function scrollToBottom(el) {
        el.scrollTop = el.scrollHeight;
    }

    // =========================================
    // ポーリング
    // =========================================
    function startPoll() {
        pollTimer = setInterval(doPoll, 3000);
    }

    function stopPoll() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = null;
    }

    async function doPoll() {
        if (!activeRoomId) return;
        try {
            const res = await Chat.poll(activeRoomId, latestMsgAt || '');
            if (!res.success) return;
            const newMsgs = res.data.messages || [];
            if (!newMsgs.length) return;

            const area = document.getElementById('chatMessagesArea');
            const isAtBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 80;

            newMsgs.forEach(msg => {
                // 自分が送ったものは既に画面にある場合スキップ
                if (document.querySelector(`.chat-msg[data-id="${CSS.escape(msg.id)}"]`)) return;
                appendMessage(area, msg);
                if (msg.created_at > (latestMsgAt || '')) latestMsgAt = msg.created_at;
            });

            if (isAtBottom) scrollToBottom(area);
            await Chat.markRead(activeRoomId);
        } catch (e) { /* 無視 */ }
    }

    // =========================================
    // メッセージ送信
    // =========================================
    document.getElementById('chatSendBtn').addEventListener('click', sendMessage);

    // =========================================
    // @メンション オートコンプリート
    // =========================================
    let employeesCache = null;
    let mentionDropdown = null;
    let mentionActiveIdx = -1;
    let pendingMentions = {}; // { 名前: email }

    async function loadEmployees() {
        if (employeesCache) return employeesCache;
        try {
            const res = await Chat.getEmployees();
            employeesCache = res.data?.employees || [];
        } catch (e) { employeesCache = []; }
        return employeesCache;
    }

    function getMentionContext(textarea) {
        const pos = textarea.selectionStart;
        const text = textarea.value.substring(0, pos);
        const m = text.match(/@([^\s@]*)$/);
        if (!m) return null;
        return { query: m[1], atPos: pos - m[0].length };
    }

    function getOrCreateDropdown() {
        if (!mentionDropdown) {
            mentionDropdown = document.createElement('div');
            mentionDropdown.className = 'mention-dropdown';
            mentionDropdown.id = 'mentionDropdown';
            document.body.appendChild(mentionDropdown);
        }
        return mentionDropdown;
    }

    function hideMentionDropdown() {
        if (mentionDropdown) mentionDropdown.style.display = 'none';
        mentionActiveIdx = -1;
        document.getElementById('chatAtHint').style.display = 'none';
    }

    function showMentionDropdown(employees, query, textarea) {
        const filtered = employees.filter(e =>
            e.name.includes(query) || e.email.toLowerCase().includes(query.toLowerCase())
        ).slice(0, 8);

        const dd = getOrCreateDropdown();
        if (filtered.length === 0) { hideMentionDropdown(); return; }

        dd.innerHTML =
            '<div class="mention-hint">@メンション候補</div>' +
            filtered.map((e, i) => {
                const initial = (e.name || '?').charAt(0);
                return `<div class="mention-item" data-idx="${i}" data-name="${escapeHtml(e.name)}" data-email="${escapeHtml(e.email)}">
                    <span class="mention-item-avatar">${escapeHtml(initial)}</span>
                    <span class="mention-item-name">${escapeHtml(e.name)}</span>
                </div>`;
            }).join('');

        // ドロップダウンをテキストエリアの上に表示
        const rect = textarea.getBoundingClientRect();
        dd.style.display = 'block';
        const ddH = dd.offsetHeight;
        dd.style.left = rect.left + 'px';
        dd.style.top = (rect.top - ddH - 4) + 'px';
        mentionActiveIdx = -1;
        document.getElementById('chatAtHint').style.display = 'block';

        dd.querySelectorAll('.mention-item').forEach(item => {
            item.addEventListener('mousedown', e => {
                e.preventDefault();
                insertMention(textarea, item.dataset.name, item.dataset.email);
            });
        });
    }

    function setMentionActive(idx) {
        if (!mentionDropdown) return;
        const items = mentionDropdown.querySelectorAll('.mention-item');
        items.forEach((el, i) => el.classList.toggle('mention-active', i === idx));
        mentionActiveIdx = idx;
    }

    function insertMention(textarea, name, email) {
        const ctx = getMentionContext(textarea);
        if (!ctx) return;
        const before = textarea.value.substring(0, ctx.atPos);
        const after  = textarea.value.substring(textarea.selectionStart);
        const insert = '@' + name + ' ';
        textarea.value = before + insert + after;
        const newPos = before.length + insert.length;
        textarea.setSelectionRange(newPos, newPos);
        pendingMentions[name] = email;
        hideMentionDropdown();
        textarea.focus();
        // 高さリセット
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px';
    }

    document.getElementById('chatInputText').addEventListener('keydown', function (e) {
        // ドロップダウンが表示中のキー操作
        if (mentionDropdown && mentionDropdown.style.display !== 'none') {
            const items = mentionDropdown.querySelectorAll('.mention-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setMentionActive(Math.min(mentionActiveIdx + 1, items.length - 1));
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                setMentionActive(Math.max(mentionActiveIdx - 1, 0));
                return;
            }
            if (e.key === 'Enter' && mentionActiveIdx >= 0) {
                e.preventDefault();
                const item = items[mentionActiveIdx];
                if (item) insertMention(this, item.dataset.name, item.dataset.email);
                return;
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                hideMentionDropdown();
                return;
            }
        }
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });

    document.getElementById('chatInputText').addEventListener('input', async function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 150) + 'px';

        const ctx = getMentionContext(this);
        if (ctx !== null) {
            const employees = await loadEmployees();
            showMentionDropdown(employees, ctx.query, this);
        } else {
            hideMentionDropdown();
        }
    });

    document.getElementById('chatInputText').addEventListener('blur', () => {
        // mousedown で insertMention が先に動くよう少し遅らせる
        setTimeout(hideMentionDropdown, 150);
    });

    // =========================================
    // @ボタン → メンション選択パネル
    // =========================================
    const mentionPickerBtn  = document.getElementById('chatMentionBtn');
    const mentionPicker     = document.getElementById('mentionPicker');
    const mentionPickerSearch = document.getElementById('mentionPickerSearch');
    const mentionPickerList = document.getElementById('mentionPickerList');
    let pickerEmployees = [];

    function renderPickerList(list) {
        if (list.length === 0) {
            mentionPickerList.innerHTML = '<div class="mention-picker-empty">該当する社員が見つかりません</div>';
            return;
        }
        mentionPickerList.innerHTML = list.map(e => {
            const initial = (e.name || '?').charAt(0);
            return `<div class="mention-picker-item" data-name="${escapeHtml(e.name)}" data-email="${escapeHtml(e.email)}">
                <span class="mention-picker-avatar">${escapeHtml(initial)}</span>
                <span class="mention-picker-name">${escapeHtml(e.name)}</span>
            </div>`;
        }).join('');
        mentionPickerList.querySelectorAll('.mention-picker-item').forEach(item => {
            item.addEventListener('click', () => {
                const name  = item.dataset.name;
                const email = item.dataset.email;
                const textarea = document.getElementById('chatInputText');
                const pos  = textarea.selectionStart;
                const text = textarea.value;
                // カーソル直前がスペース/改行でなければ半角スペースを補う
                const before = text.substring(0, pos);
                const after  = text.substring(pos);
                const prefix = (before.length > 0 && !/[\s]$/.test(before)) ? ' ' : '';
                const insert = prefix + '@' + name + ' ';
                textarea.value = before + insert + after;
                const newPos = before.length + insert.length;
                textarea.setSelectionRange(newPos, newPos);
                pendingMentions[name] = email;
                closeMentionPicker();
                textarea.focus();
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px';
            });
        });
    }

    function closeMentionPicker() {
        mentionPicker.classList.remove('open');
        mentionPickerBtn.classList.remove('active');
    }

    mentionPickerBtn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const isOpen = mentionPicker.classList.contains('open');
        if (isOpen) { closeMentionPicker(); return; }

        mentionPickerList.innerHTML = '<div class="mention-picker-empty">読み込み中...</div>';
        mentionPicker.classList.add('open');
        mentionPickerBtn.classList.add('active');
        mentionPickerSearch.value = '';

        pickerEmployees = await loadEmployees();
        renderPickerList(pickerEmployees);
        mentionPickerSearch.focus();
    });

    mentionPickerSearch.addEventListener('input', () => {
        const q = mentionPickerSearch.value.trim();
        const filtered = q
            ? pickerEmployees.filter(e => e.name.includes(q) || e.email.toLowerCase().includes(q.toLowerCase()))
            : pickerEmployees;
        renderPickerList(filtered);
    });

    // パネル外クリックで閉じる
    document.addEventListener('click', (e) => {
        if (!mentionPicker.contains(e.target) && e.target !== mentionPickerBtn) {
            closeMentionPicker();
        }
    });

    // ルーム切り替え時に下書きをリセット
    function resetMentionState() {
        pendingMentions = {};
        hideMentionDropdown();
        closeMentionPicker();
    }

    async function sendMessage() {
        if (sending || !activeRoomId) return;
        const input = document.getElementById('chatInputText');
        const content = input.value.trim();
        if (!content) return;

        sending = true;
        document.getElementById('chatSendBtn').disabled = true;

        // 編集モード
        if (editingMsgId) {
            try {
                const res = await Chat.updateMessage(editingMsgId, content);
                if (res.success) {
                    // DOM上のメッセージを更新
                    const node = document.querySelector(`.chat-msg[data-id="${CSS.escape(editingMsgId)}"]`);
                    if (node) {
                        const contentEl = node.querySelector('.chat-content');
                        if (contentEl) {
                            let html = escapeHtml(content).replace(/\n/g, '<br>');
                            html = html.replace(/(@[\w.@-]+)/g, '<span class="chat-mention">$1</span>');
                            html += ' <span class="chat-edited">(編集済み)</span>';
                            contentEl.innerHTML = html;
                        }
                    }
                    cancelEdit();
                }
            } catch (e) {
                if (typeof showToast === 'function') showToast('編集に失敗しました: ' + e.message, 'danger');
            } finally {
                sending = false;
                document.getElementById('chatSendBtn').disabled = false;
                input.focus();
            }
            return;
        }

        // 新規送信モード
        // 本文中に含まれているメンションのみ抽出
        const mentions = Object.entries(pendingMentions)
            .filter(([name]) => content.includes('@' + name))
            .map(([, email]) => email);
        pendingMentions = {};

        try {
            const res = await Chat.sendMessage(activeRoomId, content, mentions);
            if (res.success) {
                input.value = '';
                input.style.height = 'auto';
                const msg = res.data.message;
                if (msg) {
                    const area = document.getElementById('chatMessagesArea');
                    // 重複チェック
                    if (!document.querySelector(`.chat-msg[data-id="${CSS.escape(msg.id)}"]`)) {
                        appendMessage(area, msg);
                        scrollToBottom(area);
                    }
                    if (msg.created_at > (latestMsgAt || '')) latestMsgAt = msg.created_at;
                }
            }
        } catch (e) {
            if (typeof showToast === 'function') showToast('送信に失敗しました: ' + e.message, 'danger');
        } finally {
            sending = false;
            document.getElementById('chatSendBtn').disabled = false;
            input.focus();
        }
    }

    // 編集開始
    function startEditMessage(msgId, node) {
        const contentEl = node.querySelector('.chat-content');
        // 削除済みは不可
        if (contentEl?.querySelector('.chat-deleted')) return;

        // 現在のテキストを取得（HTMLから平文へ）
        const tmp = document.createElement('div');
        tmp.innerHTML = contentEl ? contentEl.innerHTML : '';
        // <br> → \n、<span>等を除去
        tmp.querySelectorAll('br').forEach(br => br.replaceWith('\n'));
        tmp.querySelectorAll('.chat-edited').forEach(el => el.remove());
        tmp.querySelectorAll('.chat-mention').forEach(el => el.replaceWith(el.textContent));
        const currentText = tmp.textContent;

        editingMsgId = msgId;

        const input = document.getElementById('chatInputText');
        input.value = currentText;
        input.style.height = 'auto';
        input.style.height = input.scrollHeight + 'px';
        input.focus();

        // バナー表示
        const banner = document.getElementById('chatEditBanner');
        const bannerText = document.getElementById('chatEditBannerText');
        bannerText.textContent = '編集中: ' + currentText.slice(0, 60) + (currentText.length > 60 ? '…' : '');
        banner.classList.add('active');

        // 送信ボタンのラベルを変更
        document.getElementById('chatSendBtn').textContent = '更新';
    }

    // 編集キャンセル
    function cancelEdit() {
        editingMsgId = null;
        const input = document.getElementById('chatInputText');
        input.value = '';
        input.style.height = 'auto';
        document.getElementById('chatEditBanner').classList.remove('active');
        document.getElementById('chatSendBtn').textContent = '送信';
        resetMentionState();
    }

    // 編集キャンセルボタン
    document.getElementById('chatEditCancel').addEventListener('click', cancelEdit);

    // =========================================
    // グループ作成モーダル（admin）
    // =========================================
    document.getElementById('chatCreateRoomBtn')?.addEventListener('click', () => {
        document.getElementById('newRoomName').value = '';
        document.getElementById('newRoomDesc').value = '';
        document.getElementById('createRoomModal').classList.add('open');
    });
    document.getElementById('createRoomCancel')?.addEventListener('click', () => {
        document.getElementById('createRoomModal').classList.remove('open');
    });
    document.getElementById('createRoomSubmit')?.addEventListener('click', async () => {
        const name = document.getElementById('newRoomName').value.trim();
        const desc = document.getElementById('newRoomDesc').value.trim();
        if (!name) { alert('ルーム名を入力してください'); return; }
        try {
            const res = await Chat.createRoom(name, desc);
            if (res.success) {
                document.getElementById('createRoomModal').classList.remove('open');
                await loadRooms();
                openRoom(res.data.room.id);
            }
        } catch (e) {
            alert('作成に失敗しました: ' + e.message);
        }
    });

    // =========================================
    // DM開始モーダル
    // =========================================
    document.getElementById('chatStartDmBtn').addEventListener('click', async () => {
        const sel = document.getElementById('dmTargetSelect');
        sel.innerHTML = '<option value="">選択してください</option>';
        try {
            const res = await Chat.getEmployees();
            (res.data.employees || []).forEach(emp => {
                sel.innerHTML += `<option value="${escapeHtml(emp.email)}">${escapeHtml(emp.name)}</option>`;
            });
        } catch (e) {}
        document.getElementById('startDmModal').classList.add('open');
    });
    document.getElementById('startDmCancel').addEventListener('click', () => {
        document.getElementById('startDmModal').classList.remove('open');
    });
    document.getElementById('startDmSubmit').addEventListener('click', async () => {
        const email = document.getElementById('dmTargetSelect').value;
        if (!email) { alert('相手を選択してください'); return; }
        try {
            const res = await Chat.createDm(email);
            if (res.success) {
                document.getElementById('startDmModal').classList.remove('open');
                await loadRooms();
                openRoom(res.data.room.id);
            }
        } catch (e) {
            alert('DM開始に失敗しました: ' + e.message);
        }
    });

    // 背景クリックでは閉じない（×ボタン・キャンセルのみ）

    init();
});
</script>
<?php require_once '../functions/footer.php'; ?>
