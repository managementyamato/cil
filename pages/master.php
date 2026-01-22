<?php
require_once '../config/config.php';
$data = getData();

$message = '';
$messageType = '';

// 表示モード（table/card）
$viewMode = isset($_GET['view']) ? trim($_GET['view']) : 'table';
// ソート
$sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'id';
$sortOrder = isset($_GET['order']) ? trim($_GET['order']) : 'asc';

// トラブル対応から来た場合のP番号を取得
$suggestedPjNumber = isset($_GET['new_from_trouble']) ? trim($_GET['new_from_trouble']) : '';

// PJ更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pj'])) {
    $updateId = $_POST['update_pj'];
    foreach ($data['projects'] as &$pj) {
        if ($pj['id'] === $updateId) {
            // 基本情報
            $pj['occurrence_date'] = trim($_POST['occurrence_date'] ?? '');
            $pj['transaction_type'] = trim($_POST['transaction_type'] ?? '');
            // 担当・取引先
            $pj['sales_assignee'] = trim($_POST['sales_assignee'] ?? '');
            $pj['customer_name'] = trim($_POST['customer_name'] ?? '');
            $pj['dealer_name'] = trim($_POST['dealer_name'] ?? '');
            $pj['general_contractor'] = trim($_POST['general_contractor'] ?? '');
            // 現場情報
            $pj['name'] = trim($_POST['site_name'] ?? '');
            $pj['postal_code'] = trim($_POST['postal_code'] ?? '');
            $pj['prefecture'] = trim($_POST['prefecture'] ?? '');
            $pj['address'] = trim($_POST['address'] ?? '');
            $pj['shipping_address'] = trim($_POST['shipping_address'] ?? '');
            // 商品情報
            $pj['product_category'] = trim($_POST['product_category'] ?? '');
            $pj['product_series'] = trim($_POST['product_series'] ?? '');
            $pj['product_name'] = trim($_POST['product_name'] ?? '');
            $pj['product_spec'] = trim($_POST['product_spec'] ?? '');
            // パートナー情報
            $pj['install_partner'] = trim($_POST['install_partner'] ?? '');
            $pj['remove_partner'] = trim($_POST['remove_partner'] ?? '');
            // 関連日付
            $pj['contract_date'] = trim($_POST['contract_date'] ?? '');
            $pj['install_schedule_date'] = trim($_POST['install_schedule_date'] ?? '');
            $pj['install_complete_date'] = trim($_POST['install_complete_date'] ?? '');
            $pj['shipping_date'] = trim($_POST['shipping_date'] ?? '');
            $pj['install_request_date'] = trim($_POST['install_request_date'] ?? '');
            $pj['install_date'] = trim($_POST['install_date'] ?? '');
            $pj['remove_schedule_date'] = trim($_POST['remove_schedule_date'] ?? '');
            $pj['remove_request_date'] = trim($_POST['remove_request_date'] ?? '');
            $pj['remove_date'] = trim($_POST['remove_date'] ?? '');
            $pj['remove_inspection_date'] = trim($_POST['remove_inspection_date'] ?? '');
            $pj['warranty_end_date'] = trim($_POST['warranty_end_date'] ?? '');
            // メモ
            $pj['memo'] = trim($_POST['memo'] ?? '');
            $pj['chat_url'] = trim($_POST['chat_url'] ?? '');
            break;
        }
    }
    unset($pj);
    saveData($data);
    header('Location: master.php?updated=1');
    exit;
}

