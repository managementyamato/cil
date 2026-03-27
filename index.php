<?php
// ルートアクセス時：ログイン画面にリダイレクト
require_once __DIR__ . '/config/config.php';

if (isset($_SESSION['user_email'])) {
    header('Location: /pages/index.php');
} else {
    header('Location: /pages/login.php');
}
exit;
