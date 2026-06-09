<?php
/**
 * 申請・報告ハブ - 値引き申請タブ
 *
 * 期待する親側変数:
 *   $canEditApproval, $canDel, $isAdminUser, $discountDriveFolder, $currentUser
 */
if (!defined('IN_HUB_PAGE')) { http_response_code(403); exit('Forbidden'); }
?>
<style<?= nonceAttr() ?>>
/* ── 金額表示 ── */
.amount-display{display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;}
.amount-display .original{color:var(--gray-500);}
.amount-display .arrow{color:var(--gray-400);}
.amount-display .discount{color:#c62828;font-weight:600;}
.amount-display .after{color:#2e7d32;font-weight:600;}
</style>

<div class="settings-detail-header">
    <div class="filter-bar" id="approvalFilters">
        <button class="filter-btn active" data-filter="all">すべて</button>
        <button class="filter-btn" data-filter="pending">承認待ち</button>
        <button class="filter-btn" data-filter="approved">承認済み</button>
        <button class="filter-btn" data-filter="rejected">却下</button>
    </div>
    <div class="page-header-actions">
        <?php if ($isAdminUser): ?>
        <a href="settings.php?tab=google_drive_folders" class="btn btn-secondary" title="PDF保存先フォルダ設定">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:0.35rem;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>保存先
        </a>
        <?php endif; ?>
        <?php if ($canEditApproval): ?>
        <?= uiNewButton('新規登録', ['id' => 'btnNewApproval']) ?>
        <?php endif; ?>
    </div>
</div>
<?php if ($isAdminUser && !$discountDriveFolder): ?>
<div style="background:#fff3e0;border:1px solid #ffcc80;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.85rem;color:#e65100;">
    【注意】PDFの保存先Driveフォルダが未設定です。<a href="settings.php?tab=google_drive_folders" style="color:#e65100;font-weight:600;">設定ページ</a>から設定してください（未設定の場合はマイドライブ直下に保存されます）。
</div>
<?php endif; ?>
<?php if ($isAdminUser): ?>
<div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
    <label style="font-size:0.85rem;font-weight:600;">月別集計:</label>
    <input type="month" class="form-input" id="approvalMonthPicker" style="width:180px;">
</div>
<div id="approvalMonthlySummary" style="display:none;margin-bottom:1rem;"></div>
<?php endif; ?>
<div id="approvalList"></div>

<!-- 値引きモーダル -->
<div class="hub-modal" id="approvalModal">
<div class="hub-modal-content" style="max-width:520px;">
    <div class="hub-modal-header">
        <h3 id="approvalModalTitle">値引き申請</h3>
        <button class="hub-modal-close" data-close-hub-modal>&times;</button>
    </div>
    <div class="hub-modal-body">
        <div id="approvalEditNote" style="display:none;background:#fff3e0;border:1px solid #ffe0b2;border-radius:6px;padding:8px 12px;font-size:0.82rem;color:#e65100;margin-bottom:0.75rem;">
            このまま送信すると承認者全員に「再申請メール」が再送されます。
        </div>
        <div class="form-group">
            <label class="form-label">案件名 <span style="color:#c62828;">*</span></label>
            <input type="text" class="form-input" id="apprProjectName" placeholder="案件名">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
                <label class="form-label">レンタル期間 <span style="color:#c62828;">*</span></label>
                <input type="text" class="form-input" id="apprRentalPeriod" placeholder="例: 12ヶ月">
            </div>
            <div class="form-group">
                <label class="form-label">販売額 <span style="color:#c62828;">*</span></label>
                <input type="text" class="form-input" id="apprSalesAmount" placeholder="例: 月額150,000円">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
                <label class="form-label">値引き前金額 <span style="color:#c62828;">*</span></label>
                <input type="number" class="form-input" id="apprOriginalAmount" min="0" placeholder="0">
            </div>
            <div class="form-group">
                <label class="form-label">値引き額 <span style="color:#c62828;">*</span></label>
                <input type="number" class="form-input" id="apprDiscountAmount" min="0" placeholder="0">
            </div>
        </div>
        <div style="font-size:0.85rem;color:var(--gray-500);margin-bottom:0.75rem;">
            値引き後: <strong id="apprAfterAmount">¥0</strong>（<span id="apprRate">0</span>%引き）
        </div>
        <div class="form-group">
            <label class="form-label">理由 <span style="color:#c62828;">*</span></label>
            <textarea class="form-input" id="apprReason" rows="3" placeholder="値引き理由を入力"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">添付PDF（任意 / 最大25MB）</label>
            <input type="file" id="apprPdfFile" accept="application/pdf" class="form-input" style="padding:0.4rem;">
            <div id="apprPdfStatus" style="font-size:0.78rem;color:var(--gray-500);margin-top:0.25rem;"></div>
        </div>
    </div>
    <div class="hub-modal-footer">
        <button class="btn btn-secondary" data-close-hub-modal>キャンセル</button>
        <button class="btn btn-primary" id="btnSubmitApproval">申請</button>
    </div>
</div>
</div>

<!-- 値引き申請 詳細モーダル -->
<div class="hub-modal" id="apprDetailModal">
<div class="hub-modal-content" style="max-width:600px;">
    <div class="hub-modal-header">
        <h3 id="apprDetailTitle">値引き申請</h3>
        <button class="hub-modal-close" data-close-hub-modal>&times;</button>
    </div>
    <div class="hub-modal-body" id="apprDetailBody"></div>
    <div class="hub-modal-footer" id="apprDetailFooter"></div>
</div>
</div>

<!-- 審査モーダル -->
<div class="hub-modal" id="reviewModal">
<div class="hub-modal-content" style="max-width:420px;">
    <div class="hub-modal-header">
        <h3 id="reviewModalTitle">承認</h3>
        <button class="hub-modal-close" data-close-hub-modal>&times;</button>
    </div>
    <div class="hub-modal-body">
        <p id="reviewModalDesc" style="font-size:0.85rem;color:var(--gray-600);"></p>
        <div id="reviewModalDetail" style="background:var(--gray-50);border-radius:8px;padding:0.8rem;margin:0.5rem 0;font-size:0.82rem;"></div>
        <div class="form-group">
            <label class="form-label">コメント（任意）</label>
            <textarea class="form-input" id="reviewComment" rows="2"></textarea>
        </div>
    </div>
    <div class="hub-modal-footer">
        <button class="btn btn-secondary" data-close-hub-modal>キャンセル</button>
        <button class="btn btn-primary" id="btnConfirmReview">確定</button>
    </div>
</div>
</div>

<script<?= nonceAttr() ?>>
(function(){
    'use strict';
    const RH = window.ReportsHub;
    const CSRF = '<?= generateCsrfToken() ?>';
    const API  = '/api/reports-hub-api.php';
    const CAN_EDIT_APPROVAL = <?= $canEditApproval ? 'true' : 'false' ?>;
    const CAN_DEL  = <?= $canDel ? 'true' : 'false' ?>;
    const IS_ADMIN = <?= $isAdminUser ? 'true' : 'false' ?>;
    const ME = <?= json_encode($currentUser) ?>;

    const esc = RH.esc, fmt = RH.fmt;
    const showAlert = RH.showAlert, openModal = RH.openModal, closeModal = RH.closeModal;
    const apiGet  = (type, action, extra) => RH.apiGet(API, type, action, extra);
    const apiPost = (params) => RH.apiPost(API, CSRF, params);

    let allApprovals = [];
    let approvalFilter = 'all';
    let editingApprovalId = null;
    let pendingReviewAction = '';
    let pendingReviewId = '';

    async function loadApprovals() {
        const res = await apiGet('approval', 'list');
        allApprovals = res.items || [];
        renderApprovals();
        renderApprovalMonthlySummary();
    }

    function renderApprovals() {
        const filtered = approvalFilter === 'all' ? allApprovals : allApprovals.filter(a => a.status === approvalFilter);
        const c = document.getElementById('approvalList');
        if (!c) return;

        if (!filtered.length) { c.innerHTML = '<p style="color:var(--gray-400);text-align:center;padding:2rem;">申請はありません</p>'; return; }

        c.innerHTML = filtered.map(a => {
            const after = a.original_amount - a.discount_amount;
            const rate = a.original_amount > 0 ? Math.round(a.discount_amount / a.original_amount * 100) : 0;
            const statusLabel = a.status === 'pending' ? '承認待ち' : a.status === 'approved' ? '承認済み' : '却下';
            return `<div class="hub-card" data-action="view-approval" data-id="${esc(a.id)}" style="cursor:pointer;">
                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <span class="status-badge ${a.status}">${statusLabel}</span>
                    <span class="hub-card-title" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(a.project_name)}</span>
                    ${a.resubmit_count ? '<span style="font-size:0.72rem;color:#e65100;background:#fff3e0;border:1px solid #ffe0b2;padding:1px 6px;border-radius:10px;">再申請' + a.resubmit_count + '回</span>' : ''}
                    <span style="white-space:nowrap;font-size:0.85rem;color:var(--gray-800);">¥${fmt(a.original_amount)} → <strong style="color:#16a34a;">¥${fmt(after)}</strong> <span style="color:var(--gray-500);font-size:0.78rem;">(${rate}%引)</span></span>
                </div>
            </div>`;
        }).join('');
    }

    function openApprovalDetail(id) {
        const a = allApprovals.find(x => x.id === id);
        if (!a) return;
        const after = a.original_amount - a.discount_amount;
        const rate = a.original_amount > 0 ? Math.round(a.discount_amount / a.original_amount * 100) : 0;
        const canEditOwn = CAN_EDIT_APPROVAL && (a.applicant_email === ME) && (a.status === 'pending' || a.status === 'rejected');
        const statusLabel = a.status === 'pending' ? '承認待ち' : a.status === 'approved' ? '承認済み' : '却下';

        document.getElementById('apprDetailTitle').textContent = a.project_name;

        const rows = [];
        rows.push(`<tr><th style="width:130px;">ステータス</th><td><span class="status-badge ${esc(a.status)}">${statusLabel}</span>${a.resubmit_count ? ' <span style="font-size:0.72rem;color:#e65100;background:#fff3e0;border:1px solid #ffe0b2;padding:1px 6px;border-radius:10px;margin-left:6px;">再申請'+a.resubmit_count+'回</span>' : ''}</td></tr>`);
        rows.push(`<tr><th>申請者</th><td>${esc(a.applicant_name || '')}</td></tr>`);
        rows.push(`<tr><th>申請日時</th><td>${esc(a.created_at || '')}</td></tr>`);
        if (a.rental_period) rows.push(`<tr><th>レンタル期間</th><td>${esc(a.rental_period)}</td></tr>`);
        if (a.sales_amount)  rows.push(`<tr><th>販売額</th><td>${esc(a.sales_amount)}</td></tr>`);
        rows.push(`<tr><th>値引き前金額</th><td>¥${fmt(a.original_amount)}</td></tr>`);
        rows.push(`<tr><th>値引き額</th><td style="color:#dc2626;font-weight:600;">-¥${fmt(a.discount_amount)} (${rate}%引き)</td></tr>`);
        rows.push(`<tr><th>値引き後金額</th><td style="color:#16a34a;font-weight:700;">¥${fmt(after)}</td></tr>`);
        rows.push(`<tr><th>理由</th><td style="white-space:pre-wrap;">${esc(a.reason || '')}</td></tr>`);
        if (a.drive_view_link) {
            rows.push(`<tr><th>添付PDF</th><td><a href="${esc(a.drive_view_link)}" target="_blank" rel="noopener" style="color:var(--primary);">PDFを開く</a></td></tr>`);
        }
        if (a.reviewed_at) {
            rows.push(`<tr><th>審査日時</th><td>${esc(a.reviewed_at)}</td></tr>`);
            if (a.review_comment) rows.push(`<tr><th>審査コメント</th><td style="white-space:pre-wrap;">${esc(a.review_comment)}</td></tr>`);
        }
        if (a.last_resent_at) rows.push(`<tr><th>最終再送日時</th><td>${esc(a.last_resent_at)}${a.resend_count ? ' （計'+a.resend_count+'回）' : ''}</td></tr>`);

        document.getElementById('apprDetailBody').innerHTML =
            `<table class="info-table" style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                <style>#apprDetailBody .info-table th,#apprDetailBody .info-table td{padding:8px 12px;border-bottom:1px solid var(--gray-100);text-align:left;vertical-align:top;}#apprDetailBody .info-table th{color:var(--gray-500);font-weight:600;background:transparent;}</style>
                ${rows.join('')}
            </table>`;

        const buttons = [];
        buttons.push('<button class="btn btn-secondary" data-close-hub-modal>閉じる</button>');
        if (canEditOwn) buttons.push('<button class="btn btn-secondary" data-action="edit-approval" data-id="'+esc(a.id)+'">編集して再申請</button>');
        if (IS_ADMIN && (a.status === 'pending' || a.status === 'rejected')) buttons.push('<button class="btn btn-secondary" data-action="resend-approval" data-id="'+esc(a.id)+'" title="承認者全員にメール再送（内容変更なし）">メール再送</button>');
        if (CAN_DEL) buttons.push('<button class="btn btn-danger" data-action="delete-approval" data-id="'+esc(a.id)+'">削除</button>');
        if (IS_ADMIN && a.status === 'pending') {
            buttons.push('<button class="btn btn-danger" data-action="review-approval" data-id="'+esc(a.id)+'" data-act="reject">却下</button>');
            buttons.push('<button class="btn btn-primary" data-action="review-approval" data-id="'+esc(a.id)+'" data-act="approve">承認</button>');
        }
        document.getElementById('apprDetailFooter').innerHTML = buttons.join('');

        openModal('apprDetailModal');
    }

    // フィルタ
    document.getElementById('approvalFilters').addEventListener('click', e => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;
        document.querySelectorAll('#approvalFilters .filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        approvalFilter = btn.dataset.filter;
        renderApprovals();
    });

    // 月別集計 (管理者のみ)
    const monthPicker = document.getElementById('approvalMonthPicker');
    if (monthPicker) {
        const now = new Date();
        monthPicker.value = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
        monthPicker.addEventListener('change', renderApprovalMonthlySummary);
    }

    function renderApprovalMonthlySummary() {
        const container = document.getElementById('approvalMonthlySummary');
        if (!container || !monthPicker) return;
        const ym = monthPicker.value;
        if (!ym) { container.style.display = 'none'; return; }

        const monthly = allApprovals.filter(a => (a.created_at || '').startsWith(ym));
        if (!monthly.length) {
            container.style.display = 'block';
            container.innerHTML = '<div style="background:var(--gray-50);border-radius:8px;padding:1rem;font-size:0.85rem;color:var(--gray-500);text-align:center;">この月の申請はありません</div>';
            return;
        }

        const byUser = {};
        monthly.forEach(a => {
            const name = a.applicant_name || a.applicant_email || '不明';
            if (!byUser[name]) byUser[name] = { count: 0, totalDiscount: 0, pending: 0, approved: 0, rejected: 0 };
            byUser[name].count++;
            byUser[name].totalDiscount += Number(a.discount_amount) || 0;
            if (a.status === 'pending') byUser[name].pending++;
            else if (a.status === 'approved') byUser[name].approved++;
            else if (a.status === 'rejected') byUser[name].rejected++;
        });

        const totalDiscount = monthly.reduce((s, a) => s + (Number(a.discount_amount) || 0), 0);
        const label = ym.replace('-', '年') + '月';

        let html = `<div style="background:var(--gray-50);border-radius:8px;padding:1rem;">
            <div style="font-weight:700;font-size:0.9rem;margin-bottom:0.75rem;">${esc(label)} 値引き申請サマリー（全${monthly.length}件 / 合計 ¥${fmt(totalDiscount)}）</div>
            <table style="width:100%;font-size:0.85rem;border-collapse:collapse;">
                <thead><tr style="border-bottom:2px solid var(--gray-300);text-align:left;">
                    <th style="padding:6px 8px;">申請者</th>
                    <th style="padding:6px 8px;text-align:center;">件数</th>
                    <th style="padding:6px 8px;text-align:right;">値引き合計</th>
                    <th style="padding:6px 8px;text-align:center;">承認待ち</th>
                    <th style="padding:6px 8px;text-align:center;">承認</th>
                    <th style="padding:6px 8px;text-align:center;">却下</th>
                </tr></thead><tbody>`;

        Object.keys(byUser).sort().forEach(name => {
            const u = byUser[name];
            html += `<tr style="border-bottom:1px solid var(--gray-200);">
                <td style="padding:6px 8px;font-weight:600;">${esc(name)}</td>
                <td style="padding:6px 8px;text-align:center;">${u.count}</td>
                <td style="padding:6px 8px;text-align:right;">¥${fmt(u.totalDiscount)}</td>
                <td style="padding:6px 8px;text-align:center;">${u.pending || '-'}</td>
                <td style="padding:6px 8px;text-align:center;">${u.approved || '-'}</td>
                <td style="padding:6px 8px;text-align:center;">${u.rejected || '-'}</td>
            </tr>`;
        });

        html += '</tbody></table></div>';
        container.style.display = 'block';
        container.innerHTML = html;
    }

    function updateApprovalCalc() {
        const orig = parseInt(document.getElementById('apprOriginalAmount').value) || 0;
        const disc = parseInt(document.getElementById('apprDiscountAmount').value) || 0;
        const after = Math.max(0, orig - disc);
        const rate = orig > 0 ? Math.round(disc / orig * 100) : 0;
        document.getElementById('apprAfterAmount').textContent = '¥' + fmt(after);
        document.getElementById('apprRate').textContent = rate;
    }
    document.getElementById('apprOriginalAmount')?.addEventListener('input', updateApprovalCalc);
    document.getElementById('apprDiscountAmount')?.addEventListener('input', updateApprovalCalc);

    function resetApprovalModal(mode) {
        editingApprovalId = (mode === 'edit') ? editingApprovalId : null;
        const isEdit = (mode === 'edit');
        document.getElementById('approvalModalTitle').textContent = isEdit ? '値引き申請を編集して再申請' : '値引き申請';
        document.getElementById('btnSubmitApproval').textContent = isEdit ? '再申請を送信' : '申請';
        document.getElementById('approvalEditNote').style.display = isEdit ? '' : 'none';
    }

    document.getElementById('btnNewApproval')?.addEventListener('click', () => {
        editingApprovalId = null;
        ['apprProjectName','apprRentalPeriod','apprSalesAmount','apprOriginalAmount','apprDiscountAmount','apprReason'].forEach(id => document.getElementById(id).value = '');
        const pdfInput = document.getElementById('apprPdfFile');
        if (pdfInput) pdfInput.value = '';
        const pdfStatus = document.getElementById('apprPdfStatus');
        if (pdfStatus) pdfStatus.textContent = '';
        resetApprovalModal('new');
        updateApprovalCalc();
        openModal('approvalModal');
    });

    function openEditApproval(id) {
        const a = allApprovals.find(x => x.id === id);
        if (!a) return showAlert('申請が見つかりません', 'danger');
        if (a.applicant_email !== ME) return showAlert('編集権限がありません（申請者本人のみ）', 'danger');
        if (a.status !== 'pending' && a.status !== 'rejected') return showAlert('承認済みの申請は編集できません', 'danger');

        editingApprovalId = id;
        document.getElementById('apprProjectName').value    = a.project_name || '';
        document.getElementById('apprRentalPeriod').value   = a.rental_period || '';
        document.getElementById('apprSalesAmount').value    = a.sales_amount || '';
        document.getElementById('apprOriginalAmount').value = a.original_amount || '';
        document.getElementById('apprDiscountAmount').value = a.discount_amount || '';
        document.getElementById('apprReason').value         = a.reason || '';
        const pdfInput = document.getElementById('apprPdfFile');
        if (pdfInput) pdfInput.value = '';
        const pdfStatus = document.getElementById('apprPdfStatus');
        if (pdfStatus) pdfStatus.textContent = a.drive_file_id ? '既存PDFあり（差し替えたい場合のみ選択）' : '';
        resetApprovalModal('edit');
        updateApprovalCalc();
        openModal('approvalModal');
    }

    async function uploadApprovalPdf(file) {
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('pdf', file);
        const r = await fetch('/api/upload-discount-pdf.php', { method: 'POST', body: fd });
        if (!r.ok) {
            const t = await r.text();
            throw new Error('HTTP ' + r.status + ': ' + t.substring(0, 200));
        }
        const json = await r.json();
        if (!json.success) throw new Error(json.error || 'アップロード失敗');
        return json.data || json;
    }

    document.getElementById('btnSubmitApproval')?.addEventListener('click', async () => {
        const submitBtn = document.getElementById('btnSubmitApproval');
        const pdfInput  = document.getElementById('apprPdfFile');
        const pdfStatus = document.getElementById('apprPdfStatus');

        let driveInfo = {};
        if (pdfInput && pdfInput.files && pdfInput.files[0]) {
            const file = pdfInput.files[0];
            if (file.size > 25 * 1024 * 1024) {
                return showAlert('PDFは25MB以内にしてください', 'danger');
            }
            if (file.type !== 'application/pdf') {
                return showAlert('PDFファイルを選択してください', 'danger');
            }
            try {
                submitBtn.disabled = true;
                if (pdfStatus) pdfStatus.textContent = 'Driveにアップロード中...';
                const up = await uploadApprovalPdf(file);
                driveInfo = {
                    drive_file_id: up.drive_file_id || '',
                    drive_view_link: up.drive_view_link || '',
                    drive_download_link: up.drive_download_link || '',
                    drive_file_name: up.drive_file_name || '',
                    original_name: up.original_name || '',
                };
                if (pdfStatus) pdfStatus.textContent = 'アップロード完了';
            } catch (err) {
                submitBtn.disabled = false;
                if (pdfStatus) pdfStatus.textContent = '';
                return showAlert('PDFアップロード失敗: ' + err.message, 'danger');
            }
        }

        const isEdit = !!editingApprovalId;
        const params = {
            type: 'approval',
            action: isEdit ? 'update' : 'create',
            project_name: document.getElementById('apprProjectName').value,
            rental_period: document.getElementById('apprRentalPeriod').value,
            sales_amount: document.getElementById('apprSalesAmount').value,
            original_amount: document.getElementById('apprOriginalAmount').value,
            discount_amount: document.getElementById('apprDiscountAmount').value,
            reason: document.getElementById('apprReason').value,
            ...driveInfo,
        };
        if (isEdit) params.id = editingApprovalId;

        const res = await apiPost(params);
        submitBtn.disabled = false;
        if (res.error) return showAlert(res.error, 'danger');
        showAlert(isEdit ? '再申請を送信しました（承認者全員に通知）' : '値引き申請を送信しました', 'success');
        editingApprovalId = null;
        closeModal('approvalModal');
        loadApprovals();
    });

    async function handleApprovalAction(btn) {
        if (btn.dataset.action === 'view-approval') {
            openApprovalDetail(btn.dataset.id);
            return;
        }
        if (btn.dataset.action === 'review-approval') {
            pendingReviewAction = btn.dataset.act;
            pendingReviewId = btn.dataset.id;
            const label = pendingReviewAction === 'approve' ? '承認' : '却下';
            document.getElementById('reviewModalTitle').textContent = label;
            document.getElementById('reviewModalDesc').textContent = `この値引き申請を${label}しますか？`;
            document.getElementById('reviewComment').value = '';

            const a = allApprovals.find(x => x.id === pendingReviewId);
            const detailEl = document.getElementById('reviewModalDetail');
            if (a) {
                const after = a.original_amount - a.discount_amount;
                const rate = a.original_amount > 0 ? Math.round(a.discount_amount / a.original_amount * 100) : 0;
                detailEl.innerHTML = `
                    <div style="margin-bottom:0.3rem;"><strong>${esc(a.project_name)}</strong></div>
                    ${a.rental_period ? '<div>レンタル期間: ' + esc(a.rental_period) + '</div>' : ''}
                    ${a.sales_amount ? '<div>販売額: ' + esc(a.sales_amount) + '</div>' : ''}
                    <div style="margin-top:0.3rem;">¥${fmt(a.original_amount)} → <span style="color:var(--danger);">-¥${fmt(a.discount_amount)}</span> → <strong>¥${fmt(after)}</strong> (${rate}%引き)</div>
                    <div style="margin-top:0.3rem;color:var(--gray-600);">${esc(a.reason)}</div>
                    ${a.drive_view_link ? '<div style="margin-top:0.5rem;"><a href="' + esc(a.drive_view_link) + '" target="_blank" rel="noopener" style="color:var(--primary);font-weight:600;">添付PDFを確認する</a></div>' : ''}
                `;
                detailEl.style.display = '';
            } else {
                detailEl.style.display = 'none';
            }

            openModal('reviewModal');
        }
        if (btn.dataset.action === 'delete-approval') {
            if (!confirm('この申請を削除しますか？')) return;
            const res = await apiPost({ type: 'approval', action: 'delete', id: btn.dataset.id });
            if (res.error) return showAlert(res.error, 'danger');
            showAlert('削除しました', 'success');
            loadApprovals();
        }
        if (btn.dataset.action === 'edit-approval') {
            openEditApproval(btn.dataset.id);
        }
        if (btn.dataset.action === 'resend-approval') {
            if (!confirm('この申請のメールを承認者全員に再送しますか？\n（内容変更なし。前回のメールリンクは無効になります）')) return;
            btn.disabled = true;
            try {
                const res = await apiPost({ type: 'approval', action: 'resend_email', id: btn.dataset.id });
                if (res.error) { showAlert(res.error, 'danger'); return; }
                showAlert('承認者全員にメールを再送しました', 'success');
                loadApprovals();
            } finally {
                btn.disabled = false;
            }
        }
    }

    document.getElementById('approvalList').addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        handleApprovalAction(btn);
    });

    document.getElementById('apprDetailModal').addEventListener('click', async e => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const act = btn.dataset.action;
        if (['review-approval','delete-approval','edit-approval','resend-approval'].includes(act)) {
            closeModal('apprDetailModal');
        }
        await handleApprovalAction(btn);
    });

    document.getElementById('btnConfirmReview')?.addEventListener('click', async () => {
        const res = await apiPost({
            type: 'approval', action: pendingReviewAction,
            id: pendingReviewId,
            comment: document.getElementById('reviewComment').value,
        });
        if (res.error) return showAlert(res.error, 'danger');
        const label = pendingReviewAction === 'approve' ? '承認' : '却下';
        showAlert(`${label}しました`, 'success');
        closeModal('reviewModal');
        loadApprovals();
    });

    // 初期ロード
    loadApprovals().catch(e => console.error('loadApprovals failed:', e));
})();
</script>
