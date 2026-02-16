<?php
require_once '../api/auth.php';
require_once '../api/mf-api.php';
require_once '../functions/notification-functions.php';
require_once '../api/integration/api-auth.php';
require_once '../api/google-oauth.php';
require_once '../api/google-calendar.php';
require_once '../api/google-chat.php';

// アルコールチェック用Chat設定を取得
$alcoholChatConfigFile = __DIR__ . '/../config/alcohol-chat-config.json';
$alcoholChatConfig = file_exists($alcoholChatConfigFile)
    ? json_decode(file_get_contents($alcoholChatConfigFile), true)
    : [];

// 管理者権限チェック
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$notificationConfig = getNotificationConfig();
$integrationConfig = getIntegrationConfig();
$googleOAuth = new GoogleOAuthClient();
$googleCalendar = new GoogleCalendarClient();
$googleChat = new GoogleChatClient();

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// Chat連携解除
if (isset($_POST['disconnect_chat'])) {
    $googleChat->disconnect();
    $_SESSION['chat_success'] = 'Google Chatの連携を解除しました';
    header('Location: settings.php?tab=google_chat');
    exit;
}

// カレンダー連携解除
if (isset($_POST['disconnect_calendar'])) {
    $googleCalendar->disconnect();
    $_SESSION['calendar_success'] = 'Googleカレンダーの連携を解除しました';
    header('Location: settings.php?tab=google_calendar');
    exit;
}

// セッションメッセージ
$calendarSuccess = $_SESSION['calendar_success'] ?? null;
$calendarError = $_SESSION['calendar_error'] ?? null;
$chatSuccess = $_SESSION['chat_success'] ?? null;
$chatError = $_SESSION['chat_error'] ?? null;
unset($_SESSION['calendar_success'], $_SESSION['calendar_error'], $_SESSION['chat_success'], $_SESSION['chat_error']);

// タブ切り替え（空の場合は一覧表示）
$activeTab = $_GET['tab'] ?? '';

