<?php
/**
 * ローカル環境初期化スクリプト
 * 初回セットアップ時に必要なファイルを作成します
 */

echo "=== ローカル環境初期化スクリプト ===\n\n";

// 1. data.jsonの作成
$dataFile = __DIR__ . '/data.json';
if (!file_exists($dataFile)) {
    echo "data.json を作成中...\n";
    $initialData = array(
        'projects' => array(),
        'troubles' => array(),
        'customers' => array(),
        'partners' => array(),
        'employees' => array(),
        'products' => array(),
        'mf_invoices' => array(),
        'mf_sync_timestamp' => null
    );
    file_put_contents($dataFile, json_encode($initialData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "✅ data.json を作成しました\n\n";
} else {
    echo "data.json は既に存在します\n\n";
}

// 2. users.jsonの作成
$usersFile = __DIR__ . '/users.json';
if (!file_exists($usersFile)) {
    echo "users.json を作成中...\n";
    $initialUsers = array();
    file_put_contents($usersFile, json_encode($initialUsers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "✅ users.json を作成しました\n\n";
} else {
    echo "users.json は既に存在します\n\n";
}

// 3. mf-config.json の確認
$mfConfigFile = __DIR__ . '/mf-config.json';
$mfConfigExample = __DIR__ . '/mf-config.json.example';

if (!file_exists($mfConfigFile)) {
    if (file_exists($mfConfigExample)) {
        echo "mf-config.json.example から mf-config.json を作成中...\n";
        copy($mfConfigExample, $mfConfigFile);
        echo "✅ mf-config.json を作成しました\n";
        echo "   MF連携を使用する場合は、このファイルにClient IDとClient Secretを設定してください\n\n";
    } else {
        echo "mf-config.json を作成中...\n";
        $mfConfig = array(
            'client_id' => '',
            'client_secret' => '',
            'access_token' => null,
            'refresh_token' => null,
            'updated_at' => null,
            'expires_in' => 3600,
            'token_obtained_at' => null
        );
        file_put_contents($mfConfigFile, json_encode($mfConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "✅ mf-config.json を作成しました\n";
        echo "   MF連携を使用する場合は、このファイルにClient IDとClient Secretを設定してください\n\n";
    }
} else {
    echo "mf-config.json は既に存在します\n\n";
}

// 4. .htaccessの作成（オプション）
$htaccessFile = __DIR__ . '/.htaccess';
if (!file_exists($htaccessFile)) {
    echo ".htaccess を作成中...\n";
    $htaccess = <<<'HTACCESS'
# セキュリティ設定
<Files "data.json">
    Require all denied
</Files>
<Files "users.json">
    Require all denied
</Files>
<Files "mf-config.json">
    Require all denied
</Files>

# PHPエラー表示（開発環境用）
php_flag display_errors on
php_value error_reporting E_ALL
HTACCESS;
    file_put_contents($htaccessFile, $htaccess);
    echo "✅ .htaccess を作成しました\n\n";
} else {
    echo ".htaccess は既に存在します\n\n";
}

echo "=== 初期化完了 ===\n";
echo "\n次のステップ:\n";
echo "1. ブラウザで http://localhost:8000 にアクセス\n";
echo "2. ユーザー登録画面が表示されるので、アカウントを作成\n";
echo "3. ログイン後、システムを使い始めることができます\n\n";
echo "MF連携を使用する場合:\n";
echo "1. mf-config.json に Client ID と Client Secret を設定\n";
echo "2. http://localhost:8000/mf-settings.php でOAuth認証を実行\n";
