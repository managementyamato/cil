<?php
require_once '../config/config.php';
require_once __DIR__ . '/../api/google-oauth.php';

// すでにログイン済みの場合はトップページにリダイレクト
if (isset($_SESSION['user_email'])) {
    header('Location: index.php');
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
setSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>ログイン - Yamato Gear</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <style<?= nonceAttr() ?>>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0e6251;
            overflow: hidden;
            position: relative;
        }

        /* #21: 斜め分割 — 左側をやや明るいティールに */
        .bg-slant {
            position: absolute;
            top: 0;
            left: 0;
            width: 55%;
            height: 100%;
            background: linear-gradient(160deg, #117a65, #0a3d30);
            clip-path: polygon(0 0, 82% 0, 65% 100%, 0 100%);
            z-index: 0;
        }

        /* #35: ジオメトリック図形 */
        .geo {
            position: absolute;
            background: rgba(255, 255, 255, 0.06);
            z-index: 1;
        }
        .geo1 {
            top: -80px;
            right: -80px;
            width: 320px;
            height: 320px;
            transform: rotate(45deg);
            border-radius: 32px;
        }
        .geo2 {
            bottom: -60px;
            left: 30%;
            width: 220px;
            height: 220px;
            transform: rotate(28deg);
            border-radius: 24px;
        }
        .geo3 {
            top: 15%;
            left: 5%;
            width: 100px;
            height: 100px;
            transform: rotate(18deg);
            border-radius: 12px;
            opacity: 0.08;
        }
        .geo4 {
            bottom: 20%;
            right: 8%;
            width: 60px;
            height: 60px;
            transform: rotate(35deg);
            border-radius: 8px;
            opacity: 0.1;
        }

        /* #6: カード */
        .login-container {
            position: relative;
            z-index: 2;
            background: #ffffff;
            border-radius: 20px;
            padding: 3rem;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
        }

        .logo-icon {
            text-align: center;
            margin-bottom: 0.75rem;
        }

        .logo {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(120deg, #111111 47%, #e05555 47%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.25rem;
            text-align: center;
            letter-spacing: -0.5px;
        }

        .subtitle {
            color: #666666;
            margin-bottom: 2.5rem;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 400;
        }

        .error-message {
            background: #fff5f5;
            color: #922b21;
            padding: 0.875rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            border: 1px solid #f5c6c2;
            border-left: 3px solid #e74c3c;
            line-height: 1.5;
        }

        .google-login-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            padding: 0.9rem 1rem;
            background: rgba(255, 255, 255, 0.95);
            color: #3c4043;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .google-login-btn:hover {
            background: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .google-login-btn svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .login-note {
            text-align: center;
            font-size: 0.75rem;
            color: #999999;
            margin-top: 1.5rem;
        }

        .not-configured {
            padding: 1.25rem;
            background: rgba(255, 243, 199, 0.15);
            border: 1px solid rgba(255, 243, 199, 0.3);
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.85);
            text-align: center;
            font-size: 0.875rem;
            line-height: 1.6;
        }
    </style>
    <script<?= nonceAttr() ?>>(function(){var t=localStorage.getItem('pageTheme');if(!t)return;var themes={blue:['#1a5276','#2980b9','#133d55'],purple:['#6c3483','#8e44ad','#4a235a'],red:['#922b21','#c0392b','#641e16'],orange:['#b9770e','#d68910','#7e5109'],green:['#1e8449','#27ae60','#145a32'],pink:['#c2185b','#e91e63','#880e4f'],indigo:['#303f9f','#3f51b5','#1a237e'],brown:['#5d4037','#795548','#3e2723'],navy:['#0d1642','#1a237e','#060b2e']};var c=themes[t];if(!c)return;document.documentElement.style.setProperty('--login-bg',c[0]);document.documentElement.style.setProperty('--login-accent',c[1]);document.documentElement.style.setProperty('--login-dark',c[2]);var s=document.createElement('style');s.textContent='body{background:var(--login-bg)!important}.bg-slant{background:linear-gradient(160deg,var(--login-accent),var(--login-dark))!important}';document.head.appendChild(s);})();</script>
</head>
<body>
    <!-- #21 斜め分割 -->
    <div class="bg-slant"></div>

    <!-- #35 ジオメトリック装飾 -->
    <div class="geo geo1"></div>
    <div class="geo geo2"></div>
    <div class="geo geo3"></div>
    <div class="geo geo4"></div>

    <!-- #6 ガラスモーフィズムカード -->
    <div class="login-container">
        <div class="logo-icon">
            <img src="/favicon.png" width="72" height="72" alt="Yamato Gear ロゴ">
        </div>
        <div class="logo">Yamato Gear</div>
        <div class="subtitle">業務管理システム</div>

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
