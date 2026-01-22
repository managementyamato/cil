<?php
require_once '../api/auth.php';
$data = getData();

$total = count($data['troubles']);
$pending = count(array_filter($data['troubles'], function($t) { return $t['status'] === '未対応'; }));
$inProgress = count(array_filter($data['troubles'], function($t) { return $t['status'] === '対応中'; }));
$onHold = count(array_filter($data['troubles'], function($t) { return $t['status'] === '保留'; }));
$completed = count(array_filter($data['troubles'], function($t) { return $t['status'] === '完了'; }));

// 完了率を計算
$completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

// P番号別統計（上位5件）
$pjStats = [];
foreach ($data['troubles'] ?? array() as $t) {
    $pjNumber = $t['pj_number'] ?? '';
    if (empty($pjNumber)) {
        $pjNumber = 'その他';
    }
    $pjStats[$pjNumber] = (isset($pjStats[$pjNumber]) ? $pjStats[$pjNumber] : 0) + 1;
}
arsort($pjStats);
$pjStats = array_slice($pjStats, 0, 5, true);

// 月別トラブル推移（過去6ヶ月）
$monthlyTroubles = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthlyTroubles[$month] = 0;
}
foreach ($data['troubles'] ?? array() as $t) {
    $date = $t['occurrence_date'] ?? $t['created_at'] ?? '';
    if ($date) {
        $month = date('Y-m', strtotime($date));
        if (isset($monthlyTroubles[$month])) {
            $monthlyTroubles[$month]++;
        }
    }
}

// 月別売上推移（過去6ヶ月）
$monthlySales = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthlySales[$month] = 0;
}
foreach ($data['mf_invoices'] ?? array() as $invoice) {
    $salesDate = $invoice['sales_date'] ?? '';
    if ($salesDate) {
        $month = date('Y-m', strtotime(str_replace('/', '-', $salesDate)));
        if (isset($monthlySales[$month])) {
            $monthlySales[$month] += floatval($invoice['total_amount'] ?? 0);
        }
    }
}

// 今月・先月の売上
$currentMonth = date('Y-m');
$currentMonthSales = $monthlySales[$currentMonth] ?? 0;
$lastMonth = date('Y-m', strtotime('-1 month'));
$lastMonthSales = $monthlySales[$lastMonth] ?? 0;
$salesChange = $lastMonthSales > 0 ? round((($currentMonthSales - $lastMonthSales) / $lastMonthSales) * 100, 1) : 0;

require_once '../functions/header.php';
?>

<style>
/* ダッシュボード専用スタイル */
.dashboard-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 900px) {
    .dashboard-summary { grid-template-columns: 1fr; }
}

/* 売上サマリーカード */
.sales-summary-card {
    display: block;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
}
.sales-summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
}
.sales-summary-card h3 {
    margin: 0 0 1rem 0;
    font-size: 0.875rem;
    font-weight: 500;
    opacity: 0.9;
}
.sales-summary-card .amount {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}
.sales-summary-card .change {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}
.sales-summary-card .change.positive { background: rgba(255,255,255,0.2); }
.sales-summary-card .change.negative { background: rgba(239,68,68,0.3); }

/* トラブルサマリーカード */
.trouble-summary-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.trouble-summary-card h3 {
    margin: 0 0 1rem 0;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--gray-600);
}
.trouble-stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.5rem;
    text-align: center;
}
.trouble-stat-item {
    padding: 0.75rem 0.5rem;
    border-radius: 8px;
    background: var(--gray-50);
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
    cursor: pointer;
    border: 2px solid transparent;
}
.trouble-stat-item:hover {
    background: var(--gray-100);
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.trouble-stat-item .value {
    font-size: 1.5rem;
    font-weight: bold;
    line-height: 1.2;
}
.trouble-stat-item .label {
    font-size: 0.75rem;
    color: var(--gray-600);
    margin-top: 0.25rem;
}
.trouble-stat-item.pending .value { color: #ef4444; }
.trouble-stat-item.in-progress .value { color: #f59e0b; }
.trouble-stat-item.on-hold .value { color: #6b7280; }
.trouble-stat-item.completed .value { color: #10b981; }

/* 完了率プログレスバー */
.completion-bar {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
}
.completion-bar .header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}
.completion-bar .header .rate {
    font-weight: bold;
    color: #10b981;
}
.completion-bar .bar {
    height: 8px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
}
.completion-bar .bar .fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    border-radius: 4px;
    transition: width 0.5s ease;
}

/* グラフセクション */
.charts-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 1100px) {
    .charts-section { grid-template-columns: 1fr; }
}
.chart-card {
    display: block;
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
    cursor: pointer;
}
.chart-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}
.chart-card h3 {
    margin: 0 0 1rem 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--gray-700);
}
.chart-container {
    height: 220px;
}

