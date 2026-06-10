<?php
/**
 * メール作成・送信ページ
 * Gmail API 経由でメールを送信する
 */
require_once '../api/auth.php';
require_once '../api/google-gmail.php';
setSecurityHeaders();

$csrfToken = generateCsrfToken();
$gmailConfigured = (new GoogleGmailClient())->isConfigured();

// アドレス帳: 従業員 + 連絡先マスタ
$data = getData();
$addressBook = [];
foreach ($data['employees'] ?? [] as $emp) {
    if (empty($emp['email']) || !empty($emp['leave_date']) || !empty($emp['deleted_at'])) continue;
    $addressBook[] = [
        'name'  => $emp['name'] ?? '',
        'email' => $emp['email'],
        'dept'  => $emp['department'] ?? '',
    ];
}
foreach ($data['contact_masters'] ?? [] as $cm) {
    if (empty($cm['email'])) continue;
    $exists = false;
    foreach ($addressBook as $a) {
        if ($a['email'] === $cm['email']) { $exists = true; break; }
    }
    if ($exists) continue;
    $addressBook[] = [
        'name'  => $cm['name'] ?? '',
        'email' => $cm['email'],
        'dept'  => $cm['department'] ?? '',
    ];
}
usort($addressBook, fn($a, $b) => strcmp($a['name'], $b['name']));

