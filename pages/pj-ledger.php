<?php
require_once '../api/auth.php';
require_once '../functions/header.php';
require_once '../functions/pj-ledger-data.php';

$pjData = getPjLedgerData();
$allProjects = filterPjDeleted($pjData['projects'] ?? []);

// プロジェクト管理に存在するPJ番号のみ表示
$mainData = getData();
$masterProjectIds = [];
foreach (($mainData['projects'] ?? []) as $mp) {
    if (!empty($mp['deleted_at'])) continue;
    $masterProjectIds[strtoupper(trim($mp['id']))] = true;
}

// キャンセル除外 + プロジェクト管理に存在するもののみ
$projects = array_values(array_filter($allProjects, function($p) use ($masterProjectIds) {
    if (($p['status'] ?? '') === 'キャンセル') return false;
    $pjNum = strtoupper(trim($p['pj_number'] ?? ''));
    return isset($masterProjectIds[$pjNum]);
}));
usort($projects, function($a, $b) {
    return ($a['no'] ?? 0) - ($b['no'] ?? 0);
});

$canEditPage = canEdit();
$canDeletePage = canDelete();
$lastSync = $pjData['last_sync'] ?? null;

// ─── MF請求書データとPJ番号を紐付け ──────────────
$mfInvoices = $mainData['mf_invoices'] ?? [];

// PJ番号ごとの請求状況を集計
// invoiceByPj[PJ番号] = ['invoices' => [...], 'total' => 合計金額, 'latest_status' => 最新ステータス, 'latest_date' => 最新日付]
$invoiceByPj = [];
foreach ($mfInvoices as $inv) {
    $pid = strtoupper(trim($inv['project_id'] ?? ''));
    if ($pid === '') continue;

    if (!isset($invoiceByPj[$pid])) {
        $invoiceByPj[$pid] = ['invoices' => [], 'total' => 0, 'paid_total' => 0, 'count' => 0, 'paid_count' => 0, 'latest_date' => ''];
    }
    $invoiceByPj[$pid]['invoices'][] = $inv;
    $invoiceByPj[$pid]['total'] += ($inv['total_amount'] ?? 0);
    $invoiceByPj[$pid]['count']++;

    $ps = $inv['payment_status'] ?? '';
    if ($ps === '入金済み' || $ps === '入金済') {
        $invoiceByPj[$pid]['paid_total'] += ($inv['total_amount'] ?? 0);
        $invoiceByPj[$pid]['paid_count']++;
    }

    $bd = $inv['billing_date'] ?? '';
    if ($bd > ($invoiceByPj[$pid]['latest_date'] ?? '')) {
        $invoiceByPj[$pid]['latest_date'] = $bd;
        $invoiceByPj[$pid]['latest_status'] = $ps;
    }
}

// サマリー計算（MFベース）+ 担当者別集計
$activeCount = 0; $endedCount = 0;
$totalMfSales = 0; $totalCost = 0;
$yaStats = []; // 担当者別: ['sales' => 0, 'cost' => 0, 'count' => 0]
foreach ($projects as $p) {
    $s = $p['status'] ?? '';
    if ($s === '使用中') $activeCount++;
    elseif ($s === '終了') $endedCount++;

    $pjNum = strtoupper(trim($p['pj_number'] ?? ''));
    $invD = $invoiceByPj[$pjNum] ?? null;
    $mfSales = $invD ? $invD['total'] : 0;
    $cost = ($p['additional_material_cost'] ?? 0) + ($p['support_material_cost'] ?? 0)
          + ($p['shipping_cost'] ?? 0) + ($p['new_install_material_cost'] ?? 0)
          + ($p['monthly_material_cost'] ?? 0) + ($p['support_cost'] ?? 0) + ($p['expenses'] ?? 0);
    $totalMfSales += $mfSales;
    $totalCost += $cost;

    // 担当者別集計
    $ya = $p['ya_person'] ?? '';
    if ($ya !== '') {
        if (!isset($yaStats[$ya])) $yaStats[$ya] = ['sales' => 0, 'cost' => 0, 'count' => 0];
        $yaStats[$ya]['sales'] += $mfSales;
        $yaStats[$ya]['cost'] += $cost;
        $yaStats[$ya]['count']++;
    }
}
$totalProfit = $totalMfSales - $totalCost;
$avgProfitRate = $totalMfSales > 0 ? round(($totalProfit / $totalMfSales) * 100, 1) : 0;
// 利益率でソート（降順）
uasort($yaStats, function($a, $b) {
    $rateA = $a['sales'] > 0 ? ($a['sales'] - $a['cost']) / $a['sales'] : 0;
    $rateB = $b['sales'] > 0 ? ($b['sales'] - $b['cost']) / $b['sales'] : 0;
    return $rateB <=> $rateA;
});

// 請求サマリー
$invoicedPjCount = 0; $uninvoicedPjCount = 0;
foreach ($projects as $p) {
    $s = $p['status'] ?? '';
    if ($s === 'キャンセル') continue;
    $pjNum = strtoupper(trim($p['pj_number'] ?? ''));
    if ($pjNum === '') continue;
    if (isset($invoiceByPj[$pjNum]) && $invoiceByPj[$pjNum]['count'] > 0) {
        $invoicedPjCount++;
    } else {
        $uninvoicedPjCount++;
    }
}
?>

<style<?= nonceAttr() ?>>
/* ─── PJ台帳用追加スタイル ─────────────────────────── */
.pj-row td {
    vertical-align: middle;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
    font-size: 0.9em;
}
.pj-row:hover { background: var(--gray-50) !important; }
.pj-row.filter-hidden { display: none !important; }

/* PJ番号リンク */
.pj-link { color: var(--primary); text-decoration: none; cursor: pointer; }
.pj-link:hover { text-decoration: underline; }

