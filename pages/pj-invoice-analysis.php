<?php
require_once '../api/auth.php';

if (!hasPermission(getPageViewPermission('pj-invoice-analysis.php'))) {
    header('Location: index.php');
    exit;
}

$data = getData();

// プロジェクトをIDでマップ化
$projMap = [];
foreach ($data['projects'] as $p) {
    $projMap[$p['id']] = $p;
}

// メーカー名 → 製品カテゴリ名 のフォールバックマップ（product_categoryが空の場合に使用）
$makerNameToId = [];
foreach ($data['manufacturers'] ?? [] as $m) {
    if (!empty($m['name']) && !empty($m['id'])) {
        $makerNameToId[trim($m['name'])] = $m['id'];
    }
}
$makerIdToCategory = [];
foreach ($data['productCategories'] ?? [] as $cat) {
    if (empty($cat['maker_ids']) || empty($cat['name'])) continue;
    foreach ($cat['maker_ids'] as $mid) {
        $makerIdToCategory[$mid] = $cat['name'];
    }
}
// メーカー名 → カテゴリ名 を直結
$makerNameToCategory = [];
foreach ($makerNameToId as $name => $id) {
    if (isset($makerIdToCategory[$id])) {
        $makerNameToCategory[$name] = $makerIdToCategory[$id];
    }
}

// カテゴリID → カテゴリ名 マップ（product_categoryフィールドがIDで保存されている場合のため）
$catIdToName = [];
foreach ($data['productCategories'] ?? [] as $cat) {
    if (!empty($cat['id']) && !empty($cat['name'])) {
        $catIdToName[$cat['id']] = $cat['name'];
    }
}

// タグ名 → カテゴリ名 マップ（PICLES・ゲンバルジャー等 PJ未紐付き請求書用）
$tagNameToCategory = [];
foreach ($data['productCategories'] ?? [] as $cat) {
    if (!empty($cat['tag_name']) && !empty($cat['name'])) {
        $tagNameToCategory[trim($cat['tag_name'])] = $cat['name'];
    }
}

// フィルター値取得
$filterType = $_GET['type'] ?? 'all'; // 販売 / レンタル / all
$filterCat  = $_GET['cat']  ?? 'all'; // product_category

// 利用可能な年月を収集（降順）
$availableYearMonths = [];
foreach ($data['mf_invoices'] as $inv) {
    if (!empty($inv['billing_date'])) {
        $ym = substr($inv['billing_date'], 0, 7); // YYYY-MM
        $availableYearMonths[$ym] = true;
    }
}
krsort($availableYearMonths);
$availableYearMonths = array_keys($availableYearMonths);

// デフォルトは最新月
$filterYearMonth = $_GET['ym'] ?? ($availableYearMonths[0] ?? date('Y-m'));

// 集計
$summary = [];   // [cat][inch] = ['count'=>, 'total'=>]
$kpi     = [];   // [cat] = ['count'=>, 'total'=>]
$grandTotal = 0;
$grandCount = 0;
$noPidCount = 0;
$unsetPjDetail = []; // pid => [id, name, maker, lcd, led, count, total, types]

