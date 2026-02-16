<?php
require_once '../api/auth.php';

// 管理者のみアクセス可能
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

require_once '../api/integration/api-auth.php';

// POSTリクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST処理時のCSRF検証
    verifyCsrfToken();

    $config = getIntegrationConfig();

    // 連携の有効/無効切り替え
    if (isset($_POST['toggle_enabled'])) {
        $config['enabled'] = !$config['enabled'];
        saveIntegrationConfig($config);
        header('Location: integration-settings.php?msg=status_updated');
        exit;
    }

    // ログの有効/無効切り替え
    if (isset($_POST['toggle_log'])) {
        $config['log_enabled'] = !$config['log_enabled'];
        saveIntegrationConfig($config);
        header('Location: integration-settings.php?msg=log_updated');
        exit;
    }

    // 新しいAPIキーを生成
    if (isset($_POST['generate_key'])) {
        $keyName = trim($_POST['key_name'] ?? '');
        if (empty($keyName)) {
            header('Location: integration-settings.php?error=name_required');
            exit;
        }

        $newKey = array(
            'name' => $keyName,
            'key' => generateApiKey(),
            'active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['user_email']
        );

        if (!isset($config['api_keys'])) {
            $config['api_keys'] = array();
        }
        $config['api_keys'][] = $newKey;
        saveIntegrationConfig($config);
        header('Location: integration-settings.php?msg=key_generated');
        exit;
    }

    // APIキーの有効/無効切り替え
    if (isset($_POST['toggle_key'])) {
        $keyIndex = intval($_POST['key_index']);
        if (isset($config['api_keys'][$keyIndex])) {
            $config['api_keys'][$keyIndex]['active'] = !$config['api_keys'][$keyIndex]['active'];
            saveIntegrationConfig($config);
        }
        header('Location: integration-settings.php?msg=key_updated');
        exit;
    }

    // APIキーの削除
    if (isset($_POST['delete_key'])) {
        $keyIndex = intval($_POST['key_index']);
        if (isset($config['api_keys'][$keyIndex])) {
            $deletedKey = $config['api_keys'][$keyIndex];
            array_splice($config['api_keys'], $keyIndex, 1);
            saveIntegrationConfig($config);
            writeAuditLog('delete', 'api_key', 'APIキーを削除: ' . ($deletedKey['name'] ?? ''));
        }
        header('Location: integration-settings.php?msg=key_deleted');
        exit;
    }

    // 許可IPの追加
    if (isset($_POST['add_ip'])) {
        $ip = trim($_POST['ip_address'] ?? '');
        if (!empty($ip)) {
            if (!isset($config['allowed_ips'])) {
                $config['allowed_ips'] = array();
            }
            if (!in_array($ip, $config['allowed_ips'])) {
                $config['allowed_ips'][] = $ip;
                saveIntegrationConfig($config);
            }
        }
        header('Location: integration-settings.php?msg=ip_added');
        exit;
    }

    // 許可IPの削除
    if (isset($_POST['delete_ip'])) {
        $ipIndex = intval($_POST['ip_index']);
        if (isset($config['allowed_ips'][$ipIndex])) {
            $deletedIp = $config['allowed_ips'][$ipIndex];
            array_splice($config['allowed_ips'], $ipIndex, 1);
            saveIntegrationConfig($config);
            writeAuditLog('delete', 'allowed_ip', '許可IPを削除: ' . $deletedIp);
        }
        header('Location: integration-settings.php?msg=ip_deleted');
        exit;
    }
}