/* 金額セル */
.money { font-family: 'Segoe UI', monospace; }
.money-positive { color: #2e7d32; }
.money-negative { color: #c62828; }
.num { text-align: right; font-variant-numeric: tabular-nums; }

/* 請求ステータス */
.inv-badge { display: inline-flex; align-items: center; gap: 0.2rem; padding: 0.15rem 0.5rem; border-radius: 10px; font-size: 0.68rem; font-weight: 600; white-space: nowrap; }
.inv-none { background: #fff3e0; color: #e65100; }
.inv-partial { background: #e3f2fd; color: #1565c0; }
.inv-paid { background: #e8f5e9; color: #2e7d32; }
.inv-count { font-size: 0.65rem; color: var(--gray-500); margin-left: 0.2rem; }

/* 展開詳細グリッド */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}
.detail-section {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.detail-section-title {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary-light);
}
.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 0.375rem 0;
    font-size: 0.875rem;
    border-bottom: 1px solid var(--gray-100);
}
.detail-row:last-child { border-bottom: none; }
.detail-label { color: var(--gray-500); }
.detail-value { color: var(--gray-900); font-weight: 500; text-align: right; }
.detail-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
}

/* 担当者別サマリー */
.ya-summary-wrap {
    display: flex; gap: 0.5rem; flex-wrap: wrap;
}
.ya-card {
    background: white; border-radius: 8px; padding: 0.5rem 0.75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08); min-width: 120px; cursor: pointer;
    transition: transform 0.1s, box-shadow 0.1s;
}
.ya-card:hover { transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,0.12); }
.ya-card-active { outline: 2px solid var(--primary); outline-offset: -1px; }
.ya-card-name { font-size: 0.8rem; font-weight: 700; }
.ya-card-rate { font-size: 1.2rem; font-weight: 800; margin: 0.1rem 0; }
.ya-card-detail { font-size: 0.65rem; color: var(--gray-500); }

/* モーダル */
.pj-modal .modal-content { max-width: 900px; max-height: 90vh; overflow-y: auto; }
.pj-modal .form-section { margin-bottom: 1rem; border: 1px solid var(--gray-200); border-radius: 6px; padding: 0.75rem; }
.pj-modal .form-section-title { font-weight: 600; font-size: 0.85rem; margin-bottom: 0.5rem; color: var(--gray-700); }
.pj-modal .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem; }
.pj-modal .form-group { margin-bottom: 0; }
.pj-modal .form-group label { font-size: 0.75rem; display: block; margin-bottom: 0.15rem; color: var(--gray-600); }
.pj-modal .form-input { font-size: 0.8rem; padding: 0.3rem 0.5rem; }
.pj-modal .form-group.full-width { grid-column: 1 / -1; }

</style>

