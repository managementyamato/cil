<?php
require_once '../api/auth.php';
require_once '../functions/header.php';

$canEdit = canEditCurrentPage();
$canDel  = canDelete();
?>

<style<?= nonceAttr() ?>>
/* ===== 商談パイプライン ===== */

/* サマリーバー */
.pipeline-summary {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.summary-card {
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    padding: 1rem 1.5rem;
    min-width: 180px;
    flex: 1;
}
.summary-card .label {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-bottom: 0.25rem;
}
.summary-card .value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-900);
}

/* フィルターバー */
.pipeline-filters {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    align-items: center;
}

/* ビュー切替 */
.view-toggle {
    display: flex;
    gap: 0;
    margin-left: auto;
}
.view-toggle button {
    padding: 0.4rem 0.75rem;
    border: 1px solid var(--gray-300);
    background: #fff;
    cursor: pointer;
    font-size: 0.85rem;
    color: var(--gray-600);
}
.view-toggle button:first-child {
    border-radius: 6px 0 0 6px;
}
.view-toggle button:last-child {
    border-radius: 0 6px 6px 0;
    border-left: none;
}
.view-toggle button.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}

/* カンバンボード */
.kanban-board {
    display: flex;
    gap: 0.75rem;
    overflow-x: auto;
    padding-bottom: 1rem;
}
.kanban-column {
    min-width: 240px;
    max-width: 280px;
    flex: 1;
    border-radius: 8px;
    padding: 0.75rem;
}
.kanban-column-header {
    font-weight: 600;
    font-size: 0.9rem;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.kanban-column-header .count {
    background: rgba(0,0,0,0.1);
    border-radius: 10px;
    padding: 0.1rem 0.5rem;
    font-size: 0.75rem;
}

/* ディールカード */
.deal-card {
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: box-shadow 0.15s;
}
.deal-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.deal-card-title {
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0.25rem;
    color: var(--gray-900);
}
.deal-card-customer {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-bottom: 0.5rem;
}
.deal-card-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.75rem;
    color: var(--gray-600);
}
.deal-card-amount {
    font-weight: 600;
    color: var(--gray-800);
}
.deal-card-probability {
    display: inline-block;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    background: var(--gray-100);
    font-size: 0.7rem;
}
.deal-card-assignee {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 0.35rem;
}
.deal-card-date {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 0.25rem;
}

/* テーブルビュー */
.pipeline-table-view {
    display: none;
}
.pipeline-table-view.active {
    display: block;
}
.kanban-board.active {
    display: flex;
}
.kanban-board {
    display: none;
}

/* モーダル内 */
.form-row-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

/* ステージバッジ */
.stage-badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

@media (max-width: 768px) {
    .kanban-board {
        flex-direction: column;
    }
    .kanban-column {
        max-width: 100%;
        min-width: 100%;
    }
    .form-row-2col {
        grid-template-columns: 1fr;
    }
    .pipeline-summary {
        flex-direction: column;
    }
}
</style>

<div class="page-container">
    <div id="alertContainer"></div>

    <div class="page-header">
        <h2>商談パイプライン</h2>
        <div class="page-header-actions">
            <?php if ($canEdit): ?>
            <button type="button" class="btn btn-primary" data-action="openAddModal">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                新規商談
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- サマリーバー -->
    <div class="pipeline-summary">
        <div class="summary-card">
            <div class="label">商談数</div>
            <div class="value" id="summaryCount">0</div>
        </div>
        <div class="summary-card">
            <div class="label">パイプライン合計</div>
            <div class="value" id="summaryTotal">¥0</div>
        </div>
        <div class="summary-card">
            <div class="label">加重合計（金額 x 確度）</div>
            <div class="value" id="summaryWeighted">¥0</div>
        </div>
    </div>

    <!-- フィルター＋ビュー切替 -->
    <div class="pipeline-filters">
        <select id="filterStage" class="form-input" style="width:auto;min-width:120px;">
            <option value="">全ステージ</option>
        </select>
        <select id="filterAssignee" class="form-input" style="width:auto;min-width:120px;">
            <option value="">全担当者</option>
        </select>
        <div class="view-toggle">
            <button type="button" id="btnKanban" class="active" data-action="viewKanban">カンバン</button>
            <button type="button" id="btnTable" data-action="viewTable">テーブル</button>
        </div>
    </div>

    <!-- カンバンビュー -->
    <div id="kanbanView" class="kanban-board active"></div>

    <!-- テーブルビュー -->
    <div id="tableView" class="pipeline-table-view">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>案件名</th>
                        <th>顧客名</th>
                        <th>ステージ</th>
                        <th>金額</th>
                        <th>確度</th>
                        <th>担当者</th>
                        <th>見込日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="8" class="text-center text-muted p-2rem">読込中...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 商談追加/編集モーダル -->
