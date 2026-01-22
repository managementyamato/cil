<?php
require_once '../config/config.php';
require_once '../functions/profit-loss-functions.php';

// 管理者権限チェック
if (!isAdmin()) {
    header('Location: /pages/index.php');
    exit;
}

// 会計年度の一覧を取得
$availableYears = getAvailableFiscalYears();

// 選択された会計年度（デフォルトは最新年度）
$selectedYear = $_GET['year'] ?? ($availableYears[0] ?? date('Y'));

// 損益計算書データを読み込み
$profitLossData = loadProfitLossData($selectedYear);

// サマリー計算
$summary = null;
if ($profitLossData) {
    $summary = calculateSummary($profitLossData['data']);
}

require_once '../functions/header.php';
?>

<style>
.profit-loss-container {
    max-width: 1600px;
}

/* サマリーカード */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-left: 4px solid var(--primary);
}

.summary-card.positive {
    border-left-color: var(--success);
}

.summary-card.negative {
    border-left-color: var(--danger);
}

.summary-card-label {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin-bottom: 0.5rem;
}

.summary-card-value {
    font-size: 2rem;
    font-weight: 700;
    font-family: 'Consolas', 'Monaco', monospace;
}

.summary-card-value.positive {
    color: var(--success);
}

.summary-card-value.negative {
    color: var(--danger);
}

.summary-card-sub {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 0.5rem;
}

/* 年度選択 */
.year-selector {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.year-selector label {
    font-weight: 600;
    margin: 0;
}

.year-selector select {
    padding: 0.5rem 1rem;
    border: 1px solid var(--gray-300);
    border-radius: 6px;
    font-size: 1rem;
}

/* グラフエリア */
.charts-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.chart-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.chart-card h3 {
    margin: 0 0 1rem 0;
    font-size: 1.125rem;
}

.chart-placeholder {
    height: 300px;
    background: #f9fafb;
    border: 2px dashed var(--gray-300);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-500);
}

/* テーブル */
.profit-loss-table-wrapper {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow-x: auto;
}

.view-toggle {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.view-toggle button {
    padding: 0.5rem 1rem;
    border: 1px solid var(--gray-300);
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.view-toggle button.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.profit-loss-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    min-width: 1200px;
}

.profit-loss-table thead {
    background: var(--primary);
    color: white;
    position: sticky;
    top: 0;
    z-index: 10;
}

.profit-loss-table th,
.profit-loss-table td {
    padding: 0.75rem 0.5rem;
    border: 1px solid var(--gray-300);
}

.profit-loss-table th {
    font-weight: 600;
    text-align: center;
}

.profit-loss-table td {
    text-align: right;
}

.profit-loss-table td:first-child {
    text-align: left;
    position: sticky;
    background: white;
    left: 0;
    min-width: 250px;
    font-weight: 600;
    z-index: 5;
}

.profit-loss-table tbody tr:nth-child(even) td:first-child {
    background: #f9fafb;
}

.profit-loss-table tbody tr:hover td:first-child {
    background: #e0f2fe;
}

/* セクションヘッダー */
.section-row {
    background: #dbeafe !important;
    font-weight: 700;
    color: #1e40af;
}

.section-row td {
    font-size: 1rem;
    padding: 1rem 0.75rem;
}

.section-row td:first-child {
    background: #dbeafe !important;
}

.section-row.expandable {
    cursor: pointer;
    user-select: none;
}

.section-row.expandable:hover {
    background: #bfdbfe !important;
}

.section-row.expandable:hover td:first-child {
    background: #bfdbfe !important;
}

.expand-icon {
    display: inline-block;
    margin-right: 0.5rem;
    transition: transform 0.2s;
}

.expand-icon.expanded {
    transform: rotate(90deg);
}

/* 詳細行 */
.detail-row {
    display: none;
}

.detail-row.show {
    display: table-row;
}

.detail-row:nth-child(even) {
    background: #f9fafb;
}

.detail-row:hover {
    background: #e0f2fe;
}

.detail-row td:first-child {
    padding-left: 2rem;
    font-weight: 400;
    font-size: 0.875rem;
}

/* 数値表示 */
.number-cell {
    font-family: 'Consolas', 'Monaco', monospace;
}

.positive {
    color: #059669;
}

.negative {
    color: #dc2626;
    font-weight: 600;
}

.zero {
    color: var(--gray-400);
}

/* 空の状態 */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: var(--gray-400);
}
</style>

