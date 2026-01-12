<?php
require_once 'config.php';
require_once 'mf-api.php';

// 編集権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

// データ読み込み
$data = getData();

// MFから同期
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_from_mf'])) {
    if (!MFApiClient::isConfigured()) {
        header('Location: finance.php?error=mf_not_configured');
        exit;
    }

    try {
        $client = new MFApiClient();

        // 過去3ヶ月分のデータを取得
        $from = date('Y-m-d', strtotime('-3 months'));
        $to = date('Y-m-d');

        $invoices = $client->getInvoices($from, $to);
        $financeData = $client->extractFinanceData($invoices);

        // プロジェクトと照合して財務データを更新
        $syncedCount = 0;
        foreach ($financeData as $mfData) {
            // プロジェクト名で照合
            $projectName = $mfData['project_name'];
            foreach ($data['projects'] as $project) {
                if (stripos($project['name'], $projectName) !== false || stripos($projectName, $project['name']) !== false) {
                    // 既存の財務データがあれば保持
                    $existingFinance = $data['finance'][$project['id']] ?? array();

                    $data['finance'][$project['id']] = array(
                        'revenue' => $mfData['revenue'],
                        'cost' => $existingFinance['cost'] ?? 0,
                        'labor_cost' => $existingFinance['labor_cost'] ?? 0,
                        'material_cost' => $existingFinance['material_cost'] ?? 0,
                        'other_cost' => $existingFinance['other_cost'] ?? 0,
                        'gross_profit' => $mfData['revenue'] - ($existingFinance['cost'] ?? 0),
                        'net_profit' => $mfData['revenue'] - (($existingFinance['cost'] ?? 0) + ($existingFinance['labor_cost'] ?? 0) + ($existingFinance['material_cost'] ?? 0) + ($existingFinance['other_cost'] ?? 0)),
                        'notes' => ($existingFinance['notes'] ?? '') . "\n[MF同期] " . $mfData['date'] . ' - ' . $mfData['partner'],
                        'updated_at' => date('Y-m-d H:i:s'),
                        'mf_synced' => true
                    );
                    $syncedCount++;
                    break;
                }
            }
        }

        saveData($data);
        header('Location: finance.php?synced=' . $syncedCount);
        exit;
    } catch (Exception $e) {
        header('Location: finance.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// 財務データ追加・更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_finance'])) {
    $projectId = $_POST['project_id'] ?? '';
    $revenue = floatval($_POST['revenue'] ?? 0);
    $cost = floatval($_POST['cost'] ?? 0);
    $laborCost = floatval($_POST['labor_cost'] ?? 0);
    $materialCost = floatval($_POST['material_cost'] ?? 0);
    $otherCost = floatval($_POST['other_cost'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!isset($data['finance'])) {
        $data['finance'] = array();
    }

    $data['finance'][$projectId] = array(
        'revenue' => $revenue,
        'cost' => $cost,
        'labor_cost' => $laborCost,
        'material_cost' => $materialCost,
        'other_cost' => $otherCost,
        'gross_profit' => $revenue - $cost,
        'net_profit' => $revenue - ($cost + $laborCost + $materialCost + $otherCost),
        'notes' => $notes,
        'updated_at' => date('Y-m-d H:i:s')
    );

    saveData($data);
    header('Location: finance.php?saved=1');
    exit;
}

// 財務データ削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_finance'])) {
    $projectId = $_POST['project_id'] ?? '';
    if (isset($data['finance'][$projectId])) {
        unset($data['finance'][$projectId]);
        saveData($data);
        header('Location: finance.php?deleted=1');
        exit;
    }
}

require_once 'header.php';
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-label {
    color: var(--gray-600);
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: bold;
}

.stat-value.positive {
    color: #10b981;
}

.stat-value.negative {
    color: #ef4444;
}

.profit-cell {
    font-weight: 600;
}

.profit-positive {
    color: #10b981;
}

.profit-negative {
    color: #ef4444;
}

.finance-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.finance-form-grid .form-group {
    margin-bottom: 0;
}

.modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

.mf-sync-badge {
    display: inline-block;
    background: #3b82f6;
    color: white;
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    margin-left: 0.5rem;
    font-weight: 500;
}

.sync-date {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 0.2rem;
}
</style>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">財務データを保存しました</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">財務データを削除しました</div>
<?php endif; ?>

<?php if (isset($_GET['synced'])): ?>
    <div class="alert alert-success">MFから<?= intval($_GET['synced']) ?>件の財務データを同期しました</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <?php if ($_GET['error'] === 'mf_not_configured'): ?>
        <div class="alert alert-error">MF APIの設定が完了していません。<a href="mf-settings.php" style="color: inherit; text-decoration: underline;">設定ページ</a>から設定してください。</div>
    <?php else: ?>
        <div class="alert alert-error">エラー: <?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php
// 集計データ計算
$totalRevenue = 0;
$totalCost = 0;
$totalGrossProfit = 0;
$totalNetProfit = 0;
$projectCount = 0;
$mfSyncedCount = 0;

