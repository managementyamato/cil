<?php
/**
 * 申請・報告ハブ - リード管理タブ (現在非公開)
 *
 * 期待する親側変数:
 *   $canEditLead, $canDel, $employees
 *
 * 注意: 現在 reports-hub.php の $REPORTS_TABS には登録されていない。
 *      公開する場合は親側で $REPORTS_TABS に 'lead' を追加してください。
 */
if (!defined('IN_HUB_PAGE')) { http_response_code(403); exit('Forbidden'); }
?>
<style<?= nonceAttr() ?>>
/* ── リードステータス ── */
.lead-status{display:inline-block;padding:2px 10px;border-radius:9px;font-size:0.73rem;font-weight:600;}
.lead-status[data-s="未接触"]{background:#e3f2fd;color:#1565c0;}
.lead-status[data-s="商談中"]{background:#fff3e0;color:#e65100;}
.lead-status[data-s="受注"]{background:#e8f5e9;color:#2e7d32;}
.lead-status[data-s="失注"]{background:#f5f5f5;color:#757575;}
</style>

<div class="settings-detail-header">
    <div class="filter-bar" id="leadFilters">
        <button class="filter-btn active" data-filter="all">すべて</button>
        <button class="filter-btn" data-filter="未接触">未接触</button>
        <button class="filter-btn" data-filter="商談中">商談中</button>
        <button class="filter-btn" data-filter="受注">受注</button>
        <button class="filter-btn" data-filter="失注">失注</button>
    </div>
    <div class="page-header-actions">
        <?php if ($canEditLead): ?>
        <?= uiNewButton('新規登録', ['id' => 'btnNewLead']) ?>
        <?php endif; ?>
    </div>
</div>
<div id="leadList"></div>

<!-- リードモーダル -->
<div class="hub-modal" id="leadModal">
<div class="hub-modal-content" style="max-width:560px;">
    <div class="hub-modal-header">
        <h3 id="leadModalTitle">リード登録</h3>
        <button class="hub-modal-close" data-close-hub-modal>&times;</button>
    </div>
    <div class="hub-modal-body">
        <input type="hidden" id="leadId">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
                <label class="form-label">会社名 <span style="color:#c62828;">*</span></label>
                <input type="text" class="form-input" id="leadCompanyName" placeholder="会社名">
            </div>
            <div class="form-group">
                <label class="form-label">担当者名</label>
                <input type="text" class="form-input" id="leadPersonName" placeholder="担当者名">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
                <label class="form-label">役職</label>
                <input type="text" class="form-input" id="leadTitle" placeholder="役職">
            </div>
            <div class="form-group">
                <label class="form-label">電話番号</label>
                <input type="text" class="form-input" id="leadPhone" placeholder="電話番号">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
                <label class="form-label">メール</label>
                <input type="email" class="form-input" id="leadEmail" placeholder="メールアドレス">
            </div>
            <div class="form-group">
                <label class="form-label">ステータス</label>
                <select class="form-input" id="leadStatus">
                    <option value="未接触">未接触</option>
                    <option value="商談中">商談中</option>
                    <option value="受注">受注</option>
                    <option value="失注">失注</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">営業担当</label>
            <select class="form-input" id="leadSalesAssignee">
                <option value="">選択してください</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= htmlspecialchars($emp['name'] ?? '') ?>" data-email="<?= htmlspecialchars($emp['email'] ?? '') ?>"><?= htmlspecialchars($emp['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">メモ</label>
            <textarea class="form-input" id="leadMemo" rows="3" placeholder="メモ"></textarea>
        </div>
    </div>
    <div class="hub-modal-footer">
        <button class="btn btn-secondary" data-close-hub-modal>キャンセル</button>
        <button class="btn btn-primary" id="btnSaveLead">保存</button>
    </div>
</div>
</div>

<script<?= nonceAttr() ?>>
(function(){
    'use strict';
    const RH = window.ReportsHub;
    const CSRF = '<?= generateCsrfToken() ?>';
    const API  = '/api/reports-hub-api.php';
    const CAN_EDIT_LEAD = <?= $canEditLead ? 'true' : 'false' ?>;
    const CAN_DEL = <?= $canDel ? 'true' : 'false' ?>;

    const esc = RH.esc, dateShort = RH.dateShort;
    const showAlert = RH.showAlert, openModal = RH.openModal, closeModal = RH.closeModal;
    const apiGet  = (type, action, extra) => RH.apiGet(API, type, action, extra);
    const apiPost = (params) => RH.apiPost(API, CSRF, params);

    let allLeads = [];
    let leadFilter = 'all';

    async function loadLeads() {
        const res = await apiGet('lead', 'list');
        allLeads = res.items || [];
        renderLeads();
    }

    function renderLeads() {
        const filtered = leadFilter === 'all' ? allLeads : allLeads.filter(l => (l.status || '未接触') === leadFilter);
        const c = document.getElementById('leadList');
        if (!c) return;
        if (!filtered.length) { c.innerHTML = '<p style="color:var(--gray-400);text-align:center;padding:2rem;">リードはありません</p>'; return; }

        c.innerHTML = filtered.map(l => `<div class="hub-card">
            <div class="hub-card-header">
                <div>
                    <span class="hub-card-title">${esc(l.company_name)}</span>
                    <span class="lead-status" data-s="${esc(l.status || '未接触')}" style="margin-left:8px;">${esc(l.status || '未接触')}</span>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    ${CAN_EDIT_LEAD ? '<button class="btn btn-sm btn-outline" data-action="edit-lead" data-id="'+esc(l.id)+'">編集</button>' : ''}
                    ${CAN_DEL && CAN_EDIT_LEAD ? '<button class="btn btn-sm btn-danger" data-action="delete-lead" data-id="'+esc(l.id)+'">削除</button>' : ''}
                </div>
            </div>
            <div style="display:flex;gap:1.5rem;font-size:0.82rem;color:var(--gray-600);">
                ${l.person_name ? '<span>'+esc(l.person_name)+'</span>' : ''}
                ${l.title ? '<span>'+esc(l.title)+'</span>' : ''}
                ${l.phone ? '<span>'+esc(l.phone)+'</span>' : ''}
                ${l.email ? '<span>'+esc(l.email)+'</span>' : ''}
            </div>
            <div style="display:flex;gap:1.5rem;font-size:0.82rem;color:var(--gray-500);margin-top:0.3rem;">
                ${l.sales_assignee ? '<span>営業: '+esc(l.sales_assignee)+'</span>' : ''}
                ${l.memo ? '<span>'+esc(l.memo.substring(0,80))+'</span>' : ''}
            </div>
            <div class="hub-card-meta" style="margin-top:0.4rem;">${esc(dateShort(l.created_at))}</div>
        </div>`).join('');
    }

    document.getElementById('leadFilters').addEventListener('click', e => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;
        document.querySelectorAll('#leadFilters .filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        leadFilter = btn.dataset.filter;
        renderLeads();
    });

    document.getElementById('btnNewLead')?.addEventListener('click', () => {
        document.getElementById('leadModalTitle').textContent = 'リード登録';
        document.getElementById('leadId').value = '';
        ['leadCompanyName','leadPersonName','leadTitle','leadPhone','leadEmail','leadMemo'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('leadStatus').value = '未接触';
        document.getElementById('leadSalesAssignee').value = '';
        openModal('leadModal');
    });

    document.getElementById('leadList').addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        if (btn.dataset.action === 'edit-lead') {
            const l = allLeads.find(l => l.id === btn.dataset.id);
            if (!l) return;
            document.getElementById('leadModalTitle').textContent = 'リード編集';
            document.getElementById('leadId').value = l.id;
            document.getElementById('leadCompanyName').value = l.company_name || '';
            document.getElementById('leadPersonName').value = l.person_name || '';
            document.getElementById('leadTitle').value = l.title || '';
            document.getElementById('leadPhone').value = l.phone || '';
            document.getElementById('leadEmail').value = l.email || '';
            document.getElementById('leadStatus').value = l.status || '未接触';
            document.getElementById('leadSalesAssignee').value = l.sales_assignee || '';
            document.getElementById('leadMemo').value = l.memo || '';
            openModal('leadModal');
        }
        if (btn.dataset.action === 'delete-lead') {
            if (!confirm('このリードを削除しますか？')) return;
            apiPost({ type: 'lead', action: 'delete', id: btn.dataset.id }).then(res => {
                if (res.error) return showAlert(res.error, 'danger');
                showAlert('削除しました', 'success');
                loadLeads();
            });
        }
    });

    document.getElementById('btnSaveLead')?.addEventListener('click', async () => {
        const id = document.getElementById('leadId').value;
        const sel = document.getElementById('leadSalesAssignee');
        const salesEmail = sel.selectedOptions[0]?.dataset?.email || '';
        const params = {
            type: 'lead', action: id ? 'update' : 'create',
            company_name: document.getElementById('leadCompanyName').value,
            person_name: document.getElementById('leadPersonName').value,
            title: document.getElementById('leadTitle').value,
            phone: document.getElementById('leadPhone').value,
            email: document.getElementById('leadEmail').value,
            status: document.getElementById('leadStatus').value,
            sales_assignee: sel.value,
            sales_email: salesEmail,
            memo: document.getElementById('leadMemo').value,
        };
        if (id) params.id = id;
        const res = await apiPost(params);
        if (res.error) return showAlert(res.error, 'danger');
        showAlert(id ? '更新しました' : 'リードを登録しました', 'success');
        closeModal('leadModal');
        loadLeads();
    });

    loadLeads().catch(e => console.error('loadLeads failed:', e));
})();
</script>
