<?php
require_once '../api/auth.php';
require_once '../functions/mf-auto-mapper.php';

// 編集権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

// データ読み込み
$data = getData();

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// 手動マッピング保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mappings'])) {
    $mappings = $_POST['mapping'] ?? [];
    $savedCount = 0;

    if (!isset($data['mf_invoice_mappings'])) {
        $data['mf_invoice_mappings'] = [];
    }

    foreach ($mappings as $invoiceId => $projectId) {
        if (empty($projectId) || $projectId === 'none') {
            // マッピング解除
            if (isset($data['mf_invoice_mappings'][$invoiceId])) {
                unset($data['mf_invoice_mappings'][$invoiceId]);
            }
            continue;
        }

        // プロジェクトが存在するか確認
        $projectExists = false;
        $projectName = '';
        foreach ($data['projects'] ?? [] as $project) {
            if ($project['id'] === $projectId) {
                $projectExists = true;
                $projectName = $project['name'] ?? '';
                break;
            }
        }

        if (!$projectExists) {
            continue;
        }

        $data['mf_invoice_mappings'][$invoiceId] = [
            'project_id' => $projectId,
            'project_name' => $projectName,
            'method' => 'manual',
            'mapped_at' => date('Y-m-d H:i:s'),
            'mapped_by' => $_SESSION['user_email'] ?? '',
        ];
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

// 自動マッピング実行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_map'])) {
    $result = MFAutoMapper::applyAutoMapping($data);

    if ($result['success']) {
        $data = $result['data'];
        saveData($data);
        header('Location: mf-mapping.php?auto_mapped=' . $result['mapped_count']);
        exit;
    } else {
        header('Location: mf-mapping.php?auto_mapped=0&msg=' . urlencode($result['message']));
        exit;
    }
}

