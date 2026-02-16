<?php
require_once '../api/auth.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Header Test</title>
</head>
<body>
    <h1>Headers already sent test</h1>
    <p>If you see this, headers were sent successfully.</p>
    <?php
    echo "<p>Session status: " . session_status() . "</p>";
    echo "<p>Headers sent: " . (headers_sent($file, $line) ? "Yes (file: $file, line: $line)" : "No") . "</p>";
    ?>
</body>
</html>