<div class="profit-loss-container">
    <h2>損益計算書</h2>

    <div class="year-selector">
        <label for="year">会計年度:</label>
        <select id="year" name="year" onchange="window.location.href='?year='+this.value">
            <?php if (empty($availableYears)): ?>
                <option value="">データなし</option>
            <?php else: ?>
                <?php foreach ($availableYears as $year): ?>
                    <option value="<?= htmlspecialchars($year) ?>" <?= $year === $selectedYear ? 'selected' : '' ?>>
                        <?= htmlspecialchars($year) ?>年度
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>

        <div style="margin-left: auto; display: flex; gap: 0.5rem;">
            <a href="/pages/profit-loss-upload.php" class="btn btn-primary">
                CSVアップロード
            </a>
            <?php if ($profitLossData): ?>
                <button onclick="exportToCSV()" class="btn btn-secondary">
                    CSVエクスポート
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$profitLossData): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📊</div>
            <h3>損益計算書データがありません</h3>
            <p style="color: var(--gray-600); margin-bottom: 1.5rem;">
                <?= htmlspecialchars($selectedYear) ?>年度のデータがアップロードされていません
            </p>
            <a href="/pages/profit-loss-upload.php" class="btn btn-primary">
                CSVをアップロード
            </a>
        </div>
    <?php else: ?>
        <!-- サマリーカード -->
        <?php if ($summary): ?>
            <?php
            // 月名マッピング
            $monthNames = [
                '01' => '1月', '02' => '2月', '03' => '3月', '04' => '4月',
                '05' => '5月', '06' => '6月', '07' => '7月', '08' => '8月',
                '09' => '9月', '10' => '10月', '11' => '11月', '12' => '12月'
            ];
            $displayMonth = $monthNames[$summary['latest_month']] ?? '';
            ?>
            <div class="summary-cards">
                <div class="summary-card <?= $summary['revenue'] > 0 ? 'positive' : 'negative' ?>">
                    <div class="summary-card-label"><?= $displayMonth ?>売上高</div>
                    <div class="summary-card-value <?= $summary['revenue'] > 0 ? 'positive' : 'negative' ?>">
                        ¥<?= number_format($summary['revenue']) ?>
                    </div>
                </div>

                <div class="summary-card <?= $summary['gross_profit'] > 0 ? 'positive' : 'negative' ?>">
                    <div class="summary-card-label"><?= $displayMonth ?>売上総利益</div>
                    <div class="summary-card-value <?= $summary['gross_profit'] > 0 ? 'positive' : 'negative' ?>">
                        ¥<?= number_format($summary['gross_profit']) ?>
                    </div>
                    <div class="summary-card-sub">
                        利益率: <?= $summary['revenue'] > 0 ? number_format(($summary['gross_profit'] / $summary['revenue']) * 100, 1) : 0 ?>%
                    </div>
                </div>

                <div class="summary-card <?= $summary['operating_profit'] > 0 ? 'positive' : 'negative' ?>">
                    <div class="summary-card-label"><?= $displayMonth ?>営業利益</div>
                    <div class="summary-card-value <?= $summary['operating_profit'] > 0 ? 'positive' : 'negative' ?>">
                        ¥<?= number_format($summary['operating_profit']) ?>
                    </div>
                    <div class="summary-card-sub">
                        利益率: <?= $summary['revenue'] > 0 ? number_format(($summary['operating_profit'] / $summary['revenue']) * 100, 1) : 0 ?>%
                    </div>
                </div>

                <div class="summary-card <?= $summary['ordinary_profit'] > 0 ? 'positive' : 'negative' ?>">
                    <div class="summary-card-label"><?= $displayMonth ?>経常利益</div>
                    <div class="summary-card-value <?= $summary['ordinary_profit'] > 0 ? 'positive' : 'negative' ?>">
                        ¥<?= number_format($summary['ordinary_profit']) ?>
                    </div>
                    <div class="summary-card-sub">
                        利益率: <?= $summary['revenue'] > 0 ? number_format(($summary['ordinary_profit'] / $summary['revenue']) * 100, 1) : 0 ?>%
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- グラフエリア -->
        <div class="charts-container">
            <div class="chart-card">
                <h3>月別売上推移</h3>
                <canvas id="revenueChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>売上・利益推移</h3>
                <canvas id="profitChart"></canvas>
            </div>
        </div>

        <!-- テーブル -->
        <div class="profit-loss-table-wrapper">
            <?php if (isset($profitLossData['uploaded_at'])): ?>
                <p style="color: var(--gray-600); font-size: 0.875rem; margin-bottom: 1rem;">
                    最終更新: <?= htmlspecialchars($profitLossData['uploaded_at']) ?>
                </p>
            <?php endif; ?>

            <div class="view-toggle">
                <button class="active" onclick="setView('summary')">サマリー表示</button>
                <button onclick="setView('detail')">詳細表示</button>
            </div>

            <table class="profit-loss-table" id="profitLossTable">
                <thead>
                    <tr>
                        <th>勘定科目</th>
                        <th>9月</th>
                        <th>10月</th>
                        <th>11月</th>
                        <th>12月</th>
                        <th>1月</th>
                        <th>2月</th>
                        <th>3月</th>
                        <th>4月</th>
                        <th>5月</th>
                        <th>6月</th>
                        <th>7月</th>
                        <th>8月</th>
                        <th>決算整理</th>
                        <th>合計</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $currentSection = '';
                    $sectionIndex = 0;
                    foreach ($profitLossData['data'] as $rowIndex => $row):
                        $isSectionRow = !empty($row['account']) && empty($row['sub_account']);

                        if ($isSectionRow) {
                            $sectionIndex++;
                            $currentSection = 'section-' . $sectionIndex;
                        }
                    ?>
                        <tr class="<?= $isSectionRow ? 'section-row expandable' : 'detail-row' ?>"
                            <?= $isSectionRow ? 'data-section="' . $currentSection . '"' : 'data-parent="' . $currentSection . '"' ?>
                            <?= $isSectionRow ? 'onclick="toggleSection(\'' . $currentSection . '\')"' : '' ?>>
                            <td>
                                <?php if ($isSectionRow): ?>
                                    <span class="expand-icon">▶</span>
                                <?php endif; ?>
                                <?= htmlspecialchars($row['account'] ?: $row['sub_account']) ?>
                            </td>
                            <?php foreach (['09', '10', '11', '12', '01', '02', '03', '04', '05', '06', '07', '08'] as $month): ?>
                                <?php
                                $value = $row['months'][$month] ?? 0;
                                $class = 'number-cell';
                                if ($value < 0) $class .= ' negative';
                                elseif ($value > 0) $class .= ' positive';
                                else $class .= ' zero';
                                ?>
                                <td class="<?= $class ?>">
                                    <?= $value != 0 ? number_format($value) : '' ?>
                                </td>
                            <?php endforeach; ?>
                            <?php
                            $adjValue = $row['adjustment'] ?? 0;
                            $adjClass = 'number-cell';
                            if ($adjValue < 0) $adjClass .= ' negative';
                            elseif ($adjValue > 0) $adjClass .= ' positive';
                            else $adjClass .= ' zero';
                            ?>
                            <td class="<?= $adjClass ?>">
                                <?= $adjValue != 0 ? number_format($adjValue) : '' ?>
                            </td>
                            <?php
                            $totalValue = $row['total'] ?? 0;
                            $totalClass = 'number-cell';
                            if ($totalValue < 0) $totalClass .= ' negative';
                            elseif ($totalValue > 0) $totalClass .= ' positive';
                            else $totalClass .= ' zero';
                            ?>
                            <td class="<?= $totalClass ?>">
                                <?= $totalValue != 0 ? number_format($totalValue) : '' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentView = 'summary';

