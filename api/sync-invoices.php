<?php
/**
 * MFクラウド請求書から請求書データを同期するAPI
 * finance.phpの「MFから同期」ボタンから呼び出される
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/mf-api.php';

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
    if (!empty($_POST['target_month'])) {
        $targetMonth = $_POST['target_month'];
        $from = date('Y-m-01', strtotime($targetMonth . '-01'));
        $to = date('Y-m-t', strtotime($targetMonth . '-01'));
    } else {
        // デフォルト: 今月
        $from = date('Y-m-01');
        $to = date('Y-m-t');
    }

    // MFから請求書を取得
    $invoices = $client->getAllInvoices($from, $to);

    // データ取得
    $data = getData();
    if (!isset($data['mf_invoices'])) {
        $data['mf_invoices'] = [];
    }

    // MFから取得した請求書のIDマップを作成
    $mfInvoiceIds = [];
    foreach ($invoices as $invoice) {
        $mfInvoiceIds[$invoice['id']] = true;
    }

    // 既存の請求書のIDマップを作成（重複チェック用）
    $existingIds = [];
    foreach ($data['mf_invoices'] as $existingInvoice) {
        $existingIds[$existingInvoice['id']] = true;
    }

    // MFで削除された請求書を検知して削除
    // 同期対象期間内の請求書で、MFに存在しないものを削除
    $deleteCount = 0;
    $data['mf_invoices'] = array_values(array_filter($data['mf_invoices'], function($invoice) use ($mfInvoiceIds, $from, $to, &$deleteCount) {
        $billingDate = $invoice['billing_date'] ?? '';

        // 請求日が同期対象期間内かどうか確認
        if ($billingDate >= $from && $billingDate <= $to) {
            // 期間内の請求書で、MFに存在しない場合は削除
            if (!isset($mfInvoiceIds[$invoice['id']])) {
                $deleteCount++;
                return false; // 削除
            }
        }
        return true; // 保持
    }));

    if (empty($invoices)) {
        $periodLabel = date('Y年n月', strtotime($from));
        $message = "{$periodLabel}の請求書: MFに該当なし";
        if ($deleteCount > 0) {
            $message .= "、削除{$deleteCount}件";
        }

        if ($deleteCount > 0) {
            $data['mf_sync_timestamp'] = date('Y-m-d H:i:s');
            saveData($data);
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'synced' => 0,
            'new' => 0,
            'skip' => 0,
            'deleted' => $deleteCount,
            'period' => ['from' => $from, 'to' => $to]
        ]);
        exit;
    }

    $newCount = 0;
    $skipCount = 0;
    $updateCount = 0;

    foreach ($invoices as $invoice) {
        $invoiceId = $invoice['id'] ?? '';

        // 重複チェック：既存のIDと一致する場合はスキップ
        if (isset($existingIds[$invoiceId])) {
            $skipCount++;
            continue;
        }

        // タグからPJ番号と担当者名を抽出
        $tags = $invoice['tag_names'] ?? [];
        $projectId = '';
        $assignee = '';
        $closingDate = '';

        foreach ($tags as $tag) {
            // PJ番号を抽出（P + 数字）
            if (preg_match('/^P\d+$/i', $tag)) {
                $projectId = $tag;
            }
            // 〆日を抽出（例: 20日〆, 末日〆）
            if (preg_match('/(末日|[\d]+日)〆/', $tag, $matches)) {
                $closingDate = $matches[1] . '〆';
            }
            // 担当者名を抽出（日本語の人名を想定）
            if (mb_strlen($tag) === 2 &&
                preg_match('/^[ぁ-んァ-ヶー一-龯]+$/', $tag) &&
                !preg_match('/(株式|有限|合同|本社|支店|営業|部|課|係|室|〆|メール|販売|レンタル|建設|工事|開発|総務|経理|人事|企画|管理|その他|郵送|派遣|修理|交換|水没|末締)/', $tag)) {
                $assignee = $tag;
            }
        }

        // 金額詳細を取得
        $subtotal = floatval($invoice['subtotal_price'] ?? 0);
        $tax = floatval($invoice['excise_price'] ?? 0);
        $total = floatval($invoice['total_price'] ?? 0);

        $data['mf_invoices'][] = [
            'id' => $invoiceId,
            'billing_number' => $invoice['billing_number'] ?? '',
            'title' => $invoice['title'] ?? '',
            'partner_name' => $invoice['partner_name'] ?? '',
            'billing_date' => $invoice['billing_date'] ?? '',
            'due_date' => $invoice['due_date'] ?? '',
            'sales_date' => $invoice['sales_date'] ?? '',
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total_amount' => $total,
            'payment_status' => $invoice['payment_status'] ?? '未設定',
            'posting_status' => $invoice['posting_status'] ?? '未郵送',
            'email_status' => $invoice['email_status'] ?? '未送信',
            'memo' => $invoice['memo'] ?? '',
            'note' => $invoice['note'] ?? '',
            'tag_names' => $tags,
            'project_id' => $projectId,
            'assignee' => $assignee,
            'closing_date' => $closingDate,
            'pdf_url' => $invoice['pdf_url'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'synced_at' => date('Y-m-d H:i:s')
        ];
        $newCount++;
    }

    // 同期時刻を記録
    $data['mf_sync_timestamp'] = date('Y-m-d H:i:s');

    saveData($data);

    $periodLabel = date('Y年n月', strtotime($from));
    $message = "{$periodLabel}の請求書を同期: 新規{$newCount}件";
    if ($skipCount > 0) {
        $message .= "、既存{$skipCount}件";
    }
    if ($deleteCount > 0) {
        $message .= "、削除{$deleteCount}件";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'synced' => count($invoices),
        'new' => $newCount,
        'skip' => $skipCount,
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
