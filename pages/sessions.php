<?php
require_once '../api/auth.php';
require_once '../functions/login-security.php';

$userId = $_SESSION['user_email'];
$userName = $_SESSION['user_name'] ?? '';

// POST処理
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'logout_other':
            // 他のセッションをすべてログアウト
            $count = removeOtherSessions($userId);
            $message = '他のすべてのセッションからログアウトしました。';
            $messageType = 'success';
            writeAuditLog('logout_others', 'session', '他のセッションをすべてログアウト');
            break;

        case 'logout_session':
            // 特定のセッションをログアウト
            $targetSessionId = $_POST['session_id'] ?? '';
            if ($targetSessionId && $targetSessionId !== session_id()) {
                forceLogoutSession($userId, $targetSessionId);
                $message = '選択したセッションをログアウトしました。';
                $messageType = 'success';
                writeAuditLog('logout_session', 'session', 'セッションを強制ログアウト');
            } else {
                $message = '現在のセッションはここからログアウトできません。';
                $messageType = 'error';
            }
            break;
    }
}

// アクティブセッション取得
$sessions = getActiveSessions($userId);

// ログイン履歴取得
$loginHistory = getLoginHistory($userId);

include '../functions/header.php';
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

/* 設定カード */
.setting-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}
.setting-card h3 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: var(--gray-900);
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--gray-200);
}

.card-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0;
    padding: 0;
    border: none;
}

.card-body {
    padding: 1.5rem;
}

.session-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.session-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
}

.session-item:last-child {
    border-bottom: none;
}

.session-icon {
    width: 48px;
    height: 48px;
    background: #f3f4f6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-size: 1.5rem;
}

.session-icon.current {
    background: #dbeafe;
    color: #2563eb;
}

.session-info {
    flex: 1;
}

.session-device {
    font-weight: 500;
    color: #1f2937;
}

.session-details {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.session-current-badge {
    background: #dcfce7;
    color: #166534;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.session-actions {
    margin-left: 1rem;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-danger:hover {
    background: #b91c1c;
}

.btn-outline {
    background: white;
    border: 1px solid #d1d5db;
    color: #374151;
}

.btn-outline:hover {
    background: #f9fafb;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
}

.history-table th,
.history-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.history-table th {
    background: #f9fafb;
    font-weight: 500;
    color: #6b7280;
    font-size: 0.875rem;
}

.history-table td {
    font-size: 0.875rem;
    color: #1f2937;
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.alert-success {
    background: var(--success-light);
    color: #2E7D32;
    border-left: 4px solid var(--success);
}

.alert-error {
    background: var(--danger-light);
    color: #C62828;
    border-left: 4px solid var(--danger);
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
}
</style>

<div class="page-container">
<div class="settings-detail-header">
    <a href="settings.php" class="btn btn-secondary btn-sm">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        一覧に戻る
    </a>
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="w-24 h-24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        セッション管理
    </h2>
</div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- アクティブセッション -->
    <div class="setting-card">
        <div class="card-header">
            <h3>アクティブなセッション</h3>
            <?php if (count($sessions) > 1): ?>
                <form method="POST"  class="d-inline" id="logoutOtherForm">
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="action" value="logout_other">
                    <button type="submit" class="btn btn-danger">他のセッションをすべてログアウト</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($sessions)): ?>
                <div class="empty-state">アクティブなセッションはありません</div>
            <?php else: ?>
                <ul class="session-list">
                    <?php foreach ($sessions as $session):
                        $isCurrent = ($session['session_id'] === session_id());
                        $deviceIcon = '💻';
                        if (strpos($session['device'] ?? '', 'iPhone') !== false || strpos($session['device'] ?? '', 'Android Phone') !== false) {
                            $deviceIcon = '📱';
                        } elseif (strpos($session['device'] ?? '', 'iPad') !== false || strpos($session['device'] ?? '', 'Tablet') !== false) {
                            $deviceIcon = '📱';
                        }
                    ?>
                        <li class="session-item">
                            <div class="session-icon <?= $isCurrent ? 'current' : '' ?>">
                                <?= $deviceIcon ?>
                            </div>
                            <div class="session-info">
                                <div class="session-device">
                                    <?= htmlspecialchars($session['device'] ?? 'Unknown Device') ?>
                                    <?php if ($isCurrent): ?>
                                        <span class="session-current-badge">現在のセッション</span>
                                    <?php endif; ?>
                                </div>
                                <div class="session-details">
                                    IP: <?= htmlspecialchars($session['ip'] ?? 'Unknown') ?> ・
                                    最終アクティビティ: <?= date('Y/m/d H:i', strtotime($session['last_activity'] ?? 'now')) ?>
                                </div>
                            </div>
                            <?php if (!$isCurrent): ?>
                                <div class="session-actions">
                                    <form method="POST"  class="d-inline" class="logout-session-form">
                                        <?= csrfTokenField() ?>
                                        <input type="hidden" name="action" value="logout_session">
                                        <input type="hidden" name="session_id" value="<?= htmlspecialchars($session['session_id']) ?>">
                                        <button type="submit" class="btn btn-outline">ログアウト</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- ログイン履歴 -->
    <div class="setting-card">
        <div class="card-header">
            <h3>ログイン履歴（最新20件）</h3>
        </div>
        <div   class="card-body p-0">
            <?php if (empty($loginHistory)): ?>
                <div class="empty-state">ログイン履歴はありません</div>
            <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>日時</th>
                            <th>IPアドレス</th>
                            <th>デバイス</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($loginHistory, 0, 20) as $record): ?>
                            <tr>
                                <td><?= date('Y/m/d H:i:s', strtotime($record['timestamp'])) ?></td>
                                <td><?= htmlspecialchars($record['ip'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars(parseUserAgent($record['user_agent'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div><!-- /.page-container -->

<script<?= nonceAttr() ?>>
// 他のセッション全てログアウトフォーム
document.getElementById('logoutOtherForm')?.addEventListener('submit', function(e) {
    if (!confirm('他のすべてのセッションからログアウトしますか？')) {
        e.preventDefault();
    }
});

// 個別セッションログアウトフォーム
document.querySelectorAll('.logout-session-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!confirm('このセッションをログアウトしますか？')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../functions/footer.php'; ?>