if (isset($data['finance']) && !empty($data['finance'])) {
    foreach ($data['finance'] as $finance) {
        $totalRevenue += $finance['revenue'];
        $totalCost += $finance['cost'] + $finance['labor_cost'] + $finance['material_cost'] + $finance['other_cost'];
        $totalGrossProfit += $finance['gross_profit'];
        $totalNetProfit += $finance['net_profit'];
        $projectCount++;
        if (isset($finance['mf_synced']) && $finance['mf_synced']) {
            $mfSyncedCount++;
        }
    }
}
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">登録案件数</div>
        <div class="stat-value"><?= number_format($projectCount) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">MF同期済み</div>
        <div class="stat-value" style="color: #3b82f6;"><?= number_format($mfSyncedCount) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">総売上</div>
        <div class="stat-value">¥<?= number_format($totalRevenue) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">総原価</div>
        <div class="stat-value">¥<?= number_format($totalCost) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">粗利益</div>
        <div class="stat-value <?= $totalGrossProfit >= 0 ? 'positive' : 'negative' ?>">¥<?= number_format($totalGrossProfit) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">純利益</div>
        <div class="stat-value <?= $totalNetProfit >= 0 ? 'positive' : 'negative' ?>">¥<?= number_format($totalNetProfit) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">利益率</div>
        <div class="stat-value <?= $totalNetProfit >= 0 ? 'positive' : 'negative' ?>">
            <?= $totalRevenue > 0 ? number_format(($totalNetProfit / $totalRevenue) * 100, 1) : 0 ?>%
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">財務管理</h2>
        <div style="display: flex; gap: 0.5rem;">
            <?php if (MFApiClient::isConfigured()): ?>
                <form method="POST" action="" style="margin: 0;">
                    <button type="submit" name="sync_from_mf" class="btn btn-primary" style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                        MFから同期
                    </button>
                </form>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
                <a href="mf-settings.php" class="btn btn-secondary" style="font-size: 0.875rem; padding: 0.5rem 1rem; text-decoration: none;">
                    <?= MFApiClient::isConfigured() ? 'MF設定' : 'MF連携設定' ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($data['projects'])): ?>
            <p style="color: var(--gray-600); text-align: center; padding: 2rem;">
                プロジェクトが登録されていません。先にプロジェクト管理から案件を登録してください。
            </p>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>PJ番号</th>
                            <th>案件名</th>
                            <th>顧客名</th>
                            <th>売上</th>
                            <th>原価合計</th>
                            <th>粗利益</th>
                            <th>純利益</th>
                            <th>利益率</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['projects'] as $project): ?>
                            <?php
                            $finance = isset($data['finance'][$project['id']]) ? $data['finance'][$project['id']] : null;
                            $revenue = $finance ? $finance['revenue'] : 0;
                            $totalProjectCost = $finance ? ($finance['cost'] + $finance['labor_cost'] + $finance['material_cost'] + $finance['other_cost']) : 0;
                            $grossProfit = $finance ? $finance['gross_profit'] : 0;
                            $netProfit = $finance ? $finance['net_profit'] : 0;
                            $profitRate = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($project['id']) ?></td>
                                <td>
                                    <?= htmlspecialchars($project['name']) ?>
                                    <?php if ($finance && isset($finance['mf_synced']) && $finance['mf_synced']): ?>
                                        <span class="mf-sync-badge">MF同期</span>
                                        <?php if (isset($finance['updated_at'])): ?>
                                            <div class="sync-date">更新: <?= htmlspecialchars($finance['updated_at']) ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($project['customer_name'] ?? '-') ?></td>
                                <td>¥<?= number_format($revenue) ?></td>
                                <td>¥<?= number_format($totalProjectCost) ?></td>
                                <td class="profit-cell <?= $grossProfit >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                    ¥<?= number_format($grossProfit) ?>
                                </td>
                                <td class="profit-cell <?= $netProfit >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                    ¥<?= number_format($netProfit) ?>
                                </td>
                                <td class="<?= $profitRate >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                    <?= number_format($profitRate, 1) ?>%
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-icon" onclick='showFinanceModal(<?= json_encode($project) ?>, <?= json_encode($finance) ?>)' title="財務データ編集">編集</button>
                                        <?php if ($finance): ?>
                                            <button type="button" class="btn-icon" onclick='confirmDeleteFinance(<?= json_encode($project['id']) ?>, <?= json_encode($project['name']) ?>)' title="削除">削除</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 財務データ編集モーダル -->