<div class="page-container">
    <!-- ヘッダー -->
    <div class="d-flex align-center justify-between flex-wrap gap-1 mb-2">
        <div class="d-flex align-center gap-1">
            <h2 style="margin:0">PJ管理台帳</h2>
            <span class="text-14 text-gray" id="pjCount">(<?= count($projects) ?>件)</span>
            <?php if ($lastSync): ?>
            <span style="font-size:0.7rem; color:var(--gray-400);">最終同期: <?= htmlspecialchars($lastSync) ?></span>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-1 align-center">
            <?php if ($canEditPage): ?>
            <button class="btn btn-outline" id="btnSync">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="align-middle mr-05"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                スプシ連携
            </button>
            <button class="btn btn-primary" id="btnAdd">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                新規登録
            </button>
            <button class="btn btn-secondary" id="btnBulkType">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                一括種別変更
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 担当者別利益率 -->
    <div class="ya-summary-wrap mb-2">
        <?php
        $yaColorMap = [
            '東田' => ['bg' => '#fce4ec', 'text' => '#c62828', 'bar' => '#ef9a9a'],
            '小黒' => ['bg' => '#e8eaf6', 'text' => '#283593', 'bar' => '#9fa8da'],
            '永沼' => ['bg' => '#e0f2f1', 'text' => '#00695c', 'bar' => '#80cbc4'],
            '西井' => ['bg' => '#fff3e0', 'text' => '#e65100', 'bar' => '#ffcc80'],
            '浅井' => ['bg' => '#f3e5f5', 'text' => '#6a1b9a', 'bar' => '#ce93d8'],
            '足本' => ['bg' => '#e3f2fd', 'text' => '#1565c0', 'bar' => '#90caf9'],
            '鈴木' => ['bg' => '#fce4ec', 'text' => '#ad1457', 'bar' => '#f48fb1'],
            '馬庭' => ['bg' => '#e8f5e9', 'text' => '#2e7d32', 'bar' => '#a5d6a7'],
            '宇佐美' => ['bg' => '#fff8e1', 'text' => '#f57f17', 'bar' => '#fff176'],
        ];
        foreach ($yaStats as $name => $stat):
            $profit = $stat['sales'] - $stat['cost'];
            $rate = $stat['sales'] > 0 ? round(($profit / $stat['sales']) * 100, 1) : 0;
            $c = $yaColorMap[$name] ?? ['bg' => '#f5f5f5', 'text' => '#616161', 'bar' => '#bdbdbd'];
            $rateColor = $rate >= 30 ? '#2e7d32' : ($rate < 0 ? '#c62828' : '#616161');
        ?>
        <div class="ya-card" style="border-left:3px solid <?= $c['text'] ?>" data-ya="<?= htmlspecialchars($name) ?>">
            <div class="ya-card-name" style="color:<?= $c['text'] ?>"><?= htmlspecialchars($name) ?></div>
            <div class="ya-card-rate" style="color:<?= $rateColor ?>"><?= $rate ?>%</div>
            <div class="ya-card-detail">売上 ¥<?= number_format($stat['sales']) ?> / <?= $stat['count'] ?>件</div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 検索フォーム -->
    <div class="d-flex gap-1 align-center flex-wrap mb-2">
        <div class="min-w-150 flex-1">
            <input type="text" id="filterSearch" placeholder="PJ番号・案件名で検索..." class="full-input">
        </div>
        <select id="filterDealer" class="rounded text-14 p-pad-border">
            <option value="">全ディーラー</option>
        </select>
        <select id="filterYaPerson" class="rounded text-14 p-pad-border">
            <option value="">全YA担当</option>
        </select>
        <select id="filterInvoice" class="rounded text-14 p-pad-border">
            <option value="">全請求状況</option>
            <option value="未請求">未請求</option>
            <option value="請求中">請求中</option>
            <option value="一部入金">一部入金</option>
            <option value="入金済">入金済</option>
        </select>
    </div>

    <!-- タグフィルタ（種別＋ステータス） -->
    <?php
    $typeAll = count($projects);
    $typeRental = 0; $typeSales = 0; $typeOther = 0;
    foreach ($projects as $p) {
        $t = $p['type'] ?? '';
        if ($t === 'レンタル') $typeRental++;
        elseif ($t === '販売') $typeSales++;
        else $typeOther++;
    }
    ?>
    <div class="mb-2 d-flex gap-1 flex-wrap align-center">
        <button class="btn btn-primary btn-link filter-type-btn active" data-type="">全種別 (<?= $typeAll ?>)</button>
        <button class="btn btn-secondary btn-link filter-type-btn" data-type="レンタル">レンタル (<?= $typeRental ?>)</button>
        <button class="btn btn-secondary btn-link filter-type-btn" data-type="販売">販売 (<?= $typeSales ?>)</button>
        <button class="btn btn-secondary btn-link filter-type-btn" data-type="その他">その他 (<?= $typeOther ?>)</button>
        <select id="filterStatus" class="rounded text-14 p-pad-border">
            <option value="">全ステータス</option>
            <option value="使用中">使用中</option>
            <option value="終了">終了</option>
        </select>
    </div>

    <!-- テーブル -->
    <div class="table-wrapper">
        <table class="table" id="pjTable">
            <thead>
                <tr>
                    <th class="whitespace-nowrap">PJ番号</th>
                    <th class="whitespace-nowrap">YA担当</th>
                    <th class="whitespace-nowrap">ステータス</th>
                    <th class="whitespace-nowrap">請求状況</th>
                    <th class="whitespace-nowrap">MF請求合計</th>
                    <th class="whitespace-nowrap">原価合計</th>
                    <th class="whitespace-nowrap">利益</th>
                    <th class="whitespace-nowrap">利益率</th>
                </tr>
            </thead>
            <tbody id="pjTableBody">
                <?php if (empty($projects)): ?>
                <tr id="emptyRow"><td colspan="8" style="text-align:center; padding:2rem; color:var(--gray-500);">データがありません</td></tr>
                <?php endif; ?>
                <?php foreach ($projects as $idx => $p):
                    $st = $p['status'] ?? '';

                    // 請求状況
                    $pjNumForAttr = strtoupper(trim($p['pj_number'] ?? ''));
                    $invDataForAttr = $invoiceByPj[$pjNumForAttr] ?? null;
                    $invStatus = '';
                    if (!$invDataForAttr || $invDataForAttr['count'] === 0) {
                        $invStatus = '未請求';
                    } elseif ($invDataForAttr['paid_count'] === $invDataForAttr['count']) {
                        $invStatus = '入金済';
                    } elseif ($invDataForAttr['paid_count'] > 0) {
                        $invStatus = '一部入金';
                    } else {
                        $invStatus = '請求中';
                    }

                    $type = $p['type'] ?? '';
                    $yaName = $p['ya_person'] ?? '';
                ?>
                <tr class="pj-row"
                    data-id="<?= htmlspecialchars($p['id']) ?>"
                    data-status="<?= htmlspecialchars($st) ?>"
                    data-type="<?= htmlspecialchars($type) ?>"
                    data-dealer="<?= htmlspecialchars($p['dealer'] ?? '') ?>"
                    data-ya="<?= htmlspecialchars($yaName) ?>"
                    data-inv="<?= htmlspecialchars($invStatus) ?>">
                    <td class="whitespace-nowrap">
                        <a href="javascript:void(0)" class="pj-link" data-action="open-detail" data-id="<?= htmlspecialchars($p['id']) ?>"><strong><?= htmlspecialchars($p['pj_number'] ?? '') ?></strong></a>
                    </td>
                    <td class="whitespace-nowrap"><?php
                        $yaColors = [
                            '東田' => ['bg' => '#fce4ec', 'text' => '#c62828'],
                            '小黒' => ['bg' => '#e8eaf6', 'text' => '#283593'],
                            '永沼' => ['bg' => '#e0f2f1', 'text' => '#00695c'],
                            '西井' => ['bg' => '#fff3e0', 'text' => '#e65100'],
                            '浅井' => ['bg' => '#f3e5f5', 'text' => '#6a1b9a'],
                            '足本' => ['bg' => '#e3f2fd', 'text' => '#1565c0'],
                            '鈴木' => ['bg' => '#fce4ec', 'text' => '#ad1457'],
                            '馬庭' => ['bg' => '#e8f5e9', 'text' => '#2e7d32'],
                            '宇佐美' => ['bg' => '#fff8e1', 'text' => '#f57f17'],
                        ];
                        $yaColor = $yaColors[$yaName] ?? ['bg' => '#f5f5f5', 'text' => '#616161'];
                        if (!empty($yaName)):
                    ?>
                        <span class="d-inline-block rounded text-xs font-medium tag-xs" style="background:<?= $yaColor['bg'] ?>;color:<?= $yaColor['text'] ?>"><?= htmlspecialchars($yaName) ?></span>
                    <?php else: ?>-<?php endif; ?>
                    </td>
                    <td class="whitespace-nowrap"><?php
                        if ($st === '使用中') echo '<span class="d-inline-block rounded text-xs font-semibold tag-xs" style="background:#e8f5e9;color:#2e7d32">● 使用中</span>';
                        elseif ($st === '終了') echo '<span class="d-inline-block rounded text-xs font-semibold tag-xs" style="background:#fce4ec;color:#c62828">終了</span>';
                        else echo htmlspecialchars($st);
                    ?></td>
                    <td class="whitespace-nowrap"><?php
                        $invData = $invoiceByPj[$pjNumForAttr] ?? null;
                        if (!$invData || $invData['count'] === 0) {
                            echo '<span class="inv-badge inv-none">未請求</span>';
                        } else {
                            $paidCnt = $invData['paid_count'];
                            $cnt = $invData['count'];
                            if ($paidCnt === $cnt) echo '<span class="inv-badge inv-paid">入金済</span>';
                            elseif ($paidCnt > 0) echo '<span class="inv-badge inv-partial">一部入金</span>';
                            else echo '<span class="inv-badge inv-partial">請求中</span>';
                        }
                    ?></td>
                    <?php
                        // MF請求合計
                        $invData = $invoiceByPj[$pjNumForAttr] ?? null;
                        $mfTotal = $invData ? $invData['total'] : 0;

                        // 原価合計（スプシから: 追加部材+対応部材+輸送費+新規設置部材+月間部材+対応費）
                        $costTotal = ($p['additional_material_cost'] ?? 0)
                                   + ($p['support_material_cost'] ?? 0)
                                   + ($p['shipping_cost'] ?? 0)
                                   + ($p['new_install_material_cost'] ?? 0)
                                   + ($p['monthly_material_cost'] ?? 0)
                                   + ($p['support_cost'] ?? 0)
                                   + ($p['expenses'] ?? 0);

                        // 利益 = MF売上 - 原価
                        $calcProfit = $mfTotal - $costTotal;
                        $calcRate = $mfTotal > 0 ? round(($calcProfit / $mfTotal) * 100, 1) : 0;
                    ?>
                    <td class="num money whitespace-nowrap"><?= $mfTotal ? '¥' . number_format($mfTotal) : '-' ?></td>
                    <td class="num money whitespace-nowrap"><?= $costTotal ? '¥' . number_format($costTotal) : '-' ?></td>
                    <td class="num money whitespace-nowrap"><?php
                        if ($mfTotal > 0) {
                            $cls = $calcProfit > 0 ? 'money-positive' : 'money-negative';
                            echo '<span class="' . $cls . '">¥' . number_format($calcProfit) . '</span>';
                        } else echo '-';
                    ?></td>
                    <td class="num whitespace-nowrap"><?php
                        if ($mfTotal > 0) {
                            $cls = $calcRate >= 30 ? 'money-positive' : ($calcRate < 0 ? 'money-negative' : '');
                            echo '<span class="' . $cls . '">' . $calcRate . '%</span>';
                        } else echo '-';
                    ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div id="pjPagination" class="pagination-container"></div>
