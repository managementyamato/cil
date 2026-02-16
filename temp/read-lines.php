<?php
// Read lines around errors in problematic files

echo "=== pages/customers.php (lines 28-35) ===\n";
$f = file('C:/Claude/master/pages/customers.php');
for ($i = 27; $i < 35 && $i < count($f); $i++) {
    echo ($i+1) . ": " . $f[$i];
}

echo "\n=== pages/masters.php (lines 8-15) ===\n";
$f2 = file('C:/Claude/master/pages/masters.php');
for ($i = 7; $i < 15 && $i < count($f2); $i++) {
    echo ($i+1) . ": " . $f2[$i];
}

echo "\n=== public_html/pages/tasks.php (lines 5-12) ===\n";
$f3 = file('C:/Claude/master/public_html/pages/tasks.php');
for ($i = 4; $i < 12 && $i < count($f3); $i++) {
    echo ($i+1) . ": " . $f3[$i];
}
