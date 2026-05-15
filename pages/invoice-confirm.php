<?php
/**
 * 請求書確認ページ
 *
 * MFクラウドから同期済みの請求書を一覧表示し、確認（チェック）できるページ
 * 新規設置・撤去・移設等の請求書を確認するために使用
 */
require_once '../api/auth.php';
require_once '../functions/api-middleware.php';
set_error_handler(null);
set_exception_handler(null);

setSecurityHeaders();

// --- データ読み込み ---
$data = getData();
$mfInvoices = $data['mf_invoices'] ?? [];

// 確認記録を読み込み
$confirmations = $data['invoice_confirmations'] ?? [];
$confirmedMap = [];
foreach ($confirmations as $c) {
    if (!empty($c['mf_invoice_id']) && ($c['status'] ?? '') === 'confirmed' && empty($c['deleted_at'])) {
        $confirmedMap[$c['mf_invoice_id']] = $c;
    }
}

// --- フィルター ---
$filterMonth = $_GET['month'] ?? date('Y-m');
$filterPartner = $_GET['partner'] ?? '';
$filterStatus = $_GET['confirmed'] ?? '';  // '' = all, '1' = confirmed, '0' = unconfirmed
$searchKeyword = $_GET['search'] ?? '';

// 月フィルター（'all' で全期間）
$isAllMonths = ($filterMonth === 'all');
$monthStart = $isAllMonths ? '0000-00-00' : ($filterMonth . '-01');
$monthEnd   = $isAllMonths ? '9999-12-31' : date('Y-m-t', strtotime($monthStart));

// 設置・撤去・移設等に関連する請求書のみ抽出するキーワード
$pickupKeywords = ['設置', '撤去', '移設', '搬入', '搬出', '据付', '取付', '取外', '解体', '運搬', '施工'];

/**
 * 請求書が対象キーワードにマッチするか判定
 * 件名、品目名、品目詳細、メモ、備考を検索対象とする
 */
function invoiceMatchesKeywords(array $inv, array $keywords): bool {
    // 品目名のみを検索対象とする
    foreach ($inv['items'] ?? [] as $item) {
        $name = $item['name'] ?? '';
        foreach ($keywords as $kw) {
            if (mb_stripos($name, $kw) !== false) {
                return true;
            }
        }
    }
    return false;
}

/**
 * 請求書からマッチしたキーワード一覧を返す
 */
function getMatchedKeywords(array $inv, array $keywords): array {
    $matched = [];
    foreach ($inv['items'] ?? [] as $item) {
        $name = $item['name'] ?? '';
        foreach ($keywords as $kw) {
            if (mb_stripos($name, $kw) !== false && !in_array($kw, $matched)) {
                $matched[] = $kw;
            }
        }
    }
    return $matched;
}

/**
 * テキスト内のキーワードをハイライト表示
 */
function highlightKeywords(string $text, array $keywords): string {
    $escaped = htmlspecialchars($text);
    foreach ($keywords as $kw) {
        $escapedKw = preg_quote(htmlspecialchars($kw), '/');
        $escaped = preg_replace('/(' . $escapedKw . ')/iu', '<span class="ic-match-kw">$1</span>', $escaped);
    }
    return $escaped;
}

// 月フィルター + キーワード自動フィルター（'all' なら月絞り込みなし）
$filtered = array_filter($mfInvoices, function($inv) use ($monthStart, $monthEnd, $pickupKeywords, $isAllMonths) {
    $billingDate = $inv['billing_date'] ?? $inv['sales_date'] ?? '';
    if (empty($billingDate)) return false;
    if (!$isAllMonths && ($billingDate < $monthStart || $billingDate > $monthEnd)) return false;

    // 対象キーワードにマッチするもののみ
    return invoiceMatchesKeywords($inv, $pickupKeywords);
});

// 取引先フィルター
if (!empty($filterPartner)) {
    $filtered = array_filter($filtered, fn($inv) => ($inv['partner_name'] ?? '') === $filterPartner);
}