// 設定項目の定義
$settingTypes = [
    'google_oauth' => [
        'name' => 'Googleログイン',
        'description' => 'Googleアカウントでのログインを有効にします',
        'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'status' => $googleOAuth->isConfigured(),
        'status_label' => $googleOAuth->isConfigured() ? '設定済み' : '未設定',
    ],
    'google_calendar' => [
        'name' => 'Googleカレンダー連携',
        'description' => 'ダッシュボードに今日の予定を表示します',
        'icon' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'status' => $googleCalendar->isConfigured(),
        'status_label' => $googleCalendar->isConfigured() ? '連携済み' : '未連携',
    ],
    'google_chat' => [
        'name' => 'Google Chat連携',
        'description' => 'アルコールチェック画像を取り込みます',
        'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'status' => $googleChat->isConfigured(),
        'status_label' => $googleChat->isConfigured() ? '連携済み' : '未連携',
    ],
    'mf_invoice' => [
        'name' => 'MF請求書連携',
        'description' => 'MoneyForward請求書とのAPI連携',
        'icon' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'status' => MFApiClient::isConfigured(),
        'status_label' => MFApiClient::isConfigured() ? '設定済み' : '未設定',
    ],
    'recurring_invoices' => [
        'name' => '定期請求書作成',
        'description' => '毎月の定期請求書を一括作成',
        'icon' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/>',
        'status' => file_exists(__DIR__ . '/../config/recurring-invoices.csv'),
        'status_label' => file_exists(__DIR__ . '/../config/recurring-invoices.csv') ? 'CSV登録済み' : 'CSV未登録',
    ],
    'notification' => [
        'name' => '通知設定',
        'description' => 'トラブル発生時のメール通知を設定',
        'icon' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'status' => $notificationConfig['enabled'],
        'status_label' => $notificationConfig['enabled'] ? '有効' : '無効',
    ],
    'api_integration' => [
        'name' => 'API連携設定',
        'description' => '外部システムとのAPI連携を設定',
        'icon' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'status' => $integrationConfig['enabled'],
        'status_label' => $integrationConfig['enabled'] ? '有効' : '無効',
    ],
    'user_permissions' => [
        'name' => 'アカウント権限設定',
        'description' => '各ユーザーの閲覧・編集権限を設定',
        'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'status' => null,
        'status_label' => '',
    ],
    'employees' => [
        'name' => '従業員マスタ',
        'description' => '従業員情報の管理を行います',
        'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'status' => null,
        'status_label' => '',
    ],
    'audit_log' => [
        'name' => '操作ログ',
        'description' => 'システムの操作履歴を確認',
        'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'status' => null,
        'status_label' => '',
    ],
    'sessions' => [
        'name' => 'セッション管理',
        'description' => 'ログイン中のセッションを管理',
        'icon' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'status' => null,
        'status_label' => '',
    ],
];

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
/* 設定選択グリッド */
.settings-select-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media (max-width: 1100px) {
    .settings-select-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (max-width: 850px) {
    .settings-select-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 600px) {
    .settings-select-grid {
        grid-template-columns: 1fr;
    }
}
.settings-select-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
}
.settings-select-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.settings-select-icon {
    width: 48px;
    height: 48px;
    background: var(--gray-100);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.settings-select-icon svg {
    width: 24px;
    height: 24px;
    color: var(--gray-600);
}
.settings-select-card:hover .settings-select-icon {
    background: var(--primary-light);
}
.settings-select-card:hover .settings-select-icon svg {
    color: var(--primary);
}
.settings-select-info {
    flex: 1;
}
.settings-select-name {
    font-weight: 600;
    font-size: 1rem;
    color: var(--gray-900);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.settings-select-desc {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-top: 0.25rem;
}
.settings-select-arrow {
    width: 20px;
    height: 20px;
    color: var(--gray-400);
    flex-shrink: 0;
}
.settings-select-card:hover .settings-select-arrow {
    color: var(--primary);
}

/* ステータスバッジ */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.125rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
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

/* 詳細ヘッダー */
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
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.setting-card h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
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

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}
.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
</style>

<div class="page-container">
<div class="page-header">
    <h2>設定</h2>
</div>

<?php if (empty($activeTab) || !isset($settingTypes[$activeTab])): ?>
<!-- 設定選択画面 -->
<div class="settings-select-grid">
    <?php
// 直接リンク先を定義
$directLinks = [
    'google_oauth' => 'google-oauth-settings.php',
    'google_calendar' => 'settings.php?tab=google_calendar',
    'google_chat' => 'settings.php?tab=google_chat',
    'mf_invoice' => 'mf-settings.php',
    'notification' => 'notification-settings.php',
    'api_integration' => 'integration-settings.php',
    'user_permissions' => 'user-permissions.php',
    'employees' => 'employees.php',
    'audit_log' => 'audit-log.php',
    'sessions' => 'sessions.php',
    'recurring_invoices' => 'recurring-invoices.php',
];
?>
<?php foreach ($settingTypes as $key => $setting): ?>
    <?php if ($key === 'employees' && !canEdit()) continue; ?>
    <a href="<?= $directLinks[$key] ?>" class="settings-select-card">
        <div class="settings-select-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $setting['icon'] ?></svg>
        </div>
        <div class="settings-select-info">
            <div class="settings-select-name">
                <?= htmlspecialchars($setting['name']) ?>
                <?php if ($setting['status'] !== null): ?>
                    <span class="status-badge <?= $setting['status'] ? 'success' : 'warning' ?>"><?= $setting['status_label'] ?></span>
                <?php endif; ?>
            </div>
            <div class="settings-select-desc"><?= htmlspecialchars($setting['description']) ?></div>
        </div>
        <svg class="settings-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- 設定詳細画面 -->
<div class="settings-detail-header">
    <a href="settings.php" class="btn btn-secondary btn-sm">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        一覧に戻る
    </a>
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="w-24 h-24"><?= $settingTypes[$activeTab]['icon'] ?></svg>
        <?= htmlspecialchars($settingTypes[$activeTab]['name']) ?>
    </h2>
</div>

<?php if ($activeTab === 'google_oauth'): ?>
<!-- Google OAuth設定 -->
<div class="setting-card">
    <div    class="d-flex justify-between mb-2 align-start">
        <div>
            <h3>Googleログイン設定</h3>
            <p>Googleアカウントでのログインを有効にします。Google Cloud Consoleで OAuth 2.0 クライアントを作成してください。</p>
        </div>
        <?php if ($googleOAuth->isConfigured()): ?>
            <span class="status-badge success">✓ 設定済み</span>
        <?php else: ?>
            <span class="status-badge warning">未設定</span>
        <?php endif; ?>
    </div>
    <div class="setting-actions">
        <a href="google-oauth-settings.php" class="btn btn-primary">
            <?= $googleOAuth->isConfigured() ? 'OAuth設定編集' : 'OAuth設定' ?>
        </a>
    </div>
</div>

<?php elseif ($activeTab === 'google_calendar'): ?>
<!-- Googleカレンダー連携 -->
<?php if ($calendarSuccess): ?>
<div class="alert alert-success"><?= htmlspecialchars($calendarSuccess) ?></div>
<?php endif; ?>
<?php if ($calendarError): ?>
<div class="alert alert-error"><?= htmlspecialchars($calendarError) ?></div>
<?php endif; ?>
<div class="setting-card">
    <div    class="d-flex justify-between mb-2 align-start">
        <div>
            <h3>Googleカレンダー連携</h3>
            <p>ダッシュボードに今日の予定を表示します</p>
        </div>
        <?php if ($googleCalendar->isConfigured()): ?>
            <span class="status-badge success">✓ 連携済み</span>
        <?php else: ?>
            <span class="status-badge warning">未連携</span>
        <?php endif; ?>
    </div>
    <div class="setting-actions">
        <?php if ($googleCalendar->isConfigured()): ?>
            <form method="POST"  class="d-inline" class="disconnect-calendar-form">
                <?= csrfTokenField() ?>
                <button type="submit" name="disconnect_calendar" class="btn btn-secondary">連携解除</button>
            </form>
        <?php else: ?>
            <?php if ($googleOAuth->isConfigured()): ?>
                <a href="<?= htmlspecialchars($googleCalendar->getAuthUrl()) ?>" class="btn btn-primary">Googleカレンダーを連携</a>
            <?php else: ?>
                <span   class="text-gray-500 text-14">※先にGoogle OAuthを設定してください</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($googleCalendar->isConfigured()): ?>
<!-- カレンダー表示設定 -->
<div   class="setting-card mt-3">
    <div  class="mb-2">
        <h3>カレンダー表示設定</h3>
        <p>ダッシュボードの「今日の予定」に表示するカレンダーを選択します。<br>共有カレンダーを含めることで、チームの予定も一緒に表示できます。</p>
    </div>
    <div id="calendarListContainer">
        <div  id="calendarList"        class="calendar-list d-flex flex-column gap-1">
            <p   class="text-gray-500">カレンダー一覧を読み込み中...</p>
        </div>
    </div>
    <button type="button"  id="saveCalendarBtn"  class="btn btn-primary d-none">
        設定を保存
    </button>
</div>

<style<?= nonceAttr() ?>>
.calendar-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
}
.calendar-item:hover {
    border-color: var(--gray-400);
}
.calendar-item.selected {
    border-color: var(--primary);
    background: var(--primary-light);
}
.calendar-color {
    width: 14px;
    height: 14px;
    border-radius: 3px;
    flex-shrink: 0;
}
.calendar-name {
    flex: 1;
    font-weight: 500;
    font-size: 0.9rem;
}
.calendar-badge {
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    background: var(--gray-100);
    color: var(--gray-600);
}
.calendar-badge.primary {
    background: #dbeafe;
    color: #1d4ed8;
}
.calendar-checkbox {
    width: 16px;
    height: 16px;
}
</style>

