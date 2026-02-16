<?php
/**
 * 定期請求書作成cronスクリプト
 *
 * 使い方:
 * 毎月1日の午前9時に実行する場合（crontab）:
 * 0 9 1 * * cd /path/to/project && php scripts/cron-recurring-invoices.php
 *
 * Windows タスクスケジューラの場合:
 * プログラム: C:\xampp\php\php.exe
 * 引数: C:\Claude\master\scripts\cron-recurring-invoices.php
 * トリガー: 毎月1日 09:00
 */

// CLI実行のみ許可
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('このスクリプトはコマンドラインからのみ実行できます');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/recurring-invoice.php';
require_once __DIR__ . '/../functions/logger.php';

echo "=== 定期請求書作成 開始 ===\n";
echo "実行日時: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // MF API設定チェック
    if (!MFApiClient::isConfigured()) {
        $error = 'MFクラウド請求書APIが設定されていません。環境変数またはconfig/mf-accounting-config.jsonを確認してください。';
        echo "❌ エラー: {$error}\n";
        logError('定期請求書cron実行失敗', ['error' => $error]);
        exit(1);
    }

    // 定期請求書リストを読み込み
    echo "📋 CSVファイルを読み込み中...\n";
    $invoiceList = loadRecurringInvoiceList();
    echo "   登録件数: " . count($invoiceList) . "件\n\n";

    if (empty($invoiceList)) {
        echo "⚠️  CSVファイルに有効な請求書IDが見つかりません\n";
        exit(0);
    }

    // 各請求書を作成
    $client = new MFApiClient();
    $successCount = 0;
    $failedCount = 0;

    foreach ($invoiceList as $index => $invoice) {
        $num = $index + 1;
        $templateId = $invoice['mf_billing_id'];
        $note = $invoice['note'];

        echo "[{$num}/" . count($invoiceList) . "] テンプレートID: {$templateId}";
        if ($note) {
            echo " ({$note})";
        }
        echo "\n";

        $result = createInvoiceFromTemplate($client, $templateId, $note);

        if ($result['success']) {
            $successCount++;
            $closingType = $result['closing_type'] ? "[{$result['closing_type']}] " : '';
            echo "   ✅ 成功: {$closingType}{$result['message']}\n";
            echo "      請求書番号: {$result['billing_number']}\n";
            echo "      請求日: {$result['billing_date']}, 支払期限: {$result['due_date']}\n";
            echo "      金額: ¥" . number_format($result['total_price']) . "\n";
        } else {
            $failedCount++;
            echo "   ❌ 失敗: {$result['message']}\n";
        }

        echo "\n";

        // API負荷を考慮して少し待機
        if ($index < count($invoiceList) - 1) {
            sleep(2);
        }
    }

    echo "=== 定期請求書作成 完了 ===\n";
    echo "成功: {$successCount}件\n";
    echo "失敗: {$failedCount}件\n";
    echo "合計: " . count($invoiceList) . "件\n";

    logInfo('定期請求書cron実行完了', [
        'total' => count($invoiceList),
        'success' => $successCount,
        'failed' => $failedCount
    ]);

    // 失敗があった場合はエラーコードで終了
    exit($failedCount > 0 ? 1 : 0);

} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";

    logException($e, '定期請求書cron実行');
    exit(1);
}
