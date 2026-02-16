<?php
require_once '../api/auth.php';

// admin権限チェック
if (!isAdmin()) {
    header('Location: /pages/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>PHP Info</title>
</head>
<body>
<h1>PHP Configuration</h1>
<h2>Loaded Extensions</h2>
<pre><?php
$extensions = get_loaded_extensions();
sort($extensions);
foreach ($extensions as $ext) {
    echo $ext . "\n";
}
?></pre>

<h2>ZipArchive</h2>
<p>ZipArchive class exists: <?= class_exists('ZipArchive') ? 'YES ✅' : 'NO ❌' ?></p>

<h2>fileinfo</h2>
<p>fileinfo extension loaded: <?= extension_loaded('fileinfo') ? 'YES ✅' : 'NO ❌' ?></p>
<p>mime_content_type function exists: <?= function_exists('mime_content_type') ? 'YES ✅' : 'NO ❌' ?></p>

<h2>Memory Limit</h2>
<p><?= ini_get('memory_limit') ?></p>

<h2>php.ini Location</h2>
<p><?= php_ini_loaded_file() ?></p>

<hr>
<a href="index.php">← ダッシュボードに戻る</a>
</body>
</html>
