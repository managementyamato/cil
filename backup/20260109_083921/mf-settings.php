<?php
require_once 'config.php';
require_once 'mf-api.php';

// 管理者のみアクセス可能
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

// OAuth2認証成功
if (isset($_GET['auth']) && $_GET['auth'] === 'success') {
    $message = 'OAuth2認証に成功しました！';
}

// OAuth2エラー
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Client ID/Secret 保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_credentials'])) {
    $clientId = trim($_POST['client_id'] ?? '');
    $clientSecret = trim($_POST['client_secret'] ?? '');
    $officeId = trim($_POST['office_id'] ?? '');

    if (empty($clientId) || empty($clientSecret)) {
        $error = 'Client IDとClient Secretを入力してください';
    } else {
        // 既存の設定を読み込み
        $configFile = __DIR__ . '/mf-config.json';
        $config = array();
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true) ?: array();
        }

        // 認証情報を保存
        $config['client_id'] = $clientId;
        $config['client_secret'] = $clientSecret;
        $config['office_id'] = $officeId;
        $config['updated_at'] = date('Y-m-d H:i:s');

        file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $message = 'Client IDとClient Secretを保存しました。「OAuth2認証を開始」ボタンをクリックしてください。';
    }
}

// 旧形式：アクセストークン直接入力（非推奨）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $accessToken = trim($_POST['access_token'] ?? '');
    $officeId = trim($_POST['office_id'] ?? '');

    if (empty($accessToken)) {
        $error = 'アクセストークンを入力してください';
    } else {
        // 接続テスト
        $client = new MFApiClient($accessToken);
        $testResult = $client->testConnection();

        if ($testResult['success']) {
            MFApiClient::saveConfig($accessToken, $officeId);
            $message = '設定を保存し、接続テストに成功しました';
        } else {
            $error = '接続テストに失敗しました: ' . $testResult['message'];
        }
    }
}

// 手動同期
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_now'])) {
    if (!MFApiClient::isConfigured()) {
        $error = 'MF APIの設定が完了していません';
    } else {
        try {
            $client = new MFApiClient();

            // 過去3ヶ月分のデータを取得
            $from = date('Y-m-d', strtotime('-3 months'));
            $to = date('Y-m-d');

            $invoices = $client->getInvoices($from, $to);
            $quotes = $client->getQuotes($from, $to);

            $financeData = $client->extractFinanceData($invoices, $quotes);

            // データを保存（実装は後述）
            $data = getData();
            if (!isset($data['mf_sync_history'])) {
                $data['mf_sync_history'] = array();
            }

            $data['mf_sync_history'][] = array(
                'synced_at' => date('Y-m-d H:i:s'),
                'records_count' => count($financeData),
                'from' => $from,
                'to' => $to
            );

            saveData($data);

            $message = count($financeData) . '件のデータを同期しました';
        } catch (Exception $e) {
            $error = '同期エラー: ' . $e->getMessage();
        }
    }
}

// 設定削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_settings'])) {
    $configFile = __DIR__ . '/mf-config.json';
    if (file_exists($configFile)) {
        unlink($configFile);
        $message = '設定を削除しました';
    }
}

// 現在の設定を読み込み
$configFile = __DIR__ . '/mf-config.json';
$currentConfig = array();
if (file_exists($configFile)) {
    $currentConfig = json_decode(file_get_contents($configFile), true) ?: array();
}

$isConfigured = MFApiClient::isConfigured();

require_once 'header.php';
?>

<style>
.mf-settings-container {
    max-width: 900px;
}

.info-box {
    background: #dbeafe;
    color: #1e40af;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.info-box h3 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
}

.info-box ul {
    margin: 0.5rem 0 0 1.5rem;
    padding: 0;
}

.info-box li {
    margin: 0.5rem 0;
}

.status-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 600;
    margin-bottom: 1rem;
}

.status-connected {
    background: #d1fae5;
    color: #065f46;
}

.status-disconnected {
    background: #fee2e2;
    color: #991b1b;
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--gray-200);
}

.form-section:last-child {
    border-bottom: none;
}

.form-section h3 {
    margin: 0 0 1rem 0;
    color: var(--gray-700);
}

.help-text {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin-top: 0.5rem;
}

.danger-zone {
    background: #fef2f2;
    border: 1px solid #fecaca;
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 2rem;
}

.danger-zone h3 {
    color: #991b1b;
    margin: 0 0 1rem 0;
}

.sync-history {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
}

