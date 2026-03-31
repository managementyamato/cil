<?php
require_once '../api/auth.php';
require_once '../functions/header.php';

$data        = getData();
$currentUser = $_SESSION['user_email'];

$allApprovals = filterDeleted($data['discount_approvals'] ?? []);
usort($allApprovals, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

// adminは全件、それ以外は自分の申請のみ
if (isAdmin()) {
    $approvals = $allApprovals;
} else {
    $approvals = array_values(array_filter($allApprovals, fn($a) => ($a['applicant_email'] ?? '') === $currentUser));
}

// 承認待ち件数
$pendingCount   = count(array_filter($approvals, fn($a) => ($a['status'] ?? '') === 'pending'));
$approvedCount  = count(array_filter($approvals, fn($a) => ($a['status'] ?? '') === 'approved'));
$rejectedCount  = count(array_filter($approvals, fn($a) => ($a['status'] ?? '') === 'rejected'));

$statusLabels = ['pending' => '承認待ち', 'approved' => '承認', 'rejected' => '却下'];
$statusColors = [
    'pending'  => ['bg' => '#fff3e0', 'text' => '#e65100'],
    'approved' => ['bg' => '#e8f5e9', 'text' => '#2e7d32'],
    'rejected' => ['bg' => '#fce4ec', 'text' => '#c62828'],
];
?>

<style<?= nonceAttr() ?>>
.approval-card {
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    background: white;
}
.approval-card.pending { border-left: 4px solid #fb8c00; }
.approval-card.approved { border-left: 4px solid var(--success); }
.approval-card.rejected { border-left: 4px solid var(--danger); }
.approval-amounts {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
    margin: 0.75rem 0;
    padding: 0.75rem;
    background: var(--gray-50);
    border-radius: 8px;
}
.amount-item { text-align: center; }
.amount-label { font-size: 0.72rem; color: var(--gray-500); }
.amount-value { font-size: 1.05rem; font-weight: 600; }
.amount-arrow { color: var(--gray-400); font-size: 1.2rem; }
.status-pill {
    display: inline-block;
    padding: 0.2rem 0.7rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}
.review-section {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: var(--gray-50);
    border-radius: 6px;
    font-size: 0.875rem;
}
.summary-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.summary-item {
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.15s;
}
.summary-item:hover, .summary-item.active { border-color: var(--primary); }
.summary-num { font-size: 1.5rem; font-weight: 700; display: block; }
.modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10001; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: white; border-radius: 12px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
.modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 1.25rem 1.5rem; }
.modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 0.75rem; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--gray-500); line-height: 1; }
</style>