<div id="dealModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="dealModalTitle">新規商談</h3>
            <button type="button" class="close" data-close-modal="dealModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="dealForm">
                <?= csrfTokenField() ?>
                <input type="hidden" id="dealId" name="id">

                <div class="form-row-2col">
                    <div class="form-group">
                        <label for="dealCustomerName">顧客名 <span class="required">*</span></label>
                        <input type="text" id="dealCustomerName" name="customer_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="dealTitle">案件名 <span class="required">*</span></label>
                        <input type="text" id="dealTitle" name="title" class="form-input" required>
                    </div>
                </div>

                <div class="form-row-2col">
                    <div class="form-group">
                        <label for="dealAmount">金額（円）</label>
                        <input type="number" id="dealAmount" name="amount" class="form-input" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label for="dealProbability">確度</label>
                        <select id="dealProbability" name="probability" class="form-input">
                            <option value="10">10%</option>
                            <option value="20">20%</option>
                            <option value="30">30%</option>
                            <option value="40">40%</option>
                            <option value="50">50%</option>
                            <option value="60">60%</option>
                            <option value="70">70%</option>
                            <option value="80">80%</option>
                            <option value="90">90%</option>
                        </select>
                    </div>
                </div>

                <div class="form-row-2col">
                    <div class="form-group">
                        <label for="dealStage">ステージ</label>
                        <select id="dealStage" name="stage" class="form-input">
                            <option value="リード">リード</option>
                            <option value="初回接触">初回接触</option>
                            <option value="提案中">提案中</option>
                            <option value="見積提出">見積提出</option>
                            <option value="交渉中">交渉中</option>
                            <option value="受注">受注</option>
                            <option value="失注">失注</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dealAssignee">担当者</label>
                        <select id="dealAssignee" name="assignee" class="form-input">
                            <option value="">未設定</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="dealExpectedCloseDate">受注見込日</label>
                    <input type="date" id="dealExpectedCloseDate" name="expected_close_date" class="form-input">
                </div>

                <div class="form-group">
                    <label for="dealMemo">メモ</label>
                    <textarea id="dealMemo" name="memo" class="form-input" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <?php if ($canDel): ?>
            <button type="button" class="btn btn-danger" id="btnDeleteDeal" data-action="deleteDeal" style="margin-right:auto;display:none;">削除</button>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary" data-close-modal="dealModal">キャンセル</button>
            <button type="submit" form="dealForm" class="btn btn-primary" id="btnSaveDeal">保存</button>
        </div>
    </div>
</div>