<script<?= nonceAttr() ?>>
// XSS対策：HTMLエスケープ関数
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

const calendarCsrfToken = '<?= generateCsrfToken() ?>';

// イベントリスナー登録
document.addEventListener('DOMContentLoaded', function() {
    // カレンダー連携解除フォーム
    const disconnectCalendarForm = document.querySelector('.disconnect-calendar-form');
    if (disconnectCalendarForm) {
        disconnectCalendarForm.addEventListener('submit', function(e) {
            if (!confirm('カレンダー連携を解除しますか？')) {
                e.preventDefault();
            }
        });
    }

    // Chat連携解除フォーム
    const disconnectChatForm = document.querySelector('.disconnect-chat-form');
    if (disconnectChatForm) {
        disconnectChatForm.addEventListener('submit', function(e) {
            if (!confirm('Google Chat連携を解除しますか？')) {
                e.preventDefault();
            }
        });
    }

    // カレンダー設定保存ボタン
    const saveCalendarBtn = document.getElementById('saveCalendarBtn');
    if (saveCalendarBtn) {
        saveCalendarBtn.addEventListener('click', saveCalendarSettings);
    }

    // Chatスペース設定保存ボタン
    const saveChatSpaceBtn = document.getElementById('saveChatSpaceBtn');
    if (saveChatSpaceBtn) {
        saveChatSpaceBtn.addEventListener('click', saveChatSpaceConfig);
    }
});

