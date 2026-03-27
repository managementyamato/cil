<?php
/**
 * メンテナンス中表示ページ
 * - 認証チェックあり（未ログインはログイン画面へ）
 * - 管理部は自動でダッシュボードへリダイレクト
 * - メンテナンスモード無効時は自動でダッシュボードへリダイレクト
 * ※ auth.php 内の maintenance check は maintenance.php 自身では発動しない設計（無限ループ防止）
 */
require_once '../api/auth.php';

// 管理部はスキップ（常にダッシュボードへ）
if (isAdmin()) {
    header('Location: /pages/index.php');
    exit;
}

// メンテナンスモードが無効ならスキップ
$maintenanceFile = __DIR__ . '/../config/maintenance.json';
$maintenance = ['enabled' => false, 'message' => 'システムメンテナンス中です。しばらくお待ちください。', 'end_time' => null];
if (file_exists($maintenanceFile)) {
    $maintenance = json_decode(file_get_contents($maintenanceFile), true) ?? $maintenance;
}
if (empty($maintenance['enabled'])) {
    header('Location: /pages/index.php');
    exit;
}

$endTimeText = '';
if (!empty($maintenance['end_time'])) {
    $ts = strtotime($maintenance['end_time']);
    if ($ts) {
        $endTimeText = date('Y年m月d日 H:i', $ts) . ' 頃に再開予定';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="refresh" content="60">
    <title>メンテナンス中 - Yamato Gear</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1f2937;
        }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            padding: 3rem 2.5rem;
            max-width: 480px;
            width: 90%;
            text-align: center;
        }
        .icon-wrapper {
            width: 80px;
            height: 80px;
            background: #fef3c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .icon-wrapper svg {
            width: 40px;
            height: 40px;
            color: #d97706;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.75rem;
        }
        .message {
            font-size: 0.95rem;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .end-time {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #f3f4f6;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            color: #374151;
            margin-bottom: 1.5rem;
        }
        .refresh-note {
            font-size: 0.8rem;
            color: #9ca3af;
            margin-top: 1rem;
        }
        .brand {
            font-size: 0.85rem;
            color: #9ca3af;
            margin-top: 2rem;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        /* スピナー */
        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid #e5e7eb;
            border-top-color: #d97706;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrapper">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
        </div>

        <h1>メンテナンス中</h1>
        <p class="message"><?= htmlspecialchars($maintenance['message']) ?></p>

        <?php if ($endTimeText): ?>
        <div class="end-time">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            <?= htmlspecialchars($endTimeText) ?>
        </div>
        <?php endif; ?>

        <p class="refresh-note">
            <span class="spinner"></span>
            このページは60秒ごとに自動更新されます
        </p>

        <div class="brand">Yamato Gear</div>
    </div>
</body>
</html>