/* P番号リスト */
.pj-stats-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.pj-stats-card h3 {
    margin: 0 0 1rem 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--gray-700);
}
.pj-stat-row {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid var(--gray-100);
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s;
    text-decoration: none;
    color: inherit;
}
.pj-stat-row:last-child { border-bottom: none; }
.pj-stat-row:hover {
    background: var(--gray-50);
    transform: translateX(4px);
}
.pj-stat-row .pj-name {
    width: 120px;
    font-weight: 500;
    color: var(--primary);
}
.pj-stat-row .pj-bar {
    flex: 1;
    margin: 0 1rem;
    height: 6px;
    background: var(--gray-200);
    border-radius: 3px;
    overflow: hidden;
}
.pj-stat-row .pj-bar .fill {
    height: 100%;
    background: var(--primary);
    border-radius: 3px;
}
.pj-stat-row .pj-count {
    width: 50px;
    text-align: right;
    font-weight: 600;
    color: var(--gray-700);
}
</style>

<!-- 上段: 売上サマリー & トラブルサマリー -->
<div class="dashboard-summary">
    <!-- 売上サマリー -->
    <a href="finance.php" class="sales-summary-card">
        <h3>今月の売上</h3>
        <div class="amount">¥<?= number_format($currentMonthSales) ?></div>
        <?php if ($lastMonthSales > 0): ?>
        <span class="change <?= $salesChange >= 0 ? 'positive' : 'negative' ?>">
            前月比 <?= $salesChange >= 0 ? '+' : '' ?><?= $salesChange ?>%
        </span>
        <?php endif; ?>
    </a>

    <!-- トラブルサマリー -->
    <div class="trouble-summary-card">
        <h3>トラブル対応状況</h3>
        <div class="trouble-stats">
            <a href="troubles.php" class="trouble-stat-item">
                <div class="value"><?= $total ?></div>
                <div class="label">総件数</div>
            </a>
            <a href="troubles.php?status=未対応" class="trouble-stat-item pending">
                <div class="value"><?= $pending ?></div>
                <div class="label">未対応</div>
            </a>
            <a href="troubles.php?status=対応中" class="trouble-stat-item in-progress">
                <div class="value"><?= $inProgress ?></div>
                <div class="label">対応中</div>
            </a>
            <a href="troubles.php?status=保留" class="trouble-stat-item on-hold">
                <div class="value"><?= $onHold ?></div>
                <div class="label">保留</div>
            </a>
            <a href="troubles.php?status=完了" class="trouble-stat-item completed">
                <div class="value"><?= $completed ?></div>
                <div class="label">完了</div>
            </a>
        </div>
        <div class="completion-bar">
            <div class="header">
                <span>完了率</span>
                <span class="rate"><?= $completionRate ?>%</span>
            </div>
            <div class="bar">
                <div class="fill" style="width: <?= $completionRate ?>%"></div>
            </div>
        </div>
    </div>
</div>

<!-- 中段: グラフ -->
<div class="charts-section">
    <a href="finance.php" class="chart-card">
        <h3>売上推移（過去6ヶ月）</h3>
        <div class="chart-container">
            <canvas id="salesChart"></canvas>
        </div>
    </a>
    <a href="troubles.php" class="chart-card">
        <h3>トラブル発生推移（過去6ヶ月）</h3>
        <div class="chart-container">
            <canvas id="troubleChart"></canvas>
        </div>
    </a>
</div>

<!-- 下段: P番号別 -->
<div class="pj-stats-card">
    <h3>P番号別トラブル件数（上位5件）</h3>
    <?php if (empty($pjStats)): ?>
        <p style="color: var(--gray-500); text-align: center; padding: 1rem;">データがありません</p>
    <?php else: ?>
        <?php foreach ($pjStats as $pjNumber => $count): ?>
        <a href="troubles.php?pj_number=<?= urlencode($pjNumber) ?>" class="pj-stat-row">
            <div class="pj-name"><?= htmlspecialchars($pjNumber) ?></div>
            <div class="pj-bar">
                <div class="fill" style="width: <?= $total > 0 ? ($count / $total) * 100 : 0 ?>%"></div>
            </div>
            <div class="pj-count"><?= $count ?>件</div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// 売上推移グラフ
new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels: [<?= implode(',', array_map(function($m) { return "'" . date('n月', strtotime($m . '-01')) . "'"; }, array_keys($monthlySales))); ?>],
        datasets: [{
            data: [<?= implode(',', array_values($monthlySales)); ?>],
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
            borderRadius: 6,
            barThickness: 32
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: {
                    callback: v => v >= 1000000 ? '¥' + (v/1000000) + 'M' : v >= 1000 ? '¥' + (v/1000) + 'K' : '¥' + v
                }
            },
            x: { grid: { display: false } }
        }
    }
});

// トラブル推移グラフ
new Chart(document.getElementById('troubleChart'), {
    type: 'line',
    data: {
        labels: [<?= implode(',', array_map(function($m) { return "'" . date('n月', strtotime($m . '-01')) . "'"; }, array_keys($monthlyTroubles))); ?>],
        datasets: [{
            data: [<?= implode(',', array_values($monthlyTroubles)); ?>],
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#ef4444',
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { stepSize: 1 }
            },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php require_once '../functions/footer.php'; ?>
