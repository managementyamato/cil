<?php
require_once '../config/config.php';

// 管理者権限チェック
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$configFile = __DIR__ . '/../config/mf-sync-config.json';

// 設定を保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sync_settings'])) {
    $targetMonth = trim($_POST['target_month'] ?? '');

    // 年月の形式をチェック (YYYY-MM)
    if (!preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
        $error = '年月の形式が正しくありません（YYYY-MM形式で入力してください）';
    } else {
        $config = [
            'target_month' => $targetMonth,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        header('Location: mf-sync-settings.php?saved=1');
        exit;
    }
}

// 設定を読み込み
$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?? [];
}

$targetMonth = $config['target_month'] ?? date('Y-m');

require_once '../functions/header.php';
?>

<style>
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--gray-700);
}

.form-input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 4px;
    font-size: 1rem;
}

.form-help {
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: var(--gray-600);
}

.info-box {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 4px;
}

.info-box p {
    margin: 0;
    color: #1e40af;
}
</style>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">
        同期設定を保存しました。次回の同期から適用されます。
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 style="margin: 0;">MF請求書 同期設定</h2>
    </div>
    <div class="card-body">
        <div class="info-box">
            <p>
                <strong>⚠️ 注意:</strong> 指定した月の請求書のみを同期します。
                請求日を基準に、その月に請求された請求書が対象となります。
            </p>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="target_month">
                    同期対象月
                </label>
                <input
                    type="month"
                    class="form-input"
                    id="target_month"
                    name="target_month"
                    value="<?= htmlspecialchars($targetMonth) ?>"
                    required
                    style="max-width: 250px;"
                >
                <div class="form-help">
                    どの月の請求書を同期するか指定してください（請求日を基準）<br>
                    例: 2026年1月 = 2026年1月に請求された請求書のみ同期
                </div>
            </div>

            <div style="display: flex; gap: 0.5rem; margin-top: 2rem;">
                <button type="submit" name="save_sync_settings" class="btn btn-primary">
                    設定を保存
                </button>
                <a href="settings.php" class="btn btn-secondary">
                    戻る
                </a>
            </div>
        </form>

        <?php if (!empty($config)): ?>
            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--gray-200);">
                <h3 style="font-size: 1rem; margin-bottom: 0.5rem; color: var(--gray-700);">現在の設定</h3>
                <p style="color: var(--gray-600); margin: 0;">
                    同期対象: <strong><?= date('Y年n月', strtotime($targetMonth . '-01')) ?></strong><br>
                    <?php if (isset($config['updated_at'])): ?>
                        最終更新: <?= htmlspecialchars($config['updated_at']) ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../functions/footer.php'; ?>
