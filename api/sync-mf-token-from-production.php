<?php
/**
 * 本番環境からMFトークンを同期
 *
 * 本番環境で認証後、このスクリプトを実行してトークンをローカルにコピーします
 */

$productionUrl = 'https://cil.yamato-basic.com/mf-config.json';
$localConfigFile = __DIR__ . '/../config/mf-config.json';

echo "=== 本番環境からMFトークンを同期 ===" . PHP_EOL . PHP_EOL;

echo "本番環境URL: " . $productionUrl . PHP_EOL;

// 本番環境からmf-config.jsonをダウンロード（HTTPSなのでブラウザ経由で手動コピーを案内）
echo PHP_EOL;
echo "【手順】" . PHP_EOL;
echo "1. ブラウザで以下のURLにアクセスしてください：" . PHP_EOL;
echo "   " . $productionUrl . PHP_EOL . PHP_EOL;
echo "2. 表示されたJSON内容をコピーしてください" . PHP_EOL . PHP_EOL;
echo "3. このスクリプトを実行すると、入力を求められます" . PHP_EOL . PHP_EOL;

// ユーザーからJSON入力を受け取る
echo "コピーしたJSON内容を貼り付けてEnterを押してください：" . PHP_EOL;
$input = '';
$line = '';

// 複数行入力を受け付ける
while (($line = fgets(STDIN)) !== false) {
    $input .= $line;
    // JSONが完成したら終了（閉じ括弧を検出）
    if (strpos($input, '}') !== false) {
        break;
    }
}

$input = trim($input);

// JSONバリデーション
$config = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo PHP_EOL . "エラー: 無効なJSON形式です" . PHP_EOL;
    echo "JSONエラー: " . json_last_error_msg() . PHP_EOL;
    exit(1);
}

// 必要なフィールドチェック
$requiredFields = ['client_id', 'client_secret'];
foreach ($requiredFields as $field) {
    if (!isset($config[$field])) {
        echo "エラー: " . $field . " が見つかりません" . PHP_EOL;
        exit(1);
    }
}

// ローカルに保存
if (file_put_contents($localConfigFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
    echo PHP_EOL . "✓ トークンをローカルに保存しました: " . $localConfigFile . PHP_EOL;

    if (isset($config['access_token'])) {
        echo "✓ アクセストークンが含まれています" . PHP_EOL;
    } else {
        echo "! アクセストークンがありません（本番環境でOAuth認証を完了してください）" . PHP_EOL;
    }

    echo PHP_EOL . "同期完了！" . PHP_EOL;
} else {
    echo PHP_EOL . "エラー: ファイルの保存に失敗しました" . PHP_EOL;
    exit(1);
}
