<?php
/**
 * 定期請求書作成機能
 * CSVに記載されたMF請求書IDをテンプレートとして、新しい請求書を作成
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/mf-api.php';
require_once __DIR__ . '/../functions/audit-log.php';
require_once __DIR__ . '/../functions/logger.php';

/**
 * CSVファイルから定期請求書リストを読み込む
 *
 * @return array [['mf_billing_id' => '123456', 'note' => 'A社'], ...]
 * @throws Exception CSVファイルが存在しない、または読み込めない場合
 */
function loadRecurringInvoiceList() {
    $csvPath = __DIR__ . '/../config/recurring-invoices.csv';

    if (!file_exists($csvPath)) {
        throw new Exception('定期請求書設定ファイルが見つかりません: ' . $csvPath);
    }

    $file = fopen($csvPath, 'r');
    if (!$file) {
        throw new Exception('CSVファイルを開けませんでした');
    }

    $invoices = [];
    $isFirstRow = true;

    while (($row = fgetcsv($file)) !== false) {
        // ヘッダー行をスキップ
        if ($isFirstRow) {
            $isFirstRow = false;
            continue;
        }

        // 空行をスキップ
        if (empty($row[0])) {
            continue;
        }

        $invoices[] = [
            'mf_billing_id' => trim($row[0]),
            'note' => $row[1] ?? ''
        ];
    }

    fclose($file);
    return $invoices;
}

/**
 * MFのタグに基づいて請求日・支払期限を計算
 *
 * タグのルール（締め日による判定）:
 * - "20日〆" → 請求日: 当月20日、支払期限: 翌月末日
 * - "15日〆" → 請求日: 当月15日、支払期限: 翌月末日
 * - "末日〆" or "末〆" → 請求日: 当月末日、支払期限: 翌月末日
 * - タグがない場合 → テンプレートの日付をそのまま使用
 *
 * @param array $tags タグ配列 [['name' => 'タグ名'], ...]
 * @param string|null $targetMonth 対象月 (Y-m形式、nullの場合は当月)
 * @return array ['billing_date' => 'Y-m-d', 'due_date' => 'Y-m-d', 'closing_type' => string|null]
 */
function calculateDatesFromTags($tags, $targetMonth = null) {
    $billingDate = null;
    $dueDate = null;
    $closingType = null;

    // 対象月が指定されていない場合は当月
    if (!$targetMonth) {
        $targetMonth = date('Y-m');
    }

    $baseDate = $targetMonth . '-01';

    foreach ($tags as $tagName) {
        // タグは文字列配列なのでそのまま使用

        // 20日〆
        if (strpos($tagName, '20日〆') !== false) {
            $closingType = '20日〆';
            // 指定月20日
            $billingDate = date('Y-m-20', strtotime($baseDate));
            // 翌月末日
            $dueDate = date('Y-m-t', strtotime($baseDate . ' +1 month'));
            break;
        }

        // 15日〆
        if (strpos($tagName, '15日〆') !== false) {
            $closingType = '15日〆';
            // 指定月15日
            $billingDate = date('Y-m-15', strtotime($baseDate));
            // 翌月末日
            $dueDate = date('Y-m-t', strtotime($baseDate . ' +1 month'));
            break;
        }

        // 末〆（末日〆も含む）
        if (strpos($tagName, '末日〆') !== false || strpos($tagName, '末〆') !== false) {
            $closingType = strpos($tagName, '末日〆') !== false ? '末日〆' : '末〆';
            // 指定月末日
            $billingDate = date('Y-m-t', strtotime($baseDate));
            // 翌月末日
            $dueDate = date('Y-m-t', strtotime($baseDate . ' +1 month'));
            break;
        }
    }

    return [
        'billing_date' => $billingDate,
        'due_date' => $dueDate,
        'closing_type' => $closingType
    ];
}

/**
 * テンプレート請求書から新しい請求書を作成
 *
 * @param MFApiClient $client MF APIクライアント
 * @param string $templateBillingId テンプレートとなるMF請求書ID
 * @param string|null $note 備考（ログ用）
 * @return array ['success' => bool, 'new_billing_id' => string, 'message' => string]
 */
