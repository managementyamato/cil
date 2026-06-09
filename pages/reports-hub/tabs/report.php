<?php
/**
 * 申請・報告ハブ - 週報タブ
 *
 * 期待する親側変数:
 *   $canEditReport, $isAdminUser, $weeklyDriveFolder, $employees,
 *   $friday, $monday, $currentUser
 */
if (!defined('IN_HUB_PAGE')) { http_response_code(403); exit('Forbidden'); }
?>
<style<?= nonceAttr() ?>>
/* ── 週報 一覧カード ── */
.report-list-card{background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:0.85rem 1.1rem;margin-bottom:0.5rem;display:flex;align-items:center;justify-content:space-between;gap:0.75rem;cursor:pointer;transition:all .15s;}
.report-list-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.06);border-color:var(--primary);background:#fafbff;}
.report-list-left{display:flex;align-items:center;gap:0.85rem;min-width:0;flex:1;}
.report-user-avatar{width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;flex-shrink:0;}
.report-user-info{min-width:0;}
.report-user-name{font-weight:600;font-size:0.9rem;color:var(--gray-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.report-week-date{font-size:0.78rem;color:var(--gray-500);margin-top:1px;}
.report-list-right{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.report-preview-text{font-size:0.78rem;color:var(--gray-500);max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* ── 月・週グルーピング ── */
.report-month-group{margin-bottom:1.25rem;border:1px solid var(--gray-200);border-radius:12px;overflow:hidden;background:#fff;}
.report-month-header{padding:0.75rem 1rem;background:#f0f4ff;border-bottom:1px solid var(--gray-200);cursor:pointer;display:flex;align-items:center;justify-content:space-between;user-select:none;}
.report-month-header:hover{background:#e4ebfb;}
.report-month-title{font-weight:700;font-size:0.95rem;color:var(--gray-800);display:flex;align-items:center;gap:0.5rem;}
.report-month-title .chevron{transition:transform .15s;font-size:0.75rem;color:var(--gray-500);}
.report-month-group.collapsed .report-month-title .chevron{transform:rotate(-90deg);}
.report-month-meta{font-size:0.8rem;color:var(--gray-500);font-weight:400;}
.report-month-body{padding:0.75rem 0.75rem 0.5rem;}
.report-month-group.collapsed .report-month-body{display:none;}

.report-week-group{margin-bottom:0.5rem;border:1px solid var(--gray-200);border-radius:8px;background:var(--gray-50);overflow:hidden;}
.report-week-header{padding:0.55rem 0.85rem;cursor:pointer;display:flex;align-items:center;justify-content:space-between;user-select:none;font-size:0.85rem;}
.report-week-header:hover{background:#eef2f7;}
.report-week-title{font-weight:600;color:var(--gray-700);display:flex;align-items:center;gap:0.4rem;}
.report-week-title .chevron{transition:transform .15s;font-size:0.7rem;color:var(--gray-400);}
.report-week-group.collapsed .report-week-title .chevron{transform:rotate(-90deg);}
.report-week-meta{font-size:0.75rem;color:var(--gray-500);}
.report-week-body{padding:0.5rem 0.5rem 0.25rem;background:#fff;border-top:1px solid var(--gray-200);}
.report-week-group.collapsed .report-week-body{display:none;}

/* ── 週報エディタ ── */
.section-block{margin-bottom:1rem;}
.section-label{font-size:0.82rem;font-weight:600;color:var(--gray-700);margin-bottom:4px;}
.section-editor{min-height:60px;border:1px solid var(--gray-200);border-radius:8px;padding:0.6rem 0.75rem;font-size:0.85rem;line-height:1.6;outline:none;}
.section-editor:focus{border-color:var(--primary);box-shadow:0 0 0 2px rgba(37,99,235,.1);}
.section-editor:empty::before{content:attr(data-ph);color:var(--gray-400);}
.section-editor[contenteditable="false"]{opacity:.6;background:var(--gray-50);}
.section-editor img{max-width:100%;height:auto;border-radius:6px;margin:4px 0;cursor:default;}
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;}
.btn-attach-img{background:none;border:1px solid var(--gray-300);border-radius:6px;padding:2px 8px;font-size:0.78rem;color:var(--gray-500);cursor:pointer;display:flex;align-items:center;gap:3px;transition:all .15s;}
.btn-attach-img:hover{border-color:var(--primary);color:var(--primary);}

/* ── コメント ── */
.report-comments{margin-top:1rem;border-top:1px solid var(--gray-200);padding-top:1rem;}
.report-comment{display:flex;gap:0.5rem;margin-bottom:0.75rem;}
.report-comment-avatar{width:32px;height:32px;border-radius:50%;background:var(--gray-200);display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;color:var(--gray-600);flex-shrink:0;}
.report-comment-body{flex:1;background:var(--gray-50);border-radius:8px;padding:0.5rem 0.75rem;}
.report-comment-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;gap:0.5rem;}
.report-comment-name{font-weight:600;font-size:0.8rem;}
.report-comment-time{font-size:0.7rem;color:var(--gray-400);}
.report-comment-actions{display:flex;gap:4px;}
.report-comment-action-btn{background:none;border:none;cursor:pointer;color:var(--gray-400);font-size:0.72rem;padding:1px 6px;border-radius:4px;}
.report-comment-action-btn:hover{background:var(--gray-200);color:var(--gray-700);}
.report-comment-text{font-size:0.85rem;line-height:1.5;word-break:break-word;}
.report-comment-form{margin-top:0.75rem;border:1px solid var(--gray-300);border-radius:8px;background:#fff;}
.report-comment-form-toolbar{display:flex;gap:4px;padding:4px 6px;border-bottom:1px solid var(--gray-200);background:var(--gray-50);border-radius:8px 8px 0 0;}
.report-comment-form-toolbar button{background:none;border:1px solid var(--gray-300);border-radius:5px;padding:2px 8px;font-size:0.75rem;color:var(--gray-600);cursor:pointer;display:flex;align-items:center;gap:3px;}
.report-comment-form-toolbar button:hover{border-color:var(--primary);color:var(--primary);}
.report-comment-input{min-height:48px;max-height:200px;overflow-y:auto;padding:0.5rem 0.75rem;font-size:0.85rem;line-height:1.5;outline:none;}
.report-comment-input:empty::before{content:attr(data-ph);color:var(--gray-400);}
.report-comment-input img{max-width:100%;height:auto;border-radius:6px;margin:4px 0;display:block;}
.report-comment-form-foot{display:flex;justify-content:flex-end;padding:4px 6px;border-top:1px solid var(--gray-200);}
.mention-dropdown{position:absolute;background:#fff;border:1px solid var(--gray-300);border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.1);max-height:200px;overflow-y:auto;z-index:10010;min-width:180px;}
.mention-dropdown-item{padding:6px 12px;font-size:0.85rem;cursor:pointer;}
.mention-dropdown-item:hover,.mention-dropdown-item.active{background:#e0ecff;color:var(--primary);}

/* ── 週報詳細モーダル本文 ── */
.report-detail-section{margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:1px solid var(--gray-100);}
.report-detail-section:last-child{border-bottom:none;margin-bottom:0;}
.report-detail-label{font-weight:600;font-size:0.82rem;color:var(--primary);margin-bottom:4px;display:flex;align-items:center;gap:6px;}
.report-detail-label svg{width:14px;height:14px;stroke:var(--primary);flex-shrink:0;}
.report-detail-body{font-size:0.88rem;color:var(--gray-700);line-height:1.7;word-break:break-word;}
.report-detail-body:empty::after{content:'（記入なし）';color:var(--gray-400);font-style:italic;}
.report-detail-body *{background-color:transparent !important;color:inherit !important;font-family:inherit !important;}
.report-detail-body img{max-width:100%;height:auto;border-radius:6px;margin:4px 0;display:block;}
.report-detail-body a{color:var(--primary) !important;word-break:break-all;}
.report-comment-text img{max-width:100%;height:auto;border-radius:6px;margin:4px 0;display:block;}
.report-comment-text a{color:var(--primary);word-break:break-all;}
.report-comment-text blockquote{border-left:3px solid var(--gray-300);padding:2px 0 2px 0.6rem;margin:4px 0;color:var(--gray-500);font-size:0.82rem;}
.report-comment-text .mention{background:#e0ecff;color:var(--primary);padding:1px 4px;border-radius:4px;font-weight:600;}
.report-detail-meta{display:flex;align-items:center;gap:1rem;padding:0.75rem 0;margin-bottom:0.75rem;border-bottom:1px solid var(--gray-200);}
.report-detail-meta-item{font-size:0.82rem;color:var(--gray-600);display:flex;align-items:center;gap:4px;}

@media(max-width:768px){
    .report-preview-text{display:none;}
    .report-list-card{padding:0.7rem 0.85rem;}
}
</style>

<div class="settings-detail-header">
    <div>
        <span style="font-size:0.85rem;color:var(--gray-500);">今週: <?= htmlspecialchars($monday) ?> 〜 <?= htmlspecialchars($friday) ?>（金曜提出）</span>
    </div>
    <div class="page-header-actions">
        <?php if ($isAdminUser): ?>
        <a href="settings.php?tab=google_drive_folders" class="btn btn-secondary" title="添付ファイル保存先フォルダ設定">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:0.35rem;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>保存先
        </a>
        <?php endif; ?>
        <?php if ($canEditReport): ?>
        <?= uiNewButton('新規登録', ['id' => 'btnNewReport']) ?>
        <?php endif; ?>
    </div>
</div>
<?php if ($isAdminUser && !$weeklyDriveFolder): ?>
<div style="background:#fff3e0;border:1px solid #ffcc80;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.85rem;color:#e65100;">
    【注意】添付ファイルの保存先Driveフォルダが未設定です。<a href="settings.php?tab=google_drive_folders" style="color:#e65100;font-weight:600;">設定ページ</a>から設定してください（未設定の場合はマイドライブ直下に保存されます）。
</div>
<?php endif; ?>
<div id="reportList"></div>

<!-- 週報モーダル -->
<div class="hub-modal" id="reportModal">
<div class="hub-modal-content" style="max-width:720px;">
    <div class="hub-modal-header">
        <h3 id="reportModalTitle">週報作成</h3>
        <button class="hub-modal-close" data-close-hub-modal>&times;</button>
    </div>
    <div class="hub-modal-body">
        <div class="form-group">
            <label class="form-label">提出日（金曜日）</label>
            <input type="date" class="form-input" id="reportWeekStart" value="<?= htmlspecialchars($friday) ?>">
        </div>

        <div class="section-block">
            <div class="section-header">
                <div class="section-label">今期の役割</div>
                <button type="button" class="btn-attach-img" data-target="secRole"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> 添付</button>
            </div>
            <div class="section-editor" contenteditable="true" id="secRole" data-ph="今期の役割を入力..."></div>
        </div>
        <div class="section-block">
            <div class="section-header">
                <div class="section-label">今週の報告</div>
                <button type="button" class="btn-attach-img" data-target="secReport"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> 添付</button>
            </div>
            <div class="section-editor" contenteditable="true" id="secReport" data-ph="今週の活動・実績..."></div>
        </div>
        <div class="section-block">
            <div class="section-header">
                <div class="section-label">現在抱えている課題</div>
                <button type="button" class="btn-attach-img" data-target="secIssues"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> 添付</button>
            </div>
            <div class="section-editor" contenteditable="true" id="secIssues" data-ph="課題・困りごと..."></div>
        </div>
        <div class="section-block">
            <div class="section-header">
                <div class="section-label">次週目標・計画</div>
                <button type="button" class="btn-attach-img" data-target="secNextGoals"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> 添付</button>
            </div>
            <div class="section-editor" contenteditable="true" id="secNextGoals" data-ph="来週の予定・目標..."></div>
        </div>
        <div class="section-block">
            <div class="section-header">
                <div class="section-label">いま思いつく第二領域活動</div>
                <button type="button" class="btn-attach-img" data-target="secSecondArea"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> 添付</button>
            </div>
            <div class="section-editor" contenteditable="true" id="secSecondArea" data-ph="第二領域の取り組み..."></div>
        </div>
        <div class="section-block">
            <div class="section-header">
                <div class="section-label">報告・連絡・相談事項</div>
                <button type="button" class="btn-attach-img" data-target="secMisc"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> 添付</button>
            </div>
            <div class="section-editor" contenteditable="true" id="secMisc" data-ph="その他共有事項..."></div>
        </div>

        <div class="section-block" style="border:1px solid var(--warning);border-radius:8px;padding:0.75rem;background:#fffbf0;">
            <div class="section-header">
                <div class="section-label" style="color:var(--warning);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    秘匿メッセージ（社長のみ閲覧可）
                </div>
            </div>
            <textarea class="form-input" id="privateMessage" rows="3" placeholder="社長だけに伝えたい内容を入力..."></textarea>
        </div>

    </div>
    <div class="hub-modal-footer">
        <button class="btn btn-secondary" data-close-hub-modal>キャンセル</button>
        <button class="btn btn-secondary" id="btnSaveDraft">下書き保存</button>
        <button class="btn btn-primary" id="btnSubmitReport">提出</button>
    </div>
</div>
</div>

<!-- 週報詳細モーダル -->
<div class="hub-modal" id="reportDetailModal">
<div class="hub-modal-content" style="max-width:720px;">
    <div class="hub-modal-header">
        <h3 id="reportDetailTitle">週報詳細</h3>
        <button class="hub-modal-close" data-close-hub-modal>&times;</button>
    </div>
    <div class="hub-modal-body" id="reportDetailBody"></div>
    <div class="hub-modal-footer" id="reportDetailFooter"></div>
</div>
</div>

<script<?= nonceAttr() ?>>
(function(){
    'use strict';
    const RH = window.ReportsHub;
    const CSRF = '<?= generateCsrfToken() ?>';
    const API  = '/api/reports-hub-api.php';
    const FRIDAY = <?= json_encode($friday) ?>;
    const CAN_EDIT_REPORT = <?= $canEditReport ? 'true' : 'false' ?>;
    const CAN_DEL  = <?= $canDel  ? 'true' : 'false' ?>;
    const IS_ADMIN = <?= $isAdminUser ? 'true' : 'false' ?>;
    const ME = <?= json_encode($currentUser) ?>;
    const MENTION_USERS = <?= json_encode(array_values(array_filter(array_map(fn($e) => $e['name'] ?? '', $employees)))) ?>;

    const esc = RH.esc, fmt = RH.fmt, dateShort = RH.dateShort;
    const showAlert = RH.showAlert, openModal = RH.openModal, closeModal = RH.closeModal;
    const apiGet  = (type, action, extra) => RH.apiGet(API, type, action, extra);
    const apiPost = (params) => RH.apiPost(API, CSRF, params);

    let allReports = [];
    let editingReportWeek = null;

    async function loadReports() {
        const res = await apiGet('report', 'list');
        allReports = res.items || [];
        renderReports();
    }

    function linkify(html) {
        return html.replace(/(<[^>]+>)|(https?:\/\/[^\s<>"']+)/g, function(match, tag, url) {
            if (tag) return tag;
            return '<a href="' + url + '" target="_blank" rel="noopener" style="color:var(--primary);text-decoration:underline;">' + url + '</a>';
        });
    }

    function getInitial(name) { return name ? name.charAt(0) : '?'; }

    function weekOfMonth(yyyyMmDd) {
        if (!yyyyMmDd || yyyyMmDd.length < 10) return 1;
        const day = parseInt(yyyyMmDd.substring(8, 10), 10);
        return Math.floor((day - 1) / 7) + 1;
    }

    function formatMD(yyyyMmDd) {
        if (!yyyyMmDd || yyyyMmDd.length < 10) return yyyyMmDd || '';
        return parseInt(yyyyMmDd.substring(5, 7), 10) + '/' + parseInt(yyyyMmDd.substring(8, 10), 10);
    }

    function renderReportCard(r, idx) {
        const isConfirmed = r.confirmed_at;
        let statusClass, statusLabel;
        if (isConfirmed) { statusClass = 'confirmed'; statusLabel = '確認済み'; }
        else if (r.status === 'submitted') { statusClass = 'submitted'; statusLabel = '提出済み'; }
        else { statusClass = 'draft'; statusLabel = '下書き'; }

        const userName = r.user_name || r.user_email || '';
        const previewText = (r.sec_report || '').replace(/<[^>]*>/g, '').trim();
        const showConfirmBtn = IS_ADMIN && r.status === 'submitted' && !isConfirmed;

        return `<div class="report-list-card" data-action="view-report" data-idx="${idx}">
            <div class="report-list-left">
                <div class="report-user-avatar">${esc(getInitial(userName))}</div>
                <div class="report-user-info">
                    <div class="report-user-name">${esc(userName)}</div>
                    <div class="report-week-date">${esc(r.week_start)}${r.submitted_at ? ' / 提出 ' + esc(r.submitted_at.substring(0, 10)) : ''}</div>
                </div>
            </div>
            <div class="report-list-right">
                <span class="report-preview-text">${esc(previewText.substring(0, 60))}${previewText.length > 60 ? '...' : ''}</span>
                <span class="status-badge ${statusClass}">${statusLabel}</span>
                ${showConfirmBtn ? '<button class="btn btn-sm btn-primary" data-action="confirm-report" data-id="'+esc(r.id)+'" data-name="'+esc(userName)+'" data-week="'+esc(r.week_start)+'">確認</button>' : ''}
                ${r.status === 'draft' && CAN_EDIT_REPORT ? '<button class="btn btn-sm btn-outline" data-action="edit-report" data-id="'+esc(r.id)+'" data-week="'+esc(r.week_start)+'">編集</button>' : ''}
                ${r.user_email === ME ? '<button class="btn btn-sm btn-danger" data-action="delete-report" data-id="'+esc(r.id)+'">削除</button>' : ''}
            </div>
        </div>`;
    }

    function renderReports() {
        const c = document.getElementById('reportList');
        if (!c) return;
        if (!allReports.length) { c.innerHTML = '<p style="color:var(--gray-400);text-align:center;padding:2rem;">週報はまだありません</p>'; return; }

        const tree = {};
        allReports.forEach((r, idx) => {
            const ws = r.week_start || '';
            if (!ws || ws.length < 10) return;
            const ym = ws.substring(0, 7);
            if (!tree[ym]) tree[ym] = { weeks: {}, total: 0 };
            if (!tree[ym].weeks[ws]) tree[ym].weeks[ws] = [];
            tree[ym].weeks[ws].push({ r, idx });
            tree[ym].total++;
        });

        const monthKeys = Object.keys(tree).sort().reverse();
        let html = '';
        monthKeys.forEach((ym, monthIdx) => {
            const monthData = tree[ym];
            const weekKeys = Object.keys(monthData.weeks).sort().reverse();
            const [yy, mm] = ym.split('-');
            const monthLabel = yy + '年' + parseInt(mm, 10) + '月分 週報';
            const monthMeta = weekKeys.length + '週間・' + monthData.total + '件';
            const monthCollapsed = monthIdx > 0 ? 'collapsed' : '';

            html += `<div class="report-month-group ${monthCollapsed}" data-month="${esc(ym)}">`;
            html += `<div class="report-month-header" data-action="toggle-month" data-month="${esc(ym)}">
                <div class="report-month-title"><span class="chevron">▼</span>${esc(monthLabel)}</div>
                <div class="report-month-meta">${esc(monthMeta)}</div>
            </div>`;
            html += `<div class="report-month-body">`;

            weekKeys.forEach((ws) => {
                const weekReports = monthData.weeks[ws];
                const weekNum = weekOfMonth(ws);
                const monthNum = parseInt(mm, 10);
                const weekLabel = monthNum + '月第' + weekNum + '週 (' + formatMD(ws) + '金提出)';
                const weekMeta = weekReports.length + '件';

                html += `<div class="report-week-group collapsed" data-week="${esc(ws)}">`;
                html += `<div class="report-week-header" data-action="toggle-week" data-week="${esc(ws)}">
                    <div class="report-week-title"><span class="chevron">▼</span>${esc(weekLabel)}</div>
                    <div class="report-week-meta">${esc(weekMeta)}</div>
                </div>`;
                html += `<div class="report-week-body">`;
                weekReports.forEach(entry => { html += renderReportCard(entry.r, entry.idx); });
                html += `</div></div>`;
            });

            html += `</div></div>`;
        });

        c.innerHTML = html;
    }

    let currentDetailReport = null;
    function openReportDetail(r) {
        currentDetailReport = r;
        const isConfirmed = r.confirmed_at;
        let statusClass, statusLabel;
        if (isConfirmed) { statusClass = 'confirmed'; statusLabel = '確認済み'; }
        else if (r.status === 'submitted') { statusClass = 'submitted'; statusLabel = '提出済み'; }
        else { statusClass = 'draft'; statusLabel = '下書き'; }

        const userName = r.user_name || r.user_email || '';
        document.getElementById('reportDetailTitle').textContent = userName + ' の週報';

        const sections = [
            { key: 'sec_role', label: '今期の役割', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' },
            { key: 'sec_report', label: '今週の報告', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' },
            { key: 'sec_issues', label: '課題', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' },
            { key: 'sec_next_goals', label: '次週目標', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>' },
            { key: 'sec_second_area', label: '第二領域', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' },
            { key: 'sec_misc', label: '報連相', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>' },
        ];

        let meta = `<div class="report-detail-meta">
            <div class="report-detail-meta-item"><strong>${esc(userName)}</strong></div>
            <div class="report-detail-meta-item">${esc(r.week_start)}</div>
            <div class="report-detail-meta-item"><span class="status-badge ${statusClass}">${statusLabel}</span></div>
            ${isConfirmed ? '<div class="report-detail-meta-item" style="color:#27ae60;">確認: ' + esc(r.confirmed_by_name || '') + ' / ' + esc(r.confirmed_at) + '</div>' : ''}
        </div>`;

        let body = meta;
        sections.forEach(s => {
            const val = r[s.key] || '';
            body += `<div class="report-detail-section">
                <div class="report-detail-label">${s.icon} ${s.label}</div>
                <div class="report-detail-body">${linkify(val)}</div>
            </div>`;
        });

        if ((IS_ADMIN || r.user_email === ME) && r.private_message) {
            body += `<div class="report-detail-section" style="border:1px solid var(--warning);border-radius:8px;padding:0.75rem;background:#fffbf0;">
                <div class="report-detail-label" style="color:var(--warning);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    秘匿メッセージ
                </div>
                <div class="report-detail-body" style="white-space:pre-wrap;">${esc(r.private_message)}</div>
            </div>`;
        }

        if (r.status === 'submitted' || isConfirmed) {
            body += `<div class="report-comments" id="reportCommentsSection" data-report-id="${esc(r.id)}">
                <div style="font-weight:600;font-size:0.9rem;margin-bottom:0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    コメント
                </div>
                <div id="reportCommentsList"><span style="color:var(--gray-400);font-size:0.8rem;">読み込み中...</span></div>
                <div class="report-comment-form">
                    <div class="report-comment-form-toolbar">
                        <button type="button" id="btnCommentAttach" title="ファイル添付（画像/PDF/Word/Excel/PowerPoint）">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            添付
                        </button>
                        <button type="button" id="btnCommentMention" title="メンション挿入">
                            <span style="font-weight:700;">@</span>メンション
                        </button>
                        <span style="flex:1;"></span>
                        <span style="font-size:0.7rem;color:var(--gray-400);align-self:center;">Enter:送信 / Shift+Enter:改行</span>
                    </div>
                    <div class="report-comment-input" id="reportCommentInput" contenteditable="true" data-ph="コメントを入力..."></div>
                    <div class="report-comment-form-foot">
                        <button class="btn btn-primary btn-sm" id="btnPostComment">送信</button>
                    </div>
                </div>
            </div>`;
        }

        document.getElementById('reportDetailBody').innerHTML = body;

        if (r.status === 'submitted' || isConfirmed) {
            loadReportComments(r.id);
            const inputEl = document.getElementById('reportCommentInput');
            document.getElementById('btnPostComment')?.addEventListener('click', () => postReportComment(r.id));
            document.getElementById('btnCommentAttach')?.addEventListener('click', () => attachToComment(inputEl));
            document.getElementById('btnCommentMention')?.addEventListener('click', () => insertMentionTrigger(inputEl));
            inputEl?.addEventListener('keydown', e => {
                if (e.key === 'Enter' && !e.shiftKey && !mentionDropdownOpen()) {
                    e.preventDefault();
                    postReportComment(r.id);
                }
            });
            inputEl?.addEventListener('input', () => handleMentionInput(inputEl));
            inputEl?.addEventListener('keydown', handleMentionKey);
            inputEl?.addEventListener('paste', e => {
                const items = e.clipboardData?.items; if (!items) return;
                for (const it of items) {
                    if (it.type.startsWith('image/')) {
                        e.preventDefault();
                        uploadCommentFile(it.getAsFile(), inputEl);
                        return;
                    }
                }
            });
            inputEl?.addEventListener('dragover', e => e.preventDefault());
            inputEl?.addEventListener('drop', e => {
                const files = e.dataTransfer?.files; if (!files || !files.length) return;
                e.preventDefault();
                for (const f of files) uploadCommentFile(f, inputEl);
            });
        }

        // フッターボタン
        const showConfirmBtn = IS_ADMIN && r.status === 'submitted' && !isConfirmed;
        let footer = '';
        if (showConfirmBtn) {
            footer += `<button class="btn btn-primary" data-action="confirm-report" data-id="${esc(r.id)}" data-name="${esc(userName)}" data-week="${esc(r.week_start)}">確認する</button>`;
        }
        if (r.status === 'draft' && CAN_EDIT_REPORT) {
            footer += `<button class="btn btn-secondary" data-action="edit-report" data-id="${esc(r.id)}" data-week="${esc(r.week_start)}">編集</button>`;
        }
        footer += `<button class="btn btn-secondary" data-close-hub-modal>閉じる</button>`;
        document.getElementById('reportDetailFooter').innerHTML = footer;

        openModal('reportDetailModal');
    }

    // 週報モーダル
    document.getElementById('btnNewReport')?.addEventListener('click', () => {
        editingReportWeek = null;
        document.getElementById('reportModalTitle').textContent = '週報作成';
        document.getElementById('reportWeekStart').value = FRIDAY;
        ['secRole','secReport','secIssues','secNextGoals','secSecondArea','secMisc'].forEach(id => document.getElementById(id).innerHTML = '');
        document.getElementById('privateMessage').value = '';
        const existing = allReports.find(r => r.week_start === FRIDAY && r.status === 'draft');
        if (existing) populateReportModal(existing);
        openModal('reportModal');
    });

    function populateReportModal(r) {
        editingReportWeek = r.week_start;
        document.getElementById('reportWeekStart').value = r.week_start;
        document.getElementById('secRole').innerHTML = r.sec_role || '';
        document.getElementById('secReport').innerHTML = r.sec_report || '';
        document.getElementById('secIssues').innerHTML = r.sec_issues || '';
        document.getElementById('secNextGoals').innerHTML = r.sec_next_goals || '';
        document.getElementById('secSecondArea').innerHTML = r.sec_second_area || '';
        document.getElementById('secMisc').innerHTML = r.sec_misc || '';
        document.getElementById('privateMessage').value = r.private_message || '';
    }

    // ── 画像圧縮 ──
    function compressImage(file, maxWidth, quality) {
        return new Promise((resolve) => {
            if (file.type === 'image/gif') { resolve(file); return; }
            if (file.size <= 200 * 1024) { resolve(file); return; }
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                let w = img.width, h = img.height;
                if (w <= maxWidth && file.size <= 500 * 1024) { resolve(file); return; }
                if (w > maxWidth) { h = Math.round(h * maxWidth / w); w = maxWidth; }
                const canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                canvas.toBlob((blob) => {
                    resolve(new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' }));
                }, 'image/jpeg', quality);
            };
            img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
            img.src = url;
        });
    }

    const weeklyFileInput = document.createElement('input');
    weeklyFileInput.type = 'file';
    weeklyFileInput.accept = 'image/jpeg,image/png,image/gif,image/webp,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx';
    weeklyFileInput.style.display = 'none';
    document.body.appendChild(weeklyFileInput);
    let weeklyFileTarget = null;
    let uploadingCount = 0;

    const OFFICE_MIMES = [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    const OFFICE_EXTS = ['doc','docx','xls','xlsx','ppt','pptx'];

    function isOfficeFile(file) {
        if (OFFICE_MIMES.includes(file.type)) return true;
        const ext = (file.name || '').split('.').pop().toLowerCase();
        return OFFICE_EXTS.includes(ext);
    }

    async function uploadWeeklyFile(file, editorEl) {
        if (!file) return;
        const isImage = file.type.startsWith('image/');
        const isPdf = file.type === 'application/pdf';
        const isOffice = !isImage && !isPdf && isOfficeFile(file);
        if (!isImage && !isPdf && !isOffice) { showAlert('画像 / PDF / Word / Excel / PowerPoint のみ添付できます', 'danger'); return; }
        if (isImage) file = await compressImage(file, 1600, 0.80);
        const maxSize = isImage ? 10 * 1024 * 1024 : 25 * 1024 * 1024;
        if (file.size > maxSize) { showAlert('ファイルサイズが大きすぎます（画像10MB / その他25MB）', 'danger'); return; }

        uploadingCount++;

        if (isImage) {
            const img = document.createElement('img');
            img.alt = '添付画像';
            img.style.opacity = '0.5';
            const blobUrl = URL.createObjectURL(file);
            img.src = blobUrl;
            editorEl.appendChild(img);
            editorEl.appendChild(document.createElement('br'));

            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('file', file);
            try {
                const r = await fetch('/api/upload-weekly-image.php', { method: 'POST', body: fd });
                const json = await r.json();
                if (!json.success) throw new Error(json.error || 'アップロード失敗');
                img.src = json.data.url;
                img.style.opacity = '1';
                URL.revokeObjectURL(blobUrl);
            } catch (err) {
                img.remove();
                showAlert('アップロード失敗: ' + err.message, 'danger');
            } finally {
                uploadingCount--;
            }
            return;
        }

        const ext = (file.name || '').split('.').pop().toLowerCase();
        const colorMap = {
            pdf:  { bg: '#fff3e0', bd: '#ffe0b2', fg: '#e65100' },
            doc:  { bg: '#e3f2fd', bd: '#bbdefb', fg: '#1565c0' },
            docx: { bg: '#e3f2fd', bd: '#bbdefb', fg: '#1565c0' },
            xls:  { bg: '#e8f5e9', bd: '#c8e6c9', fg: '#2e7d32' },
            xlsx: { bg: '#e8f5e9', bd: '#c8e6c9', fg: '#2e7d32' },
            ppt:  { bg: '#fff3e0', bd: '#ffcc80', fg: '#e65100' },
            pptx: { bg: '#fff3e0', bd: '#ffcc80', fg: '#e65100' },
        };
        const c = colorMap[ext] || { bg: '#f5f5f5', bd: '#e0e0e0', fg: '#424242' };
        const link = document.createElement('a');
        link.target = '_blank';
        link.style.cssText = `display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:${c.bg};border:1px solid ${c.bd};border-radius:6px;color:${c.fg};text-decoration:none;font-size:0.82rem;margin:4px 0;opacity:0.5;`;
        link.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> ' + esc(file.name || ext.toUpperCase()) + ' …';
        editorEl.appendChild(link);
        editorEl.appendChild(document.createElement('br'));

        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('file', file);
        try {
            const r = await fetch('/api/upload-weekly-image.php', { method: 'POST', body: fd });
            const json = await r.json();
            if (!json.success) throw new Error(json.error || 'アップロード失敗');
            link.href = json.data.url;
            link.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> ' + esc(json.data.original_name || file.name || ext.toUpperCase());
            link.style.opacity = '1';
        } catch (err) {
            link.remove();
            showAlert('アップロード失敗: ' + err.message, 'danger');
        } finally {
            uploadingCount--;
        }
    }

    document.querySelectorAll('.btn-attach-img').forEach(btn => {
        btn.addEventListener('click', () => {
            weeklyFileTarget = document.getElementById(btn.dataset.target);
            weeklyFileInput.value = '';
            weeklyFileInput.click();
        });
    });
    weeklyFileInput.addEventListener('change', () => {
        if (weeklyFileInput.files[0] && weeklyFileTarget) {
            uploadWeeklyFile(weeklyFileInput.files[0], weeklyFileTarget);
        }
    });

    document.querySelectorAll('.section-editor').forEach(editor => {
        editor.addEventListener('paste', e => {
            const items = e.clipboardData?.items;
            if (!items) return;
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    e.preventDefault();
                    uploadWeeklyFile(item.getAsFile(), editor);
                    return;
                }
            }
        });
        editor.addEventListener('dragover', e => e.preventDefault());
        editor.addEventListener('drop', e => {
            const files = e.dataTransfer?.files;
            if (!files) return;
            for (const file of files) {
                if (file.type.startsWith('image/') || file.type === 'application/pdf' || isOfficeFile(file)) {
                    e.preventDefault();
                    uploadWeeklyFile(file, editor);
                    return;
                }
            }
        });
    });

    function collectReportData() {
        return {
            week_start: document.getElementById('reportWeekStart').value,
            sec_role: document.getElementById('secRole').innerHTML,
            sec_report: document.getElementById('secReport').innerHTML,
            sec_issues: document.getElementById('secIssues').innerHTML,
            sec_next_goals: document.getElementById('secNextGoals').innerHTML,
            sec_second_area: document.getElementById('secSecondArea').innerHTML,
            sec_misc: document.getElementById('secMisc').innerHTML,
            private_message: document.getElementById('privateMessage').value,
        };
    }

    document.getElementById('btnSaveDraft')?.addEventListener('click', async () => {
        if (uploadingCount > 0) return showAlert('ファイルアップロード中です。完了までお待ちください。', 'danger');
        const d = collectReportData();
        if (!d.week_start) return showAlert('対象週を選択してください', 'danger');
        const res = await apiPost({ type: 'report', action: 'save', ...d });
        if (res.error) return showAlert(res.error, 'danger');
        showAlert('下書き保存しました', 'success');
        closeModal('reportModal');
        loadReports();
    });

    document.getElementById('btnSubmitReport')?.addEventListener('click', async () => {
        if (uploadingCount > 0) return showAlert('ファイルアップロード中です。完了までお待ちください。', 'danger');
        if (!confirm('週報を提出しますか？')) return;
        const d = collectReportData();
        if (!d.week_start) return showAlert('対象週を選択してください', 'danger');
        const res = await apiPost({ type: 'report', action: 'submit', ...d });
        if (res.error) return showAlert(res.error, 'danger');
        showAlert('週報を提出しました', 'success');
        closeModal('reportModal');
        loadReports();
    });

    // ── コメント機能 ──
    let lastLoadedComments = [];

    function renderCommentBody(body) {
        if (!body) return '';
        return body.replace(/(^|[\s>])@([A-Za-z0-9_぀-ヿ一-龯]+)/g,
            (m, p, name) => `${p}<span class="mention">@${name}</span>`);
    }

    async function loadReportComments(reportId) {
        const container = document.getElementById('reportCommentsList');
        if (!container) return;
        try {
            const res = await apiGet('report', 'list_comments', '&report_id=' + encodeURIComponent(reportId));
            const comments = res.items || [];
            lastLoadedComments = comments;
            if (comments.length === 0) {
                container.innerHTML = '<span style="color:var(--gray-400);font-size:0.8rem;">コメントはありません</span>';
                return;
            }
            container.innerHTML = comments.map((c, idx) => {
                const initials = (c.user_name || '?').slice(0, 1);
                return `<div class="report-comment">
                    <div class="report-comment-avatar">${esc(initials)}</div>
                    <div class="report-comment-body">
                        <div class="report-comment-header">
                            <span class="report-comment-name">${esc(c.user_name || c.user_email || '')}</span>
                            <div class="report-comment-actions">
                                <button type="button" class="report-comment-action-btn" data-quote-idx="${idx}" title="この投稿を引用">引用</button>
                                <button type="button" class="report-comment-action-btn" data-mention-idx="${idx}" title="この投稿者にメンション">@返信</button>
                            </div>
                            <span class="report-comment-time">${esc((c.created_at || '').slice(5, 16))}</span>
                        </div>
                        <div class="report-comment-text">${renderCommentBody(c.body)}</div>
                    </div>
                </div>`;
            }).join('');
            container.querySelectorAll('[data-quote-idx]').forEach(btn => {
                btn.addEventListener('click', () => quoteComment(comments[+btn.dataset.quoteIdx]));
            });
            container.querySelectorAll('[data-mention-idx]').forEach(btn => {
                btn.addEventListener('click', () => mentionUser(comments[+btn.dataset.mentionIdx]?.user_name || ''));
            });
        } catch {
            container.innerHTML = '<span style="color:var(--danger);font-size:0.8rem;">読み込みエラー</span>';
        }
    }

    function quoteComment(c) {
        if (!c) return;
        const input = document.getElementById('reportCommentInput');
        if (!input) return;
        const author = c.user_name || c.user_email || '';
        const time = (c.created_at || '').slice(5, 16);
        const tmp = document.createElement('div');
        tmp.innerHTML = c.body || '';
        let text = (tmp.textContent || '').trim();
        if (text.length > 200) text = text.slice(0, 200) + '...';
        const quoteHtml = `<blockquote>${esc(author)} (${esc(time)}): ${esc(text)}</blockquote><div><br></div>`;
        input.innerHTML = quoteHtml + (input.innerHTML || '');
        input.focus();
        const range = document.createRange();
        range.selectNodeContents(input);
        range.collapse(false);
        const sel = window.getSelection();
        sel.removeAllRanges(); sel.addRange(range);
    }

    function mentionUser(name) {
        if (!name) return;
        const input = document.getElementById('reportCommentInput');
        if (!input) return;
        input.focus();
        document.execCommand('insertText', false, '@' + name + ' ');
    }

    async function uploadCommentFile(file, editorEl) {
        if (!file || !editorEl) return;
        const isImage = file.type.startsWith('image/');
        const isPdf = file.type === 'application/pdf';
        const ext = (file.name || '').split('.').pop().toLowerCase();
        const isOffice = OFFICE_MIMES.includes(file.type) || OFFICE_EXTS.includes(ext);

        if (!isImage && !isPdf && !isOffice) {
            showAlert('画像 / PDF / Word / Excel / PowerPoint のみ添付できます', 'danger');
            return;
        }
        const maxSize = isImage ? 10 * 1024 * 1024 : 25 * 1024 * 1024;
        if (file.size > maxSize) {
            showAlert('ファイルサイズが大きすぎます（画像10MB / その他25MB）', 'danger');
            return;
        }

        let placeholder;
        if (isImage) {
            placeholder = document.createElement('img');
            placeholder.alt = '添付画像';
            placeholder.style.opacity = '0.5';
            placeholder.src = URL.createObjectURL(file);
        } else {
            placeholder = document.createElement('a');
            placeholder.target = '_blank';
            placeholder.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#fff3e0;border:1px solid #ffe0b2;border-radius:6px;color:#e65100;text-decoration:none;font-size:0.8rem;margin:2px 0;opacity:0.5;';
            placeholder.textContent = '[添付中] ' + (file.name || 'ファイル');
        }
        editorEl.appendChild(placeholder);
        editorEl.appendChild(document.createElement('br'));

        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('file', file);
        try {
            const r = await fetch('/api/upload-weekly-image.php', { method: 'POST', body: fd });
            const json = await r.json();
            if (!json.success) throw new Error(json.error || 'アップロード失敗');
            if (isImage) {
                placeholder.src = json.data.url;
                placeholder.style.opacity = '1';
            } else {
                placeholder.href = json.data.url;
                placeholder.style.opacity = '1';
                placeholder.textContent = (json.data.original_name || file.name || 'ファイル');
            }
        } catch (err) {
            placeholder.remove();
            showAlert('アップロード失敗: ' + err.message, 'danger');
        }
    }

    function attachToComment(editorEl) {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx';
        input.style.display = 'none';
        document.body.appendChild(input);
        input.addEventListener('change', () => {
            if (input.files[0]) uploadCommentFile(input.files[0], editorEl);
            input.remove();
        });
        input.click();
    }

    // ── @メンション typeahead ──
    let mentionState = { active: false, query: '', anchor: 0, dropdown: null, selectedIdx: 0, filtered: [] };

    function mentionDropdownOpen() { return mentionState.active; }

    function closeMentionDropdown() {
        if (mentionState.dropdown) mentionState.dropdown.remove();
        mentionState = { active: false, query: '', anchor: 0, dropdown: null, selectedIdx: 0, filtered: [] };
    }

    function insertMentionTrigger(editorEl) {
        if (!editorEl) return;
        editorEl.focus();
        document.execCommand('insertText', false, '@');
        handleMentionInput(editorEl);
    }

    function handleMentionInput(editorEl) {
        const sel = window.getSelection();
        if (!sel.rangeCount) { closeMentionDropdown(); return; }
        const range = sel.getRangeAt(0);
        const node = range.startContainer;
        if (node.nodeType !== 3) { closeMentionDropdown(); return; }
        const offset = range.startOffset;
        const text = node.textContent.slice(0, offset);
        const m = text.match(/@([A-Za-z0-9_぀-ヿ一-龯\s　]*)$/);
        if (!m) { closeMentionDropdown(); return; }
        const query = m[1].trim();
        const posterName = currentDetailReport?.user_name || '';
        const ordered = [];
        if (posterName && !ordered.includes(posterName)) ordered.push(posterName);
        for (const n of MENTION_USERS) {
            if (n && !ordered.includes(n)) ordered.push(n);
        }
        const ql = query.toLowerCase();
        const filtered = ordered.filter(n => n && (query === '' || n.toLowerCase().includes(ql)));
        if (filtered.length === 0) { closeMentionDropdown(); return; }

        if (!mentionState.dropdown) {
            mentionState.dropdown = document.createElement('div');
            mentionState.dropdown.className = 'mention-dropdown';
            document.body.appendChild(mentionState.dropdown);
        }
        const rect = range.getBoundingClientRect();
        mentionState.dropdown.style.left = rect.left + 'px';
        mentionState.dropdown.style.top = (rect.bottom + window.scrollY + 4) + 'px';
        mentionState.active = true;
        mentionState.query = query;
        mentionState.filtered = filtered;
        mentionState.selectedIdx = 0;
        mentionState.editorEl = editorEl;
        mentionState.range = range.cloneRange();
        renderMentionDropdown();
    }

    function renderMentionDropdown() {
        if (!mentionState.dropdown) return;
        const posterName = currentDetailReport?.user_name || '';
        mentionState.dropdown.innerHTML = mentionState.filtered.map((n, i) => {
            const badge = (n === posterName) ? ' <span style="font-size:0.7rem;color:#fff;background:var(--primary);padding:1px 5px;border-radius:3px;margin-left:4px;">投稿者</span>' : '';
            return `<div class="mention-dropdown-item ${i === mentionState.selectedIdx ? 'active' : ''}" data-name="${esc(n)}">${esc(n)}${badge}</div>`;
        }).join('');
        mentionState.dropdown.querySelectorAll('.mention-dropdown-item').forEach(el => {
            el.addEventListener('mousedown', e => { e.preventDefault(); selectMention(el.dataset.name); });
        });
    }

    function selectMention(name) {
        const editorEl = mentionState.editorEl;
        if (!editorEl) { closeMentionDropdown(); return; }
        const sel = window.getSelection();
        sel.removeAllRanges();
        const node = mentionState.range.startContainer;
        const offset = mentionState.range.startOffset;
        const text = node.textContent;
        const before = text.slice(0, offset);
        const after = text.slice(offset);
        const replaced = before.replace(/@([A-Za-z0-9_぀-ヿ一-龯]*)$/, '@' + name + ' ');
        node.textContent = replaced + after;
        const range = document.createRange();
        const newOffset = replaced.length;
        range.setStart(node, newOffset); range.collapse(true);
        sel.addRange(range);
        editorEl.focus();
        closeMentionDropdown();
    }

    function handleMentionKey(e) {
        if (!mentionState.active) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            mentionState.selectedIdx = (mentionState.selectedIdx + 1) % mentionState.filtered.length;
            renderMentionDropdown();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            mentionState.selectedIdx = (mentionState.selectedIdx - 1 + mentionState.filtered.length) % mentionState.filtered.length;
            renderMentionDropdown();
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            const name = mentionState.filtered[mentionState.selectedIdx];
            if (name) selectMention(name);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            closeMentionDropdown();
        }
    }

    // メンションドロップダウン: タブ切替時に残らないようコンテナ消失を監視
    const reportListEl = document.getElementById('reportList');
    if (reportListEl) {
        const obs = new MutationObserver(() => {
            if (!document.contains(reportListEl) && mentionState.dropdown) {
                closeMentionDropdown();
                obs.disconnect();
            }
        });
        obs.observe(document.body, { childList: true, subtree: true });
    }

    // ドロップダウン外クリックで閉じる (タブのライフサイクルに紐付けたいので reportList から)
    if (reportListEl) {
        reportListEl.addEventListener('click', e => {
            if (mentionState.active && !mentionState.dropdown?.contains(e.target)) closeMentionDropdown();
        });
    }

    async function postReportComment(reportId) {
        const input = document.getElementById('reportCommentInput');
        if (!input) return;
        const body = (input.innerHTML || '').trim();
        const plain = (input.textContent || '').trim();
        if (!plain && !input.querySelector('img,a')) return;
        input.contentEditable = 'false';
        try {
            const res = await apiPost({ type: 'report', action: 'add_comment', report_id: reportId, body: body });
            if (res.error) { showAlert(res.error, 'danger'); return; }
            input.innerHTML = '';
            loadReportComments(reportId);
            const mc = res.item?.mentioned_count;
            const mn = res.item?.matched_names || [];
            if (typeof mc === 'number' && mc > 0) {
                showAlert('コメントを投稿し、' + mc + ' 名にメンション通知を送りました', 'success');
            } else if (mn.length > 0) {
                showAlert('コメント投稿: メンション検出は ' + mn.length + ' 件（送信は0件 / 自分自身のメンションは除外されます）', 'success');
            }
        } catch {
            showAlert('コメントの投稿に失敗しました', 'danger');
        } finally {
            input.contentEditable = 'true';
            input.focus();
        }
    }

    // ── 確認ボタン (週報 submitted → confirmed) ──
    async function handleConfirmReport(btn) {
        const id = btn.dataset.id;
        const name = btn.dataset.name || '';
        const week = btn.dataset.week || '';
        if (!window.confirm(name + ' さんの週報（' + week + '）を確認済みにしますか？')) return;
        btn.disabled = true;
        const orig = btn.textContent;
        btn.textContent = '処理中...';
        try {
            const res = await apiPost({ type: 'report', action: 'confirm', id });
            if (res.error) {
                alert('エラー: ' + res.error);
                btn.disabled = false;
                btn.textContent = orig;
                return;
            }
            alert('確認しました');
            closeModal('reportDetailModal');
            loadReports();
        } catch (err) {
            alert('通信エラー: ' + err.message);
            btn.disabled = false;
            btn.textContent = orig;
        }
    }

    // ── 一覧クリック委譲 ──
    async function handleReportAction(btn) {
        const action = btn.dataset.action;
        if (action === 'toggle-month') {
            const group = btn.closest('.report-month-group');
            if (group) group.classList.toggle('collapsed');
            return;
        }
        if (action === 'toggle-week') {
            const group = btn.closest('.report-week-group');
            if (group) group.classList.toggle('collapsed');
            return;
        }
        if (action === 'view-report') {
            const idx = parseInt(btn.dataset.idx, 10);
            if (allReports[idx]) openReportDetail(allReports[idx]);
            return;
        }
        if (action === 'edit-report') {
            closeModal('reportDetailModal');
            const r = allReports.find(r => r.id === btn.dataset.id);
            if (r) {
                document.getElementById('reportModalTitle').textContent = '週報編集';
                populateReportModal(r);
                openModal('reportModal');
            }
            return;
        }
        if (action === 'confirm-report') {
            handleConfirmReport(btn);
            return;
        }
        if (action === 'delete-report') {
            if (!confirm('この週報を削除しますか？')) return;
            const res = await apiPost({ type: 'report', action: 'delete', id: btn.dataset.id });
            if (res.error) return showAlert(res.error, 'danger');
            showAlert('削除しました', 'success');
            await loadReports();
            return;
        }
    }

    document.getElementById('reportList').addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        if (btn.dataset.action !== 'view-report') e.stopPropagation();
        handleReportAction(btn);
    });

    document.getElementById('reportDetailFooter').addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if (btn) handleReportAction(btn);
    });

    // 初期ロード
    loadReports().catch(e => console.error('loadReports failed:', e));
})();
</script>