// 既存のマッピングを取得
$existingMappings = $data['mf_invoice_mappings'] ?? [];

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
.mapping-container {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.mapping-grid {
    display: grid;
    gap: 0.75rem;
}

.mapping-row {
    display: grid;
    grid-template-columns: 2fr 100px 2fr 80px;
    gap: 1rem;
    align-items: center;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 6px;
    border-left: 4px solid var(--primary, #555);
}

.mapping-row.mapped {
    border-left-color: #10b981;
}

.invoice-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.invoice-title {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
}

.invoice-meta {
    font-size: 0.85rem;
    color: #6b7280;
}

.invoice-price {
    font-weight: 700;
    color: #333;
    font-size: 1.1rem;
}

.project-select {
    width: 100%;
    padding: 0.5rem;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.9rem;
    background: white;
}

.project-select:focus {
    outline: none;
    border-color: var(--primary, #555);
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
    color: #1f2937;
}

.stat-label-text {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.filter-bar-mapping {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 0.5rem 1rem;
    border: 2px solid #d1d5db;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
}

.filter-btn.active {
    border-color: var(--primary, #555);
    background: var(--primary, #555);
    color: white;
}

.search-box {
    flex: 1;
    min-width: 200px;
    padding: 0.5rem 1rem;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.9rem;
}

.search-box:focus {
    outline: none;
    border-color: var(--primary, #555);
}

@media (max-width: 768px) {
    .mapping-row {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
}
</style>

<div class="page-container">

<?php if (isset($_GET['saved'])): ?>
    <div         class="alert alert-success p-2 rounded-lg mb-2 bg-success-light">
        <?= intval($_GET['saved']) ?>件のマッピングを保存しました
    </div>
<?php endif; ?>

<?php if (isset($_GET['auto_mapped'])): ?>
    <?php if (intval($_GET['auto_mapped']) > 0): ?>
        <div         class="alert alert-success p-2 rounded-lg mb-2 bg-success-light">
            タグから<?= intval($_GET['auto_mapped']) ?>件を自動マッピングしました
        </div>
    <?php else: ?>
        <div         class="alert p-2 rounded-lg mb-2 text-924 bg-warning-light">
            <?= htmlspecialchars($_GET['msg'] ?? '自動マッピング可能な請求書はありませんでした') ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'no_mappings'): ?>
    <div         class="alert p-2 rounded-lg mb-2 bg-danger-light">
        マッピングが選択されていません
    </div>
<?php endif; ?>

<div class="page-header">
    <h2>MF請求書マッピング</h2>
    <div class="page-header-actions">
        <form method="POST"  class="d-inline">
            <?= csrfTokenField() ?>
            <input type="hidden" name="auto_map" value="1">
            <button type="submit" class="btn btn-secondary">自動マッピング実行</button>
        </form>
        <a href="finance.php" class="btn btn-secondary">売上管理に戻る</a>
    </div>
</div>

<?php if (empty($data['mf_invoices'])): ?>
    <div class="card">
        <div       class="card-body text-center p-3rem">
            <p  class="text-gray-500">MF請求書データがありません。<a href="finance.php"     class="text-3b8">売上管理</a>から「MFから同期」ボタンでデータを取得してください。</p>
        </div>
    </div>
<?php else: ?>
    <?php
    $totalInvoices = count($data['mf_invoices']);
    $mappedCount = 0;
    foreach ($data['mf_invoices'] as $inv) {
        if (isset($existingMappings[$inv['id']])) {
            $mappedCount++;
        }
    }
    $unmappedCount = $totalInvoices - $mappedCount;
    ?>

    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-value"><?= $totalInvoices ?></div>
            <div class="stat-label-text">総請求書数</div>
        </div>
        <div class="stat-item">
            <div         class="stat-value text-10b981"><?= $mappedCount ?></div>
            <div class="stat-label-text">マッピング済み</div>
        </div>
        <div class="stat-item">
            <div         class="stat-value" class="text-ef4"><?= $unmappedCount ?></div>
            <div class="stat-label-text">未マッピング</div>
        </div>
    </div>

    <div class="filter-bar-mapping">
        <button class="filter-btn active" data-filter="all">すべて (<?= $totalInvoices ?>)</button>
        <button class="filter-btn" data-filter="unmapped">未マッピング (<?= $unmappedCount ?>)</button>
        <button class="filter-btn" data-filter="mapped">マッピング済み (<?= $mappedCount ?>)</button>
        <input type="text" class="search-box" id="searchBox" placeholder="請求書名、取引先名で検索...">
    </div>

    <form method="POST" action="">
        <?= csrfTokenField() ?>
        <input type="hidden" name="save_mappings" value="1">

        <div class="mapping-container">
            <div class="mapping-grid" id="mappingGrid">
                <?php
                // 請求書番号の降順でソート
                $sortedInvoices = $data['mf_invoices'];
                usort($sortedInvoices, function($a, $b) {
                    return strcmp($b['billing_number'] ?? '', $a['billing_number'] ?? '');
                });
                ?>
                <?php foreach ($sortedInvoices as $invoice): ?>
                    <?php
                    $isMapped = isset($existingMappings[$invoice['id']]);
                    $mappedProjectId = $isMapped ? ($existingMappings[$invoice['id']]['project_id'] ?? '') : '';
                    ?>
                    <div class="mapping-row <?= $isMapped ? 'mapped' : '' ?>" data-mapped="<?= $isMapped ? 'true' : 'false' ?>" data-search="<?= strtolower(htmlspecialchars($invoice['title'] . ' ' . $invoice['partner_name'] . ' ' . ($invoice['billing_number'] ?? ''))) ?>">
                        <div class="invoice-info">
                            <div class="invoice-title"><?= htmlspecialchars($invoice['partner_name'] ?? '') ?></div>
                            <div class="invoice-meta">
                                <?= htmlspecialchars($invoice['billing_number'] ?? '') ?> |
                                <?= htmlspecialchars($invoice['title'] ?? '') ?> |
                                売上日: <?= htmlspecialchars($invoice['sales_date'] ?? '') ?>
                            </div>
                        </div>

                        <div class="invoice-price">
                            &yen;<?= number_format($invoice['total_amount'] ?? 0) ?>
                        </div>

                        <div>
                            <select name="mapping[<?= htmlspecialchars($invoice['id']) ?>]" class="project-select">
                                <option value="none" <?= !$isMapped ? 'selected' : '' ?>>-- プロジェクトを選択 --</option>
                                <?php foreach ($data['projects'] ?? [] as $project): ?>
                                    <option value="<?= htmlspecialchars($project['id']) ?>" <?= ($mappedProjectId === $project['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($project['id']) ?>: <?= htmlspecialchars($project['name'] ?? '') ?>
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

        <div  class="mt-3 text-center">
            <button type="submit"         class="btn btn-primary text-base btn-pad-075-2">
                マッピングを保存
            </button>
        </div>
    </form>
<?php endif; ?>

</div><!-- /.page-container -->

<script<?= nonceAttr() ?>>
// フィルター機能
const filterBtns = document.querySelectorAll('.filter-btn');
const mappingRows = document.querySelectorAll('.mapping-row');
const searchBox = document.getElementById('searchBox');

let currentFilter = 'all';

filterBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        filterBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentFilter = this.dataset.filter;
        applyFilters();
    });
});

if (searchBox) {
    searchBox.addEventListener('input', function() {
        applyFilters();
    });
}

function applyFilters() {
    const searchTerm = (searchBox ? searchBox.value : '').toLowerCase();

    mappingRows.forEach(row => {
        const isMapped = row.dataset.mapped === 'true';
        const searchText = row.dataset.search || '';

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
            row.classList.add('mapped');
        } else {
            badge.textContent = '未';
            badge.className = 'status-badge status-unmapped';
            row.dataset.mapped = 'false';
            row.classList.remove('mapped');
        }
    });
});
</script>

<?php require_once '../functions/footer.php'; ?>
