<?php
/**
 * 申請・報告ハブ
 * 週報 / 値引き申請 / 商談記録 / リード管理 を統合
 */
require_once '../api/auth.php';
require_once '../functions/header.php';
require_once '../functions/soft-delete.php';

$data       = getData();
$canEdit    = canEditCurrentPage();
$canDel     = canDelete();
$isAdminUser = isAdmin();
$currentUser = $_SESSION['user_email'] ?? '';
$userName    = $_SESSION['user_name'] ?? $currentUser;

// 値引き申請用 Drive保存先フォルダ設定（adminのみ表示）
$discountDriveFolder = null;
$weeklyDriveFolder   = null;
if ($isAdminUser) {
    $dcfg = __DIR__ . '/../config/discount-approvals-drive-config.json';
    if (file_exists($dcfg)) {
        $cfg = json_decode(file_get_contents($dcfg), true);
        if (!empty($cfg['folder_id'])) {
            $discountDriveFolder = [
                'id'   => $cfg['folder_id'],
                'name' => $cfg['folder_name'] ?? '',
            ];
        }
    }
    $wcfg = __DIR__ . '/../config/weekly-reports-drive-config.json';
    if (file_exists($wcfg)) {
        $cfg = json_decode(file_get_contents($wcfg), true);
        if (!empty($cfg['folder_id'])) {
            $weeklyDriveFolder = [
                'id'   => $cfg['folder_id'],
                'name' => $cfg['folder_name'] ?? '',
            ];
        }
    }
}