<script<?= nonceAttr() ?>>
(function() {
    const csrfToken = '<?= generateCsrfToken() ?>';
    const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
    const canDel = <?= $canDel ? 'true' : 'false' ?>;

    const STAGES = ['リード','初回接触','提案中','見積提出','交渉中','受注','失注'];
    const STAGE_COLORS = {
        'リード':   '#e3f2fd',
        '初回接触': '#fff3e0',
        '提案中':   '#f3e5f5',
        '見積提出': '#e8f5e9',
        '交渉中':   '#fff9c4',
        '受注':     '#c8e6c9',
        '失注':     '#f5f5f5'
    };

    let allDeals = [];
    let employees = [];
    let currentView = 'kanban'; // 'kanban' | 'table'

    // ===== ユーティリティ =====
    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function formatYen(n) {
        return '¥' + Number(n || 0).toLocaleString();
    }

    function showAlert(message, type) {
        const container = document.getElementById('alertContainer');
        if (!container) return;
        const alert = document.createElement('div');
        alert.className = 'alert alert-' + type;
        alert.textContent = message;
        container.appendChild(alert);
        setTimeout(() => alert.remove(), 4000);
    }

    function setLoading(btn, isLoading, text) {
        btn.disabled = isLoading;
        if (isLoading) {
            btn.dataset.originalText = btn.textContent;
            btn.textContent = text || '処理中...';
        } else {
            btn.textContent = btn.dataset.originalText || btn.textContent;
        }
    }

    async function apiPost(payload) {
        const res = await fetch('/api/pipeline-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '処理に失敗しました');
        return data;
    }

    // ===== データ読込 =====
    async function loadDeals() {
        const stage = document.getElementById('filterStage').value;
        const assignee = document.getElementById('filterAssignee').value;
        let url = '/api/pipeline-api.php?action=list';
        if (stage) url += '&stage=' + encodeURIComponent(stage);
        if (assignee) url += '&assignee=' + encodeURIComponent(assignee);

        const res = await fetch(url);
        const data = await res.json();
        if (!data.success) { showAlert(data.error || '読込失敗', 'danger'); return; }

        allDeals = data.data.deals || [];
        employees = data.data.employees || [];

        populateAssigneeSelects();
        populateStageFilter();
        updateSummary();
        renderCurrentView();
    }

    function populateAssigneeSelects() {
        // フィルター用
        const filterSel = document.getElementById('filterAssignee');
        const currentFilterVal = filterSel.value;
        filterSel.innerHTML = '<option value="">全担当者</option>';
        employees.forEach(name => {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            filterSel.appendChild(opt);
        });
        filterSel.value = currentFilterVal;

        // モーダル用
        const modalSel = document.getElementById('dealAssignee');
        const currentModalVal = modalSel.value;
        modalSel.innerHTML = '<option value="">未設定</option>';
        employees.forEach(name => {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            modalSel.appendChild(opt);
        });
        modalSel.value = currentModalVal;
    }

    function populateStageFilter() {
        const sel = document.getElementById('filterStage');
        const currentVal = sel.value;
        sel.innerHTML = '<option value="">全ステージ</option>';
        STAGES.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s;
            opt.textContent = s;
            sel.appendChild(opt);
        });
        sel.value = currentVal;
    }

    function updateSummary() {
        // 失注を除いた集計
        const activeDeals = allDeals.filter(d => d.stage !== '失注');
        const count = activeDeals.length;
        const total = activeDeals.reduce((s, d) => s + (d.amount || 0), 0);
        const weighted = activeDeals.reduce((s, d) => s + (d.amount || 0) * (d.probability || 0) / 100, 0);

        document.getElementById('summaryCount').textContent = count;
        document.getElementById('summaryTotal').textContent = formatYen(total);
        document.getElementById('summaryWeighted').textContent = formatYen(Math.round(weighted));
    }

    // ===== カンバン描画 =====
    function renderKanban() {
        const board = document.getElementById('kanbanView');
        board.innerHTML = '';

        STAGES.forEach(stage => {
            const stageDeals = allDeals.filter(d => (d.stage || 'リード') === stage);
            const col = document.createElement('div');
            col.className = 'kanban-column';
            col.style.background = STAGE_COLORS[stage] || '#f5f5f5';

            col.innerHTML =
                '<div class="kanban-column-header">' +
                    '<span>' + escapeHtml(stage) + '</span>' +
                    '<span class="count">' + stageDeals.length + '</span>' +
                '</div>';

            stageDeals.forEach(deal => {
                const card = document.createElement('div');
                card.className = 'deal-card';
                card.dataset.action = 'editDeal';
                card.dataset.id = deal.id;
                card.innerHTML =
                    '<div class="deal-card-title">' + escapeHtml(deal.title) + '</div>' +
                    '<div class="deal-card-customer">' + escapeHtml(deal.customer_name) + '</div>' +
                    '<div class="deal-card-meta">' +
                        '<span class="deal-card-amount">' + formatYen(deal.amount) + '</span>' +
                        '<span class="deal-card-probability">' + (deal.probability || 0) + '%</span>' +
                    '</div>' +
                    (deal.assignee ? '<div class="deal-card-assignee">' + escapeHtml(deal.assignee) + '</div>' : '') +
                    (deal.expected_close_date ? '<div class="deal-card-date">' + escapeHtml(deal.expected_close_date) + '</div>' : '');
                col.appendChild(card);
            });

            board.appendChild(col);
        });
    }

    // ===== テーブル描画 =====
    function renderTable() {
        const tbody = document.getElementById('tableBody');
        if (allDeals.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted p-2rem">データがありません</td></tr>';
            return;
        }

        tbody.innerHTML = allDeals.map(deal => {
            const stageColor = STAGE_COLORS[deal.stage] || '#f5f5f5';
            return '<tr>' +
                '<td>' + escapeHtml(deal.title) + '</td>' +
                '<td>' + escapeHtml(deal.customer_name) + '</td>' +
                '<td><span class="stage-badge" style="background:' + stageColor + '">' + escapeHtml(deal.stage || 'リード') + '</span></td>' +
                '<td style="text-align:right">' + formatYen(deal.amount) + '</td>' +
                '<td style="text-align:center">' + (deal.probability || 0) + '%</td>' +
                '<td>' + escapeHtml(deal.assignee || '-') + '</td>' +
                '<td>' + escapeHtml(deal.expected_close_date || '-') + '</td>' +
                '<td>' +
                    (canEdit ? '<button type="button" class="btn btn-sm btn-outline" data-action="editDeal" data-id="' + escapeHtml(deal.id) + '">編集</button>' : '') +
                '</td>' +
            '</tr>';
        }).join('');
    }

    function renderCurrentView() {
        if (currentView === 'kanban') {
            renderKanban();
        } else {
            renderTable();
        }
    }

    // ===== モーダル =====
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
    }

    function openAddModal() {
        document.getElementById('dealModalTitle').textContent = '新規商談';
        document.getElementById('dealForm').reset();
        document.getElementById('dealId').value = '';
        const delBtn = document.getElementById('btnDeleteDeal');
        if (delBtn) delBtn.style.display = 'none';
        openModal('dealModal');
    }

    function openEditModal(id) {
        const deal = allDeals.find(d => d.id === id);
        if (!deal) return;

        document.getElementById('dealModalTitle').textContent = '商談を編集';
        document.getElementById('dealId').value = deal.id;
        document.getElementById('dealCustomerName').value = deal.customer_name || '';
        document.getElementById('dealTitle').value = deal.title || '';
        document.getElementById('dealAmount').value = deal.amount || 0;
        document.getElementById('dealProbability').value = deal.probability || 10;
        document.getElementById('dealStage').value = deal.stage || 'リード';
        document.getElementById('dealAssignee').value = deal.assignee || '';
        document.getElementById('dealExpectedCloseDate').value = deal.expected_close_date || '';
        document.getElementById('dealMemo').value = deal.memo || '';

        const delBtn = document.getElementById('btnDeleteDeal');
        if (delBtn) {
            delBtn.style.display = 'inline-block';
            delBtn.dataset.id = deal.id;
        }

        openModal('dealModal');
    }

    // ===== イベント =====
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const action = btn.dataset.action;
        const id = btn.dataset.id;

        switch (action) {
            case 'openAddModal': openAddModal(); break;
            case 'editDeal': openEditModal(id); break;
            case 'deleteDeal': confirmDelete(id); break;
            case 'viewKanban':
                currentView = 'kanban';
                document.getElementById('btnKanban').classList.add('active');
                document.getElementById('btnTable').classList.remove('active');
                document.getElementById('kanbanView').classList.add('active');
                document.getElementById('tableView').classList.remove('active');
                renderKanban();
                break;
            case 'viewTable':
                currentView = 'table';
                document.getElementById('btnTable').classList.add('active');
                document.getElementById('btnKanban').classList.remove('active');
                document.getElementById('tableView').classList.add('active');
                document.getElementById('kanbanView').classList.remove('active');
                renderTable();
                break;
        }
    });

    // モーダル閉じる
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modalId = btn.dataset.closeModal;
            document.getElementById(modalId).classList.remove('active');
        });
    });

    // フォーム送信
    document.getElementById('dealForm').addEventListener('submit', async e => {
        e.preventDefault();
        const btn = document.getElementById('btnSaveDeal');
        const id = document.getElementById('dealId').value;
        const isEdit = !!id;

        const payload = {
            action: isEdit ? 'update' : 'create',
            customer_name: document.getElementById('dealCustomerName').value,
            title: document.getElementById('dealTitle').value,
            amount: parseInt(document.getElementById('dealAmount').value) || 0,
            probability: parseInt(document.getElementById('dealProbability').value) || 10,
            stage: document.getElementById('dealStage').value,
            assignee: document.getElementById('dealAssignee').value,
            expected_close_date: document.getElementById('dealExpectedCloseDate').value,
            memo: document.getElementById('dealMemo').value,
        };
        if (isEdit) payload.id = id;

        setLoading(btn, true, '保存中...');
        try {
            const data = await apiPost(payload);
            showAlert(data.message || '保存しました', 'success');
            document.getElementById('dealModal').classList.remove('active');
            await loadDeals();
        } catch (err) {
            showAlert(err.message, 'danger');
        } finally {
            setLoading(btn, false);
        }
    });

    // 削除
    async function confirmDelete(id) {
        if (!confirm('この商談を削除しますか？')) return;
        try {
            const data = await apiPost({ action: 'delete', id: id });
            showAlert(data.message || '削除しました', 'success');
            document.getElementById('dealModal').classList.remove('active');
            await loadDeals();
        } catch (err) {
            showAlert(err.message, 'danger');
        }
    }

    // フィルター変更
    document.getElementById('filterStage').addEventListener('change', loadDeals);
    document.getElementById('filterAssignee').addEventListener('change', loadDeals);

    // 初期読込
    document.addEventListener('DOMContentLoaded', loadDeals);
})();
</script>
</div><!-- /.page-container -->

<?php require_once '../functions/footer.php'; ?>
