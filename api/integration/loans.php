<?php
/**
 * 借入金返済データAPI
 * GET: 借入先・返済データを取得
 * POST: 入金確認ステータスを更新
 */

require_once 'api-auth.php';
require_once dirname(__DIR__) . '/loans-api.php';

// CORS設定（許可されたオリジンのみ）
setIntegrationCorsHeaders();

// OPTIONSリクエスト（プリフライト）の処理
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 認証チェック
$auth = authenticateApiRequest();
if (!$auth['success']) {
    sendErrorResponse($auth['error'], $auth['code']);
}

$keyInfo = $auth['key_info'];
$method = $_SERVER['REQUEST_METHOD'];
$api = new LoansApi();

switch ($method) {
    case 'GET':
        handleGetLoans($api, $keyInfo);
        break;
    case 'POST':
        handlePostLoans($api, $keyInfo);
        break;
    default:
        sendErrorResponse('許可されていないメソッドです', 405);
}

/**
 * 借入金データ取得
 */
function handleGetLoans($api, $keyInfo) {
    $action = $_GET['action'] ?? 'summary';
    $year = intval($_GET['year'] ?? date('Y'));
    $month = isset($_GET['month']) ? intval($_GET['month']) : null;
    $loanId = $_GET['loan_id'] ?? null;

    switch ($action) {
        case 'loans':
            // 借入先一覧
            $loans = $api->getLoans();
            logApiRequest('get_loans', $keyInfo['name'], array('count' => count($loans)));
            sendSuccessResponse($loans, '借入先一覧を取得しました');
            break;

        case 'repayments':
            // 返済データ
            $repayments = $api->getRepayments($loanId, $year, $month);
            logApiRequest('get_repayments', $keyInfo['name'], array(
                'year' => $year,
                'month' => $month,
                'loan_id' => $loanId,
                'count' => count($repayments)
            ));
            sendSuccessResponse($repayments, '返済データを取得しました');
            break;

        case 'confirmed':
            // 確認済み返済データのみ
            $confirmed = $api->getConfirmedRepayments($year, $month);
            logApiRequest('get_confirmed_repayments', $keyInfo['name'], array(
                'year' => $year,
                'month' => $month,
                'count' => count($confirmed)
            ));
            sendSuccessResponse(array_values($confirmed), '確認済み返済データを取得しました');
            break;

        case 'summary':
        default:
            // 年間サマリー
            $summary = $api->getYearlySummary($year);
            logApiRequest('get_loans_summary', $keyInfo['name'], array('year' => $year));
            sendSuccessResponse($summary, $year . '年の返済サマリーを取得しました');
            break;
    }
}

/**
 * 入金確認ステータス更新
 */
function handlePostLoans($api, $keyInfo) {
    // JSONデータを取得
    $input = file_get_contents('php://input');
    $requestData = json_decode($input, true);

    if (!$requestData) {
        sendErrorResponse('無効なJSONデータです', 400);
    }

    $action = $requestData['action'] ?? '';

    switch ($action) {
        case 'confirm':
            // 入金確認
            $loanId = $requestData['loan_id'] ?? '';
            $year = intval($requestData['year'] ?? 0);
            $month = intval($requestData['month'] ?? 0);
            $confirmed = !empty($requestData['confirmed']);

            if (empty($loanId) || !$year || !$month) {
                sendErrorResponse('loan_id, year, month は必須です', 400);
            }

            $api->confirmRepayment($loanId, $year, $month, $confirmed);
            logApiRequest('confirm_repayment', $keyInfo['name'], array(
                'loan_id' => $loanId,
                'year' => $year,
                'month' => $month,
                'confirmed' => $confirmed
            ));

            sendSuccessResponse(array(
                'loan_id' => $loanId,
                'year' => $year,
                'month' => $month,
                'confirmed' => $confirmed
            ), $confirmed ? '入金確認しました' : '入金確認を解除しました');
            break;

        case 'upsert_repayment':
            // 返済データ登録/更新
            $repayment = array(
                'loan_id' => $requestData['loan_id'] ?? '',
                'year' => intval($requestData['year'] ?? 0),
                'month' => intval($requestData['month'] ?? 0),
                'principal' => intval($requestData['principal'] ?? 0),
                'interest' => intval($requestData['interest'] ?? 0),
                'balance' => intval($requestData['balance'] ?? 0),
                'payment_date' => $requestData['payment_date'] ?? ''
            );

            if (empty($repayment['loan_id']) || !$repayment['year'] || !$repayment['month']) {
                sendErrorResponse('loan_id, year, month は必須です', 400);
            }

            $result = $api->upsertRepayment($repayment);
            logApiRequest('upsert_repayment', $keyInfo['name'], $repayment);
            sendSuccessResponse($result, '返済データを保存しました');
            break;

        case 'add_loan':
            // 借入先追加
            $loan = array(
                'name' => trim($requestData['name'] ?? ''),
                'initial_amount' => intval($requestData['initial_amount'] ?? 0),
                'start_date' => $requestData['start_date'] ?? '',
                'interest_rate' => floatval($requestData['interest_rate'] ?? 0),
                'repayment_day' => intval($requestData['repayment_day'] ?? 25),
                'notes' => trim($requestData['notes'] ?? '')
            );

            if (empty($loan['name'])) {
                sendErrorResponse('借入先名は必須です', 400);
            }

            $result = $api->addLoan($loan);
            logApiRequest('add_loan', $keyInfo['name'], array('name' => $loan['name']));
            sendSuccessResponse($result, '借入先を追加しました');
            break;

        case 'bulk_confirm':
            // 一括確認
            $confirmations = $requestData['confirmations'] ?? array();
            $results = array('confirmed' => 0, 'errors' => array());

            foreach ($confirmations as $idx => $conf) {
                $loanId = $conf['loan_id'] ?? '';
                $year = intval($conf['year'] ?? 0);
                $month = intval($conf['month'] ?? 0);

                if (empty($loanId) || !$year || !$month) {
                    $results['errors'][] = array('index' => $idx, 'error' => '必須項目が不足');
                    continue;
                }

                $api->confirmRepayment($loanId, $year, $month, true);
                $results['confirmed']++;
            }

            logApiRequest('bulk_confirm', $keyInfo['name'], array('count' => $results['confirmed']));
            sendSuccessResponse($results, '一括確認を実行しました');
            break;

        default:
            sendErrorResponse('不明なアクションです: ' . $action, 400);
    }
}
