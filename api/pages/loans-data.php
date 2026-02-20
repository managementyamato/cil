<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET']
]);

// 編集権限チェック
if (!canEdit()) {
    errorResponse('権限がありません', 403);
}

require_once __DIR__ . '/../loans-api.php';

$api = new LoansApi();
$loansData = $api->getData();
$loans = $loansData['loans'] ?? [];
$repayments = $loansData['repayments'] ?? [];

// 借入金サマリー
$totalLoanAmount = 0;
$totalMonthlyRepayment = 0;
$loanSummary = [];

foreach ($loans as $loan) {
    $loanId = $loan['id'] ?? '';
    $loanName = $loan['name'] ?? '';
    $balance = floatval($loan['balance'] ?? 0);
    $totalLoanAmount += $balance;

    // 最新の返済データを取得
    $latestRepayment = null;
    $monthlyTotal = 0;
    foreach ($repayments as $rep) {
        if (($rep['loan_id'] ?? '') === $loanId) {
            $repTotal = floatval($rep['principal'] ?? 0) + floatval($rep['interest'] ?? 0);
            if (!$latestRepayment || ($rep['date'] ?? '') > ($latestRepayment['date'] ?? '')) {
                $latestRepayment = $rep;
                $monthlyTotal = $repTotal;
            }
        }
    }
    $totalMonthlyRepayment += $monthlyTotal;

    $loanSummary[] = [
        'id' => $loanId,
        'name' => $loanName,
        'initial_amount' => floatval($loan['initial_amount'] ?? 0),
        'balance' => $balance,
        'interest_rate' => floatval($loan['interest_rate'] ?? 0),
        'repayment_day' => intval($loan['repayment_day'] ?? 25),
        'start_date' => $loan['start_date'] ?? '',
        'end_date' => $loan['end_date'] ?? '',
        'notes' => $loan['notes'] ?? '',
        'monthly_repayment' => $monthlyTotal,
    ];
}

// Google Drive連携状態
$driveConfigured = false;
$driveConfigFile = __DIR__ . '/../../config/google-drive-token.json';
if (file_exists($driveConfigFile)) {
    $driveConfigured = true;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'loans' => $loanSummary,
        'totalLoanAmount' => $totalLoanAmount,
        'totalMonthlyRepayment' => $totalMonthlyRepayment,
        'loanCount' => count($loans),
        'driveConfigured' => $driveConfigured,
    ]
], JSON_UNESCAPED_UNICODE);
