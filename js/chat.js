/**
 * chat.js
 * チャット共通ロジック（フルページ + ウィジェット両用）
 * window.Chat として公開
 */
(function (window) {
    'use strict';

    const API_BASE = '/api/chat.php';

    // =========================================
    // API ラッパー
    // =========================================
    const Chat = {

        csrfToken: null,
        currentUser: null,
        currentUserName: null,

        init(csrfToken, currentUser, currentUserName) {
            this.csrfToken = csrfToken;
            this.currentUser = currentUser;
            this.currentUserName = currentUserName;
        },

        async fetchGet(action, params = {}) {
            const qs = new URLSearchParams({ action, ...params }).toString();
            const res = await fetch(`${API_BASE}?${qs}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        },

        async fetchPost(action, body = {}) {
            const res = await fetch(API_BASE, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken,
                },
                body: JSON.stringify({ action, ...body }),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.error || `HTTP ${res.status}`);
            }
            return res.json();
        },

        // ルーム一覧
        async listRooms() {
            return this.fetchGet('list_rooms');
        },

        // メッセージ一覧
        async getMessages(roomId, limit = 50, before = '') {
            return this.fetchGet('get_messages', { room_id: roomId, limit, before });
        },

        // ポーリング
        async poll(roomId, since) {
            return this.fetchGet('poll', { room_id: roomId, since });
        },

        // 未読数合計
        async getUnreadCount() {
            return this.fetchGet('get_unread_count');
        },

        // 従業員一覧（DM用）
        async getEmployees() {
            return this.fetchGet('get_employees');
        },

        // メッセージ送信
        async sendMessage(roomId, content, mentions = []) {
            return this.fetchPost('send_message', { room_id: roomId, content, mentions });
        },

        // グループルーム作成
        async createRoom(name, description, members = []) {
            return this.fetchPost('create_room', { name, description, members });
        },

        // DM開始
        async createDm(targetEmail) {
            return this.fetchPost('create_dm', { target_email: targetEmail });
        },

        // メッセージ削除
        async deleteMessage(messageId) {
            return this.fetchPost('delete_message', { message_id: messageId });
        },

        // メッセージ編集
        async updateMessage(messageId, content) {
            return this.fetchPost('update_message', { message_id: messageId, content });
        },

        // 既読更新
        async markRead(roomId) {
            return this.fetchPost('mark_read', { room_id: roomId });
        },

        // =========================================
        // レンダリングヘルパー
        // =========================================

        /**
         * メッセージバブルのHTMLを生成
         * @param {Object} msg
         * @param {boolean} compact  ウィジェット用簡略表示
         */
        renderMessage(msg, compact = false) {
            const isOwn = msg.user_email === this.currentUser;
            const isDeleted = !!msg.deleted_at;
            const side = isOwn ? 'own' : 'other';

            let contentHtml;
            if (isDeleted) {
                contentHtml = '<em class="chat-deleted">このメッセージは削除されました</em>';
            } else {
                contentHtml = escapeHtml(msg.content).replace(/\n/g, '<br>');
                // @メンション強調
                contentHtml = contentHtml.replace(/(@[\w.@-]+)/g, '<span class="chat-mention">$1</span>');
                // 編集済みラベル
                if (msg.updated_at) {
                    contentHtml += ' <span class="chat-edited">(編集済み)</span>';
                }
            }

            const timeStr = msg.created_at
                ? new Date(msg.created_at.replace(' ', 'T')).toLocaleString('ja-JP', {
                    month: '2-digit', day: '2-digit',
                    hour: '2-digit', minute: '2-digit',
                  })
                : '';

            const senderLabel = isOwn ? '' : `<span class="chat-sender">${escapeHtml(msg.user_name || msg.user_email)}</span>`;

            let actionBtns = '';
            if (!isDeleted && (isOwn || window.chatIsAdmin)) {
                if (isOwn) {
                    actionBtns += `<button class="chat-msg-edit" data-id="${escapeHtml(msg.id)}" title="編集">✏</button>`;
                }
                actionBtns += `<button class="chat-msg-delete" data-id="${escapeHtml(msg.id)}" title="削除">✕</button>`;
            }

            if (compact) {
                // ウィジェット用: シンプル表示
                return `
                <div class="chat-msg chat-msg-${side}" data-id="${escapeHtml(msg.id)}">
                    <div class="chat-bubble chat-bubble-${side}">
                        ${senderLabel}
                        <div class="chat-content">${contentHtml}</div>
                        <div class="chat-time">${timeStr}</div>
                    </div>
                </div>`;
            }

            return `
            <div class="chat-msg chat-msg-${side}" data-id="${escapeHtml(msg.id)}">
                <div class="chat-bubble chat-bubble-${side}">
                    ${senderLabel}
                    <div class="chat-content">${contentHtml}</div>
                    <div class="chat-time">${timeStr}</div>
                </div>
                ${actionBtns}
            </div>`;
        },

        /**
         * ルームアイテムのHTMLを生成
         */
        renderRoomItem(room, activeRoomId) {
            const isActive = room.id === activeRoomId ? 'active' : '';
            const icon = room.type === 'dm' ? '💬' : '📢';
            const name = escapeHtml(room.display_name || room.name || 'ルーム');
            const badge = room.unread_count > 0
                ? `<span class="chat-room-badge">${Math.min(room.unread_count, 99)}</span>`
                : '';
            return `
            <div class="chat-room-item ${isActive}" data-id="${escapeHtml(room.id)}" data-type="${escapeHtml(room.type)}">
                <span class="chat-room-icon">${icon}</span>
                <span class="chat-room-name">${name}</span>
                ${badge}
            </div>`;
        },
    };

    window.Chat = Chat;

})(window);
