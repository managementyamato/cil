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

// OAuth設定保存（Client ID/Secretを保存し、認証フローを開始）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_oauth_settings'])) {
    $clientId = trim($_POST['client_id'] ?? '');
    $clientSecret = trim($_POST['client_secret'] ?? '');

    if (empty($clientId) || empty($clientSecret)) {
        $error = 'Client IDとClient Secretを入力してください';
    } else {
        // OAuth設定を保存
        MFApiClient::saveOAuthConfig($clientId, $clientSecret);

        // OAuth認証フローを開始
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $redirectUri = $protocol . '://' . $host . '/mf-callback.php';

        // CSRF対策用のstateを生成してセッションに保存
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        // 認証URLにリダイレクト
        $authUrl = MFApiClient::getAuthorizationUrl($clientId, $redirectUri, $state);
        header('Location: ' . $authUrl);
        exit;
    }
}

// 従来のアクセストークン直接入力方式の設定保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_token_settings'])) {
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
        <div class="alert alert-error" style="white-space: pre-line;"><?= htmlspecialchars($error) ?></div>
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
                <p style="margin: 0 0 0.5rem 0;">マネーフォワード クラウド請求書のAPIを使用して、以下の機能が利用できます：</p>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <li>トラブル案件から直接請求書を作成</li>
                    <li>取引先情報の自動取得</li>
                    <li>請求書データの同期</li>
                    <li>見積書データの同期</li>
                </ul>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: #666;">
                    ※このシステムはMFクラウド「請求書」と連携します。MFクラウド会計とは異なりますのでご注意ください。
                </p>
            </div>

            <!-- OAuth認証方式（推奨） -->
            <form method="POST" action="">
                <div class="form-section">
                    <h3>OAuth認証設定（推奨）</h3>
                    <p style="color: var(--gray-600); font-size: 0.875rem; margin-bottom: 1rem;">
                        より安全な認証方式です。Client IDとClient Secretを使用してMFと連携します。
                    </p>

                    <div class="form-group">
                        <label for="client_id">Client ID *</label>
                        <input
                            type="text"
                            class="form-input"
                            id="client_id"
                            name="client_id"
                            value="<?= htmlspecialchars($currentConfig['client_id'] ?? '') ?>"
                            placeholder="MFクラウド請求書で発行したClient IDを入力"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="client_secret">Client Secret *</label>
                        <input
                            type="text"
                            class="form-input"
                            id="client_secret"
                            name="client_secret"
                            value="<?= htmlspecialchars($currentConfig['client_secret'] ?? '') ?>"
                            placeholder="MFクラウド請求書で発行したClient Secretを入力"
                            required
                        >
                        <div class="help-text">
                            <strong>取得方法:</strong>
                            <ol style="margin: 0.5rem 0 0 1.5rem; padding: 0;">
                                <li><a href="https://invoice.moneyforward.com/" target="_blank" style="color: var(--primary);">MF クラウド請求書</a>にログイン</li>
                                <li>「各種設定」→「連携サービス設定」を開く</li>
                                <li>「API連携設定」→「OAuth認証アプリケーションを追加」をクリック</li>
                                <li>リダイレクトURIに <code style="background: #f0f0f0; padding: 0.2rem 0.4rem; border-radius: 3px;"><?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/mf-callback.php</code> を入力</li>
                                <li>発行されたClient IDとClient Secretをコピー</li>
                            </ol>
                        </div>
                    </div>

                    <button type="submit" name="save_oauth_settings" class="btn btn-primary">
                        OAuth認証を開始
                    </button>
                </div>
            </form>

            <div style="border-top: 2px dashed var(--gray-300); margin: 2rem 0; padding-top: 2rem;">
                <p style="text-align: center; color: var(--gray-500); font-size: 0.875rem; margin-bottom: 1rem;">または</p>
            </div>

            <!-- アクセストークン直接入力方式 -->
            <form method="POST" action="">
                <div class="form-section">
                    <h3>アクセストークン直接入力</h3>
                    <p style="color: var(--gray-600); font-size: 0.875rem; margin-bottom: 1rem;">
                        既にアクセストークンをお持ちの場合はこちらを使用できます。
                    </p>

                    <div class="form-group">
                        <label for="access_token">アクセストークン *</label>
                        <input
                            type="text"
                            class="form-input"
                            id="access_token"
                            name="access_token"
                            value="<?= htmlspecialchars($currentConfig['access_token'] ?? '') ?>"
                            placeholder="MFクラウド請求書で発行したアクセストークンを入力"
                            required
                        >
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
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" name="save_token_settings" class="btn btn-secondary">
                            設定を保存（接続テスト実行）
                        </button>
                        <?php if ($isConfigured): ?>
                            <button type="submit" name="sync_now" class="btn btn-secondary">
                                今すぐ同期
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

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
