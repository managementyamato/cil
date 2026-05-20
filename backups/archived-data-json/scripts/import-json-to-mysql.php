<?php
/**
 * data.json → ローカル MySQL への一括インポート（ローカル開発用）
 *
 * 使用方法:
 *   scripts/php.exe scripts/import-json-to-mysql.php
 *
 * - .env.local の DB_MODE は何でもよい（このスクリプト内で db モードに強制切り替え）
 * - data.json の内容を読み、ローカル MySQL に書き込む
 * - スキーマは事前に scripts/create-tables.sql で作成済みであること
 */

// CLI 専用
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

// DB_MODE を db に強制（saveData が DB に書くように）
$_ENV['DB_MODE'] = 'db';
putenv('DB_MODE=db');

require_once __DIR__ . '/../config/config.php';

$dataFile = __DIR__ . '/../data.json';
if (!file_exists($dataFile)) {
    fwrite(STDERR, "data.json not found: $dataFile\n");
    exit(1);
}

echo "Reading data.json ...\n";
$raw = file_get_contents($dataFile);
$data = json_decode($raw, true);
if ($data === null) {
    fwrite(STDERR, "Failed to decode data.json\n");
    exit(1);
}

echo "Entities found:\n";
foreach ($data as $entity => $rows) {
    if (is_array($rows)) {
        echo sprintf("  %-30s %d rows\n", $entity, count($rows));
    }
}

echo "\nConnecting to local MySQL ...\n";
try {
    $pdo = Database::connect();
    echo "OK\n\n";
} catch (Throwable $e) {
    fwrite(STDERR, "DB connect failed: " . $e->getMessage() . "\n");
    exit(1);
}

// data.json には id 重複が混じっていることがある（手動編集や移行の名残り）。
// MySQL の PRIMARY KEY 制約に引っかかる前に、entity ごとに id ベースで dedupe する。
echo "Deduplicating by id ...\n";
$dedupeStats = [];
foreach ($data as $entity => $rows) {
    if (!is_array($rows)) continue;
    $seen = [];
    $unique = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $id = $row['id'] ?? null;
        if ($id === null) { $unique[] = $row; continue; }
        $key = (string)$id;
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $unique[] = $row;
    }
    if (count($unique) !== count($rows)) {
        $dedupeStats[$entity] = count($rows) - count($unique);
    }
    $data[$entity] = $unique;
}
if ($dedupeStats) {
    foreach ($dedupeStats as $entity => $dropped) {
        echo "  $entity: dropped $dropped duplicate row(s)\n";
    }
} else {
    echo "  no duplicates\n";
}
echo "\n";

echo "Importing via Database::saveAllData ...\n";
$start = microtime(true);
try {
    Database::saveAllData($data);
    $elapsed = round(microtime(true) - $start, 2);
    echo "OK ({$elapsed}s)\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Import failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

echo "\nVerifying row counts in MySQL:\n";
$tables = ['troubles', 'customers', 'employees', 'projects', 'partners', 'manufacturers'];
foreach ($tables as $tbl) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `{$tbl}`");
        $count = $stmt->fetchColumn();
        echo sprintf("  %-30s %d rows\n", $tbl, $count);
    } catch (Throwable $e) {
        echo sprintf("  %-30s ERROR: %s\n", $tbl, $e->getMessage());
    }
}

echo "\nImport complete.\n";
