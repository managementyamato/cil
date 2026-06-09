<?php
/**
 * 顧客ランク（S/A/B）ヘルパー
 *
 * 価格表（営業ツール pricing タブ）と同じ3段階に統一:
 *   S = 上位ディーラー（大興物産・レンタルニッケン等）
 *   A = 標準ディーラー（その他の取引先ディーラー）
 *   B = 新規開拓・直販（エンドユーザー直接）
 *
 * ランクは「取引関係」で決まる分類なので、最終的には人が割り当てる（customers.customer_rank）。
 * MF請求の年間合計からは「目安（提案）」だけを算出し、自動では確定しない。
 */

require_once __DIR__ . '/../config/config.php';

/** 売上目安 → ランクの閾値（円・税込年間合計）。これ未満は B。 */
const CUSTOMER_RANK_SUGGEST_THRESHOLDS = [
    'S' => 100000000, // 1億以上 → 上位候補
    'A' => 10000000,  // 1千万以上 → 標準候補
    // それ未満 = B（新規・直販候補）
];

/** ランクの表示メタ（ラベル・説明・バッジ色）。価格表 PP_TIER_META と一致させる。 */
function customerRankMeta(string $rank): array {
    $map = [
        'S' => ['label' => '上位ディーラー', 'short' => 'S', 'desc' => '大興物産・レンタルニッケン等', 'color' => '#7c3aed'],
        'A' => ['label' => '標準ディーラー', 'short' => 'A', 'desc' => 'その他の取引先ディーラー',     'color' => '#2563eb'],
        'B' => ['label' => '新規開拓・直販', 'short' => 'B', 'desc' => 'エンドユーザー直接',           'color' => '#059669'],
    ];
    return $map[$rank] ?? ['label' => '未設定', 'short' => '—', 'desc' => '', 'color' => '#9ca3af'];
}

/** 有効なランク値か（S/A/B のみ） */
function isValidCustomerRank(?string $rank): bool {
    return in_array($rank, ['S', 'A', 'B'], true);
}

/**
 * 直近1年のMF請求合計（税込）を会社名で集計する。
 * partner_name が完全一致 または 会社名で前方一致するものを対象（支店サフィックス対応）。
 */
function customerAnnualSales(string $companyName, ?string $since = null): int {
    $companyName = trim($companyName);
    if ($companyName === '') return 0;
    if ($since === null) $since = date('Y-m-d', strtotime('-1 year'));

    try {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(COALESCE(total_amount, amount)), 0) AS s
             FROM `mf_invoices`
             WHERE (deleted_at IS NULL OR deleted_at = '')
               AND billing_date >= ?
               AND (partner_name = ? OR partner_name LIKE ? OR customer_name = ?)"
        );
        $stmt->execute([$since, $companyName, $companyName . '%', $companyName]);
        return (int) round((float) $stmt->fetchColumn());
    } catch (\Throwable $e) {
        error_log('[customerAnnualSales] ' . $e->getMessage());
        return 0;
    }
}

/**
 * 直近Nヶ月のMF請求（税込）の合計と月平均を返す。
 * アカウントマネジメントリストの「直近5ヶ月 合計／月平均」に対応。
 *
 * @return array{total:int, monthly_avg:int, months:int}
 */
function customerRecentBilling(string $companyName, int $months = 5): array {
    $companyName = trim($companyName);
    $months = max(1, $months);
    if ($companyName === '') return ['total' => 0, 'monthly_avg' => 0, 'months' => $months];
    $since = date('Y-m-d', strtotime('-' . $months . ' months'));
    try {
        $pdo = Database::connect();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(COALESCE(total_amount, amount)), 0) AS s
             FROM `mf_invoices`
             WHERE (deleted_at IS NULL OR deleted_at = '')
               AND billing_date >= ?
               AND (partner_name = ? OR partner_name LIKE ? OR customer_name = ?)"
        );
        $stmt->execute([$since, $companyName, $companyName . '%', $companyName]);
        $total = (int) round((float) $stmt->fetchColumn());
        return ['total' => $total, 'monthly_avg' => (int) round($total / $months), 'months' => $months];
    } catch (\Throwable $e) {
        error_log('[customerRecentBilling] ' . $e->getMessage());
        return ['total' => 0, 'monthly_avg' => 0, 'months' => $months];
    }
}

/** 年間売上額からランク目安（S/A/B）を判定 */
function rankFromAnnualSales(int $annualSales): string {
    if ($annualSales >= CUSTOMER_RANK_SUGGEST_THRESHOLDS['S']) return 'S';
    if ($annualSales >= CUSTOMER_RANK_SUGGEST_THRESHOLDS['A']) return 'A';
    return 'B';
}

/**
 * MF実績からのランク「目安（提案）」を算出する。
 * 確定ランクではなく、人が割り当てる際の参考値。
 *
 * @return array{rank:string, annual_sales:int}
 */
function suggestCustomerRank(string $companyName): array {
    $sales = customerAnnualSales($companyName);
    return ['rank' => rankFromAnnualSales($sales), 'annual_sales' => $sales];
}

/** 顧客行の確定ランク（S/A/B）。未設定なら ''。旧5段階データ(C/D)は未設定扱い。 */
function effectiveCustomerRank(array $customer): string {
    $r = $customer['customer_rank'] ?? '';
    return isValidCustomerRank($r) ? $r : '';
}

/**
 * MF取引先由来の「確定顧客」かどうか。
 * source=mf_partners または mf_partner_id を持つものを正式顧客とみなす。
 */
function isMfVerifiedCustomer(array $customer): bool {
    if (($customer['source'] ?? '') === 'mf_partners') return true;
    $mfId = $customer['mf_partner_id'] ?? '';
    return $mfId !== '' && $mfId !== null;
}

/** 会社名の正規化（前後空白・全角/半角空白を除去）— 名寄せ用 */
function normalizeCompanyName(string $name): string {
    $name = preg_replace('/[\s　]+/u', '', trim($name));
    return $name ?? '';
}