foreach ($data['mf_invoices'] as $inv) {
    $pid = $inv['project_id'] ?? '';
    if (empty($pid)) { $noPidCount++; continue; }
    $proj = $projMap[$pid] ?? null;
    if (!$proj) continue;

    // 年月フィルター
    $invYm = substr($inv['billing_date'] ?? '', 0, 7);
    if ($invYm !== $filterYearMonth) continue;

    // タイプフィルター（tag_namesに「販売」「レンタル」が含まれるか）
    if ($filterType !== 'all') {
        if (!in_array($filterType, $inv['tag_names'] ?? [])) continue;
    }

    // LEDパネル枚数 → インチ変換テーブル（例: 4×3 → 59インチ）
    $panelToInch = [
        '4x3' => 59,  '4×3' => 59,
        '6x4' => 90,  '6×4' => 90,
        '7x4' => 100, '7×4' => 100,
        '9x6' => 140, '9×6' => 140,
        '7x10' => 150, '7×10' => 150,
        '10x7' => 150, '10×7' => 150,
    ];

    // product_categoryが空なら maker → マスタマッピングでフォールバック
    $rawCat = $proj['product_category'] ?? '';
    if ($rawCat !== '' && $rawCat !== null) {
        // IDで保存されている場合は名前に変換
        $cat = $catIdToName[$rawCat] ?? $rawCat;
    } else {
        $cat = $makerNameToCategory[trim($proj['maker'] ?? '')] ?? null;
    }

    // さらにPJ名からメーカー名・タグ名キーワードを検索してカテゴリ補完
    if ($cat === null || $cat === 'その他') {
        $pjName = $proj['name'] ?? '';
        // メーカー名で検索
        foreach ($makerNameToCategory as $makerName => $catName) {
            if ($makerName !== '' && mb_stripos($pjName, $makerName) !== false) {
                $cat = $catName;
                break;
            }
        }
        // それでも未分類ならタグ名（ゲンバルジャー等）で検索
        if ($cat === null || $cat === 'その他') {
            foreach ($tagNameToCategory as $tagName => $catName) {
                if ($tagName !== '' && mb_stripos($pjName, $tagName) !== false) {
                    $cat = $catName;
                    break;
                }
            }
        }
        $cat = $cat ?? 'その他';
    }

    // 製品カテゴリフィルター
    if ($filterCat !== 'all' && $cat !== $filterCat) continue;

    // インチ決定用ヘルパー関数（テキストからインチを抽出）
    $extractInchFromText = function(string $text) use ($panelToInch): string {
        if ($text === '') return '';
        // 1) 「◯◯インチ」「◯◯型」を優先
        if (preg_match('/(\d{2,3})\s*(?:インチ|型|inch)/iu', $text, $tm)) {
            $isLed = preg_match('/LED(?:ビジョン)?/i', $text);
            return $tm[1] . 'インチ (' . ($isLed ? 'LED' : 'LCD') . ')';
        }
        // 2) LEDパネル枚数パターン（必ずマッピングテーブルに存在する場合のみ）
        if (preg_match_all('/(\d+)\s*[×x×]\s*(\d+)/u', $text, $allMatches, PREG_SET_ORDER)) {
            foreach ($allMatches as $pm) {
                $key1 = $pm[1] . 'x' . $pm[2];
                $key2 = $pm[1] . '×' . $pm[2];
                if (isset($panelToInch[$key1])) return $panelToInch[$key1] . 'インチ (LED)';
                if (isset($panelToInch[$key2])) return $panelToInch[$key2] . 'インチ (LED)';
            }
        }
        return '';
    };

    // インチ表示（PJのサイズ項目 → PJ名 → 請求書タイトル → 未設定 の順で決定）
    $lcd = $proj['lcd_size'] ?? '';
    $led = $proj['led_size'] ?? '';
    // サイズフィールドから先頭の数値のみ抽出（"125ｘ3" → "125" など）
    $lcdNum = preg_match('/^(\d+)/u', $lcd, $m) ? $m[1] : '';
    $ledNum = preg_match('/^(\d+)/u', $led, $m) ? $m[1] : '';
    if ($lcdNum !== '' && $lcd !== '-') {
        $inch = $lcdNum . 'インチ (LCD)';
    } elseif ($ledNum !== '' && $led !== '-') {
        $inch = $ledNum . 'インチ (LED)';
    } else {
        $inch = $extractInchFromText($proj['name'] ?? '');
        if ($inch === '') $inch = $extractInchFromText($inv['title'] ?? '');
        if ($inch === '') $inch = '未設定';
    }

    $amount = (int)($inv['total_amount'] ?? 0);

    // その他詳細リスト収集
    if ($cat === 'その他') {
        if (!isset($unsetPjDetail[$pid])) {
            $unsetPjDetail[$pid] = [
                'id'    => $proj['id'],
                'name'  => $proj['name'] ?? '',
                'maker' => $proj['maker'] ?? '',
                'lcd'   => $proj['lcd_size'] ?? '',
                'led'   => $proj['led_size'] ?? '',
                'count' => 0,
                'total' => 0,
                'types' => [],
            ];
        }
        $unsetPjDetail[$pid]['count']++;
        $unsetPjDetail[$pid]['total'] += $amount;
        // 種別: プロジェクトのtagフィールドを優先、なければ請求書タグ
        $projTag = $proj['tag'] ?? '';
        if (in_array($projTag, ['販売', 'レンタル'])) {
            $unsetPjDetail[$pid]['types'][$projTag] = true;
        } else {
            foreach ($inv['tag_names'] ?? [] as $tag) {
                if (in_array($tag, ['販売', 'レンタル'])) {
                    $unsetPjDetail[$pid]['types'][$tag] = true;
                }
            }
        }
    }

    if (!isset($summary[$cat][$inch])) {
        $summary[$cat][$inch] = ['count' => 0, 'total' => 0];
    }
    $summary[$cat][$inch]['count']++;
    $summary[$cat][$inch]['total'] += $amount;

    if (!isset($kpi[$cat])) $kpi[$cat] = ['count' => 0, 'total' => 0];
    $kpi[$cat]['count']++;
    $kpi[$cat]['total'] += $amount;

    $grandTotal += $amount;
    $grandCount++;
}

