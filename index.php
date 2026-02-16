<?php
// ルートアクセス時：ログイン画面を直接表示（リダイレクトしない）
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/google-oauth.php';

// すでにログイン済みの場合はダッシュボードにリダイレクト
if (isset($_SESSION['user_email'])) {
    header('Location: /pages/index.php');
    exit;
}

// エラーメッセージ
$error = '';

// セッションからエラーメッセージを取得
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

$googleOAuth = new GoogleOAuthClient();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>ログイン - YA管理一覧</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', sans-serif;
            background: linear-gradient(135deg, #5a6c7d 0%, #3d4551 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            padding: 3rem;
            max-width: 400px;
            width: 90%;
        }

        .logo {
            font-size: 1.75rem;
            font-weight: 600;
            color: #5a6c7d;
            margin-bottom: 0.5rem;
            text-align: center;
            letter-spacing: 0.5px;
        }

        .subtitle {
            color: #6b7684;
            margin-bottom: 2rem;
            text-align: center;
            font-weight: 400;
        }

        .error-message {
            background: #fef0ef;
            color: #a84d42;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            border-left: 3px solid #c87b6f;
        }

        .google-login-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 1rem;
            background: white;
            color: #3c4043;
            border: 1px solid #dadce0;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }

        .google-login-btn:hover {
            background: #f8f9fa;
            border-color: #c6c9cc;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .google-login-btn svg {
            width: 20px;
            height: 20px;
            margin-right: 0.75rem;
        }

        .not-configured {
            padding: 1.5rem;
            background: #fef3c7;
            border-radius: 8px;
            color: #92400e;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">YA管理一覧</div>
        <div class="subtitle">ログイン</div>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($googleOAuth->isConfigured()): ?>
            <a href="<?= htmlspecialchars($googleOAuth->getAuthUrl()) ?>" class="google-login-btn">
                <svg viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Googleでログイン
            </a>

        <?php else: ?>
            <div class="not-configured">
                Googleログインが設定されていません。<br>
                管理者に連絡してください。
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