// カレンダー一覧を取得
(function() {
    fetch('../api/calendar-settings.php', {
        headers: { 'X-CSRF-Token': calendarCsrfToken }
    })
    .then(r => r.json())
    .then(data => {
        const container = document.getElementById('calendarList');
        const saveBtn = document.getElementById('saveCalendarBtn');

        if (data.error) {
            container.innerHTML = '<p   class="text-red">エラー: ' + escapeHtml(data.error) + '</p>';
            return;
        }

        const calendars = data.data?.calendars || [];
        if (calendars.length === 0) {
            container.innerHTML = '<p   class="text-gray-500">利用可能なカレンダーがありません</p>';
            return;
        }

        let html = '';
        calendars.forEach(cal => {
            const checked = cal.selected ? 'checked' : '';
            const primaryBadge = cal.primary ? '<span class="calendar-badge primary">メイン</span>' : '';
            html += `
                <label class="calendar-item ${cal.selected ? 'selected' : ''}" data-id="${escapeHtml(cal.id)}">
                    <input type="checkbox" class="calendar-checkbox" value="${escapeHtml(cal.id)}" ${checked}>
                    <span         class="calendar-color" style="background: ${escapeHtml(cal.backgroundColor)}"></span>
                    <span class="calendar-name">${escapeHtml(cal.name)}</span>
                    ${primaryBadge}
                </label>
            `;
        });
        container.innerHTML = html;
        saveBtn.style.display = '';

        // チェックボックス変更時のスタイル更新
        container.querySelectorAll('.calendar-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const item = this.closest('.calendar-item');
                if (this.checked) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
        });
    })
    .catch(err => {
        console.error('Error loading calendars:', err);
        document.getElementById('calendarList').innerHTML = '<p   class="text-red">カレンダーの読み込みに失敗しました</p>';
    });
})();

