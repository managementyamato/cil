<?php
/**
 * MFから最新のデータを取得して同期するスクリプト
 * コマンドラインから実行可能
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mf-api.php';

echo "=== MF請求書データ取得スクリプト ===\n\n";

// 設定確認
if (!MFApiClient::isConfigured()) {
    echo "エラー: MF APIの設定が完了していません。\n";
    echo "mf-settings.php から設定を完了してください。\n";
    exit(1);
}

echo "MF API設定: OK\n";

// データ読み込み
$data = getData();
echo "既存データ読み込み: OK\n";

// 既存の請求書数を確認
$existingCount = isset($data['mf_invoices']) ? count($data['mf_invoices']) : 0;
echo "既存の請求書数: {$existingCount}件\n\n";

try {
    $client = new MFApiClient();

    echo "MF APIに接続中...\n";

    // デバッグ情報を収集
    $debugInfo = array(
        'sync_time' => date('Y-m-d H:i:s'),
        'request_params' => array(),
        'raw_response' => null,
        'parsed_invoices' => null,
        'errors' => array()
    );

    // パラメータなしで全ページ取得を試す
    $invoices = null;
    try {
        echo "全件取得を試行中...\n";
        $invoices = $client->getAllInvoices(null, null);
        $debugInfo['request_params'] = array('from' => null, 'to' => null, 'note' => '日付フィルタなしで全件取得');
        echo "全件取得: 成功\n";
    } catch (Exception $e1) {
        echo "全件取得: 失敗 - {$e1->getMessage()}\n";
        $debugInfo['errors'][] = 'パラメータなし取得エラー: ' . $e1->getMessage();

        // 過去3ヶ月分のデータを全ページ取得
        echo "過去3ヶ月分の取得を試行中...\n";
        $from = date('Y-m-d', strtotime('-3 months'));
        $to = date('Y-m-d');
        $invoices = $client->getAllInvoices($from, $to);
        $debugInfo['request_params'] = array('from' => $from, 'to' => $to, 'note' => '過去3ヶ月分で取得');
        echo "過去3ヶ月分取得: 成功\n";
    }

    $debugInfo['parsed_invoices'] = $invoices;
    $debugInfo['invoice_count'] = count($invoices);

    echo "\n取得した請求書数: " . count($invoices) . "件\n\n";

    // サンプルデータを保存（最初の3件）
    if (!empty($invoices)) {
        $debugInfo['sample_invoices'] = array_slice($invoices, 0, 3);

        // 最初の請求書の詳細を表示
        $firstInvoice = $invoices[0];
        echo "--- サンプル（最初の請求書）---\n";
        echo "ID: " . ($firstInvoice['id'] ?? '-') . "\n";
        echo "請求書番号: " . ($firstInvoice['billing_number'] ?? '-') . "\n";
        echo "顧客名: " . ($firstInvoice['partner_name'] ?? '-') . "\n";
        echo "タイトル: " . ($firstInvoice['title'] ?? '-') . "\n";
        echo "請求日: " . ($firstInvoice['billing_date'] ?? '-') . "\n";
        echo "合計金額: ¥" . number_format($firstInvoice['total_amount'] ?? 0) . "\n";
        echo "タグ: " . implode(', ', $firstInvoice['tag_names'] ?? array()) . "\n";
        echo "---\n\n";
    }

    // デバッグ情報をファイルに保存
    $debugFile = __DIR__ . '/mf-sync-debug.json';
    file_put_contents($debugFile, json_encode($debugInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "デバッグ情報を保存: {$debugFile}\n\n";

    // 請求書データをmf_invoices配列に保存
    if (!isset($data['mf_invoices'])) {
        $data['mf_invoices'] = array();
    }

    // 既存のIDマップを作成（重複チェック用）
    $existingIds = array();
    foreach ($data['mf_invoices'] as $existingInvoice) {
        $existingIds[$existingInvoice['id']] = true;
    }

    $newCount = 0;
    $updateCount = 0;
    $skipCount = 0;

    echo "データ処理中...\n";

    foreach ($invoices as $invoice) {
        $invoiceId = $invoice['id'] ?? '';

        // タグからPJ番号と担当者名を抽出
        $tags = $invoice['tag_names'] ?? array();
        $projectId = '';
        $assignee = '';

        foreach ($tags as $tag) {
            // PJ番号を抽出（P + 数字）
            if (preg_match('/^P\d+$/i', $tag)) {
                $projectId = $tag;
            }
            // 担当者名を抽出（日本語の人名を想定）
            // 会社名や長い文字列を除外
            if (mb_strlen($tag) <= 4 &&
                preg_match('/^[ぁ-んァ-ヶー一-龯]+$/', $tag) &&
                !preg_match('/(株式会社|有限会社|合同会社|〆|メール|販売|レンタル)/', $tag)) {
                $assignee = $tag;
            }
        }

        // 金額詳細を計算
        $subtotal = 0;
        $tax = 0;
        $total = 0;

        if (isset($invoice['items']) && is_array($invoice['items'])) {
            foreach ($invoice['items'] as $item) {
                $subtotal += floatval($item['price'] ?? 0) * floatval($item['quantity'] ?? 0);
            }
        }

        // 合計金額（total_amountがあればそれを使用）
        if (isset($invoice['total_amount'])) {
            $total = floatval($invoice['total_amount']);
            // 消費税を逆算（小計から計算できない場合）
            if ($subtotal > 0) {
                $tax = $total - $subtotal;
            }
        } else {
            // total_amountがない場合は小計のみ
            $total = $subtotal;
        }

        $invoiceData = array(
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
            'memo' => $invoice['memo'] ?? '',
            'note' => $invoice['note'] ?? '',
            'tag_names' => $tags,
            'project_id' => $projectId,
            'assignee' => $assignee,
            'pdf_url' => $invoice['pdf_url'] ?? '',
            'synced_at' => date('Y-m-d H:i:s')
        );

        // 重複チェック：既存のIDと一致する場合は更新
        if (isset($existingIds[$invoiceId])) {
            // 既存データを更新
            for ($i = 0; $i < count($data['mf_invoices']); $i++) {
                if ($data['mf_invoices'][$i]['id'] === $invoiceId) {
                    // created_atは保持
                    $invoiceData['created_at'] = $data['mf_invoices'][$i]['created_at'] ?? date('Y-m-d H:i:s');
                    $data['mf_invoices'][$i] = $invoiceData;
                    $updateCount++;
                    break;
                }
            }
        } else {
            // 新規追加
            $invoiceData['created_at'] = date('Y-m-d H:i:s');
            $data['mf_invoices'][] = $invoiceData;
            $newCount++;
        }
    }

    // 同期時刻を記録
    $data['mf_sync_timestamp'] = date('Y-m-d H:i:s');

    // データ保存
    echo "\nデータ保存中...\n";
    saveData($data);
    echo "保存完了\n\n";

    // 結果サマリー
    echo "=== 同期結果 ===\n";
    echo "取得した請求書数: " . count($invoices) . "件\n";
    echo "新規追加: {$newCount}件\n";
    echo "更新: {$updateCount}件\n";
    echo "合計請求書数: " . count($data['mf_invoices']) . "件\n";
    echo "同期時刻: " . $data['mf_sync_timestamp'] . "\n";
    echo "\n✅ 同期が完了しました！\n";

} catch (Exception $e) {
    echo "\n❌ エラーが発生しました:\n";
    echo $e->getMessage() . "\n";
    echo "\nスタックトレース:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
