<?php
// cURL/PHP設定のデバッグページ

echo "<h2>PHP設定チェック</h2>";

echo "<h3>cURL</h3>";
if (function_exists('curl_init')) {
    echo "✓ cURL は利用可能です<br>";

    // cURLのバージョン情報
    $version = curl_version();
    echo "cURL バージョン: " . $version['version'] . "<br>";
    echo "SSL バージョン: " . $version['ssl_version'] . "<br>";
} else {
    echo "✗ cURL は利用できません<br>";
}

echo "<h3>allow_url_fopen</h3>";
if (ini_get('allow_url_fopen')) {
    echo "✓ allow_url_fopen は有効です<br>";
} else {
    echo "✗ allow_url_fopen は無効です<br>";
}

echo "<h3>OpenSSL</h3>";
if (extension_loaded('openssl')) {
    echo "✓ OpenSSL拡張は有効です<br>";
} else {
    echo "✗ OpenSSL拡張は無効です<br>";
}

echo "<h3>cURL テスト（MF APIへの接続確認）</h3>";
if (function_exists('curl_init')) {
    $ch = curl_init('https://api.biz.moneyforward.com/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($result !== false) {
        echo "✓ MF APIへの接続成功 (HTTP " . $httpCode . ")<br>";
    } else {
        echo "✗ MF APIへの接続失敗: " . $error . "<br>";
    }
} else {
    echo "cURLが利用できないためテストできません<br>";
}

echo "<h3>file_get_contents テスト</h3>";
if (ini_get('allow_url_fopen')) {
    $context = stream_context_create(array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false
        )
    ));

    $result = @file_get_contents('https://api.biz.moneyforward.com/', false, $context);

    if ($result !== false) {
        echo "✓ file_get_contentsでの接続成功<br>";
    } else {
        $error = error_get_last();
        echo "✗ file_get_contentsでの接続失敗: " . ($error['message'] ?? '不明') . "<br>";
    }
} else {
    echo "allow_url_fopenが無効のためテストできません<br>";
}
