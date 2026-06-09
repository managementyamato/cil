<?php
/**
 * 顧客マスタとディーラー名の混在状況を調査する診断スクリプト
 *
 * 目的:
 *   - スプシインポートで「ディーラー名」が customer に紛れ込んでいるか可視化する
 *   - 影響範囲（件数・候補レコード）を把握する
 *
 * 実行: scripts/php.exe scripts/diag-customer-vs-dealer.php
 *
 * 読取専用。何も変更しません。
 */

if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

require_once __DIR__ . '/../config/config.php';

$pdo = Database::connect();
$dbName = env('DB_NAME', 'yamato_mgt');

echo "=====================================================================\n";
echo " 顧客マスタ / ディーラー名 混在診断 — " . date('Y-m-d H:i:s') . "\n";
echo "=====================================================================\n\n";

// ----- 1. customers テーブル全体像 -----
$totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL")->fetchColumn();
echo "■ 顧客マスタ (customers) 総件数: {$totalCustomers}\n\n";

// ----- 2. source 別の内訳 -----
echo "■ source カラム別の件数:\n";
$stmt = $pdo->query("SELECT COALESCE(NULLIF(source, ''), '(空)') AS src, COUNT(*) AS cnt FROM customers WHERE deleted_at IS NULL GROUP BY src ORDER BY cnt DESC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("  %-40s %5d 件\n", $row['src'], $row['cnt']);
}
echo "\n";

// ----- 3. source ごとに最初の 5 件サンプル -----
echo "■ source 別サンプル (各最大 5 件):\n";
$stmt = $pdo->query("SELECT DISTINCT COALESCE(NULLIF(source, ''), '(空)') AS src FROM customers WHERE deleted_at IS NULL");
$sources = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($sources as $src) {
    echo "  -- source = [{$src}] --\n";
    if ($src === '(空)') {
        $q = $pdo->query("SELECT id, companyName, contact, phone, created_at FROM customers WHERE deleted_at IS NULL AND (source IS NULL OR source = '') LIMIT 5");
    } else {
        $q = $pdo->prepare("SELECT id, companyName, contact, phone, created_at FROM customers WHERE deleted_at IS NULL AND source = ? LIMIT 5");
        $q->execute([$src]);
    }
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        printf("    %-12s | %-40s | contact=%-12s phone=%-15s created=%s\n",
            $r['id'], mb_substr($r['companyName'] ?? '', 0, 40), $r['contact'] ?? '', $r['phone'] ?? '', $r['created_at'] ?? '');
    }
    echo "\n";
}

// ----- 4. 「contact/phone/email/address が全部空」= インポート臭いレコード -----
$lonely = (int)$pdo->query("
    SELECT COUNT(*) FROM customers
    WHERE deleted_at IS NULL
      AND (contact IS NULL OR contact = '')
      AND (phone IS NULL OR phone = '')
      AND (email IS NULL OR email = '')
      AND (address IS NULL OR address = '')
")->fetchColumn();
echo "■ contact/phone/email/address が全て空のレコード: {$lonely} 件 (= インポートで companyName だけ登録された疑い)\n\n";

// ----- 5. projects.customer_name と customers.companyName の突き合わせ -----
$totalProjects = (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL")->fetchColumn();
$pjWithCustomer = (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL AND customer_name IS NOT NULL AND customer_name <> ''")->fetchColumn();
echo "■ projects 総件数: {$totalProjects} / customer_name が入っている: {$pjWithCustomer}\n";

$pjMatched = (int)$pdo->query("
    SELECT COUNT(DISTINCT p.id) FROM projects p
    INNER JOIN customers c ON c.companyName = p.customer_name AND c.deleted_at IS NULL
    WHERE p.deleted_at IS NULL AND p.customer_name IS NOT NULL AND p.customer_name <> ''
")->fetchColumn();
$pjUnmatched = $pjWithCustomer - $pjMatched;
echo "  - customers に一致する: {$pjMatched}\n";
echo "  - customers に一致しない (顧客マスタ未登録 or 名前ズレ): {$pjUnmatched}\n\n";

// ----- 6. projects.dealer_name と customers.companyName の突き合わせ -----
//   ※ dealer_name の値が customers にも登録されている = ディーラーが顧客マスタにも入っている疑い
echo "■ projects.dealer_name が customers にも登録されているケース (= ディーラー混在の証拠):\n";
$dealerInCustomers = $pdo->query("
    SELECT
      c.companyName AS dealer_in_customers,
      COUNT(DISTINCT p.id) AS pj_count,
      c.contact, c.phone, c.email
    FROM customers c
    INNER JOIN projects p ON p.dealer_name = c.companyName AND p.deleted_at IS NULL
    WHERE c.deleted_at IS NULL
    GROUP BY c.id, c.companyName, c.contact, c.phone, c.email
    ORDER BY pj_count DESC
    LIMIT 30
");
$totalDealerDuplicates = 0;
while ($r = $dealerInCustomers->fetch(PDO::FETCH_ASSOC)) {
    $totalDealerDuplicates++;
    printf("  %-30s | 該当案件 %3d 件 | contact=%-10s phone=%-13s email=%s\n",
        mb_substr($r['dealer_in_customers'], 0, 30),
        $r['pj_count'],
        $r['contact'] ?? '',
        $r['phone'] ?? '',
        $r['email'] ?? ''
    );
}
if ($totalDealerDuplicates === 0) {
    echo "  (該当なし)\n";
}
echo "\n";

// ----- 7. ディーラーらしい命名パターンの検出 -----
echo "■ ディーラーらしい命名パターンに該当する customers (キーワード推測):\n";
$dealerKeywords = ['レンタル', 'リース', 'アクティオ', '西尾', 'カンエツ', 'ニッケン', '建機'];
$placeholders = implode(',', array_fill(0, count($dealerKeywords), '?'));
$likeClauses = [];
foreach ($dealerKeywords as $kw) {
    $likeClauses[] = "companyName LIKE ?";
}
$sql = "SELECT companyName, COUNT(*) AS dup FROM customers WHERE deleted_at IS NULL AND (" . implode(' OR ', $likeClauses) . ") GROUP BY companyName ORDER BY companyName";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_map(fn($k) => "%{$k}%", $dealerKeywords));
$dealerKeywordHits = 0;
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dealerKeywordHits++;
    echo "  - " . $r['companyName'] . "\n";
}
if ($dealerKeywordHits === 0) {
    echo "  (該当なし)\n";
} else {
    echo "  → {$dealerKeywordHits} 件ヒット\n";
}
echo "\n";

echo "=====================================================================\n";
echo " まとめ\n";
echo "=====================================================================\n";
echo "・顧客マスタ総数: {$totalCustomers}\n";
echo "・うち詳細情報なし (companyName のみ): {$lonely}\n";
echo "・projects.dealer_name と一致する customers (混在の主因候補): {$totalDealerDuplicates}\n";
echo "・命名パターンでディーラー疑い customers: {$dealerKeywordHits}\n";