</div>

<!-- 詳細モーダル -->
<div id="pjDetailModal" class="modal pj-modal">
    <div class="modal-content" style="max-width:700px">
        <div class="modal-header">
            <h3 id="detailModalTitle">PJ詳細</h3>
            <button type="button" class="close" data-close-modal="pjDetailModal">&times;</button>
        </div>
        <div class="modal-body" id="detailModalBody" style="padding:1.25rem;">
            <!-- JSで動的に描画 -->
        </div>
    </div>
</div>

<!-- 一括種別変更モーダル -->
<div id="bulkTypeModal" class="modal pj-modal">
    <div class="modal-content" style="max-width:600px">
        <div class="modal-header">
            <h3>一括レンタル/販売変更</h3>
            <button type="button" class="close" data-close-modal="bulkTypeModal">&times;</button>
        </div>
        <div class="modal-body" style="padding:1.25rem;">
            <div class="d-flex gap-1 align-center mb-2">
                <div class="flex-1">
                    <input type="text" id="bulkSearch" placeholder="PJ番号・案件名で絞り込み..." class="form-input">
                </div>
                <select id="bulkFilterCurrent" class="form-input" style="width:auto;">
                    <option value="">現在の種別</option>
                    <option value="レンタル">レンタル</option>
                    <option value="販売">販売</option>
                    <option value="その他">その他（未設定）</option>
                </select>
            </div>
            <div style="margin-bottom:0.75rem;display:flex;align-items:center;gap:0.75rem;">
                <label style="font-size:0.82rem;font-weight:600;color:var(--gray-700);">変更先:</label>
                <select id="bulkNewType" class="form-input" style="width:auto;">
                    <option value="レンタル">レンタル</option>
                    <option value="販売">販売</option>
                    <option value="">未設定に戻す</option>
                </select>
                <button class="btn btn-sm btn-outline" id="bulkSelectAll">全選択</button>
                <button class="btn btn-sm btn-outline" id="bulkDeselectAll">全解除</button>
            </div>
            <div id="bulkTypeList" style="max-height:400px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:8px;"></div>
            <div style="margin-top:0.75rem;display:flex;justify-content:space-between;align-items:center;">
                <span id="bulkSelectedCount" style="font-size:0.82rem;color:var(--gray-600);">0件選択中</span>
                <div class="d-flex gap-1">
                    <button class="btn btn-secondary" data-close-modal="bulkTypeModal">キャンセル</button>
                    <button class="btn btn-primary" id="btnBulkApply">一括変更</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php // 全プロジェクトデータをJS用にJSON出力（詳細モーダル用）
