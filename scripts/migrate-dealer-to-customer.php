<?php
/**
 * projects.dealer_name のうち customers に登録されているものを
 * customer_name にコピーするマイグレーションスクリプト。
 *
 * 経緯:
 *   スプシインポート時に、本来「顧客名」に入るべき会社名が
 *   dealer_name 側に入ってしまった案件が多数存在する。
 *   customers マスタ (= MF 取引先からインポート) に該当が見つかる場合は
 *   それが「真の顧客」であるとみなして customer_name 側に反映する。
 *
 * 使い方:
 *   scripts/php.exe scripts/migrate-dealer-to-customer.php             ← dry-run (デフォルト)
 *   scripts/php.exe scripts/migrate-dealer-to-customer.php --apply     ← 実適用
 *   scripts/php.exe scripts/migrate-dealer-to-customer.php --apply --clear-dealer
 *                                                            ← 適用 + dealer_name を空にする
 *
 * dry-run では何も変更しません。--apply で初めて UPDATE を実行します。
 */

if (php_sapi_name() !== 'cli') die('CLI only');

require_once __DIR__ . '/../config/config.php';

$args = $argv;
$apply       = in_array('--apply', $args, true);
$clearDealer = in_array('--clear-dealer', $args, true);

echo "==================================================================\n";
echo " dealer_name → customer_name 移送スクリプト\n";
echo " " . ($apply ? '*** 適用モード (--apply) ***' : '[ dry-run モード — 変更しません ]') . "\n";
if ($clearDealer) echo " *** dealer_name を空にするオプションが ON です ***\n";
echo "==================================================================\n\n";

$pdo = Database::connect();

// 対象抽出: dealer_name が customers.companyName と一致する案件
$sql = "
    SELECT p.id, p.dealer_name, p.customer_name
    FROM projects p
    INNER JOIN customers c ON c.companyName = p.dealer_name AND c.deleted_at IS NULL
    WHERE p.deleted_at IS NULL
      AND p.dealer_name IS NOT NULL
      AND p.dealer_name <> ''
";
$stmt = $pdo->query($sql);
$targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($targets);

echo "■ 対象案件: {$total} 件\n\n";

// 既に customer_name に値が入っているケースは上書きしない (安全策)
$willUpdate = 0;
$skipExistingCustomer = 0;
$breakdown = []; // dealer_name => count
foreach ($targets as $r) {
    $hasCustomer = !empty(trim((string)$r['customer_name']));
    if ($hasCustomer && trim($r['customer_name']) !== trim($r['dealer_name'])) {
        $skipExistingCustomer++;
        continue;
    }
    $willUpdate++;
    $breakdown[$r['dealer_name']] = ($breakdown[$r['dealer_name']] ?? 0) + 1;
}

echo "■ 内訳:\n";
echo "  - customer_name にコピーする           : {$willUpdate} 件\n";
echo "  - 既に別の値が入っているのでスキップ : {$skipExistingCustomer} 件\n\n";

echo "■ 会社別 (案件数降順):\n";
arsort($breakdown);
foreach ($breakdown as $name => $cnt) {
    printf("  %-50s %4d 件\n", mb_substr($name, 0, 48), $cnt);
}
echo "\n";

if (!$apply) {
    echo "==================================================================\n";
    echo " dry-run 完了。実適用するには --apply を付けて再実行してください。\n";
    echo "==================================================================\n";
    exit(0);
}

// ----- バックアップ -----
$backupDir = __DIR__ . '/../backups/dealer-migration';
if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
$backupFile = $backupDir . '/before_' . date('Ymd_His') . '.csv';
$fp = fopen($backupFile, 'w');
fputcsv($fp, ['id', 'dealer_name (旧値)', 'customer_name (旧値)']);
foreach ($targets as $r) {
    fputcsv($fp, [$r['id'], $r['dealer_name'], $r['customer_name']]);
}
fclose($fp);
echo "■ バックアップ CSV を保存: " . str_replace(__DIR__ . '/../', '', $backupFile) . "\n";
echo "  (万が一の場合はこの CSV を元に手動で戻せます)\n\n";

// ----- 実適用 -----
echo "==================================================================\n";
echo " 適用開始\n";
echo "==================================================================\n\n";

$pdo->beginTransaction();
$updatedIds = [];
try {
    $updateSql = $clearDealer
        ? "UPDATE projects SET customer_name = :cn, dealer_name = '', updated_at = NOW() WHERE id = :id"
        : "UPDATE projects SET customer_name = :cn, updated_at = NOW() WHERE id = :id";
    $upd = $pdo->prepare($updateSql);

    $done = 0; $skipped = 0;
    foreach ($targets as $r) {
        $hasCustomer = !empty(trim((string)$r['customer_name']));
        if ($hasCustomer && trim($r['customer_name']) !== trim($r['dealer_name'])) {
            $skipped++;
            continue;
        }
        $upd->execute([
            ':cn' => $r['dealer_name'],
            ':id' => $r['id'],
        ]);
        $updatedIds[] = $r['id'];
        $done++;
        if ($done % 50 === 0) echo "  処理中... {$done} / {$willUpdate}\n";
    }

    $pdo->commit();
    echo "\n";
    echo "==================================================================\n";
    echo " 適用完了\n";
    echo "==================================================================\n";
    echo "  更新済み: {$done} 件\n";
    echo "  スキップ: {$skipped} 件\n";
    if ($clearDealer) {
        echo "  ※ dealer_name も同時に空にしました\n";
    } else {
        echo "  ※ dealer_name は元のまま残しています (確認後 --clear-dealer で空にできます)\n";
    }

    // ----- 適用後サンプル出力 (verify) -----
    if ($done > 0) {
        echo "\n■ 適用後サンプル (先頭 10 件):\n";
        $sampleIds = array_slice($updatedIds, 0, 10);
        $placeholders = implode(',', array_fill(0, count($sampleIds), '?'));
        $verify = $pdo->prepare("SELECT id, customer_name, dealer_name FROM projects WHERE id IN ({$placeholders})");
        $verify->execute($sampleIds);
        printf("  %-15s | %-30s | %-30s\n", 'id', 'customer_name (新)', 'dealer_name');
        echo "  " . str_repeat('-', 80) . "\n";
        while ($r = $verify->fetch(PDO::FETCH_ASSOC)) {
            printf("  %-15s | %-30s | %-30s\n",
                $r['id'],
                mb_strimwidth($r['customer_name'] ?? '', 0, 30, '..'),
                mb_strimwidth($r['dealer_name'] ?? '', 0, 30, '..')
            );
        }

        // 集計ベリファイ
        echo "\n■ 集計ベリファイ:\n";
        $afterFilled = (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL AND customer_name IS NOT NULL AND customer_name <> ''")->fetchColumn();
        echo "  projects.customer_name が入っている件数 (適用後): {$afterFilled} 件\n";
        echo "  ※ 適用前は 0 件だったので、{$done} 件増えていれば OK\n";
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\n❌ エラーが発生したためロールバックしました: " . $e->getMessage() . "\n";
    echo "  バックアップ CSV: {$backupFile}\n";
    exit(1);
}

echo "\n";
echo "==================================================================\n";
echo " ロールバック手順 (必要なら):\n";
echo "==================================================================\n";
echo "  バックアップ CSV: {$backupFile}\n";
echo "  これを元に手動で UPDATE するか、以下の SQL で一括で戻せます:\n";
echo "\n";
echo "  -- 例: dealer_name が残っている場合 (--clear-dealer なしで適用したケース)\n";
echo "  UPDATE projects SET customer_name = '' WHERE id IN (...);\n";