// タグベース集計（PJ未紐付き請求書: PICLES・ゲンバルジャー等）
// タイプフィルター（販売/レンタル）が指定されている場合はタグ製品は対象外
if ($filterType === 'all') {
    foreach ($data['mf_invoices'] as $inv) {
        if (!empty($inv['project_id'])) continue; // PJ紐付きは上のループ処理済み

        // 年月フィルター
        $invYm = substr($inv['billing_date'] ?? '', 0, 7);
        if ($invYm !== $filterYearMonth) continue;

        // tag_namesからカテゴリを特定（最初にマッチしたもの）、未マッチは「その他」
        $cat = 'その他';
        foreach ($inv['tag_names'] ?? [] as $tag) {
            if (isset($tagNameToCategory[$tag])) {
                $cat = $tagNameToCategory[$tag];
                break;
            }
        }

        // 製品カテゴリフィルター
        if ($filterCat !== 'all' && $cat !== $filterCat) continue;

        $inch = '—'; // タグベース製品はインチ区分なし
        $amount = (int)($inv['total_amount'] ?? 0);

        if (!isset($summary[$cat][$inch])) {
            $summary[$cat][$inch] = ['count' => 0, 'total' => 0];
        }
        $summary[$cat][$inch]['count']++;
        $summary[$cat][$inch]['total'] += $amount;

        if (!isset($kpi[$cat])) $kpi[$cat] = ['count' => 0, 'total' => 0];
        $kpi[$cat]['count']++;
        $kpi[$cat]['total'] += $amount;

        $grandTotal += $amount;
        $grandCount++;
    }
}

// カテゴリを合計金額降順にソート
arsort($kpi);

// LED / LCD / その他 に分割したサマリーを構築
// inch 値: "100インチ (LED)" → type=LED, key=100インチ
$summaryLed   = []; // [cat][inch] = ['count'=>, 'total'=>]
$summaryLcd   = []; // [cat][inch] = ['count'=>, 'total'=>]
$summaryOther = []; // [cat][inch] = ['count'=>, 'total'=>] ※ 未設定・— など
foreach ($summary as $cat => $inches) {
    foreach ($inches as $inch => $v) {
        if (str_ends_with($inch, '(LED)')) {
            $inchKey = str_replace(' (LED)', '', $inch);
            $summaryLed[$cat][$inchKey] = $v;
        } elseif (str_ends_with($inch, '(LCD)')) {
            $inchKey = str_replace(' (LCD)', '', $inch);
            $summaryLcd[$cat][$inchKey] = $v;
        } else {
            $summaryOther[$cat][$inch] = $v;
        }
    }
}