$projectsJson = [];
foreach ($projects as $p) {
    $pjNum = strtoupper(trim($p['pj_number'] ?? ''));
    $invD = $invoiceByPj[$pjNum] ?? null;
    $p['_inv'] = $invD;
    $projectsJson[$p['id']] = $p;
}
?>
<script<?= nonceAttr() ?>>
window._pjProjects = <?= json_encode($projectsJson, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<!-- 編集モーダル -->
<div id="pjModal" class="modal pj-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">新規登録</h3>
            <button type="button" class="close" data-close-modal="pjModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="pjForm">
                <?= csrfTokenField() ?>
                <input type="hidden" id="formId" name="id" value="">

                <!-- 基本情報 -->
                <div class="form-section">
                    <div class="form-section-title" style="color:#2e7d32">基本情報</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>PJ番号</label>
                            <input type="text" name="pj_number" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>営業部</label>
                            <select name="sales_dept" class="form-input">
                                <option value="">-</option>
                                <option value="完了">完了</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>YA担当</label>
                            <input type="text" name="ya_person" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>スペース</label>
                            <input type="text" name="space" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>請求者番号</label>
                            <input type="text" name="invoice_number" class="form-input">
                        </div>
                        <div class="form-group full-width">
                            <label>案件名 <span style="color:red">*</span></label>
                            <input type="text" name="project_name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label>ディーラー</label>
                            <input type="text" name="dealer" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>営業所名</label>
                            <input type="text" name="branch_name" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>連絡先メールアドレス</label>
                            <input type="email" name="contact_email" class="form-input">
                        </div>
                    </div>
                </div>

                <!-- 機器情報 -->
                <div class="form-section">
                    <div class="form-section-title" style="color:#e65100">機器情報</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>種別</label>
                            <select name="type" class="form-input">
                                <option value="">-</option>
                                <option value="レンタル">レンタル</option>
                                <option value="販売">販売</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>メーカー</label>
                            <select name="manufacturer" class="form-input">
                                <option value="">-</option>
                                <option value="LEDY">LEDY</option>
                                <option value="ZEMSO">ZEMSO</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>屋内/屋外</label>
                            <select name="indoor_outdoor" class="form-input">
                                <option value="">-</option>
                                <option value="屋内">屋内</option>
                                <option value="屋外">屋外</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>ピッチ</label>
                            <input type="text" name="pitch" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>横枚数</label>
                            <input type="number" name="horizontal_panels" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>縦枚数</label>
                            <input type="number" name="vertical_panels" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>合計枚数</label>
                            <input type="number" name="total_panels" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>LEDサイズ</label>
                            <input type="text" name="led_size" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>マイク1</label>
                            <input type="text" name="mic1" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>マイク2</label>
                            <input type="text" name="mic2" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>縦横</label>
                            <select name="orientation" class="form-input">
                                <option value="">-</option>
                                <option value="横">横</option>
                                <option value="縦">縦</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>色</label>
                            <input type="text" name="color" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>LCDサイズ</label>
                            <input type="text" name="lcd_size" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>CMS/プレイヤー</label>
                            <input type="text" name="cms_player" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>ルーター</label>
                            <input type="text" name="router" class="form-input">
                        </div>
                    </div>
                </div>

                <!-- 契約情報 -->
                <div class="form-section">
                    <div class="form-section-title" style="color:#1565c0">契約情報</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>ステータス</label>
                            <select name="status" class="form-input">
                                <option value="">-</option>
                                <option value="使用中">使用中</option>
                                <option value="終了">終了</option>
                                <option value="キャンセル">キャンセル</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>施工日</label>
                            <input type="date" name="construction_date" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>終了予定日</label>
                            <input type="date" name="end_date" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>保証終了日</label>
                            <input type="date" name="warranty_end_date" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>期間(月)</label>
                            <input type="number" name="period_months" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>レンタル日数</label>
                            <input type="number" name="rental_days" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>営業稼働日数</label>
                            <input type="number" name="sales_working_days" class="form-input" min="0">
                        </div>
                    </div>
                </div>

                <!-- 金額情報 -->
                <div class="form-section">
                    <div class="form-section-title" style="color:#c62828">金額情報</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>売上合計予想</label>
                            <input type="number" name="total_sales_estimate" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>実際の請求金額</label>
                            <input type="number" name="actual_invoice_amount" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>月額費用を除く初期費用</label>
                            <input type="number" name="initial_cost" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>割引金額</label>
                            <input type="number" name="discount_amount" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>レンタル月額売上</label>
                            <input type="number" name="monthly_rental_sales" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>追加売上（追加部材）</label>
                            <input type="number" name="additional_sales" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>追加部材原価</label>
                            <input type="number" name="additional_material_cost" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>対応部材原価</label>
                            <input type="number" name="support_material_cost" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>諸経費</label>
                            <input type="number" name="expenses" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>利益</label>
                            <input type="number" name="profit" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>利益率</label>
                            <input type="text" name="profit_rate" class="form-input" placeholder="例: 39%">
                        </div>
                        <div class="form-group">
                            <label>乖離率</label>
                            <input type="text" name="deviation_rate" class="form-input" placeholder="例: 5%">
                        </div>
                    </div>
                </div>

                <!-- 製品管理・備考 -->
                <div class="form-section">
                    <div class="form-section-title" style="color:#6a1b9a">製品管理・備考</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>輸送費原価</label>
                            <input type="number" name="shipping_cost" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>新規設置時部材原価</label>
                            <input type="number" name="new_install_material_cost" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>月間部材原価</label>
                            <input type="number" name="monthly_material_cost" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>対応費原価</label>
                            <input type="number" name="support_cost" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>使用パネル合計</label>
                            <input type="number" name="used_panel_count" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label>技術費率予想</label>
                            <input type="text" name="tech_cost_ratio_estimate" class="form-input" placeholder="例: 15%">
                        </div>
                        <div class="form-group">
                            <label>技術費率実績</label>
                            <input type="text" name="tech_cost_ratio_actual" class="form-input" placeholder="例: 12%">
                        </div>
                        <div class="form-group full-width">
                            <label>備考</label>
                            <textarea name="remarks" class="form-input" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal="pjModal">キャンセル</button>
            <button type="submit" form="pjForm" class="btn btn-primary">保存</button>
        </div>
    </div>
</div>

<script<?= nonceAttr() ?>>
const csrfToken = '<?= generateCsrfToken() ?>';
const canEdit = <?= $canEditPage ? 'true' : 'false' ?>;

// ─── モーダル制御 ─────────────────────────────────
document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById(btn.dataset.closeModal).classList.remove('active');
    });
});
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); });
});

// ─── フィルタ動的生成 ────────────────────────────
(function() {
    const dealers = new Set();
    const yaPersons = new Set();
    document.querySelectorAll('#pjTableBody tr.pj-row').forEach(tr => {
        const d = tr.dataset.dealer;
        const y = tr.dataset.ya;
        if (d) dealers.add(d);
        if (y) yaPersons.add(y);
    });
    const selD = document.getElementById('filterDealer');
    [...dealers].sort().forEach(d => {
        const opt = document.createElement('option');
        opt.value = d; opt.textContent = d;
        selD.appendChild(opt);
    });
    const selY = document.getElementById('filterYaPerson');
    [...yaPersons].sort().forEach(y => {
        const opt = document.createElement('option');
        opt.value = y; opt.textContent = y;
        selY.appendChild(opt);
    });
})();

