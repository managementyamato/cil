<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
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
                <div class="nav-dropdown">
                    <a href="#" class="nav-tab <?= in_array(basename($_SERVER['PHP_SELF']), ['master.php', 'customers.php', 'partners.php', 'employees.php', 'products.php']) ? 'active' : '' ?>">マスタ ▼</a>
                    <div class="dropdown-menu">
                        <a href="master.php" class="dropdown-item">📋 プロジェクト管理</a>
                        <a href="customers.php" class="dropdown-item">🏢 顧客マスタ</a>
                        <a href="partners.php" class="dropdown-item">👥 パートナーマスタ</a>
                        <a href="employees.php" class="dropdown-item">👨‍💼 従業員マスタ</a>
                        <a href="products.php" class="dropdown-item">📦 商品マスタ</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>
    <main class="container">