// 確認状態フィルター
if ($filterStatus === '1') {
    $filtered = array_filter($filtered, fn($inv) => isset($confirmedMap[$inv['id'] ?? '']));
} elseif ($filterStatus === '0') {
    $filtered = array_filter($filtered, fn($inv) => !isset($confirmedMap[$inv['id'] ?? '']));
}

// キーワード検索（追加の手動絞り込み・PJ番号と担当者・明細名も含む）
if (!empty($searchKeyword)) {
    $filtered = array_filter($filtered, function($inv) use ($searchKeyword) {
        $kw = $searchKeyword;
        if (stripos($inv['title'] ?? '', $kw) !== false) return true;
        if (stripos($inv['partner_name'] ?? '', $kw) !== false) return true;
        if (stripos($inv['billing_number'] ?? '', $kw) !== false) return true;
        // PJ番号（project_id）でマッチ
        if (stripos($inv['project_id'] ?? '', $kw) !== false) return true;
        // 担当者でマッチ
        if (stripos($inv['assignee'] ?? '', $kw) !== false) return true;
        // 明細名でマッチ
        foreach ($inv['items'] ?? [] as $itm) {
            if (stripos($itm['name'] ?? '', $kw) !== false) return true;
        }
        return false;
    });
}

// ソート（請求日 降順）
usort($filtered, fn($a, $b) => strcmp($b['billing_date'] ?? '', $a['billing_date'] ?? ''));

// --- POST処理（確認トグル）---
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';
    $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
              || (($_POST['ajax'] ?? '') === '1');

    if ($action === 'toggle_confirm') {
        if (!canEditCurrentPage() || !canEdit()) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => '編集権限がありません']);
                exit;
            }
            $message = '編集権限がありません';
            $messageType = 'danger';
        } else {
            $mfInvoiceId = $_POST['mf_invoice_id'] ?? '';
            $currentlyConfirmed = false;
            $changedRow = null;

            // 既に確認済みかチェック
            foreach ($data['invoice_confirmations'] as $idx => $c) {
                if (($c['mf_invoice_id'] ?? '') === $mfInvoiceId && ($c['status'] ?? '') === 'confirmed' && empty($c['deleted_at'])) {
                    // 確認解除
                    $data['invoice_confirmations'][$idx]['status'] = 'pending';
                    $data['invoice_confirmations'][$idx]['updated_at'] = date('Y-m-d H:i:s');
                    $currentlyConfirmed = true;
                    $changedRow = $data['invoice_confirmations'][$idx];
                    break;
                }
            }

            $newConfirmedAt = date('Y-m-d H:i:s');
            if (!$currentlyConfirmed) {
                // 新規確認
                $newRow = [
                    'id'              => uniqid('ic_', true),
                    'mf_invoice_id'   => $mfInvoiceId,
                    'status'          => 'confirmed',
                    'confirmed_by'    => $_SESSION['user_email'] ?? '',
                    'confirmed_at'    => $newConfirmedAt,
                    'requested_by'    => $_SESSION['user_email'] ?? '',
                    'requested_by_name' => $_SESSION['user_name'] ?? '',
                    'created_at'      => $newConfirmedAt,
                ];
                $data['invoice_confirmations'][] = $newRow;
                $changedRow = $newRow;
            }

            // 同時編集衝突防止: 単一行 UPSERT
            saveEntityRow('invoice_confirmations', $changedRow);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success'         => true,
                    'mf_invoice_id'   => $mfInvoiceId,
                    'is_confirmed'    => !$currentlyConfirmed,
                    'confirmed_by'    => $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? '',
                    'confirmed_at'    => $newConfirmedAt,
                    'message'         => $currentlyConfirmed ? '確認を解除しました' : '確認済みにしました',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // フィルターを維持してリダイレクト（fallback: JSなし環境向け）
            $params = array_filter([
                'month' => $filterMonth,
                'partner' => $filterPartner,
                'confirmed' => $filterStatus,
                'search' => $searchKeyword,
                'msg' => $currentlyConfirmed ? '確認を解除しました' : '確認済みにしました',
            ]);
            header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($params));
            exit;
        }
    }
}