// PJ追加（詳細情報対応）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pj'])) {
    // 基本情報
    $occurrenceDate = trim($_POST['occurrence_date'] ?? '');
    $transactionType = trim($_POST['transaction_type'] ?? '');
    $customPjNumber = trim($_POST['custom_pj_number'] ?? '');

    // 担当・取引先情報
    $salesAssignee = trim($_POST['sales_assignee'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? '');
    $dealerName = trim($_POST['dealer_name'] ?? '');
    $generalContractor = trim($_POST['general_contractor'] ?? '');

    // 現場情報
    $siteName = trim($_POST['site_name'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $prefecture = trim($_POST['prefecture'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $shippingAddress = trim($_POST['shipping_address'] ?? '');

    // 商品情報
    $productCategory = trim($_POST['product_category'] ?? '');
    $productSeries = trim($_POST['product_series'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $productSpec = trim($_POST['product_spec'] ?? '');

    // パートナー情報
    $installPartner = trim($_POST['install_partner'] ?? '');
    $removePartner = trim($_POST['remove_partner'] ?? '');

    // 関連日付
    $contractDate = trim($_POST['contract_date'] ?? '');
    $installScheduleDate = trim($_POST['install_schedule_date'] ?? '');
    $installCompleteDate = trim($_POST['install_complete_date'] ?? '');
    $shippingDate = trim($_POST['shipping_date'] ?? '');
    $installRequestDate = trim($_POST['install_request_date'] ?? '');
    $installDate = trim($_POST['install_date'] ?? '');
    $removeScheduleDate = trim($_POST['remove_schedule_date'] ?? '');
    $removeRequestDate = trim($_POST['remove_request_date'] ?? '');
    $removeDate = trim($_POST['remove_date'] ?? '');
    $removeInspectionDate = trim($_POST['remove_inspection_date'] ?? '');
    $warrantyEndDate = trim($_POST['warranty_end_date'] ?? '');

    // メモ・連絡先
    $memo = trim($_POST['memo'] ?? '');
    $chatUrl = trim($_POST['chat_url'] ?? '');

    // 必須項目チェック（最低限の情報）
    if ($siteName && $customerName) {
        // PJ番号を設定（カスタム入力があればそれを使用、なければ自動生成）
        if (!empty($customPjNumber)) {
            $pjNumber = $customPjNumber;
        } else {
            $pjNumber = date('Ymd') . '-' . sprintf('%03d', count($data['projects']) + 1);
        }

        $newProject = array(
            'id' => $pjNumber,
            'name' => $siteName,
            // 基本情報
            'occurrence_date' => $occurrenceDate,
            'transaction_type' => $transactionType,
            // 担当・取引先
            'sales_assignee' => $salesAssignee,
            'customer_name' => $customerName,
            'dealer_name' => $dealerName,
            'general_contractor' => $generalContractor,
            // 現場情報
            'postal_code' => $postalCode,
            'prefecture' => $prefecture,
            'address' => $address,
            'shipping_address' => $shippingAddress,
            // 商品情報
            'product_category' => $productCategory,
            'product_series' => $productSeries,
            'product_name' => $productName,
            'product_spec' => $productSpec,
            // パートナー情報
            'install_partner' => $installPartner,
            'remove_partner' => $removePartner,
            // 関連日付
            'contract_date' => $contractDate,
            'install_schedule_date' => $installScheduleDate,
            'install_complete_date' => $installCompleteDate,
            'shipping_date' => $shippingDate,
            'install_request_date' => $installRequestDate,
            'install_date' => $installDate,
            'remove_schedule_date' => $removeScheduleDate,
            'remove_request_date' => $removeRequestDate,
            'remove_date' => $removeDate,
            'remove_inspection_date' => $removeInspectionDate,
            'warranty_end_date' => $warrantyEndDate,
            // メモ・連絡先
            'memo' => $memo,
            'chat_url' => $chatUrl,
            'created_at' => date('Y-m-d H:i:s')
        );

        $data['projects'][] = $newProject;
        saveData($data);
        header('Location: master.php?added=1');
        exit;
    } else {
        $message = '現場名と顧客名は必須です';
        $messageType = 'danger';
    }
}

// PJ削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pj'])) {
    $deleteId = $_POST['delete_pj'];
    $data['projects'] = array_values(array_filter($data['projects'], function($p) use ($deleteId) {
        return $p['id'] !== $deleteId;
    }));
    saveData($data);
    header('Location: master.php?deleted=1');
    exit;
}

// PJ一括削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $deleteIds = $_POST['project_ids'] ?? array();

    if (empty($deleteIds) || !is_array($deleteIds)) {
        header('Location: master.php?error=no_selection');
        exit;
    }

    $originalCount = count($data['projects']);

    $data['projects'] = array_values(array_filter($data['projects'], function($p) use ($deleteIds) {
        return !in_array($p['id'], $deleteIds);
    }));

    $deletedCount = $originalCount - count($data['projects']);

    saveData($data);
    header("Location: master.php?bulk_deleted=$deletedCount");
    exit;
}

// 担当者追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignee'])) {
    $assigneeName = trim($_POST['assignee_name'] ?? '');

    if ($assigneeName) {
        // 重複チェック
        $exists = false;
        foreach ($data['assignees'] as $a) {
            if ($a['name'] === $assigneeName) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            $message = 'この担当者は既に登録されています';
            $messageType = 'danger';
        } else {
            $maxId = 0;
            foreach ($data['assignees'] as $a) {
                if ($a['id'] > $maxId) $maxId = $a['id'];
            }
            $data['assignees'][] = ['id' => $maxId + 1, 'name' => $assigneeName];
            saveData($data);
            header('Location: master.php?added_assignee=1');
            exit;
        }
    }
}

// 担当者削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignee'])) {
    $deleteId = (int)$_POST['delete_assignee'];
    $data['assignees'] = array_values(array_filter($data['assignees'], function($a) use ($deleteId) {
        return $a['id'] !== $deleteId;
    }));
    saveData($data);
    header('Location: master.php?deleted_assignee=1');
    exit;
}

// 検索処理とタグフィルタ
$searchPjNumber = isset($_GET['search_pj']) ? trim($_GET['search_pj']) : '';
$searchSiteName = isset($_GET['search_site']) ? trim($_GET['search_site']) : '';
$filterTag = isset($_GET['tag']) ? trim($_GET['tag']) : '';
$filteredProjects = $data['projects'];

// タグ別の件数を計算
$tagCounts = array('レンタル' => 0, '販売' => 0, 'その他' => 0);
foreach ($data['projects'] as $p) {
    $siteName = $p['name'] ?? '';
    if (strpos($siteName, '【レ】') !== false || strpos($siteName, '【レ終】') !== false) {
        $tagCounts['レンタル']++;
    } elseif (strpos($siteName, '【売】') !== false || strpos($siteName, '【販】') !== false) {
        $tagCounts['販売']++;
    } else {
        $tagCounts['その他']++;
    }
}

if (!empty($searchPjNumber) || !empty($searchSiteName) || !empty($filterTag)) {
    $filteredProjects = array_filter($data['projects'], function($p) use ($searchPjNumber, $searchSiteName, $filterTag) {
        $matchesPj = empty($searchPjNumber) || stripos($p['id'], $searchPjNumber) !== false;
        $matchesSite = empty($searchSiteName) || stripos($p['name'] ?? '', $searchSiteName) !== false;

        // タグフィルタ
        $matchesTag = true;
        if (!empty($filterTag)) {
            $siteName = $p['name'] ?? '';
            if ($filterTag === 'レンタル') {
                $matchesTag = strpos($siteName, '【レ】') !== false || strpos($siteName, '【レ終】') !== false;
            } elseif ($filterTag === '販売') {
                $matchesTag = strpos($siteName, '【売】') !== false || strpos($siteName, '【販】') !== false;
            } elseif ($filterTag === 'その他') {
                $matchesTag = strpos($siteName, '【レ】') === false && strpos($siteName, '【レ終】') === false && strpos($siteName, '【売】') === false && strpos($siteName, '【販】') === false;
            }
        }

        return $matchesPj && $matchesSite && $matchesTag;
    });
}

// ソート処理
usort($filteredProjects, function($a, $b) use ($sortBy, $sortOrder) {
    $valA = '';
    $valB = '';

    switch ($sortBy) {
        case 'id':
            // 案件番号から数値を抽出してソート（例: "1", "2", "10" → 数値比較）
            $valA = $a['id'] ?? '';
            $valB = $b['id'] ?? '';
            // 数値のみ抽出
            preg_match('/(\d+)/', $valA, $matchA);
            preg_match('/(\d+)/', $valB, $matchB);
            $numA = isset($matchA[1]) ? (int)$matchA[1] : 0;
            $numB = isset($matchB[1]) ? (int)$matchB[1] : 0;
            if ($sortOrder === 'asc') {
                return $numA - $numB;
            } else {
                return $numB - $numA;
            }
        case 'name':
            $valA = $a['name'] ?? '';
            $valB = $b['name'] ?? '';
            break;
        case 'customer':
            $valA = $a['customer_name'] ?? '';
            $valB = $b['customer_name'] ?? '';
            break;
        case 'install_date':
            $valA = $a['install_schedule_date'] ?? '';
            $valB = $b['install_schedule_date'] ?? '';
            break;
        default:
            // デフォルトも数値ソート
            $valA = $a['id'] ?? '';
            $valB = $b['id'] ?? '';
            preg_match('/(\d+)/', $valA, $matchA);
            preg_match('/(\d+)/', $valB, $matchB);
            $numA = isset($matchA[1]) ? (int)$matchA[1] : 0;
            $numB = isset($matchB[1]) ? (int)$matchB[1] : 0;
            if ($sortOrder === 'asc') {
                return $numA - $numB;
            } else {
                return $numB - $numA;
            }
    }

    if ($sortOrder === 'asc') {
        return strcmp($valA, $valB);
    } else {
        return strcmp($valB, $valA);
    }
});
$filteredProjects = array_values($filteredProjects);

require_once '../functions/header.php';
?>

<?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success">案件を登録しました</div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">案件を更新しました</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">案件を削除しました</div>
<?php endif; ?>

<?php if (isset($_GET['bulk_deleted'])): ?>
    <div class="alert alert-success"><?= (int)$_GET['bulk_deleted'] ?>件の案件を削除しました</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'no_selection'): ?>
    <div class="alert alert-danger">削除する案件を選択してください</div>
<?php endif; ?>

<?php if (isset($_GET['added_assignee'])): ?>
    <div class="alert alert-success">担当者を追加しました</div>
<?php endif; ?>

<?php if (isset($_GET['deleted_assignee'])): ?>
    <div class="alert alert-success">担当者を削除しました</div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
<?php endif; ?>

<style>
/* 案件マスタ専用スタイル */
.view-toggle {
    display: flex;
    gap: 0.25rem;
    background: var(--gray-100);
    padding: 0.25rem;
    border-radius: 8px;
}
.view-toggle-btn {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 6px;
    font-size: 0.875rem;
    color: var(--gray-700);
    transition: all 0.2s;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.view-toggle-btn.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.view-toggle-btn:hover:not(.active) {
    background: var(--gray-200);
}

.sort-header {
    cursor: pointer;
    user-select: none;
    transition: background 0.2s;
}
.sort-header:hover {
    background: var(--gray-200);
}
.sort-icon {
    opacity: 0.3;
    margin-left: 0.25rem;
}
.sort-header.active .sort-icon {
    opacity: 1;
}

.project-row {
    cursor: pointer;
    transition: all 0.2s;
}
.project-row:hover {
    background: var(--primary-light) !important;
}
.project-row.expanded {
    background: var(--gray-50);
}

.project-detail-row {
    display: none;
}
.project-detail-row.show {
    display: table-row;
}
.project-detail-cell {
    padding: 0 !important;
    background: var(--gray-50);
}
.project-detail-content {
    padding: 1.5rem;
    border-top: 2px solid var(--primary);
    animation: slideDown 0.3s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

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
.detail-row:last-child {
    border-bottom: none;
}
.detail-label {
    color: var(--gray-500);
}
.detail-value {
    color: var(--gray-900);
    font-weight: 500;
    text-align: right;
}
.detail-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
}

/* カード表示 */
.project-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}
.project-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: all 0.2s;
    cursor: pointer;
}
.project-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}
.project-card-header {
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid var(--gray-100);
}
.project-card-id {
    font-weight: 700;
    font-size: 1rem;
    color: var(--gray-900);
}
.project-card-body {
    padding: 1rem;
}
.project-card-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--gray-900);
    margin-bottom: 0.5rem;
}
.project-card-customer {
    color: var(--gray-600);
    font-size: 0.875rem;
    margin-bottom: 0.75rem;
}
.project-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.8125rem;
    color: var(--gray-500);
}
.project-card-meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.project-card-footer {
    padding: 0.75rem 1rem;
    background: var(--gray-50);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.project-card-troubles {
    font-size: 0.8125rem;
    color: var(--gray-600);
}
.project-card-troubles.has-troubles {
    color: var(--danger);
    font-weight: 600;
}

/* カード詳細モーダル */
.card-detail-modal {
    display: none;
    position: fixed;
    z-index: 1001;
    right: 0;
    top: 0;
    width: 500px;
    max-width: 100%;
    height: 100%;
    background: white;
    box-shadow: -4px 0 24px rgba(0,0,0,0.15);
    animation: slideIn 0.3s ease;
}
@keyframes slideIn {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}
.card-detail-modal.show {
    display: block;
}
.card-detail-overlay {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.3);
}
.card-detail-overlay.show {
    display: block;
}
.card-detail-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-detail-body {
    padding: 1.5rem;
    height: calc(100% - 140px);
    overflow-y: auto;
}
.card-detail-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--gray-200);
    display: flex;
    gap: 0.5rem;
}
</style>

