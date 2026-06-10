<?php
require_once '../api/auth.php';
require_once '../api/mf-api.php';
require_once '../functions/notification-functions.php';
require_once '../api/integration/api-auth.php';
require_once '../api/google-oauth.php';
require_once '../api/google-calendar.php';
require_once '../api/google-chat.php';
require_once '../api/google-gmail.php';

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
$googleGmail = new GoogleGmailClient();

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// Gmail連携解除
if (isset($_POST['disconnect_gmail'])) {
    $googleGmail->disconnect();
    $_SESSION['gmail_success'] = 'Gmail連携を解除しました';
    header('Location: settings.php?tab=gmail');
    exit;
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
$gmailSuccess = $_SESSION['gmail_success'] ?? null;
$gmailError = $_SESSION['gmail_error'] ?? null;
unset($_SESSION['calendar_success'], $_SESSION['calendar_error'], $_SESSION['chat_success'], $_SESSION['chat_error'], $_SESSION['gmail_success'], $_SESSION['gmail_error']);

// タブ切り替え（空の場合は一覧表示）
$activeTab = $_GET['tab'] ?? '';

// 設定カテゴリの定義（表示順）
$settingCategories = [
    'google'    => ['label' => 'Google 連携',       'icon' => '<path d="M21 12a9 9 0 1 1-9-9m9 9h-9V3"/>'],
    'business'  => ['label' => '業務システム連携',  'icon' => '<path d="M3 9h18M9 3v18"/><rect x="3" y="3" width="18" height="18" rx="2"/>'],
    'operation' => ['label' => '通知・運用',        'icon' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'],
    'cms'       => ['label' => 'HP管理 (CMS)',      'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
    'account'   => ['label' => 'アカウント・権限',  'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'],
    'audit'     => ['label' => '監査・診断',        'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
    'export'    => ['label' => 'データ出力 (CSV)',  'icon' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>'],
    'import'    => ['label' => 'データ取込 (CSV)',  'icon' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>'],
];

// 設定項目の定義
$settingTypes = [
    'google_oauth' => [
        'name' => 'Googleログイン',
        'category' => 'google',
        'description' => 'Googleアカウントでのログインを有効にします',
        'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'status' => $googleOAuth->isConfigured(),
        'status_label' => $googleOAuth->isConfigured() ? '設定済み' : '未設定',
    ],
    'google_calendar' => [
        'name' => 'Googleカレンダー連携',
        'category' => 'google',
        'description' => 'ダッシュボードに今日の予定を表示します',
        'icon' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'status' => $googleCalendar->isConfigured(),
        'status_label' => $googleCalendar->isConfigured() ? '連携済み' : '未連携',
    ],
    'google_chat' => [
        'name' => 'Google Chat連携',
        'category' => 'google',
        'description' => 'アルコールチェック画像を取り込みます',
        'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'status' => $googleChat->isConfigured(),
        'status_label' => $googleChat->isConfigured() ? '連携済み' : '未連携',
    ],
    'gmail' => [
        'name' => 'Gmail連携',
        'category' => 'google',
        'description' => '社内連絡先からメールを送信します',
        'icon' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'status' => $googleGmail->isConfigured(),
        'status_label' => $googleGmail->isConfigured() ? '連携済み' : '未連携',
    ],
    'mf_invoice' => [
        'name' => 'MF請求書連携',
        'category' => 'business',
        'description' => 'MoneyForward請求書とのAPI連携',
        'icon' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'status' => MFApiClient::isConfigured(),
        'status_label' => MFApiClient::isConfigured() ? '設定済み' : '未設定',
    ],
    'notification' => [
        'name' => '通知設定',
        'category' => 'operation',
        'description' => 'トラブル発生時のメール通知を設定',
        'icon' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'status' => $notificationConfig['enabled'],
        'status_label' => $notificationConfig['enabled'] ? '有効' : '無効',
    ],
    'api_integration' => [
        'name' => 'API連携設定',
        'category' => 'business',
        'description' => '外部システムとのAPI連携を設定',
        'icon' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'status' => $integrationConfig['enabled'],
        'status_label' => $integrationConfig['enabled'] ? '有効' : '無効',
    ],
    'user_permissions' => [
        'name' => 'アカウント権限設定',
        'category' => 'account',
        'description' => '各ユーザーの閲覧・編集権限を設定',
        'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'status' => null,
        'status_label' => '',
    ],
    'cms_news' => [
        'name' => 'HP更新 設定',
        'category' => 'cms',
        'description' => 'GitHub PAT・リポジトリ等を登録',
        'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'status' => (function() {
            if (!function_exists('cmsConfigIsReady')) {
                require_once __DIR__ . '/../api/cms/cms-config.php';
            }
            return cmsConfigIsReady();
        })(),
        'status_label' => (function() {
            if (!function_exists('cmsConfigIsReady')) {
                require_once __DIR__ . '/../api/cms/cms-config.php';
            }
            return cmsConfigIsReady() ? '設定済み' : '未設定';
        })(),
    ],
    'employees' => [
        'name' => '従業員マスタ',
        'category' => 'account',
        'description' => '従業員情報の管理を行います',
        'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'status' => null,
        'status_label' => '',
    ],
    'audit_log' => [
        'name' => '操作ログ',
        'category' => 'audit',
        'description' => 'システムの操作履歴を確認',
        'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'status' => null,
        'status_label' => '',
    ],
    'sessions' => [
        'name' => 'セッション管理',
        'category' => 'account',
        'description' => 'ログイン中のセッションを管理',
        'icon' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'status' => null,
        'status_label' => '',
    ],
    'google_drive_folders' => [
        'name' => 'Google Drive保存先',
        'category' => 'google',
        'description' => '値引き申請PDF・週報添付ファイルの保存先フォルダ',
        'icon' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'status' => (function() {
            $d = __DIR__ . '/../config/discount-approvals-drive-config.json';
            $w = __DIR__ . '/../config/weekly-reports-drive-config.json';
            $dOk = file_exists($d) && !empty(json_decode(file_get_contents($d), true)['folder_id']);
            $wOk = file_exists($w) && !empty(json_decode(file_get_contents($w), true)['folder_id']);
            return $dOk && $wOk;
        })(),
        'status_label' => (function() {
            $d = __DIR__ . '/../config/discount-approvals-drive-config.json';
            $w = __DIR__ . '/../config/weekly-reports-drive-config.json';
            $dOk = file_exists($d) && !empty(json_decode(file_get_contents($d), true)['folder_id']);
            $wOk = file_exists($w) && !empty(json_decode(file_get_contents($w), true)['folder_id']);
            if ($dOk && $wOk) return '設定済み';
            if ($dOk || $wOk) return '一部設定済み';
            return '未設定';
        })(),
    ],
    'csv_export' => [
        'name' => 'CSVダウンロード',
        'category' => 'export',
        'description' => 'トラブル・アルコール・請求書のCSVを一括で出力',
        'icon' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'status' => null,
        'status_label' => '',
    ],
    'csv_import' => [
        'name' => 'CSVインポート',
        'category' => 'import',
        'description' => '案件・顧客・従業員等のデータをCSVから一括取込',
        'icon' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'status' => null,
        'status_label' => '',
    ],
    'maintenance' => [
        'name' => 'メンテナンスモード',
        'category' => 'operation',
        'description' => 'メンテナンス中は管理部以外のアクセスをブロック',
        'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'status' => (function() {
            $f = __DIR__ . '/../config/maintenance.json';
            if (!file_exists($f)) return false;
            $d = json_decode(file_get_contents($f), true);
            return !empty($d['enabled']);
        })(),
        'status_label' => (function() {
            $f = __DIR__ . '/../config/maintenance.json';
            if (!file_exists($f)) return '無効';
            $d = json_decode(file_get_contents($f), true);
            return !empty($d['enabled']) ? '有効（ブロック中）' : '無効';
        })(),
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

/* === セクション・検索（IA再構成） === */
.settings-search {
    position: relative;
    margin-bottom: 1.25rem;
    max-width: 540px;
}
.settings-search .form-input {
    padding-left: 2.4rem;
    padding-right: 2.4rem;
}
.settings-search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: var(--gray-500, #6b7280);
    pointer-events: none;
}
.settings-search-clear {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    color: var(--gray-500, #6b7280);
    font-size: 18px;
    cursor: pointer;
    border-radius: 50%;
    line-height: 1;
}
.settings-search-clear:hover {
    background: var(--gray-100, #f3f4f6);
    color: var(--gray-700, #374151);
}
.settings-search-empty {
    padding: 1.5rem;
    text-align: center;
    color: var(--gray-500, #6b7280);
    background: var(--gray-50, #f9fafb);
    border: 1px dashed var(--gray-200, #e5e7eb);
    border-radius: 8px;
}

.settings-section {
    margin-bottom: 1.5rem;
}
.settings-section.is-empty {
    display: none;
}
.settings-section-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin: 1.25rem 0 0.85rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--gray-200, #e5e7eb);
}
.settings-section:first-of-type .settings-section-header {
    margin-top: 0.5rem;
}
.settings-section-icon {
    width: 20px;
    height: 20px;
    color: var(--gray-600, #4b5563);
    flex-shrink: 0;
}
.settings-section-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--gray-700, #374151);
}
.settings-section-count {
    margin-left: auto;
    font-size: 0.8rem;
    color: var(--gray-500, #6b7280);
    font-weight: 400;
}
.settings-section-unset {
    color: #d97706;
    margin-left: 0.25rem;
}

.settings-select-card.is-hidden {
    display: none;
}
</style>

<div class="page-container">
<div class="page-header">
    <h2>設定</h2>
    <?php if (!empty($activeTab) && isset($settingTypes[$activeTab])): ?>
    <?= uiBackButton('list', ['href' => 'settings.php']) ?>
    <?php endif; ?>
</div>

<?php if (empty($activeTab) || !isset($settingTypes[$activeTab])): ?>
<!-- 設定選択画面 -->
<?php
// 直接リンク先を定義
$directLinks = [
    'google_oauth' => 'google-oauth-settings.php',
    'google_calendar' => 'settings.php?tab=google_calendar',
    'google_chat' => 'settings.php?tab=google_chat',
    'gmail' => 'settings.php?tab=gmail',
    'mf_invoice' => 'mf-settings.php',
    'notification' => 'notification-settings.php',
    'api_integration' => 'integration-settings.php',
    'user_permissions' => 'user-permissions.php',
    'cms_news' => 'cms-settings.php',
    'employees' => 'employees.php',
    'audit_log' => 'audit-log.php',
    'sessions' => 'sessions.php',
    'google_drive_folders' => 'settings.php?tab=google_drive_folders',
    'maintenance' => 'settings.php?tab=maintenance',
    'csv_export' => 'settings.php?tab=csv_export',
    'csv_import' => 'settings.php?tab=csv_import',
];

// 項目をカテゴリごとにグループ化（カテゴリ未指定は最後の "other" に分類）
$groupedTypes = [];
foreach ($settingTypes as $key => $setting) {
    if ($key === 'employees' && !canEdit()) continue;
    $cat = $setting['category'] ?? 'other';
    $groupedTypes[$cat][$key] = $setting;
}

// 検索用 normalized 文字列
function settingsSearchKey(string $key, array $setting): string {
    return mb_strtolower($key . ' ' . ($setting['name'] ?? '') . ' ' . ($setting['description'] ?? ''));
}
?>

<!-- 検索ボックス -->
<div class="settings-search">
    <svg class="settings-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" id="settingsSearchInput" class="form-input" placeholder="設定を検索（例: カレンダー、通知、HP）" autocomplete="off">
    <button type="button" id="settingsSearchClear" class="settings-search-clear" aria-label="クリア" style="display:none;">×</button>
</div>
<div id="settingsSearchEmpty" class="settings-search-empty" style="display:none;">該当する設定がありません</div>

<?php foreach ($settingCategories as $catKey => $cat): ?>
    <?php if (empty($groupedTypes[$catKey])) continue; ?>
    <?php
        $items = $groupedTypes[$catKey];
        $total = count($items);
        $unset = 0;
        foreach ($items as $s) {
            if (isset($s['status']) && $s['status'] === false) $unset++;
        }
    ?>
    <section class="settings-section" data-category="<?= htmlspecialchars($catKey) ?>">
        <div class="settings-section-header">
            <svg class="settings-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $cat['icon'] ?></svg>
            <span class="settings-section-title"><?= htmlspecialchars($cat['label']) ?></span>
            <span class="settings-section-count">
                <?= $total ?> 件<?php if ($unset > 0): ?> <span class="settings-section-unset">(<?= $unset ?> 件未設定)</span><?php endif; ?>
            </span>
        </div>
        <div class="settings-select-grid">
            <?php foreach ($items as $key => $setting): ?>
            <a href="<?= htmlspecialchars($directLinks[$key] ?? '#') ?>"
               class="settings-select-card"
               data-search="<?= htmlspecialchars(settingsSearchKey($key, $setting), ENT_QUOTES) ?>">
                <div class="settings-select-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $setting['icon'] ?></svg>
                </div>
                <div class="settings-select-info">
                    <div class="settings-select-name">
                        <?= htmlspecialchars($setting['name']) ?>
                        <?php if ($setting['status'] !== null): ?>
                            <span class="status-badge <?= $setting['status'] ? 'success' : 'warning' ?>"><?= htmlspecialchars($setting['status_label']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="settings-select-desc"><?= htmlspecialchars($setting['description']) ?></div>
                </div>
                <svg class="settings-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php
// カテゴリ未指定の項目があれば最後に表示（防御）
if (!empty($groupedTypes['other'])):
?>
<section class="settings-section" data-category="other">
    <div class="settings-section-header">
        <span class="settings-section-title">その他</span>
    </div>
    <div class="settings-select-grid">
        <?php foreach ($groupedTypes['other'] as $key => $setting): ?>
        <a href="<?= htmlspecialchars($directLinks[$key] ?? '#') ?>"
           class="settings-select-card"
           data-search="<?= htmlspecialchars(settingsSearchKey($key, $setting), ENT_QUOTES) ?>">
            <div class="settings-select-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $setting['icon'] ?></svg>
            </div>
            <div class="settings-select-info">
                <div class="settings-select-name"><?= htmlspecialchars($setting['name']) ?></div>
                <div class="settings-select-desc"><?= htmlspecialchars($setting['description']) ?></div>
            </div>
            <svg class="settings-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php else: ?>
<!-- 設定詳細画面 -->
<div class="settings-detail-header">
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
            <form method="POST"  class="d-inline disconnect-calendar-form">
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
// escapeHtml は js/common-utils.js で定義済み

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
(async function() {
    try {
        const data = await (await fetch('../api/calendar-settings.php', {
            headers: { 'X-CSRF-Token': calendarCsrfToken }
        })).json();
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
    } catch (err) {
        console.error('Error loading calendars:', err);
        document.getElementById('calendarList').innerHTML = '<p   class="text-red">カレンダーの読み込みに失敗しました</p>';
    }
})();

async function saveCalendarSettings() {
    const checkboxes = document.querySelectorAll('#calendarList .calendar-checkbox:checked');
    const calendarIds = Array.from(checkboxes).map(cb => cb.value);

    if (calendarIds.length === 0) {
        if (!confirm('カレンダーが選択されていません。全てのカレンダーを表示しますか？')) {
            return;
        }
    }

    try {
        const data = await (await fetch('../api/calendar-settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': calendarCsrfToken
            },
            body: JSON.stringify({ calendar_ids: calendarIds })
        })).json();
        if (data.success) {
            alert('カレンダー設定を保存しました');
        } else {
            alert('エラー: ' + (data.message || '保存に失敗しました'));
        }
    } catch (err) {
        console.error('Error saving calendar settings:', err);
        alert('通信エラーが発生しました');
    }
}

// escapeHtml は js/common-utils.js で定義済み
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
        <h4        class="text-base text-gray-900 m-0-05">アルコールチェック同期元スペース</h4>
        <p       class="text-gray-600 text-14 m-0-1">画像を取得するGoogle Chatスペースを選択します</p>
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
            <form method="POST"  class="d-inline disconnect-chat-form">
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

<?php elseif ($activeTab === 'gmail'): ?>
<!-- Gmail連携 -->
<?php if ($gmailSuccess): ?>
<div class="alert alert-success"><?= htmlspecialchars($gmailSuccess) ?></div>
<?php endif; ?>
<?php if ($gmailError): ?>
<div class="alert alert-error"><?= htmlspecialchars($gmailError) ?></div>
<?php endif; ?>
<div class="setting-card">
    <div class="d-flex justify-between mb-2 align-start">
        <div>
            <h3>Gmail連携</h3>
            <p>社内連絡先ページからメールを直接送信できます</p>
        </div>
        <?php if ($googleGmail->isConfigured()): ?>
            <span class="status-badge success">✓ 連携済み</span>
        <?php else: ?>
            <span class="status-badge warning">未連携</span>
        <?php endif; ?>
    </div>

    <div class="setting-actions">
        <?php if ($googleGmail->isConfigured()): ?>
            <form method="POST" class="d-inline disconnect-gmail-form">
                <?= csrfTokenField() ?>
                <button type="submit" name="disconnect_gmail" class="btn btn-secondary">連携解除</button>
            </form>
        <?php else: ?>
            <?php if ($googleOAuth->isConfigured()): ?>
                <a href="<?= htmlspecialchars($googleGmail->getAuthUrl()) ?>" class="btn btn-primary">Gmailを連携</a>
                <p class="text-xs text-gray-500 mt-1">※ Google Cloud Consoleで Gmail API を有効にしてください</p>
            <?php else: ?>
                <span class="text-gray-500 text-14">※先にGoogle OAuthを設定してください</span>
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

<?php elseif ($activeTab === 'google_drive_folders'): ?>
<!-- Google Drive保存先フォルダ設定 -->
<?php
$discountDriveCfgFile = __DIR__ . '/../config/discount-approvals-drive-config.json';
$weeklyDriveCfgFile   = __DIR__ . '/../config/weekly-reports-drive-config.json';
$discountDriveCfg = file_exists($discountDriveCfgFile) ? (json_decode(file_get_contents($discountDriveCfgFile), true) ?? []) : [];
$weeklyDriveCfg   = file_exists($weeklyDriveCfgFile)   ? (json_decode(file_get_contents($weeklyDriveCfgFile), true) ?? [])   : [];
?>
<div class="setting-card">
    <div class="d-flex justify-between mb-2 align-start">
        <div>
            <h3>値引き申請 PDF保存先</h3>
            <p>値引き申請で添付したPDFを保存するGoogle Driveフォルダを設定します。</p>
        </div>
        <?php if (!empty($discountDriveCfg['folder_id'])): ?>
            <span class="status-badge success">✓ 設定済み</span>
        <?php else: ?>
            <span class="status-badge warning">未設定</span>
        <?php endif; ?>
    </div>

    <div style="background:#f9fafb;border-radius:8px;padding:1rem;margin-bottom:1rem;">
        <p style="font-size:0.85rem;color:var(--gray-600);margin-bottom:0.75rem;">
            Google DriveのフォルダURLから <code>folders/</code> の後ろの部分をコピーしてください。<br>
            例: https://drive.google.com/drive/folders/<strong>1abc...xyz</strong>
        </p>
        <?php if (!empty($discountDriveCfg['folder_id'])): ?>
        <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:6px;padding:0.5rem 0.75rem;margin-bottom:0.75rem;font-size:0.85rem;">
            現在の設定: <strong><?= htmlspecialchars($discountDriveCfg['folder_name'] ?: $discountDriveCfg['folder_id']) ?></strong>
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label">フォルダID <span style="color:#c62828;">*</span></label>
            <input type="text" class="form-input" id="discountDriveFolderId" value="<?= htmlspecialchars($discountDriveCfg['folder_id'] ?? '') ?>" placeholder="例: 1iCPEOmRroKpI1N_Iyi1mWFlfsPJRNiXa">
        </div>
        <div class="form-group">
            <label class="form-label">フォルダ名（表示用・任意）</label>
            <input type="text" class="form-input" id="discountDriveFolderName" value="<?= htmlspecialchars($discountDriveCfg['folder_name'] ?? '') ?>" placeholder="例: 値引き承認PDF">
        </div>
    </div>

    <div style="display:flex;gap:0.75rem;align-items:center;">
        <button class="btn btn-primary" id="btnSaveDiscountDrive">保存</button>
        <span id="discountDriveFlash" style="display:none;font-size:0.85rem;"></span>
    </div>
</div>

<div class="setting-card" style="margin-top:1.5rem;">
    <div class="d-flex justify-between mb-2 align-start">
        <div>
            <h3>週報 添付ファイル保存先</h3>
            <p>週報に添付した画像・PDFを保存するGoogle Driveフォルダを設定します。</p>
        </div>
        <?php if (!empty($weeklyDriveCfg['folder_id'])): ?>
            <span class="status-badge success">✓ 設定済み</span>
        <?php else: ?>
            <span class="status-badge warning">未設定</span>
        <?php endif; ?>
    </div>

    <div style="background:#f9fafb;border-radius:8px;padding:1rem;margin-bottom:1rem;">
        <p style="font-size:0.85rem;color:var(--gray-600);margin-bottom:0.75rem;">
            Google DriveのフォルダURLから <code>folders/</code> の後ろの部分をコピーしてください。<br>
            例: https://drive.google.com/drive/folders/<strong>1abc...xyz</strong>
        </p>
        <?php if (!empty($weeklyDriveCfg['folder_id'])): ?>
        <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:6px;padding:0.5rem 0.75rem;margin-bottom:0.75rem;font-size:0.85rem;">
            現在の設定: <strong><?= htmlspecialchars($weeklyDriveCfg['folder_name'] ?: $weeklyDriveCfg['folder_id']) ?></strong>
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label">フォルダID <span style="color:#c62828;">*</span></label>
            <input type="text" class="form-input" id="weeklyDriveFolderId" value="<?= htmlspecialchars($weeklyDriveCfg['folder_id'] ?? '') ?>" placeholder="例: 1iCPEOmRroKpI1N_Iyi1mWFlfsPJRNiXa">
        </div>
        <div class="form-group">
            <label class="form-label">フォルダ名（表示用・任意）</label>
            <input type="text" class="form-input" id="weeklyDriveFolderName" value="<?= htmlspecialchars($weeklyDriveCfg['folder_name'] ?? '') ?>" placeholder="例: 週報添付ファイル">
        </div>
    </div>

    <div style="display:flex;gap:0.75rem;align-items:center;">
        <button class="btn btn-primary" id="btnSaveWeeklyDrive">保存</button>
        <span id="weeklyDriveFlash" style="display:none;font-size:0.85rem;"></span>
    </div>
</div>

<script<?= nonceAttr() ?>>
(function() {
    const csrfToken = <?= json_encode(generateCsrfToken()) ?>;

    function showFlash(el, msg, type) {
        el.textContent = msg;
        el.style.color = type === 'success' ? '#065f46' : '#991b1b';
        el.style.display = 'inline';
        setTimeout(() => { el.style.display = 'none'; }, 4000);
    }

    // 値引き申請 Drive保存先
    document.getElementById('btnSaveDiscountDrive').addEventListener('click', async () => {
        const id   = document.getElementById('discountDriveFolderId').value.trim();
        const name = document.getElementById('discountDriveFolderName').value.trim();
        const flash = document.getElementById('discountDriveFlash');
        if (!id) { showFlash(flash, 'フォルダIDを入力してください', 'error'); return; }

        try {
            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('type', 'approval');
            fd.append('action', 'set_drive_folder');
            fd.append('folder_id', id);
            fd.append('folder_name', name);
            const res = await fetch('/api/reports-hub-api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || '保存に失敗しました');
            showFlash(flash, '保存しました', 'success');
            setTimeout(() => location.reload(), 800);
        } catch (e) {
            showFlash(flash, e.message || '保存に失敗しました', 'error');
        }
    });

    // 週報 Drive保存先
    document.getElementById('btnSaveWeeklyDrive').addEventListener('click', async () => {
        const id   = document.getElementById('weeklyDriveFolderId').value.trim();
        const name = document.getElementById('weeklyDriveFolderName').value.trim();
        const flash = document.getElementById('weeklyDriveFlash');
        if (!id) { showFlash(flash, 'フォルダIDを入力してください', 'error'); return; }

        try {
            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('type', 'report');
            fd.append('action', 'set_drive_folder');
            fd.append('folder_id', id);
            fd.append('folder_name', name);
            const res = await fetch('/api/reports-hub-api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || '保存に失敗しました');
            showFlash(flash, '保存しました', 'success');
            setTimeout(() => location.reload(), 800);
        } catch (e) {
            showFlash(flash, e.message || '保存に失敗しました', 'error');
        }
    });
})();
</script>

<?php elseif ($activeTab === 'csv_export'): ?>
<!-- CSVダウンロード（集約） -->
<?php
$_currentYearMonth = date('Y-m');
$_today            = date('Y-m-d');
$_firstOfMonth     = date('Y-m-01');
$TROUBLE_STATUSES_EXPORT = ['未対応', '対応中', '保留', '完了'];
?>
<style<?= nonceAttr() ?>>
.csv-export-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1rem;
}
.csv-export-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 1.25rem;
}
.csv-export-card h3 {
    margin: 0 0 0.35rem 0;
    font-size: 1rem;
    color: var(--gray-900);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.csv-export-card h3 svg {
    width: 18px;
    height: 18px;
    color: var(--primary, #3b82f6);
}
.csv-export-card .desc {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-bottom: 0.9rem;
}
.csv-export-card .field {
    margin-bottom: 0.6rem;
}
.csv-export-card .field label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.25rem;
}
.csv-export-card .field-row {
    display: flex;
    gap: 0.5rem;
}
.csv-export-card .field-row > * {
    flex: 1;
}
.csv-export-actions {
    margin-top: 0.75rem;
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}
</style>

<div class="setting-card" style="margin-bottom:1rem;">
    <h3 style="margin-top:0;">CSVダウンロード</h3>
    <p style="margin:0;color:var(--gray-600);font-size:0.875rem;">
        各機能ページに分散していたCSVエクスポート機能をここに集約しています。期間・条件を指定してダウンロードしてください。
    </p>
</div>

<div class="csv-export-grid">

    <!-- トラブル一覧 -->
    <form method="GET" action="download-troubles-csv.php" class="csv-export-card">
        <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            トラブル一覧
        </h3>
        <div class="desc">条件で絞り込んだトラブル一覧をCSV出力します。</div>

        <div class="field">
            <label>ステータス</label>
            <select name="status" class="form-input">
                <option value="">すべて</option>
                <?php foreach ($TROUBLE_STATUSES_EXPORT as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>PJ番号 / キーワード検索</label>
            <input type="text" name="search" class="form-input" placeholder="PJ番号・内容・解決策で検索">
        </div>

        <div class="csv-export-actions">
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                CSVダウンロード
            </button>
        </div>
    </form>

    <!-- アルコールチェック -->
    <form method="GET" action="download-alcohol-check-csv.php" class="csv-export-card">
        <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2h-4l-1 8a4 4 0 0 0 6 0z"/><path d="M9 22h6"/><line x1="12" y1="13" x2="12" y2="22"/></svg>
            アルコールチェック
        </h3>
        <div class="desc">指定期間のアルコールチェック結果をCSV出力します。</div>

        <div class="field">
            <label>期間（開始 〜 終了）</label>
            <div class="field-row">
                <input type="date" name="start_date" class="form-input" value="<?= htmlspecialchars($_firstOfMonth) ?>" required>
                <input type="date" name="end_date"   class="form-input" value="<?= htmlspecialchars($_today) ?>" required>
            </div>
        </div>

        <div class="csv-export-actions">
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                CSVダウンロード
            </button>
        </div>
    </form>

    <!-- 請求書（MF） -->
    <form method="GET" action="download-invoices-csv.php" class="csv-export-card">
        <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            請求書 (MF)
        </h3>
        <div class="desc">対象月・タグで絞り込んだ請求書一覧をCSV出力します。</div>

        <div class="field">
            <label>対象月 (YYYY-MM)</label>
            <input type="month" name="year_month" class="form-input" value="<?= htmlspecialchars($_currentYearMonth) ?>">
        </div>
        <div class="field">
            <label>タグ検索（カンマ区切りでOR検索）</label>
            <input type="text" name="search_tag" class="form-input" placeholder="例: 案件A, 案件B">
        </div>

        <div class="csv-export-actions">
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                CSVダウンロード
            </button>
        </div>
    </form>

</div>

<?php elseif ($activeTab === 'csv_import'): ?>
<!-- CSVインポート -->
<style<?= nonceAttr() ?>>
.ci-wrap { max-width: 800px; }
.ci-step { background: white; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1.25rem; margin-bottom: 1rem; }
.ci-step h3 { margin: 0 0 0.5rem 0; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem; }
.ci-step h3 .ci-num { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--primary, #3b82f6); color: white; font-size: 0.75rem; font-weight: 700; flex-shrink: 0; }
.ci-desc { font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.75rem; }
.ci-drop { border: 2px dashed var(--gray-300); border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer; transition: border-color 0.15s, background 0.15s; }
.ci-drop:hover, .ci-drop.dragover { border-color: var(--primary, #3b82f6); background: rgba(59,130,246,0.04); }
.ci-drop svg { display: block; margin: 0 auto 0.5rem; color: var(--gray-400); }
.ci-drop-label { font-size: 0.875rem; color: var(--gray-600); }
.ci-drop-sub { font-size: 0.75rem; color: var(--gray-400); margin-top: 0.25rem; }
.ci-file-info { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.75rem; padding: 0.5rem 0.75rem; background: var(--gray-50); border-radius: 6px; font-size: 0.8rem; }
.ci-file-info .ci-fname { flex: 1; font-weight: 600; color: var(--gray-800); }
.ci-file-info .ci-fsize { color: var(--gray-500); }
.ci-preview-wrap { margin-top: 0.75rem; }
.ci-preview-stats { display: flex; gap: 1rem; margin-bottom: 0.75rem; font-size: 0.8rem; }
.ci-preview-stats .ci-stat { padding: 0.35rem 0.75rem; border-radius: 6px; font-weight: 600; }
.ci-stat-total { background: var(--gray-100); color: var(--gray-700); }
.ci-stat-valid { background: #dcfce7; color: #166534; }
.ci-stat-error { background: #fee2e2; color: #991b1b; }
.ci-preview-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.ci-preview-table th { background: var(--gray-50); border: 1px solid var(--gray-200); padding: 0.4rem 0.6rem; font-weight: 600; text-align: left; white-space: nowrap; }
.ci-preview-table td { border: 1px solid var(--gray-200); padding: 0.35rem 0.6rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ci-preview-table tr.ci-row-error td { background: #fff5f5; }
.ci-preview-table .ci-err-cell { color: #dc2626; font-size: 0.72rem; }
.ci-table-scroll { overflow-x: auto; max-height: 400px; overflow-y: auto; border-radius: 6px; }
.ci-errors { margin-top: 0.5rem; padding: 0.6rem 0.8rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; font-size: 0.78rem; color: #991b1b; }
.ci-errors ul { margin: 0.25rem 0 0 1rem; padding: 0; }
.ci-result { padding: 1rem; border-radius: 8px; font-size: 0.875rem; }
.ci-result-ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.ci-result-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
.ci-actions { display: flex; gap: 0.5rem; margin-top: 0.75rem; justify-content: flex-end; }
.ci-hidden { display: none; }
.ci-tpl-links { display: flex; flex-wrap: wrap; gap: 0.4rem 0.75rem; margin-top: 0.5rem; }
.ci-tpl-links a { font-size: 0.78rem; color: var(--primary, #3b82f6); text-decoration: none; }
.ci-tpl-links a:hover { text-decoration: underline; }
</style>

<div class="ci-wrap">
    <div class="setting-card" style="margin-bottom:1rem;">
        <h3 style="margin-top:0;">CSVインポート</h3>
        <p style="margin:0;color:var(--gray-600);font-size:0.875rem;">
            CSVファイルからデータを一括取込します。Shift-JIS / UTF-8 どちらにも対応。
        </p>
    </div>

    <!-- STEP 1: エンティティ選択 -->
    <div class="ci-step">
        <h3><span class="ci-num">1</span> 取込先を選択</h3>
        <div class="ci-desc">インポートするデータの種類を選んでください。</div>
        <select id="ciEntity" class="form-input">
            <option value="">-- 選択してください --</option>
            <option value="projects">案件</option>
            <option value="troubles">トラブル対応</option>
            <option value="customers">顧客</option>
            <option value="employees">従業員</option>
            <option value="leads">リード</option>
            <option value="business_cards">名刺</option>
            <option value="contacts">社内連絡先</option>
            <option value="partners">協力会社</option>
            <option value="manufacturers">メーカー</option>
            <option value="weekly_reports">週報</option>
            <option value="discount_approvals">値引き申請</option>
        </select>
        <div id="ciTemplateArea" class="ci-hidden" style="margin-top:0.75rem;">
            <div style="font-size:0.8rem;color:var(--gray-600);">テンプレートCSV:</div>
            <div class="ci-tpl-links">
                <a id="ciTplLink" href="#" data-action="download-template">ヘッダ付きテンプレートをダウンロード</a>
            </div>
            <div id="ciExpectedCols" style="margin-top:0.5rem;font-size:0.75rem;color:var(--gray-500);"></div>
        </div>
    </div>

    <!-- STEP 2: ファイルアップロード -->
    <div class="ci-step" id="ciStep2">
        <h3><span class="ci-num">2</span> CSVファイルをアップロード</h3>
        <div class="ci-desc">ドラッグ&ドロップまたはクリックでファイルを選択。</div>
        <div id="ciDrop" class="ci-drop">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            <div class="ci-drop-label">CSVファイルをここにドロップ</div>
            <div class="ci-drop-sub">またはクリックして選択 (.csv / .tsv)</div>
        </div>
        <input type="file" id="ciFileInput" accept=".csv,.tsv,.txt" style="display:none;">
        <div id="ciFileInfo" class="ci-file-info ci-hidden">
            <span class="ci-fname" id="ciFileName"></span>
            <span class="ci-fsize" id="ciFileSize"></span>
            <button type="button" class="btn btn-sm" id="ciFileClear">変更</button>
        </div>
    </div>

    <!-- STEP 3: プレビュー & インポート -->
    <div class="ci-step ci-hidden" id="ciStep3">
        <h3><span class="ci-num">3</span> プレビュー</h3>
        <div id="ciPreviewContent"></div>
        <div class="ci-actions" id="ciImportActions" class="ci-hidden">
            <button type="button" class="btn" id="ciCancelBtn">キャンセル</button>
            <button type="button" class="btn btn-primary" id="ciImportBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                インポート実行
            </button>
        </div>
    </div>

    <!-- 結果表示 -->
    <div id="ciResult" class="ci-hidden"></div>
</div>

<script<?= nonceAttr() ?>>
(function(){
    var CSRF = <?= json_encode($csrfToken) ?>;
    var entitySel = document.getElementById('ciEntity');
    var tplArea   = document.getElementById('ciTemplateArea');
    var tplLink   = document.getElementById('ciTplLink');
    var colsInfo  = document.getElementById('ciExpectedCols');
    var dropZone  = document.getElementById('ciDrop');
    var fileInput = document.getElementById('ciFileInput');
    var fileInfo  = document.getElementById('ciFileInfo');
    var fname     = document.getElementById('ciFileName');
    var fsize     = document.getElementById('ciFileSize');
    var clearBtn  = document.getElementById('ciFileClear');
    var step3     = document.getElementById('ciStep3');
    var previewEl = document.getElementById('ciPreviewContent');
    var importBtn = document.getElementById('ciImportBtn');
    var cancelBtn = document.getElementById('ciCancelBtn');
    var resultEl  = document.getElementById('ciResult');
    var currentFile = null;
    var previewData = null;

    // エンティティのカラム情報（APIから取得）
    var entityMeta = {};
    fetch('/api/csv-import.php?action=entities')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                d.entities.forEach(function(e){ entityMeta[e.key] = e; });
            }
        });

    entitySel.addEventListener('change', function(){
        var ent = this.value;
        if (ent && entityMeta[ent]) {
            tplArea.classList.remove('ci-hidden');
            tplLink.href = '/api/csv-import.php?action=template&entity=' + ent;
            var cols = entityMeta[ent].columns;
            var req  = entityMeta[ent].required;
            var labels = Object.keys(cols).map(function(k){
                var lbl = cols[k];
                if (req.indexOf(k) !== -1) lbl += ' *';
                return lbl;
            });
            colsInfo.textContent = 'カラム: ' + labels.join(', ') + '  (* = 必須)';
        } else {
            tplArea.classList.add('ci-hidden');
        }
        resetPreview();
    });

    // ドラッグ&ドロップ
    dropZone.addEventListener('click', function(){ fileInput.click(); });
    dropZone.addEventListener('dragover', function(e){ e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', function(){ dropZone.classList.remove('dragover'); });
    dropZone.addEventListener('drop', function(e){
        e.preventDefault();
        dropZone.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) handleFile(e.dataTransfer.files[0]);
    });
    fileInput.addEventListener('change', function(){
        if (this.files.length > 0) handleFile(this.files[0]);
    });
    clearBtn.addEventListener('click', function(){
        fileInput.value = '';
        currentFile = null;
        fileInfo.classList.add('ci-hidden');
        dropZone.style.display = '';
        resetPreview();
    });

    function handleFile(f) {
        currentFile = f;
        fname.textContent = f.name;
        fsize.textContent = formatSize(f.size);
        fileInfo.classList.remove('ci-hidden');
        dropZone.style.display = 'none';
        doPreview();
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function resetPreview() {
        step3.classList.add('ci-hidden');
        previewEl.innerHTML = '';
        resultEl.classList.add('ci-hidden');
        resultEl.innerHTML = '';
        previewData = null;
    }

    function doPreview() {
        var ent = entitySel.value;
        if (!ent) { alert('取込先を選択してください'); return; }
        if (!currentFile) return;

        previewEl.innerHTML = '<div style="padding:1rem;text-align:center;color:var(--gray-500);">読み込み中...</div>';
        step3.classList.remove('ci-hidden');
        resultEl.classList.add('ci-hidden');

        var fd = new FormData();
        fd.append('action', 'preview');
        fd.append('entity', ent);
        fd.append('csrf_token', CSRF);
        fd.append('csv_file', currentFile);

        fetch('/api/csv-import.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.success) {
                    previewEl.innerHTML = '<div class="ci-errors">' + escapeHtml(d.error) + '</div>';
                    document.getElementById('ciImportActions').style.display = 'none';
                    return;
                }
                previewData = d;
                renderPreview(d);
            })
            .catch(function(e){
                previewEl.innerHTML = '<div class="ci-errors">通信エラー: ' + escapeHtml(e.message) + '</div>';
            });
    }

    function renderPreview(d) {
        var html = '';
        // 統計
        html += '<div class="ci-preview-stats">';
        html += '<span class="ci-stat ci-stat-total">' + d.totalRows + ' 件</span>';
        html += '<span class="ci-stat ci-stat-valid">' + d.validCount + ' 件 OK</span>';
        if (d.errorCount > 0) html += '<span class="ci-stat ci-stat-error">' + d.errorCount + ' 件 エラー</span>';
        html += '</div>';

        // エラーメッセージ
        if (d.errors && d.errors.length > 0) {
            html += '<div class="ci-errors"><strong>エラー行:</strong><ul>';
            d.errors.forEach(function(e){ html += '<li>' + escapeHtml(e) + '</li>'; });
            if (d.errorCount > d.errors.length) html += '<li>...他 ' + (d.errorCount - d.errors.length) + ' 件</li>';
            html += '</ul></div>';
        }

        // テーブル
        var cols = d.columns;
        var colKeys = Object.keys(cols);
        html += '<div class="ci-table-scroll"><table class="ci-preview-table"><thead><tr>';
        html += '<th>#</th>';
        colKeys.forEach(function(k){
            if (d.preview.length > 0 && d.preview[0].data[k] !== undefined) {
                html += '<th>' + escapeHtml(cols[k]) + '</th>';
            }
        });
        html += '</tr></thead><tbody>';

        d.preview.forEach(function(row){
            var cls = row.errors.length > 0 ? ' class="ci-row-error"' : '';
            html += '<tr' + cls + '>';
            html += '<td>' + row.row + '</td>';
            colKeys.forEach(function(k){
                if (row.data[k] !== undefined) {
                    html += '<td>' + escapeHtml(row.data[k] || '') + '</td>';
                }
            });
            html += '</tr>';
            if (row.errors.length > 0) {
                var span = colKeys.filter(function(k){ return row.data[k] !== undefined; }).length + 1;
                html += '<tr class="ci-row-error"><td colspan="' + span + '" class="ci-err-cell">' + row.errors.join(', ') + '</td></tr>';
            }
        });

        if (d.totalRows > 20) {
            var span = colKeys.filter(function(k){ return d.preview[0] && d.preview[0].data[k] !== undefined; }).length + 1;
            html += '<tr><td colspan="' + span + '" style="text-align:center;color:var(--gray-500);font-size:0.75rem;">... 他 ' + (d.totalRows - 20) + ' 件</td></tr>';
        }

        html += '</tbody></table></div>';
        previewEl.innerHTML = html;

        // ボタン表示
        var actionsEl = document.getElementById('ciImportActions');
        if (d.validCount > 0) {
            actionsEl.style.display = 'flex';
            importBtn.textContent = d.validCount + ' 件をインポート';
        } else {
            actionsEl.style.display = 'none';
        }
    }

    // インポート実行
    importBtn.addEventListener('click', function(){
        if (!previewData || !currentFile) return;
        if (!confirm(previewData.validCount + ' 件を「' + previewData.label + '」にインポートします。よろしいですか？')) return;

        importBtn.disabled = true;
        importBtn.textContent = 'インポート中...';

        var fd = new FormData();
        fd.append('action', 'import');
        fd.append('entity', entitySel.value);
        fd.append('csrf_token', CSRF);
        fd.append('csv_file', currentFile);

        fetch('/api/csv-import.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d){
                importBtn.disabled = false;
                importBtn.textContent = 'インポート実行';
                step3.classList.add('ci-hidden');

                if (d.success) {
                    var cls = d.skipped > 0 ? 'ci-result-warn' : 'ci-result-ok';
                    resultEl.className = 'ci-result ' + cls;
                    resultEl.innerHTML = '<strong>' + escapeHtml(d.message) + '</strong>';
                } else {
                    resultEl.className = 'ci-result ci-errors';
                    resultEl.innerHTML = escapeHtml(d.error);
                }
                resultEl.classList.remove('ci-hidden');
            })
            .catch(function(e){
                importBtn.disabled = false;
                importBtn.textContent = 'インポート実行';
                resultEl.className = 'ci-result ci-errors';
                resultEl.innerHTML = '通信エラー: ' + escapeHtml(e.message);
                resultEl.classList.remove('ci-hidden');
            });
    });

    cancelBtn.addEventListener('click', function(){
        resetPreview();
    });

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }
})();
</script>

<?php elseif ($activeTab === 'maintenance'): ?>
<!-- メンテナンスモード -->
<?php
$maintenanceFile = __DIR__ . '/../config/maintenance.json';
$maint = ['enabled' => false, 'message' => 'システムメンテナンス中です。しばらくお待ちください。', 'end_time' => null];
if (file_exists($maintenanceFile)) {
    $maint = array_merge($maint, json_decode(file_get_contents($maintenanceFile), true) ?? []);
}
?>
<div class="setting-card">
    <div class="d-flex justify-between mb-2 align-start">
        <div>
            <h3>メンテナンスモード</h3>
            <p>有効にすると、管理部（admin）以外の全ユーザーのアクセスをブロックし、メンテナンス画面を表示します。</p>
        </div>
        <span class="status-badge <?= $maint['enabled'] ? 'danger' : 'success' ?>" id="maintenanceStatusBadge">
            <?= $maint['enabled'] ? '⚠️ 有効（ブロック中）' : '✓ 無効' ?>
        </span>
    </div>

    <div style="background:#f9fafb;border-radius:8px;padding:1rem;margin-bottom:1rem;">
        <div style="margin-bottom:0.75rem;">
            <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:0.35rem;">表示メッセージ</label>
            <input type="text" id="maintenanceMessage" value="<?= htmlspecialchars($maint['message']) ?>"
                   maxlength="200" style="width:100%;padding:0.5rem 0.75rem;border:1px solid #d1d5db;border-radius:8px;font-size:0.9rem;">
        </div>
        <div>
            <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:0.35rem;">終了予定日時（任意）</label>
            <input type="datetime-local" id="maintenanceEndTime"
                   value="<?= $maint['end_time'] ? date('Y-m-d\TH:i', strtotime($maint['end_time'])) : '' ?>"
                   style="padding:0.5rem 0.75rem;border:1px solid #d1d5db;border-radius:8px;font-size:0.9rem;">
        </div>
    </div>

    <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
        <button class="btn btn-danger" id="enableMaintenanceBtn" <?= $maint['enabled'] ? 'style="display:none"' : '' ?>>
            🔒 メンテナンスモードを有効にする
        </button>
        <button class="btn btn-success" id="disableMaintenanceBtn" <?= !$maint['enabled'] ? 'style="display:none"' : '' ?>>
            ✓ メンテナンスモードを無効にする
        </button>
        <a href="/pages/maintenance.php?preview=1" target="_blank" class="btn btn-secondary btn-sm">
            👁 表示確認
        </a>
        <?php if (!empty($maint['updated_by'])): ?>
        <span style="font-size:0.8rem;color:#9ca3af;margin-left:auto;">
            最終更新: <?= htmlspecialchars($maint['updated_by']) ?> / <?= htmlspecialchars($maint['updated_at'] ?? '') ?>
        </span>
        <?php endif; ?>
    </div>

    <div id="maintenanceFlash" style="display:none;margin-top:0.75rem;" class="alert"></div>
</div>

<script<?= nonceAttr() ?>>
(function() {
    const csrfToken = <?= json_encode(generateCsrfToken()) ?>;
    const enableBtn  = document.getElementById('enableMaintenanceBtn');
    const disableBtn = document.getElementById('disableMaintenanceBtn');
    const badge      = document.getElementById('maintenanceStatusBadge');
    const flash      = document.getElementById('maintenanceFlash');

    async function toggleMaintenance(enable) {
        const confirmed = enable
            ? confirm('メンテナンスモードを有効にします。\n管理部以外のユーザーはアクセスできなくなります。よろしいですか？')
            : confirm('メンテナンスモードを無効にします。よろしいですか？');
        if (!confirmed) return;

        const payload = {
            enabled:  enable,
            message:  document.getElementById('maintenanceMessage').value,
            end_time: document.getElementById('maintenanceEndTime').value || null,
        };

        try {
            const res  = await fetch('/api/maintenance-api.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body:    JSON.stringify(payload),
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            // UI 更新
            enableBtn.style.display  = enable ? 'none'  : '';
            disableBtn.style.display = enable ? ''      : 'none';
            badge.className = 'status-badge ' + (enable ? 'danger' : 'success');
            badge.textContent = enable ? '⚠️ 有効（ブロック中）' : '✓ 無効';

            flash.className = 'alert alert-success';
            flash.textContent = json.message;
            flash.style.display = 'block';
            setTimeout(() => { flash.style.display = 'none'; }, 4000);
        } catch (e) {
            flash.className = 'alert alert-danger';
            flash.textContent = e.message || '操作に失敗しました';
            flash.style.display = 'block';
        }
    }

    enableBtn.addEventListener('click',  () => toggleMaintenance(true));
    disableBtn.addEventListener('click', () => toggleMaintenance(false));
})();
</script>

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
async function loadChatSpaces() {
    const select = document.getElementById('chatSpaceSelect');
    if (!select) return;

    select.innerHTML = '<option value="">読み込み中...</option>';

    try {
        const data = await (await fetch(location.origin + '/api/alcohol-chat-sync.php?action=get_spaces')).json();
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
    } catch (err) {
        select.innerHTML = '<option value="">読み込みエラー</option>';
    }
}

// スペース設定を保存
async function saveChatSpaceConfig() {
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

    try {
        const data = await (await fetch(location.origin + '/api/alcohol-chat-sync.php?action=save_config', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: 'space_id=' + encodeURIComponent(spaceId) + '&space_name=' + encodeURIComponent(spaceName)
        })).json();
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
    } catch (err) {
        statusDiv.style.background = '#ffebee';
        statusDiv.style.color = '#c62828';
        statusDiv.textContent = 'エラーが発生しました';
    }
}
</script>
<?php endif; ?>

</div><!-- /.page-container -->

<?php if (empty($activeTab) || !isset($settingTypes[$activeTab])): ?>
<script>
// 設定検索（クライアントサイドフィルタ）
(function() {
    const input = document.getElementById('settingsSearchInput');
    const clearBtn = document.getElementById('settingsSearchClear');
    const emptyEl  = document.getElementById('settingsSearchEmpty');
    if (!input) return;

    const cards    = Array.from(document.querySelectorAll('.settings-select-card[data-search]'));
    const sections = Array.from(document.querySelectorAll('.settings-section'));

    function applyFilter() {
        const q = input.value.trim().toLowerCase();
        clearBtn.style.display = q ? '' : 'none';

        let total = 0;
        cards.forEach(card => {
            const hay = card.dataset.search || '';
            const hit = !q || hay.includes(q);
            card.classList.toggle('is-hidden', !hit);
            if (hit) total++;
        });
        sections.forEach(sec => {
            const visible = sec.querySelectorAll('.settings-select-card:not(.is-hidden)').length;
            sec.classList.toggle('is-empty', visible === 0);
        });
        emptyEl.style.display = (q && total === 0) ? '' : 'none';
    }

    input.addEventListener('input', applyFilter);
    clearBtn.addEventListener('click', () => {
        input.value = '';
        applyFilter();
        input.focus();
    });
    // Esc で検索クリア
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && input.value) {
            input.value = '';
            applyFilter();
        }
    });
})();
</script>
<?php endif; ?>

<?php require_once '../functions/footer.php'; ?>
