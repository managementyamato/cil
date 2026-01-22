<?php
require_once '../config/config.php';

// 閲覧権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

// データ読み込み
$data = getData();

// 月でグループ化
$monthlyData = array();

if (isset($data['mf_invoices']) && !empty($data['mf_invoices'])) {
    foreach ($data['mf_invoices'] as $invoice) {
        // 請求日から年月を取得
        $billingDate = $invoice['billing_date'] ?? '';
        if (empty($billingDate)) continue;

        // 日付をパース（形式: YYYY/MM/DD または YYYY-MM-DD）
        $dateObj = DateTime::createFromFormat('Y/m/d', $billingDate);
        if (!$dateObj) {
            $dateObj = DateTime::createFromFormat('Y-m-d', $billingDate);
        }
        if (!$dateObj) continue;

        $yearMonth = $dateObj->format('Y-m');

        if (!isset($monthlyData[$yearMonth])) {
            $monthlyData[$yearMonth] = array(
                'count' => 0,
                'total_amount' => 0,
                'subtotal' => 0,
                'tax' => 0,
                'invoices' => array()
            );
        }

        $monthlyData[$yearMonth]['count']++;
        $monthlyData[$yearMonth]['total_amount'] += floatval($invoice['total_amount'] ?? 0);
        $monthlyData[$yearMonth]['subtotal'] += floatval($invoice['subtotal'] ?? 0);
        $monthlyData[$yearMonth]['tax'] += floatval($invoice['tax'] ?? 0);
        $monthlyData[$yearMonth]['invoices'][] = $invoice;
    }
}

// 月を降順でソート
krsort($monthlyData);

require_once '../functions/header.php';
?>

<style>
.month-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
    cursor: pointer;
    transition: box-shadow 0.2s;
}

.month-card:hover {
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.month-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-200);
}

.month-title {
    font-size: 1.25rem;
    font-weight: bold;
    color: var(--gray-700);
}

.month-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.stat-box {
    text-align: center;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin-bottom: 0.25rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--gray-900);
}

.invoice-details {
    display: none;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
}

.invoice-details.show {
    display: block;
}

.invoice-item {
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 4px;
    margin-bottom: 0.5rem;
}

.invoice-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.invoice-item-title {
    font-weight: 600;
    color: var(--gray-700);
}

.invoice-item-amount {
    font-weight: bold;
    color: #2563eb;
}

.invoice-item-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--gray-600);
}

.invoice-tags {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}

.tag {
    background: #dbeafe;
    color: #1e40af;
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.tag.project {
    background: #dcfce7;
    color: #166534;
}

.tag.assignee {
    background: #fef3c7;
    color: #92400e;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
}

.summary-label {
    color: var(--gray-600);
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.summary-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--gray-900);
}
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">月別請求書集計</h2>
    <a href="finance.php" class="btn btn-secondary">損益に戻る</a>
</div>

<?php if (empty($monthlyData)): ?>
    <div class="card">
        <div class="card-body">
            <p style="color: var(--gray-600); text-align: center; padding: 2rem;">
                請求書データがありません。<br>
                「MFから同期」を実行してください。
            </p>
        </div>
    </div>
<?php else: ?>
    <?php
    // 全体集計
    $totalInvoices = 0;
    $totalAmount = 0;
    $totalSubtotal = 0;
    $totalTax = 0;

    foreach ($monthlyData as $month => $monthInfo) {
        $totalInvoices += $monthInfo['count'];
        $totalAmount += $monthInfo['total_amount'];
        $totalSubtotal += $monthInfo['subtotal'];
        $totalTax += $monthInfo['tax'];
    }
    ?>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">総請求書数</div>
            <div class="summary-value"><?= number_format($totalInvoices) ?>件</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">総請求額（税込）</div>
            <div class="summary-value">¥<?= number_format($totalAmount) ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-label">小計</div>
            <div class="summary-value">¥<?= number_format($totalSubtotal) ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-label">消費税</div>
            <div class="summary-value">¥<?= number_format($totalTax) ?></div>
        </div>
    </div>

    <?php foreach ($monthlyData as $yearMonth => $monthInfo): ?>
        <div class="month-card" onclick="toggleMonth('<?= $yearMonth ?>')">
            <div class="month-header">
                <div class="month-title"><?= date('Y年m月', strtotime($yearMonth . '-01')) ?></div>
                <div style="color: var(--gray-600);">▼ クリックで詳細表示</div>
            </div>

            <div class="month-stats">
                <div class="stat-box">
                    <div class="stat-label">請求書数</div>
                    <div class="stat-value"><?= number_format($monthInfo['count']) ?>件</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">請求額（税込）</div>
                    <div class="stat-value">¥<?= number_format($monthInfo['total_amount']) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">小計</div>
                    <div class="stat-value">¥<?= number_format($monthInfo['subtotal']) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">消費税</div>
                    <div class="stat-value">¥<?= number_format($monthInfo['tax']) ?></div>
                </div>
            </div>

            <div class="invoice-details" id="details-<?= $yearMonth ?>">
                <?php foreach ($monthInfo['invoices'] as $invoice): ?>
                    <div class="invoice-item">
                        <div class="invoice-item-header">
                            <div class="invoice-item-title">
                                <?= htmlspecialchars($invoice['partner_name'] ?? '-') ?>
                                <?php if (!empty($invoice['title'])): ?>
                                    <span style="color: var(--gray-600); font-weight: normal; font-size: 0.875rem;">
                                        - <?= htmlspecialchars($invoice['title']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="invoice-item-amount">
                                ¥<?= number_format($invoice['total_amount'] ?? 0) ?>
                            </div>
                        </div>

                        <div class="invoice-item-info">
                            <div>
                                <strong>請求番号:</strong> <?= htmlspecialchars($invoice['billing_number'] ?? '-') ?>
                            </div>
                            <div>
                                <strong>請求日:</strong> <?= htmlspecialchars($invoice['billing_date'] ?? '-') ?>
                            </div>
                            <div>
                                <strong>支払期限:</strong> <?= htmlspecialchars($invoice['due_date'] ?? '-') ?>
                            </div>
                            <div>
                                <strong>ステータス:</strong> <?= htmlspecialchars($invoice['payment_status'] ?? '-') ?>
                            </div>
                        </div>

                        <div class="invoice-item-info" style="margin-top: 0.5rem;">
                            <div>
                                <strong>小計:</strong> ¥<?= number_format($invoice['subtotal'] ?? 0) ?>
                            </div>
                            <div>
                                <strong>消費税:</strong> ¥<?= number_format($invoice['tax'] ?? 0) ?>
                            </div>
                        </div>

                        <?php if (!empty($invoice['project_id']) || !empty($invoice['assignee']) || !empty($invoice['tag_names'])): ?>
                            <div class="invoice-tags">
                                <?php if (!empty($invoice['project_id'])): ?>
                                    <span class="tag project"><?= htmlspecialchars($invoice['project_id']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($invoice['assignee'])): ?>
                                    <span class="tag assignee">担当: <?= htmlspecialchars($invoice['assignee']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($invoice['tag_names'])): ?>
                                    <?php foreach ($invoice['tag_names'] as $tag): ?>
                                        <?php if ($tag !== $invoice['project_id'] && $tag !== $invoice['assignee']): ?>
                                            <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
function toggleMonth(yearMonth) {
    const details = document.getElementById('details-' + yearMonth);
    if (details) {
        details.classList.toggle('show');
    }
}
</script>

<?php require_once '../functions/footer.php'; ?>
