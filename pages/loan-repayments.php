<?php
require_once '../config/config.php';
require_once '../api/loans-api.php';

// 編集者以上のみアクセス可能
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

$api = new LoansApi();
$message = '';
$error = '';

// パラメータ取得
$year = intval($_GET['year'] ?? date('Y'));
$filterLoanId = $_GET['loan_id'] ?? '';

// 返済データ追加/更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_repayment'])) {
    $repayment = array(
        'loan_id' => $_POST['loan_id'] ?? '',
        'year' => intval($_POST['year'] ?? $year),
        'month' => intval($_POST['month'] ?? 1),
        'principal' => intval($_POST['principal'] ?? 0),
        'interest' => intval($_POST['interest'] ?? 0),
        'balance' => intval($_POST['balance'] ?? 0),
        'payment_date' => $_POST['payment_date'] ?? ''
    );

    if (empty($repayment['loan_id'])) {
        $error = '借入先を選択してください';
    } else {
        $api->upsertRepayment($repayment);
        $message = '返済データを保存しました';
    }
}

// 入金確認
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_repayment'])) {
    $loanId = $_POST['loan_id'] ?? '';
    $repYear = intval($_POST['year'] ?? 0);
    $repMonth = intval($_POST['month'] ?? 0);
    $confirmed = !empty($_POST['confirmed']);

    if ($loanId && $repYear && $repMonth) {
        $api->confirmRepayment($loanId, $repYear, $repMonth, $confirmed);
        $message = $confirmed ? '入金確認しました' : '入金確認を解除しました';
    }
}

// 一括登録
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_save'])) {
    $loanId = $_POST['bulk_loan_id'] ?? '';
    $bulkYear = intval($_POST['bulk_year'] ?? $year);

    if (empty($loanId)) {
        $error = '借入先を選択してください';
    } else {
        for ($m = 1; $m <= 12; $m++) {
            $principal = intval($_POST["principal_$m"] ?? 0);
            $interest = intval($_POST["interest_$m"] ?? 0);
            $balance = intval($_POST["balance_$m"] ?? 0);

            if ($principal > 0 || $interest > 0 || $balance > 0) {
                $api->upsertRepayment(array(
                    'loan_id' => $loanId,
                    'year' => $bulkYear,
                    'month' => $m,
                    'principal' => $principal,
                    'interest' => $interest,
                    'balance' => $balance
                ));
            }
        }
        $message = '返済データを一括登録しました';
    }
}

$loans = $api->getLoans();
$summary = $api->getYearlySummary($year);

require_once '../functions/header.php';
?>

<style>
.repayments-container {
    max-width: 1400px;
}

.year-nav {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.year-nav a {
    padding: 0.5rem 1rem;
    background: #f3f4f6;
    border-radius: 6px;
    text-decoration: none;
    color: #374151;
}

.year-nav a:hover {
    background: #e5e7eb;
}

.year-nav .current-year {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
}

.repayment-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.repayment-table th,
.repayment-table td {
    padding: 0.75rem 0.5rem;
    border: 1px solid #e5e7eb;
    text-align: right;
}

.repayment-table th {
    background: #f9fafb;
    font-weight: 600;
    text-align: center;
}

.repayment-table td:first-child {
    text-align: left;
    font-weight: 500;
}

.repayment-table .month-header {
    background: #dbeafe;
}

.confirmed {
    background: #d1fae5 !important;
}

.unconfirmed {
    background: #fef3c7 !important;
}

.confirm-btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    cursor: pointer;
    border: none;
    border-radius: 4px;
}

.confirm-btn.confirmed {
    background: #10b981;
    color: white;
}

.confirm-btn.unconfirmed {
    background: #f59e0b;
    color: white;
}

.bulk-form {
    background: #f0f9ff;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.bulk-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.bulk-table th,
.bulk-table td {
    padding: 0.5rem;
    border: 1px solid #e5e7eb;
    text-align: center;
}

.bulk-table input {
    width: 100%;
    padding: 0.25rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    text-align: right;
}

.summary-row {
    font-weight: 600;
    background: #f9fafb;
}

.tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    background: #f3f4f6;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
    font-weight: 500;
}

