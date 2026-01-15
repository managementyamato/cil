<?php
/**
 * PHP環境診断スクリプト
 * ローカル環境でのMF連携に必要な設定を確認
 */

echo "<h2>PHP環境診断</h2>";
echo "<style>
    body { font-family: sans-serif; padding: 20px; }
    .ok { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    table { border-collapse: collapse; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";

// 1. PHPバージョン
echo "<h3>1. PHPバージョン</h3>";
echo "<p>バージョン: " . PHP_VERSION . "</p>";

// 2. allow_url_fopen の確認
echo "<h3>2. allow_url_fopen（必須）</h3>";
$allow_url_fopen = ini_get('allow_url_fopen');
if ($allow_url_fopen) {
    echo "<p class='ok'>✓ 有効</p>";
} else {
    echo "<p class='error'>✗ 無効（MF連携に必須！）</p>";
    echo "<p>解決方法: php.ini で allow_url_fopen = On に設定</p>";
}

// 3. OpenSSL拡張
echo "<h3>3. OpenSSL拡張（HTTPS通信に必須）</h3>";
if (extension_loaded('openssl')) {
    echo "<p class='ok'>✓ 有効</p>";
    echo "<p>バージョン: " . OPENSSL_VERSION_TEXT . "</p>";
} else {
    echo "<p class='error'>✗ 無効（HTTPS通信に必須！）</p>";
    echo "<p>解決方法: php.ini で extension=openssl を有効化</p>";
}

// 4. cURL拡張（オプション）
echo "<h3>4. cURL拡張（オプション）</h3>";
if (extension_loaded('curl')) {
    echo "<p class='ok'>✓ 有効</p>";
    $curl_version = curl_version();
    echo "<p>バージョン: " . $curl_version['version'] . "</p>";
    echo "<p>SSL: " . $curl_version['ssl_version'] . "</p>";
} else {
    echo "<p class='warning'>△ 無効（file_get_contents で代替可能）</p>";
}

// 5. ストリームラッパー
echo "<h3>5. ストリームラッパー</h3>";
$wrappers = stream_get_wrappers();
echo "<table>";
echo "<tr><th>プロトコル</th><th>状態</th></tr>";
foreach (['http', 'https', 'ftp', 'file'] as $protocol) {
    $status = in_array($protocol, $wrappers) ?
        "<span class='ok'>✓ 有効</span>" :
        "<span class='error'>✗ 無効</span>";
    echo "<tr><td>$protocol://</td><td>$status</td></tr>";
}
echo "</table>";

// 6. 実際のHTTPSテスト
echo "<h3>6. HTTPS接続テスト</h3>";

// テスト1: シンプルなHTTPS接続
echo "<h4>テスト1: Google へのHTTPS接続</h4>";
$test_url = "https://www.google.com";
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$result = @file_get_contents($test_url, false, $context);
if ($result !== false) {
    echo "<p class='ok'>✓ 成功（" . strlen($result) . " バイト取得）</p>";
} else {
    echo "<p class='error'>✗ 失敗</p>";
    $error = error_get_last();
    echo "<pre>" . print_r($error, true) . "</pre>";
}

// テスト2: MoneyForward API エンドポイント
echo "<h4>テスト2: MoneyForward API エンドポイント</h4>";
$mf_url = "https://api.biz.moneyforward.com/";
$result2 = @file_get_contents($mf_url, false, $context);
if ($result2 !== false) {
    echo "<p class='ok'>✓ 接続成功</p>";
} else {
    echo "<p class='error'>✗ 接続失敗</p>";
    $error2 = error_get_last();
    echo "<pre>" . print_r($error2, true) . "</pre>";
}

// 7. php.ini の場所
echo "<h3>7. PHP設定ファイル</h3>";
echo "<p>php.ini の場所: " . php_ini_loaded_file() . "</p>";
$additional = php_ini_scanned_files();
if ($additional) {
    echo "<p>追加の設定ファイル: " . $additional . "</p>";
}

// 8. 重要な設定値
echo "<h3>8. 重要な設定値</h3>";
echo "<table>";
$important_settings = [
    'allow_url_fopen',
    'allow_url_include',
    'max_execution_time',
    'default_socket_timeout',
    'user_agent',
    'extension_dir'
];

foreach ($important_settings as $setting) {
    $value = ini_get($setting);
    echo "<tr><td>$setting</td><td>" . ($value ?: '(未設定)') . "</td></tr>";
}
echo "</table>";

// 9. ロードされている拡張
echo "<h3>9. ロードされている拡張モジュール</h3>";
$extensions = get_loaded_extensions();
sort($extensions);
echo "<p>合計: " . count($extensions) . " 個</p>";
echo "<details><summary>拡張一覧を表示</summary>";
echo "<ul>";
foreach ($extensions as $ext) {
    echo "<li>$ext</li>";
}
echo "</ul>";
echo "</details>";

// 10. 推奨事項
echo "<h3>10. 推奨事項</h3>";
$issues = [];

if (!ini_get('allow_url_fopen')) {
    $issues[] = "allow_url_fopen を有効にしてください";
}
if (!extension_loaded('openssl')) {
    $issues[] = "OpenSSL拡張を有効にしてください";
}
if (!in_array('https', stream_get_wrappers())) {
    $issues[] = "HTTPSストリームラッパーが利用できません";
}

if (empty($issues)) {
    echo "<p class='ok'>✓ すべての必須要件を満たしています！</p>";
} else {
    echo "<p class='error'>以下の問題を解決する必要があります:</p>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li class='error'>$issue</li>";
    }
    echo "</ul>";
}

// Windows環境での php.ini 編集方法
if (PHP_OS_FAMILY === 'Windows') {
    echo "<h3>Windows環境での修正方法</h3>";
    echo "<ol>";
    echo "<li>php.ini ファイルを開く（上記の場所を参照）</li>";
    echo "<li>以下の行を探して、先頭の ; を削除（コメント解除）:</li>";
    echo "<pre>";
    echo ";extension=openssl\n";
    echo "↓\n";
    echo "extension=openssl\n";
    echo "</pre>";
    echo "<li>以下の設定を確認:</li>";
    echo "<pre>allow_url_fopen = On</pre>";
    echo "<li>PHPサーバーを再起動</li>";
    echo "</ol>";
}
