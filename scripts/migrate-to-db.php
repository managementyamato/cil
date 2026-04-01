<?php
/**
 * data.json → MySQL 移行スクリプト
 *
 * Usage:
 *   php scripts/migrate-to-db.php                 # ドライラン（確認のみ）
 *   php scripts/migrate-to-db.php --execute       # 実行
 *   php scripts/migrate-to-db.php --execute --force  # 既存データを上書き
 *
 * 前提条件:
 *   1. MySQL に DB を作成済み（XSERVER管理画面 or CREATE DATABASE）
 *   2. scripts/create-tables.sql を実行済み
 *   3. .env に DB_HOST, DB_NAME, DB_USER, DB_PASS を設定済み
 */

// エラーを全て表示
error_reporting(E_ALL);
ini_set('display_errors', '1');

// プロジェクトルートを設定
$projectDir = dirname(__DIR__);
chdir($projectDir);

// 設定読み込み
require_once $projectDir . '/config/config.php';
require_once $projectDir . '/functions/data-schema.php';
require_once $projectDir . '/config/database.php';

// --- コマンドライン引数 ---
$execute = in_array('--execute', $argv ?? []);
$force   = in_array('--force', $argv ?? []);

echo "============================================================\n";
echo "  data.json → MySQL 移行ツール\n";
echo "============================================================\n\n";

if (!$execute) {
    echo "【ドライラン】 データは書き込みません。\n";
    echo "  実行するには: php scripts/migrate-to-db.php --execute\n\n";
}

// --- Step 1: DB接続確認 ---
echo "[1/4] DB接続確認...\n";
try {
    $pdo = Database::connect();
    echo "  OK: " . env('DB_HOST', 'localhost') . " / " . env('DB_NAME', 'yamato_mgt') . "\n\n";
} catch (Exception $e) {
    echo "  ERROR: DB接続失敗\n";
    echo "  " . $e->getMessage() . "\n\n";
    echo "  .env に以下を設定してください:\n";
    echo "    DB_HOST=localhost\n";
    echo "    DB_NAME=yamato_mgt\n";
    echo "    DB_USER=your_user\n";
    echo "    DB_PASS=your_password\n";
    exit(1);
}

// --- Step 2: data.json 読み込み ---
echo "[2/4] data.json 読み込み...\n";
if (!file_exists(DATA_FILE)) {
    echo "  ERROR: data.json が見つかりません: " . DATA_FILE . "\n";
    exit(1);
}

$jsonContent = file_get_contents(DATA_FILE);
$jsonSize = strlen($jsonContent);
echo "  ファイルサイズ: " . number_format($jsonSize / 1024 / 1024, 2) . " MB\n";

$data = json_decode($jsonContent, true);
if ($data === null) {
    echo "  ERROR: JSONパース失敗: " . json_last_error_msg() . "\n";
    exit(1);
}
echo "  エンティティ数: " . count($data) . "\n\n";

// --- Step 3: テーブル存在確認 ---
echo "[3/4] テーブル確認...\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$requiredTables = [
    'system_meta',
    'projects', 'troubles', 'customers', 'partners', 'employees',
    'manufacturers', 'invoices', 'mf_invoices', 'loans', 'repayments',
    'invoice_templates', 'invoice_excel_templates', 'scheduled_invoices',
    'tasks', 'announcements', 'memos',
    'chat_rooms', 'chat_messages', 'chat_read_status',
    'slides', 'company_rules', 'contacts', 'leads',
    'morning_todos', 'weekly_reports', 'discount_approvals',
    'slide_confirmations',
];

$missingTables = array_diff($requiredTables, $tables);
if (!empty($missingTables)) {
    echo "  ERROR: 以下のテーブルが存在しません:\n";
    foreach ($missingTables as $t) {
        echo "    - {$t}\n";
    }
    echo "\n  先に実行: mysql -u user -p dbname < scripts/create-tables.sql\n";
    exit(1);
}
echo "  OK: 全 " . count($requiredTables) . " テーブル確認済み\n\n";

// --- Step 4: データ移行 ---
echo "[4/4] データ移行...\n\n";

// メタエンティティ
$metaEntities = ['assignees', 'productCategories', 'settings', 'mf_sync_timestamp', 'customers_sync_timestamp'];

// テーブルエンティティ（chat_read_statusはidなし）
$noIdEntities = ['chat_read_status'];

$totalRecords = 0;
$totalErrors  = 0;
$results      = [];

// テーブルカラム情報をキャッシュ
$tableColumns = [];
foreach ($requiredTables as $table) {
    if ($table === 'system_meta') continue;
    $cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
    $tableColumns[$table] = $cols;
}