// 従業員リスト（担当者選択用）
$employees = filterDeleted($data['employees'] ?? []);
usort($employees, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

// 今週の月曜〜金曜（週報は金曜に提出）
$monday = date('Y-m-d', strtotime('monday this week'));
$friday = date('Y-m-d', strtotime('friday this week'));
?>
<style <?= nonceAttr() ?>>
/* ── タブ ── */
.hub-tabs{display:flex;gap:4px;border-bottom:2px solid var(--gray-200);margin-bottom:1.25rem;overflow-x:auto;}
.hub-tab{padding:0.6rem 1.2rem;font-size:0.85rem;font-weight:600;color:var(--gray-500);border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;white-space:nowrap;transition:all .15s;}
.hub-tab:hover{color:var(--gray-700);}
.hub-tab.active{color:var(--primary);border-bottom-color:var(--primary);}
.hub-tab .badge{display:inline-block;background:var(--gray-200);color:var(--gray-600);font-size:0.7rem;padding:1px 7px;border-radius:9px;margin-left:6px;font-weight:500;}
.hub-tab.active .badge{background:var(--primary-light,#e8f0fe);color:var(--primary);}
.tab-panel{display:none;}
.tab-panel.active{display:block;}

/* ── 共通カード ── */
.hub-card{background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:1rem 1.25rem;margin-bottom:0.75rem;transition:box-shadow .15s;}
.hub-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.06);}
.hub-card-header{display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-bottom:0.5rem;}
.hub-card-title{font-weight:600;font-size:0.95rem;color:var(--gray-800);}
.hub-card-meta{font-size:0.78rem;color:var(--gray-500);}

/* ── ステータスバッジ ── */
.status-badge{display:inline-block;padding:2px 10px;border-radius:9px;font-size:0.73rem;font-weight:600;}
.status-badge.pending{background:#fff3e0;color:#e65100;}
.status-badge.approved{background:#e8f5e9;color:#2e7d32;}
.status-badge.rejected{background:#fce4ec;color:#c62828;}
.status-badge.draft{background:var(--gray-100);color:var(--gray-500);}
.status-badge.submitted{background:#e3f2fd;color:#1565c0;}
.status-badge.confirmed{background:#e8f5e9;color:#2e7d32;}

/* ── リード/商談ステータス色 ── */
.lead-status{display:inline-block;padding:2px 10px;border-radius:9px;font-size:0.73rem;font-weight:600;}
.lead-status[data-s="未接触"]{background:#e3f2fd;color:#1565c0;}
.lead-status[data-s="商談中"]{background:#fff3e0;color:#e65100;}
.lead-status[data-s="受注"]{background:#e8f5e9;color:#2e7d32;}
.lead-status[data-s="失注"]{background:#f5f5f5;color:#757575;}

.deal-stage{display:inline-block;padding:2px 10px;border-radius:9px;font-size:0.73rem;font-weight:600;}
.deal-stage[data-s="リード"]{background:#e3f2fd;color:#1565c0;}
.deal-stage[data-s="初回接触"]{background:#e8eaf6;color:#283593;}
.deal-stage[data-s="提案中"]{background:#fff3e0;color:#e65100;}
.deal-stage[data-s="見積提出"]{background:#fce4ec;color:#ad1457;}
.deal-stage[data-s="交渉中"]{background:#f3e5f5;color:#6a1b9a;}
.deal-stage[data-s="受注"]{background:#e8f5e9;color:#2e7d32;}
.deal-stage[data-s="失注"]{background:#f5f5f5;color:#757575;}

/* ── フィルタバー ── */
.filter-bar{display:flex;gap:6px;margin-bottom:1rem;flex-wrap:wrap;}
.filter-btn{padding:4px 14px;border:1px solid var(--gray-300);border-radius:8px;font-size:0.78rem;background:#fff;cursor:pointer;color:var(--gray-600);transition:all .15s;}
.filter-btn:hover{border-color:var(--primary);}
.filter-btn.active{background:var(--primary);color:#fff;border-color:var(--primary);}

/* ── サマリーカード ── */
.summary-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.75rem;margin-bottom:1.25rem;}
.summary-card{background:#fff;border:1px solid var(--gray-200);border-radius:10px;padding:0.75rem 1rem;text-align:center;}
.summary-card .num{font-size:1.4rem;font-weight:700;color:var(--gray-800);}
.summary-card .label{font-size:0.73rem;color:var(--gray-500);margin-top:2px;}

/* ── 金額表示 ── */
.amount-display{display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;}
.amount-display .original{color:var(--gray-500);}
.amount-display .arrow{color:var(--gray-400);}
.amount-display .discount{color:#c62828;font-weight:600;}
.amount-display .after{color:#2e7d32;font-weight:600;}

/* ── 週報エディタ ── */
.section-block{margin-bottom:1rem;}
.section-label{font-size:0.82rem;font-weight:600;color:var(--gray-700);margin-bottom:4px;}
.section-editor{min-height:60px;border:1px solid var(--gray-200);border-radius:8px;padding:0.6rem 0.75rem;font-size:0.85rem;line-height:1.6;outline:none;}
.section-editor:focus{border-color:var(--primary);box-shadow:0 0 0 2px rgba(37,99,235,.1);}
.section-editor:empty::before{content:attr(data-ph);color:var(--gray-400);}
.section-editor[contenteditable="false"]{opacity:.6;background:var(--gray-50);}
.section-editor img{max-width:100%;height:auto;border-radius:6px;margin:4px 0;cursor:default;}
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;}
.btn-attach-img{background:none;border:1px solid var(--gray-300);border-radius:6px;padding:2px 8px;font-size:0.78rem;color:var(--gray-500);cursor:pointer;display:flex;align-items:center;gap:3px;transition:all .15s;}
.btn-attach-img:hover{border-color:var(--primary);color:var(--primary);}

/* ── 秘匿メッセージ ── */
.private-msg-card{background:#fffde7;border:1px solid #fff9c4;border-radius:10px;padding:1rem;margin-top:1rem;}
.recipient-pills{display:flex;flex-wrap:wrap;gap:4px;margin:0.5rem 0;}
.recipient-pill{padding:4px 12px;border:1px solid var(--gray-300);border-radius:16px;font-size:0.78rem;cursor:pointer;transition:all .15s;background:#fff;}
.recipient-pill.selected{background:var(--primary);color:#fff;border-color:var(--primary);}

/* ── モーダル ── */
.hub-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:10001;align-items:center;justify-content:center;}
.hub-modal.active{display:flex;}
.hub-modal-content{background:#fff;border-radius:14px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);}
.hub-modal-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;}
.hub-modal-header h3{margin:0;font-size:1.1rem;}
.hub-modal-body{padding:1.25rem 1.5rem;}
.hub-modal-footer{padding:1rem 1.5rem;border-top:1px solid var(--gray-200);display:flex;justify-content:flex-end;gap:0.75rem;}
.hub-modal-close{background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--gray-400);line-height:1;}
.hub-modal-close:hover{color:var(--gray-700);}

/* ── 週報一覧カード ── */
.report-list-card{background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:0.85rem 1.1rem;margin-bottom:0.5rem;display:flex;align-items:center;justify-content:space-between;gap:0.75rem;cursor:pointer;transition:all .15s;}
.report-list-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.06);border-color:var(--primary);background:#fafbff;}
.report-list-left{display:flex;align-items:center;gap:0.85rem;min-width:0;flex:1;}
.report-user-avatar{width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;flex-shrink:0;}
.report-user-info{min-width:0;}
.report-user-name{font-weight:600;font-size:0.9rem;color:var(--gray-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.report-week-date{font-size:0.78rem;color:var(--gray-500);margin-top:1px;}
.report-list-right{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.report-preview-text{font-size:0.78rem;color:var(--gray-500);max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* ── 週報詳細モーダル ── */
.report-detail-section{margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:1px solid var(--gray-100);}
.report-detail-section:last-child{border-bottom:none;margin-bottom:0;}
.report-detail-label{font-weight:600;font-size:0.82rem;color:var(--primary);margin-bottom:4px;display:flex;align-items:center;gap:6px;}
.report-detail-label svg{width:14px;height:14px;stroke:var(--primary);flex-shrink:0;}
.report-detail-body{font-size:0.88rem;color:var(--gray-700);line-height:1.7;word-break:break-word;}
.report-detail-body:empty::after{content:'（記入なし）';color:var(--gray-400);font-style:italic;}
.report-detail-meta{display:flex;align-items:center;gap:1rem;padding:0.75rem 0;margin-bottom:0.75rem;border-bottom:1px solid var(--gray-200);}
.report-detail-meta-item{font-size:0.82rem;color:var(--gray-600);display:flex;align-items:center;gap:4px;}

/* ── レスポンシブ ── */
@media(max-width:768px){
    .hub-tabs{gap:2px;}
    .hub-tab{padding:0.5rem 0.8rem;font-size:0.8rem;}
    .summary-row{grid-template-columns:repeat(2,1fr);}
    .report-preview-text{display:none;}
    .report-list-card{padding:0.7rem 0.85rem;}
}
</style>

<div class="page-container">
<div class="page-header">
    <h2>申請・報告</h2>
    <div id="headerActions"></div>
</div>

<!-- タブ -->
<div class="hub-tabs" id="hubTabs">
    <button class="hub-tab active" data-tab="report">週報<span class="badge" id="badgeReport">0</span></button>
    <button class="hub-tab" data-tab="approval">値引き申請<span class="badge" id="badgeApproval">0</span></button>
    <button class="hub-tab" data-tab="deal">商談記録<span class="badge" id="badgeDeal">0</span></button>
    <!-- リード管理タブ（いったん非公開） -->
    <!-- <button class="hub-tab" data-tab="lead">リード管理<span class="badge" id="badgeLead">0</span></button> -->
</div>

<!-- ============================================================ -->
<!--  TAB 1: 週報                                                  -->
<!-- ============================================================ -->
<div class="tab-panel active" id="panelReport">
    <div class="settings-detail-header" >
        <div>
            <span style="font-size:0.85rem;color:var(--gray-500);">今週: <?= htmlspecialchars($monday) ?> 〜 <?= htmlspecialchars($friday) ?>（金曜提出）</span>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;">
            <?php if ($isAdminUser): ?>
            <a href="settings.php?tab=google_drive_folders" class="btn btn-secondary btn-sm" title="添付ファイル保存先フォルダ設定">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                保存先
            </a>
            <?php endif; ?>
            <?php if ($canEdit): ?>
            <button class="btn btn-primary btn-sm" id="btnNewReport">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                週報作成
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($isAdminUser && !$weeklyDriveFolder): ?>
    <div style="background:#fff3e0;border:1px solid #ffcc80;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.85rem;color:#e65100;">
        【注意】添付ファイルの保存先Driveフォルダが未設定です。<a href="settings.php?tab=google_drive_folders" style="color:#e65100;font-weight:600;">設定ページ</a>から設定してください（未設定の場合はマイドライブ直下に保存されます）。
    </div>
    <?php endif; ?>
    <div id="reportList"></div>
</div>

<!-- 週報モーダル -->
<div class="hub-modal" id="reportModal">
<div class="hub-modal-content" style="max-width:720px;">
    <div class="hub-modal-header">
        <h3 id="reportModalTitle">週報作成</h3>
        <button class="hub-modal-close" data-close-hub-modal>&times;</button>
    </div>
    <div class="hub-modal-body">
        <div class="form-group">
            <label class="form-label">提出日（金曜日）</label>
            <input type="date" class="form-input" id="reportWeekStart" value="<?= $friday ?>">
        </div>

        <div class="section-block">
            <div class="section-header">
                <div class="section-label">今期の役割</div>
                <button type="button" class="btn-attach-img" data-target="secRole"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> 添付</button>
            </div>
            <div class="section-editor" contenteditable="true" id="secRole" data-ph="今期の役割を入力..."></div>
        </div>
        <div class="section-block">
            <div class="section-header">
                <div class="section-label">今週の報告</div>
                <button type="button" class="btn-attach-img" data-target="secReport"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> 添付</button>
            </div>
            <div class="section-editor" contenteditable="true" id="secReport" data-ph="今週の活動・実績..."></div>
        </div>
        <div class="section-block">
            <div class="section-header">
                <div class="section-label">現在抱えている課題</div>
                <button type="button" class="btn-attach-img" data-target="secIssues"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> 添付</button>
            </div>
            <div class="section-editor" contenteditable="true" id="secIssues" data-ph="課題・困りごと..."></div>
        </div>
        <div class="section-block">
            <div class="section-header">
                <div class="section-label">次週目標・計画</div>
                <button type="button" class="btn-attach-img" data-target="secNextGoals"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> 添付</button>
            </div>
            <div class="section-editor" contenteditable="true" id="secNextGoals" data-ph="来週の予定・目標..."></div>
        </div>
        <div class="section-block">
            <div class="section-header">
                <div class="section-label">いま思いつく第二領域活動</div>
                <button type="button" class="btn-attach-img" data-target="secSecondArea"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> 添付</button>
            </div>
            <div class="section-editor" contenteditable="true" id="secSecondArea" data-ph="第二領域の取り組み..."></div>
        </div>
        <div class="section-block">
            <div class="section-header">
                <div class="section-label">報告・連絡・相談事項</div>
                <button type="button" class="btn-attach-img" data-target="secMisc"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg> 添付</button>
            </div>
            <div class="section-editor" contenteditable="true" id="secMisc" data-ph="その他共有事項..."></div>
        </div>

        <!-- 秘匿メッセージ -->
        <div class="private-msg-card">
            <div style="font-weight:600;font-size:0.85rem;margin-bottom:4px;">秘匿メッセージ（対象者のみ閲覧可）</div>
            <div class="recipient-pills" id="recipientPills">
                <?php foreach ($employees as $emp):
                    $eName = htmlspecialchars($emp['name'] ?? '');
                    $eEmail = htmlspecialchars($emp['email'] ?? '');
                    if (empty($eEmail) || $eEmail === $currentUser) continue;
                ?>
                <span class="recipient-pill" data-email="<?= $eEmail ?>"><?= $eName ?></span>
                <?php endforeach; ?>
            </div>
            <textarea class="form-input" id="privateMessage" rows="2" placeholder="秘匿メッセージ（任意）"></textarea>
        </div>
    </div>
    <div class="hub-modal-footer">
        <button class="btn btn-secondary" data-close-hub-modal>キャンセル</button>
        <button class="btn btn-secondary" id="btnSaveDraft">下書き保存</button>
        <button class="btn btn-primary" id="btnSubmitReport">提出</button>
    </div>
</div>
</div>

<!-- 週報詳細モーダル -->
<div class="hub-modal" id="reportDetailModal">
<div class="hub-modal-content" style="max-width:720px;">
    <div class="hub-modal-header">
        <h3 id="reportDetailTitle">週報詳細</h3>
        <button class="hub-modal-close" data-close-hub-modal>&times;</button>
    </div>
    <div class="hub-modal-body" id="reportDetailBody"></div>
    <div class="hub-modal-footer" id="reportDetailFooter"></div>
</div>
</div>

<!-- ============================================================ -->
<!--  TAB 2: 値引き申請                                            -->
<!-- ============================================================ -->
<div class="tab-panel" id="panelApproval">
    <div class="settings-detail-header" >
        <div class="filter-bar" id="approvalFilters">
            <button class="filter-btn active" data-filter="all">すべて</button>
            <button class="filter-btn" data-filter="pending">承認待ち</button>
            <button class="filter-btn" data-filter="approved">承認済み</button>
            <button class="filter-btn" data-filter="rejected">却下</button>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;">
            <?php if ($isAdminUser): ?>
            <a href="settings.php?tab=google_drive_folders" class="btn btn-secondary btn-sm" title="PDF保存先フォルダ設定">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                保存先
            </a>
            <?php endif; ?>
            <?php if ($canEdit): ?>
            <button class="btn btn-primary btn-sm" id="btnNewApproval">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            値引き申請
        </button>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($isAdminUser && !$discountDriveFolder): ?>
    <div style="background:#fff3e0;border:1px solid #ffcc80;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.85rem;color:#e65100;">
        【注意】PDFの保存先Driveフォルダが未設定です。<a href="settings.php?tab=google_drive_folders" style="color:#e65100;font-weight:600;">設定ページ</a>から設定してください（未設定の場合はマイドライブ直下に保存されます）。
    </div>
    <?php endif; ?>
    <div id="approvalList"></div>
</div>


<!-- 値引きモーダル -->
<div class="hub-modal" id="approvalModal">
<div class="hub-modal-content" style="max-width:520px;">
    <div class="hub-modal-header">
        <h3>値引き申請</h3>
        <button class="hub-modal-close" data-close-hub-modal>&times;</button>
    </div>
    <div class="hub-modal-body">
        <div class="form-group">
            <label class="form-label">案件名 <span style="color:#c62828;">*</span></label>
            <input type="text" class="form-input" id="apprProjectName" placeholder="案件名">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
                <label class="form-label">レンタル期間 <span style="color:#c62828;">*</span></label>
                <input type="text" class="form-input" id="apprRentalPeriod" placeholder="例: 12ヶ月">
            </div>
            <div class="form-group">
                <label class="form-label">販売額 <span style="color:#c62828;">*</span></label>
                <input type="text" class="form-input" id="apprSalesAmount" placeholder="例: 月額150,000円">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
                <label class="form-label">値引き前金額 <span style="color:#c62828;">*</span></label>
                <input type="number" class="form-input" id="apprOriginalAmount" min="0" placeholder="0">
            </div>
            <div class="form-group">
                <label class="form-label">値引き額 <span style="color:#c62828;">*</span></label>
                <input type="number" class="form-input" id="apprDiscountAmount" min="0" placeholder="0">
            </div>
        </div>
        <div style="font-size:0.85rem;color:var(--gray-500);margin-bottom:0.75rem;">
            値引き後: <strong id="apprAfterAmount">¥0</strong>（<span id="apprRate">0</span>%引き）
        </div>
        <div class="form-group">
            <label class="form-label">理由 <span style="color:#c62828;">*</span></label>
            <textarea class="form-input" id="apprReason" rows="3" placeholder="値引き理由を入力"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">添付PDF（任意 / 最大25MB）</label>
            <input type="file" id="apprPdfFile" accept="application/pdf" class="form-input" style="padding:0.4rem;">
            <div id="apprPdfStatus" style="font-size:0.78rem;color:var(--gray-500);margin-top:0.25rem;"></div>
        </div>
    </div>
    <div class="hub-modal-footer">
        <button class="btn btn-secondary" data-close-hub-modal>キャンセル</button>
        <button class="btn btn-primary" id="btnSubmitApproval">申請</button>
    </div>
</div>
</div>

<!-- 審査モーダル -->
<div class="hub-modal" id="reviewModal">
<div class="hub-modal-content" style="max-width:420px;">
    <div class="hub-modal-header">
        <h3 id="reviewModalTitle">承認</h3>
        <button class="hub-modal-close" data-close-hub-modal>&times;</button>
    </div>
    <div class="hub-modal-body">
        <p id="reviewModalDesc" style="font-size:0.85rem;color:var(--gray-600);"></p>
        <div class="form-group">
            <label class="form-label">コメント（任意）</label>
            <textarea class="form-input" id="reviewComment" rows="2"></textarea>
        </div>
    </div>
    <div class="hub-modal-footer">
        <button class="btn btn-secondary" data-close-hub-modal>キャンセル</button>
        <button class="btn btn-primary" id="btnConfirmReview">確定</button>
    </div>
</div>
</div>

<!-- ============================================================ -->
<!--  TAB 3: 商談記録                                              -->
<!-- ============================================================ -->
<div class="tab-panel" id="panelDeal">
    <div class="settings-detail-header" >
        <div class="filter-bar" id="dealFilters">
            <button class="filter-btn active" data-filter="all">すべて</button>
            <button class="filter-btn" data-filter="リード">リード</button>
            <button class="filter-btn" data-filter="提案中">提案中</button>
            <button class="filter-btn" data-filter="見積提出">見積提出</button>
            <button class="filter-btn" data-filter="交渉中">交渉中</button>
            <button class="filter-btn" data-filter="受注">受注</button>
            <button class="filter-btn" data-filter="失注">失注</button>
        </div>
        <?php if ($canEdit): ?>
        <button class="btn btn-primary btn-sm" id="btnNewDeal">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            商談登録
        </button>
        <?php endif; ?>
    </div>
    <div class="summary-row" id="dealSummary"></div>
    <div id="dealList"></div>
</div>

<!-- 商談モーダル -->
<div class="hub-modal" id="dealModal">
<div class="hub-modal-content" style="max-width:560px;">
    <div class="hub-modal-header">
        <h3 id="dealModalTitle">商談登録</h3>
        <button class="hub-modal-close" data-close-hub-modal>&times;</button>
    </div>
    <div class="hub-modal-body">
        <input type="hidden" id="dealId">
        <div class="form-group">
            <label class="form-label">顧客名 <span style="color:#c62828;">*</span></label>
            <input type="text" class="form-input" id="dealCustomerName" placeholder="顧客名">
        </div>
        <div class="form-group">
            <label class="form-label">商談名 <span style="color:#c62828;">*</span></label>
            <input type="text" class="form-input" id="dealTitle" placeholder="商談名">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
                <label class="form-label">金額</label>
                <input type="number" class="form-input" id="dealAmount" min="0" placeholder="0">
            </div>
            <div class="form-group">
                <label class="form-label">確度（%）</label>
                <input type="number" class="form-input" id="dealProbability" min="0" max="100" placeholder="0">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
                <label class="form-label">ステージ</label>
                <select class="form-input" id="dealStage">
                    <option value="リード">リード</option>
                    <option value="初回接触">初回接触</option>
                    <option value="提案中">提案中</option>
                    <option value="見積提出">見積提出</option>
                    <option value="交渉中">交渉中</option>
                    <option value="受注">受注</option>
                    <option value="失注">失注</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">担当者</label>
                <select class="form-input" id="dealAssignee">
                    <option value="">選択してください</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= htmlspecialchars($emp['name'] ?? '') ?>"><?= htmlspecialchars($emp['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">受注予定日</label>
            <input type="date" class="form-input" id="dealExpectedClose">
        </div>
        <div class="form-group">
            <label class="form-label">メモ</label>
            <textarea class="form-input" id="dealMemo" rows="3" placeholder="商談メモ"></textarea>
        </div>
    </div>
    <div class="hub-modal-footer">
        <button class="btn btn-secondary" data-close-hub-modal>キャンセル</button>
        <button class="btn btn-primary" id="btnSaveDeal">保存</button>
    </div>
</div>
</div>

<!-- ============================================================ -->
<!--  TAB 4: リード管理                                            -->
<!-- ============================================================ -->
<div class="tab-panel" id="panelLead">
    <div class="settings-detail-header" >
        <div class="filter-bar" id="leadFilters">
            <button class="filter-btn active" data-filter="all">すべて</button>
            <button class="filter-btn" data-filter="未接触">未接触</button>
            <button class="filter-btn" data-filter="商談中">商談中</button>
            <button class="filter-btn" data-filter="受注">受注</button>
            <button class="filter-btn" data-filter="失注">失注</button>
        </div>
        <?php if ($canEdit): ?>
        <button class="btn btn-primary btn-sm" id="btnNewLead">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            リード登録
        </button>
        <?php endif; ?>
    </div>
    <div id="leadList"></div>
</div>

<!-- リードモーダル -->
<div class="hub-modal" id="leadModal">
<div class="hub-modal-content" style="max-width:560px;">
    <div class="hub-modal-header">
        <h3 id="leadModalTitle">リード登録</h3>
        <button class="hub-modal-close" data-close-hub-modal>&times;</button>
    </div>
    <div class="hub-modal-body">
        <input type="hidden" id="leadId">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
                <label class="form-label">会社名 <span style="color:#c62828;">*</span></label>
                <input type="text" class="form-input" id="leadCompanyName" placeholder="会社名">
            </div>
            <div class="form-group">
                <label class="form-label">担当者名</label>
                <input type="text" class="form-input" id="leadPersonName" placeholder="担当者名">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
                <label class="form-label">役職</label>
                <input type="text" class="form-input" id="leadTitle" placeholder="役職">
            </div>
            <div class="form-group">
                <label class="form-label">電話番号</label>
                <input type="text" class="form-input" id="leadPhone" placeholder="電話番号">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
                <label class="form-label">メール</label>
                <input type="email" class="form-input" id="leadEmail" placeholder="メールアドレス">
            </div>
            <div class="form-group">
                <label class="form-label">ステータス</label>
                <select class="form-input" id="leadStatus">
                    <option value="未接触">未接触</option>
                    <option value="商談中">商談中</option>
                    <option value="受注">受注</option>
                    <option value="失注">失注</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">営業担当</label>
            <select class="form-input" id="leadSalesAssignee">
                <option value="">選択してください</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= htmlspecialchars($emp['name'] ?? '') ?>" data-email="<?= htmlspecialchars($emp['email'] ?? '') ?>"><?= htmlspecialchars($emp['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">メモ</label>
            <textarea class="form-input" id="leadMemo" rows="3" placeholder="メモ"></textarea>
        </div>
    </div>
    <div class="hub-modal-footer">
        <button class="btn btn-secondary" data-close-hub-modal>キャンセル</button>
        <button class="btn btn-primary" id="btnSaveLead">保存</button>
    </div>
</div>
</div>

<script <?= nonceAttr() ?>>
(function(){
    'use strict';
    const CSRF  = '<?= generateCsrfToken() ?>';
    const API   = '/api/reports-hub-api.php';
    const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
    const CAN_DEL  = <?= $canDel  ? 'true' : 'false' ?>;
    const IS_ADMIN = <?= $isAdminUser ? 'true' : 'false' ?>;
    const ME     = <?= json_encode($currentUser) ?>;

    // ── ユーティリティ ──
    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
    function fmt(n) { return Number(n||0).toLocaleString(); }
    function dateShort(s) { return s ? s.substring(0, 10) : ''; }

    async function apiGet(type, action, extra = '') {
        try {
            const r = await fetch(`${API}?type=${type}&action=${action}${extra}`);
            if (!r.ok) return { items: [], error: 'HTTP ' + r.status };
            const json = await r.json();
            return json.data || json;
        } catch(e) { console.error('apiGet error:', e); return { items: [], error: e.message }; }
    }
    async function apiPost(params) {
        try {
            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            for (const [k, v] of Object.entries(params)) fd.append(k, v);
            const r = await fetch(API, { method: 'POST', body: fd });
            if (!r.ok) { const t = await r.text(); return { error: 'HTTP ' + r.status + ': ' + t.substring(0, 200) }; }
            const json = await r.json();
            if (!json.success) return { error: json.error || '不明なエラー' };
            return json.data || json;
        } catch(e) { console.error('apiPost error:', e); return { error: e.message }; }
    }

    function showAlert(msg, type) {
        const el = document.createElement('div');
        el.className = `alert alert-${type}`;
        el.textContent = msg;
        el.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:10000;padding:0.75rem 1.25rem;border-radius:8px;font-size:0.85rem;box-shadow:0 4px 12px rgba(0,0,0,.15);';
        if (type === 'success') el.style.background = '#e8f5e9', el.style.color = '#2e7d32';
        else el.style.background = '#fce4ec', el.style.color = '#c62828';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }

    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    // モーダル閉じる共通処理: ×ボタン・キャンセルのみ（背景クリックでは閉じない）
    document.addEventListener('click', e => {
        if (e.target.hasAttribute('data-close-hub-modal')) {
            const modal = e.target.closest('.hub-modal');
            if (modal) modal.classList.remove('active');
        }
    });

    // ── タブ切替 ──
    function switchTab(tab) {
        document.querySelectorAll('.hub-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        const btn = document.querySelector('.hub-tab[data-tab="'+tab+'"]');
        if (btn) btn.classList.add('active');
        const panel = document.getElementById('panel' + tab.charAt(0).toUpperCase() + tab.slice(1));
        if (panel) panel.classList.add('active');
        currentTab = tab;
    }

    // URLハッシュでタブ自動切替（例: #approval）
    const hashTab = location.hash.replace('#', '');
    let currentTab = hashTab || 'report';
    if (hashTab) switchTab(hashTab);

    document.getElementById('hubTabs').addEventListener('click', e => {
        const btn = e.target.closest('.hub-tab');
        if (!btn || !btn.dataset.tab) return;
        switchTab(btn.dataset.tab);
    });

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  週報
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    let allReports = [];
    let editingReportWeek = null;

    async function loadReports() {
        const res = await apiGet('report', 'list');
        allReports = res.items || [];
        document.getElementById('badgeReport').textContent = allReports.length;
        renderReports();
    }

    function linkify(html) {
        // HTMLタグ内のURLはそのまま保持し、テキスト部分のURLのみリンク化する
        return html.replace(/(<[^>]+>)|(https?:\/\/[^\s<>"']+)/g, function(match, tag, url) {
            if (tag) return tag; // HTMLタグはそのまま返す
            return '<a href="' + url + '" target="_blank" rel="noopener" style="color:var(--primary);text-decoration:underline;">' + url + '</a>';
        });
    }

    function getInitial(name) {
        if (!name) return '?';
        return name.charAt(0);
    }

    function renderReports() {
        const c = document.getElementById('reportList');
        if (!allReports.length) { c.innerHTML = '<p style="color:var(--gray-400);text-align:center;padding:2rem;">週報はまだありません</p>'; return; }

        c.innerHTML = allReports.map((r, idx) => {
            const isConfirmed = r.confirmed_at;
            let statusClass, statusLabel;
            if (isConfirmed) {
                statusClass = 'confirmed';
                statusLabel = '確認済み';
            } else if (r.status === 'submitted') {
                statusClass = 'submitted';
                statusLabel = '提出済み';
            } else {
                statusClass = 'draft';
                statusLabel = '下書き';
            }

            const userName = r.user_name || r.user_email || '';
            // プレビュー用：今週の報告の先頭テキスト
            const previewText = (r.sec_report || '').replace(/<[^>]*>/g, '').trim();
            const showConfirmBtn = IS_ADMIN && r.status === 'submitted' && !isConfirmed;

            return `<div class="report-list-card" data-action="view-report" data-idx="${idx}">
                <div class="report-list-left">
                    <div class="report-user-avatar">${esc(getInitial(userName))}</div>
                    <div class="report-user-info">
                        <div class="report-user-name">${esc(userName)}</div>
                        <div class="report-week-date">${esc(r.week_start)}${r.submitted_at ? ' / 提出 ' + esc(r.submitted_at.substring(0, 10)) : ''}</div>
                    </div>
                </div>
                <div class="report-list-right">
                    <span class="report-preview-text">${esc(previewText.substring(0, 60))}${previewText.length > 60 ? '...' : ''}</span>
                    <span class="status-badge ${statusClass}">${statusLabel}</span>
                    ${showConfirmBtn ? '<button class="btn btn-sm btn-primary" data-action="confirm-report" data-id="'+esc(r.id)+'" data-name="'+esc(userName)+'" data-week="'+esc(r.week_start)+'" onclick="event.stopPropagation()">確認</button>' : ''}
                    ${r.status === 'draft' && CAN_EDIT ? '<button class="btn btn-sm btn-outline" data-action="edit-report" data-week="'+esc(r.week_start)+'" onclick="event.stopPropagation()">編集</button>' : ''}
                    ${CAN_DEL || r.user_email === ME ? '<button class="btn btn-sm btn-danger" data-action="delete-report" data-id="'+esc(r.id)+'">削除</button>' : ''}
                </div>
            </div>`;
        }).join('');
    }

    function openReportDetail(r) {
        const isConfirmed = r.confirmed_at;
        let statusClass, statusLabel;
        if (isConfirmed) { statusClass = 'confirmed'; statusLabel = '確認済み'; }
        else if (r.status === 'submitted') { statusClass = 'submitted'; statusLabel = '提出済み'; }
        else { statusClass = 'draft'; statusLabel = '下書き'; }

        const userName = r.user_name || r.user_email || '';
        document.getElementById('reportDetailTitle').textContent = userName + ' の週報';

        const sections = [
            { key: 'sec_role', label: '今期の役割', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' },
            { key: 'sec_report', label: '今週の報告', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' },
            { key: 'sec_issues', label: '課題', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' },
            { key: 'sec_next_goals', label: '次週目標', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>' },
            { key: 'sec_second_area', label: '第二領域', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' },
            { key: 'sec_misc', label: '報連相', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>' },
        ];

        let meta = `<div class="report-detail-meta">
            <div class="report-detail-meta-item"><strong>${esc(userName)}</strong></div>
            <div class="report-detail-meta-item">${esc(r.week_start)}</div>
            <div class="report-detail-meta-item"><span class="status-badge ${statusClass}">${statusLabel}</span></div>
            ${isConfirmed ? '<div class="report-detail-meta-item" style="color:#27ae60;">確認: ' + esc(r.confirmed_by_name || '') + ' / ' + esc(r.confirmed_at) + '</div>' : ''}
        </div>`;

        let body = meta;
        sections.forEach(s => {
            const val = r[s.key] || '';
            const html = linkify(val);
            body += `<div class="report-detail-section">
                <div class="report-detail-label">${s.icon} ${s.label}</div>
                <div class="report-detail-body">${html}</div>
            </div>`;
        });

        document.getElementById('reportDetailBody').innerHTML = body;

        // フッターボタン
        const showConfirmBtn = IS_ADMIN && r.status === 'submitted' && !isConfirmed;
        let footer = '';
        if (showConfirmBtn) {
            footer += `<button class="btn btn-primary" data-action="confirm-report" data-id="${esc(r.id)}" data-name="${esc(userName)}" data-week="${esc(r.week_start)}">確認する</button>`;
        }
        if (r.status === 'draft' && CAN_EDIT) {
            footer += `<button class="btn btn-secondary" data-action="edit-report" data-week="${esc(r.week_start)}">編集</button>`;
        }
        footer += `<button class="btn btn-secondary" data-close-hub-modal>閉じる</button>`;
        document.getElementById('reportDetailFooter').innerHTML = footer;

        openModal('reportDetailModal');
    }

    // 週報モーダル
    document.getElementById('btnNewReport')?.addEventListener('click', () => {
        editingReportWeek = null;
        document.getElementById('reportModalTitle').textContent = '週報作成';
        document.getElementById('reportWeekStart').value = '<?= $friday ?>';
        ['secRole','secReport','secIssues','secNextGoals','secSecondArea','secMisc'].forEach(id => document.getElementById(id).innerHTML = '');
        document.getElementById('privateMessage').value = '';
        document.querySelectorAll('.recipient-pill').forEach(p => p.classList.remove('selected'));

        // 既存の下書きがあればロード
        const existing = allReports.find(r => r.week_start === '<?= $friday ?>' && r.status === 'draft');
        if (existing) populateReportModal(existing);

        openModal('reportModal');
    });

    function populateReportModal(r) {
        editingReportWeek = r.week_start;
        document.getElementById('reportWeekStart').value = r.week_start;
        document.getElementById('secRole').innerHTML = r.sec_role || '';
        document.getElementById('secReport').innerHTML = r.sec_report || '';
        document.getElementById('secIssues').innerHTML = r.sec_issues || '';
        document.getElementById('secNextGoals').innerHTML = r.sec_next_goals || '';
        document.getElementById('secSecondArea').innerHTML = r.sec_second_area || '';
        document.getElementById('secMisc').innerHTML = r.sec_misc || '';
        document.getElementById('privateMessage').value = r.private_message || '';
        const recips = r.private_recipients || [];
        document.querySelectorAll('.recipient-pill').forEach(p => {
            p.classList.toggle('selected', recips.includes(p.dataset.email));
        });
    }

    // ── 画像圧縮 ──
    function compressImage(file, maxWidth, quality) {
        return new Promise((resolve) => {
            // GIF はアニメーション保持のため圧縮しない
            if (file.type === 'image/gif') { resolve(file); return; }
            // 小さい画像(200KB以下)はそのまま
            if (file.size <= 200 * 1024) { resolve(file); return; }
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                let w = img.width, h = img.height;
                if (w <= maxWidth && file.size <= 500 * 1024) { resolve(file); return; }
                if (w > maxWidth) { h = Math.round(h * maxWidth / w); w = maxWidth; }
                const canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                canvas.toBlob((blob) => {
                    resolve(new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' }));
                }, 'image/jpeg', quality);
            };
            img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
            img.src = url;
        });
    }

    // ── 画像・PDFアップロード ──
    const weeklyFileInput = document.createElement('input');
    weeklyFileInput.type = 'file';
    weeklyFileInput.accept = 'image/jpeg,image/png,image/gif,image/webp,application/pdf';
    weeklyFileInput.style.display = 'none';
    document.body.appendChild(weeklyFileInput);
    let weeklyFileTarget = null;
    let uploadingCount = 0;

    async function uploadWeeklyFile(file, editorEl) {
        if (!file) return;
        const isImage = file.type.startsWith('image/');
        const isPdf = file.type === 'application/pdf';
        if (!isImage && !isPdf) { showAlert('画像またはPDFを選択してください', 'danger'); return; }
        // 画像は圧縮（最大1600px幅, JPEG 80%品質）
        if (isImage) file = await compressImage(file, 1600, 0.80);
        const maxSize = isPdf ? 25 * 1024 * 1024 : 10 * 1024 * 1024;
        if (file.size > maxSize) { showAlert('ファイルサイズが大きすぎます（画像10MB / PDF25MB）', 'danger'); return; }

        uploadingCount++;

        // 画像: ローカルプレビューを即表示してからバックグラウンドでアップロード
        if (isImage) {
            const img = document.createElement('img');
            img.alt = '添付画像';
            img.style.opacity = '0.5';
            const blobUrl = URL.createObjectURL(file);
            img.src = blobUrl;
            editorEl.appendChild(img);
            editorEl.appendChild(document.createElement('br'));

            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('file', file);
            try {
                const r = await fetch('/api/upload-weekly-image.php', { method: 'POST', body: fd });
                const json = await r.json();
                if (!json.success) throw new Error(json.error || 'アップロード失敗');
                img.src = json.data.url;
                img.style.opacity = '1';
                URL.revokeObjectURL(blobUrl);
            } catch (err) {
                img.remove();
                showAlert('アップロード失敗: ' + err.message, 'danger');
            } finally {
                uploadingCount--;
            }
            return;
        }

        // PDF: リンクプレースホルダーを先に表示
        const link = document.createElement('a');
        link.target = '_blank';
        link.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#fff3e0;border:1px solid #ffe0b2;border-radius:6px;color:#e65100;text-decoration:none;font-size:0.82rem;margin:4px 0;opacity:0.5;';
        link.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> ' + esc(file.name || 'PDF') + ' …';
        editorEl.appendChild(link);
        editorEl.appendChild(document.createElement('br'));

        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('file', file);
        try {
            const r = await fetch('/api/upload-weekly-image.php', { method: 'POST', body: fd });
            const json = await r.json();
            if (!json.success) throw new Error(json.error || 'アップロード失敗');
            link.href = json.data.url;
            link.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> ' + esc(json.data.original_name || 'PDF');
            link.style.opacity = '1';
        } catch (err) {
            link.remove();
            showAlert('アップロード失敗: ' + err.message, 'danger');
        } finally {
            uploadingCount--;
        }
    }

    // 添付ボタンクリック
    document.querySelectorAll('.btn-attach-img').forEach(btn => {
        btn.addEventListener('click', () => {
            weeklyFileTarget = document.getElementById(btn.dataset.target);
            weeklyFileInput.value = '';
            weeklyFileInput.click();
        });
    });
    weeklyFileInput.addEventListener('change', () => {
        if (weeklyFileInput.files[0] && weeklyFileTarget) {
            uploadWeeklyFile(weeklyFileInput.files[0], weeklyFileTarget);
        }
    });

    // ペースト画像対応
    document.querySelectorAll('.section-editor').forEach(editor => {
        editor.addEventListener('paste', e => {
            const items = e.clipboardData?.items;
            if (!items) return;
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    e.preventDefault();
                    uploadWeeklyFile(item.getAsFile(), editor);
                    return;
                }
            }
        });
        // ドラッグ＆ドロップ対応
        editor.addEventListener('dragover', e => e.preventDefault());
        editor.addEventListener('drop', e => {
            const files = e.dataTransfer?.files;
            if (!files) return;
            for (const file of files) {
                if (file.type.startsWith('image/') || file.type === 'application/pdf') {
                    e.preventDefault();
                    uploadWeeklyFile(file, editor);
                    return;
                }
            }
        });
    });

    // 秘匿メッセージ宛先
    document.getElementById('recipientPills')?.addEventListener('click', e => {
        const pill = e.target.closest('.recipient-pill');
        if (pill) pill.classList.toggle('selected');
    });

    function collectReportData() {
        const recips = [];
        document.querySelectorAll('.recipient-pill.selected').forEach(p => recips.push(p.dataset.email));
        return {
            week_start: document.getElementById('reportWeekStart').value,
            sec_role: document.getElementById('secRole').innerHTML,
            sec_report: document.getElementById('secReport').innerHTML,
            sec_issues: document.getElementById('secIssues').innerHTML,
            sec_next_goals: document.getElementById('secNextGoals').innerHTML,
            sec_second_area: document.getElementById('secSecondArea').innerHTML,
            sec_misc: document.getElementById('secMisc').innerHTML,
            private_message: document.getElementById('privateMessage').value,
            private_recipients: JSON.stringify(recips),
        };
    }

    document.getElementById('btnSaveDraft')?.addEventListener('click', async () => {
        if (uploadingCount > 0) return showAlert('ファイルアップロード中です。完了までお待ちください。', 'danger');
        const d = collectReportData();
        if (!d.week_start) return showAlert('対象週を選択してください', 'danger');
        const res = await apiPost({ type: 'report', action: 'save', ...d });
        if (res.error) return showAlert(res.error, 'danger');
        showAlert('下書き保存しました', 'success');
        closeModal('reportModal');
        loadReports();
    });

    document.getElementById('btnSubmitReport')?.addEventListener('click', async () => {
        if (uploadingCount > 0) return showAlert('ファイルアップロード中です。完了までお待ちください。', 'danger');
        if (!confirm('週報を提出しますか？')) return;
        const d = collectReportData();
        if (!d.week_start) return showAlert('対象週を選択してください', 'danger');
        const res = await apiPost({ type: 'report', action: 'submit', ...d });
        if (res.error) return showAlert(res.error, 'danger');
        showAlert('週報を提出しました', 'success');
        closeModal('reportModal');
        loadReports();
    });

    // 週報イベント委譲
    async function handleReportAction(btn) {
        if (btn.dataset.action === 'view-report') {
            const idx = parseInt(btn.dataset.idx, 10);
            if (allReports[idx]) openReportDetail(allReports[idx]);
        }
        if (btn.dataset.action === 'edit-report') {
            closeModal('reportDetailModal');
            const r = allReports.find(r => r.week_start === btn.dataset.week);
            if (r) {
                document.getElementById('reportModalTitle').textContent = '週報編集';
                populateReportModal(r);
                openModal('reportModal');
            }
        }
        if (btn.dataset.action === 'confirm-report') {
            if (!confirm(esc(btn.dataset.name) + ' さんの週報（' + esc(btn.dataset.week) + '）を確認済みにしますか？')) return;
            const res = await apiPost({ type: 'report', action: 'confirm', id: btn.dataset.id });
            if (res.error) return showAlert(res.error, 'danger');
            showAlert('確認しました', 'success');
            closeModal('reportDetailModal');
            loadReports();
        }
        if (btn.dataset.action === 'delete-report') {
            if (!confirm('この週報を削除しますか？')) return;
            const res = await apiPost({ type: 'report', action: 'delete', id: btn.dataset.id });
            if (res.error) return showAlert(res.error, 'danger');
            showAlert('削除しました', 'success');
            loadReports();
        }
    }

    document.getElementById('reportList').addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if (btn) handleReportAction(btn);
    });

    document.getElementById('reportDetailFooter').addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if (btn) handleReportAction(btn);
    });

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  値引き申請
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    let allApprovals = [];
    let approvalFilter = 'all';
    let pendingReviewAction = '';
    let pendingReviewId = '';

    async function loadApprovals() {
        const res = await apiGet('approval', 'list');
        allApprovals = res.items || [];
        document.getElementById('badgeApproval').textContent = allApprovals.length;
        renderApprovals();
    }

    function renderApprovals() {
        const filtered = approvalFilter === 'all' ? allApprovals : allApprovals.filter(a => a.status === approvalFilter);
        const c = document.getElementById('approvalList');

        if (!filtered.length) { c.innerHTML = '<p style="color:var(--gray-400);text-align:center;padding:2rem;">申請はありません</p>'; return; }

        c.innerHTML = filtered.map(a => {
            const after = a.original_amount - a.discount_amount;
            const rate = a.original_amount > 0 ? Math.round(a.discount_amount / a.original_amount * 100) : 0;
            return `<div class="hub-card">
                <div class="hub-card-header">
                    <div>
                        <span class="hub-card-title">${esc(a.project_name)}</span>
                        <span class="status-badge ${a.status}" style="margin-left:8px;">${a.status === 'pending' ? '承認待ち' : a.status === 'approved' ? '承認済み' : '却下'}</span>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center;">
                        ${IS_ADMIN ? '<span class="hub-card-meta">' + esc(a.applicant_name) + '</span>' : ''}
                        ${CAN_DEL ? '<button class="btn btn-sm btn-danger" data-action="delete-approval" data-id="'+esc(a.id)+'">削除</button>' : ''}
                    </div>
                </div>
                ${a.rental_period || a.sales_amount ? '<div style="font-size:0.82rem;color:var(--gray-600);margin-top:0.3rem;">' + (a.rental_period ? '<strong>レンタル期間:</strong> '+esc(a.rental_period)+'　' : '') + (a.sales_amount ? '<strong>販売額:</strong> '+esc(a.sales_amount) : '') + '</div>' : ''}
                <div class="amount-display">
                    <span class="original">¥${fmt(a.original_amount)}</span>
                    <span class="arrow">→</span>
                    <span class="discount">-¥${fmt(a.discount_amount)}</span>
                    <span class="arrow">→</span>
                    <span class="after">¥${fmt(after)}</span>
                    <span style="font-size:0.75rem;color:var(--gray-500);">(${rate}%引き)</span>
                </div>
                <div style="font-size:0.82rem;color:var(--gray-600);margin-top:0.4rem;">${esc(a.reason)}</div>
                ${a.drive_view_link ? '<div style="margin-top:0.4rem;"><a href="'+esc(a.drive_view_link)+'" target="_blank" rel="noopener" style="font-size:0.82rem;color:var(--primary);text-decoration:none;">添付PDFを開く</a></div>' : ''}
                ${a.reviewed_at ? '<div style="font-size:0.78rem;color:var(--gray-500);margin-top:0.3rem;">審査: '+esc(a.reviewed_at)+' / '+esc(a.review_comment || '')+'</div>' : ''}
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.5rem;">
                    <span class="hub-card-meta">${esc(a.created_at)}</span>
                    ${IS_ADMIN && a.status === 'pending' ? '<div style="display:flex;gap:6px;"><button class="btn btn-sm btn-primary" data-action="review-approval" data-id="'+esc(a.id)+'" data-act="approve">承認</button><button class="btn btn-sm btn-danger" data-action="review-approval" data-id="'+esc(a.id)+'" data-act="reject">却下</button></div>' : ''}
                </div>
            </div>`;
        }).join('');
    }

    // フィルタ
    document.getElementById('approvalFilters').addEventListener('click', e => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;
        document.querySelectorAll('#approvalFilters .filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        approvalFilter = btn.dataset.filter;
        renderApprovals();
    });

    // 値引き計算
    function updateApprovalCalc() {
        const orig = parseInt(document.getElementById('apprOriginalAmount').value) || 0;
        const disc = parseInt(document.getElementById('apprDiscountAmount').value) || 0;
        const after = Math.max(0, orig - disc);
        const rate = orig > 0 ? Math.round(disc / orig * 100) : 0;
        document.getElementById('apprAfterAmount').textContent = '¥' + fmt(after);
        document.getElementById('apprRate').textContent = rate;
    }
    document.getElementById('apprOriginalAmount')?.addEventListener('input', updateApprovalCalc);
    document.getElementById('apprDiscountAmount')?.addEventListener('input', updateApprovalCalc);

    document.getElementById('btnNewApproval')?.addEventListener('click', () => {
        ['apprProjectName','apprRentalPeriod','apprSalesAmount','apprOriginalAmount','apprDiscountAmount','apprReason'].forEach(id => document.getElementById(id).value = '');
        const pdfInput = document.getElementById('apprPdfFile');
        if (pdfInput) pdfInput.value = '';
        const pdfStatus = document.getElementById('apprPdfStatus');
        if (pdfStatus) pdfStatus.textContent = '';
        updateApprovalCalc();
        openModal('approvalModal');
    });

    // PDFアップロード用ヘルパー
    async function uploadApprovalPdf(file) {
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('pdf', file);
        const r = await fetch('/api/upload-discount-pdf.php', { method: 'POST', body: fd });
        if (!r.ok) {
            const t = await r.text();
            throw new Error('HTTP ' + r.status + ': ' + t.substring(0, 200));
        }
        const json = await r.json();
        if (!json.success) throw new Error(json.error || 'アップロード失敗');
        return json.data || json;
    }

    document.getElementById('btnSubmitApproval')?.addEventListener('click', async () => {
        const submitBtn = document.getElementById('btnSubmitApproval');
        const pdfInput  = document.getElementById('apprPdfFile');
        const pdfStatus = document.getElementById('apprPdfStatus');

        // PDFがある場合は先にアップロード
        let driveInfo = {};
        if (pdfInput && pdfInput.files && pdfInput.files[0]) {
            const file = pdfInput.files[0];
            if (file.size > 25 * 1024 * 1024) {
                return showAlert('PDFは25MB以内にしてください', 'danger');
            }
            if (file.type !== 'application/pdf') {
                return showAlert('PDFファイルを選択してください', 'danger');
            }
            try {
                submitBtn.disabled = true;
                if (pdfStatus) pdfStatus.textContent = 'Driveにアップロード中...';
                const up = await uploadApprovalPdf(file);
                driveInfo = {
                    drive_file_id: up.drive_file_id || '',
                    drive_view_link: up.drive_view_link || '',
                    drive_download_link: up.drive_download_link || '',
                    drive_file_name: up.drive_file_name || '',
                    original_name: up.original_name || '',
                };
                if (pdfStatus) pdfStatus.textContent = 'アップロード完了';
            } catch (err) {
                submitBtn.disabled = false;
                if (pdfStatus) pdfStatus.textContent = '';
                return showAlert('PDFアップロード失敗: ' + err.message, 'danger');
            }
        }

        const res = await apiPost({
            type: 'approval', action: 'create',
            project_name: document.getElementById('apprProjectName').value,
            rental_period: document.getElementById('apprRentalPeriod').value,
            sales_amount: document.getElementById('apprSalesAmount').value,
            original_amount: document.getElementById('apprOriginalAmount').value,
            discount_amount: document.getElementById('apprDiscountAmount').value,
            reason: document.getElementById('apprReason').value,
            ...driveInfo,
        });
        submitBtn.disabled = false;
        if (res.error) return showAlert(res.error, 'danger');
        showAlert('値引き申請を送信しました', 'success');
        closeModal('approvalModal');
        loadApprovals();
    });


    // 審査
    document.getElementById('approvalList').addEventListener('click', async e => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        if (btn.dataset.action === 'review-approval') {
            pendingReviewAction = btn.dataset.act;
            pendingReviewId = btn.dataset.id;
            const label = pendingReviewAction === 'approve' ? '承認' : '却下';
            document.getElementById('reviewModalTitle').textContent = label;
            document.getElementById('reviewModalDesc').textContent = `この値引き申請を${label}しますか？`;
            document.getElementById('reviewComment').value = '';
            openModal('reviewModal');
        }
        if (btn.dataset.action === 'delete-approval') {
            if (!confirm('この申請を削除しますか？')) return;
            const res = await apiPost({ type: 'approval', action: 'delete', id: btn.dataset.id });
            if (res.error) return showAlert(res.error, 'danger');
            showAlert('削除しました', 'success');
            loadApprovals();
        }
    });

    document.getElementById('btnConfirmReview')?.addEventListener('click', async () => {
        const res = await apiPost({
            type: 'approval', action: pendingReviewAction,
            id: pendingReviewId,
            comment: document.getElementById('reviewComment').value,
        });
        if (res.error) return showAlert(res.error, 'danger');
        const label = pendingReviewAction === 'approve' ? '承認' : '却下';
        showAlert(`${label}しました`, 'success');
        closeModal('reviewModal');
        loadApprovals();
    });

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  商談記録
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    let allDeals = [];
    let dealFilter = 'all';

    async function loadDeals() {
        const res = await apiGet('deal', 'list');
        allDeals = res.items || [];
        document.getElementById('badgeDeal').textContent = allDeals.length;
        renderDealSummary();
        renderDeals();
    }

    function renderDealSummary() {
        const active = allDeals.filter(d => !['受注','失注'].includes(d.stage));
        const totalAmt = active.reduce((s, d) => s + (d.amount || 0), 0);
        const weighted = active.reduce((s, d) => s + (d.amount || 0) * (d.probability || 0) / 100, 0);
        document.getElementById('dealSummary').innerHTML = `
            <div class="summary-card"><div class="num">${allDeals.length}</div><div class="label">全商談</div></div>
            <div class="summary-card"><div class="num">${active.length}</div><div class="label">進行中</div></div>
            <div class="summary-card"><div class="num">¥${fmt(totalAmt)}</div><div class="label">合計金額</div></div>
            <div class="summary-card"><div class="num">¥${fmt(Math.round(weighted))}</div><div class="label">加重金額</div></div>
        `;
    }

    function renderDeals() {
        const filtered = dealFilter === 'all' ? allDeals : allDeals.filter(d => d.stage === dealFilter);
        const c = document.getElementById('dealList');
        if (!filtered.length) { c.innerHTML = '<p style="color:var(--gray-400);text-align:center;padding:2rem;">商談はありません</p>'; return; }

        c.innerHTML = filtered.map(d => `<div class="hub-card">
            <div class="hub-card-header">
                <div>
                    <span class="hub-card-title">${esc(d.customer_name)}</span>
                    <span class="deal-stage" data-s="${esc(d.stage)}" style="margin-left:8px;">${esc(d.stage)}</span>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    ${CAN_EDIT ? '<button class="btn btn-sm btn-outline" data-action="edit-deal" data-id="'+esc(d.id)+'">編集</button>' : ''}
                    ${CAN_DEL ? '<button class="btn btn-sm btn-danger" data-action="delete-deal" data-id="'+esc(d.id)+'">削除</button>' : ''}
                </div>
            </div>
            <div style="font-size:0.85rem;color:var(--gray-700);font-weight:500;">${esc(d.title)}</div>
            <div style="display:flex;gap:1.5rem;margin-top:0.4rem;font-size:0.82rem;color:var(--gray-600);">
                <span>¥${fmt(d.amount)}</span>
                <span>確度: ${d.probability || 0}%</span>
                <span>担当: ${esc(d.assignee || '-')}</span>
                ${d.expected_close_date ? '<span>予定: '+esc(d.expected_close_date)+'</span>' : ''}
            </div>
            ${d.memo ? '<div style="font-size:0.82rem;color:var(--gray-500);margin-top:0.3rem;">'+esc(d.memo)+'</div>' : ''}
            <div class="hub-card-meta" style="margin-top:0.4rem;">${esc(dateShort(d.created_at))}</div>
        </div>`).join('');
    }

    // フィルタ
    document.getElementById('dealFilters').addEventListener('click', e => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;
        document.querySelectorAll('#dealFilters .filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        dealFilter = btn.dataset.filter;
        renderDeals();
    });

    document.getElementById('btnNewDeal')?.addEventListener('click', () => {
        document.getElementById('dealModalTitle').textContent = '商談登録';
        document.getElementById('dealId').value = '';
        ['dealCustomerName','dealTitle','dealAmount','dealProbability','dealExpectedClose','dealMemo'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('dealStage').value = 'リード';
        document.getElementById('dealAssignee').value = '';
        openModal('dealModal');
    });

    document.getElementById('dealList').addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        if (btn.dataset.action === 'edit-deal') {
            const d = allDeals.find(d => d.id === btn.dataset.id);
            if (!d) return;
            document.getElementById('dealModalTitle').textContent = '商談編集';
            document.getElementById('dealId').value = d.id;
            document.getElementById('dealCustomerName').value = d.customer_name || '';
            document.getElementById('dealTitle').value = d.title || '';
            document.getElementById('dealAmount').value = d.amount || '';
            document.getElementById('dealProbability').value = d.probability || '';
            document.getElementById('dealStage').value = d.stage || 'リード';
            document.getElementById('dealAssignee').value = d.assignee || '';
            document.getElementById('dealExpectedClose').value = d.expected_close_date || '';
            document.getElementById('dealMemo').value = d.memo || '';
            openModal('dealModal');
        }
        if (btn.dataset.action === 'delete-deal') {
            if (!confirm('この商談を削除しますか？')) return;
            apiPost({ type: 'deal', action: 'delete', id: btn.dataset.id }).then(res => {
                if (res.error) return showAlert(res.error, 'danger');
                showAlert('削除しました', 'success');
                loadDeals();
            });
        }
    });

    document.getElementById('btnSaveDeal')?.addEventListener('click', async () => {
        const id = document.getElementById('dealId').value;
        const params = {
            type: 'deal', action: id ? 'update' : 'create',
            customer_name: document.getElementById('dealCustomerName').value,
            title: document.getElementById('dealTitle').value,
            amount: document.getElementById('dealAmount').value || '0',
            probability: document.getElementById('dealProbability').value || '0',
            stage: document.getElementById('dealStage').value,
            assignee: document.getElementById('dealAssignee').value,
            expected_close_date: document.getElementById('dealExpectedClose').value,
            memo: document.getElementById('dealMemo').value,
        };
        if (id) params.id = id;
        const res = await apiPost(params);
        if (res.error) return showAlert(res.error, 'danger');
        showAlert(id ? '更新しました' : '商談を登録しました', 'success');
        closeModal('dealModal');
        loadDeals();
    });

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  リード管理
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    let allLeads = [];
    let leadFilter = 'all';

    async function loadLeads() {
        const res = await apiGet('lead', 'list');
        allLeads = res.items || [];
        document.getElementById('badgeLead').textContent = allLeads.length;
        renderLeads();
    }

    function renderLeads() {
        const filtered = leadFilter === 'all' ? allLeads : allLeads.filter(l => (l.status || '未接触') === leadFilter);
        const c = document.getElementById('leadList');
        if (!filtered.length) { c.innerHTML = '<p style="color:var(--gray-400);text-align:center;padding:2rem;">リードはありません</p>'; return; }

        c.innerHTML = filtered.map(l => `<div class="hub-card">
            <div class="hub-card-header">
                <div>
                    <span class="hub-card-title">${esc(l.company_name)}</span>
                    <span class="lead-status" data-s="${esc(l.status || '未接触')}" style="margin-left:8px;">${esc(l.status || '未接触')}</span>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    ${CAN_EDIT ? '<button class="btn btn-sm btn-outline" data-action="edit-lead" data-id="'+esc(l.id)+'">編集</button>' : ''}
                    ${CAN_DEL ? '<button class="btn btn-sm btn-danger" data-action="delete-lead" data-id="'+esc(l.id)+'">削除</button>' : ''}
                </div>
            </div>
            <div style="display:flex;gap:1.5rem;font-size:0.82rem;color:var(--gray-600);">
                ${l.person_name ? '<span>'+esc(l.person_name)+'</span>' : ''}
                ${l.title ? '<span>'+esc(l.title)+'</span>' : ''}
                ${l.phone ? '<span>'+esc(l.phone)+'</span>' : ''}
                ${l.email ? '<span>'+esc(l.email)+'</span>' : ''}
            </div>
            <div style="display:flex;gap:1.5rem;font-size:0.82rem;color:var(--gray-500);margin-top:0.3rem;">
                ${l.sales_assignee ? '<span>営業: '+esc(l.sales_assignee)+'</span>' : ''}
                ${l.memo ? '<span>'+esc(l.memo.substring(0,80))+'</span>' : ''}
            </div>
            <div class="hub-card-meta" style="margin-top:0.4rem;">${esc(dateShort(l.created_at))}</div>
        </div>`).join('');
    }

    // フィルタ
    document.getElementById('leadFilters').addEventListener('click', e => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;
        document.querySelectorAll('#leadFilters .filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        leadFilter = btn.dataset.filter;
        renderLeads();
    });

    document.getElementById('btnNewLead')?.addEventListener('click', () => {
        document.getElementById('leadModalTitle').textContent = 'リード登録';
        document.getElementById('leadId').value = '';
        ['leadCompanyName','leadPersonName','leadTitle','leadPhone','leadEmail','leadMemo'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('leadStatus').value = '未接触';
        document.getElementById('leadSalesAssignee').value = '';
        openModal('leadModal');
    });

    document.getElementById('leadList').addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        if (btn.dataset.action === 'edit-lead') {
            const l = allLeads.find(l => l.id === btn.dataset.id);
            if (!l) return;
            document.getElementById('leadModalTitle').textContent = 'リード編集';
            document.getElementById('leadId').value = l.id;
            document.getElementById('leadCompanyName').value = l.company_name || '';
            document.getElementById('leadPersonName').value = l.person_name || '';
            document.getElementById('leadTitle').value = l.title || '';
            document.getElementById('leadPhone').value = l.phone || '';
            document.getElementById('leadEmail').value = l.email || '';
            document.getElementById('leadStatus').value = l.status || '未接触';
            document.getElementById('leadSalesAssignee').value = l.sales_assignee || '';
            document.getElementById('leadMemo').value = l.memo || '';
            openModal('leadModal');
        }
        if (btn.dataset.action === 'delete-lead') {
            if (!confirm('このリードを削除しますか？')) return;
            apiPost({ type: 'lead', action: 'delete', id: btn.dataset.id }).then(res => {
                if (res.error) return showAlert(res.error, 'danger');
                showAlert('削除しました', 'success');
                loadLeads();
            });
        }
    });

    document.getElementById('btnSaveLead')?.addEventListener('click', async () => {
        const id = document.getElementById('leadId').value;
        const sel = document.getElementById('leadSalesAssignee');
        const salesEmail = sel.selectedOptions[0]?.dataset?.email || '';
        const params = {
            type: 'lead', action: id ? 'update' : 'create',
            company_name: document.getElementById('leadCompanyName').value,
            person_name: document.getElementById('leadPersonName').value,
            title: document.getElementById('leadTitle').value,
            phone: document.getElementById('leadPhone').value,
            email: document.getElementById('leadEmail').value,
            status: document.getElementById('leadStatus').value,
            sales_assignee: sel.value,
            sales_email: salesEmail,
            memo: document.getElementById('leadMemo').value,
        };
        if (id) params.id = id;
        const res = await apiPost(params);
        if (res.error) return showAlert(res.error, 'danger');
        showAlert(id ? '更新しました' : 'リードを登録しました', 'success');
        closeModal('leadModal');
        loadLeads();
    });

    // ── 初期ロード ──
    loadReports().catch(e => console.error('loadReports failed:', e));
    loadApprovals().catch(e => console.error('loadApprovals failed:', e));
    loadDeals().catch(e => console.error('loadDeals failed:', e));
    // loadLeads().catch(e => console.error('loadLeads failed:', e)); // いったん非公開

})();
</script>
</div><!-- /.page-container -->
<?php require_once '../functions/footer.php'; ?>
