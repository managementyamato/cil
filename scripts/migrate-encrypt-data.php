<?php
/**
 * 既存データの暗号化マイグレーションスクリプト
 *
 * 使い方:
 *   php scripts/migrate-encrypt-data.php              # 実行
 *   php scripts/migrate-encrypt-data.php --dry-run    # 確認のみ（変更なし）
 *
 * 処理内容:
 *   data.json 内の customers, assignees, partners の
 *   phone, email, address フィールドを暗号化する。
 *   既に「enc:」プレフィックスが付いているフィールドはスキップする。
 */

// プロジェクトルートに移動
chdir(dirname(__DIR__));

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/encryption.php';

// コマンドライン引数
$dryRun = in_array('--dry-run', $argv ?? []);

echo "=== 顧客データ暗号化マイグレーション ===\n";
echo "開始時刻: " . date('Y-m-d H:i:s') . "\n";
echo "モード: " . ($dryRun ? "ドライラン（変更なし）" : "本番実行") . "\n\n";

try {
    // 暗号化鍵の確認
    getEncryptionKey();
    echo "[OK] 暗号化鍵を確認しました\n";
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "config/encryption.key を作成してください。\n";
    exit(1);
}

// データ読み込み
$data = getData();
echo "[OK] data.json を読み込みました\n\n";

// バックアップ確認
if (!$dryRun) {
    $backupDir = __DIR__ . '/../backups/auto';
    $backups = glob($backupDir . '/*/data.json');
    if (empty($backups)) {
        echo "[WARNING] バックアップが見つかりません。先にバックアップを取得してください。\n";
        echo "  php scripts/backup-data.php\n";
        exit(1);
    }
    $latestBackup = end($backups);
    $backupAge = time() - filemtime($latestBackup);
    if ($backupAge > 3600) {
        echo "[WARNING] 最新のバックアップが1時間以上前です。\n";
        echo "  最新: " . date('Y-m-d H:i:s', filemtime($latestBackup)) . "\n";
        echo "  先にバックアップを取得してください: php scripts/backup-data.php\n";
        exit(1);
    }
    echo "[OK] バックアップを確認しました（" . date('Y-m-d H:i:s', filemtime($latestBackup)) . "）\n\n";
}

$stats = [
    'customers' => ['total' => 0, 'encrypted' => 0, 'skipped' => 0],
    'branches' => ['total' => 0, 'encrypted' => 0, 'skipped' => 0],
    'assignees' => ['total' => 0, 'encrypted' => 0, 'skipped' => 0],
    'partners' => ['total' => 0, 'encrypted' => 0, 'skipped' => 0],
];

// 顧客の暗号化
echo "--- 顧客データ ---\n";
if (isset($data['customers']) && is_array($data['customers'])) {
    foreach ($data['customers'] as &$customer) {
        $stats['customers']['total']++;
        $modified = false;

        foreach (CUSTOMER_ENCRYPT_FIELDS as $field) {
            if (!empty($customer[$field]) && !str_starts_with($customer[$field], ENCRYPTION_PREFIX)) {
                if (!$dryRun) {
                    $customer[$field] = encryptValue($customer[$field]);
                }
                $modified = true;
            }
        }

        if ($modified) {
            $stats['customers']['encrypted']++;
        } else {
            $stats['customers']['skipped']++;
        }

        // 営業所
        if (isset($customer['branches']) && is_array($customer['branches'])) {
            foreach ($customer['branches'] as &$branch) {
                $stats['branches']['total']++;
                $branchModified = false;

                foreach (BRANCH_ENCRYPT_FIELDS as $field) {
                    if (!empty($branch[$field]) && !str_starts_with($branch[$field], ENCRYPTION_PREFIX)) {
                        if (!$dryRun) {
                            $branch[$field] = encryptValue($branch[$field]);
                        }
                        $branchModified = true;
                    }
                }

                if ($branchModified) {
                    $stats['branches']['encrypted']++;
                } else {
                    $stats['branches']['skipped']++;
                }
            }
            unset($branch);
        }
    }
    unset($customer);
}
echo "  顧客: {$stats['customers']['total']}件中 {$stats['customers']['encrypted']}件を暗号化（{$stats['customers']['skipped']}件スキップ）\n";
echo "  営業所: {$stats['branches']['total']}件中 {$stats['branches']['encrypted']}件を暗号化（{$stats['branches']['skipped']}件スキップ）\n";

// 担当者の暗号化
echo "\n--- 担当者データ ---\n";
if (isset($data['assignees']) && is_array($data['assignees'])) {
    foreach ($data['assignees'] as &$assignee) {
        $stats['assignees']['total']++;
        $modified = false;

        foreach (ASSIGNEE_ENCRYPT_FIELDS as $field) {
            if (!empty($assignee[$field]) && !str_starts_with($assignee[$field], ENCRYPTION_PREFIX)) {
                if (!$dryRun) {
                    $assignee[$field] = encryptValue($assignee[$field]);
                }
                $modified = true;
            }
        }

        if ($modified) {
            $stats['assignees']['encrypted']++;
        } else {
            $stats['assignees']['skipped']++;
        }
    }
    unset($assignee);
}
echo "  担当者: {$stats['assignees']['total']}件中 {$stats['assignees']['encrypted']}件を暗号化（{$stats['assignees']['skipped']}件スキップ）\n";

// パートナーの暗号化
echo "\n--- パートナーデータ ---\n";
if (isset($data['partners']) && is_array($data['partners'])) {
    foreach ($data['partners'] as &$partner) {
        $stats['partners']['total']++;
        $modified = false;

        foreach (PARTNER_ENCRYPT_FIELDS as $field) {
            if (!empty($partner[$field]) && !str_starts_with($partner[$field], ENCRYPTION_PREFIX)) {
                if (!$dryRun) {
                    $partner[$field] = encryptValue($partner[$field]);
                }
                $modified = true;
            }
        }

        if ($modified) {
            $stats['partners']['encrypted']++;
        } else {
            $stats['partners']['skipped']++;
        }
    }
    unset($partner);
}
echo "  パートナー: {$stats['partners']['total']}件中 {$stats['partners']['encrypted']}件を暗号化（{$stats['partners']['skipped']}件スキップ）\n";

// 保存
if (!$dryRun) {
    saveData($data);
    echo "\n[OK] data.json を保存しました\n";
} else {
    echo "\n[DRY-RUN] 変更は保存されていません\n";
}

// サマリー
$totalEncrypted = $stats['customers']['encrypted'] + $stats['branches']['encrypted']
    + $stats['assignees']['encrypted'] + $stats['partners']['encrypted'];
$totalRecords = $stats['customers']['total'] + $stats['branches']['total']
    + $stats['assignees']['total'] + $stats['partners']['total'];

echo "\n=== サマリー ===\n";
echo "合計: {$totalRecords}レコード中 {$totalEncrypted}レコードを暗号化\n";
echo "終了時刻: " . date('Y-m-d H:i:s') . "\n";
