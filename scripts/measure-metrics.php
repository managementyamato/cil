<?php
/**
 * 週次メトリクス集計
 *
 * 計測項目（docs/refactor-policy.md の KPI 準拠）:
 *   1. 1000 行超ファイル数
 *   2. 直接ファイル操作違反数（lint baseline 件数）
 *   3. E2E テスト本数 / pages カバレッジ率
 *
 * 出力:
 *   - 標準出力: サマリーテーブル
 *   - scripts/metrics-history.json: 週次履歴に append
 *
 * 使い方:
 *   php scripts/measure-metrics.php          # 計測して履歴に追加
 *   php scripts/measure-metrics.php --dry    # 計測のみ（履歴に追記しない）
 */

declare(strict_types=1);

const HISTORY_PATH = __DIR__ . '/metrics-history.json';

$projectRoot = realpath(__DIR__ . '/..');
$dry = in_array('--dry', $argv, true);

// --- 1. 1000 行超ファイル数 ---
$bigFiles = countBigFiles($projectRoot, 1000);
$mediumFiles = countBigFiles($projectRoot, 500);

// --- 2. 直接ファイル操作違反数 ---
$violationCount = countLintViolations($projectRoot);

// --- 3. E2E カバレッジ率 ---
// pages 直下のエントリーポイント PHP のみカウントする
// (pages/sales-tools/tabs/*.php 等の include 用フラグメントは除外)
$pageCount = countDirectFiles($projectRoot . '/pages', '/\.php$/');
$e2eFileCount = countFiles($projectRoot . '/tests/e2e', '/\.spec\.js$/');
$e2eTestCount = countE2eTests($projectRoot . '/tests/e2e');
$coverage = $pageCount > 0 ? round($e2eFileCount / $pageCount * 100, 1) : 0;

// --- 結果 ---
$snapshot = [
    'date'                 => date('Y-m-d'),
    'big_files_1000'       => $bigFiles['count'],
    'big_files_500'        => $mediumFiles['count'],
    'biggest_file'         => $bigFiles['biggest'],
    'biggest_lines'        => $bigFiles['biggest_lines'],
    'direct_fs_violations' => $violationCount,
    'pages_count'          => $pageCount,
    'e2e_spec_count'       => $e2eFileCount,
    'e2e_test_count'       => $e2eTestCount,
    'coverage_percent'     => $coverage,
];

echo "================================================================\n";
echo "  リファクタ進捗メトリクス  " . $snapshot['date'] . "\n";
echo "================================================================\n";
printf("  1000 行超ファイル数       : %d\n", $snapshot['big_files_1000']);
printf("  500-999 行ファイル数       : %d\n", $snapshot['big_files_500'] - $snapshot['big_files_1000']);
printf("  最大ファイル                : %s (%d 行)\n", $snapshot['biggest_file'], $snapshot['biggest_lines']);
printf("  直接ファイル操作違反数      : %d\n", $snapshot['direct_fs_violations']);
printf("  pages 配下 PHP ファイル数   : %d\n", $snapshot['pages_count']);
printf("  E2E spec ファイル数         : %d\n", $snapshot['e2e_spec_count']);
printf("  E2E test 関数数             : %d\n", $snapshot['e2e_test_count']);
printf("  カバレッジ率 (spec/pages)   : %s%%\n", $snapshot['coverage_percent']);
echo "----------------------------------------------------------------\n";

// 目標との比較
$targets = [
    'big_files_1000'       => ['sprint1' => 12, 'sprint2' => 9, 'lower' => true],
    'direct_fs_violations' => ['sprint1' => 45, 'sprint2' => 30, 'lower' => true],
    'coverage_percent'     => ['sprint1' => 33, 'sprint2' => 40, 'lower' => false],
];

echo "  Sprint 1 目標との差分:\n";
foreach ($targets as $key => $t) {
    $current = $snapshot[$key];
    $diff = $t['lower'] ? ($current - $t['sprint1']) : ($t['sprint1'] - $current);
    $status = $diff <= 0 ? 'OK   ' : 'NG   ';
    printf("    %s %-25s : %s vs target %s\n", $status, $key, $current, $t['sprint1']);
}
echo "================================================================\n";

// --- 履歴に追加 ---
if ($dry) {
    echo "[dry-run] 履歴には保存しませんでした\n";
    exit(0);
}

