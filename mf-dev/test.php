<?php
/**
 * 開発環境用 MF API テストページ
 */

// 開発用の設定ファイルパス
define('DEV_MF_CONFIG_FILE', __DIR__ . '/mf-config.json');

// mf-api.phpを読み込み（親ディレクトリから）
require_once __DIR__ . '/../mf-api.php';

// 設定の読み込み
function loadDevConfig() {
    if (file_exists(DEV_MF_CONFIG_FILE)) {
        $json = file_get_contents(DEV_MF_CONFIG_FILE);
        return json_decode($json, true) ?: array();
    }
    return array();
}

$config = loadDevConfig();
$isAuthenticated = !empty($config['access_token']);

$invoices = array();
$error = '';

if ($isAuthenticated && isset($_GET['action']) && $_GET['action'] === 'fetch') {
    try {
        $client = new MFApiClient(DEV_MF_CONFIG_FILE);

        // 今月の請求書を取得
        $from = date('Y-m-01');
        $to = date('Y-m-t');
        $invoices = $client->getAllInvoices($from, $to);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MF API テスト - 開発環境</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            background: #3182ce;
            color: white;
            border: none;
            cursor: pointer;
        }
        .alert-danger {
            background: #fed7d7;
            color: #742a2a;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 0.5rem;
            text-align: left;
        }
        th {
            background: #f7fafc;
        }
        pre {
            background: #f7fafc;
            padding: 1rem;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🧪 MF API テスト - 開発環境</h1>

    <?php if (!$isAuthenticated): ?>
        <div class="card">
            <p>先に <a href="mf-settings.php">OAuth認証</a> を完了してください。</p>
        </div>
    <?php else: ?>
        <div class="card">
            <p><a href="?action=fetch" class="btn">今月の請求書を取得</a></p>
        </div>

        <?php if ($error): ?>
            <div class="alert-danger">
                エラー: <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($invoices)): ?>
            <div class="card">
                <h2>請求書一覧 (<?= count($invoices) ?>件)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>請求番号</th>
                            <th>取引先</th>
                            <th>件名</th>
                            <th>請求日</th>
                            <th>ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><?= htmlspecialchars($invoice['billing_number'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($invoice['partner_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($invoice['title'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($invoice['billing_date'] ?? '-') ?></td>
                                <td style="font-size: 0.75rem;"><?= htmlspecialchars($invoice['id'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2>レスポンス詳細 (最初の1件)</h2>
                <pre><?= htmlspecialchars(json_encode($invoices[0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
