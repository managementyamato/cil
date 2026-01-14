<?php
require_once 'config.php';

// すでにログイン済みの場合はトップページにリダイレクト
if (isset($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

// エラーメッセージ
$error = '';

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'メールアドレスとパスワードを入力してください。';
    } else if (!isset($USERS[$email])) {
        $error = 'メールアドレスまたはパスワードが正しくありません。';
    } else if (!password_verify($password, $USERS[$email]['password'])) {
        $error = 'メールアドレスまたはパスワードが正しくありません。';
    } else {
        // ログイン成功
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $USERS[$email]['name'];
        $_SESSION['user_role'] = $USERS[$email]['role'];
        header('Location: index.php');
        exit;
    }
}
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #3d4551;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d4d7db;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #5a6c7d;
            box-shadow: 0 0 0 3px rgba(90, 108, 125, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 0.875rem;
            background: #5a6c7d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .login-btn:hover {
            background: #475563;
        }

        .setup-notice {
            margin-top: 2rem;
            padding: 1rem;
            background: #fff8e6;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #8b6914;
            border-left: 3px solid #c5956f;
        }

        .setup-notice strong {
            display: block;
            margin-bottom: 0.5rem;
        }

        .setup-notice code {
            display: block;
            background: white;
            padding: 0.5rem;
            border-radius: 4px;
            margin-top: 0.5rem;
            font-family: monospace;
            font-size: 0.75rem;
            overflow-x: auto;
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

        <?php if (empty($USERS)): ?>
            <div class="setup-notice">
                <strong>初期セットアップが必要です</strong>
                システムを使用するには、管理者アカウントを作成する必要があります。
                <div style="margin-top: 1rem;">
                    <a href="setup.php" style="display: inline-block; padding: 0.75rem 1.5rem; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; font-weight: 500;">
                        初期セットアップを開始
                    </a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">メールアドレス</label>
                    <input type="email" id="email" name="email" required autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password">パスワード</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>

                <button type="submit" class="login-btn">ログイン</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
