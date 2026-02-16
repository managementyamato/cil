<?php
/**
 * タグマッチングテストスクリプト
 *
 * 使い方:
 * php scripts/test-tag-matching.php
 */

// CLI実行のみ許可
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('このスクリプトはコマンドラインからのみ実行できます');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/recurring-invoice.php';
require_once __DIR__ . '/../api/mf-api.php';

echo "=== タグマッチングテスト ===\n\n";

// MF API設定チェック
if (!MFApiClient::isConfigured()) {
    echo "❌ エラー: MFクラウド請求書APIが設定されていません\n";
    echo "   環境変数またはconfig/mf-accounting-config.jsonを確認してください\n";
    exit(1);
}

// CSVから請求書IDを読み込み
try {
    echo "📋 CSVファイルを読み込み中...\n";
    $invoiceList = loadRecurringInvoiceList();
    echo "   登録件数: " . count($invoiceList) . "件\n\n";

    if (empty($invoiceList)) {
        echo "⚠️  CSVファイルに有効な請求書IDが見つかりません\n";
        exit(0);
    }
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}

// MF APIクライアント
$client = new MFApiClient();

// 各請求書のタグをチェック
foreach ($invoiceList as $index => $invoice) {
    $num = $index + 1;
    $templateId = $invoice['mf_billing_id'];
    $note = $invoice['note'];

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "[{$num}/" . count($invoiceList) . "] テンプレートID: {$templateId}";
    if ($note) {
        echo " ({$note})";
    }
    echo "\n";

    try {
        // 請求書の詳細を取得
        $template = $client->getInvoiceDetail($templateId);

        if (!isset($template['billing'])) {
            echo "❌ 請求書が見つかりません\n\n";
            continue;
        }

        $billing = $template['billing'];
        $tags = $billing['tags'] ?? [];

        echo "\n📌 設定されているタグ:\n";
        if (empty($tags)) {
            echo "   （タグなし）\n";
        } else {
            foreach ($tags as $tag) {
                echo "   - " . ($tag['name'] ?? '(名前なし)') . "\n";
            }
        }

        // 「指定フォーマット」タグチェック
        echo "\n🔍 判定結果:\n";
        $hasRecurringTag = false;
        foreach ($tags as $tag) {
            if (strpos($tag['name'] ?? '', '指定フォーマット') !== false) {
                $hasRecurringTag = true;
                break;
            }
        }

        if ($hasRecurringTag) {
            echo "   ✅ 「指定フォーマット」タグ: あり\n";
        } else {
            echo "   ❌ 「指定フォーマット」タグ: なし → この請求書はスキップされます\n";
        }

        // 締め日タグチェック
        $dates = calculateDatesFromTags($tags);

        if ($dates['closing_type']) {
            echo "   ✅ 締め日タグ: {$dates['closing_type']}\n";
            echo "   📅 請求日: {$dates['billing_date']}\n";
            echo "   📅 支払期限: {$dates['due_date']}\n";
        } else {
            echo "   ⚠️  締め日タグ: なし → テンプレートの日付を使用\n";
            echo "   📅 テンプレート請求日: {$billing['billing_date']}\n";
            echo "   📅 テンプレート支払期限: {$billing['due_date']}\n";
        }

        // 処理可否の判定
        echo "\n🎯 処理判定:\n";
        if ($hasRecurringTag) {
            echo "   ✅ この請求書は処理対象です\n";
            if ($dates['closing_type']) {
                echo "   → {$dates['closing_type']}のルールで日付を自動調整して作成されます\n";
            } else {
                echo "   → テンプレートの日付のまま作成されます\n";
            }
        } else {
            echo "   ❌ この請求書はスキップされます\n";
            echo "   → 「指定フォーマット」タグを追加してください\n";
        }

    } catch (Exception $e) {
        echo "❌ エラー: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n📊 サマリー:\n";
echo "   登録されている請求書: " . count($invoiceList) . "件\n";
echo "\n💡 タグの設定方法:\n";
echo "   1. MFクラウド請求書で各請求書を開く\n";
echo "   2. 必須タグ「指定フォーマット」を追加\n";
echo "   3. 任意タグ「20日〆」「15日〆」「末〆」のいずれかを追加\n";
echo "\n";