.sync-history-item {
    padding: 0.75rem;
    background: white;
    border-radius: 6px;
    margin-bottom: 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>

<div class="mf-settings-container">
    <h2>マネーフォワード クラウド会計 連携設定</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="status-badge <?= $isConfigured ? 'status-connected' : 'status-disconnected' ?>">
                <?= $isConfigured ? '✓ 接続済み' : '✗ 未接続' ?>
            </div>

            <?php if ($isConfigured && !empty($currentConfig['updated_at'])): ?>
                <p style="color: var(--gray-600); font-size: 0.875rem;">
                    最終更新: <?= htmlspecialchars($currentConfig['updated_at']) ?>
                </p>
            <?php endif; ?>

            <div class="info-box">
                <h3>MF API連携について</h3>
                <p style="margin: 0 0 0.5rem 0;">マネーフォワード クラウド会計のAPIを使用して、以下のデータを自動同期できます：</p>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <li>請求書データ（売上情報）</li>
                    <li>見積書データ（案件情報）</li>
                    <li>取引先情報</li>
                    <li>経費データ（今後実装予定）</li>
                </ul>
            </div>

            <!-- OAuth2認証フォーム（推奨） -->
            <form method="POST" action="">
                <div class="form-section">
                    <h3>🔐 OAuth2認証設定（推奨）</h3>
                    <p style="margin-bottom: 1rem; color: var(--gray-600); font-size: 0.875rem;">
                        安全なOAuth2認証を使用してMFクラウド会計と連携します。
                    </p>

                    <div class="form-group">
                        <label for="client_id">Client ID *</label>
                        <input
                            type="text"
                            class="form-input"
                            id="client_id"
                            name="client_id"
                            value="<?= htmlspecialchars($currentConfig['client_id'] ?? '') ?>"
                            placeholder="MFクラウド会計で発行したClient IDを入力"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="client_secret">Client Secret *</label>
                        <input
                            type="password"
                            class="form-input"
                            id="client_secret"
                            name="client_secret"
                            value="<?= htmlspecialchars($currentConfig['client_secret'] ?? '') ?>"
                            placeholder="MFクラウド会計で発行したClient Secretを入力"
                            required
                        >
                        <div class="help-text">
                            <strong>取得方法:</strong>
                            <ol style="margin: 0.5rem 0 0 1.5rem; padding: 0;">
                                <li>MFクラウド会計にログイン</li>
                                <li>「設定」→「API連携」→「アプリケーションを作成」</li>
                                <li>アプリ名を入力し、リダイレクトURIに以下を設定：<br>
                                    <?php
                                    $baseDir = dirname($_SERVER['PHP_SELF']);
                                    $baseDir = ($baseDir === '/' || $baseDir === '\\') ? '' : $baseDir;
                                    $redirectUriDisplay = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $baseDir . '/mf-callback.php';
                                    ?>
                                    <code style="background: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">
                                        <?= htmlspecialchars($redirectUriDisplay) ?>
                                    </code>
                                </li>
                                <li>「作成」をクリックしてClient IDとClient Secretを取得</li>
                            </ol>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="office_id">事業所ID（オプション）</label>
                        <input
                            type="text"
                            class="form-input"
                            id="office_id"
                            name="office_id"
                            value="<?= htmlspecialchars($currentConfig['office_id'] ?? '') ?>"
                            placeholder="複数事業所がある場合に指定"
                        >
                        <div class="help-text">
                            複数の事業所がある場合、特定の事業所のデータのみ取得できます
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <button type="submit" name="save_credentials" class="btn btn-primary">
                            認証情報を保存
                        </button>

                        <?php if (!empty($currentConfig['client_id']) && !empty($currentConfig['client_secret'])): ?>
                            <a href="mf-callback.php?action=start" class="btn btn-success">
                                🔓 OAuth2認証を開始
                            </a>
                        <?php endif; ?>

                        <?php if ($isConfigured): ?>
                            <button type="submit" name="sync_now" class="btn btn-secondary">
                                今すぐ同期
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- 旧形式：アクセストークン直接入力（非推奨） -->
            <details style="margin-top: 2rem;">
                <summary style="cursor: pointer; color: var(--gray-600); font-size: 0.875rem;">
                    📝 旧形式：アクセストークン直接入力（非推奨）
                </summary>
                <form method="POST" action="" style="margin-top: 1rem;">
                    <div class="form-section">
                        <div class="form-group">
                            <label for="access_token">アクセストークン *</label>
                            <input
                                type="text"
                                class="form-input"
                                id="access_token"
                                name="access_token"
                                value="<?= htmlspecialchars($currentConfig['access_token'] ?? '') ?>"
                                placeholder="MFクラウド会計で発行したアクセストークンを入力"
                                required
                            >
                        </div>

                        <button type="submit" name="save_settings" class="btn btn-secondary">
                            アクセストークンを保存
                        </button>
                    </div>
                </form>
            </details>

            <?php if ($isConfigured): ?>
                <div class="form-section">
                    <h3>同期履歴</h3>
                    <?php
                    $data = getData();
                    $syncHistory = array_reverse($data['mf_sync_history'] ?? array());
                    ?>

                    <?php if (empty($syncHistory)): ?>
                        <p style="color: var(--gray-600);">まだ同期履歴がありません</p>
                    <?php else: ?>
                        <div class="sync-history">
                            <?php foreach (array_slice($syncHistory, 0, 5) as $history): ?>
                                <div class="sync-history-item">
                                    <div>
                                        <strong><?= htmlspecialchars($history['synced_at']) ?></strong>
                                        <div style="font-size: 0.875rem; color: var(--gray-600);">
                                            <?= htmlspecialchars($history['from']) ?> 〜 <?= htmlspecialchars($history['to']) ?>
                                        </div>
                                    </div>
                                    <div style="font-weight: 600; color: var(--primary);">
                                        <?= number_format($history['records_count']) ?>件
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="danger-zone">
                    <h3>危険な操作</h3>
                    <p style="margin: 0 0 1rem 0; color: #991b1b;">
                        API連携設定を削除します。保存されているアクセストークンも削除されます。
                    </p>
                    <form method="POST" action="" onsubmit="return confirm('本当に設定を削除しますか？')">
                        <button type="submit" name="delete_settings" class="btn btn-danger">
                            設定を削除
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
