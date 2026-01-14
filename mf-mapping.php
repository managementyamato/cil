<?php
require_once 'config.php';

// 編集権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

// データ読み込み
$data = getData();

// マッピング保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mappings'])) {
    $mappings = $_POST['mapping'] ?? array();
    $savedCount = 0;

    foreach ($mappings as $invoiceId => $projectId) {
        if (empty($projectId) || $projectId === 'none') {
            continue;
        }

        // 該当するプロジェクトを検索
        $projectExists = false;
        foreach ($data['projects'] as $project) {
            if ($project['id'] === $projectId) {
                $projectExists = true;
                break;
            }
        }

        if (!$projectExists) {
            continue;
        }

        // 該当するMF請求書を検索
        $invoice = null;
        foreach ($data['mf_invoices'] as $inv) {
            if ($inv['id'] === $invoiceId) {
                $invoice = $inv;
                break;
            }
        }

        if (!$invoice) {
            continue;
        }

        // 既存の財務データがあれば保持
        $existingFinance = isset($data['finance'][$projectId]) ? $data['finance'][$projectId] : array();

        // 財務データを更新
        $data['finance'][$projectId] = array(
            'revenue' => $invoice['total_price'],
            'cost' => $existingFinance['cost'] ?? 0,
            'labor_cost' => $existingFinance['labor_cost'] ?? 0,
            'material_cost' => $existingFinance['material_cost'] ?? 0,
            'other_cost' => $existingFinance['other_cost'] ?? 0,
            'gross_profit' => $invoice['total_price'] - ($existingFinance['cost'] ?? 0),
            'net_profit' => $invoice['total_price'] - (($existingFinance['cost'] ?? 0) + ($existingFinance['labor_cost'] ?? 0) + ($existingFinance['material_cost'] ?? 0) + ($existingFinance['other_cost'] ?? 0)),
            'notes' => ($existingFinance['notes'] ?? '') . "\n[MF手動マッピング] " . $invoice['billing_date'] . ' - ' . $invoice['partner_name'] . ' (請求書番号: ' . $invoice['billing_number'] . ')',
            'mf_billing_id' => $invoiceId,
            'mf_billing_number' => $invoice['billing_number'],
            'updated_at' => date('Y-m-d H:i:s'),
            'mf_mapped' => true
        );

        $savedCount++;
    }

    if ($savedCount > 0) {
        saveData($data);
        header('Location: mf-mapping.php?saved=' . $savedCount);
        exit;
    } else {
        header('Location: mf-mapping.php?error=no_mappings');
        exit;
    }
}

// 既存のマッピングを取得
$existingMappings = array();
if (isset($data['finance'])) {
    foreach ($data['finance'] as $projectId => $finance) {
        if (isset($finance['mf_billing_id'])) {
            $existingMappings[$finance['mf_billing_id']] = $projectId;
        }
    }
}

$pageTitle = 'MF請求書手動マッピング';
require_once 'header.php';
?>

