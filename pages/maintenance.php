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
// ただし ?preview=1 の場合は管理者もプレビュー表示可
$isPreview = isAdmin() && ($_GET['preview'] ?? '') === '1';
if (isAdmin() && !$isPreview) {
    header('Location: /pages/index.php');
    exit;
}

// メンテナンスモードが無効ならスキップ
$maintenanceFile = __DIR__ . '/../config/maintenance.json';
$maintenance = ['enabled' => false, 'message' => 'システムメンテナンスのため、一時的にご利用いただけません。', 'end_time' => null];
if (file_exists($maintenanceFile)) {
    $maintenance = json_decode(file_get_contents($maintenanceFile), true) ?? $maintenance;
}
if (empty($maintenance['enabled']) && !$isPreview) {
    header('Location: /pages/index.php');
    exit;
}

$endTimeText = '';
if (!empty($maintenance['end_time'])) {
    $ts = strtotime($maintenance['end_time']);
    if ($ts) {
        $endTimeText = date('Y年m月d日 H:i', $ts) . ' 再開予定';
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
            background: #f5f6f7;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        header {
            background: #1c2833;
            padding: 0 2rem;
            height: 56px;
            display: flex;
            align-items: center;
        }
        .logo {
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.04em;
        }
        .logo span {
            color: #e8a020;
        }
        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
        }
        .container {
            max-width: 520px;
            width: 100%;
        }
        .label {
            display: inline-block;
            background: #1c2833;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            padding: 0.25rem 0.75rem;
            margin-bottom: 1.25rem;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1c2833;
            margin-bottom: 1rem;
            line-height: 1.3;
        }
        .divider {
            width: 40px;
            height: 3px;
            background: #e8a020;
            margin-bottom: 1.25rem;
        }
        .message {
            font-size: 0.95rem;
            color: #4b5563;
            line-height: 1.75;
            margin-bottom: 1.5rem;
        }
        .end-time {
            font-size: 0.875rem;
            color: #1c2833;
            font-weight: 600;
            margin-bottom: 2rem;
        }
        .end-time::before {
            content: '▸ ';
            color: #e8a020;
        }
        .note {
            font-size: 0.8rem;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 1.25rem;
        }
        .logout-link {
            display: inline-block;
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #9ca3af;
            text-decoration: none;
        }
        .logout-link:hover {
            color: #6b7280;
            text-decoration: underline;
        }
        .preview-banner {
            background: #1c2833;
            border-left: 4px solid #e8a020;
            color: #e8a020;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.5rem 2rem;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .preview-banner a {
            color: #fff;
            text-decoration: none;
            margin-left: auto;
            font-weight: 400;
            font-size: 0.75rem;
            opacity: 0.7;
        }
        .preview-banner a:hover { opacity: 1; }
    </style>
</head>
<body>
    <?php if ($isPreview): ?>
    <div class="preview-banner">
        👁 管理者プレビューモード — 実際のメンテナンス画面の表示確認
        <a href="/pages/settings.php?tab=maintenance">← 設定に戻る</a>
    </div>
    <?php endif; ?>
    <header>
        <div class="logo">YAMATO <span>GEAR</span> 管理システム</div>
    </header>
    <main>
        <div class="container">
            <div class="label">MAINTENANCE</div>
            <h1>ただいまメンテナンス中です</h1>
            <div class="divider"></div>
            <p class="message"><?= htmlspecialchars($maintenance['message']) ?></p>
            <?php if ($endTimeText): ?>
            <p class="end-time"><?= htmlspecialchars($endTimeText) ?></p>
            <?php endif; ?>
            <p class="note">このページは60秒ごとに自動的に更新されます。</p>
            <?php if (!$isPreview): ?>
            <a href="/pages/logout.php" class="logout-link">ログアウト</a>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