if (!empty($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = 'success';
}

$canEditPage = canEditCurrentPage() && canEdit();

// 統計
$totalCount = count($filtered);
$confirmedCount = count(array_filter($filtered, fn($inv) => isset($confirmedMap[$inv['id'] ?? ''])));
$unconfirmedCount = $totalCount - $confirmedCount;

// 取引先リスト（フィルター用 - キーワードマッチ済みのもののみ・全期間時は月絞り込みなし）
$partnerNames = [];
foreach ($mfInvoices as $inv) {
    $billingDate = $inv['billing_date'] ?? $inv['sales_date'] ?? '';
    if (empty($billingDate)) continue;
    if (!$isAllMonths && ($billingDate < $monthStart || $billingDate > $monthEnd)) continue;
    if (!invoiceMatchesKeywords($inv, $pickupKeywords)) continue;
    $pn = $inv['partner_name'] ?? '';
    if (!empty($pn) && !in_array($pn, $partnerNames)) {
        $partnerNames[] = $pn;
    }
}
sort($partnerNames);

// 従業員マップ
$employees = filterDeleted($data['employees'] ?? []);
$employeeMap = [];
foreach ($employees as $emp) {
    if (!empty($emp['email'])) {
        $employeeMap[$emp['email']] = $emp['name'] ?? $emp['email'];
    }
}

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
.ic-stats { display:grid; grid-template-columns:repeat(3, 1fr); gap:10px; margin-bottom:16px; }
.ic-stat { background:white; padding:14px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); text-align:center; text-decoration:none; transition:box-shadow 0.2s; }
.ic-stat:hover { box-shadow:0 2px 8px rgba(0,0,0,0.15); }
.ic-stat-num { font-size:22px; font-weight:700; }
.ic-stat-label { font-size:12px; color:var(--gray-500); margin-top:2px; }
.ic-table { width:100%; border-collapse:collapse; background:white; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
.ic-table th { background:var(--gray-50); padding:10px 14px; text-align:left; font-size:12px; font-weight:600; color:var(--gray-600); border-bottom:1px solid var(--gray-200); white-space:nowrap; }
.ic-table td { padding:10px 14px; border-bottom:1px solid var(--gray-100); font-size:13px; vertical-align:top; }
.ic-table tr:hover { background:var(--gray-50); }
.ic-table tr.is-confirmed { opacity:0.55; }
.ic-table tr.is-confirmed:hover { opacity:0.8; }
.ic-amount { font-weight:600; white-space:nowrap; }
.ic-check-btn { width:28px; height:28px; border-radius:50%; border:2px solid var(--gray-300); background:white; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.15s; }
.ic-check-btn:hover { border-color:var(--primary); background:var(--gray-50); }
.ic-check-btn.checked { border-color:#27ae60; background:#27ae60; color:white; }
.ic-tag { display:inline-block; padding:1px 6px; border-radius:3px; font-size:11px; margin-right:3px; }
.ic-tag-assignee { background:#f3e5f5; color:#7b1fa2; }
.ic-tag-project { background:#e3f2fd; color:#1565c0; }
.ic-detail-link { color:var(--primary); text-decoration:none; font-weight:500; }
.ic-detail-link:hover { text-decoration:underline; }
.ic-confirm-info { font-size:11px; color:var(--gray-400); }
.ic-match-kw { background:#fff3cd; padding:0 2px; border-radius:2px; font-weight:600; }
.ic-items-toggle { cursor:pointer; color:var(--primary); font-size:12px; }
.ic-items-detail { display:none; margin-top:6px; padding:8px; background:var(--gray-50); border-radius:4px; font-size:12px; }
.ic-items-detail table { width:100%; border-collapse:collapse; }
.ic-items-detail td { padding:3px 6px; border-bottom:1px solid var(--gray-200); }
@media (max-width: 768px) {
    .ic-stats { grid-template-columns:repeat(3, 1fr); }
    .ic-table { font-size:12px; }
    .ic-table th, .ic-table td { padding:8px 10px; }
}
</style>

<div class="page-container">

    <div class="page-header">
        <h2>請求書確認</h2>
    </div>
    <p style="color:var(--gray-500); font-size:13px; margin:-8px 0 16px;">
        MF請求書から<?php echo implode('・', array_slice($pickupKeywords, 0, 6)); ?>等に関連するものを自動ピックアップ
    </p>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- 統計 -->
    <div class="ic-stats">
        <?php
        $baseParams = array_filter(['month' => $filterMonth, 'partner' => $filterPartner, 'search' => $searchKeyword]);
        ?>
        <a href="?<?= http_build_query($baseParams) ?>" class="ic-stat">
            <div class="ic-stat-num" style="color:var(--gray-700);"><?= $totalCount ?></div>
            <div class="ic-stat-label">全件</div>
        </a>
        <a href="?<?= http_build_query(array_merge($baseParams, ['confirmed' => '0'])) ?>" class="ic-stat">
            <div class="ic-stat-num" style="color:#e67e22;"><?= $unconfirmedCount ?></div>
            <div class="ic-stat-label">未確認</div>
        </a>
        <a href="?<?= http_build_query(array_merge($baseParams, ['confirmed' => '1'])) ?>" class="ic-stat">
            <div class="ic-stat-num" style="color:#27ae60;"><?= $confirmedCount ?></div>
            <div class="ic-stat-label">確認済み</div>
        </a>
    </div>

    <!-- フィルター -->
    <div style="background:white; padding:12px 16px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:16px;">
        <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <select name="month" class="form-input" style="width:140px;">
                <option value="all" <?= $filterMonth === 'all' ? 'selected' : '' ?>>全期間</option>
                <?php for ($i = -36; $i <= 3; $i++):
                    $m = date('Y-m', strtotime("$i months"));
                    $ml = date('Y年n月', strtotime("$i months"));
                ?>
                <option value="<?= $m ?>" <?= $m === $filterMonth ? 'selected' : '' ?>><?= $ml ?></option>
                <?php endfor; ?>
            </select>
            <select name="partner" class="form-input" style="max-width:200px;">
                <option value="">全取引先</option>
                <?php foreach ($partnerNames as $pn): ?>
                <option value="<?= htmlspecialchars($pn) ?>" <?= $filterPartner === $pn ? 'selected' : '' ?>><?= htmlspecialchars($pn) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="confirmed" class="form-input" style="width:120px;">
                <option value="">全て</option>
                <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>未確認</option>
                <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>確認済み</option>
            </select>
            <input type="text" name="search" class="form-input" placeholder="件名・取引先・番号で検索"
                   value="<?= htmlspecialchars($searchKeyword) ?>" style="max-width:220px;">
            <button type="submit" class="btn btn-primary">検索</button>
            <a href="?month=<?= htmlspecialchars($filterMonth) ?>" class="btn btn-secondary">リセット</a>
        </form>
    </div>

    <!-- 請求書テーブル -->
    <?php if (empty($filtered)): ?>
    <div style="background:white; padding:60px 20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1); text-align:center; color:var(--gray-400);">
        <?= $isAllMonths ? '全期間' : htmlspecialchars($filterMonth) ?> の請求書データがありません<br>
        <span style="font-size:12px;">損益ページから同期を実行してください</span>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="ic-table">
            <thead>
                <tr>
                    <?php if ($canEditPage): ?><th style="width:40px;"></th><?php endif; ?>
                    <th>請求書番号</th>
                    <th>取引先</th>
                    <th>件名</th>
                    <th>請求日</th>
                    <th>支払期限</th>
                    <th style="text-align:right;">金額（税込）</th>
                    <th>担当 / PJ</th>
                    <th>確認</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filtered as $inv):
                    $invId = $inv['id'] ?? '';
                    $isConfirmed = isset($confirmedMap[$invId]);
                    $confirmInfo = $isConfirmed ? $confirmedMap[$invId] : null;
                    $items = $inv['items'] ?? [];
                ?>
                <tr class="<?= $isConfirmed ? 'is-confirmed' : '' ?>" data-row-invoice-id="<?= htmlspecialchars($invId) ?>">
                    <?php if ($canEditPage): ?>
                    <td>
                        <button type="button" class="ic-check-btn <?= $isConfirmed ? 'checked' : '' ?>"
                                data-action="toggle-confirm" data-id="<?= htmlspecialchars($invId) ?>"
                                title="<?= $isConfirmed ? '確認済み（クリックで解除）' : '未確認（クリックで確認）' ?>">
                            <span class="ic-check-icon" style="<?= $isConfirmed ? '' : 'display:none;' ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                        </button>
                    </td>
                    <?php endif; ?>
                    <td style="white-space:nowrap; font-family:monospace; font-size:12px;">
                        <a href="#" class="ic-detail-link" data-action="showDetail" data-invoice='<?= htmlspecialchars(json_encode($inv, JSON_UNESCAPED_UNICODE)) ?>'>
                            <?= htmlspecialchars($inv['billing_number'] ?? '-') ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($inv['partner_name'] ?? '-') ?></td>
                    <td>
                        <?php $matchedKws = getMatchedKeywords($inv, $pickupKeywords); ?>
                        <?= highlightKeywords($inv['title'] ?? '-', $matchedKws) ?>
                        <?php if (!empty($items)): ?>
                        <span class="ic-items-toggle" data-action="toggleItems" data-target="items-<?= htmlspecialchars($invId) ?>">
                            (<?= count($items) ?>明細)
                        </span>
                        <div class="ic-items-detail" id="items-<?= htmlspecialchars($invId) ?>">
                            <table>
                                <?php foreach ($items as $itm): ?>
                                <tr>
                                    <td><?= highlightKeywords($itm['name'] ?? '', $matchedKws) ?></td>
                                    <td style="text-align:right; white-space:nowrap;"><?= !empty($itm['quantity']) ? htmlspecialchars($itm['quantity']) . ' ' . htmlspecialchars($itm['unit'] ?? '') : '' ?></td>
                                    <td style="text-align:right; white-space:nowrap;">&yen;<?= number_format($itm['price'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;"><?= htmlspecialchars($inv['billing_date'] ?? '-') ?></td>
                    <td style="white-space:nowrap;">
                        <?php
                        $dueDate = $inv['due_date'] ?? '';
                        $isOverdue = !empty($dueDate) && $dueDate < date('Y-m-d') && !$isConfirmed;
                        ?>
                        <span style="<?= $isOverdue ? 'color:#c0392b; font-weight:600;' : '' ?>">
                            <?= htmlspecialchars($dueDate ?: '-') ?>
                        </span>
                    </td>
                    <td class="ic-amount" style="text-align:right;">
                        &yen;<?= number_format($inv['total_amount'] ?? 0) ?>
                    </td>
                    <td>
                        <?php if (!empty($inv['assignee'])): ?>
                        <span class="ic-tag ic-tag-assignee"><?= htmlspecialchars($inv['assignee']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($inv['project_id'])): ?>
                        <span class="ic-tag ic-tag-project"><?= htmlspecialchars($inv['project_id']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="ic-confirm-cell">
                        <?php if ($isConfirmed && $confirmInfo): ?>
                        <div class="ic-confirm-info">
                            <?= htmlspecialchars($employeeMap[$confirmInfo['confirmed_by'] ?? ''] ?? ($confirmInfo['confirmed_by'] ?? '')) ?><br>
                            <?= htmlspecialchars(substr($confirmInfo['confirmed_at'] ?? '', 5, 11)) ?>
                        </div>
                        <?php else: ?>
                        <span class="ic-confirm-empty" style="color:var(--gray-300); font-size:12px;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--gray-50); font-weight:600;">
                    <?php if ($canEditPage): ?><td></td><?php endif; ?>
                    <td colspan="5" style="text-align:right; padding-right:14px;">合計 (<?= $totalCount ?>件)</td>
                    <td class="ic-amount" style="text-align:right;">
                        &yen;<?= number_format(array_sum(array_column($filtered, 'total_amount'))) ?>
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

</div>

<!-- 請求書詳細モーダル -->
<div id="detailModal" class="modal">
    <div class="modal-content" style="max-width:640px;">
        <div class="modal-header">
            <h3 id="detailModalTitle">請求書詳細</h3>
            <button type="button" class="close" data-close-modal="detailModal">&times;</button>
        </div>
        <div class="modal-body" id="detailModalBody" style="max-height:70vh; overflow-y:auto;"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal="detailModal">閉じる</button>
        </div>
    </div>
</div>

<script<?= nonceAttr() ?>>
function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function formatNumber(n) {
    return Number(n || 0).toLocaleString();
}

function showInvoiceDetail(inv) {
    const body = document.getElementById('detailModalBody');
    document.getElementById('detailModalTitle').textContent = '請求書 ' + (inv.billing_number || '');

    let html = '<table style="width:100%; border-collapse:collapse; font-size:13px;">';
    const infoRows = [
        ['請求書番号', inv.billing_number],
        ['取引先', inv.partner_name],
        ['件名', inv.title],
        ['請求日', inv.billing_date],
        ['売上日', inv.sales_date],
        ['支払期限', inv.due_date],
        ['担当', inv.assignee],
        ['PJ', inv.project_id],
        ['メモ', inv.memo],
        ['備考', inv.note],
    ];
    for (const [label, val] of infoRows) {
        if (!val) continue;
        html += '<tr><th style="padding:6px 10px; background:var(--gray-50); border:1px solid var(--gray-200); width:100px; font-weight:600; white-space:nowrap;">'
            + escapeHtml(label) + '</th><td style="padding:6px 10px; border:1px solid var(--gray-200);">' + escapeHtml(val) + '</td></tr>';
    }
    html += '</table>';

    // 明細
    const items = inv.items || [];
    if (items.length > 0) {
        html += '<h4 style="margin:16px 0 8px; font-size:13px; color:var(--gray-600);">明細</h4>';
        html += '<table style="width:100%; border-collapse:collapse; font-size:12px;">';
        html += '<thead><tr style="background:var(--gray-50);">'
            + '<th style="padding:6px 8px; border:1px solid var(--gray-200); text-align:left;">品目</th>'
            + '<th style="padding:6px 8px; border:1px solid var(--gray-200); text-align:right; width:70px;">数量</th>'
            + '<th style="padding:6px 8px; border:1px solid var(--gray-200); text-align:center; width:40px;">単位</th>'
            + '<th style="padding:6px 8px; border:1px solid var(--gray-200); text-align:right; width:90px;">単価</th>'
            + '<th style="padding:6px 8px; border:1px solid var(--gray-200); text-align:right; width:100px;">金額</th>'
            + '</tr></thead><tbody>';
        for (const itm of items) {
            const amt = (itm.price || 0) * (itm.quantity || 0);
            html += '<tr>'
                + '<td style="padding:6px 8px; border:1px solid var(--gray-200);">' + escapeHtml(itm.name || '')
                + (itm.detail ? '<div style="font-size:11px; color:var(--gray-400);">' + escapeHtml(itm.detail) + '</div>' : '') + '</td>'
                + '<td style="padding:6px 8px; border:1px solid var(--gray-200); text-align:right;">' + (itm.quantity || '') + '</td>'
                + '<td style="padding:6px 8px; border:1px solid var(--gray-200); text-align:center;">' + escapeHtml(itm.unit || '') + '</td>'
                + '<td style="padding:6px 8px; border:1px solid var(--gray-200); text-align:right;">&yen;' + formatNumber(itm.price) + '</td>'
                + '<td style="padding:6px 8px; border:1px solid var(--gray-200); text-align:right;">&yen;' + formatNumber(amt) + '</td>'
                + '</tr>';
        }
        html += '</tbody></table>';
    }

    // 合計
    html += '<div style="margin-top:12px; text-align:right; font-size:13px;">';
    html += '<div style="color:var(--gray-500);">小計: &yen;' + formatNumber(inv.subtotal) + '</div>';
    html += '<div style="color:var(--gray-500);">消費税: &yen;' + formatNumber(inv.tax) + '</div>';
    html += '<div style="font-size:18px; font-weight:700; margin-top:4px;">合計: &yen;' + formatNumber(inv.total_amount) + '</div>';
    html += '</div>';

    // タグ
    const tags = inv.tag_names || [];
    if (tags.length > 0) {
        html += '<div style="margin-top:12px; display:flex; gap:4px; flex-wrap:wrap;">';
        for (const tag of tags) {
            html += '<span style="display:inline-block; padding:2px 8px; background:var(--gray-100); border-radius:3px; font-size:11px; color:var(--gray-600);">' + escapeHtml(tag) + '</span>';
        }
        html += '</div>';
    }

    body.innerHTML = html;

    // モーダル表示
    document.getElementById('detailModal').classList.add('active');
}

// 確認トグル: 即座にUI更新 → 裏でAJAX送信（楽観的更新）
const CSRF_TOKEN = '<?= htmlspecialchars(generateCsrfToken()) ?>';
async function toggleConfirm(btn) {
    const id = btn.dataset.id;
    if (!id) return;
    const row = btn.closest('tr');
    const wasConfirmed = btn.classList.contains('checked');

    // 楽観的に即座にUI反映
    btn.classList.toggle('checked');
    if (row) row.classList.toggle('is-confirmed');
    const icon = btn.querySelector('.ic-check-icon');
    if (icon) icon.style.display = wasConfirmed ? 'none' : '';
    btn.disabled = true;

    try {
        const fd = new FormData();
        fd.append('action', 'toggle_confirm');
        fd.append('mf_invoice_id', id);
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('ajax', '1');

        const res = await fetch(window.location.pathname, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (!json.success) throw new Error(json.error || '更新失敗');

        // 確認情報セルを更新
        const cell = row?.querySelector('.ic-confirm-cell');
        if (cell) {
            if (json.is_confirmed) {
                const dateStr = (json.confirmed_at || '').slice(5, 16);
                cell.innerHTML = '<div class="ic-confirm-info">'
                    + escapeHtml(json.confirmed_by || '') + '<br>'
                    + escapeHtml(dateStr) + '</div>';
            } else {
                cell.innerHTML = '<span class="ic-confirm-empty" style="color:var(--gray-300); font-size:12px;">-</span>';
            }
        }
        btn.title = json.is_confirmed ? '確認済み（クリックで解除）' : '未確認（クリックで確認）';
    } catch (err) {
        // エラー時はUIをロールバック
        btn.classList.toggle('checked');
        if (row) row.classList.toggle('is-confirmed');
        if (icon) icon.style.display = wasConfirmed ? '' : 'none';
        alert('更新に失敗しました: ' + err.message);
    } finally {
        btn.disabled = false;
    }
}

document.addEventListener('click', e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;

    switch (btn.dataset.action) {
        case 'toggle-confirm': {
            e.preventDefault();
            toggleConfirm(btn);
            break;
        }
        case 'toggleItems': {
            const target = document.getElementById(btn.dataset.target);
            if (target) target.style.display = target.style.display === 'block' ? 'none' : 'block';
            break;
        }
        case 'showDetail': {
            e.preventDefault();
            const inv = JSON.parse(btn.dataset.invoice);
            showInvoiceDetail(inv);
            break;
        }
    }
});

document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => document.getElementById(btn.dataset.closeModal).classList.remove('active'));
});
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('active'); });
});
</script>

<?php require_once '../functions/footer.php'; ?>
