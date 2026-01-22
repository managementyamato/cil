<?php
/**
 * スプレッドシート同期管理ページ
 */
require_once '../config/config.php';

// 権限チェック（管理者のみ）
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = '';

// 同期実行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync'])) {
    $spreadsheetUrl = $_POST['spreadsheet_url'] ?? '';

    if (empty($spreadsheetUrl)) {
        $message = 'スプレッドシートのURLを入力してください';
        $messageType = 'error';
    } else {
        // 設定を保存
        $data = getData();
        $data['settings']['trouble_spreadsheet_url'] = $spreadsheetUrl;
        saveData($data);

        // 同期実行
        try {
            require_once './google-sheets-config.php';
            $result = syncTroublesFromSheet($spreadsheetUrl);

            if ($result['success']) {
                $message = $result['message'];
                $messageType = 'success';
            } else {
                $message = $result['message'];
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = 'エラー: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// 現在の設定を取得
$data = getData();
$currentUrl = $data['settings']['trouble_spreadsheet_url'] ?? '';
$troubleCount = count($data['troubles'] ?? array());
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>トラブル対応 - スプレッドシート同期</title>
    <link rel="stylesheet" href="/style.css">
    <style>
        .sync-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        .sync-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        .form-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn-sync {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-sync:hover {
            background: #45a049;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info-box {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #2196F3;
            margin-bottom: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2196F3;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .setup-guide {
            background: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #ffc107;
            margin-bottom: 20px;
        }
        .setup-guide h3 {
            margin-top: 0;
            color: #856404;
        }
        .setup-guide ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .setup-guide li {
            margin: 5px 0;
            color: #856404;
        }
        .btn-back {
            display: inline-block;
            padding: 8px 16px;
            background: #f5f5f5;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .btn-back:hover {
            background: #e0e0e0;
        }
    </style>
</head>
<body>
    <?php include '../functions/header.php'; ?>

    <div class="sync-container">
        <a href="troubles.php" class="btn-back">← トラブル対応一覧に戻る</a>

        <h1>トラブル対応データ - スプレッドシート同期</h1>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $troubleCount; ?></div>
                <div class="stat-label">登録済みトラブル件数</div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!file_exists(__DIR__ . '/vendor/autoload.php')): ?>
            <div class="setup-guide">
                <h3>セットアップが必要です</h3>
                <p>Google Sheets APIを使用するには、以下の手順でセットアップしてください:</p>
                <ol>
                    <li>Composerをインストール（まだの場合）</li>
                    <li>プロジェクトディレクトリで以下のコマンドを実行:
                        <code style="display:block;background:#f5f5f5;padding:10px;margin:10px 0;">
                            composer require google/apiclient:^2.0
                        </code>
                    </li>
                    <li>Google Cloud Consoleでプロジェクトを作成</li>
                    <li>Google Sheets APIを有効化</li>
                    <li>サービスアカウントを作成してJSONキーをダウンロード</li>
                    <li>ダウンロードしたJSONファイルを <code>credentials.json</code> として保存</li>
                    <li>スプレッドシートをサービスアカウントのメールアドレスと共有</li>
                </ol>
            </div>
        <?php elseif (!file_exists(__DIR__ . '/credentials.json')): ?>
            <div class="setup-guide">
                <h3>認証情報が必要です</h3>
                <p>Google Cloud Consoleからサービスアカウントキーをダウンロードし、<br>
                <code>credentials.json</code> としてこのディレクトリに保存してください。</p>
            </div>
        <?php endif; ?>

        <div class="sync-form">
            <form method="POST">
                <div class="info-box">
                    <strong>スプレッドシート同期について</strong>
                    <p>既存のスプレッドシートからトラブル対応データを読み込みます。<br>
                    同期を実行すると、スプレッドシートのデータがシステムに追加されます。</p>
                </div>

                <div class="form-group">
                    <label for="spreadsheet_url">スプレッドシートURL</label>
                    <input type="text"
                           id="spreadsheet_url"
                           name="spreadsheet_url"
                           value="<?php echo htmlspecialchars($currentUrl); ?>"
                           placeholder="https://docs.google.com/spreadsheets/d/YOUR_SPREADSHEET_ID/edit"
                           required>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        スプレッドシートのURLを貼り付けてください
                    </small>
                </div>

                <button type="submit" name="sync" class="btn-sync">
                    同期を実行
                </button>
            </form>
        </div>

        <div class="info-box" style="margin-top: 20px;">
            <strong>注意事項</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>同期を実行すると、スプレッドシートのデータが既存のデータに追加されます</li>
                <li>重複したデータが作成される可能性があるため、初回のみの実行を推奨します</li>
                <li>スプレッドシートの形式は以下の列順である必要があります:<br>
                    A:現場名, B:トラブル内容, C:対応内容, D:記入者, E:対応者, F:状態, G:日付, H:コールNo, I:プロジェクトコンタクト, J:案件No, K:社名, L:お客様お名前, M:様
                </li>
            </ul>
        </div>
    </div>
</body>
</html>