$config = getIntegrationConfig();
$logs = getApiLogs(50);

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
/* 設定詳細ヘッダー */
.settings-detail-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.settings-detail-header h2 {
    margin: 0;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.settings-section {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.settings-section h2 {
    font-size: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-badge.active {
    background: var(--success-light);
    color: #2E7D32;
}

.status-badge.inactive {
    background: var(--danger-light);
    color: #C62828;
}

.api-key-display {
    font-family: monospace;
    background: #f3f4f6;
    padding: 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    word-break: break-all;
    max-width: 400px;
}

.key-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.key-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 6px;
    flex-wrap: wrap;
}

.key-info {
    flex: 1;
    min-width: 200px;
}

.key-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.key-meta {
    font-size: 0.75rem;
    color: #6b7280;
}

.key-actions {
    display: flex;
    gap: 0.5rem;
}

.ip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.ip-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #e5e7eb;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.875rem;
}

.ip-tag button {
    background: none;
    border: none;
    color: var(--danger);
    cursor: pointer;
    padding: 0;
    font-size: 1rem;
    line-height: 1;
}

.log-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.log-table th,
.log-table td {
    padding: 0.5rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.log-table th {
    background: #f9fafb;
    font-weight: 600;
}

.log-action {
    font-family: monospace;
    font-size: 0.75rem;
}

.add-form {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.add-form input {
    flex: 1;
    max-width: 300px;
}

.toggle-btn {
    padding: 0.5rem 1rem;
}

.alert {
    padding: 0.75rem 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.alert-success {
    background: var(--success-light);
    color: #2E7D32;
}

.alert-error {
    background: var(--danger-light);
    color: #C62828;
}

.api-endpoint {
    background: #1f2937;
    color: #f9fafb;
    padding: 1rem;
    border-radius: 6px;
    font-family: monospace;
    font-size: 0.875rem;
    margin-top: 0.5rem;
    overflow-x: auto;
}

.api-endpoint .method {
    color: #34d399;
    font-weight: bold;
}

.api-endpoint .url {
    color: #93c5fd;
}

.copy-btn {
    background: #374151;
    color: white;
    border: none;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.75rem;
    margin-left: 0.5rem;
}

.copy-btn:hover {
    background: #4b5563;
}

.tabs {
    display: flex;
    gap: 0;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 1rem;
}

.tab {
    padding: 0.75rem 1.5rem;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    color: #6b7280;
}

.tab.active {
    border-bottom-color: var(--primary);
    color: var(--primary);
    font-weight: 500;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}
</style>

<div class="page-container">
<div class="settings-detail-header">
    <a href="settings.php" class="btn btn-secondary btn-sm">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        一覧に戻る
    </a>
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="w-24 h-24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        API連携設定
    </h2>
</div>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success">
    <?php
    $messages = array(
        'status_updated' => '連携ステータスを更新しました',
        'log_updated' => 'ログ設定を更新しました',
        'key_generated' => 'APIキーを生成しました',
        'key_updated' => 'APIキーを更新しました',
        'key_deleted' => 'APIキーを削除しました',
        'ip_added' => 'IPアドレスを追加しました',
        'ip_deleted' => 'IPアドレスを削除しました'
    );
    $msgKey = $_GET['msg'] ?? '';
    echo htmlspecialchars($messages[$msgKey] ?? '更新しました');
    ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-error">
    <?php
    $errors = array(
        'name_required' => 'APIキー名を入力してください'
    );
    $errorKey = $_GET['error'] ?? '';
    echo htmlspecialchars($errors[$errorKey] ?? 'エラーが発生しました');
    ?>
</div>
<?php endif; ?>

<div class="tabs">
    <div class="tab active" data-tab="settings">設定</div>
    <div class="tab" data-tab="endpoints">APIエンドポイント</div>
    <div class="tab" data-tab="logs">ログ</div>
</div>

<div id="tab-settings" class="tab-content active">
    <!-- 基本設定 -->
    <div class="settings-section">
        <h2>基本設定</h2>
        <div  class="d-flex align-center gap-2 mb-2">
            <span>API連携:</span>
            <span class="status-badge <?= $config['enabled'] ? 'active' : 'inactive' ?>">
                <?= $config['enabled'] ? '有効' : '無効' ?>
            </span>
            <form method="post"  class="d-inline">
                <?= csrfTokenField() ?>
                <button type="submit" name="toggle_enabled" class="btn btn-secondary toggle-btn">
                    <?= $config['enabled'] ? '無効にする' : '有効にする' ?>
                </button>
            </form>
        </div>
        <div  class="d-flex align-center gap-2">
            <span>ログ記録:</span>
            <span class="status-badge <?= $config['log_enabled'] ? 'active' : 'inactive' ?>">
                <?= $config['log_enabled'] ? '有効' : '無効' ?>
            </span>
            <form method="post"  class="d-inline">
                <?= csrfTokenField() ?>
                <button type="submit" name="toggle_log" class="btn btn-secondary toggle-btn">
                    <?= $config['log_enabled'] ? '無効にする' : '有効にする' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- APIキー管理 -->
    <div class="settings-section">
        <h2>APIキー管理</h2>
        <p    class="text-gray-500 mb-2 text-14">
            外部システムからのAPI呼び出しに使用するキーを管理します。
        </p>

        <div class="key-list">
            <?php if (empty($config['api_keys'])): ?>
            <p  class="text-gray-500">APIキーがありません。下のフォームから生成してください。</p>
            <?php else: ?>
            <?php foreach ($config['api_keys'] as $index => $key): ?>
            <div class="key-item">
                <div class="key-info">
                    <div class="key-name"><?= htmlspecialchars($key['name']) ?></div>
                    <div class="api-key-display"><?= htmlspecialchars($key['key']) ?></div>
                    <div class="key-meta">
                        作成: <?= htmlspecialchars($key['created_at']) ?> /
                        作成者: <?= htmlspecialchars($key['created_by']) ?>
                    </div>
                </div>
                <span class="status-badge <?= $key['active'] ? 'active' : 'inactive' ?>">
                    <?= $key['active'] ? '有効' : '無効' ?>
                </span>
                <div class="key-actions">
                    <form method="post"  class="d-inline">
                        <?= csrfTokenField() ?>
                        <input type="hidden" name="key_index" value="<?= $index ?>">
                        <button type="submit" name="toggle_key" class="btn btn-secondary btn-sm">
                            <?= $key['active'] ? '無効化' : '有効化' ?>
                        </button>
                    </form>
                    <form method="post"  class="d-inline" class="delete-api-key-form">
                        <?= csrfTokenField() ?>
                        <input type="hidden" name="key_index" value="<?= $index ?>">
                        <button type="submit" name="delete_key" class="btn btn-danger btn-sm">削除</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form method="post" class="add-form">
            <?= csrfTokenField() ?>
            <input type="text" name="key_name" placeholder="APIキー名（例: 基幹システム連携）" required>
            <button type="submit" name="generate_key" class="btn btn-primary">新しいキーを生成</button>
        </form>
    </div>

    <!-- 許可IPアドレス -->
    <div class="settings-section">
        <h2>許可IPアドレス</h2>
        <p    class="text-gray-500 mb-2 text-14">
            空の場合は全てのIPアドレスからのアクセスを許可します。
        </p>

        <div class="ip-list">
            <?php if (empty($config['allowed_ips'])): ?>
            <span  class="text-gray-500">制限なし（全てのIPを許可）</span>
            <?php else: ?>
            <?php foreach ($config['allowed_ips'] as $index => $ip): ?>
            <div class="ip-tag">
                <?= htmlspecialchars($ip) ?>
                <form method="post"  class="d-inline">
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="ip_index" value="<?= $index ?>">
                    <button type="submit" name="delete_ip" title="削除">&times;</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form method="post" class="add-form">
            <?= csrfTokenField() ?>
            <input type="text" name="ip_address" placeholder="IPアドレス（例: 192.168.1.100）" required>
            <button type="submit" name="add_ip" class="btn btn-secondary">追加</button>
        </form>
    </div>
</div>

<div id="tab-endpoints" class="tab-content">
    <div class="settings-section">
        <h2>利用可能なAPIエンドポイント</h2>
        <p    class="text-gray-500 mb-2 text-14">
            以下のエンドポイントに対して、X-Api-Keyヘッダーを付与してリクエストを送信してください。
        </p>

        <h3  class="mt-3 mb-1">案件API</h3>
        <div class="api-endpoint">
            <span class="method">GET</span> <span class="url">/api/integration/projects.php</span>
            <button type="button" class="copy-btn" data-text="/api/integration/projects.php">コピー</button>
        </div>
        <p    class="text-gray-500 mt-1 text-14">案件一覧を取得します。?id=xxx または ?external_id=xxx で絞り込み可能。</p>

        <div class="api-endpoint">
            <span class="method">POST</span> <span class="url">/api/integration/projects.php</span>
        </div>
        <p    class="text-gray-500 mt-1 text-14">案件を登録・更新します。external_idが一致する場合は更新、なければ新規作成。</p>

        <h3  class="mt-3 mb-1">顧客API</h3>
        <div class="api-endpoint">
            <span class="method">GET</span> <span class="url">/api/integration/customers.php</span>
            <button type="button" class="copy-btn" data-text="/api/integration/customers.php">コピー</button>
        </div>
        <p    class="text-gray-500 mt-1 text-14">顧客一覧を取得します。?id=xxx または ?external_id=xxx で絞り込み可能。</p>

        <div class="api-endpoint">
            <span class="method">POST</span> <span class="url">/api/integration/customers.php</span>
        </div>
        <p    class="text-gray-500 mt-1 text-14">顧客を登録・更新します。external_idが一致する場合は更新、なければ新規作成。</p>

        <h3  class="mt-3 mb-1">リクエスト例</h3>
        <div class="api-endpoint">
curl -X POST "https://example.com/api/integration/projects.php" \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: YOUR_API_KEY" \
  -d '{
    "name": "テストプロジェクト",
    "customer": "テスト顧客",
    "external_id": "EXT-001"
  }'
        </div>

        <h3  class="mt-3 mb-1">一括登録例</h3>
        <div class="api-endpoint">
curl -X POST "https://example.com/api/integration/projects.php" \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: YOUR_API_KEY" \
  -d '{
    "projects": [
      {"name": "案件1", "external_id": "EXT-001"},
      {"name": "案件2", "external_id": "EXT-002"}
    ]
  }'
        </div>
    </div>
</div>

<div id="tab-logs" class="tab-content">
    <div class="settings-section">
        <h2>APIログ（最新50件）</h2>

        <?php if (empty($logs)): ?>
        <p  class="text-gray-500">ログがありません。</p>
        <?php else: ?>
        <div    class="overflow-x-auto">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>日時</th>
                        <th>アクション</th>
                        <th>APIキー</th>
                        <th>IP</th>
                        <th>エンドポイント</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['timestamp']) ?></td>
                        <td><span class="log-action"><?= htmlspecialchars($log['action']) ?></span></td>
                        <td><?= htmlspecialchars($log['key_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($log['ip']) ?></td>
                        <td  class="text-xs"><?= htmlspecialchars($log['endpoint']) ?></td>
                        <td  class="text-xs"><?= htmlspecialchars(json_encode($log['details'], JSON_UNESCAPED_UNICODE)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script<?= nonceAttr() ?>>
// イベントリスナー登録
document.addEventListener('DOMContentLoaded', function() {
    // APIキー削除フォーム
    document.querySelectorAll('.delete-api-key-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('このAPIキーを削除しますか？')) {
                e.preventDefault();
            }
        });
    });

    // コピーボタン
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const text = this.getAttribute('data-text');
            copyToClipboard(text);
        });
    });
});

// タブ切り替え
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        this.classList.add('active');
        document.getElementById('tab-' + this.dataset.tab).classList.add('active');
    });
});

// クリップボードにコピー
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('コピーしました');
    });
}
</script>
</div><!-- /.page-container -->

<?php require_once '../functions/footer.php'; ?>