// 各テーブル用インチ列を収集してソート
$collectInches = function(array $sum): array {
    $cols = [];
    foreach ($sum as $inches) {
        foreach (array_keys($inches) as $k) $cols[$k] = true;
    }
    ksort($cols);
    if (isset($cols['未設定'])) { unset($cols['未設定']); $cols['未設定'] = true; }
    return array_keys($cols);
};
$allInchesLed   = $collectInches($summaryLed);
$allInchesLcd   = $collectInches($summaryLcd);
$allInchesOther = $collectInches($summaryOther);

// 旧 $allInches は棒グラフ等の後方互換用に維持
$allInches = array_unique(array_merge(
    array_map(fn($i) => $i . ' (LED)', $allInchesLed),
    array_map(fn($i) => $i . ' (LCD)', $allInchesLcd),
    $allInchesOther
));

// カテゴリ表示順（KPI合計降順）
$sortedCats = array_keys($kpi);

// グラフ用データ（製品別合計）
$chartLabels = [];
$chartData   = [];
foreach ($kpi as $cat => $v) {
    $chartLabels[] = $cat;
    $chartData[]   = $v['total'];
}

// 未設定詳細を合計金額降順でソート
uasort($unsetPjDetail, function($a, $b) { return $b['total'] - $a['total']; });

require_once '../functions/header.php';
?>
<style<?= nonceAttr() ?>>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.page-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-900);
}
.page-subtitle {
    font-size: 0.875rem;
    color: var(--gray-500);
    margin-top: 0.25rem;
}