function saveCalendarSettings() {
    const checkboxes = document.querySelectorAll('#calendarList .calendar-checkbox:checked');
    const calendarIds = Array.from(checkboxes).map(cb => cb.value);

    if (calendarIds.length === 0) {
        if (!confirm('カレンダーが選択されていません。全てのカレンダーを表示しますか？')) {
            return;
        }
    }

    fetch('../api/calendar-settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': calendarCsrfToken
        },
        body: JSON.stringify({ calendar_ids: calendarIds })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('カレンダー設定を保存しました');
        } else {
            alert('エラー: ' + (data.message || '保存に失敗しました'));
        }
    })
    .catch(err => {
        console.error('Error saving calendar settings:', err);
        alert('通信エラーが発生しました');
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
<?php endif; ?>

<?php elseif ($activeTab === 'google_chat'): ?>
<!-- Google Chat連携 -->
<?php if ($chatSuccess): ?>
<div class="alert alert-success"><?= htmlspecialchars($chatSuccess) ?></div>
<?php endif; ?>
<?php if ($chatError): ?>
<div class="alert alert-error"><?= htmlspecialchars($chatError) ?></div>
<?php endif; ?>
<div class="setting-card">
    <div    class="d-flex justify-between mb-2 align-start">
        <div>
            <h3>Google Chat連携</h3>
            <p>Google Chatからアルコールチェック画像を取り込みます</p>
        </div>
        <?php if ($googleChat->isConfigured()): ?>
            <span class="status-badge success">✓ 連携済み</span>
        <?php else: ?>
            <span class="status-badge warning">未連携</span>
        <?php endif; ?>
    </div>

    <?php if ($googleChat->isConfigured()): ?>
    <!-- スペース設定 -->
    <div        class="mt-3 border-top-gray-200">
        <h4        class="text-base text-gray-900" class="m-0-05">アルコールチェック同期元スペース</h4>
        <p       class="text-gray-600 text-14" class="m-0-1">画像を取得するGoogle Chatスペースを選択します</p>
        <div  class="d-flex gap-1 align-center flex-wrap">
            <select id="chatSpaceSelect"         class="form-input flex-1 max-w-400 min-w-200">
                <option value="">読み込み中...</option>
            </select>
            <button type="button" id="saveChatSpaceBtn" class="btn btn-primary">保存</button>
        </div>
        <?php if (!empty($alcoholChatConfig['space_name'])): ?>
        <p       class="text-2xs text-gray-600 mt-075 mb-0">
            現在の設定: <strong><?= htmlspecialchars($alcoholChatConfig['space_name']) ?></strong>
        </p>
        <?php endif; ?>
        <div id="spaceConfigStatus"    class="d-none mt-1 p-1 rounded text-2xs"></div>
    </div>
    <?php endif; ?>

    <div class="setting-actions">
        <?php if ($googleChat->isConfigured()): ?>
            <form method="POST"  class="d-inline" class="disconnect-chat-form">
                <?= csrfTokenField() ?>
                <button type="submit" name="disconnect_chat" class="btn btn-secondary">連携解除</button>
            </form>
        <?php else: ?>
            <?php if ($googleOAuth->isConfigured()): ?>
                <a href="<?= htmlspecialchars($googleChat->getAuthUrl()) ?>" class="btn btn-primary">Google Chatを連携</a>
                <p      class="text-xs text-gray-500 mt-1">※ Google Cloud Consoleで Chat API を有効にしてください</p>
            <?php else: ?>
                <span   class="text-gray-500 text-14">※先にGoogle OAuthを設定してください</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($activeTab === 'mf_invoice'): ?>
<!-- MF請求書連携設定 -->
<div class="setting-card">
    <div    class="d-flex justify-between mb-2 align-start">
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

<?php elseif ($activeTab === 'notification'): ?>
<!-- 通知設定 -->
<div class="setting-card">
    <div    class="d-flex justify-between mb-2 align-start">
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

<?php elseif ($activeTab === 'api_integration'): ?>
<!-- API連携設定 -->
<div class="setting-card">
    <div    class="d-flex justify-between mb-2 align-start">
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

<?php elseif ($activeTab === 'user_permissions'): ?>
<!-- アカウント権限設定 -->
<div class="setting-card">
    <div  class="mb-2">
        <h3>アカウント権限設定</h3>
        <p>各ユーザーの閲覧・編集権限を設定します</p>
    </div>
    <div class="setting-actions">
        <a href="user-permissions.php" class="btn btn-primary">権限設定</a>
    </div>
</div>

<?php elseif ($activeTab === 'employees'): ?>
<!-- 従業員マスタ -->
<div class="setting-card">
    <div  class="mb-2">
        <h3>従業員マスタ</h3>
        <p>従業員情報の管理を行います</p>
    </div>
    <div class="setting-actions">
        <a href="employees.php" class="btn btn-primary">従業員マスタ</a>
    </div>
</div>

<?php elseif ($activeTab === 'audit_log'): ?>
<!-- 操作ログ -->
<div class="setting-card">
    <div  class="mb-2">
        <h3>操作ログ</h3>
        <p>システムの操作履歴を確認できます</p>
    </div>
    <div class="setting-actions">
        <a href="audit-log.php" class="btn btn-primary">操作ログを確認</a>
    </div>
</div>

<?php endif; ?>
<?php endif; ?>

<?php if ($googleChat->isConfigured() && $activeTab === 'google_chat'): ?>
<script<?= nonceAttr() ?>>
const csrfToken = '<?= generateCsrfToken() ?>';

// ページ読み込み時にスペース一覧を取得
document.addEventListener('DOMContentLoaded', function() {
    loadChatSpaces();
});

// スペース一覧を読み込み
function loadChatSpaces() {
    const select = document.getElementById('chatSpaceSelect');
    if (!select) return;

    select.innerHTML = '<option value="">読み込み中...</option>';

    fetch(location.origin + '/api/alcohol-chat-sync.php?action=get_spaces')
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                select.innerHTML = '<option value="">エラー: ' + escapeHtml(data.error) + '</option>';
                return;
            }

            const spaces = data.spaces || [];
            if (spaces.length === 0) {
                select.innerHTML = '<option value="">スペースが見つかりません</option>';
                return;
            }

            const savedSpaceId = <?= json_encode($alcoholChatConfig['space_id'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            select.innerHTML = '<option value="">スペースを選択...</option>';
            spaces.forEach(space => {
                const opt = document.createElement('option');
                opt.value = space.name;
                opt.textContent = space.displayName;
                if (space.name === savedSpaceId) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            });
        })
        .catch(err => {
            select.innerHTML = '<option value="">読み込みエラー</option>';
        });
}

// スペース設定を保存
function saveChatSpaceConfig() {
    const select = document.getElementById('chatSpaceSelect');
    const statusDiv = document.getElementById('spaceConfigStatus');
    const spaceId = select.value;
    const spaceName = select.options[select.selectedIndex]?.text || '';

    if (!spaceId) {
        statusDiv.style.display = 'block';
        statusDiv.style.background = '#ffebee';
        statusDiv.style.color = '#c62828';
        statusDiv.textContent = 'スペースを選択してください';
        return;
    }

    statusDiv.style.display = 'block';
    statusDiv.style.background = '#e3f2fd';
    statusDiv.style.color = '#1565c0';
    statusDiv.textContent = '保存中...';

    fetch(location.origin + '/api/alcohol-chat-sync.php?action=save_config', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken
        },
        body: 'space_id=' + encodeURIComponent(spaceId) + '&space_name=' + encodeURIComponent(spaceName)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statusDiv.style.background = '#e8f5e9';
            statusDiv.style.color = '#2e7d32';
            statusDiv.textContent = 'スペース設定を保存しました: ' + spaceName;
        } else {
            statusDiv.style.background = '#ffebee';
            statusDiv.style.color = '#c62828';
            statusDiv.textContent = 'エラー: ' + (data.error || '保存に失敗しました');
        }
        setTimeout(() => { statusDiv.style.display = 'none'; }, 3000);
    })
    .catch(err => {
        statusDiv.style.background = '#ffebee';
        statusDiv.style.color = '#c62828';
        statusDiv.textContent = 'エラーが発生しました';
    });
}
</script>
<?php endif; ?>

</div><!-- /.page-container -->

<?php require_once '../functions/footer.php'; ?>