.tab-btn.active {
    background: white;
    border-bottom: 2px solid #3b82f6;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}
</style>

<div class="repayments-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>返済スケジュール管理</h2>
        <a href="loans.php" class="btn btn-secondary">借入先管理に戻る</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- 年ナビゲーション -->
    <div class="year-nav">
        <a href="?year=<?= $year - 1 ?><?= $filterLoanId ? '&loan_id=' . urlencode($filterLoanId) : '' ?>">&lt; <?= $year - 1 ?>年</a>
        <span class="current-year"><?= $year ?>年</span>
        <a href="?year=<?= $year + 1 ?><?= $filterLoanId ? '&loan_id=' . urlencode($filterLoanId) : '' ?>"><?= $year + 1 ?>年 &gt;</a>
    </div>

    <!-- タブ -->
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('overview')">年間一覧</button>
        <button class="tab-btn" onclick="showTab('bulk')">一括登録</button>
    </div>

    <!-- 年間一覧タブ -->
    <div id="overview" class="tab-content active">
        <?php if (empty($loans)): ?>
            <div class="card" style="padding: 2rem; text-align: center;">
                <p>借入先が登録されていません</p>
                <a href="loans.php" class="btn btn-primary">借入先を追加</a>
            </div>
        <?php else: ?>
            <?php foreach ($summary as $item): ?>
            <div class="card" style="margin-bottom: 2rem;">
                <h3 style="margin: 0 0 1rem 0; padding: 1rem; background: #f9fafb; border-radius: 8px 8px 0 0;">
                    <?= htmlspecialchars($item['loan']['name']) ?>
                </h3>
                <div style="overflow-x: auto;">
                    <table class="repayment-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">項目</th>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <th class="month-header"><?= $m ?>月</th>
                                <?php endfor; ?>
                                <th style="width: 100px;">年合計</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalPrincipal = 0;
                            $totalInterest = 0;
                            ?>
                            <tr>
                                <td>元金</td>
                                <?php for ($m = 1; $m <= 12; $m++):
                                    $monthData = $item['months'][$m];
                                    $principal = $monthData['principal'] ?? 0;
                                    $totalPrincipal += $principal;
                                    $isConfirmed = !empty($monthData['confirmed']);
                                ?>
                                <td class="<?= $isConfirmed ? 'confirmed' : ($monthData ? 'unconfirmed' : '') ?>">
                                    <?= $principal ? number_format($principal) : '-' ?>
                                </td>
                                <?php endfor; ?>
                                <td class="summary-row"><?= number_format($totalPrincipal) ?></td>
                            </tr>
                            <tr>
                                <td>利息</td>
                                <?php for ($m = 1; $m <= 12; $m++):
                                    $monthData = $item['months'][$m];
                                    $interest = $monthData['interest'] ?? 0;
                                    $totalInterest += $interest;
                                    $isConfirmed = !empty($monthData['confirmed']);
                                ?>
                                <td class="<?= $isConfirmed ? 'confirmed' : ($monthData ? 'unconfirmed' : '') ?>">
                                    <?= $interest ? number_format($interest) : '-' ?>
                                </td>
                                <?php endfor; ?>
                                <td class="summary-row"><?= number_format($totalInterest) ?></td>
                            </tr>
                            <tr>
                                <td>残高</td>
                                <?php for ($m = 1; $m <= 12; $m++):
                                    $monthData = $item['months'][$m];
                                    $balance = $monthData['balance'] ?? 0;
                                    $isConfirmed = !empty($monthData['confirmed']);
                                ?>
                                <td class="<?= $isConfirmed ? 'confirmed' : ($monthData ? 'unconfirmed' : '') ?>">
                                    <?= $balance ? number_format($balance) : '-' ?>
                                </td>
                                <?php endfor; ?>
                                <td class="summary-row">-</td>
                            </tr>
                            <tr>
                                <td>確認</td>
                                <?php for ($m = 1; $m <= 12; $m++):
                                    $monthData = $item['months'][$m];
                                    $isConfirmed = !empty($monthData['confirmed']);
                                ?>
                                <td style="text-align: center;">
                                    <?php if ($monthData): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="loan_id" value="<?= htmlspecialchars($item['loan']['id']) ?>">
                                        <input type="hidden" name="year" value="<?= $year ?>">
                                        <input type="hidden" name="month" value="<?= $m ?>">
                                        <input type="hidden" name="confirmed" value="<?= $isConfirmed ? '' : '1' ?>">
                                        <button type="submit" name="confirm_repayment"
                                            class="confirm-btn <?= $isConfirmed ? 'confirmed' : 'unconfirmed' ?>"
                                            title="<?= $isConfirmed ? '確認済み - クリックで解除' : '未確認 - クリックで確認' ?>">
                                            <?= $isConfirmed ? '✓' : '?' ?>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span style="color: #9ca3af;">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endfor; ?>
                                <td>-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 一括登録タブ -->
    <div id="bulk" class="tab-content">
        <div class="bulk-form">
            <h3 style="margin-top: 0;">返済データ一括登録</h3>
            <form method="POST">
                <div class="form-row" style="display: flex; gap: 1rem; align-items: end; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label>借入先 *</label>
                        <select name="bulk_loan_id" class="form-input" required>
                            <option value="">選択してください</option>
                            <?php foreach ($loans as $loan): ?>
                            <option value="<?= htmlspecialchars($loan['id']) ?>"><?= htmlspecialchars($loan['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>年度</label>
                        <input type="number" name="bulk_year" class="form-input" value="<?= $year ?>" style="width: 100px;">
                    </div>
                </div>

                <table class="bulk-table">
                    <thead>
                        <tr>
                            <th>月</th>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <th><?= $m ?>月</th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>元金</td>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <td><input type="number" name="principal_<?= $m ?>" placeholder="0"></td>
                            <?php endfor; ?>
                        </tr>
                        <tr>
                            <td>利息</td>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <td><input type="number" name="interest_<?= $m ?>" placeholder="0"></td>
                            <?php endfor; ?>
                        </tr>
                        <tr>
                            <td>残高</td>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <td><input type="number" name="balance_<?= $m ?>" placeholder="0"></td>
                            <?php endfor; ?>
                        </tr>
                    </tbody>
                </table>

                <div style="margin-top: 1rem;">
                    <button type="submit" name="bulk_save" class="btn btn-primary">一括登録</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showTab(tabId) {
    // タブコンテンツを切り替え
    document.querySelectorAll('.tab-content').forEach(function(el) {
        el.classList.remove('active');
    });
    document.getElementById(tabId).classList.add('active');

    // タブボタンを切り替え
    document.querySelectorAll('.tab-btn').forEach(function(el) {
        el.classList.remove('active');
    });
    event.target.classList.add('active');
}
</script>

<?php require_once '../functions/footer.php'; ?>
