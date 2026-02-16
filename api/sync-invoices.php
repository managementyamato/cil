<?php
/**
 * MFクラウド請求書から請求書データを同期するAPI
 * finance.phpの「MFから同期」ボタンから呼び出される
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/mf-api.php';
require_once __DIR__ . '/../functions/mf-invoice-sync.php';

header('Content-Type: application/json; charset=utf-8');

// 認証チェック
if (!isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です']);
    exit;
}

// 編集権限チェック
if (!canEdit()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '編集権限が必要です']);
    exit;
}

// CSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

try {
    // MF APIが設定されているか確認
    if (!MFApiClient::isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'MFクラウド請求書APIが設定されていません。設定画面から認証してください。']);
        exit;
    }

    $client = new MFApiClient();

    // 期間指定
    $isAllPeriod = false;
    if (!empty($_POST['target_month'])) {
        $targetMonth = $_POST['target_month'];
        if ($targetMonth === 'all') {
            // 全期間: 過去3年分
            $isAllPeriod = true;
            $from = date('Y-m-01', strtotime('-3 years'));
            $to = date('Y-m-d');
        } else {
            $from = date('Y-m-01', strtotime($targetMonth . '-01'));
            $to = date('Y-m-t', strtotime($targetMonth . '-01'));
        }
    } else {
        // デフォルト: 今月
        $from = date('Y-m-01');
        $to = date('Y-m-t');
    }

    // MFから請求書を取得（同期は常に最新データを取得、結果はキャッシュ保存）
    $invoices = $client->getAllInvoices($from, $to, true);

    // データ取得
    $data = getData();
    if (!isset($data['mf_invoices'])) {
        $data['mf_invoices'] = [];
    }

    // 共通関数で同期実行
    $syncResult = syncMfInvoices($data, $invoices, $from, $to);
    $data = $syncResult['data'];

    saveData($data);

    $periodLabel = $isAllPeriod ? '全期間' : date('Y年n月', strtotime($from));
    $newCount = $syncResult['new'];
    $updateCount = $syncResult['updated'];
    $deleteCount = $syncResult['deleted'];

    $message = "{$periodLabel}の請求書を同期: 新規{$newCount}件";
    if ($updateCount > 0) {
        $message .= "、更新{$updateCount}件";
    }
    if ($deleteCount > 0) {
        $message .= "、削除{$deleteCount}件";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'synced' => count($invoices),
        'new' => $newCount,
        'updated' => $updateCount,
        'deleted' => $deleteCount,
        'period' => ['from' => $from, 'to' => $to]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
