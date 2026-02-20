<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/api-middleware.php';

initApi([
    'requireAuth' => true,
    'requireCsrf' => false,
    'allowedMethods' => ['GET'],
]);

if (!isAdmin()) {
    errorResponse('管理者権限が必要です', 403);
}

$settingItems = [
    [
        'key' => 'google_oauth',
        'name' => 'Googleログイン',
        'description' => 'Googleアカウントでのログインを有効にします',
        'link' => '/pages/google-oauth-settings.php',
    ],
    [
        'key' => 'google_calendar',
        'name' => 'Googleカレンダー連携',
        'description' => 'ダッシュボードに今日の予定を表示します',
        'link' => '/pages/settings.php?tab=google_calendar',
    ],
    [
        'key' => 'google_chat',
        'name' => 'Google Chat連携',
        'description' => 'アルコールチェック画像を取り込みます',
        'link' => '/pages/settings.php?tab=google_chat',
    ],
    [
        'key' => 'mf_invoice',
        'name' => 'MF請求書連携',
        'description' => 'MoneyForward請求書とのAPI連携',
        'link' => '/pages/mf-settings.php',
    ],
    [
        'key' => 'recurring_invoices',
        'name' => '定期請求書作成',
        'description' => '毎月の定期請求書を一括作成',
        'link' => '/pages/recurring-invoices.php',
    ],
    [
        'key' => 'notification',
        'name' => '通知設定',
        'description' => 'トラブル発生時のメール通知を設定',
        'link' => '/pages/notification-settings.php',
    ],
    [
        'key' => 'api_integration',
        'name' => 'API連携設定',
        'description' => '外部システムとのAPI連携を設定',
        'link' => '/pages/integration-settings.php',
    ],
    [
        'key' => 'user_permissions',
        'name' => 'アカウント権限設定',
        'description' => '各ユーザーの閲覧・編集権限を設定',
        'link' => '/pages/user-permissions.php',
    ],
    [
        'key' => 'employees',
        'name' => '従業員マスタ',
        'description' => '従業員情報の管理を行います',
        'link' => '/pages/employees.php',
        'requireEdit' => true,
    ],
    [
        'key' => 'audit_log',
        'name' => '操作ログ',
        'description' => 'システムの操作履歴を確認',
        'link' => '/pages/audit-log.php',
    ],
    [
        'key' => 'sessions',
        'name' => 'セッション管理',
        'description' => 'ログイン中のセッションを管理',
        'link' => '/pages/sessions.php',
    ],
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'settings' => $settingItems,
    ],
], JSON_UNESCAPED_UNICODE);