// ─── PJ番号クリックで詳細モーダル ───────────────────
document.getElementById('pjTableBody').addEventListener('click', function(e) {
    const link = e.target.closest('[data-action="open-detail"]');
    if (!link) return;
    e.preventDefault();
    const id = link.dataset.id;
    const p = window._pjProjects[id];
    if (!p) return;
    openDetailModal(p);
});

function fmt(v) { return v ? '¥' + Number(v).toLocaleString() : '-'; }

function openDetailModal(p) {
    document.getElementById('detailModalTitle').textContent = (p.pj_number || '') + ' ' + (p.project_name || '');

    // YA担当カラー
    const yaColors = {
        '東田':['#fce4ec','#c62828'],'小黒':['#e8eaf6','#283593'],'永沼':['#e0f2f1','#00695c'],
        '西井':['#fff3e0','#e65100'],'浅井':['#f3e5f5','#6a1b9a'],'足本':['#e3f2fd','#1565c0'],
        '鈴木':['#fce4ec','#ad1457'],'馬庭':['#e8f5e9','#2e7d32'],'宇佐美':['#fff8e1','#f57f17']
    };
    const yc = yaColors[p.ya_person] || ['#f5f5f5','#616161'];
    const yaTag = p.ya_person ? '<span class="d-inline-block rounded text-xs font-medium tag-xs" style="background:'+yc[0]+';color:'+yc[1]+'">'+escapeHtml(p.ya_person)+'</span>' : '-';

    // 種別
    let typeTag = '-';
    if (p.type === 'レンタル') typeTag = '<span class="d-inline-block rounded text-xs font-semibold tag-xs" style="background:#dbeafe;color:#1d4ed8">レンタル</span>';
    else if (p.type === '販売') typeTag = '<span class="d-inline-block rounded text-xs font-semibold tag-xs" style="background:#d1fae5;color:#065f46">販売</span>';
    else if (p.type) typeTag = escapeHtml(p.type);

    // ステータス
    let stTag = escapeHtml(p.status || '-');
    if (p.status === '使用中') stTag = '<span class="d-inline-block rounded text-xs font-semibold tag-xs" style="background:#e8f5e9;color:#2e7d32">● 使用中</span>';
    else if (p.status === '終了') stTag = '<span class="d-inline-block rounded text-xs font-semibold tag-xs" style="background:#fce4ec;color:#c62828">終了</span>';

    // 請求状況
    let invTag = '<span class="inv-badge inv-none">未請求</span>';
    const inv = p._inv;
    if (inv && inv.count > 0) {
        if (inv.paid_count === inv.count) invTag = '<span class="inv-badge inv-paid">入金済</span>';
        else if (inv.paid_count > 0) invTag = '<span class="inv-badge inv-partial">一部入金</span>';
        else invTag = '<span class="inv-badge inv-partial">請求中</span>';
        invTag += ' <span class="inv-count">' + inv.count + '件</span>';
    }

    // 利益色
    const profit = p.profit || 0;
    let profitHtml = '-';
    if (profit) {
        const cls = profit > 0 ? 'money-positive' : 'money-negative';
        profitHtml = '<span class="'+cls+'">'+fmt(profit)+'</span>';
    }
    const pr = p.profit_rate || '';
    let prHtml = '-';
    if (pr !== '') {
        const rv = parseFloat(String(pr).replace('%',''));
        const cls = rv >= 30 ? 'money-positive' : (rv < 0 ? 'money-negative' : '');
        prHtml = '<span class="'+cls+'">'+escapeHtml(pr)+'</span>';
    }

    const row = (label, val) => '<div class="detail-row"><span class="detail-label">'+label+'</span><span class="detail-value">'+val+'</span></div>';

    let html = '<div class="detail-grid">';

    // 基本情報
    html += '<div class="detail-section"><div class="detail-section-title">基本情報</div>';
    html += row('種別', typeTag);
    html += row('YA担当', yaTag);
    html += row('ディーラー', escapeHtml(p.dealer || '-'));
    html += row('営業所', escapeHtml(p.branch_name || '-'));
    html += row('ステータス', stTag);
    html += row('請求状況', invTag);
    html += '</div>';

    // 金額
    html += '<div class="detail-section"><div class="detail-section-title">金額</div>';
    html += row('月額売上', fmt(p.monthly_rental_sales));
    html += row('売上合計予想', fmt(p.total_sales_estimate));
    html += row('実際請求額', fmt(p.actual_invoice_amount));
    html += row('乖離率', escapeHtml(p.deviation_rate || '-'));
    html += row('初期費用', fmt(p.initial_cost));
    html += row('割引金額', fmt(p.discount_amount));
    html += row('追加売上', fmt(p.additional_sales));
    html += row('諸経費', fmt(p.expenses));
    html += row('利益', profitHtml);
    html += row('利益率', prHtml);
    html += '</div>';

    // 原価
    html += '<div class="detail-section"><div class="detail-section-title">原価・コスト</div>';
    html += row('追加部材原価', fmt(p.additional_material_cost));
    html += row('対応部材原価', fmt(p.support_material_cost));
    html += row('輸送費原価', fmt(p.shipping_cost));
    html += row('新規設置時部材', fmt(p.new_install_material_cost));
    html += row('月間部材原価', fmt(p.monthly_material_cost));
    html += row('対応費原価', fmt(p.support_cost));
    html += '</div>';

    // 契約
    html += '<div class="detail-section"><div class="detail-section-title">契約情報</div>';
    html += row('施工日', escapeHtml(p.construction_date || '-'));
    html += row('終了予定日', escapeHtml(p.end_date || '-'));
    html += row('保証終了日', escapeHtml(p.warranty_end_date || '-'));
    html += row('期間', p.period_months ? p.period_months + 'ヶ月' : '-');
    html += row('メーカー', escapeHtml(p.manufacturer || '-'));
    html += row('LEDサイズ', escapeHtml(p.led_size || '-'));
    html += '</div>';

    html += '</div>'; // detail-grid

    <?php if ($canEditPage): ?>
    html += '<div class="detail-actions">';
    html += '<button class="btn btn-sm btn-outline" data-action="edit-from-detail" data-id="'+escapeHtml(p.id)+'">編集</button>';
    <?php if ($canDeletePage): ?>
    html += '<button class="btn btn-sm btn-danger" data-action="delete-from-detail" data-id="'+escapeHtml(p.id)+'">削除</button>';
    <?php endif; ?>
    html += '</div>';
    <?php endif; ?>

    document.getElementById('detailModalBody').innerHTML = html;
    document.getElementById('pjDetailModal').classList.add('active');
}