<!-- 案件マスタ -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h2 style="margin: 0;">案件マスタ <span style="font-size: 0.875rem; color: var(--gray-500);">（<?= count($filteredProjects) ?>件<?= (!empty($searchPjNumber) || !empty($searchSiteName) || !empty($filterTag) || !empty($filterStatus)) ? ' / ' . count($data['projects']) . '件中' : '' ?>）</span></h2>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <!-- 表示切り替え -->
            <div class="view-toggle">
                <a href="?view=table<?= $filterTag ? '&tag=' . urlencode($filterTag) : '' ?><?= $filterStatus ? '&status=' . urlencode($filterStatus) : '' ?>" class="view-toggle-btn <?= $viewMode === 'table' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
                    テーブル
                </a>
                <a href="?view=card<?= $filterTag ? '&tag=' . urlencode($filterTag) : '' ?><?= $filterStatus ? '&status=' . urlencode($filterStatus) : '' ?>" class="view-toggle-btn <?= $viewMode === 'card' ? 'active' : '' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                    カード
                </a>
            </div>
            <button type="button" class="btn btn-danger" onclick="bulkDelete()" style="font-size: 0.875rem; padding: 0.5rem 1rem; display: none;" id="bulkDeleteBtn">選択した案件を削除</button>
            <button type="button" class="btn btn-primary" onclick="showAddModal()" style="font-size: 0.875rem; padding: 0.5rem 1rem;">新規登録</button>
        </div>
    </div>
    <div class="card-body">
        <!-- タグフィルタ -->
        <div style="margin-bottom: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="master.php?view=<?= $viewMode ?>" class="btn <?= empty($filterTag) ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none;">
                全種別 (<?= count($data['projects']) ?>)
            </a>
            <a href="master.php?view=<?= $viewMode ?>&tag=レンタル" class="btn <?= $filterTag === 'レンタル' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none;">
                【レ】レンタル (<?= $tagCounts['レンタル'] ?>)
            </a>
            <a href="master.php?view=<?= $viewMode ?>&tag=販売" class="btn <?= $filterTag === '販売' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none;">
                【売】販売 (<?= $tagCounts['販売'] ?>)
            </a>
            <a href="master.php?view=<?= $viewMode ?>&tag=その他" class="btn <?= $filterTag === 'その他' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none;">
                その他 (<?= $tagCounts['その他'] ?>)
            </a>
        </div>

        <!-- 検索フォーム -->
        <form method="GET" style="margin-bottom: 1rem;">
            <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
            <input type="hidden" name="tag" value="<?= htmlspecialchars($filterTag) ?>">
            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 150px;">
                    <input type="text"
                           name="search_pj"
                           value="<?= htmlspecialchars($searchPjNumber) ?>"
                           placeholder="P番号で検索..."
                           style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--gray-300); border-radius: 4px; font-size: 0.875rem;">
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <input type="text"
                           name="search_site"
                           value="<?= htmlspecialchars($searchSiteName) ?>"
                           placeholder="現場名で検索..."
                           style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--gray-300); border-radius: 4px; font-size: 0.875rem;">
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.5rem; font-size: 0.875rem; white-space: nowrap;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.25rem;">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    検索
                </button>
                <?php if (!empty($searchPjNumber) || !empty($searchSiteName) || !empty($filterTag)): ?>
                    <a href="master.php?view=<?= $viewMode ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none; white-space: nowrap;">クリア</a>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($viewMode === 'table'): ?>
        <!-- テーブル表示 -->
        <form id="bulkDeleteForm" method="POST">
            <input type="hidden" name="bulk_delete" value="1">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40px; white-space: nowrap;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                            <th class="sort-header <?= $sortBy === 'id' ? 'active' : '' ?>" onclick="sortTable('id')" style="white-space: nowrap;">
                                案件番号<span class="sort-icon"><?= $sortBy === 'id' ? ($sortOrder === 'asc' ? '▲' : '▼') : '▼' ?></span>
                            </th>
                            <th class="sort-header <?= $sortBy === 'name' ? 'active' : '' ?>" onclick="sortTable('name')" style="white-space: nowrap;">
                                現場名<span class="sort-icon"><?= $sortBy === 'name' ? ($sortOrder === 'asc' ? '▲' : '▼') : '▼' ?></span>
                            </th>
                            <th class="sort-header <?= $sortBy === 'customer' ? 'active' : '' ?>" onclick="sortTable('customer')" style="white-space: nowrap;">
                                顧客名<span class="sort-icon"><?= $sortBy === 'customer' ? ($sortOrder === 'asc' ? '▲' : '▼') : '▼' ?></span>
                            </th>
                            <th style="white-space: nowrap;">営業担当</th>
                            <th class="sort-header <?= $sortBy === 'install_date' ? 'active' : '' ?>" onclick="sortTable('install_date')" style="white-space: nowrap;">
                                設置予定日<span class="sort-icon"><?= $sortBy === 'install_date' ? ($sortOrder === 'asc' ? '▲' : '▼') : '▼' ?></span>
                            </th>
                        </tr>
                    </thead>
                <tbody>
                    <?php foreach ($filteredProjects as $idx => $pj): ?>
                        <?php
                        // タグを判定
                        $siteName = $pj['name'] ?? '';
                        $tag = '';
                        $tagColor = '';
                        if (strpos($siteName, '【レ】') !== false || strpos($siteName, '【レ終】') !== false) {
                            $tag = 'レンタル';
                            $tagColor = '#3b82f6'; // 青
                        } elseif (strpos($siteName, '【売】') !== false || strpos($siteName, '【販】') !== false) {
                            $tag = '販売';
                            $tagColor = '#10b981'; // 緑
                        }
                        ?>
                        <tr class="project-row" data-idx="<?= $idx ?>" onclick="toggleDetail(<?= $idx ?>, event)">
                            <td onclick="event.stopPropagation()"><input type="checkbox" class="project-checkbox" name="project_ids[]" value="<?= htmlspecialchars($pj['id']) ?>" onchange="updateBulkDeleteBtn()"></td>
                            <td><strong><?= htmlspecialchars($pj['id']) ?></strong></td>
                            <td>
                                <?php if ($tag): ?>
                                    <span style="display: inline-block; background: <?= $tagColor ?>; color: white; padding: 0.125rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-right: 0.5rem; font-weight: 600;"><?= $tag ?></span>
                                <?php endif; ?>
                                <?= htmlspecialchars($pj['name']) ?>
                            </td>
                            <td><?= htmlspecialchars($pj['customer_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($pj['sales_assignee'] ?? '-') ?></td>
                            <td><?= !empty($pj['install_schedule_date']) ? date('Y/m/d', strtotime($pj['install_schedule_date'])) : '-' ?></td>
                        </tr>
                        <!-- 詳細行 -->
                        <tr class="project-detail-row" id="detail-<?= $idx ?>">
                            <td colspan="6" class="project-detail-cell">
                                <div class="project-detail-content">
                                    <div class="detail-grid">
                                        <!-- 基本情報 -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">基本情報</div>
                                            <div class="detail-row"><span class="detail-label">案件番号</span><span class="detail-value"><?= htmlspecialchars($pj['id']) ?></span></div>
                                            <div class="detail-row"><span class="detail-label">発生日</span><span class="detail-value"><?= !empty($pj['occurrence_date']) ? date('Y/m/d', strtotime($pj['occurrence_date'])) : '-' ?></span></div>
                                            <div class="detail-row"><span class="detail-label">取引形態</span><span class="detail-value"><?= htmlspecialchars($pj['transaction_type'] ?? '-') ?></span></div>
                                        </div>
                                        <!-- 担当・取引先 -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">担当・取引先</div>
                                            <div class="detail-row"><span class="detail-label">営業担当</span><span class="detail-value"><?= htmlspecialchars($pj['sales_assignee'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">顧客名</span><span class="detail-value"><?= htmlspecialchars($pj['customer_name'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">ディーラー</span><span class="detail-value"><?= htmlspecialchars($pj['dealer_name'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">ゼネコン</span><span class="detail-value"><?= htmlspecialchars($pj['general_contractor'] ?? '-') ?></span></div>
                                        </div>
                                        <!-- 現場情報 -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">現場情報</div>
                                            <div class="detail-row"><span class="detail-label">現場名</span><span class="detail-value"><?= htmlspecialchars($pj['name'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">都道府県</span><span class="detail-value"><?= htmlspecialchars($pj['prefecture'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">住所</span><span class="detail-value"><?= htmlspecialchars($pj['address'] ?? '-') ?></span></div>
                                        </div>
                                        <!-- 商品情報 -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">商品情報</div>
                                            <div class="detail-row"><span class="detail-label">カテゴリ</span><span class="detail-value"><?= htmlspecialchars($pj['product_category'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">シリーズ</span><span class="detail-value"><?= htmlspecialchars($pj['product_series'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">商品名</span><span class="detail-value"><?= htmlspecialchars($pj['product_name'] ?? '-') ?></span></div>
                                        </div>
                                        <!-- 日付情報 -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">日付情報</div>
                                            <div class="detail-row"><span class="detail-label">成約日</span><span class="detail-value"><?= !empty($pj['contract_date']) ? date('Y/m/d', strtotime($pj['contract_date'])) : '-' ?></span></div>
                                            <div class="detail-row"><span class="detail-label">設置予定日</span><span class="detail-value"><?= !empty($pj['install_schedule_date']) ? date('Y/m/d', strtotime($pj['install_schedule_date'])) : '-' ?></span></div>
                                            <div class="detail-row"><span class="detail-label">設置日</span><span class="detail-value"><?= !empty($pj['install_date']) ? date('Y/m/d', strtotime($pj['install_date'])) : '-' ?></span></div>
                                            <div class="detail-row"><span class="detail-label">撤去日</span><span class="detail-value"><?= !empty($pj['remove_date']) ? date('Y/m/d', strtotime($pj['remove_date'])) : '-' ?></span></div>
                                        </div>
                                        <!-- パートナー -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">パートナー</div>
                                            <div class="detail-row"><span class="detail-label">設置時</span><span class="detail-value"><?= htmlspecialchars($pj['install_partner'] ?? '-') ?></span></div>
                                            <div class="detail-row"><span class="detail-label">撤去時</span><span class="detail-value"><?= htmlspecialchars($pj['remove_partner'] ?? '-') ?></span></div>
                                        </div>
                                    </div>
                                    <?php if (!empty($pj['memo'])): ?>
                                    <div style="margin-top: 1rem; padding: 1rem; background: var(--warning-light); border-radius: 8px;">
                                        <strong style="color: var(--warning);">メモ:</strong>
                                        <p style="margin: 0.5rem 0 0 0; color: var(--gray-700);"><?= nl2br(htmlspecialchars($pj['memo'])) ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <div class="detail-actions">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="showEditModal('<?= htmlspecialchars($pj['id']) ?>')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            編集
                                        </button>
                                        <?php if (!empty($pj['chat_url'])): ?>
                                        <a href="<?= htmlspecialchars($pj['chat_url']) ?>" target="_blank" class="btn btn-secondary btn-sm">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                            チャット
                                        </a>
                                        <?php endif; ?>
                                        <a href="troubles.php?pj=<?= urlencode($pj['id']) ?>" class="btn btn-secondary btn-sm">
                                            トラブル履歴 (<?= $troubleCount ?>)
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('この案件を削除しますか？');">
                                            <input type="hidden" name="delete_pj" value="<?= htmlspecialchars($pj['id']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">削除</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($filteredProjects)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--gray-500); padding: 3rem;">
                                <?= (!empty($searchPjNumber) || !empty($searchSiteName) || !empty($filterStatus)) ? '検索結果が見つかりませんでした' : 'データがありません' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </form>

        <?php else: ?>
        <!-- カード表示 -->
        <div class="project-cards-grid">
            <?php foreach ($filteredProjects as $idx => $pj): ?>
                <?php
                // タグを判定
                $siteName = $pj['name'] ?? '';
                $tag = '';
                $tagColor = '';
                if (strpos($siteName, '【レ】') !== false || strpos($siteName, '【レ終】') !== false) {
                    $tag = 'レンタル';
                    $tagColor = '#3b82f6';
                } elseif (strpos($siteName, '【売】') !== false || strpos($siteName, '【販】') !== false) {
                    $tag = '販売';
                    $tagColor = '#10b981';
                }
                ?>
                <div class="project-card" onclick="showCardDetail('<?= htmlspecialchars($pj['id']) ?>')">
                    <div class="project-card-header">
                        <div class="project-card-id"><?= htmlspecialchars($pj['id']) ?></div>
                        <?php if ($tag): ?>
                            <span style="display: inline-block; background: <?= $tagColor ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;"><?= $tag ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="project-card-body">
                        <div class="project-card-name">
                            <?= htmlspecialchars($pj['name']) ?>
                        </div>
                        <div class="project-card-customer"><?= htmlspecialchars($pj['customer_name'] ?? '-') ?></div>
                        <div class="project-card-meta">
                            <span class="project-card-meta-item">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <?= htmlspecialchars($pj['sales_assignee'] ?? '-') ?>
                            </span>
                            <span class="project-card-meta-item">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?= !empty($pj['install_schedule_date']) ? date('Y/m/d', strtotime($pj['install_schedule_date'])) : '-' ?>
                            </span>
                        </div>
                    </div>
                    <div class="project-card-footer">
                        <span style="color: var(--gray-500); font-size: 0.8125rem;">
                            取引: <?= htmlspecialchars($pj['transaction_type'] ?? '-') ?>
                        </span>
                        <span style="color: var(--gray-400); font-size: 0.75rem;">クリックで詳細</span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($filteredProjects)): ?>
                <div style="grid-column: 1 / -1; text-align: center; color: var(--gray-500); padding: 3rem;">
                    <?= (!empty($searchPjNumber) || !empty($searchSiteName) || !empty($filterStatus)) ? '検索結果が見つかりませんでした' : 'データがありません' ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- カード詳細サイドパネル -->
<div class="card-detail-overlay" id="cardDetailOverlay" onclick="closeCardDetail()"></div>
<div class="card-detail-modal" id="cardDetailModal">
    <div class="card-detail-header">
        <h3 id="cardDetailTitle">案件詳細</h3>
        <span class="close" onclick="closeCardDetail()">&times;</span>
    </div>
    <div class="card-detail-body" id="cardDetailBody">
        <!-- 動的に内容が入る -->
    </div>
    <div class="card-detail-footer" id="cardDetailFooter">
        <!-- 動的にボタンが入る -->
    </div>
</div>

<!-- 担当者マスタ -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">担当者マスタ</h2>
        <button type="button" class="btn btn-primary" onclick="showAssigneeModal()" style="font-size: 0.875rem; padding: 0.5rem 1rem;">新規登録</button>
    </div>
    <div class="card-body">
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            <?php foreach ($data['assignees'] as $a): ?>
                <span style="display: inline-flex; align-items: center; gap: 0.5rem; background: var(--gray-100); padding: 0.5rem 1rem; border-radius: 9999px;">
                    <?= htmlspecialchars($a['name']) ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('削除しますか？');">
                        <input type="hidden" name="delete_assignee" value="<?= $a['id'] ?>">
                        <button type="submit" style="background: none; border: none; cursor: pointer; color: var(--gray-500); font-size: 1.25rem; line-height: 1;" title="削除">&times;</button>
                    </form>
                </span>
            <?php endforeach; ?>
            <?php if (empty($data['assignees'])): ?>
                <p style="color: var(--gray-500);">担当者が登録されていません</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 案件登録モーダル（詳細版） -->
<div id="addModal" class="modal">
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3>案件登録</h3>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="add_pj" value="1">
            <div class="modal-body">

                <!-- 基本情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">基本情報</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="custom_pj_number">P番号（カスタム）</label>
                            <input type="text" class="form-input" id="custom_pj_number" name="custom_pj_number"
                                   value="<?= htmlspecialchars($suggestedPjNumber) ?>"
                                   placeholder="空欄の場合は自動生成されます">
                            <small style="color: #666;">例: 20250119-001（空欄の場合は日付ベースで自動生成）</small>
                        </div>
                        <div class="form-group">
                            <label for="occurrence_date">案件発生日 *</label>
                            <input type="date" class="form-input" id="occurrence_date" name="occurrence_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="transaction_type">取引形態 *</label>
                        <select class="form-select" id="transaction_type" name="transaction_type" required>
                            <option value="">選択してください</option>
                            <option value="販売">販売</option>
                            <option value="レンタル">レンタル</option>
                            <option value="保守">保守</option>
                        </select>
                    </div>
                </div>

                <!-- 担当・取引先情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">担当・取引先情報</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="sales_assignee">営業担当 *</label>
                            <select class="form-select" id="sales_assignee" name="sales_assignee" required>
                                <option value="">選択してください</option>
                                <?php foreach ($data['assignees'] as $a): ?>
                                    <option value="<?= htmlspecialchars($a['name']) ?>"><?= htmlspecialchars($a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="customer_name">顧客名 *</label>
                            <select class="form-select" id="customer_name" name="customer_name" required>
                                <option value="">選択してください</option>
                                <?php foreach ($data['customers'] as $c): ?>
                                    <option value="<?= htmlspecialchars($c['companyName']) ?>"><?= htmlspecialchars($c['companyName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="dealer_name">ディーラー担当者名</label>
                            <input type="text" class="form-input" id="dealer_name" name="dealer_name">
                        </div>
                        <div class="form-group">
                            <label for="general_contractor">ゼネコン名</label>
                            <input type="text" class="form-input" id="general_contractor" name="general_contractor">
                        </div>
                    </div>
                </div>

                <!-- 現場情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">現場情報</h4>
                    <div class="form-group">
                        <label for="site_name">現場名 *</label>
                        <input type="text" class="form-input" id="site_name" name="site_name" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="postal_code">郵便番号</label>
                            <input type="text" class="form-input" id="postal_code" name="postal_code" placeholder="例: 1000001">
                        </div>
                        <div class="form-group">
                            <label for="prefecture">設置場所（都道府県）</label>
                            <input type="text" class="form-input" id="prefecture" name="prefecture">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">設置場所住所</label>
                        <input type="text" class="form-input" id="address" name="address">
                    </div>
                    <div class="form-group">
                        <label for="shipping_address">発送先住所</label>
                        <input type="text" class="form-input" id="shipping_address" name="shipping_address">
                    </div>
                </div>

                <!-- 商品情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: #2563eb;">商品情報</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="product_category">商品カテゴリ（大分類）</label>
                            <select class="form-select" id="product_category" name="product_category">
                                <option value="">カテゴリを選択</option>
                                <?php foreach ($data['productCategories'] as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="product_series">製品シリーズ（中分類）</label>
                            <select class="form-select" id="product_series" name="product_series">
                                <option value="">シリーズを選択</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="product_name">本体商品名（小分類）</label>
                            <select class="form-select" id="product_name" name="product_name">
                                <option value="">商品を選択</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="product_spec">製品仕様（自由記述）</label>
                            <input type="text" class="form-input" id="product_spec" name="product_spec" placeholder="自由に入力してください">
                        </div>
                    </div>
                </div>

                <!-- パートナー情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">パートナー情報</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="install_partner">設置時パートナー</label>
                            <select class="form-select" id="install_partner" name="install_partner">
                                <option value="">選択してください</option>
                                <?php foreach ($data['partners'] as $p): ?>
                                    <option value="<?= htmlspecialchars($p['companyName']) ?>"><?= htmlspecialchars($p['companyName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="remove_partner">撤去時パートナー</label>
                            <select class="form-select" id="remove_partner" name="remove_partner">
                                <option value="">選択してください</option>
                                <?php foreach ($data['partners'] as $p): ?>
                                    <option value="<?= htmlspecialchars($p['companyName']) ?>"><?= htmlspecialchars($p['companyName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 関連日付 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">関連日付</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="contract_date">成約日</label>
                            <input type="date" class="form-input" id="contract_date" name="contract_date">
                        </div>
                        <div class="form-group">
                            <label for="install_schedule_date">設置予定日</label>
                            <input type="date" class="form-input" id="install_schedule_date" name="install_schedule_date">
                        </div>
                        <div class="form-group">
                            <label for="install_complete_date">設定作業完了日</label>
                            <input type="date" class="form-input" id="install_complete_date" name="install_complete_date">
                        </div>
                        <div class="form-group">
                            <label for="shipping_date">発送日</label>
                            <input type="date" class="form-input" id="shipping_date" name="shipping_date">
                        </div>
                        <div class="form-group">
                            <label for="install_request_date">設置業務依頼日</label>
                            <input type="date" class="form-input" id="install_request_date" name="install_request_date">
                        </div>
                        <div class="form-group">
                            <label for="install_date">設置日</label>
                            <input type="date" class="form-input" id="install_date" name="install_date">
                        </div>
                        <div class="form-group">
                            <label for="remove_schedule_date">撤去予定日</label>
                            <input type="date" class="form-input" id="remove_schedule_date" name="remove_schedule_date">
                        </div>
                        <div class="form-group">
                            <label for="remove_request_date">撤去業務依頼日</label>
                            <input type="date" class="form-input" id="remove_request_date" name="remove_request_date">
                        </div>
                        <div class="form-group">
                            <label for="remove_date">撤去日</label>
                            <input type="date" class="form-input" id="remove_date" name="remove_date">
                        </div>
                        <div class="form-group">
                            <label for="remove_inspection_date">撤去後検品日</label>
                            <input type="date" class="form-input" id="remove_inspection_date" name="remove_inspection_date">
                        </div>
                        <div class="form-group">
                            <label for="warranty_end_date">販売時保証終了日</label>
                            <input type="date" class="form-input" id="warranty_end_date" name="warranty_end_date">
                        </div>
                    </div>
                </div>

                <!-- メモ -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">メモ</h4>
                    <div class="form-group">
                        <textarea class="form-input" id="memo" name="memo" rows="4" placeholder="特記事項など"></textarea>
                    </div>
                </div>

                <!-- 連絡用チャットURL -->
                <div>
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">連絡用チャットURL</h4>
                    <div class="form-group">
                        <input type="url" class="form-input" id="chat_url" name="chat_url" placeholder="https://slack.com/... など連絡用リンクがあれば入力してください">
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">キャンセル</button>
                <button type="submit" class="btn btn-primary">確認画面へ</button>
            </div>
        </form>
    </div>
</div>

<!-- 担当者追加モーダル -->
<div id="assigneeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>担当者登録</h3>
            <span class="close" onclick="closeModal('assigneeModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="add_assignee" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label for="assignee_name">担当者名 *</label>
                    <input type="text" class="form-input" id="assignee_name" name="assignee_name" placeholder="担当者名を入力" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assigneeModal')">キャンセル</button>
                <button type="submit" class="btn btn-primary">登録</button>
            </div>
        </form>
    </div>
</div>

<!-- 案件編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3>案件編集</h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="update_pj" value="">
            <div class="modal-body">

                <!-- 基本情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">基本情報</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>案件発生日</label>
                            <input type="date" class="form-input" name="occurrence_date">
                        </div>
                        <div class="form-group">
                            <label>取引形態</label>
                            <select class="form-select" name="transaction_type">
                                <option value="">選択してください</option>
                                <option value="販売">販売</option>
                                <option value="レンタル">レンタル</option>
                                <option value="保守">保守</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 担当・取引先情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">担当・取引先情報</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>営業担当</label>
                            <select class="form-select" name="sales_assignee">
                                <option value="">選択してください</option>
                                <?php foreach ($data['assignees'] as $a): ?>
                                    <option value="<?= htmlspecialchars($a['name']) ?>"><?= htmlspecialchars($a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>顧客名</label>
                            <select class="form-select" name="customer_name">
                                <option value="">選択してください</option>
                                <?php foreach ($data['customers'] as $c): ?>
                                    <option value="<?= htmlspecialchars($c['companyName']) ?>"><?= htmlspecialchars($c['companyName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>ディーラー担当者名</label>
                            <input type="text" class="form-input" name="dealer_name">
                        </div>
                        <div class="form-group">
                            <label>ゼネコン名</label>
                            <input type="text" class="form-input" name="general_contractor">
                        </div>
                    </div>
                </div>

                <!-- 現場情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">現場情報</h4>
                    <div class="form-group">
                        <label>現場名 *</label>
                        <input type="text" class="form-input" name="site_name" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>郵便番号</label>
                            <input type="text" class="form-input" name="postal_code" placeholder="例: 1000001">
                        </div>
                        <div class="form-group">
                            <label>都道府県</label>
                            <input type="text" class="form-input" name="prefecture">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>住所</label>
                        <input type="text" class="form-input" name="address">
                    </div>
                    <div class="form-group">
                        <label>発送先住所</label>
                        <input type="text" class="form-input" name="shipping_address">
                    </div>
                </div>

                <!-- 商品情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: #2563eb;">商品情報</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>商品カテゴリ</label>
                            <select class="form-select" name="product_category">
                                <option value="">カテゴリを選択</option>
                                <?php foreach ($data['productCategories'] as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>製品シリーズ</label>
                            <select class="form-select" name="product_series">
                                <option value="">シリーズを選択</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>本体商品名</label>
                            <select class="form-select" name="product_name">
                                <option value="">商品を選択</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>製品仕様</label>
                            <input type="text" class="form-input" name="product_spec">
                        </div>
                    </div>
                </div>

                <!-- パートナー情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">パートナー情報</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>設置時パートナー</label>
                            <select class="form-select" name="install_partner">
                                <option value="">選択してください</option>
                                <?php foreach ($data['partners'] as $p): ?>
                                    <option value="<?= htmlspecialchars($p['companyName']) ?>"><?= htmlspecialchars($p['companyName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>撤去時パートナー</label>
                            <select class="form-select" name="remove_partner">
                                <option value="">選択してください</option>
                                <?php foreach ($data['partners'] as $p): ?>
                                    <option value="<?= htmlspecialchars($p['companyName']) ?>"><?= htmlspecialchars($p['companyName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 関連日付 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">関連日付</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>成約日</label>
                            <input type="date" class="form-input" name="contract_date">
                        </div>
                        <div class="form-group">
                            <label>設置予定日</label>
                            <input type="date" class="form-input" name="install_schedule_date">
                        </div>
                        <div class="form-group">
                            <label>設定作業完了日</label>
                            <input type="date" class="form-input" name="install_complete_date">
                        </div>
                        <div class="form-group">
                            <label>発送日</label>
                            <input type="date" class="form-input" name="shipping_date">
                        </div>
                        <div class="form-group">
                            <label>設置業務依頼日</label>
                            <input type="date" class="form-input" name="install_request_date">
                        </div>
                        <div class="form-group">
                            <label>設置日</label>
                            <input type="date" class="form-input" name="install_date">
                        </div>
                        <div class="form-group">
                            <label>撤去予定日</label>
                            <input type="date" class="form-input" name="remove_schedule_date">
                        </div>
                        <div class="form-group">
                            <label>撤去業務依頼日</label>
                            <input type="date" class="form-input" name="remove_request_date">
                        </div>
                        <div class="form-group">
                            <label>撤去日</label>
                            <input type="date" class="form-input" name="remove_date">
                        </div>
                        <div class="form-group">
                            <label>撤去後検品日</label>
                            <input type="date" class="form-input" name="remove_inspection_date">
                        </div>
                        <div class="form-group">
                            <label>販売時保証終了日</label>
                            <input type="date" class="form-input" name="warranty_end_date">
                        </div>
                    </div>
                </div>

                <!-- メモ -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">メモ</h4>
                    <div class="form-group">
                        <textarea class="form-input" name="memo" rows="4" placeholder="特記事項など"></textarea>
                    </div>
                </div>

                <!-- 連絡用チャットURL -->
                <div>
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">連絡用チャットURL</h4>
                    <div class="form-group">
                        <input type="url" class="form-input" name="chat_url" placeholder="https://slack.com/... など">
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">キャンセル</button>
                <button type="submit" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<script>
// プロジェクトデータをJSで保持
const projectsData = <?= json_encode($filteredProjects, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function showAddModal() {
    document.getElementById('addModal').style.display = 'block';
}

function showAssigneeModal() {
    document.getElementById('assigneeModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// モーダル外クリックで閉じる
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// トラブル対応から来た場合は自動でモーダルを開く
<?php if (!empty($suggestedPjNumber)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showAddModal();
});
<?php endif; ?>

// ソート機能
function sortTable(column) {
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort');
    const currentOrder = urlParams.get('order') || 'desc';

    let newOrder = 'desc';
    if (currentSort === column && currentOrder === 'desc') {
        newOrder = 'asc';
    }

    urlParams.set('sort', column);
    urlParams.set('order', newOrder);
    window.location.search = urlParams.toString();
}

// 詳細行の展開/折りたたみ
function toggleDetail(idx, event) {
    if (event.target.type === 'checkbox') return;

    const row = document.querySelector(`.project-row[data-idx="${idx}"]`);
    const detailRow = document.getElementById(`detail-${idx}`);

    // 他の展開中の詳細を閉じる
    document.querySelectorAll('.project-detail-row.show').forEach(el => {
        if (el.id !== `detail-${idx}`) {
            el.classList.remove('show');
        }
    });
    document.querySelectorAll('.project-row.expanded').forEach(el => {
        if (el.dataset.idx !== String(idx)) {
            el.classList.remove('expanded');
        }
    });

    // 現在の行をトグル
    row.classList.toggle('expanded');
    detailRow.classList.toggle('show');
}

// 編集モーダル表示
function showEditModal(pjId) {
    event.stopPropagation();
    const pj = projectsData.find(p => p.id === pjId);
    if (!pj) return;

    // 編集モーダルのフォームに値をセット
    const modal = document.getElementById('editModal');
    modal.querySelector('[name="update_pj"]').value = pj.id;
    modal.querySelector('[name="occurrence_date"]').value = pj.occurrence_date || '';
    modal.querySelector('[name="transaction_type"]').value = pj.transaction_type || '';
    modal.querySelector('[name="sales_assignee"]').value = pj.sales_assignee || '';
    modal.querySelector('[name="customer_name"]').value = pj.customer_name || '';
    modal.querySelector('[name="dealer_name"]').value = pj.dealer_name || '';
    modal.querySelector('[name="general_contractor"]').value = pj.general_contractor || '';
    modal.querySelector('[name="site_name"]').value = pj.name || '';
    modal.querySelector('[name="postal_code"]').value = pj.postal_code || '';
    modal.querySelector('[name="prefecture"]').value = pj.prefecture || '';
    modal.querySelector('[name="address"]').value = pj.address || '';
    modal.querySelector('[name="shipping_address"]').value = pj.shipping_address || '';
    modal.querySelector('[name="product_category"]').value = pj.product_category || '';
    modal.querySelector('[name="product_series"]').value = pj.product_series || '';
    modal.querySelector('[name="product_name"]').value = pj.product_name || '';
    modal.querySelector('[name="product_spec"]').value = pj.product_spec || '';
    modal.querySelector('[name="install_partner"]').value = pj.install_partner || '';
    modal.querySelector('[name="remove_partner"]').value = pj.remove_partner || '';
    modal.querySelector('[name="contract_date"]').value = pj.contract_date || '';
    modal.querySelector('[name="install_schedule_date"]').value = pj.install_schedule_date || '';
    modal.querySelector('[name="install_complete_date"]').value = pj.install_complete_date || '';
    modal.querySelector('[name="shipping_date"]').value = pj.shipping_date || '';
    modal.querySelector('[name="install_request_date"]').value = pj.install_request_date || '';
    modal.querySelector('[name="install_date"]').value = pj.install_date || '';
    modal.querySelector('[name="remove_schedule_date"]').value = pj.remove_schedule_date || '';
    modal.querySelector('[name="remove_request_date"]').value = pj.remove_request_date || '';
    modal.querySelector('[name="remove_date"]').value = pj.remove_date || '';
    modal.querySelector('[name="remove_inspection_date"]').value = pj.remove_inspection_date || '';
    modal.querySelector('[name="warranty_end_date"]').value = pj.warranty_end_date || '';
    modal.querySelector('[name="memo"]').value = pj.memo || '';
    modal.querySelector('[name="chat_url"]').value = pj.chat_url || '';

    modal.style.display = 'block';
}

// カード詳細表示
function showCardDetail(pjId) {
    const pj = projectsData.find(p => p.id === pjId);
    if (!pj) return;

    document.getElementById('cardDetailTitle').textContent = pj.id + ' - ' + (pj.name || '');

    let html = `
        <div class="detail-section" style="margin-bottom: 1rem;">
            <div class="detail-section-title">基本情報</div>
            <div class="detail-row"><span class="detail-label">案件番号</span><span class="detail-value">${pj.id}</span></div>
            <div class="detail-row"><span class="detail-label">発生日</span><span class="detail-value">${pj.occurrence_date ? formatDate(pj.occurrence_date) : '-'}</span></div>
            <div class="detail-row"><span class="detail-label">取引形態</span><span class="detail-value">${pj.transaction_type || '-'}</span></div>
        </div>
        <div class="detail-section" style="margin-bottom: 1rem;">
            <div class="detail-section-title">担当・取引先</div>
            <div class="detail-row"><span class="detail-label">営業担当</span><span class="detail-value">${pj.sales_assignee || '-'}</span></div>
            <div class="detail-row"><span class="detail-label">顧客名</span><span class="detail-value">${pj.customer_name || '-'}</span></div>
            <div class="detail-row"><span class="detail-label">ディーラー</span><span class="detail-value">${pj.dealer_name || '-'}</span></div>
            <div class="detail-row"><span class="detail-label">ゼネコン</span><span class="detail-value">${pj.general_contractor || '-'}</span></div>
        </div>
        <div class="detail-section" style="margin-bottom: 1rem;">
            <div class="detail-section-title">現場情報</div>
            <div class="detail-row"><span class="detail-label">現場名</span><span class="detail-value">${pj.name || '-'}</span></div>
            <div class="detail-row"><span class="detail-label">都道府県</span><span class="detail-value">${pj.prefecture || '-'}</span></div>
            <div class="detail-row"><span class="detail-label">住所</span><span class="detail-value">${pj.address || '-'}</span></div>
        </div>
        <div class="detail-section" style="margin-bottom: 1rem;">
            <div class="detail-section-title">商品情報</div>
            <div class="detail-row"><span class="detail-label">カテゴリ</span><span class="detail-value">${pj.product_category || '-'}</span></div>
            <div class="detail-row"><span class="detail-label">シリーズ</span><span class="detail-value">${pj.product_series || '-'}</span></div>
            <div class="detail-row"><span class="detail-label">商品名</span><span class="detail-value">${pj.product_name || '-'}</span></div>
        </div>
        <div class="detail-section" style="margin-bottom: 1rem;">
            <div class="detail-section-title">日付情報</div>
            <div class="detail-row"><span class="detail-label">成約日</span><span class="detail-value">${pj.contract_date ? formatDate(pj.contract_date) : '-'}</span></div>
            <div class="detail-row"><span class="detail-label">設置予定日</span><span class="detail-value">${pj.install_schedule_date ? formatDate(pj.install_schedule_date) : '-'}</span></div>
            <div class="detail-row"><span class="detail-label">設置日</span><span class="detail-value">${pj.install_date ? formatDate(pj.install_date) : '-'}</span></div>
            <div class="detail-row"><span class="detail-label">撤去日</span><span class="detail-value">${pj.remove_date ? formatDate(pj.remove_date) : '-'}</span></div>
        </div>
        ${pj.memo ? `
        <div class="detail-section" style="background: var(--warning-light);">
            <div class="detail-section-title" style="color: var(--warning);">メモ</div>
            <p style="margin: 0; white-space: pre-wrap;">${escapeHtml(pj.memo)}</p>
        </div>
        ` : ''}
    `;

    document.getElementById('cardDetailBody').innerHTML = html;

    let footerHtml = `
        <button type="button" class="btn btn-primary btn-sm" onclick="showEditModal('${pj.id}'); closeCardDetail();">編集</button>
        ${pj.chat_url ? `<a href="${escapeHtml(pj.chat_url)}" target="_blank" class="btn btn-secondary btn-sm">チャット</a>` : ''}
    `;
    document.getElementById('cardDetailFooter').innerHTML = footerHtml;

    document.getElementById('cardDetailOverlay').classList.add('show');
    document.getElementById('cardDetailModal').classList.add('show');
}

function closeCardDetail() {
    document.getElementById('cardDetailOverlay').classList.remove('show');
    document.getElementById('cardDetailModal').classList.remove('show');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 一括削除関連
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.project-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateBulkDeleteBtn();
}

function updateBulkDeleteBtn() {
    const checkboxes = document.querySelectorAll('.project-checkbox:checked');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

    if (checkboxes.length > 0) {
        bulkDeleteBtn.style.display = 'block';
        bulkDeleteBtn.textContent = `選択した${checkboxes.length}件を削除`;
    } else {
        bulkDeleteBtn.style.display = 'none';
    }

    // 全選択チェックボックスの状態を更新
    const allCheckboxes = document.querySelectorAll('.project-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox && allCheckboxes.length > 0) {
        selectAllCheckbox.checked = checkboxes.length === allCheckboxes.length;
    }
}

function bulkDelete() {
    const checkboxes = document.querySelectorAll('.project-checkbox:checked');

    if (checkboxes.length === 0) {
        alert('削除する案件を選択してください');
        return;
    }

    const count = checkboxes.length;
    if (confirm(`選択した${count}件の案件を削除しますか？\n\nこの操作は取り消せません。`)) {
        document.getElementById('bulkDeleteForm').submit();
    }
}

// ESCキーでモーダル/サイドパネルを閉じる
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCardDetail();
        closeModal('addModal');
        closeModal('editModal');
        closeModal('assigneeModal');
    }
});
</script>

<?php require_once '../functions/footer.php'; ?>