function setView(view) {
    currentView = view;
    const buttons = document.querySelectorAll('.view-toggle button');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    if (view === 'summary') {
        // サマリー表示：セクションヘッダーのみ
        document.querySelectorAll('.detail-row').forEach(row => {
            row.classList.remove('show');
        });
        document.querySelectorAll('.expand-icon').forEach(icon => {
            icon.classList.remove('expanded');
        });
    } else {
        // 詳細表示：全て展開
        document.querySelectorAll('.detail-row').forEach(row => {
            row.classList.add('show');
        });
        document.querySelectorAll('.expand-icon').forEach(icon => {
            icon.classList.add('expanded');
        });
    }
}

function toggleSection(sectionId) {
    if (currentView === 'detail') return; // 詳細表示モードでは折りたたみ不可

    const rows = document.querySelectorAll(`[data-parent="${sectionId}"]`);
    const icon = document.querySelector(`[data-section="${sectionId}"] .expand-icon`);

    rows.forEach(row => {
        row.classList.toggle('show');
    });

    if (icon) {
        icon.classList.toggle('expanded');
    }
}

function exportToCSV() {
    const table = document.getElementById('profitLossTable');
    let csv = [];

    // ヘッダー行
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent);
    });
    csv.push(headers.join(','));

    // データ行（表示されている行のみ）
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            let value = td.textContent.trim().replace(/[▶▼]/g, '').trim();
            if (value.includes(',')) {
                value = '"' + value + '"';
            }
            row.push(value);
        });
        csv.push(row.join(','));
    });

    // ダウンロード
    const csvContent = csv.join('\n');
    const bom = '\uFEFF';
    const blob = new Blob([bom + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = '損益計算書_<?= htmlspecialchars($selectedYear) ?>年度.csv';
    link.click();
}

// グラフ描画
<?php if ($profitLossData): ?>
    const chartData = <?= json_encode(prepareChartData($profitLossData['data'])) ?>;

    // 月別売上推移グラフ
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: ['9月', '10月', '11月', '12月', '1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月'],
            datasets: [{
                label: '売上高',
                data: chartData.revenue,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 2,
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '¥' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // 売上・利益推移グラフ
    new Chart(document.getElementById('profitChart'), {
        type: 'bar',
        data: {
            labels: ['9月', '10月', '11月', '12月', '1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月'],
            datasets: [
                {
                    label: '売上高',
                    data: chartData.revenue,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: '#3b82f6',
                    borderWidth: 1
                },
                {
                    label: '売上総利益',
                    data: chartData.grossProfit,
                    backgroundColor: 'rgba(16, 185, 129, 0.5)',
                    borderColor: '#10b981',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 2,
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '¥' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
<?php endif; ?>

// 初期状態：サマリー表示
setView('summary');
</script>

<?php
// グラフデータ準備関数
function prepareChartData($data) {
    $revenue = array_fill(0, 12, 0);
    $grossProfit = array_fill(0, 12, 0);

    $months = ['09', '10', '11', '12', '01', '02', '03', '04', '05', '06', '07', '08'];

    foreach ($data as $row) {
        $account = $row['account'];
        $subAccount = $row['sub_account'] ?? '';
        $searchText = $account . ' ' . $subAccount;

        // 売上高の合計行を探す
        if (stripos($searchText, '売上高合計') !== false || stripos($searchText, '売上合計') !== false) {
            foreach ($months as $i => $month) {
                $revenue[$i] = $row['months'][$month] ?? 0;
            }
        }
        // 売上総利益の行を探す
        if (stripos($searchText, '売上総利益') !== false || stripos($searchText, '粗利益') !== false) {
            foreach ($months as $i => $month) {
                $grossProfit[$i] = $row['months'][$month] ?? 0;
            }
        }
    }

    return [
        'revenue' => $revenue,
        'grossProfit' => $grossProfit
    ];
}

// サマリー計算関数（最新月のデータを表示）
function calculateSummary($data) {
    $summary = [
        'revenue' => 0,
        'gross_profit' => 0,
        'operating_profit' => 0,
        'ordinary_profit' => 0,
        'latest_month' => null
    ];

    // 最新月を特定（データがある最後の月）
    $months = ['09', '10', '11', '12', '01', '02', '03', '04', '05', '06', '07', '08'];
    $latestMonth = null;

    foreach ($data as $row) {
        if (isset($row['months'])) {
            foreach (array_reverse($months) as $month) {
                if (($row['months'][$month] ?? 0) != 0) {
                    $latestMonth = $month;
                    break 2; // 最初に見つかった非ゼロの月で終了
                }
            }
        }
    }

    if (!$latestMonth) {
        $latestMonth = '01'; // デフォルト
    }

    $summary['latest_month'] = $latestMonth;

    foreach ($data as $row) {
        $account = $row['account'];
        $subAccount = $row['sub_account'] ?? '';

        // 最新月のデータを取得
        $monthValue = $row['months'][$latestMonth] ?? 0;

        // 勘定科目と補助科目の両方をチェック
        $searchText = $account . ' ' . $subAccount;

        if (stripos($searchText, '売上高合計') !== false || stripos($searchText, '売上合計') !== false) {
            $summary['revenue'] = $monthValue;
        } elseif (stripos($searchText, '売上総利益') !== false || stripos($searchText, '粗利益') !== false) {
            $summary['gross_profit'] = $monthValue;
        } elseif (stripos($searchText, '営業利益') !== false) {
            $summary['operating_profit'] = $monthValue;
        } elseif (stripos($searchText, '経常利益') !== false) {
            $summary['ordinary_profit'] = $monthValue;
        }
    }

    return $summary;
}
?>

<?php require_once '../functions/footer.php'; ?>