<style>
.mapping-container {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.mapping-grid {
    display: grid;
    gap: 1rem;
}

.mapping-row {
    display: grid;
    grid-template-columns: 2fr 100px 2fr 80px;
    gap: 1rem;
    align-items: center;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 6px;
    border-left: 4px solid var(--primary-color);
}

.invoice-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.invoice-title {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.95rem;
}

.invoice-meta {
    font-size: 0.85rem;
    color: var(--gray-600);
}

.invoice-price {
    font-weight: 700;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.arrow {
    text-align: center;
    font-size: 1.5rem;
    color: var(--gray-400);
}

.project-select {
    width: 100%;
    padding: 0.5rem;
    border: 2px solid var(--gray-300);
    border-radius: 6px;
    font-size: 0.9rem;
    background: white;
}

.project-select:focus {
    outline: none;
    border-color: var(--primary-color);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
}

.status-mapped {
    background: #d1fae5;
    color: #065f46;
}

.status-unmapped {
    background: #fee2e2;
    color: #991b1b;
}

.stats-bar {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 6px;
    display: flex;
    justify-content: space-around;
    margin-bottom: 1.5rem;
}

.stat-item {
    text-align: center;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--gray-800);
}

.stat-label {
    font-size: 0.85rem;
    color: var(--gray-600);
    margin-top: 0.25rem;
}

.filter-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-btn {
    padding: 0.5rem 1rem;
    border: 2px solid var(--gray-300);
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
}

.filter-btn.active {
    border-color: var(--primary-color);
    background: var(--primary-color);
    color: white;
}

.search-box {
    flex: 1;
    padding: 0.5rem 1rem;
    border: 2px solid var(--gray-300);
    border-radius: 6px;
    font-size: 0.9rem;
}

.search-box:focus {
    outline: none;
    border-color: var(--primary-color);
}
</style>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success"><?= intval($_GET['saved']) ?>件のマッピングを保存しました</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <?php if ($_GET['error'] === 'no_mappings'): ?>
        <div class="alert alert-error">マッピングが選択されていません</div>
    <?php else: ?>
        <div class="alert alert-error">エラー: <?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<div class="page-header">
    <h1>MF請求書手動マッピング</h1>
    <a href="finance.php" class="btn btn-secondary">財務管理に戻る</a>
</div>

<?php if (!isset($data['mf_invoices']) || empty($data['mf_invoices'])): ?>
    <div class="alert alert-warning">
        MF請求書データがありません。<a href="finance.php" style="color: inherit; text-decoration: underline;">財務管理</a>から「MFから同期」ボタンでデータを取得してください。
    </div>
<?php else: ?>
    <?php
    $totalInvoices = count($data['mf_invoices']);
    $mappedCount = count($existingMappings);
    $unmappedCount = $totalInvoices - $mappedCount;
    ?>

    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-value"><?= $totalInvoices ?></div>
            <div class="stat-label">総請求書数</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #10b981;"><?= $mappedCount ?></div>
            <div class="stat-label">マッピング済み</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #ef4444;"><?= $unmappedCount ?></div>
            <div class="stat-label">未マッピング</div>
        </div>
    </div>

    <div class="filter-bar">
        <button class="filter-btn active" data-filter="all">すべて (<?= $totalInvoices ?>)</button>
        <button class="filter-btn" data-filter="unmapped">未マッピング (<?= $unmappedCount ?>)</button>
        <button class="filter-btn" data-filter="mapped">マッピング済み (<?= $mappedCount ?>)</button>
        <input type="text" class="search-box" id="searchBox" placeholder="請求書名、取引先名で検索...">
    </div>

    <form method="POST" action="">
        <input type="hidden" name="save_mappings" value="1">

        <div class="mapping-container">
            <div class="mapping-grid" id="mappingGrid">
                <?php foreach ($data['mf_invoices'] as $invoice): ?>
                    <?php
                    $isMapped = isset($existingMappings[$invoice['id']]);
                    $mappedProjectId = $isMapped ? $existingMappings[$invoice['id']] : '';
                    ?>
                    <div class="mapping-row" data-mapped="<?= $isMapped ? 'true' : 'false' ?>" data-search="<?= strtolower(htmlspecialchars($invoice['title'] . ' ' . $invoice['partner_name'])) ?>">
                        <div class="invoice-info">
                            <div class="invoice-title"><?= htmlspecialchars($invoice['title']) ?></div>
                            <div class="invoice-meta">
                                請求書番号: <?= htmlspecialchars($invoice['billing_number']) ?> |
                                取引先: <?= htmlspecialchars($invoice['partner_name']) ?> |
                                日付: <?= htmlspecialchars($invoice['billing_date']) ?>
                            </div>
                        </div>

                        <div class="invoice-price">
                            ¥<?= number_format($invoice['total_price']) ?>
                        </div>

                        <div>
                            <select name="mapping[<?= htmlspecialchars($invoice['id']) ?>]" class="project-select">
                                <option value="none" <?= !$isMapped ? 'selected' : '' ?>>-- プロジェクトを選択 --</option>
                                <?php foreach ($data['projects'] as $project): ?>
                                    <option value="<?= htmlspecialchars($project['id']) ?>" <?= ($mappedProjectId === $project['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($project['id']) ?>: <?= htmlspecialchars($project['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <span class="status-badge <?= $isMapped ? 'status-mapped' : 'status-unmapped' ?>">
                                <?= $isMapped ? '済' : '未' ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="margin-top: 1.5rem; text-align: center;">
            <button type="submit" class="btn btn-primary" style="font-size: 1rem; padding: 0.75rem 2rem;">
                マッピングを保存
            </button>
        </div>
    </form>
<?php endif; ?>

<script>
// フィルター機能
const filterBtns = document.querySelectorAll('.filter-btn');
const mappingRows = document.querySelectorAll('.mapping-row');
const searchBox = document.getElementById('searchBox');

let currentFilter = 'all';

filterBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        // アクティブクラスの切り替え
        filterBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        currentFilter = this.dataset.filter;
        applyFilters();
    });
});

searchBox.addEventListener('input', function() {
    applyFilters();
});

function applyFilters() {
    const searchTerm = searchBox.value.toLowerCase();

    mappingRows.forEach(row => {
        const isMapped = row.dataset.mapped === 'true';
        const searchText = row.dataset.search;

        let showByFilter = false;
        if (currentFilter === 'all') {
            showByFilter = true;
        } else if (currentFilter === 'mapped') {
            showByFilter = isMapped;
        } else if (currentFilter === 'unmapped') {
            showByFilter = !isMapped;
        }

        const showBySearch = searchText.includes(searchTerm);

        if (showByFilter && showBySearch) {
            row.style.display = 'grid';
        } else {
            row.style.display = 'none';
        }
    });
}

// プロジェクト選択時にステータスバッジを更新
document.querySelectorAll('.project-select').forEach(select => {
    select.addEventListener('change', function() {
        const row = this.closest('.mapping-row');
        const badge = row.querySelector('.status-badge');

        if (this.value && this.value !== 'none') {
            badge.textContent = '済';
            badge.className = 'status-badge status-mapped';
            row.dataset.mapped = 'true';
        } else {
            badge.textContent = '未';
            badge.className = 'status-badge status-unmapped';
            row.dataset.mapped = 'false';
        }
    });
});
</script>

<?php require_once 'footer.php'; ?>
