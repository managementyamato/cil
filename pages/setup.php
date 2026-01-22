<?php
// config.phpを読み込む（セッションとユーザー関数を使用）
require_once '../config/config.php';

// 既にユーザーが存在する場合はログインページへ
$users = getUsers();
if (!empty($users)) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = false;

// 初回管理者登録処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($name) || empty($password)) {
        $error = 'すべての項目を入力してください。';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '有効なメールアドレスを入力してください。';
    } else if (strlen($password) < 6) {
        $error = 'パスワードは6文字以上で入力してください。';
    } else if ($password !== $confirm_password) {
        $error = 'パスワードが一致しません。';
    } else {
        // 初回管理者アカウントを作成
        $users = array(
            $email => array(
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'role' => 'admin'
            )
        );
        $saveResult = saveUsers($users);
        if ($saveResult !== false) {
            $success = true;
        } else {
            $error = 'ユーザー情報の保存に失敗しました。ディレクトリの書き込み権限を確認してください。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>初期セットアップ - YA管理一覧</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .setup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 3rem;
            max-width: 500px;
            width: 100%;
        }

        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .subtitle {
            color: #6b7280;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 0.875rem;
        }

        .welcome-message {
            background: #dbeafe;
            color: #1e40af;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .welcome-message h3 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }

        .success-message h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .success-message p {
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
        }

        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #6b7280;
            font-size: 0.75rem;
        }

        .submit-btn {
            width: 100%;
            padding: 0.875rem;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .submit-btn:hover {
            background: #1d4ed8;
        }

        .login-link {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .login-link:hover {
            background: #1d4ed8;
        }

        .security-notice {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #fef3c7;
            border-radius: 8px;
            font-size: 0.75rem;
            color: #92400e;
        }

        .security-notice strong {
            display: block;
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="logo">YA管理一覧</div>
        <div class="subtitle">初期セットアップ</div>

        <?php if ($success): ?>
            <div class="success-message">
                <h3>セットアップ完了</h3>
                <p>管理者アカウントが正常に作成されました。</p>
                <a href="login.php" class="login-link">ログインページへ</a>
            </div>
        <?php else: ?>
            <div class="welcome-message">
                <h3>ようこそ</h3>
                <p>システムを使用するには、最初の管理者アカウントを作成する必要があります。</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">メールアドレス *</label>
                    <input type="email" id="email" name="email" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <small>ログイン時に使用するメールアドレスです</small>
                </div>

                <div class="form-group">
                    <label for="name">名前 *</label>
                    <input type="text" id="name" name="name" required autocomplete="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    <small>画面に表示される名前です</small>
                </div>

                <div class="form-group">
                    <label for="password">パスワード *</label>
                    <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password">
                    <small>6文字以上で入力してください</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">パスワード（確認） *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
                    <small>確認のため、もう一度同じパスワードを入力してください</small>
                </div>

                <button type="submit" class="submit-btn">管理者アカウントを作成</button>

                <div class="security-notice">
                    <strong>セキュリティについて</strong>
                    このアカウントはシステムの全機能にアクセスできる管理者権限を持ちます。パスワードは安全に管理してください。
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