// ─── 詳細モーダル内ボタンのイベント委譲 ─────────────────
document.getElementById('pjDetailModal').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    const id = btn.dataset.id;
    if (action === 'edit-from-detail') {
        document.getElementById('pjDetailModal').classList.remove('active');
        openEditModal(id);
    } else if (action === 'delete-from-detail') {
        document.getElementById('pjDetailModal').classList.remove('active');
        confirmDelete(id);
    }
});

// ─── 種別タブフィルタ ────────────────────────────
let currentTypeFilter = '';
document.querySelectorAll('.filter-type-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        currentTypeFilter = btn.dataset.type;
        document.querySelectorAll('.filter-type-btn').forEach(b => {
            b.classList.remove('btn-primary', 'active');
            b.classList.add('btn-secondary');
        });
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-primary', 'active');
        applyFilters();
    });
});

// ─── フィルタ ─────────────────────────────────────
function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    const dealer = document.getElementById('filterDealer').value;
    const ya = document.getElementById('filterYaPerson').value;
    const inv = document.getElementById('filterInvoice').value;
    let count = 0;

    document.querySelectorAll('#pjTableBody tr.pj-row').forEach(tr => {
        const text = tr.textContent.toLowerCase();
        const matchSearch = !search || text.includes(search);
        const matchStatus = !status || tr.dataset.status === status;
        const matchDealer = !dealer || tr.dataset.dealer === dealer;
        const matchYa = !ya || tr.dataset.ya === ya;
        const matchInv = !inv || tr.dataset.inv === inv;

        // 種別フィルタ（ボタン）
        let matchType = true;
        if (currentTypeFilter === 'その他') {
            matchType = tr.dataset.type !== 'レンタル' && tr.dataset.type !== '販売';
        } else if (currentTypeFilter) {
            matchType = tr.dataset.type === currentTypeFilter;
        }

        const show = matchSearch && matchStatus && matchType && matchDealer && matchYa && matchInv;
        tr.classList.toggle('filter-hidden', !show);
        if (show) count++;
    });
    document.getElementById('pjCount').textContent = '(' + count + '件)';
}

document.getElementById('filterSearch').addEventListener('input', applyFilters);
document.getElementById('filterStatus').addEventListener('change', applyFilters);
document.getElementById('filterDealer').addEventListener('change', applyFilters);
document.getElementById('filterYaPerson').addEventListener('change', applyFilters);
document.getElementById('filterInvoice').addEventListener('change', applyFilters);

// 担当者カードクリック → フィルタ連動
document.querySelectorAll('.ya-card').forEach(card => {
    card.addEventListener('click', () => {
        const name = card.dataset.ya;
        const sel = document.getElementById('filterYaPerson');
        // トグル: 同じ担当者をクリックで解除
        sel.value = sel.value === name ? '' : name;
        // カードのアクティブ表示
        document.querySelectorAll('.ya-card').forEach(c => c.classList.remove('ya-card-active'));
        if (sel.value) card.classList.add('ya-card-active');
        applyFilters();
    });
});

// ─── ページネーション ─────────────────────────────
// defer付きのcommon-utils.jsがロードされた後に初期化
function initPjPaginator() {
    if (typeof Paginator === 'undefined') return;
    window.pjPaginator = new Paginator({
        container: '#pjTable',
        itemSelector: 'tbody tr.pj-row',
        paginationTarget: '#pjPagination',
        perPage: 50,
        perPageOptions: [20, 50, 100, 0],
        urlParamPrefix: 'pj_'
    });

    // フィルタ適用後にページネーションをリフレッシュ
    const origApplyFilters = applyFilters;
    applyFilters = function() {
        origApplyFilters();
        const table = document.getElementById('pjTable');
        if (table) table.dispatchEvent(new Event('filter-changed'));
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPjPaginator);
} else {
    initPjPaginator();
}

// ─── イベントハンドリング ──────────────────────────
document.addEventListener('click', e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    const id = btn.dataset.id;
    if (action === 'edit') openEditModal(id);
    if (action === 'delete') confirmDelete(id);
});

if (document.getElementById('btnAdd')) {
    document.getElementById('btnAdd').addEventListener('click', openAddModal);
}

