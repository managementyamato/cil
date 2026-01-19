<?php
/**
 * マネーフォワードクラウドAPI接続テスト
 */

require_once 'mf-api.php';

echo "=== マネーフォワードクラウドAPI 接続テスト ===\n\n";

// 1. 設定ファイルの確認
echo "1. 設定ファイルの確認\n";
$configFile = __DIR__ . '/mf-config.json';
if (!file_exists($configFile)) {
    echo "❌ エラー: mf-config.json が見つかりません\n";
    echo "   場所: $configFile\n";
    exit(1);
}

$config = json_decode(file_get_contents($configFile), true);
echo "✅ mf-config.json が見つかりました\n";
echo "   - Client ID: " . (isset($config['client_id']) ? '設定済み' : '未設定') . "\n";
echo "   - Client Secret: " . (isset($config['client_secret']) ? '設定済み' : '未設定') . "\n";
echo "   - Access Token: " . (isset($config['access_token']) ? '設定済み' : '未設定') . "\n";
echo "   - Refresh Token: " . (isset($config['refresh_token']) ? '設定済み' : '未設定') . "\n";
echo "\n";

// 2. MFApiClientの初期化
echo "2. MFApiClientの初期化\n";
try {
    $client = new MFApiClient();
    echo "✅ MFApiClientを初期化しました\n\n";
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. 認証状態の確認
echo "3. 認証状態の確認\n";
if (!MFApiClient::isConfigured()) {
    echo "⚠️  警告: OAuth認証が完了していません\n";
    echo "\n";
    echo "次のステップ:\n";
    echo "1. ブラウザで mf-settings.php にアクセス\n";
    echo "2. Client IDとClient Secretを入力\n";
    echo "3. 'OAuth認証を開始' ボタンをクリック\n";
    echo "4. マネーフォワードクラウドで認証を許可\n";
    echo "\n";
} else {
    echo "✅ OAuth認証が完了しています\n\n";

    // 4. APIテスト（認証済みの場合のみ）
    echo "4. APIテスト\n";
    try {
        echo "   請求書一覧を取得中...\n";
        $invoices = $client->getInvoices();
        echo "✅ APIリクエスト成功\n";

        if (isset($invoices['data'])) {
            $count = count($invoices['data']);
            echo "   取得した請求書数: $count 件\n";
        } elseif (isset($invoices['billings'])) {
            $count = count($invoices['billings']);
            echo "   取得した請求書数: $count 件\n";
        } else {
            echo "   レスポンス構造: " . json_encode(array_keys($invoices)) . "\n";
        }
    } catch (Exception $e) {
        echo "❌ APIリクエストエラー: " . $e->getMessage() . "\n";
    }
}

echo "\n=== テスト完了 ===\n";
