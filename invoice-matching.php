<?php
require_once 'config.php';
require_once 'mf-api.php';

if (!canEdit()) {
    header('Location: index.php');
    exit;
}

$data = getData();
$message = '';
$error = '';

// 手動マッピング保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['map_invoice'])) {
    $billingId = $_POST['billing_id'] ?? '';
    $projectId = $_POST['project_id'] ?? '';

    if ($billingId && $projectId && isset($data['mf_invoices'][$billingId])) {
        $inv = $data['mf_invoices'][$billingId];
        $totalPrice = $inv['total_price'];
        $billingDate = $inv['billing_date'];
        $title = $inv['title'];

        $existingFinance = $data['finance'][$projectId] ?? [];
        $existingRevenue = $existingFinance['revenue'] ?? 0;

        $data['finance'][$projectId] = [
            'revenue' => $existingRevenue + $totalPrice,
            'cost' => $existingFinance['cost'] ?? 0,
            'labor_cost' => $existingFinance['labor_cost'] ?? 0,
            'material_cost' => $existingFinance['material_cost'] ?? 0,
            'other_cost' => $existingFinance['other_cost'] ?? 0,
            'gross_profit' => ($existingRevenue + $totalPrice) - ($existingFinance['cost'] ?? 0),
            'net_profit' => ($existingRevenue + $totalPrice) - (($existingFinance['cost'] ?? 0) + ($existingFinance['labor_cost'] ?? 0) + ($existingFinance['material_cost'] ?? 0) + ($existingFinance['other_cost'] ?? 0)),
            'notes' => ($existingFinance['notes'] ?? '') . "\n[手動MF同期] {$billingDate} - {$title}",
            'updated_at' => date('Y-m-d H:i:s'),
            'mf_synced' => true,
            'mf_billing_id' => $billingId
        ];

        $data['mf_invoices'][$billingId]['status'] = 'matched';
        $data['mf_invoices'][$billingId]['matched_project_id'] = $projectId;

        saveData($data);
        $message = "請求書 #{$inv['billing_number']} をプロジェクトに紐付けました（+¥" . number_format($totalPrice) . "）";
    }
}

// 未照合請求書を取得
$unmatchedInvoices = [];
$matchedInvoices = [];
foreach ($data['mf_invoices'] as $inv) {
    if (($inv['status'] ?? '') === 'matched') {
        $matchedInvoices[] = $inv;
    } else {
        $unmatchedInvoices[] = $inv;
    }
}
usort($unmatchedInvoices, fn($a, $b) => $b['total_price'] - $a['total_price']);

$unmatchedTotal = array_sum(array_column($unmatchedInvoices, 'total_price'));
$matchedTotal = array_sum(array_column($matchedInvoices, 'total_price'));

require_once 'header.php';
?>

<style>
.matching-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
.stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.stat-card .value { font-size: 1.8rem; font-weight: 700; margin: 0.5rem 0; }
.stat-card .label { font-size: 0.875rem; color: #6b7280; }
.stat-card.danger .value { color: #dc2626; }
.stat-card.success .value { color: #059669; }
.stat-card.primary .value { color: #2563eb; }
.invoice-row { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem; }
.invoice-row:hover { border-color: #2563eb; }
.invoice-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem; }
.invoice-title { font-weight: 600; color: #111827; flex: 1; }
.invoice-amount { font-size: 1.25rem; font-weight: 700; color: #2563eb; white-space: nowrap; margin-left: 1rem; }
.invoice-meta { font-size: 0.75rem; color: #6b7280; margin-bottom: 0.75rem; }
.map-form { display: flex; gap: 0.5rem; align-items: center; }
.map-form select { flex: 1; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; }
.map-form button { padding: 0.375rem 0.75rem; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.875rem; white-space: nowrap; }
.map-form button:hover { background: #1d4ed8; }
.alert-success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
</style>

<div style="max-width: 1100px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="margin: 0;">請求書マッチング管理</h2>
        <a href="finance.php" class="btn btn-secondary" style="font-size: 0.875rem;">← 財務管理に戻る</a>
    </div>

    <?php if ($message): ?>
        <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="matching-stats">
        <div class="stat-card danger">
            <div class="label">未照合請求書</div>
            <div class="value"><?= count($unmatchedInvoices) ?>件</div>
            <div class="label">¥<?= number_format($unmatchedTotal) ?></div>
        </div>
        <div class="stat-card success">
            <div class="label">照合済み</div>
            <div class="value"><?= count($matchedInvoices) ?>件</div>
            <div class="label">¥<?= number_format($matchedTotal) ?></div>
        </div>
        <div class="stat-card primary">
            <div class="label">照合率</div>
            <div class="value"><?= count($data['mf_invoices']) > 0 ? round(count($matchedInvoices) / count($data['mf_invoices']) * 100) : 0 ?>%</div>
            <div class="label">全<?= count($data['mf_invoices']) ?>件中</div>
        </div>
    </div>

    <h3 style="margin-bottom: 1rem;">未照合の請求書（金額順）</h3>
    <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 1rem;">
        各請求書に対応するプロジェクトを選択してください。照合するとプロジェクトの財務データが自動更新されます。
    </p>

    <?php foreach ($unmatchedInvoices as $inv): ?>
    <div class="invoice-row">
        <div class="invoice-header">
            <div class="invoice-title">#<?= htmlspecialchars($inv['billing_number']) ?> | <?= htmlspecialchars($inv['title']) ?></div>
            <div class="invoice-amount">¥<?= number_format($inv['total_price']) ?></div>
        </div>
        <div class="invoice-meta">
            取引先: <?= htmlspecialchars($inv['partner_name']) ?> &nbsp;|&nbsp;
            請求日: <?= htmlspecialchars($inv['billing_date']) ?>
        </div>
        <form class="map-form" method="POST">
            <input type="hidden" name="map_invoice" value="1">
            <input type="hidden" name="billing_id" value="<?= htmlspecialchars($inv['id']) ?>">
            <select name="project_id" required>
                <option value="">-- プロジェクトを選択 --</option>
                <?php foreach ($data['projects'] as $p): ?>
                <option value="<?= htmlspecialchars($p['id']) ?>"><?= htmlspecialchars(mb_substr($p['name'], 0, 60)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">照合する</button>
        </form>
    </div>
    <?php endforeach; ?>

    <?php if (empty($unmatchedInvoices)): ?>
    <div style="text-align: center; padding: 3rem; color: #6b7280;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">✓</div>
        <div>すべての請求書が照合済みです！</div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
