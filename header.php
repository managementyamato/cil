<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>現場トラブル管理システム</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>現場トラブル管理</h1>
            <nav class="nav-tabs">
                <a href="index.php" class="nav-tab <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">分析</a>
                <a href="list.php" class="nav-tab <?= basename($_SERVER['PHP_SELF']) == 'list.php' ? 'active' : '' ?>">一覧</a>
                <a href="report.php" class="nav-tab <?= basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : '' ?>">報告</a>
                <a href="master.php" class="nav-tab <?= basename($_SERVER['PHP_SELF']) == 'master.php' ? 'active' : '' ?>">マスタ</a>
            </nav>
        </div>
    </header>
    <main class="container">
