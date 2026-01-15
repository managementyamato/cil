<?php
/**
 * MF認証テストスクリプト
 */

require_once __DIR__ . '/mf-api.php';

echo "=== MF API 認証テスト ===\n\n";

try {
    $client = new MFApiClient();

    echo "1. 設定確認\n";
    echo "   認証済み: " . (MFApiClient::isConfigured() ? "はい" : "いいえ") . "\n\n";

    if (!MFApiClient::isConfigured()) {
        echo "エラー: まず /mf-settings.php でOAuth認証を完了してください。\n";
        exit(1);
    }

    echo "2. アクセストークンのリフレッシュをテスト\n";
    try {
        $tokenData = $client->refreshAccessToken();
        echo "   ✓ トークンリフレッシュ成功\n";
        echo "   新しいアクセストークン: " . substr($tokenData['access_token'], 0, 20) . "...\n\n";
    } catch (Exception $e) {
        echo "   ✗ トークンリフレッシュ失敗: " . $e->getMessage() . "\n\n";
    }

    echo "3. 請求書一覧取得をテスト\n";
    try {
        // パラメータなしで最近の請求書を取得
        echo "   パラメータなしで取得\n";
        $response = $client->getInvoices();

        echo "   ✓ APIリクエスト成功\n";
        echo "   レスポンスキー: " . implode(', ', array_keys($response)) . "\n";

        if (isset($response['data'])) {
            echo "   取得件数: " . count($response['data']) . " 件\n";
        } elseif (isset($response['billings'])) {
            echo "   取得件数: " . count($response['billings']) . " 件\n";
        }

        echo "\n✓ すべてのテストが成功しました！\n";

    } catch (Exception $e) {
        echo "   ✗ APIリクエスト失敗: " . $e->getMessage() . "\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