// URLパラメータから初期値
$initialTo = $_GET['to'] ?? '';
$initialSubject = $_GET['subject'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>メール作成</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">
    <link rel="stylesheet" href="/style.css?v=20260206">
    <link rel="stylesheet" href="/css/components.css?v=20260211">
    <style<?= nonceAttr() ?>>
    .email-wrap { max-width: 900px; margin: 0 auto; }
    .email-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--gray-200); margin-bottom: 1.5rem; }
    .email-tab {
        padding: 0.75rem 1.5rem; border: none; background: transparent;
        font-size: 0.9375rem; font-weight: 500; color: var(--gray-600);
        cursor: pointer; border-bottom: 2px solid transparent;
        transition: color 0.15s, border-color 0.15s;
    }
    .email-tab:hover { color: var(--gray-900); }
    .email-tab.active { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }
    .email-panel { display: none; }
    .email-panel.active { display: block; }

    /* 作成フォーム */
    .compose-card {
        background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .compose-header {
        background: var(--gray-50); padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        font-weight: 600; font-size: 1rem; color: var(--gray-800);
        display: flex; align-items: center; gap: 0.5rem;
    }
    .compose-body { padding: 1.5rem; }
    .compose-row { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; }
    .compose-label {
        min-width: 60px; padding-top: 0.5rem;
        font-size: 0.875rem; font-weight: 600; color: var(--gray-600);
        text-align: right; flex-shrink: 0;
    }
    .compose-field { flex: 1; }

    /* 宛先タグ */
    .to-tags-area {
        display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center;
        min-height: 40px; padding: 0.35rem 0.5rem;
        border: 1px solid var(--gray-300); border-radius: 6px; background: #f9fafb;
        cursor: text;
    }
    .to-tags-area:focus-within { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(17,122,101,0.1); }
    .to-tag {
        display: inline-flex; align-items: center; gap: 0.25rem;
        background: var(--primary-light, #e8f0fe); color: var(--primary);
        padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8125rem; font-weight: 500;
    }
    .to-tag-remove {
        background: none; border: none; color: var(--primary); cursor: pointer;
        font-size: 1rem; line-height: 1; padding: 0 0.15rem; opacity: 0.7;
    }
    .to-tag-remove:hover { opacity: 1; }
    .to-input {
        border: none; outline: none; background: transparent;
        font-size: 0.875rem; flex: 1; min-width: 120px; padding: 0.25rem 0;
    }

    /* アドレス帳ドロップダウン */
    .addr-dropdown {
        position: absolute; z-index: 100; top: 100%; left: 0; right: 0;
        background: white; border: 1px solid var(--gray-200); border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.12); max-height: 240px; overflow-y: auto;
        display: none;
    }
    .addr-dropdown.show { display: block; }
    .addr-item {
        padding: 0.5rem 0.75rem; cursor: pointer; font-size: 0.875rem;
        display: flex; justify-content: space-between; align-items: center;
    }
    .addr-item:hover { background: var(--gray-50); }
    .addr-item-name { font-weight: 500; color: var(--gray-800); }
    .addr-item-email { color: var(--gray-500); font-size: 0.8rem; }
    .addr-item-dept { color: var(--gray-400); font-size: 0.75rem; margin-left: 0.5rem; }

    /* 本文 */
    .compose-textarea {
        width: 100%; min-height: 300px; padding: 1rem;
        border: 1px solid var(--gray-300); border-radius: 6px;
        font-size: 0.9375rem; line-height: 1.7; resize: vertical;
        font-family: inherit;
    }
    .compose-textarea:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px rgba(17,122,101,0.1); }

    /* 送信ボタン */
    .compose-footer {
        padding: 1rem 1.5rem; border-top: 1px solid var(--gray-100);
        display: flex; justify-content: flex-end; gap: 0.75rem; align-items: center;
    }
    .btn-send {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: var(--primary); color: white; border: none; border-radius: 8px;
        padding: 0.6rem 1.5rem; font-size: 0.9375rem; font-weight: 600;
        cursor: pointer; transition: background 0.15s;
    }
    .btn-send:hover { background: var(--primary-dark, #0e6655); }
    .btn-send:disabled { opacity: 0.5; cursor: not-allowed; }

    /* 送信履歴 */
    .log-table { width: 100%; border-collapse: collapse; }
    .log-table th {
        background: var(--gray-50); padding: 0.75rem 0.5rem; text-align: left;
        font-size: 0.8125rem; font-weight: 600; color: var(--gray-600);
        border-bottom: 2px solid var(--gray-200);
    }
    .log-table td {
        padding: 0.75rem 0.5rem; font-size: 0.875rem;
        border-bottom: 1px solid var(--gray-100);
    }
    .log-table tr:hover { background: var(--gray-50); }
    .log-subject { font-weight: 500; color: var(--gray-800); }
    .log-to { color: var(--gray-600); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .log-date { color: var(--gray-500); font-size: 0.8125rem; white-space: nowrap; }

    /* Gmail未連携 */
    .gmail-not-configured {
        text-align: center; padding: 4rem 2rem;
        background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .gmail-not-configured p { color: var(--gray-500); margin-bottom: 1.5rem; font-size: 1rem; }
    </style>
</head>
<body>
    <?php include '../functions/header.php'; ?>

    <div class="page-container">
        <div class="email-wrap">
            <div class="page-header mb-2">
                <h2>メール</h2>
            </div>

            <?php if (!$gmailConfigured): ?>
            <div class="gmail-not-configured">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" stroke-width="1.5" style="margin-bottom:1rem;">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                <p>メール送信にはGmail連携が必要です</p>
                <?php if (isAdmin()): ?>
                <a href="/pages/settings.php?tab=gmail" class="btn btn-primary">Gmail連携を設定</a>
                <?php else: ?>
                <p style="font-size:0.875rem;color:var(--gray-400);">管理者にGmail連携の設定を依頼してください</p>
                <?php endif; ?>
            </div>
            <?php else: ?>

            <div class="email-tabs">
                <button class="email-tab active" data-panel="compose">作成</button>
                <button class="email-tab" data-panel="sent">送信履歴</button>
            </div>

            <!-- 作成パネル -->
            <div id="panel-compose" class="email-panel active">
                <div class="compose-card">
                    <div class="compose-header">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        新規メール
                    </div>
                    <div class="compose-body">
                        <div class="compose-row">
                            <div class="compose-label">宛先</div>
                            <div class="compose-field" style="position:relative;">
                                <div class="to-tags-area" id="toTagsArea">
                                    <input type="text" class="to-input" id="toInput" placeholder="メールアドレスを入力 または 名前で検索..." autocomplete="off">
                                </div>
                                <div class="addr-dropdown" id="addrDropdown"></div>
                            </div>
                        </div>
                        <div class="compose-row">
                            <div class="compose-label">件名</div>
                            <div class="compose-field">
                                <input type="text" id="emailSubject" class="form-input" placeholder="件名を入力" value="<?= htmlspecialchars($initialSubject) ?>">
                            </div>
                        </div>
                        <div class="compose-row" style="align-items:stretch;">
                            <div class="compose-label" style="padding-top:1rem;">本文</div>
                            <div class="compose-field">
                                <textarea id="emailBody" class="compose-textarea" placeholder="本文を入力..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="compose-footer">
                        <span id="sendStatus" style="font-size:0.8125rem;color:var(--gray-500);"></span>
                        <button type="button" id="sendBtn" class="btn-send">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            送信
                        </button>
                    </div>
                </div>
            </div>

            <!-- 送信履歴パネル -->
            <div id="panel-sent" class="email-panel">
                <div style="background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);overflow:hidden;">
                    <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--gray-200);">
                        <span style="font-weight:600;font-size:1rem;color:var(--gray-800);">送信済みメール</span>
                    </div>
                    <div id="sentLogsContainer" style="padding:0;">
                        <div style="text-align:center;padding:3rem;color:var(--gray-400);">読み込み中...</div>
                    </div>
                    <div id="sentPagination" style="padding:0.75rem 1.5rem;display:flex;justify-content:center;gap:0.5rem;"></div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

<?php if ($gmailConfigured): ?>
<script<?= nonceAttr() ?>>
(function() {
    const CSRF = <?= json_encode($csrfToken) ?>;
    const ADDRESS_BOOK = <?= json_encode($addressBook, JSON_UNESCAPED_UNICODE) ?>;
    const INITIAL_TO = <?= json_encode($initialTo) ?>;

    // --- タブ切替 ---
    document.querySelectorAll('.email-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.email-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.email-panel').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('panel-' + this.dataset.panel).classList.add('active');
            if (this.dataset.panel === 'sent') loadSentLogs(1);
        });
    });

    // --- 宛先タグ管理 ---
    const recipients = [];
    const toTagsArea = document.getElementById('toTagsArea');
    const toInput = document.getElementById('toInput');
    const addrDropdown = document.getElementById('addrDropdown');

    function escapeHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function addRecipient(email, name) {
        email = email.trim();
        if (!email || recipients.some(r => r.email === email)) return;
        recipients.push({ email, name: name || email });
        renderTags();
    }

    function removeRecipient(email) {
        const idx = recipients.findIndex(r => r.email === email);
        if (idx !== -1) recipients.splice(idx, 1);
        renderTags();
    }

    function renderTags() {
        const tags = toTagsArea.querySelectorAll('.to-tag');
        tags.forEach(t => t.remove());
        recipients.forEach(r => {
            const tag = document.createElement('span');
            tag.className = 'to-tag';
            tag.innerHTML = escapeHtml(r.name === r.email ? r.email : r.name + ' <' + r.email + '>');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'to-tag-remove';
            btn.textContent = '×';
            btn.addEventListener('click', () => removeRecipient(r.email));
            tag.appendChild(btn);
            toTagsArea.insertBefore(tag, toInput);
        });
    }

    // アドレス帳ドロップダウン
    function showDropdown(query) {
        const q = (query || '').toLowerCase();
        const filtered = ADDRESS_BOOK.filter(a => {
            if (recipients.some(r => r.email === a.email)) return false;
            if (!q) return true;
            return (a.name && a.name.toLowerCase().includes(q))
                || a.email.toLowerCase().includes(q)
                || (a.dept && a.dept.toLowerCase().includes(q));
        }).slice(0, 10);

        if (filtered.length === 0) {
            addrDropdown.classList.remove('show');
            return;
        }

        addrDropdown.innerHTML = filtered.map(a =>
            `<div class="addr-item" data-email="${escapeHtml(a.email)}" data-name="${escapeHtml(a.name)}">
                <div><span class="addr-item-name">${escapeHtml(a.name)}</span><span class="addr-item-dept">${escapeHtml(a.dept)}</span></div>
                <span class="addr-item-email">${escapeHtml(a.email)}</span>
            </div>`
        ).join('');

        addrDropdown.querySelectorAll('.addr-item').forEach(item => {
            item.addEventListener('click', () => {
                addRecipient(item.dataset.email, item.dataset.name);
                toInput.value = '';
                addrDropdown.classList.remove('show');
                toInput.focus();
            });
        });

        addrDropdown.classList.add('show');
    }

    toInput.addEventListener('focus', () => showDropdown(toInput.value));
    toInput.addEventListener('input', () => showDropdown(toInput.value));
    toInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const val = toInput.value.trim().replace(/,$/, '');
            if (val && val.includes('@')) {
                addRecipient(val, val);
                toInput.value = '';
                addrDropdown.classList.remove('show');
            }
        }
        if (e.key === 'Backspace' && !toInput.value && recipients.length > 0) {
            removeRecipient(recipients[recipients.length - 1].email);
        }
    });

    // クリックでドロップダウンを閉じる
    document.addEventListener('click', function(e) {
        if (!toTagsArea.contains(e.target) && !addrDropdown.contains(e.target)) {
            addrDropdown.classList.remove('show');
        }
    });

    // タグエリアクリックでインプットにフォーカス
    toTagsArea.addEventListener('click', () => toInput.focus());

    // 初期値
    if (INITIAL_TO) {
        INITIAL_TO.split(',').forEach(addr => {
            addr = addr.trim();
            if (!addr) return;
            const match = ADDRESS_BOOK.find(a => a.email === addr);
            addRecipient(addr, match ? match.name : addr);
        });
    }

    // --- 送信 ---
    document.getElementById('sendBtn').addEventListener('click', async function() {
        const to = recipients.map(r => r.email).join(', ');
        const subject = document.getElementById('emailSubject').value.trim();
        const body = document.getElementById('emailBody').value.trim();

        if (recipients.length === 0) { alert('宛先を入力してください'); return; }
        if (!subject) { alert('件名を入力してください'); return; }

        const btn = this;
        const status = document.getElementById('sendStatus');
        btn.disabled = true;
        btn.textContent = '送信中...';
        status.textContent = '';

        try {
            const res = await fetch('/api/email-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify({ action: 'send', to, subject, body }),
            });
            const json = await res.json();
            if (!json.success) {
                const msg = json.errors ? json.errors.join('\n') : (json.error || '送信に失敗しました');
                throw new Error(msg);
            }

            // 成功 → フォームリセット
            recipients.length = 0;
            renderTags();
            document.getElementById('emailSubject').value = '';
            document.getElementById('emailBody').value = '';
            status.textContent = '送信しました';
            status.style.color = 'var(--success)';
            if (typeof showToast === 'function') showToast('メールを送信しました', 'success');
        } catch (e) {
            alert(e.message);
            status.textContent = '';
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> 送信';
        }
    });

    // --- 送信履歴 ---
    async function loadSentLogs(page) {
        const container = document.getElementById('sentLogsContainer');
        container.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--gray-400);">読み込み中...</div>';

        try {
            const res = await fetch('/api/email-api.php?action=logs&page=' + page);
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Failed');

            const logs = json.data.logs;
            if (logs.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--gray-400);">送信履歴はありません</div>';
                document.getElementById('sentPagination').innerHTML = '';
                return;
            }

            let html = '<table class="log-table"><thead><tr><th style="width:140px;">日時</th><th>件名</th><th style="width:250px;">宛先</th><th style="width:120px;">送信者</th></tr></thead><tbody>';
            logs.forEach(log => {
                html += `<tr>
                    <td class="log-date">${escapeHtml(log.sent_at)}</td>
                    <td class="log-subject">${escapeHtml(log.subject)}</td>
                    <td class="log-to" title="${escapeHtml(log.to_address)}">${escapeHtml(log.to_address)}</td>
                    <td>${escapeHtml(log.sent_by || log.from_address)}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            container.innerHTML = html;

            // ページネーション
            const totalPages = json.data.pages;
            const currentPage = json.data.page;
            let pagHtml = '';
            if (totalPages > 1) {
                for (let i = 1; i <= totalPages; i++) {
                    pagHtml += `<button class="btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-secondary'}" data-page="${i}">${i}</button>`;
                }
            }
            const pagEl = document.getElementById('sentPagination');
            pagEl.innerHTML = pagHtml;
            pagEl.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', () => loadSentLogs(parseInt(btn.dataset.page)));
            });
        } catch (e) {
            container.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--danger);">読み込みに失敗しました</div>';
        }
    }
})();
</script>
<?php endif; ?>
</body>
</html>
