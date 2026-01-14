<?php
require_once 'config.php';

// 編集権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

require_once 'header.php';

// デバッグファイルを読み込む
$syncDebugFile = __DIR__ . '/mf-sync-debug.json';
$apiDebugFile = __DIR__ . '/mf-api-debug.json';

$syncDebug = null;
$apiDebug = null;

if (file_exists($syncDebugFile)) {
    $syncDebug = json_decode(file_get_contents($syncDebugFile), true);
}

if (file_exists($apiDebugFile)) {
    $apiDebug = json_decode(file_get_contents($apiDebugFile), true);
}
?>

<style>
.debug-section {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}

.debug-section h3 {
    margin-top: 0;
    color: var(--gray-700);
    border-bottom: 2px solid var(--gray-200);
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.debug-info {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    overflow-x: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.no-data {
    color: var(--gray-500);
    font-style: italic;
    text-align: center;
    padding: 2rem;
}

.info-grid {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.info-label {
    font-weight: 600;
    color: var(--gray-700);
}

.info-value {
    color: var(--gray-600);
}

.error-box {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.success-box {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #16a34a;
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">MF API デバッグ情報</h2>
    <a href="finance.php" class="btn btn-secondary">財務管理に戻る</a>
</div>

<?php if (!$syncDebug && !$apiDebug): ?>
    <div class="debug-section">
        <div class="no-data">
            デバッグ情報がありません。<br>
            「MFから同期」を実行すると、デバッグ情報が生成されます。
        </div>
    </div>
<?php endif; ?>

<?php if ($syncDebug): ?>
    <div class="debug-section">
        <h3>同期処理の概要</h3>

        <div class="info-grid">
            <div class="info-label">同期実行時刻:</div>
            <div class="info-value"><?= htmlspecialchars($syncDebug['sync_time'] ?? '-') ?></div>

            <div class="info-label">取得件数:</div>
            <div class="info-value"><?= intval($syncDebug['invoice_count'] ?? 0) ?>件</div>

            <?php if (isset($syncDebug['request_params']['from'])): ?>
                <div class="info-label">開始日:</div>
                <div class="info-value"><?= htmlspecialchars($syncDebug['request_params']['from'] ?? 'なし') ?></div>

                <div class="info-label">終了日:</div>
                <div class="info-value"><?= htmlspecialchars($syncDebug['request_params']['to'] ?? 'なし') ?></div>
            <?php endif; ?>

            <?php if (isset($syncDebug['request_params']['note'])): ?>
                <div class="info-label">取得方法:</div>
                <div class="info-value"><?= htmlspecialchars($syncDebug['request_params']['note']) ?></div>
            <?php endif; ?>
        </div>

        <?php if (!empty($syncDebug['errors'])): ?>
            <div class="error-box">
                <strong>エラー:</strong><br>
                <?php foreach ($syncDebug['errors'] as $error): ?>
                    • <?= htmlspecialchars($error) ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($syncDebug['sample_invoices'])): ?>
            <h4 style="margin-top: 1.5rem; color: var(--gray-700);">サンプルデータ（最初の3件）</h4>
            <div class="debug-info">
<?= htmlspecialchars(json_encode($syncDebug['sample_invoices'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?>
            </div>
        <?php elseif (isset($syncDebug['invoice_count']) && $syncDebug['invoice_count'] === 0): ?>
            <div class="error-box">
                取得した請求書が0件です。以下を確認してください：
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <li>MoneyForwardに請求書データが登録されているか</li>
                    <li>日付範囲が適切か（過去3ヶ月以内にデータがあるか）</li>
                    <li>APIのアクセス権限が正しく設定されているか</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($apiDebug): ?>
    <div class="debug-section">
        <h3>API レスポンス詳細</h3>

        <?php foreach ($apiDebug as $index => $log): ?>
            <h4 style="color: var(--gray-700); margin-top: <?= $index > 0 ? '1.5rem' : '0' ?>;">
                ページ <?= intval($log['page'] ?? 0) ?>
            </h4>

            <div class="info-grid">
                <div class="info-label">リクエストURL:</div>
                <div class="info-value" style="word-break: break-all;">
                    <?= htmlspecialchars($log['full_url'] ?? '-') ?>
                </div>

                <div class="info-label">エンドポイント:</div>
                <div class="info-value"><?= htmlspecialchars($log['endpoint'] ?? '-') ?></div>

                <div class="info-label">レスポンスキー:</div>
                <div class="info-value">
                    <?= htmlspecialchars(implode(', ', $log['response_keys'] ?? [])) ?>
                </div>

                <?php if (isset($log['response']['billings'])): ?>
                    <div class="info-label">この頁の件数:</div>
                    <div class="info-value"><?= count($log['response']['billings']) ?>件</div>
                <?php endif; ?>
            </div>

            <h5 style="margin-top: 1rem; color: var(--gray-600);">完全なレスポンス:</h5>
            <div class="debug-info">
<?= htmlspecialchars(json_encode($log['response'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="debug-section">
    <h3>デバッグファイルの場所</h3>
    <div class="info-grid">
        <div class="info-label">同期デバッグ:</div>
        <div class="info-value">
            <code><?= htmlspecialchars($syncDebugFile) ?></code>
            <?php if (file_exists($syncDebugFile)): ?>
                <span style="color: #16a34a;">✓ 存在</span>
            <?php else: ?>
                <span style="color: #dc2626;">✗ 未作成</span>
            <?php endif; ?>
        </div>

        <div class="info-label">API デバッグ:</div>
        <div class="info-value">
            <code><?= htmlspecialchars($apiDebugFile) ?></code>
            <?php if (file_exists($apiDebugFile)): ?>
                <span style="color: #16a34a;">✓ 存在</span>
            <?php else: ?>
                <span style="color: #dc2626;">✗ 未作成</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
