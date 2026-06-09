<?php
/**
 * 申請・報告ハブ
 * 週報 / 値引き申請 (リード管理は現在非公開) を統合
 *
 * 共通ハブシェル (_hub-shell-top.php / _hub-shell-bottom.php) を使用し、
 * 他ハブ (master-hub / internal-hub 等) と同じ UI・ナビゲーションパターンを採用する。
 */
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../functions/soft-delete.php';
if (!defined('IN_HUB_PAGE')) define('IN_HUB_PAGE', 'reports');

$data        = getData();
$canEdit     = canEditCurrentPage();
$canDel      = canDelete();
$isAdminUser = isAdmin();
$currentUser = $_SESSION['user_email'] ?? '';
$userName    = $_SESSION['user_name'] ?? $currentUser;

// タブ別の権限 (user-permissions.php の "reports-hub.php#<tab>" で個別設定可能)
$canViewReport   = hasPermission(getPageViewPermission('reports-hub.php#report'));
$canViewApproval = hasPermission(getPageViewPermission('reports-hub.php#approval'));
$canViewLead     = hasPermission(getPageViewPermission('reports-hub.php#lead'));
$canEditReport   = $canEdit && hasPermission(getPageEditPermission('reports-hub.php#report'));
$canEditApproval = $canEdit && hasPermission(getPageEditPermission('reports-hub.php#approval'));
$canEditLead     = $canEdit && hasPermission(getPageEditPermission('reports-hub.php#lead'));

// Drive 保存先フォルダ (admin のみ参照)
$discountDriveFolder = null;
$weeklyDriveFolder   = null;
if ($isAdminUser) {
    $dcfg = __DIR__ . '/../config/discount-approvals-drive-config.json';
    if (file_exists($dcfg)) {
        $cfg = json_decode(file_get_contents($dcfg), true);
        if (!empty($cfg['folder_id'])) {
            $discountDriveFolder = ['id' => $cfg['folder_id'], 'name' => $cfg['folder_name'] ?? ''];
        }
    }
    $wcfg = __DIR__ . '/../config/weekly-reports-drive-config.json';
    if (file_exists($wcfg)) {
        $cfg = json_decode(file_get_contents($wcfg), true);
        if (!empty($cfg['folder_id'])) {
            $weeklyDriveFolder = ['id' => $cfg['folder_id'], 'name' => $cfg['folder_name'] ?? ''];
        }
    }
}

// 従業員リスト (担当者選択用)
$employees = filterDeleted($data['employees'] ?? []);
usort($employees, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

// 今週の月曜～金曜 (週報は金曜提出)
$monday = date('Y-m-d', strtotime('monday this week'));
$friday = date('Y-m-d', strtotime('friday this week'));

// タブごとの件数 (バッジ表示用)
$reportCount   = count(filterDeleted($data['weekly_reports'] ?? []));
$approvalCount = count(filterDeleted($data['discount_approvals'] ?? []));

// タブ定義 (リードは現在非公開のため登録しない)
$REPORTS_TABS = [
    'report' => [
        'label' => '週報',
        'file'  => 'reports-hub/tabs/report.php',
        'perm'  => 'reports-hub.php#report',
        'badge' => $reportCount,
        'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
    ],
    'approval' => [
        'label' => '値引き申請',
        'file'  => 'reports-hub/tabs/approval.php',
        'perm'  => 'reports-hub.php#approval',
        'badge' => $approvalCount,
        'icon'  => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    ],
];

$activeTab = $_GET['tab'] ?? 'report';
if (!isset($REPORTS_TABS[$activeTab])) $activeTab = 'report';

// アクセス可能タブの判定 + フォールバック
if (!hasPermission(getPageViewPermission($REPORTS_TABS[$activeTab]['perm']))) {
    $found = false;
    foreach ($REPORTS_TABS as $k => $t) {
        if (hasPermission(getPageViewPermission($t['perm']))) {
            $activeTab = $k;
            $found = true;
            break;
        }
    }
    if (!$found) {
        header('Location: /pages/index.php');
        exit;
    }
}

$HUB_META = [
    'title'    => '申請・報告',
    'subtitle' => '週報・値引き申請の管理',
    'icon'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
];

require_once __DIR__ . '/../functions/header.php';
?>
<script<?= nonceAttr() ?>>
/* 旧 URL (#report / #approval / #lead) の互換: ?tab= が未指定なら hash で振り分ける。
   メール本文や古いブックマークからのアクセスを想定。 */
(function(){
    if (location.search.indexOf('tab=') !== -1) return;
    var h = (location.hash || '').replace('#','');
    if (h === 'report' || h === 'approval' || h === 'lead') {
        location.replace(location.pathname + '?tab=' + h);
    }
})();
</script>
<?php
include __DIR__ . '/_hub-shell-top.php';
include __DIR__ . '/reports-hub/_styles.php';
include __DIR__ . '/' . $REPORTS_TABS[$activeTab]['file'];
include __DIR__ . '/_hub-shell-bottom.php';