// ─── Sheets同期 ───────────────────────────────────
if (document.getElementById('btnSync')) {
    document.getElementById('btnSync').addEventListener('click', async () => {
        if (!confirm('Google Sheetsからデータを同期しますか？\n既存データは上書きされます。')) return;
        const btn = document.getElementById('btnSync');
        btn.disabled = true;
        btn.textContent = '同期中...';
        try {
            const fd = new FormData();
            fd.append('action', 'sync');
            fd.append('csrf_token', csrfToken);
            const res = await fetch('/api/pj-ledger-sync.php', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.success) {
                const d = result.data;
                alert(`同期完了!\n新規: ${d.created}件\n更新: ${d.updated}件\nスキップ: ${d.skipped}件\n合計: ${d.total}件`);
                location.reload();
            } else {
                alert('同期エラー: ' + result.error);
            }
        } catch (e) {
            alert('通信エラー: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Sheets同期';
        }
    });
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = '新規登録';
    document.getElementById('formId').value = '';
    document.getElementById('pjForm').reset();
    document.getElementById('pjModal').classList.add('active');
}

async function openEditModal(id) {
    const res = await fetch('/api/pj-ledger.php?action=get&id=' + encodeURIComponent(id));
    const result = await res.json();
    if (!result.success) { alert('取得エラー: ' + result.error); return; }

    const d = result.data;
    document.getElementById('modalTitle').textContent = '編集: ' + (d.pj_number || '');
    document.getElementById('formId').value = d.id;

    const form = document.getElementById('pjForm');
    form.querySelectorAll('input, select, textarea').forEach(el => {
        if (el.name && el.name !== 'id' && el.name !== 'csrf_token' && d[el.name] !== undefined) {
            el.value = d[el.name];
        }
    });

    document.getElementById('pjModal').classList.add('active');
}

function confirmDelete(id) {
    if (!confirm('この案件を削除してもよろしいですか？')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fd.append('csrf_token', csrfToken);
    fetch('/api/pj-ledger.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert('エラー: ' + d.error); });
}

// ─── フォーム送信 ──────────────────────────────────
document.getElementById('pjForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const id = fd.get('id');
    fd.set('action', id ? 'update' : 'create');

    const res = await fetch('/api/pj-ledger.php', { method: 'POST', body: fd });
    const result = await res.json();
    if (result.success) {
        location.reload();
    } else {
        alert('エラー: ' + result.error);
    }
});

// ─── 一括種別変更 ──────────────────────────────────
(function() {
    const btnOpen = document.getElementById('btnBulkType');
    if (!btnOpen) return;

    const allProjects = Object.values(window._pjProjects || {});
    let bulkItems = [];

    function getTypeLabel(t) {
        if (t === 'レンタル') return 'レンタル';
        if (t === '販売') return '販売';
        return 'その他';
    }
    function getTypeBadge(t) {
        if (t === 'レンタル') return '<span style="display:inline-block;padding:1px 8px;border-radius:8px;font-size:0.72rem;font-weight:600;background:#e3f2fd;color:#1565c0;">レンタル</span>';
        if (t === '販売') return '<span style="display:inline-block;padding:1px 8px;border-radius:8px;font-size:0.72rem;font-weight:600;background:#fff3e0;color:#e65100;">販売</span>';
        return '<span style="display:inline-block;padding:1px 8px;border-radius:8px;font-size:0.72rem;font-weight:600;background:#f5f5f5;color:#757575;">その他</span>';
    }

    function renderBulkList() {
        const search = (document.getElementById('bulkSearch').value || '').toLowerCase();
        const filterCurrent = document.getElementById('bulkFilterCurrent').value;
        const list = document.getElementById('bulkTypeList');

        bulkItems = allProjects.filter(p => {
            if (p.status === 'キャンセル') return false;
            const t = p.type || '';
            if (filterCurrent === 'その他' && t !== '') return false;
            if (filterCurrent && filterCurrent !== 'その他' && t !== filterCurrent) return false;
            if (search) {
                const pj = (p.pj_number || '').toLowerCase();
                const name = (p.project_name || '').toLowerCase();
                if (!pj.includes(search) && !name.includes(search)) return false;
            }
            return true;
        });

        if (!bulkItems.length) {
            list.innerHTML = '<div style="padding:1.5rem;text-align:center;color:var(--gray-400);">該当するPJがありません</div>';
            updateBulkCount();
            return;
        }

        list.innerHTML = bulkItems.map(p => {
            const esc = s => (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            return `<label style="display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0.75rem;border-bottom:1px solid var(--gray-100);cursor:pointer;font-size:0.85rem;" class="bulk-type-row">
                <input type="checkbox" value="${esc(p.id)}" style="flex-shrink:0;">
                <span style="font-weight:600;min-width:60px;">${esc(p.pj_number)}</span>
                <span style="flex:1;color:var(--gray-700);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(p.project_name || p.dealer || '-')}</span>
                ${getTypeBadge(p.type || '')}
            </label>`;
        }).join('');
        updateBulkCount();
    }

    function updateBulkCount() {
        const checked = document.querySelectorAll('#bulkTypeList input[type=checkbox]:checked').length;
        document.getElementById('bulkSelectedCount').textContent = checked + '件選択中';
    }

    btnOpen.addEventListener('click', () => {
        document.getElementById('bulkSearch').value = '';
        document.getElementById('bulkFilterCurrent').value = '';
        renderBulkList();
        document.getElementById('bulkTypeModal').classList.add('active');
    });

    document.getElementById('bulkSearch').addEventListener('input', renderBulkList);
    document.getElementById('bulkFilterCurrent').addEventListener('change', renderBulkList);
    document.getElementById('bulkTypeList').addEventListener('change', updateBulkCount);

    document.getElementById('bulkSelectAll').addEventListener('click', () => {
        document.querySelectorAll('#bulkTypeList input[type=checkbox]').forEach(cb => cb.checked = true);
        updateBulkCount();
    });
    document.getElementById('bulkDeselectAll').addEventListener('click', () => {
        document.querySelectorAll('#bulkTypeList input[type=checkbox]').forEach(cb => cb.checked = false);
        updateBulkCount();
    });

    document.getElementById('btnBulkApply').addEventListener('click', async () => {
        const checked = document.querySelectorAll('#bulkTypeList input[type=checkbox]:checked');
        const ids = Array.from(checked).map(cb => cb.value);
        if (!ids.length) return alert('変更するPJを選択してください');
        const newType = document.getElementById('bulkNewType').value;
        const label = newType || '未設定';
        if (!confirm(ids.length + '件を「' + label + '」に変更しますか？')) return;

        const fd = new FormData();
        fd.append('action', 'bulk_update_type');
        fd.append('ids', JSON.stringify(ids));
        fd.append('type', newType);
        fd.append('csrf_token', csrfToken);

        const res = await fetch('/api/pj-ledger.php', { method: 'POST', body: fd });
        const result = await res.json();
        if (result.success) {
            alert(result.message || '更新しました');
            location.reload();
        } else {
            alert('エラー: ' + result.error);
        }
    });
})();

</script>

<?php require_once '../functions/footer.php'; ?>
