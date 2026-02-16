<?php
$baseDir = 'C:\\Claude\\master';
$errors = [];
$warnings = [];
$passed = 0;
$total = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    if (strpos($path, 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;
    if (strpos($path, 'snapshots' . DIRECTORY_SEPARATOR) !== false) continue;
    $total++;
    $output = [];
    $returnCode = 0;
    exec('"C:\\xampp\\php\\php.exe" -l "' . $path . '" 2>&1', $output, $returnCode);
    $outputStr = implode(chr(10), $output);
    if ($returnCode !== 0 || stripos($outputStr, 'Parse error') !== false || stripos($outputStr, 'Fatal error') !== false) {
        $errors[] = ['file' => $path, 'message' => $outputStr];
    } else {
        $passed++;
    }
}
echo "=== PHP SYNTAX CHECK RESULTS ===" . PHP_EOL;
echo "Total files checked: {$total}" . PHP_EOL;
echo "Files passed: {$passed}" . PHP_EOL;
echo "Files with errors: " . count($errors) . PHP_EOL;
echo PHP_EOL;
if (count($errors) > 0) {
    echo "=== FILES WITH SYNTAX ERRORS ===" . PHP_EOL;
    foreach ($errors as $e) {
        echo PHP_EOL;
        echo "  File: " . $e['file'] . PHP_EOL;
        echo "  Error: " . $e['message'] . PHP_EOL;
    }
} else {
    echo "All files passed syntax check!" . PHP_EOL;
}
echo PHP_EOL . "=== DONE ===" . PHP_EOL;
