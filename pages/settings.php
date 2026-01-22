<?php
require_once '../config/config.php';
require_once '../api/mf-api.php';
require_once '../functions/notification-functions.php';
require_once '../api/integration/api-auth.php';

// 管理者権限チェック
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$notificationConfig = getNotificationConfig();
$integrationConfig = getIntegrationConfig();

require_once '../functions/header.php';
?>

<style>
.settings-grid {
    display: grid;
    gap: 1.5rem;
}

.setting-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.setting-card h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
    color: var(--gray-900);
}

.setting-card p {
    margin: 0 0 1rem 0;
    color: var(--gray-600);
    font-size: 0.875rem;
}

.setting-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge.success {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.warning {
    background: #fef3c7;
    color: #92400e;
}
</style>

<div class="card">
    <div class="card-header">
        <h2 style="margin: 0;">設定</h2>
    </div>
    <div class="card-body">
        <div class="settings-grid">
            <!-- MF請求書連携設定 -->
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>MF請求書連携</h3>
                        <p>MoneyForward請求書とのAPI連携を設定します</p>
                    </div>
                    <?php if (MFApiClient::isConfigured()): ?>
                        <span class="status-badge success">✓ 設定済み</span>
                    <?php else: ?>
                        <span class="status-badge warning">未設定</span>
                    <?php endif; ?>
                </div>
                <div class="setting-actions">
                    <a href="mf-settings.php" class="btn btn-primary">
                        <?= MFApiClient::isConfigured() ? 'MF設定を編集' : 'MF連携を設定' ?>
                    </a>
                </div>
            </div>

            <!-- ユーザー管理 -->
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>ユーザー管理</h3>
                        <p>システムを利用するユーザーの管理を行います</p>
                    </div>
                </div>
                <div class="setting-actions">
                    <a href="users.php" class="btn btn-primary">ユーザー管理</a>
                </div>
            </div>

            <!-- 通知設定 -->
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>通知設定</h3>
                        <p>トラブル発生時のメール通知を設定します</p>
                    </div>
                    <?php if ($notificationConfig['enabled']): ?>
                        <span class="status-badge success">✓ 有効</span>
                    <?php else: ?>
                        <span class="status-badge warning">無効</span>
                    <?php endif; ?>
                </div>
                <div class="setting-actions">
                    <a href="notification-settings.php" class="btn btn-primary">通知設定</a>
                </div>
            </div>

            <!-- API連携設定 -->
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>API連携設定</h3>
                        <p>外部システムとのAPI連携を設定します</p>
                    </div>
                    <?php if ($integrationConfig['enabled']): ?>
                        <span class="status-badge success">✓ 有効</span>
                    <?php else: ?>
                        <span class="status-badge warning">無効</span>
                    <?php endif; ?>
                </div>
                <div class="setting-actions">
                    <a href="integration-settings.php" class="btn btn-primary">API連携設定</a>
                </div>
            </div>

            <!-- 従業員マスタ -->
            <?php if (canEdit()): ?>
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>従業員マスタ</h3>
                        <p>従業員情報の管理を行います</p>
                    </div>
                </div>
                <div class="setting-actions">
                    <a href="employees.php" class="btn btn-primary">従業員マスタ</a>
                </div>
            </div>

            <!-- データ管理 -->
            <div class="setting-card">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3>データ管理</h3>
                        <p>トラブルデータのインポート・エクスポートを行います</p>
                    </div>
                </div>
                <div class="setting-actions">
                    <a href="import-troubles.php" class="btn btn-secondary">CSVインポート</a>
                    <a href="download-template.php" class="btn btn-secondary">テンプレートDL</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../functions/footer.php'; ?>