foreach ($data as $entity => $value) {
    // メタエンティティ
    if (in_array($entity, $metaEntities, true)) {
        $displayValue = is_array($value) ? count($value) . ' items' : (string)$value;
        echo "  [{$entity}] meta → {$displayValue}\n";

        if ($execute) {
            try {
                Database::saveEntity($entity, $value);
                echo "    ✓ 保存完了\n";
            } catch (Exception $e) {
                echo "    ✗ エラー: " . $e->getMessage() . "\n";
                $totalErrors++;
            }
        }
        $results[$entity] = ['type' => 'meta', 'count' => 1];
        continue;
    }

    // テーブルに対応しないエンティティ（スキップ）
    if (!isset($tableColumns[$entity])) {
        echo "  [{$entity}] スキップ（テーブル定義なし）\n";
        continue;
    }

    // 配列でなければスキップ
    if (!is_array($value)) {
        echo "  [{$entity}] スキップ（配列ではない）\n";
        continue;
    }

    $count = count($value);
    echo "  [{$entity}] {$count} 件\n";

    if ($count === 0) {
        $results[$entity] = ['type' => 'table', 'count' => 0];
        continue;
    }

    if (!$execute) {
        // ドライラン: 各フィールドの統計だけ表示
        if ($count > 0) {
            $sampleKeys = array_keys($value[0]);
            $validCols = $tableColumns[$entity];
            $extraKeys = array_diff($sampleKeys, $validCols);
            $missingKeys = array_diff($validCols, $sampleKeys);

            if (!empty($extraKeys)) {
                echo "    ⚠ data.jsonにあるがテーブルにない列: " . implode(', ', $extraKeys) . "\n";
            }
        }
        $results[$entity] = ['type' => 'table', 'count' => $count];
        $totalRecords += $count;
        continue;
    }

    // 実行モード: 既存データチェック
    if (!$force) {
        $existing = $pdo->query("SELECT COUNT(*) FROM `{$entity}`")->fetchColumn();
        if ($existing > 0) {
            echo "    ⚠ 既存データ {$existing} 件あり → スキップ（--force で上書き）\n";
            $results[$entity] = ['type' => 'table', 'count' => 0, 'skipped' => true];
            continue;
        }
    }

    // トランザクションで書き込み
    $pdo->beginTransaction();
    try {
        // 既存データ削除（--force時）
        if ($force) {
            $pdo->exec("DELETE FROM `{$entity}`");
        }

        $validCols = $tableColumns[$entity];
        $insertCount = 0;
        $skipFields = [];

        foreach ($value as $idx => $row) {
            if (!is_array($row)) continue;

            // テーブルに存在するカラムだけ抽出
            $filteredRow = [];
            foreach ($row as $key => $val) {
                if (in_array($key, $validCols, true)) {
                    $filteredRow[$key] = $val;
                } else {
                    $skipFields[$key] = true;
                }
            }

            if (empty($filteredRow)) continue;

            // JSON/boolカラム変換
            $dbRow = Database::rowToDb($entity, $filteredRow);

            $columns = array_keys($dbRow);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));

            $stmt = $pdo->prepare(
                "INSERT INTO `{$entity}` ({$columnList}) VALUES ({$placeholders})"
            );
            $stmt->execute(array_values($dbRow));
            $insertCount++;
        }

        $pdo->commit();
        echo "    ✓ {$insertCount} 件挿入完了\n";

        if (!empty($skipFields)) {
            echo "    ⚠ スキップしたフィールド: " . implode(', ', array_keys($skipFields)) . "\n";
        }

        $results[$entity] = ['type' => 'table', 'count' => $insertCount];
        $totalRecords += $insertCount;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "    ✗ エラー: " . $e->getMessage() . "\n";

        // エラー行の詳細を表示
        if (isset($idx, $row)) {
            echo "    ✗ 行 {$idx}: id=" . ($row['id'] ?? '(なし)') . "\n";
        }

        $totalErrors++;
        $results[$entity] = ['type' => 'table', 'count' => 0, 'error' => $e->getMessage()];
    }
}

// --- 結果サマリー ---
echo "\n============================================================\n";
echo "  結果サマリー\n";
echo "============================================================\n\n";

$maxLen = max(array_map('strlen', array_keys($results)));
foreach ($results as $entity => $info) {
    $padded = str_pad($entity, $maxLen + 2);
    $status = isset($info['error']) ? '✗ ERROR' : (isset($info['skipped']) ? '⚠ SKIP' : '✓ OK');
    echo "  {$padded} {$status}  ({$info['count']} 件)\n";
}

echo "\n";
echo "  合計レコード: {$totalRecords}\n";
echo "  エラー: {$totalErrors}\n";

if (!$execute) {
    echo "\n  ※ ドライランのため実際の書き込みは行っていません。\n";
    echo "  実行するには: php scripts/migrate-to-db.php --execute\n";
}

echo "\n";