<div class="page-container">
    <div class="page-header">
        <h2>値引き承認</h2>
        <button class="btn btn-primary" id="btnNewApproval">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            値引き申請
        </button>
    </div>

    <!-- サマリーバー -->
    <div class="summary-bar">
        <div class="summary-item active" data-filter="all" style="background:#f8f9fa">
            <span class="summary-num"><?= count($approvals) ?></span>
            すべて
        </div>
        <div class="summary-item" data-filter="pending" style="background:#fff3e0">
            <span class="summary-num" style="color:#e65100"><?= $pendingCount ?></span>
            承認待ち
        </div>
        <div class="summary-item" data-filter="approved" style="background:#e8f5e9">
            <span class="summary-num" style="color:#2e7d32"><?= $approvedCount ?></span>
            承認済み
        </div>
        <div class="summary-item" data-filter="rejected" style="background:#fce4ec">
            <span class="summary-num" style="color:#c62828"><?= $rejectedCount ?></span>
            却下
        </div>
    </div>

    <!-- 申請一覧 -->
    <div id="approvalsContainer">
        <?php if (empty($approvals)): ?>
        <div class="text-center p-3rem text-gray-400">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mb-2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <p>申請がありません</p>
        </div>
        <?php else: ?>
        <?php foreach ($approvals as $approval): ?>
        <?php
            $st     = $approval['status'] ?? 'pending';
            $color  = $statusColors[$st] ?? $statusColors['pending'];
            $label  = $statusLabels[$st] ?? '承認待ち';
            $after  = ($approval['original_amount'] ?? 0) - ($approval['discount_amount'] ?? 0);
            $rate   = $approval['original_amount'] > 0
                ? round($approval['discount_amount'] / $approval['original_amount'] * 100, 1)
                : 0;
        ?>
        <div class="approval-card <?= htmlspecialchars($st) ?>"
            data-status="<?= htmlspecialchars($st) ?>"
            data-id="<?= htmlspecialchars($approval['id']) ?>">

            <div class="d-flex justify-between align-center">
                <div>
                    <strong class="text-16"><?= htmlspecialchars($approval['project_name']) ?></strong>
                    <?php if (isAdmin()): ?>
                    <span class="text-gray-500 text-14 ml-1">申請者: <?= htmlspecialchars($approval['applicant_name'] ?? $approval['applicant_email']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-1 align-center">
                    <span class="status-pill" style="background:<?= $color['bg'] ?>;color:<?= $color['text'] ?>">
                        <?= htmlspecialchars($label) ?>
                    </span>
                    <?php if (canDelete()): ?>
                    <button class="btn btn-sm btn-danger btn-delete-approval"
                        data-id="<?= htmlspecialchars($approval['id']) ?>"
                        data-name="<?= htmlspecialchars($approval['project_name']) ?>">
                        削除
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 金額 -->
            <div class="approval-amounts">
                <div class="amount-item">
                    <div class="amount-label">値引き前</div>
                    <div class="amount-value">¥<?= number_format($approval['original_amount'] ?? 0) ?></div>
                </div>
                <span class="amount-arrow">→</span>
                <div class="amount-item">
                    <div class="amount-label">値引き額</div>
                    <div class="amount-value text-danger">−¥<?= number_format($approval['discount_amount'] ?? 0) ?></div>
                </div>
                <span class="amount-arrow">=</span>
                <div class="amount-item">
                    <div class="amount-label">値引き後</div>
                    <div class="amount-value">¥<?= number_format($after) ?></div>
                </div>
                <div class="amount-item">
                    <div class="amount-label">値引き率</div>
                    <div class="amount-value text-danger"><?= $rate ?>%</div>
                </div>
            </div>

            <div class="text-gray-600 text-14">
                <strong>理由:</strong> <?= htmlspecialchars($approval['reason']) ?>
            </div>

            <?php if (!empty($approval['reviewed_at'])): ?>
            <div class="review-section">
                <strong><?= $st === 'approved' ? '承認済み' : '却下' ?></strong>
                &middot; <?= htmlspecialchars($approval['reviewed_at']) ?>
                &middot; 審査者: <?= htmlspecialchars($approval['reviewed_by'] ?? '') ?>
                <?php if (!empty($approval['review_comment'])): ?>
                <br><span class="text-gray-500">コメント: <?= htmlspecialchars($approval['review_comment']) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- admin の承認/却下ボタン -->
            <div class="d-flex justify-between align-center mt-1">
                <?php if (isAdmin() && $st === 'pending'): ?>
                <div class="d-flex gap-1">
                    <button class="btn btn-success btn-review"
                        data-action="approve"
                        data-id="<?= htmlspecialchars($approval['id']) ?>"
                        data-name="<?= htmlspecialchars($approval['project_name']) ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        承認
                    </button>
                    <button class="btn btn-danger btn-review"
                        data-action="reject"
                        data-id="<?= htmlspecialchars($approval['id']) ?>"
                        data-name="<?= htmlspecialchars($approval['project_name']) ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        却下
                    </button>
                </div>
                <?php else: ?>
                <div></div>
                <?php endif; ?>
                <div class="text-gray-400 text-12">申請日時: <?= htmlspecialchars($approval['created_at'] ?? '') ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 申請モーダル -->
<div class="modal" id="approvalModal">
    <div class="modal-content" style="max-width:520px">
        <div class="modal-header">
            <h3 class="modal-title">値引き申請</h3>
            <button class="modal-close" id="btnApprovalClose">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">案件名 <span class="text-danger">*</span></label>
                <input type="text" class="form-input" id="approvalProject" placeholder="例: 〇〇社 LEDビジョン設置案件">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">値引き前金額（円）<span class="text-danger">*</span></label>
                    <input type="number" class="form-input" id="approvalOriginal" placeholder="例: 1000000" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label">値引き額（円）<span class="text-danger">*</span></label>
                    <input type="number" class="form-input" id="approvalDiscount" placeholder="例: 50000" min="1">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">値引き後金額（自動計算）</label>
                <div class="form-input" id="approvalAfterAmount" style="background:var(--gray-50);color:var(--gray-700)">—</div>
            </div>
            <div class="form-group">
                <label class="form-label">値引き理由 <span class="text-danger">*</span></label>
                <textarea class="form-input" id="approvalReason" rows="3" placeholder="値引きが必要な理由を具体的に記載してください"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="btnApprovalCancel">キャンセル</button>
            <button class="btn btn-primary" id="btnApprovalSubmit">申請する</button>
        </div>
    </div>
</div>

<!-- 承認/却下コメントモーダル -->
<?php if (isAdmin()): ?>
<div class="modal" id="reviewModal">
    <div class="modal-content" style="max-width:440px">
        <div class="modal-header">
            <h3 class="modal-title" id="reviewModalTitle">承認</h3>
            <button class="modal-close" id="btnReviewClose">&times;</button>
        </div>
        <div class="modal-body">
            <p id="reviewModalDesc" class="text-gray-600 mb-1"></p>
            <div class="form-group">
                <label class="form-label">コメント（任意）</label>
                <textarea class="form-input" id="reviewComment" rows="3" placeholder="承認/却下の理由や補足を記載（省略可）"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="btnReviewCancel">キャンセル</button>
            <button class="btn btn-primary" id="btnReviewConfirm">確定</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script<?= nonceAttr() ?>>
(function() {
    var csrfToken = '<?= generateCsrfToken() ?>';

    // サマリーバーフィルター
    document.querySelectorAll('.summary-item').forEach(function(item) {
        item.addEventListener('click', function() {
            document.querySelectorAll('.summary-item').forEach(function(i) { i.classList.remove('active'); });
            this.classList.add('active');
            var filter = this.getAttribute('data-filter');
            document.querySelectorAll('.approval-card').forEach(function(card) {
                card.style.display = (filter === 'all' || card.getAttribute('data-status') === filter) ? '' : 'none';
            });
        });
    });

    // 申請モーダル
    var modal       = document.getElementById('approvalModal');
    var projectEl   = document.getElementById('approvalProject');
    var originalEl  = document.getElementById('approvalOriginal');
    var discountEl  = document.getElementById('approvalDiscount');
    var afterEl     = document.getElementById('approvalAfterAmount');
    var reasonEl    = document.getElementById('approvalReason');

    function updateAfter() {
        var orig = parseInt(originalEl.value) || 0;
        var disc = parseInt(discountEl.value) || 0;
        if (orig > 0) {
            afterEl.textContent = '¥' + (orig - disc).toLocaleString() + (disc > 0 ? ' （値引き率: ' + (disc/orig*100).toFixed(1) + '%）' : '');
        } else {
            afterEl.textContent = '—';
        }
    }
    originalEl.addEventListener('input', updateAfter);
    discountEl.addEventListener('input', updateAfter);

    document.getElementById('btnNewApproval').addEventListener('click', function() {
        projectEl.value  = '';
        originalEl.value = '';
        discountEl.value = '';
        afterEl.textContent = '—';
        reasonEl.value   = '';
        modal.classList.add('active');
        projectEl.focus();
    });

    function closeApprovalModal() { modal.classList.remove('active'); }
    document.getElementById('btnApprovalClose').addEventListener('click', closeApprovalModal);
    document.getElementById('btnApprovalCancel').addEventListener('click', closeApprovalModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeApprovalModal(); });

    document.getElementById('btnApprovalSubmit').addEventListener('click', function() {
        var project  = projectEl.value.trim();
        var original = parseInt(originalEl.value) || 0;
        var discount = parseInt(discountEl.value) || 0;
        var reason   = reasonEl.value.trim();

        if (!project) { showAlert('案件名を入力してください', 'error'); return; }
        if (original <= 0) { showAlert('値引き前金額を入力してください', 'error'); return; }
        if (discount <= 0) { showAlert('値引き額を入力してください', 'error'); return; }
        if (discount >= original) { showAlert('値引き額が値引き前金額以上です', 'error'); return; }
        if (!reason) { showAlert('値引き理由を入力してください', 'error'); return; }

        var fd = new FormData();
        fd.append('action', 'create');
        fd.append('project_name', project);
        fd.append('original_amount', original);
        fd.append('discount_amount', discount);
        fd.append('reason', reason);
        fd.append('csrf_token', csrfToken);

        fetch('/api/discount-approvals.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    showAlert('申請しました', 'success');
                    closeApprovalModal();
                    setTimeout(function() { location.reload(); }, 700);
                } else {
                    showAlert(res.message || 'エラーが発生しました', 'error');
                }
            });
    });

    // 削除
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-delete-approval');
        if (!btn) return;
        var id   = btn.getAttribute('data-id');
        var name = btn.getAttribute('data-name');
        if (!confirm('「' + name + '」の申請を削除しますか？')) return;
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fd.append('csrf_token', csrfToken);
        fetch('/api/discount-approvals.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    btn.closest('.approval-card').remove();
                    showAlert('削除しました', 'success');
                } else {
                    showAlert(res.message || 'エラーが発生しました', 'error');
                }
            });
    });

    <?php if (isAdmin()): ?>
    // 承認/却下
    var reviewModal   = document.getElementById('reviewModal');
    var reviewTitleEl = document.getElementById('reviewModalTitle');
    var reviewDescEl  = document.getElementById('reviewModalDesc');
    var reviewCommentEl = document.getElementById('reviewComment');
    var pendingAction = null;
    var pendingId     = null;

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-review');
        if (!btn) return;
        pendingAction = btn.getAttribute('data-action');
        pendingId     = btn.getAttribute('data-id');
        var name      = btn.getAttribute('data-name');
        reviewTitleEl.textContent = pendingAction === 'approve' ? '承認の確認' : '却下の確認';
        reviewDescEl.textContent  = '「' + name + '」の申請を' + (pendingAction === 'approve' ? '承認' : '却下') + 'しますか？';
        reviewCommentEl.value     = '';
        var confirmBtn = document.getElementById('btnReviewConfirm');
        confirmBtn.className = 'btn ' + (pendingAction === 'approve' ? 'btn-success' : 'btn-danger');
        confirmBtn.textContent = pendingAction === 'approve' ? '承認する' : '却下する';
        reviewModal.classList.add('active');
    });

    function closeReviewModal() { reviewModal.classList.remove('active'); pendingAction = null; pendingId = null; }
    document.getElementById('btnReviewClose').addEventListener('click', closeReviewModal);
    document.getElementById('btnReviewCancel').addEventListener('click', closeReviewModal);
    reviewModal.addEventListener('click', function(e) { if (e.target === reviewModal) closeReviewModal(); });

    document.getElementById('btnReviewConfirm').addEventListener('click', function() {
        if (!pendingAction || !pendingId) return;
        var fd = new FormData();
        fd.append('action', pendingAction);
        fd.append('id', pendingId);
        fd.append('comment', reviewCommentEl.value.trim());
        fd.append('csrf_token', csrfToken);
        fetch('/api/discount-approvals.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    showAlert(pendingAction === 'approve' ? '承認しました' : '却下しました', 'success');
                    closeReviewModal();
                    setTimeout(function() { location.reload(); }, 700);
                } else {
                    showAlert(res.message || 'エラーが発生しました', 'error');
                }
            });
    });
    <?php endif; ?>
})();
</script>

<?php require_once '../functions/footer.php'; ?>