$history = [];
if (file_exists(HISTORY_PATH)) {
    $raw = file_get_contents(HISTORY_PATH);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $history = $decoded;
}
$history[] = $snapshot;
file_put_contents(HISTORY_PATH, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "[OK] " . HISTORY_PATH . " に追記しました\n";

// 黄信号/赤信号チェック
checkSignals($history);
exit(0);

// ================================================================
// 関数
// ================================================================

function countBigFiles(string $root, int $threshold): array
{
    $count = 0;
    $biggest = '';
    $biggestLines = 0;
    foreach (['pages', 'api', 'functions', 'config'] as $dir) {
        $abs = $root . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($abs)) continue;
        walkLineCount($abs, $root, $threshold, $count, $biggest, $biggestLines);
    }
    return ['count' => $count, 'biggest' => $biggest, 'biggest_lines' => $biggestLines];
}

function walkLineCount(string $dir, string $root, int $threshold, int &$count, string &$biggest, int &$biggestLines): void
{
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            walkLineCount($path, $root, $threshold, $count, $biggest, $biggestLines);
            continue;
        }
        if (!preg_match('/\.php$/', $item)) continue;
        $lines = substr_count(file_get_contents($path), "\n") + 1;
        if ($lines >= $threshold) $count++;
        if ($lines > $biggestLines) {
            $biggestLines = $lines;
            $biggest = str_replace('\\', '/', substr($path, strlen($root) + 1));
        }
    }
}

function countLintViolations(string $root): int
{
    $baselinePath = $root . '/scripts/lint-baseline.json';
    if (!file_exists($baselinePath)) return 0;
    $raw = file_get_contents($baselinePath);
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? (int)($decoded['count'] ?? 0) : 0;
}

function countFiles(string $dir, string $pattern): int
{
    if (!is_dir($dir)) return 0;
    $count = 0;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $count += countFiles($path, $pattern);
        } elseif (preg_match($pattern, $item)) {
            $count++;
        }
    }
    return $count;
}

/**
 * 直下ファイルのみカウント（サブディレクトリは無視）
 * include 用フラグメントを「ページ」としてカウントしないために使う。
 */
function countDirectFiles(string $dir, string $pattern): int
{
    if (!is_dir($dir)) return 0;
    $count = 0;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_file($path) && preg_match($pattern, $item)) {
            $count++;
        }
    }
    return $count;
}

function countE2eTests(string $dir): int
{
    if (!is_dir($dir)) return 0;
    $total = 0;
    foreach (scandir($dir) as $item) {
        if (!preg_match('/\.spec\.js$/', $item)) continue;
        $content = file_get_contents($dir . DIRECTORY_SEPARATOR . $item);
        $total += preg_match_all('/^\s{2,}test\s*\(/m', $content);
    }
    return $total;
}

function checkSignals(array $history): void
{
    if (count($history) < 2) return;
    $latest = $history[count($history) - 1];
    $prev = $history[count($history) - 2];

    $warnings = [];
    if ($latest['big_files_1000'] > $prev['big_files_1000']) {
        $warnings[] = sprintf('黄信号: 1000 行超ファイル数が増加 (%d → %d)', $prev['big_files_1000'], $latest['big_files_1000']);
    }
    if ($latest['direct_fs_violations'] > $prev['direct_fs_violations']) {
        $warnings[] = sprintf('黄信号: 直接ファイル操作違反数が増加 (%d → %d)', $prev['direct_fs_violations'], $latest['direct_fs_violations']);
    }
    if ($latest['coverage_percent'] < 30) {
        $warnings[] = sprintf('赤信号: カバレッジ率が 30%% を下回りました (%.1f%%)', $latest['coverage_percent']);
    }

    // 2 週連続増加チェック (赤信号)
    if (count($history) >= 3) {
        $prev2 = $history[count($history) - 3];
        if ($latest['big_files_1000'] > $prev['big_files_1000']
            && $prev['big_files_1000'] > $prev2['big_files_1000']) {
            $warnings[] = '赤信号: 1000 行超ファイル数が 2 週連続増加 → 着手停止判断';
        }
    }

    if (!empty($warnings)) {
        echo "\n!!! アラート !!!\n";
        foreach ($warnings as $w) echo "  - " . $w . "\n";
        echo "  docs/refactor-policy.md の停止条件を確認してください\n";
    }
}
