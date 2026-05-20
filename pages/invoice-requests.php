<?php
/**
 * 請求書作成依頼 一覧・編集ページ
 * 営業部からの請求書作成依頼を受け取ってMFに送信する
 */
$_inHub = defined('IN_HUB_PAGE');
if (!$_inHub) {
    require_once '../api/auth.php';
}
require_once '../functions/api-middleware.php';
set_error_handler(null);
set_exception_handler(null);

// 管理部のみアクセス可
if (!isAdmin()) {
    header('Location: index.php?error=admin_only');
    exit;
}

if (!$_inHub) {
    setSecurityHeaders();
}

$pageTitle = '請求書作成依頼';
$bodyClass = 'page-invoice-requests';

if (!$_inHub) {
    require_once '../functions/header.php';
}

$data = getData();
$requests = array_values(array_filter($data['invoice_requests'] ?? [], fn($r) => empty($r['deleted_at'])));

// 申請日時 (フォーム送信日時) 降順でソート。
// source_timestamp はシート取込時のフォーム送信タイムスタンプ。
// 手動入力分は source_timestamp なしなので created_at にフォールバック。
// 「申請順」を優先することで、シート一括取込時にも申請が新しいものが上に並ぶ。
$requestApplicationTime = function(array $r): string {
    $ts = trim((string)($r['source_timestamp'] ?? ''));
    if ($ts !== '') {
        // フォームのタイムスタンプは "YYYY/MM/DD HH:MM:SS" 形式の事が多い
        // strcmp で大小比較可能な形式に正規化 (スラッシュ→ハイフン)
        return str_replace('/', '-', $ts);
    }
    return (string)($r['created_at'] ?? '');
};
usort($requests, fn($a, $b) => strcmp($requestApplicationTime($b), $requestApplicationTime($a)));

// プロジェクト・社員リスト
$projects = filterDeleted($data['projects'] ?? []);
$employees = filterDeleted($data['employees'] ?? []);

// 取引先リスト（MF取引先IDが紐づいている顧客のみ・検索用）
$mfPartners = [];
foreach (filterDeleted($data['customers'] ?? []) as $c) {
    if (empty($c['mf_partner_id'])) continue;
    $mfPartners[] = [
        'name' => $c['companyName'] ?? '',
        'id'   => $c['mf_partner_id'],
    ];
}
usort($mfPartners, fn($a, $b) => strcmp($a['name'], $b['name']));

$statusCounts = [
    'pending'    => 0,
    'sent'       => 0,
    'cancelled'  => 0,
];
foreach ($requests as $r) {
    $st = $r['status'] ?? 'pending';
    if (isset($statusCounts[$st])) $statusCounts[$st]++;
}
?>