<div id="financeModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 id="financeModalTitle">財務データ編集</h3>
            <span class="close" onclick="closeModal('financeModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="save_finance" value="1">
            <input type="hidden" id="finance_project_id" name="project_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>案件名</label>
                    <input type="text" class="form-input" id="finance_project_name" readonly style="background: #f3f4f6;">
                </div>

                <h4 style="margin: 1.5rem 0 1rem 0; color: var(--gray-700); font-size: 0.95rem; border-bottom: 2px solid var(--gray-200); padding-bottom: 0.5rem;">売上・原価</h4>

                <div class="finance-form-grid">
                    <div class="form-group">
                        <label for="finance_revenue">売上金額 *</label>
                        <input type="number" class="form-input" id="finance_revenue" name="revenue" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="finance_cost">原価（直接費用） *</label>
                        <input type="number" class="form-input" id="finance_cost" name="cost" step="0.01" required>
                    </div>
                </div>

                <h4 style="margin: 1.5rem 0 1rem 0; color: var(--gray-700); font-size: 0.95rem; border-bottom: 2px solid var(--gray-200); padding-bottom: 0.5rem;">詳細費用</h4>

                <div class="finance-form-grid">
                    <div class="form-group">
                        <label for="finance_labor_cost">人件費</label>
                        <input type="number" class="form-input" id="finance_labor_cost" name="labor_cost" step="0.01" value="0">
                    </div>

                    <div class="form-group">
                        <label for="finance_material_cost">材料費</label>
                        <input type="number" class="form-input" id="finance_material_cost" name="material_cost" step="0.01" value="0">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="finance_other_cost">その他費用</label>
                        <input type="number" class="form-input" id="finance_other_cost" name="other_cost" step="0.01" value="0">
                    </div>
                </div>

                <div class="form-group">
                    <label for="finance_notes">備考</label>
                    <textarea class="form-input" id="finance_notes" name="notes" rows="3"></textarea>
                </div>

                <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: var(--gray-600);">粗利益:</span>
                        <span id="preview_gross_profit" style="font-weight: 600;">¥0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 0.5rem; border-top: 1px solid var(--gray-300);">
                        <span style="color: var(--gray-700); font-weight: 600;">純利益:</span>
                        <span id="preview_net_profit" style="font-weight: 700; font-size: 1.1rem;">¥0</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('financeModal')">キャンセル</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 削除フォーム -->
<form id="deleteFinanceForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="delete_finance" value="1">
    <input type="hidden" id="delete_finance_project_id" name="project_id">
</form>

<script>
function showFinanceModal(project, finance) {
    document.getElementById('finance_project_id').value = project.id;
    document.getElementById('finance_project_name').value = project.name;
    document.getElementById('financeModalTitle').textContent = '財務データ編集: ' + project.id;

    if (finance) {
        document.getElementById('finance_revenue').value = finance.revenue;
        document.getElementById('finance_cost').value = finance.cost;
        document.getElementById('finance_labor_cost').value = finance.labor_cost;
        document.getElementById('finance_material_cost').value = finance.material_cost;
        document.getElementById('finance_other_cost').value = finance.other_cost;
        document.getElementById('finance_notes').value = finance.notes || '';
    } else {
        document.getElementById('finance_revenue').value = 0;
        document.getElementById('finance_cost').value = 0;
        document.getElementById('finance_labor_cost').value = 0;
        document.getElementById('finance_material_cost').value = 0;
        document.getElementById('finance_other_cost').value = 0;
        document.getElementById('finance_notes').value = '';
    }

    updateProfitPreview();
    document.getElementById('financeModal').style.display = 'block';
}

function updateProfitPreview() {
    const revenue = parseFloat(document.getElementById('finance_revenue').value) || 0;
    const cost = parseFloat(document.getElementById('finance_cost').value) || 0;
    const laborCost = parseFloat(document.getElementById('finance_labor_cost').value) || 0;
    const materialCost = parseFloat(document.getElementById('finance_material_cost').value) || 0;
    const otherCost = parseFloat(document.getElementById('finance_other_cost').value) || 0;

    const grossProfit = revenue - cost;
    const netProfit = revenue - (cost + laborCost + materialCost + otherCost);

    document.getElementById('preview_gross_profit').textContent = '¥' + grossProfit.toLocaleString('ja-JP');
    document.getElementById('preview_net_profit').textContent = '¥' + netProfit.toLocaleString('ja-JP');

    document.getElementById('preview_gross_profit').style.color = grossProfit >= 0 ? '#10b981' : '#ef4444';
    document.getElementById('preview_net_profit').style.color = netProfit >= 0 ? '#10b981' : '#ef4444';
}

// 入力フィールドの変更を監視
document.addEventListener('DOMContentLoaded', function() {
    const inputs = ['finance_revenue', 'finance_cost', 'finance_labor_cost', 'finance_material_cost', 'finance_other_cost'];
    inputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', updateProfitPreview);
        }
    });
});

function confirmDeleteFinance(projectId, projectName) {
    if (confirm('「' + projectName + '」の財務データを削除してもよろしいですか？')) {
        document.getElementById('delete_finance_project_id').value = projectId;
        document.getElementById('deleteFinanceForm').submit();
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// モーダル外クリックで閉じる
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php require_once 'footer.php'; ?>
