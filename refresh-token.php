<?php
/**
 * MFトークンリフレッシュスクリプト
 */

require_once __DIR__ . '/mf-api.php';

echo "=== MF トークンリフレッシュ ===\n\n";

try {
    // 設定確認
    if (!MFApiClient::isConfigured()) {
        echo "❌ MF APIの設定がありません\n";
        exit(1);
    }

    echo "✓ mf-config.json が存在します\n";

    // 現在の設定を表示
    $config = json_decode(file_get_contents(__DIR__ . '/mf-config.json'), true);
    echo "\n現在の設定:\n";
    echo "- Client ID: " . substr($config['client_id'], 0, 10) . "...\n";
    echo "- 最終更新: " . ($config['updated_at'] ?? '不明') . "\n";

    if (isset($config['token_obtained_at'])) {
        $tokenAge = time() - $config['token_obtained_at'];
        $tokenAgeMinutes = floor($tokenAge / 60);
        echo "- トークン取得: " . $tokenAgeMinutes . "分前\n";
        echo "- 有効期限: " . ($config['expires_in'] ?? 3600) . "秒\n";

        if ($tokenAge > $config['expires_in']) {
            echo "⚠️  トークンが期限切れです\n";
        } else {
            echo "✓ トークンは有効です\n";
        }
    }

    // トークンをリフレッシュ
    echo "\nトークンをリフレッシュ中...\n";
    $client = new MFApiClient();
    $newToken = $client->refreshAccessToken();

    echo "✅ トークンのリフレッシュに成功しました！\n\n";
    echo "新しいトークン情報:\n";
    echo "- Access Token: " . substr($newToken['access_token'], 0, 20) . "...\n";
    echo "- 有効期限: " . ($newToken['expires_in'] ?? 3600) . "秒\n";

} catch (Exception $e) {
    echo "\n❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}
