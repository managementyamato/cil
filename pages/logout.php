<?php
require_once '../config/config.php';

// セッションを破棄
session_destroy();

// ログインページにリダイレクト
header('Location: login.php');
exit;