function createInvoiceFromTemplate($client, $templateBillingId, $note = null, $targetMonth = null) {
    try {
        // テンプレート請求書の詳細を取得
        $template = $client->getInvoiceDetail($templateBillingId);

        if (!isset($template['billing'])) {
            return [
                'success' => false,
                'message' => "テンプレート請求書が見つかりません (ID: {$templateBillingId})"
            ];
        }

        $billing = $template['billing'];

        // タグを取得（tag_names は文字列配列）
        $tagNames = $billing['tag_names'] ?? [];

        // 「指定フォーマット」タグが付いているかチェック
        $hasRecurringTag = false;
        foreach ($tagNames as $tagName) {
            if (strpos($tagName, '指定フォーマット') !== false) {
                $hasRecurringTag = true;
                break;
            }
        }

        if (!$hasRecurringTag) {
            return [
                'success' => false,
                'message' => "「指定フォーマット」タグが付いていません (ID: {$templateBillingId})"
            ];
        }

        // タグから日付ルールを判定（対象月を指定）
        $dates = calculateDatesFromTags($tagNames, $targetMonth);

        // 請求書データを構築
        $newInvoiceData = [
            'partner_code' => $billing['partner_code'],
            'billing_date' => $dates['billing_date'] ?? $billing['billing_date'],
            'due_date' => $dates['due_date'] ?? $billing['due_date'],
            'title' => $billing['title'],
            'note' => $billing['note'],
            'items' => []
        ];

        // 明細をコピー
        foreach ($billing['items'] as $item) {
            $newInvoiceData['items'][] = [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'excise' => $item['excise'] ?? 'ten_percent'
            ];
        }

        // 新しい請求書を作成
        $result = $client->createInvoice($newInvoiceData);

        if (isset($result['billing']['id'])) {
            $newBillingId = $result['billing']['id'];
            $newBillingNumber = $result['billing']['billing_number'] ?? '';
            $totalPrice = $result['billing']['total_price'] ?? 0;

            // 監査ログに記録
            auditCreate(
                'recurring_invoices',
                $newBillingId,
                "定期請求書を作成 (テンプレート: {$templateBillingId})",
                [
                    'template_billing_id' => $templateBillingId,
                    'new_billing_id' => $newBillingId,
                    'billing_number' => $newBillingNumber,
                    'partner_code' => $newInvoiceData['partner_code'],
                    'billing_date' => $newInvoiceData['billing_date'],
                    'due_date' => $newInvoiceData['due_date'],
                    'closing_type' => $dates['closing_type'],
                    'total_price' => $totalPrice,
                    'note' => $note
                ]
            );

            logInfo('定期請求書作成成功', [
                'template_id' => $templateBillingId,
                'new_id' => $newBillingId,
                'billing_number' => $newBillingNumber,
                'closing_type' => $dates['closing_type'],
                'note' => $note
            ]);

            return [
                'success' => true,
                'new_billing_id' => $newBillingId,
                'billing_number' => $newBillingNumber,
                'total_price' => $totalPrice,
                'billing_date' => $newInvoiceData['billing_date'],
                'due_date' => $newInvoiceData['due_date'],
                'closing_type' => $dates['closing_type'],
                'message' => "請求書を作成しました (No.{$newBillingNumber})"
            ];
        } else {
            return [
                'success' => false,
                'message' => 'MF APIから請求書IDが返されませんでした'
            ];
        }

    } catch (Exception $e) {
        logError('定期請求書作成エラー', [
            'template_id' => $templateBillingId,
            'error' => $e->getMessage()
        ]);

        return [
            'success' => false,
            'message' => '請求書作成エラー: ' . $e->getMessage()
        ];
    }
}

/**
 * 全ての定期請求書を一括作成
 *
 * @return array ['success' => int, 'failed' => int, 'results' => array]
 */
function createAllRecurringInvoices($targetMonth = null) {
    try {
        // MF API設定チェック
        if (!MFApiClient::isConfigured()) {
            throw new Exception('MFクラウド請求書APIが設定されていません');
        }

        $client = new MFApiClient();

        // CSVから定期請求書リストを読み込み
        $invoiceList = loadRecurringInvoiceList();

        if (empty($invoiceList)) {
            return [
                'success' => 0,
                'failed' => 0,
                'results' => [],
                'message' => 'CSVファイルに有効な請求書IDが見つかりません'
            ];
        }

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        // 各テンプレート請求書から新規作成
        foreach ($invoiceList as $invoice) {
            $result = createInvoiceFromTemplate(
                $client,
                $invoice['mf_billing_id'],
                $invoice['note'],
                $targetMonth
            );

            $results[] = array_merge($result, [
                'template_id' => $invoice['mf_billing_id'],
                'note' => $invoice['note']
            ]);

            if ($result['success']) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        logInfo('定期請求書一括作成完了', [
            'total' => count($invoiceList),
            'success' => $successCount,
            'failed' => $failedCount
        ]);

        return [
            'success' => $successCount,
            'failed' => $failedCount,
            'total' => count($invoiceList),
            'results' => $results,
            'message' => "{$successCount}件の請求書を作成しました"
        ];

    } catch (Exception $e) {
        logException($e, '定期請求書一括作成');

        return [
            'success' => 0,
            'failed' => 0,
            'results' => [],
            'message' => 'エラー: ' . $e->getMessage()
        ];
    }
}