<style<?= nonceAttr() ?>>
.ir-status-badge { display:inline-block; padding:2px 8px; font-size:0.72rem; border-radius:10px; font-weight:600; }
.ir-status-pending { background:#fff3e0; color:#e65100; }
.ir-status-sent { background:#dcfce7; color:#15803d; }
.ir-status-cancelled { background:#f3f4f6; color:#6b7280; }
.ir-summary-row { display:flex; gap:0.75rem; margin-bottom:1.25rem; }
.ir-summary-card { flex:1; background:#fff; border:1px solid var(--gray-200); border-radius:10px; padding:0.75rem 1rem; text-align:center; }
.ir-summary-card .num { font-size:1.4rem; font-weight:700; color:var(--gray-800); }
.ir-summary-card .label { font-size:0.73rem; color:var(--gray-500); margin-top:2px; }
.ir-toolbar { display:flex; gap:0.5rem; align-items:center; margin-bottom:1rem; flex-wrap:wrap; }
.ir-list-card { background:#fff; border:1px solid var(--gray-200); border-radius:10px; padding:0.75rem 1rem; margin-bottom:0.5rem; cursor:pointer; transition:box-shadow 0.15s; }
.ir-list-card:hover { box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.ir-list-row { display:flex; align-items:center; gap:0.75rem; }
.ir-list-pj { font-weight:700; min-width:60px; color:var(--primary); }
.ir-list-subject { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ir-list-meta { font-size:0.78rem; color:var(--gray-500); white-space:nowrap; }
.ir-modal-content { max-width:780px; }
.ir-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
.ir-form-grid .form-group.full { grid-column:1/-1; }
.ir-items-table { width:100%; border-collapse:collapse; font-size:0.85rem; margin-top:0.5rem; }
.ir-items-table th, .ir-items-table td { padding:6px 8px; border:1px solid var(--gray-200); text-align:left; }
.ir-items-table th { background:var(--gray-50); font-size:0.75rem; }
.ir-items-table input { width:100%; box-sizing:border-box; padding:4px 6px; font-size:0.82rem; border:1px solid var(--gray-300); border-radius:4px; }
.ir-items-table .btn-remove { color:#dc2626; cursor:pointer; background:none; border:none; font-size:1.1rem; }
.ir-section { background:var(--gray-50); padding:0.75rem; border-radius:8px; margin-bottom:0.75rem; }
.ir-section-title { font-size:0.85rem; font-weight:600; color:var(--gray-700); margin-bottom:0.5rem; }
</style>

<div class="page-container">
    <?php if (!$_inHub) { require_once __DIR__ . '/../functions/hub-tabs.php'; renderHubTabs('accounting'); } ?>
    <div class="page-header" style="justify-content:flex-end;">
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <span style="font-size:0.85rem; color:var(--gray-500);">合計 <?= count($requests) ?>件</span>
            <span id="autoSyncStatus" style="font-size:12px; color:#888; margin-right:0.5rem;" title="ページ滞在中は5分ごとに自動同期されます"></span>
            <button class="btn btn-secondary" id="btnRematchPartners" title="取引先名から MF取引先IDを自動セット（既存の依頼に対して再実行）">取引先ID再紐付け</button>
            <?= uiSyncButton('Sheets', ['id' => 'btnSyncSheet', 'attrs' => 'title="Googleフォーム回答シートから新規依頼を取り込む"']) ?>
        </div>
    </div>

    <div class="ir-summary-row">
        <div class="ir-summary-card"><div class="num"><?= $statusCounts['pending'] ?></div><div class="label">未送信</div></div>
        <div class="ir-summary-card"><div class="num"><?= $statusCounts['sent'] ?></div><div class="label">MF送信済み</div></div>
        <div class="ir-summary-card"><div class="num"><?= count($requests) ?></div><div class="label">全件</div></div>
    </div>

    <div class="ir-toolbar">
        <select id="filterStatus" class="form-input" style="width:150px;">
            <option value="">全ステータス</option>
            <option value="pending">未送信</option>
            <option value="sent">送信済み</option>
            <option value="cancelled">キャンセル</option>
        </select>
        <input type="text" id="searchInput" class="form-input" placeholder="PJ番号・件名・担当者で検索" style="flex:1; max-width:300px;">
    </div>

    <div id="requestList">
        <?php if (empty($requests)): ?>
            <p style="color:var(--gray-400); text-align:center; padding:2rem;">依頼はまだありません</p>
        <?php else: ?>
            <?php foreach ($requests as $r): ?>
                <?php
                $st = $r['status'] ?? 'pending';
                $stLabel = $st === 'sent' ? 'MF送信済' : ($st === 'cancelled' ? 'キャンセル' : '未送信');
                ?>
                <?php
                // 申請日時の表示用: source_timestamp (フォーム送信日時) 優先、なければ created_at (手動入力分)
                // 例: "2026/05/13 14:32:45" → "2026-05-13" のような短い形式に整形
                $appliedAt = trim((string)($r['source_timestamp'] ?? ''));
                if ($appliedAt === '') $appliedAt = (string)($r['created_at'] ?? '');
                // 日付部分を抽出 (YYYY-MM-DD or YYYY/MM/DD)
                $appliedDate = preg_match('/^(\d{4})[-\/](\d{2})[-\/](\d{2})/', $appliedAt, $m)
                    ? "{$m[1]}-{$m[2]}-{$m[3]}"
                    : substr($appliedAt, 0, 10);
                ?>
                <div class="ir-list-card" data-action="view" data-id="<?= htmlspecialchars($r['id']) ?>"
                     data-status="<?= htmlspecialchars($st) ?>"
                     data-search="<?= htmlspecialchars(strtolower(($r['pj_number'] ?? '') . ' ' . ($r['subject'] ?? '') . ' ' . ($r['requester_name'] ?? ''))) ?>">
                    <div class="ir-list-row">
                        <span class="ir-status-badge ir-status-<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($stLabel) ?></span>
                        <span class="ir-list-pj"><?= htmlspecialchars($r['pj_number'] ?? '-') ?></span>
                        <span class="ir-list-subject"><?= htmlspecialchars($r['subject'] ?? '(件名未入力)') ?></span>
                        <span class="ir-list-meta"><?= htmlspecialchars($r['requester_name'] ?? '') ?></span>
                        <span class="ir-list-meta" title="申請日時"><?= htmlspecialchars($appliedDate) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="paginationBar" style="display:none; margin-top:1rem; align-items:center; justify-content:center; gap:0.5rem; flex-wrap:wrap;">
        <button class="btn btn-secondary btn-sm" id="pgFirst" title="先頭">«</button>
        <button class="btn btn-secondary btn-sm" id="pgPrev" title="前へ">‹</button>
        <span id="pgInfo" style="min-width:140px; text-align:center; font-size:0.875rem; color:var(--gray-600);"></span>
        <button class="btn btn-secondary btn-sm" id="pgNext" title="次へ">›</button>
        <button class="btn btn-secondary btn-sm" id="pgLast" title="最後">»</button>
        <select id="pgSize" class="form-input" style="width:90px; margin-left:1rem;">
            <option value="20">20件</option>
            <option value="50" selected>50件</option>
            <option value="100">100件</option>
            <option value="200">200件</option>
        </select>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content ir-modal-content">
        <div class="modal-header">
            <h3 id="editModalTitle">請求書作成依頼</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body" style="max-height:75vh; overflow-y:auto;">
            <input type="hidden" id="editId">

            <div class="ir-section">
                <div class="ir-section-title">依頼情報</div>
                <div class="ir-form-grid">
                    <div class="form-group">
                        <label class="form-label">依頼者名</label>
                        <input type="text" class="form-input" id="requesterName" placeholder="例: 小黒">
                    </div>
                    <div class="form-group">
                        <label class="form-label">PJ番号</label>
                        <input type="text" class="form-input" id="pjNumber" list="pjList" placeholder="例: P914">
                        <datalist id="pjList">
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= htmlspecialchars($p['id'] ?? '') ?>"><?= htmlspecialchars($p['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">件名 <span style="color:#dc2626;">*</span></label>
                        <input type="text" class="form-input" id="subject" placeholder="例: 中部プラントサービス様 ...モニたろう90インチ レンタル">
                    </div>
                    <div class="form-group">
                        <label class="form-label">添付ファイルID（Drive）</label>
                        <input type="text" class="form-input" id="attachedFileId" placeholder="Google Drive ID">
                    </div>
                    <div class="form-group">
                        <label class="form-label">依頼種別</label>
                        <select class="form-input" id="requestType">
                            <option value="">選択</option>
                            <option value="新規レンタル（継続あり）">新規レンタル（継続あり）</option>
                            <option value="新規レンタル（継続なし）">新規レンタル（継続なし）</option>
                            <option value="販売">販売</option>
                            <option value="撤去">撤去</option>
                            <option value="その他">その他</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="ir-section">
                <div class="ir-section-title">請求先</div>
                <div class="ir-form-grid">
                    <div class="form-group">
                        <label class="form-label">請求先名 <span style="color:#dc2626;">*</span></label>
                        <input type="text" class="form-input" id="partnerName" list="partnerList" placeholder="社名を入力すると候補表示" autocomplete="off">
                        <datalist id="partnerList">
                            <?php foreach ($mfPartners as $p): ?>
                                <option value="<?= htmlspecialchars($p['name']) ?>" data-mf-id="<?= htmlspecialchars($p['id']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <div class="hint" style="font-size:0.78rem;color:var(--gray-500);margin-top:2px;">候補から選ぶとMF取引先IDが自動セットされます（<?= count($mfPartners) ?>社登録）</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">部署名</label>
                        <input type="text" class="form-input" id="partnerDepartment" placeholder="例: 営業部">
                    </div>
                    <div class="form-group">
                        <label class="form-label">MF取引先ID <span style="color:#dc2626;">*</span></label>
                        <input type="text" class="form-input" id="mfPartnerId" placeholder="社名選択で自動入力">
                        <div class="hint" style="font-size:0.78rem;color:var(--gray-500);margin-top:2px;">MF送信時に必須。社名から自動セット or 手動入力</div>
                        <div id="mfPartnerIdGuide" style="display:none; font-size:0.8rem; background:#fff7ed; border-left:3px solid #f59e0b; padding:8px 10px; border-radius:4px; margin-top:6px; color:#9a3412; line-height:1.6;">
                            <strong>MF未登録の取引先の場合の手順:</strong><br>
                            1. <a href="https://invoice.moneyforward.com/partners" target="_blank" rel="noopener" style="color:#1d4ed8;text-decoration:underline;">MoneyForward 取引先管理</a> を開く<br>
                            2. 「取引先を追加」で新規作成<br>
                            3. 作成後、画面右上の「取引先ID」をコピー<br>
                            4. このフォームの「MF取引先ID」欄に貼り付け → 保存 → MF送信
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">請求方法</label>
                        <div style="display:flex; gap:0.5rem;">
                            <select class="form-input" id="billingMethod1" style="flex:1;">
                                <option value="">必須</option>
                                <option value="郵送">郵送</option>
                                <option value="メール">メール</option>
                                <option value="FAX">FAX</option>
                                <option value="持参">持参</option>
                            </select>
                            <select class="form-input" id="billingMethod2" style="flex:1;">
                                <option value="">任意（追加）</option>
                                <option value="郵送">郵送</option>
                                <option value="メール">メール</option>
                                <option value="FAX">FAX</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ir-section">
                <div class="ir-section-title">請求条件</div>
                <div class="ir-form-grid">
                    <div class="form-group">
                        <label class="form-label">請求開始日 <span style="color:#dc2626;">*</span></label>
                        <input type="date" class="form-input" id="billingStartDate">
                    </div>
                    <div class="form-group">
                        <label class="form-label">支払期限</label>
                        <input type="date" class="form-input" id="paymentDueDate">
                    </div>
                    <div class="form-group">
                        <label class="form-label">〆日</label>
                        <select class="form-input" id="closingDay">
                            <option value="">選択</option>
                            <option value="末日〆">末日〆</option>
                            <option value="20日〆">20日〆</option>
                            <option value="15日〆">15日〆</option>
                            <option value="10日〆">10日〆</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">レンタル期間</label>
                        <select class="form-input" id="rentalPeriod">
                            <option value="">選択</option>
                            <option value="1ヶ月未満">1ヶ月未満</option>
                            <option value="1年未満">1年未満</option>
                            <option value="1年以上">1年以上</option>
                            <option value="販売（一括）">販売（一括）</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" id="autoRenew"> 翌月以降の自動作成設定が必要</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" id="hasProrated"> 日割り対象品目あり</label>
                    </div>
                </div>
            </div>

            <div class="ir-section">
                <div class="ir-section-title">品目 <button type="button" class="btn btn-sm btn-secondary" id="btnAddItem" style="margin-left:0.5rem;">+ 行追加</button></div>
                <table class="ir-items-table">
                    <thead>
                        <tr>
                            <th style="min-width:200px;">品目名</th>
                            <th style="width:100px;">初回 日額(税抜)</th>
                            <th style="width:80px;">日数</th>
                            <th style="width:100px;">月額(税抜)</th>
                            <th style="width:60px;">数量</th>
                            <th style="width:60px;">単位</th>
                            <th style="width:90px;">消費税</th>
                            <th style="width:30px;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody"></tbody>
                </table>
            </div>

            <div class="ir-section">
                <div class="ir-section-title">メモ・特記事項</div>
                <div class="form-group">
                    <label class="form-label">備考欄</label>
                    <textarea class="form-input" id="notes" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">特記事項（経理向け）</label>
                    <textarea class="form-input" id="specialNotes" rows="3" placeholder="例: 設置費は初回のみ請求し、自動作成なし。撤去費は撤去時に請求"></textarea>
                </div>
            </div>

            <div id="mfStatus" style="margin-top:0.5rem;"></div>
        </div>
        <div class="modal-footer" style="justify-content:space-between;">
            <div>
                <button type="button" class="btn btn-danger" id="btnDelete" style="display:none;">削除</button>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <button type="button" class="btn btn-secondary" data-close-modal>キャンセル</button>
                <button type="button" class="btn btn-primary" id="btnSave">保存</button>
                <button type="button" class="btn btn-success" id="btnSendMf" style="display:none;">MFに送信</button>
            </div>
        </div>
    </div>
</div>

<script<?= nonceAttr() ?>>
(function(){
    'use strict';
    const CSRF = '<?= htmlspecialchars(generateCsrfToken()) ?>';
    const requestsData = <?= json_encode($requests, JSON_UNESCAPED_UNICODE) ?>;
    const mfPartners = <?= json_encode($mfPartners, JSON_UNESCAPED_UNICODE) ?>;
    let editingId = null;

    // 社名 → MF取引先ID 自動セット + 未登録時の案内表示
    function updateMfPartnerIdGuide() {
        const guide = document.getElementById('mfPartnerIdGuide');
        if (!guide) return;
        const mfId = document.getElementById('mfPartnerId').value.trim();
        const pname = document.getElementById('partnerName').value.trim();
        // 取引先名が入力済みでMF取引先IDが空なら案内を表示
        guide.style.display = (pname && !mfId) ? 'block' : 'none';
    }
    document.getElementById('partnerName').addEventListener('input', function() {
        const name = this.value.trim();
        const hit = mfPartners.find(p => p.name === name);
        if (hit) {
            document.getElementById('mfPartnerId').value = hit.id;
        }
        updateMfPartnerIdGuide();
    });
    document.getElementById('mfPartnerId').addEventListener('input', updateMfPartnerIdGuide);

    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
    function showAlert(msg, type) {
        if (typeof window.showAlert === 'function') return window.showAlert(msg, type);
        alert(msg);
    }

    async function apiCall(action, params, method) {
        const url = '/api/invoice-requests.php?action=' + encodeURIComponent(action);
        const opts = { method: method || 'POST' };
        if (method === 'GET') {
            const q = new URLSearchParams(params).toString();
            const r = await fetch(url + (q ? '&' + q : ''));
            return await r.json();
        }
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        for (const [k, v] of Object.entries(params || {})) {
            fd.append(k, v == null ? '' : v);
        }
        const r = await fetch(url, { method: 'POST', body: fd });
        return await r.json();
    }

    // フィルタ + ページネーション
    let currentPage = 1;
    let pageSize = 50;

    function getMatchedCards() {
        const status = document.getElementById('filterStatus').value;
        const search = document.getElementById('searchInput').value.toLowerCase();
        return Array.from(document.querySelectorAll('.ir-list-card')).filter(card => {
            const matchStatus = !status || card.dataset.status === status;
            const matchSearch = !search || card.dataset.search.includes(search);
            card.dataset.matched = (matchStatus && matchSearch) ? '1' : '0';
            return matchStatus && matchSearch;
        });
    }

    function renderPage() {
        const matched = getMatchedCards();
        const total = matched.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;
        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;

        document.querySelectorAll('.ir-list-card').forEach(card => { card.style.display = 'none'; });
        matched.slice(start, end).forEach(card => { card.style.display = ''; });

        const bar = document.getElementById('paginationBar');
        if (total > pageSize) {
            bar.style.display = 'flex';
            const from = total === 0 ? 0 : start + 1;
            const to = Math.min(end, total);
            document.getElementById('pgInfo').textContent = `${from}-${to} / ${total}件 (${currentPage}/${totalPages}ページ)`;
            document.getElementById('pgFirst').disabled = currentPage === 1;
            document.getElementById('pgPrev').disabled = currentPage === 1;
            document.getElementById('pgNext').disabled = currentPage === totalPages;
            document.getElementById('pgLast').disabled = currentPage === totalPages;
        } else {
            bar.style.display = 'none';
        }
    }

    function applyFilter() {
        currentPage = 1;
        renderPage();
    }

    document.getElementById('filterStatus').addEventListener('change', applyFilter);
    // 検索は 200ms debounce で統一
    let irSearchTimer = null;
    document.getElementById('searchInput').addEventListener('input', function(){
        clearTimeout(irSearchTimer);
        irSearchTimer = setTimeout(applyFilter, 200);
    });
    document.getElementById('pgFirst').addEventListener('click', () => { currentPage = 1; renderPage(); });
    document.getElementById('pgPrev').addEventListener('click',  () => { currentPage--; renderPage(); });
    document.getElementById('pgNext').addEventListener('click',  () => { currentPage++; renderPage(); });
    document.getElementById('pgLast').addEventListener('click',  () => { currentPage = 9999; renderPage(); });
    document.getElementById('pgSize').addEventListener('change', e => {
        pageSize = parseInt(e.target.value, 10) || 50;
        currentPage = 1;
        renderPage();
    });
    // 初期描画
    renderPage();

    // 品目行追加
    function addItemRow(item) {
        item = item || {};
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" class="i-name" value="${esc(item.name || '')}" placeholder="品目名"></td>
            <td><input type="number" class="i-init-price" value="${item.initial_unit_price || ''}" placeholder="0"></td>
            <td><input type="number" class="i-init-days" value="${item.initial_days || ''}" placeholder="0"></td>
            <td><input type="number" class="i-monthly-price" value="${item.monthly_unit_price || ''}" placeholder="0"></td>
            <td><input type="number" class="i-monthly-qty" value="${item.monthly_quantity || ''}" placeholder="1"></td>
            <td><input type="text" class="i-unit" value="${esc(item.monthly_unit || '月')}"></td>
            <td>
                <select class="i-tax">
                    <option value="10%対象" ${item.tax_type === '10%対象' ? 'selected' : ''}>10%</option>
                    <option value="軽減8%対象" ${item.tax_type === '軽減8%対象' ? 'selected' : ''}>軽減8%</option>
                    <option value="非課税" ${item.tax_type === '非課税' ? 'selected' : ''}>非課税</option>
                </select>
            </td>
            <td><button type="button" class="btn-remove" title="削除">×</button></td>`;
        tr.querySelector('.btn-remove').addEventListener('click', () => tr.remove());
        document.getElementById('itemsBody').appendChild(tr);
    }
    document.getElementById('btnAddItem').addEventListener('click', () => addItemRow());

    function collectItems() {
        const rows = document.querySelectorAll('#itemsBody tr');
        const items = [];
        rows.forEach(tr => {
            const name = tr.querySelector('.i-name').value.trim();
            if (!name) return;
            items.push({
                name,
                initial_unit_price: Number(tr.querySelector('.i-init-price').value) || 0,
                initial_days:       Number(tr.querySelector('.i-init-days').value) || 0,
                monthly_unit_price: Number(tr.querySelector('.i-monthly-price').value) || 0,
                monthly_quantity:   Number(tr.querySelector('.i-monthly-qty').value) || 0,
                monthly_unit:       tr.querySelector('.i-unit').value || '月',
                tax_type:           tr.querySelector('.i-tax').value,
            });
        });
        return items;
    }

    function clearForm() {
        editingId = null;
        document.getElementById('editId').value = '';
        ['requesterName','pjNumber','subject','attachedFileId','requestType',
         'partnerName','partnerDepartment','mfPartnerId','billingMethod1','billingMethod2',
         'billingStartDate','paymentDueDate','closingDay','rentalPeriod','notes','specialNotes']
            .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        document.getElementById('autoRenew').checked = false;
        document.getElementById('hasProrated').checked = false;
        document.getElementById('itemsBody').innerHTML = '';
        document.getElementById('mfStatus').innerHTML = '';
        document.getElementById('btnDelete').style.display = 'none';
        document.getElementById('btnSendMf').style.display = 'none';
    }

    function fillForm(r) {
        editingId = r.id;
        document.getElementById('editId').value = r.id;
        document.getElementById('requesterName').value = r.requester_name || '';
        document.getElementById('pjNumber').value = r.pj_number || '';
        document.getElementById('subject').value = r.subject || '';
        document.getElementById('attachedFileId').value = r.attached_file_id || '';
        document.getElementById('requestType').value = r.request_type || '';
        document.getElementById('partnerName').value = r.partner_name || '';
        document.getElementById('partnerDepartment').value = r.partner_department || '';
        document.getElementById('mfPartnerId').value = r.mf_partner_id || '';
        document.getElementById('billingMethod1').value = r.billing_method_1 || '';
        document.getElementById('billingMethod2').value = r.billing_method_2 || '';
        document.getElementById('billingStartDate').value = r.billing_start_date || '';
        document.getElementById('paymentDueDate').value = r.payment_due_date || '';
        document.getElementById('closingDay').value = r.closing_day || '';
        document.getElementById('rentalPeriod').value = r.rental_period || '';
        document.getElementById('autoRenew').checked = !!r.auto_renew;
        document.getElementById('hasProrated').checked = !!r.has_prorated;
        document.getElementById('notes').value = r.notes || '';
        document.getElementById('specialNotes').value = r.special_notes || '';

        document.getElementById('itemsBody').innerHTML = '';
        const items = Array.isArray(r.items) ? r.items : [];
        items.forEach(addItemRow);

        // ステータスに応じてボタン表示
        const isSent = r.status === 'sent';
        const statusEl = document.getElementById('mfStatus');
        if (isSent) {
            statusEl.innerHTML = `<div style="background:#dcfce7;padding:8px 12px;border-radius:6px;color:#15803d;">
                MF送信済み（${esc(r.mf_sent_at || '')} by ${esc(r.mf_sent_by || '')}）<br>
                MF請求書ID: ${esc(r.mf_initial_billing_id || '-')}</div>`;
            document.getElementById('btnSendMf').style.display = 'none';
        } else if (r.mf_error_message) {
            statusEl.innerHTML = `<div style="background:#fee2e2;padding:8px 12px;border-radius:6px;color:#dc2626;">
                前回送信エラー: ${esc(r.mf_error_message)}</div>`;
            document.getElementById('btnSendMf').style.display = '';
        } else {
            statusEl.innerHTML = '';
            document.getElementById('btnSendMf').style.display = '';
        }

        document.getElementById('btnDelete').style.display = '';
    }

    function openModal() {
        document.getElementById('editModal').classList.add('active');
    }
    function closeModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    // 取引先ID再紐付け
    document.getElementById('btnRematchPartners').addEventListener('click', async () => {
        if (!confirm('既存の依頼に対して、取引先名から MF取引先IDを自動セットします。よろしいですか？')) return;
        const btn = document.getElementById('btnRematchPartners');
        btn.disabled = true;
        try {
            const res = await apiCall('rematch_partners', {});
            if (!res.success) { showAlert('失敗: ' + (res.error || ''), 'danger'); return; }
            showAlert((res.data?.matched ?? 0) + ' 件にMF取引先IDをセットしました', 'success');
            if ((res.data?.matched ?? 0) > 0) setTimeout(() => location.reload(), 1000);
        } finally {
            btn.disabled = false;
        }
    });

    // Sheets同期（開発中・ボタン非表示時は handler 登録をスキップ）
    if (document.getElementById('btnSyncSheet')) {
    document.getElementById('btnSyncSheet').addEventListener('click', async () => {
        const btn = document.getElementById('btnSyncSheet');
        if (!confirm('Googleフォーム回答シートから新規依頼を取り込みます。よろしいですか？')) return;
        btn.disabled = true;
        const orig = btn.textContent;
        btn.textContent = '同期中...';
        try {
            const res = await apiCall('sync_from_sheet', {});
            if (!res.success) {
                showAlert('同期失敗: ' + (res.error || res.data?.error || ''), 'danger');
                return;
            }
            const d = res.data || {};
            let msg = (d.imported ?? 0) + ' 件取込 / ' + (d.skipped ?? 0) + ' 件スキップ';
            if (d.errors && d.errors.length) msg += ' / ' + d.errors.length + ' 件エラー';
            showAlert(msg, 'success');
            if ((d.imported ?? 0) > 0) setTimeout(() => location.reload(), 1000);
        } catch (e) {
            showAlert('通信エラー: ' + e.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.textContent = orig;
        }
    });
    } // if (btnSyncSheet)

    document.getElementById('requestList').addEventListener('click', e => {
        const card = e.target.closest('[data-action="view"]');
        if (!card) return;
        const r = requestsData.find(x => x.id === card.dataset.id);
        if (!r) return;
        clearForm();
        fillForm(r);
        document.getElementById('editModalTitle').textContent = '請求書作成依頼の編集';
        openModal();
    });

    document.querySelectorAll('[data-close-modal]').forEach(b => {
        b.addEventListener('click', closeModal);
    });

    function buildPayload() {
        const items = collectItems();
        return {
            requester_name: document.getElementById('requesterName').value,
            pj_number: document.getElementById('pjNumber').value,
            subject: document.getElementById('subject').value,
            attached_file_id: document.getElementById('attachedFileId').value,
            request_type: document.getElementById('requestType').value,
            partner_name: document.getElementById('partnerName').value,
            partner_department: document.getElementById('partnerDepartment').value,
            mf_partner_id: document.getElementById('mfPartnerId').value,
            billing_method_1: document.getElementById('billingMethod1').value,
            billing_method_2: document.getElementById('billingMethod2').value,
            billing_start_date: document.getElementById('billingStartDate').value,
            payment_due_date: document.getElementById('paymentDueDate').value,
            closing_day: document.getElementById('closingDay').value,
            rental_period: document.getElementById('rentalPeriod').value,
            auto_renew: document.getElementById('autoRenew').checked ? 1 : 0,
            has_prorated: document.getElementById('hasProrated').checked ? 1 : 0,
            notes: document.getElementById('notes').value,
            special_notes: document.getElementById('specialNotes').value,
            items: JSON.stringify(items),
        };
    }

    document.getElementById('btnSave').addEventListener('click', async () => {
        const subject = document.getElementById('subject').value.trim();
        const partnerName = document.getElementById('partnerName').value.trim();
        if (!subject || !partnerName) { showAlert('件名と請求先名は必須です', 'danger'); return; }

        const payload = buildPayload();
        const action = editingId ? 'update' : 'create';
        if (editingId) payload.id = editingId;

        const res = await apiCall(action, payload);
        if (!res.success) { showAlert(res.error || res.data?.error || '保存失敗', 'danger'); return; }
        showAlert('保存しました', 'success');
        setTimeout(() => location.reload(), 600);
    });

    document.getElementById('btnDelete').addEventListener('click', async () => {
        if (!editingId) return;
        if (!confirm('この依頼を削除しますか？')) return;
        const res = await apiCall('delete', { id: editingId });
        if (!res.success) { showAlert(res.error || '削除失敗', 'danger'); return; }
        showAlert('削除しました', 'success');
        setTimeout(() => location.reload(), 600);
    });

    document.getElementById('btnSendMf').addEventListener('click', async () => {
        if (!editingId) { showAlert('まず保存してから送信してください', 'danger'); return; }

        // 必須項目チェック
        const subject = document.getElementById('subject').value.trim();
        const partnerName = document.getElementById('partnerName').value.trim();
        const mfPartnerId = document.getElementById('mfPartnerId').value.trim();
        const billingStartDate = document.getElementById('billingStartDate').value.trim();
        const items = collectItems();
        if (!subject) { showAlert('件名は必須です', 'danger'); return; }
        if (!partnerName) { showAlert('請求先名は必須です', 'danger'); return; }
        if (!mfPartnerId) { showAlert('MF取引先IDが未設定です', 'danger'); return; }
        if (!billingStartDate) { showAlert('請求開始日が未設定です', 'danger'); return; }
        if (items.length === 0) { showAlert('品目を最低1行追加してください', 'danger'); return; }
        if (items.every(i => !i.initial_unit_price || !i.initial_days)) {
            showAlert('品目の初回日額・日数を入力してください', 'danger'); return;
        }

        const draft = confirm('MoneyForward に送信します。\n\nOK = ドラフト（下書き）として作成（推奨）\nキャンセル = 確定請求書として作成');

        // ① 先に現在のフォーム内容を保存
        const savePayload = buildPayload();
        savePayload.id = editingId;
        const saveRes = await apiCall('update', savePayload);
        if (!saveRes.success) {
            showAlert('保存失敗: ' + (saveRes.error || ''), 'danger');
            return;
        }

        // ② MF送信
        const res = await apiCall('send_to_mf', { id: editingId, draft: draft ? 1 : 0 });
        if (!res.success) {
            const err = res.error || res.data?.error || '送信失敗';
            showAlert('MF送信失敗: ' + err, 'danger');
            return;
        }
        showAlert(res.data?.message || '送信しました', 'success');
        setTimeout(() => location.reload(), 800);
    });

    // ─── スプレッドシート自動同期 (A: ページオープン時 + B: 5分ごとポーリング) ───
    // 表示・エラー処理は silent。新規取込があった時のみリロードして反映。
    // ユーザーが画面を見ている時間帯だけ動作するので、cron 不要で「ほぼリアルタイム」が実現できる。
    const AUTO_SYNC_INTERVAL_MS = 5 * 60 * 1000; // 5分

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }
    function setAutoSyncStatus(state, info) {
        const el = document.getElementById('autoSyncStatus');
        if (!el) return;
        const now = new Date();
        const hhmm = pad2(now.getHours()) + ':' + pad2(now.getMinutes());
        if (state === 'syncing') {
            el.textContent = '自動同期中...';
            el.style.color = '#856404';
        } else if (state === 'ok') {
            el.textContent = '最終同期 ' + hhmm + (info ? ' (' + info + ')' : '');
            el.style.color = '#888';
        } else if (state === 'error') {
            el.textContent = '自動同期失敗 ' + hhmm;
            el.style.color = '#c00';
        }
    }

    let autoSyncRunning = false;
    async function autoSyncFromSheet(reloadIfImported) {
        if (autoSyncRunning) return; // 二重起動防止
        autoSyncRunning = true;
        setAutoSyncStatus('syncing');
        try {
            const res = await apiCall('sync_from_sheet', {});
            if (!res.success) {
                setAutoSyncStatus('error');
                return;
            }
            const d = res.data || {};
            const imported = d.imported ?? 0;
            const skipped = d.skipped ?? 0;
            const info = imported > 0 ? '+' + imported + '件' : (skipped > 0 ? skipped + '件未更新' : '差分なし');
            setAutoSyncStatus('ok', info);

            // 新規取込があった場合のみ表示更新 (リロード)
            // ただし編集モーダル開いている時は中断しない
            if (imported > 0 && reloadIfImported) {
                const modalOpen = document.querySelector('.modal.active, .modal.show, [data-modal-open="true"]');
                if (!modalOpen) {
                    setTimeout(() => location.reload(), 500);
                }
            }
        } catch (e) {
            setAutoSyncStatus('error');
        } finally {
            autoSyncRunning = false;
        }
    }

    // A: ページオープン時に1回 (ユーザー操作なし、silent)
    //    新規取込があれば自動リロードして即表示に反映
    autoSyncFromSheet(true).then(() => {
        // B: 以降は5分ごとに自動同期
        setInterval(() => autoSyncFromSheet(true), AUTO_SYNC_INTERVAL_MS);
    });

    // ページ非表示中はポーリングしない (visibilitychange でガード)
    // タブを再びアクティブにしたタイミングで即同期
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            autoSyncFromSheet(true);
        }
    });
})();
</script>

<?php if (!$_inHub) { require_once '../functions/footer.php'; } ?>