/* フィルター */
.filter-bar {
    background: white;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-end;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.filter-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--gray-600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.filter-select {
    padding: 0.4rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    background: white;
    color: var(--gray-800);
    min-width: 140px;
    cursor: pointer;
}
.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(0,0,0,0.06);
}
.filter-btn {
    padding: 0.4rem 1rem;
    background: var(--gray-800);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
    font-weight: 500;
}
.filter-btn:hover { background: var(--gray-900); }
.filter-reset {
    padding: 0.4rem 0.75rem;
    background: white;
    color: var(--gray-600);
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

/* KPIカード */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.kpi-card {
    background: white;
    padding: 1.25rem;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}
.kpi-card.primary {
    background: linear-gradient(135deg, #444 0%, #222 100%);
    color: white;
    border: none;
}
.kpi-card.cat-monitor-suke  { border-left: 4px solid #3b82f6; }
.kpi-card.cat-monitor-taro  { border-left: 4px solid #10b981; }
.kpi-card.cat-monitor-maru  { border-left: 4px solid #f59e0b; }
.kpi-card.cat-picles        { border-left: 4px solid #8b5cf6; }
.kpi-card.cat-genbaru       { border-left: 4px solid #ec4899; }
.kpi-card.cat-monininja     { border-left: 4px solid #f97316; }
.kpi-card.cat-unknown       { border-left: 4px solid #9ca3af; }
.kpi-label {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gray-500);
    margin-bottom: 0.5rem;
}
.kpi-card.primary .kpi-label { color: rgba(255,255,255,0.8); }
.kpi-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-900);
    line-height: 1.2;
}
.kpi-card.primary .kpi-value { color: white; }
.kpi-sub {
    font-size: 0.75rem;
    color: var(--gray-400);
    margin-top: 0.25rem;
}
.kpi-card.primary .kpi-sub { color: rgba(255,255,255,0.7); }

/* グラフ */
.chart-container {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}
.chart-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 1.25rem;
}
.bar-chart {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.bar-row {
    display: grid;
    grid-template-columns: 130px 1fr 120px;
    align-items: center;
    gap: 0.75rem;
}
.bar-label {
    font-size: 0.8rem;
    color: var(--gray-700);
    font-weight: 500;
    text-align: right;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bar-track {
    background: #f3f4f6;
    border-radius: 6px;
    height: 22px;
    overflow: hidden;
}
.bar-fill {
    height: 100%;
    border-radius: 6px;
    transition: width 0.6s ease;
    display: flex;
    align-items: center;
    padding-left: 8px;
    font-size: 0.7rem;
    color: white;
    font-weight: 600;
    min-width: 4px;
}
.bar-amount {
    font-size: 0.8rem;
    color: var(--gray-700);
    font-weight: 600;
    text-align: right;
    white-space: nowrap;
}

/* クロス集計テーブル */
.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.cross-table-wrapper {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 1.5rem;
    padding: 1.5rem;
    overflow-x: auto;
}
.cross-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
    min-width: 600px;
}
.cross-table th {
    background: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
    border-right: 1px solid #e5e7eb;
    padding: 0.6rem 0.75rem;
    font-weight: 600;
    color: var(--gray-600);
    text-align: center;
    white-space: nowrap;
}
.cross-table th.row-header {
    text-align: left;
    min-width: 110px;
    position: sticky;
    left: 0;
    background: #f9fafb;
    z-index: 1;
}
.cross-table td {
    border-bottom: 1px solid #f3f4f6;
    border-right: 1px solid #f3f4f6;
    padding: 0.5rem 0.75rem;
    text-align: right;
    color: var(--gray-700);
}
.cross-table td.row-label {
    font-weight: 600;
    color: var(--gray-800);
    text-align: left;
    background: #fafafa;
    position: sticky;
    left: 0;
    z-index: 1;
}
.cross-table td.row-total {
    font-weight: 700;
    background: #f0f9ff;
    color: #1d4ed8;
    border-left: 2px solid #bfdbfe;
}
.cross-table tr.col-total td {
    font-weight: 700;
    background: #f0fdf4;
    color: #15803d;
    border-top: 2px solid #bbf7d0;
}
.cross-table td.empty {
    color: #d1d5db;
    text-align: center;
}
.amount-cell {
    white-space: nowrap;
}
.count-badge {
    display: inline-block;
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-left: 0.25rem;
}

/* ノーデータ */
.no-data {
    text-align: center;
    padding: 3rem;
    color: var(--gray-400);
    font-size: 0.95rem;
}

/* 未設定詳細テーブル */
.unset-detail-wrapper {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.unset-detail-header {
    background: #fafafa;
    border-bottom: 1px solid #e5e7eb;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.unset-detail-header .section-title {
    margin-bottom: 0;
    color: #7c3aed;
}
.unset-count-badge {
    background: #7c3aed;
    color: white;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
}
.unset-detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.unset-detail-table th {
    background: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
    padding: 0.55rem 0.75rem;
    font-weight: 600;
    color: var(--gray-600);
    white-space: nowrap;
}
.unset-detail-table th:first-child { text-align: left; }
.unset-detail-table th:last-child,
.unset-detail-table th.num { text-align: right; }
.unset-detail-table td {
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid #f3f4f6;
    color: var(--gray-700);
    vertical-align: middle;
}
.unset-detail-table tr:last-child td { border-bottom: none; }
.unset-detail-table tr:hover td { background: #faf5ff; }
.unset-pj-id {
    font-family: monospace;
    font-size: 0.78rem;
    color: var(--gray-500);
    white-space: nowrap;
}
.unset-pj-name {
    font-weight: 500;
    color: var(--gray-800);
    max-width: 280px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.unset-maker {
    color: var(--gray-600);
    white-space: nowrap;
}
.unset-inch {
    color: var(--gray-500);
    font-size: 0.78rem;
    white-space: nowrap;
}
.type-badge {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    white-space: nowrap;
}
.type-badge.hanbaai  { background: #dbeafe; color: #1d4ed8; }
.type-badge.rental   { background: #d1fae5; color: #065f46; }
.type-badge.unknown  { background: #f3f4f6; color: #6b7280; }
.unset-amount {
    text-align: right;
    font-weight: 600;
    color: var(--gray-800);
    white-space: nowrap;
}
.unset-count {
    text-align: right;
    color: var(--gray-500);
    white-space: nowrap;
}
.unset-total-row td {
    font-weight: 700;
    background: #faf5ff;
    color: #7c3aed;
    border-top: 2px solid #e9d5ff;
}

.info-note {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 0.82rem;
    color: #92400e;
    margin-bottom: 1.5rem;
    display: flex;
    gap: 0.5rem;
    align-items: flex-start;
}
</style>

<div class="content-area">

        <div class="page-header">
            <div>
                <div class="page-title">製品・インチ別 請求金額分析</div>
                <div class="page-subtitle">PJに紐づいた請求書を製品カテゴリ×インチで集計します</div>
            </div>
        </div>

        <!-- フィルター -->
        <form method="get" class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">年月</span>
                <select name="ym" class="filter-select">
                    <?php foreach ($availableYearMonths as $ym):
                        [$y, $m] = explode('-', $ym);
                    ?>
                    <option value="<?= htmlspecialchars($ym) ?>" <?= $filterYearMonth === $ym ? 'selected' : '' ?>><?= htmlspecialchars($y) ?>年<?= (int)$m ?>月</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">種別</span>
                <select name="type" class="filter-select">
                    <option value="all"   <?= $filterType === 'all'    ? 'selected' : '' ?>>すべて</option>
                    <option value="販売"  <?= $filterType === '販売'   ? 'selected' : '' ?>>販売</option>
                    <option value="レンタル" <?= $filterType === 'レンタル' ? 'selected' : '' ?>>レンタル</option>
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">製品カテゴリ</span>
                <select name="cat" class="filter-select">
                    <option value="all" <?= $filterCat === 'all' ? 'selected' : '' ?>>すべて</option>
                    <?php foreach ($data['productCategories'] ?? [] as $pc): ?>
                    <option value="<?= htmlspecialchars($pc['name']) ?>" <?= $filterCat === $pc['name'] ? 'selected' : '' ?>><?= htmlspecialchars($pc['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="その他" <?= $filterCat === 'その他' ? 'selected' : '' ?>>その他</option>
                </select>
            </div>
            <button type="submit" class="filter-btn">絞り込む</button>
            <a href="pj-invoice-analysis.php" class="filter-reset">リセット</a>
        </form>

        <!-- PJ未紐付き注記 -->
        <?php if ($noPidCount > 0): ?>
        <div class="info-note">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>PJ未紐付きの請求書 <strong><?= number_format($noPidCount) ?>件</strong> のうち、PICLES・ゲンバルジャー等タグ名が設定された製品は集計に含まれます。残りはfinance.phpのMF請求書一覧でPJを紐付けると反映されます。</span>
        </div>
        <?php endif; ?>

        <!-- KPIカード -->
        <div class="kpi-grid">
            <div class="kpi-card primary">
                <div class="kpi-label">合計請求金額</div>
                <div class="kpi-value">¥<?= number_format($grandTotal) ?></div>
                <div class="kpi-sub"><?= number_format($grandCount) ?>件の集計対象請求書</div>
            </div>
            <?php
            $catClasses = [
                'モニすけ'    => 'cat-monitor-suke',
                'モニたろう'  => 'cat-monitor-taro',
                'モニまる'   => 'cat-monitor-maru',
                'PICLES'      => 'cat-picles',
                'ゲンバルジャー' => 'cat-genbaru',
                'モニんじゃ'  => 'cat-monininja',
                'その他'     => 'cat-unknown',
            ];
            foreach ($kpi as $cat => $v):
                $cls = $catClasses[$cat] ?? '';
            ?>
            <div class="kpi-card <?= $cls ?>">
                <div class="kpi-label"><?= htmlspecialchars($cat) ?></div>
                <div class="kpi-value">¥<?= number_format($v['total']) ?></div>
                <div class="kpi-sub"><?= number_format($v['count']) ?>件</div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($kpi)): ?>
        <div class="cross-table-wrapper">
            <div class="no-data">該当するデータがありません。フィルターを変更してください。</div>
        </div>
        <?php else: ?>

        <!-- 棒グラフ: 製品別合計 -->
        <div class="chart-container">
            <div class="chart-title">製品カテゴリ別 請求金額</div>
            <div class="bar-chart">
                <?php
                $maxVal = max(array_column(array_values($kpi), 'total')) ?: 1;
                $barColors = [
                    'モニすけ'    => '#3b82f6',
                    'PICLES'      => '#8b5cf6',
                    'ゲンバルジャー' => '#ec4899',
                    'モニんじゃ'  => '#f97316',
                    'モニたろう' => '#10b981',
                    'モニまる'  => '#f59e0b',
                    'その他'   => '#9ca3af',
                ];
                foreach ($kpi as $cat => $v):
                    $pct = round($v['total'] / $maxVal * 100, 1);
                    $color = $barColors[$cat] ?? '#6b7280';
                ?>
                <div class="bar-row">
                    <div class="bar-label"><?= htmlspecialchars($cat) ?></div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= $pct ?>%; background:<?= $color ?>;">
                            <?= $pct >= 10 ? number_format(round($v['total'] / 10000)) . '万' : '' ?>
                        </div>
                    </div>
                    <div class="bar-amount">¥<?= number_format($v['total']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- クロス集計テーブル（LED / LCD 分割） -->
        <?php
        // テーブル描画ヘルパー
        $renderCrossTable = function(string $label, string $typeClass, array $summaryData, array $cols, array $sortedCats) {
            if (empty($cols) || empty($summaryData)) return;
            $colTotals    = array_fill_keys($cols, ['count'=>0,'total'=>0]);
            $colGrandTotal = ['count'=>0,'total'=>0];
            // 対象カテゴリのみ（データがある行）
            $activeCats = array_filter($sortedCats, fn($c) => isset($summaryData[$c]));
            if (empty($activeCats)) return;
            ?>
        <div class="section-title" style="margin-top:1.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
            製品 × インチ クロス集計（<?= htmlspecialchars($label) ?>）
        </div>
        <div class="cross-table-wrapper">
            <table class="cross-table">
                <thead>
                    <tr>
                        <th class="row-header">製品 ＼ インチ</th>
                        <?php foreach ($cols as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                        <th style="background:#e0f2fe; border-left:2px solid #bae6fd;">合計</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeCats as $cat):
                        $rowTotal = 0; $rowCount = 0;
                    ?>
                    <tr>
                        <td class="row-label"><?= htmlspecialchars($cat) ?></td>
                        <?php foreach ($cols as $col):
                            $cell = $summaryData[$cat][$col] ?? null;
                            if ($cell) {
                                $colTotals[$col]['count'] += $cell['count'];
                                $colTotals[$col]['total'] += $cell['total'];
                                $rowTotal += $cell['total'];
                                $rowCount += $cell['count'];
                            }
                        ?>
                        <td class="amount-cell">
                            <?php if ($cell): ?>
                                ¥<?= number_format($cell['total']) ?>
                                <span class="count-badge">(<?= $cell['count'] ?>件)</span>
                            <?php else: ?>
                                <span class="empty">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach;
                            $colGrandTotal['count'] += $rowCount;
                            $colGrandTotal['total'] += $rowTotal;
                        ?>
                        <td class="row-total amount-cell">¥<?= number_format($rowTotal) ?><span class="count-badge">(<?= $rowCount ?>件)</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="col-total">
                        <td class="row-label" style="background:#f0fdf4;">合計</td>
                        <?php foreach ($cols as $col): $ct = $colTotals[$col]; ?>
                        <td class="amount-cell">
                            <?php if ($ct['total'] > 0): ?>
                                ¥<?= number_format($ct['total']) ?>
                                <span class="count-badge">(<?= $ct['count'] ?>件)</span>
                            <?php else: ?>
                                <span class="empty">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="row-total amount-cell" style="background:#dcfce7; color:#15803d;">
                            ¥<?= number_format($colGrandTotal['total']) ?>
                            <span class="count-badge">(<?= $colGrandTotal['count'] ?>件)</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
            <?php
        };

        $renderCrossTable('LED', 'led', $summaryLed, $allInchesLed, $sortedCats);
        $renderCrossTable('LCD', 'lcd', $summaryLcd, $allInchesLcd, $sortedCats);
        if (!empty($summaryOther)) {
            $renderCrossTable('未設定 / その他', 'other', $summaryOther, $allInchesOther, $sortedCats);
        }
        ?>

        <!-- インチ別棒グラフ（製品カテゴリ単独選択時のみ詳細表示） -->
        <?php if ($filterCat !== 'all' && isset($summary[$filterCat])): ?>
        <div class="chart-container">
            <div class="chart-title"><?= htmlspecialchars($filterCat) ?> — インチ別 請求金額</div>
            <div class="bar-chart">
                <?php
                $inchData = $summary[$filterCat];
                uasort($inchData, function($a,$b){ return $b['total'] - $a['total']; });
                $maxInch = max(array_column(array_values($inchData), 'total')) ?: 1;
                $color = $barColors[$filterCat] ?? '#6b7280';
                foreach ($inchData as $inch => $v):
                    $pct = round($v['total'] / $maxInch * 100, 1);
                ?>
                <div class="bar-row">
                    <div class="bar-label"><?= htmlspecialchars($inch) ?></div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= $pct ?>%; background:<?= $color ?>;">
                            <?= $pct >= 10 ? number_format(round($v['total'] / 10000)) . '万' : '' ?>
                        </div>
                    </div>
                    <div class="bar-amount">¥<?= number_format($v['total']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- その他 PJ 詳細リスト -->
        <?php if (!empty($unsetPjDetail) && ($filterCat === 'all' || $filterCat === 'その他')): ?>
        <?php
            $unsetTotal = array_sum(array_column($unsetPjDetail, 'total'));
            $unsetCount = array_sum(array_column($unsetPjDetail, 'count'));
        ?>
        <div class="section-title" style="margin-top:0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            その他 詳細リスト
        </div>
        <div class="unset-detail-wrapper">
            <div class="unset-detail-header">
                <div class="section-title">
                    製品カテゴリが「その他」のPJ一覧
                </div>
                <span class="unset-count-badge"><?= count($unsetPjDetail) ?>件のPJ / <?= number_format($unsetCount) ?>件の請求書</span>
            </div>
            <div style="overflow-x:auto;">
            <table class="unset-detail-table">
                <thead>
                    <tr>
                        <th>PJ番号</th>
                        <th>PJ名</th>
                        <th>メーカー</th>
                        <th>LCD</th>
                        <th>LED</th>
                        <th>種別</th>
                        <th class="num">請求件数</th>
                        <th class="num">合計金額</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unsetPjDetail as $detail): ?>
                    <tr>
                        <td><span class="unset-pj-id"><?= htmlspecialchars($detail['id']) ?></span></td>
                        <td><span class="unset-pj-name" title="<?= htmlspecialchars($detail['name']) ?>"><?= htmlspecialchars($detail['name'] ?: '—') ?></span></td>
                        <td><span class="unset-maker"><?= htmlspecialchars($detail['maker'] ?: '—') ?></span></td>
                        <td><span class="unset-inch"><?= ($detail['lcd'] !== '' && $detail['lcd'] !== '-') ? htmlspecialchars($detail['lcd']) . 'インチ' : '—' ?></span></td>
                        <td><span class="unset-inch"><?= ($detail['led'] !== '' && $detail['led'] !== '-') ? htmlspecialchars($detail['led']) . 'インチ' : '—' ?></span></td>
                        <td>
                            <?php if (!empty($detail['types'])): ?>
                                <?php foreach (array_keys($detail['types']) as $t): ?>
                                <span class="type-badge <?= $t === '販売' ? 'hanbaai' : 'rental' ?>"><?= htmlspecialchars($t) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="type-badge unknown">不明</span>
                            <?php endif; ?>
                        </td>
                        <td class="unset-count"><?= number_format($detail['count']) ?>件</td>
                        <td class="unset-amount">¥<?= number_format($detail['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="unset-total-row">
                        <td colspan="6">合計</td>
                        <td class="unset-count"><?= number_format($unsetCount) ?>件</td>
                        <td class="unset-amount">¥<?= number_format($unsetTotal) ?></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>

    </div>

<script<?= nonceAttr() ?>>
<?php require_once '../functions/footer.php'; ?>
