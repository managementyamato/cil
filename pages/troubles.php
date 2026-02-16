<?php
/**
 * トラブル対応一覧ページ
 */
require_once '../api/auth.php';
require_once '../functions/notification-functions.php';

$data = getData();
$troubles = $data['troubles'] ?? array();

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// 一括削除処理（管理者のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && isAdmin()) {
    $ids = $_POST['trouble_ids'] ?? [];
    $deleted = 0;

    if (!empty($ids)) {
        // 論理削除
        $deleted = 0;
        $deletedIds = [];
        foreach ($ids as $tid) {
            $deletedItem = softDelete($data['troubles'], $tid);
            if ($deletedItem) {
                $deleted++;
                $deletedIds[] = $tid;
            }
        }

        if ($deleted > 0) {
            try {
                saveData($data);
                writeAuditLog('bulk_delete', 'trouble', "トラブル一括削除: {$deleted}件", [
                    'deleted_ids' => $deletedIds
                ]);
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'データの保存に失敗しました';
                header('Location: troubles.php');
                exit;
            }
        }
    }
    header('Location: troubles.php?bulk_deleted=' . $deleted);
    exit;
}

// 一括変更処理（編集権限が必要）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_change']) && canEdit()) {
    $ids = $_POST['trouble_ids'] ?? [];
    $newResponder = $_POST['bulk_responder'] ?? null;
    $newStatus = $_POST['bulk_status'] ?? null;
    $validStatuses = ['未対応', '対応中', '保留', '完了'];
    $changed = 0;

    if (!empty($ids)) {
        foreach ($data['troubles'] as &$trouble) {
            if (in_array($trouble['id'], $ids)) {
                if ($newResponder !== null && $newResponder !== '__no_change__') {
                    $trouble['responder'] = $newResponder;
                }
                if ($newStatus !== null && $newStatus !== '__no_change__' && in_array($newStatus, $validStatuses)) {
                    $oldStatus = $trouble['status'] ?? '';
                    if ($oldStatus !== $newStatus) {
                        $trouble['status'] = $newStatus;
                        notifyStatusChange($trouble, $oldStatus, $newStatus);
                    }
                }
                $trouble['updated_at'] = date('Y-m-d H:i:s');
                $changed++;
            }
        }
        unset($trouble);
        try {
            saveData($data);
            writeAuditLog('bulk_update', 'trouble', "トラブル一括変更: {$changed}件", [
                'ids' => $ids,
                'new_status' => $newStatus !== '__no_change__' ? $newStatus : null,
                'new_responder' => $newResponder !== '__no_change__' ? $newResponder : null
            ]);
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'データの保存に失敗しました';
            header('Location: troubles.php');
            exit;
        }
        $data = getData(); // reload
    }
    header('Location: troubles.php?bulk_updated=' . $changed);
    exit;
}

// 対応者変更処理（編集権限が必要）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_responder']) && canEdit()) {
    $troubleId = (int)$_POST['trouble_id'];
    $newResponder = trim($_POST['new_responder'] ?? '');

    foreach ($data['troubles'] as &$trouble) {
        if ($trouble['id'] === $troubleId) {
            $trouble['responder'] = $newResponder;
            $trouble['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($trouble);
    try {
        saveData($data);
        writeAuditLog('update', 'trouble', "トラブル対応者変更: ID {$troubleId} → {$newResponder}");
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'データの保存に失敗しました';
        header('Location: troubles.php');
        exit;
    }
    header('Location: troubles.php?responder_updated=1#trouble-' . $troubleId);
    exit;
}

// ステータス変更処理（編集権限が必要）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status']) && canEdit()) {
    $troubleId = (int)$_POST['trouble_id'];
    $newStatus = $_POST['new_status'];

    $validStatuses = ['未対応', '対応中', '保留', '完了'];
    if (in_array($newStatus, $validStatuses)) {
        foreach ($data['troubles'] as &$trouble) {
            if ($trouble['id'] === $troubleId) {
                $oldStatus = $trouble['status'] ?? '';
                $trouble['status'] = $newStatus;
                $trouble['updated_at'] = date('Y-m-d H:i:s');

                // ステータス変更通知
                if ($oldStatus !== $newStatus) {
                    notifyStatusChange($trouble, $oldStatus, $newStatus);
                }
                break;
            }
        }
        unset($trouble);
        try {
            saveData($data);
            writeAuditLog('update', 'trouble', "トラブルステータス変更: ID {$troubleId} {$oldStatus}→{$newStatus}");
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'データの保存に失敗しました';
            header('Location: troubles.php');
            exit;
        }
        header('Location: troubles.php?status_updated=1#trouble-' . $troubleId);
        exit;
    }
}

// モーダル編集処理（編集権限が必要）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modal_edit']) && canEdit()) {
    $troubleId = (int)$_POST['edit_id'];
    $validStatuses = ['未対応', '対応中', '保留', '完了'];
    $newStatus = $_POST['edit_status'] ?? '';

    if ($troubleId && in_array($newStatus, $validStatuses)) {
        foreach ($data['troubles'] as &$trouble) {
            if ($trouble['id'] === $troubleId) {
                $oldStatus = $trouble['status'] ?? '';

                $trouble['date'] = $_POST['edit_date'] ?? $trouble['date'];
                $trouble['deadline'] = trim($_POST['edit_deadline'] ?? '');
                $trouble['call_no'] = $_POST['edit_call_no'] ?? '';
                $trouble['pj_number'] = $_POST['edit_pj_number'] ?? '';
                $trouble['trouble_content'] = $_POST['edit_trouble_content'] ?? '';
                $trouble['response_content'] = $_POST['edit_response_content'] ?? '';
                $trouble['prevention_notes'] = trim($_POST['edit_prevention_notes'] ?? '');
                $trouble['reporter'] = $_POST['edit_reporter'] ?? '';
                $trouble['responder'] = $_POST['edit_responder'] ?? '';
                $trouble['status'] = $newStatus;
                $trouble['case_no'] = $_POST['edit_case_no'] ?? '';
                $trouble['company_name'] = $_POST['edit_company_name'] ?? '';
                $trouble['customer_name'] = $_POST['edit_customer_name'] ?? '';
                $trouble['updated_at'] = date('Y-m-d H:i:s');

                // ステータス変更通知
                if ($oldStatus !== $newStatus) {
                    notifyStatusChange($trouble, $oldStatus, $newStatus);
                }
                break;
            }
        }
        unset($trouble);
        try {
            saveData($data);
            writeAuditLog('update', 'trouble', "トラブル編集（モーダル）: ID {$troubleId}");
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'データの保存に失敗しました';
            header('Location: troubles.php');
            exit;
        }
        header('Location: troubles.php?modal_updated=1');
        exit;
    }
}

$troubles = filterDeleted($data['troubles'] ?? array());

// ソート処理
$sortBy = $_GET['sort'] ?? 'date';
$sortDir = $_GET['dir'] ?? 'desc';

usort($troubles, function($a, $b) use ($sortBy, $sortDir) {
    switch ($sortBy) {
        case 'responder':
            $valA = $a['responder'] ?? '';
            $valB = $b['responder'] ?? '';
            $cmp = strcmp($valA, $valB);
            break;
        case 'reporter':
            $valA = $a['reporter'] ?? '';
            $valB = $b['reporter'] ?? '';
            $cmp = strcmp($valA, $valB);
            break;
        case 'status':
            $order = ['未対応' => 0, '対応中' => 1, '保留' => 2, '完了' => 3];
            $valA = $order[$a['status'] ?? ''] ?? 99;
            $valB = $order[$b['status'] ?? ''] ?? 99;
            $cmp = $valA - $valB;
            break;
        case 'pj_number':
            $valA = $a['pj_number'] ?? $a['project_name'] ?? '';
            $valB = $b['pj_number'] ?? $b['project_name'] ?? '';
            $cmp = strcmp($valA, $valB);
            break;
        case 'date':
        default:
            $valA = strtotime($a['date'] ?? '1970-01-01');
            $valB = strtotime($b['date'] ?? '1970-01-01');
            $cmp = $valA - $valB;
            break;
    }
    return $sortDir === 'asc' ? $cmp : -$cmp;
});

// フィルター処理
$filterStatus = $_GET['status'] ?? '';
$filterReporter = $_GET['reporter'] ?? '';
$filterResponder = $_GET['responder'] ?? '';
$filterPjNumber = $_GET['pj_number'] ?? '';
$searchKeyword = $_GET['search'] ?? '';

if (!empty($filterStatus)) {
    $troubles = array_filter($troubles, function($t) use ($filterStatus) {
        return ($t['status'] ?? '') === $filterStatus;
    });
}

if (!empty($filterReporter)) {
    $troubles = array_filter($troubles, function($t) use ($filterReporter) {
        return ($t['reporter'] ?? '') === $filterReporter;
    });
}

if (!empty($filterResponder)) {
    $troubles = array_filter($troubles, function($t) use ($filterResponder) {
        return ($t['responder'] ?? '') === $filterResponder;
    });
}

if (!empty($filterPjNumber)) {
    $troubles = array_filter($troubles, function($t) use ($filterPjNumber) {
        $pjNumber = $t['pj_number'] ?? $t['project_name'] ?? '';
        return stripos($pjNumber, $filterPjNumber) !== false;
    });
}

if (!empty($searchKeyword)) {
    $troubles = array_filter($troubles, function($t) use ($searchKeyword) {
        return stripos($t['trouble_content'] ?? '', $searchKeyword) !== false
            || stripos($t['response_content'] ?? '', $searchKeyword) !== false
            || stripos($t['project_name'] ?? '', $searchKeyword) !== false
            || stripos($t['pj_number'] ?? '', $searchKeyword) !== false
            || stripos($t['company_name'] ?? '', $searchKeyword) !== false;
    });
}

// トラブル担当者マスタから取得（マスタのみ使用）
$troubleRespondersMaster = array_map(fn($r) => $r['name'], $data['troubleResponders'] ?? []);
sort($troubleRespondersMaster);

// ユニークな記入者・PJ番号リスト（既存データから取得）
$reporters = array();
$pjNumbers = array();
foreach ($data['troubles'] ?? array() as $t) {
    if (!empty($t['reporter'])) $reporters[] = $t['reporter'];
    $pj = $t['pj_number'] ?? $t['project_name'] ?? '';
    if (!empty($pj)) $pjNumbers[] = $pj;
}
$reporters = array_unique($reporters);
// 対応者はマスタのみ使用
$responders = $troubleRespondersMaster;
$pjNumbers = array_unique($pjNumbers);
sort($reporters);
sort($pjNumbers);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>トラブル対応一覧</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">
    <link rel="stylesheet" href="/style.css?v=20260206">
    <link rel="stylesheet" href="/css/components.css?v=20260211">
    <style<?= nonceAttr() ?>>
        .troubles-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .header-buttons {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn-primary {
            background: #2196F3;
            color: white;
        }
        .btn-primary:hover {
            background: #1976D2;
        }
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        .btn-success:hover {
            background: #45a049;
        }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
            font-size: 13px;
        }
        .filter-group select,
        .filter-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .trouble-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .trouble-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .trouble-table th {
            background: #f5f5f5;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #ddd;
            font-size: 13px;
        }
        .trouble-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        .trouble-table tr:hover {
            background: #f9f9f9;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending {
            background: var(--danger-light);
            color: #C62828;
        }
        .status-in-progress {
            background: var(--warning-light);
            color: #E65100;
        }
        .status-onhold {
            background: var(--purple-light);
            color: #6A1B9A;
        }
        .status-resolved {
            background: var(--success-light);
            color: #2E7D32;
        }
        .status-other {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        .status-select {
            padding: 6px 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .status-select.status-pending {
            background: var(--danger-light);
            color: #C62828;
            border-color: var(--danger);
        }
        .status-select.status-in-progress {
            background: var(--warning-light);
            color: #E65100;
            border-color: var(--warning);
        }
        .status-select.status-onhold {
            background: var(--purple-light);
            color: #6A1B9A;
            border-color: var(--purple);
        }
        .status-select.status-resolved {
            background: var(--success-light);
            color: #2E7D32;
            border-color: var(--success);
        }
        .status-select:hover {
            opacity: 0.8;
        }
        .btn-edit {
            padding: 5px 12px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            border: none;
            cursor: pointer;
        }
        .btn-edit:hover {
            background: var(--primary-dark);
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2196F3;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <?php include '../functions/header.php'; ?>

    <div class="troubles-container">
        <div class="page-header">
            <h1>トラブル対応一覧</h1>
            <div class="header-buttons">
                <?php if (canEdit()): ?>
                <a href="/forms/trouble-bulk-form.php" class="btn btn-primary">新規登録</a>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                    <button type="button" class="btn btn-success" id="syncBtn">スプシ同期</button>
                <?php endif; ?>
                <?php if (canEdit()): ?>
                    <a href="/pages/download-troubles-csv.php?status=<?= urlencode($filterStatus) ?>&pj_number=<?= urlencode($filterPjNumber) ?>&search=<?= urlencode($searchKeyword) ?>" class="btn btn-secondary">CSVダウンロード</a>
                <?php endif; ?>
                <button type="button"         class="btn" class="bg-f5" id="filterButton">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="align-middle mr-05">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                    フィルター<?php
                    $activeFilters = 0;
                    if (!empty($filterStatus)) $activeFilters++;
                    if (!empty($filterReporter)) $activeFilters++;
                    if (!empty($filterResponder)) $activeFilters++;
                    if ($sortBy !== 'date' || $sortDir !== 'desc') $activeFilters++;
                    if ($activeFilters > 0) echo " ({$activeFilters})";
                    ?>
                </button>
            </div>
        </div>

        <?php
        $totalCount = count($data['troubles'] ?? array());
        $pendingCount = count(array_filter($data['troubles'] ?? array(), function($t) {
            return ($t['status'] ?? '') === '未対応';
        }));
        $inProgressCount = count(array_filter($data['troubles'] ?? array(), function($t) {
            return ($t['status'] ?? '') === '対応中';
        }));
        $onHoldCount = count(array_filter($data['troubles'] ?? array(), function($t) {
            return ($t['status'] ?? '') === '保留';
        }));
        $completedCount = count(array_filter($data['troubles'] ?? array(), function($t) {
            return ($t['status'] ?? '') === '完了';
        }));
        $completionRate = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0;

        // 足本・曽我部の対応割合
        $ashimotoCount = count(array_filter($data['troubles'] ?? array(), function($t) {
            return ($t['responder'] ?? '') === '足本';
        }));
        $sogabeCount = count(array_filter($data['troubles'] ?? array(), function($t) {
            return ($t['responder'] ?? '') === '曽我部';
        }));
        $twoTotal = $ashimotoCount + $sogabeCount;
        $sogabeRate = $twoTotal > 0 ? round(($sogabeCount / $twoTotal) * 100, 1) : 0;
        $ashimotoRate = $twoTotal > 0 ? round(($ashimotoCount / $twoTotal) * 100, 1) : 0;
        ?>

        <!-- 統計サマリー -->
        <div      class="bg-white p-24-32 rounded-12 mb-2" style="box-shadow: 0 2px 8px rgba(0,0,0,0.1)">
            <!-- 件数統計 -->
            <div        class="d-flex align-center justify-center gap-4 flex-wrap mb-2">
                <div    class="text-center min-w-80">
                    <div        class="font-bold text-48" style="color: #333"><?php echo $totalCount; ?></div>
                    <div    class="text-sm text-gray-666">総件数</div>
                </div>
                <div     style="width: 1px; height: 60px; background: #e0e0e0"></div>
                <div      class="text-center min-w-70">
                    <div        class="font-bold text-36" style="color: <?php echo $pendingCount > 0 ? '#c62828' : '#999'; ?>"><?php echo $pendingCount; ?></div>
                    <div   class="text-13 text-gray-666">未対応</div>
                </div>
                <div      class="text-center min-w-70">
                    <div        class="font-bold text-36" style="color: <?php echo $inProgressCount > 0 ? '#c62828' : '#999'; ?>"><?php echo $inProgressCount; ?></div>
                    <div   class="text-13 text-gray-666">対応中</div>
                </div>
                <div      class="text-center min-w-70">
                    <div        class="font-bold text-36" style="color: <?php echo $onHoldCount > 0 ? '#c62828' : '#999'; ?>"><?php echo $onHoldCount; ?></div>
                    <div   class="text-13 text-gray-666">保留</div>
                </div>
                <div      class="text-center min-w-70">
                    <div        class="font-bold text-36" style="color: #333"><?php echo $completedCount; ?></div>
                    <div   class="text-13 text-gray-666">完了</div>
                </div>
                <div    class="text-center min-w-80">
                    <div        class="font-bold text-36" style="color: #333"><?php echo $completionRate; ?>%</div>
                    <div   class="text-13 text-gray-666">完了率</div>
                </div>
            </div>

            <!-- 対応割合（下部） -->
            <div     style="border-top: 1px solid #e0e0e0; padding-top: 16px">
                <div    class="mb-1 font-medium text-13 text-gray-666">対応割合</div>
                <div  class="d-flex align-center gap-3 mb-1">
                    <span     class="text-085">足本 <strong     class="text-base"><?php echo $ashimotoCount; ?>件</strong> <span   class="text-gray-666">(<?php echo $ashimotoRate; ?>%)</span></span>
                    <span     class="text-085">曽我部 <strong     class="text-base"><?php echo $sogabeCount; ?>件</strong> <span   class="text-gray-666">(<?php echo $sogabeRate; ?>%)</span></span>
                    <span     class="text-13 text-999">計<?php echo $twoTotal; ?>件</span>
                </div>
                <?php if ($twoTotal > 0): ?>
                <div        class="rounded max-w-400" style="background: #e0e0e0; height: 10px; overflow: hidden">
                    <div     style="background: #555; height: 100%; width: <?php echo $ashimotoRate; ?>%; float: left"></div>
                    <div     style="background: #999; height: 100%; width: <?php echo $sogabeRate; ?>%; float: left"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 検索バー -->
        <div        class="rounded-lg d-flex align-center flex-wrap gap-075 bg-white p-12-16 mb-2" style="box-shadow:0 2px 4px rgba(0,0,0,0.1)">
            <form method="GET"      class="d-flex align-center flex-wrap gap-075 flex-1">
                <div        class="d-flex align-center gap-1 min-w-200" style="max-width:350px">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"     style="flex-shrink:0">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="キーワードで検索..." class="flex-1 py-05 px-12 border-gray-300 rounded-6 text-09">
                </div>
                <div        class="d-flex align-center gap-1 min-w-200">
                    <label      class="whitespace-nowrap font-semibold text-09">PJ番号:</label>
                    <input type="text" name="pj_number" value="<?= htmlspecialchars($filterPjNumber) ?>" placeholder="PJ番号で検索..." list="pj-number-list-main" class="flex-1 max-w-300 py-05 px-12 border-gray-300 rounded-6 text-09">
                    <datalist id="pj-number-list-main">
                        <?php foreach ($pjNumbers as $pj): ?>
                            <option value="<?= htmlspecialchars($pj) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <?php if (!empty($filterStatus)): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>"><?php endif; ?>
                <?php if (!empty($filterReporter)): ?><input type="hidden" name="reporter" value="<?= htmlspecialchars($filterReporter) ?>"><?php endif; ?>
                <?php if (!empty($filterResponder)): ?><input type="hidden" name="responder" value="<?= htmlspecialchars($filterResponder) ?>"><?php endif; ?>
                <?php if ($sortBy !== 'date'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>"><?php endif; ?>
                <?php if ($sortDir !== 'desc'): ?><input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>"><?php endif; ?>
                <button type="submit"         class="btn btn-primary py-05 px-16">検索</button>
                <?php if (!empty($filterPjNumber) || !empty($searchKeyword)): ?>
                    <a href="troubles.php?<?= http_build_query(array_filter(['status' => $filterStatus, 'reporter' => $filterReporter, 'responder' => $filterResponder, 'sort' => $sortBy !== 'date' ? $sortBy : '', 'dir' => $sortDir !== 'desc' ? $sortDir : ''])) ?>" class="btn bg-light py-05 px-16">クリア</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- フィルターモーダル -->
        <div id="filterModal"        class="d-none align-center justify-center" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; backdrop-filter:blur(5px)">
            <div        class="p-3 overflow-y-auto bg-white rounded-16" style="max-width:480px; width:90%; box-shadow:0 8px 32px rgba(0,0,0,0.2); max-height:90vh">
                <div  class="d-flex justify-between align-center mb-2">
                    <h3        class="m-0 text-11">フィルター・並び替え</h3>
                    <button type="button"         class="modal-close-btn cursor-pointer p-05 text-999" style="background:none; border:none; font-size:1.2rem">✕</button>
                </div>
                <form method="GET">
                    <?php if (!empty($filterPjNumber)): ?><input type="hidden" name="pj_number" value="<?= htmlspecialchars($filterPjNumber) ?>"><?php endif; ?>
                    <?php if (!empty($searchKeyword)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($searchKeyword) ?>"><?php endif; ?>
                    <div    class="mb-2 grid grid-cols-2 gap-075">
                        <div>
                            <label    class="d-block text-sm font-semibold mb-05">状態</label>
                            <select name="status"        class="w-full p-1 text-09" class="input-base-simple">
                                <option value="">すべて</option>
                                <option value="未対応" <?php echo $filterStatus === '未対応' ? 'selected' : ''; ?>>未対応</option>
                                <option value="対応中" <?php echo $filterStatus === '対応中' ? 'selected' : ''; ?>>対応中</option>
                                <option value="保留" <?php echo $filterStatus === '保留' ? 'selected' : ''; ?>>保留</option>
                                <option value="完了" <?php echo $filterStatus === '完了' ? 'selected' : ''; ?>>完了</option>
                            </select>
                        </div>
                        <div>
                            <label    class="d-block text-sm font-semibold mb-05">記入者</label>
                            <select name="reporter"        class="w-full p-1 text-09" class="input-base-simple">
                                <option value="">すべて</option>
                                <?php foreach ($reporters as $reporter): ?>
                                    <option value="<?php echo htmlspecialchars($reporter); ?>" <?php echo $filterReporter === $reporter ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($reporter); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label    class="d-block text-sm font-semibold mb-05">対応者</label>
                            <select name="responder"        class="w-full p-1 text-09" class="input-base-simple">
                                <option value="">すべて</option>
                                <?php foreach ($responders as $responder): ?>
                                    <option value="<?php echo htmlspecialchars($responder); ?>" <?php echo $filterResponder === $responder ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($responder); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label    class="d-block text-sm font-semibold mb-05">並び替え</label>
                            <select name="sort"        class="w-full p-1 text-09" class="input-base-simple">
                                <option value="date" <?php echo $sortBy === 'date' ? 'selected' : ''; ?>>日付</option>
                                <option value="responder" <?php echo $sortBy === 'responder' ? 'selected' : ''; ?>>対応者</option>
                                <option value="reporter" <?php echo $sortBy === 'reporter' ? 'selected' : ''; ?>>記入者</option>
                                <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>状態</option>
                                <option value="pj_number" <?php echo $sortBy === 'pj_number' ? 'selected' : ''; ?>>P番号</option>
                            </select>
                        </div>
                        <div>
                            <label    class="d-block text-sm font-semibold mb-05">順序</label>
                            <select name="dir"        class="w-full p-1 text-09" class="input-base-simple">
                                <option value="desc" <?php echo $sortDir === 'desc' ? 'selected' : ''; ?>>降順</option>
                                <option value="asc" <?php echo $sortDir === 'asc' ? 'selected' : ''; ?>>昇順</option>
                            </select>
                        </div>
                    </div>
                    <div  class="d-flex gap-1 justify-end">
                        <a href="troubles.php"         class="btn no-underline py-8 px-20 bg-light rounded-6">クリア</a>
                        <button type="submit"       class="btn btn-primary py-8 px-20">適用</button>
                    </div>
                </form>
            </div>
        </div>

        <?php
        // ソートURL生成ヘルパー
        function sortUrl($column) {
            global $sortBy, $sortDir, $filterStatus, $filterReporter, $filterResponder, $filterPjNumber, $searchKeyword;
            $params = array_filter([
                'status' => $filterStatus,
                'reporter' => $filterReporter,
                'responder' => $filterResponder,
                'pj_number' => $filterPjNumber,
                'search' => $searchKeyword,
                'sort' => $column,
                'dir' => ($sortBy === $column && $sortDir === 'asc') ? 'desc' : 'asc',
            ], function($v) { return $v !== ''; });
            return 'troubles.php?' . http_build_query($params);
        }
        function sortIcon($column) {
            global $sortBy, $sortDir;
            if ($sortBy !== $column) return '';
            return $sortDir === 'asc' ? ' ▲' : ' ▼';
        }
        ?>
        <?php if (empty($troubles)): ?>
            <div class="trouble-table">
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <h3>トラブル対応データがありません</h3>
                    <p>新規登録またはスプレッドシートから同期してください</p>
                </div>
            </div>
        <?php else: ?>
            <div class="trouble-table">
                <table id="troubleTable">
                    <thead>
                        <tr>
                            <?php if (canEdit()): ?>
                            <th    class="w-40"><input type="checkbox" id="selectAll"></th>
                            <?php endif; ?>
                            <th   class="w-80"><a href="<?= sortUrl('date') ?>" class="link-inherit">日付<?= sortIcon('date') ?></a></th>
                            <th  class="w-150"><a href="<?= sortUrl('pj_number') ?>" class="link-inherit">P番号<?= sortIcon('pj_number') ?></a></th>
                            <th>トラブル内容</th>
                            <th>対応内容</th>
                            <th   class="w-80"><a href="<?= sortUrl('reporter') ?>" class="link-inherit">記入者<?= sortIcon('reporter') ?></a></th>
                            <th   class="w-80"><a href="<?= sortUrl('responder') ?>" class="link-inherit">対応者<?= sortIcon('responder') ?></a></th>
                            <th   class="w-100"><a href="<?= sortUrl('status') ?>" class="link-inherit">状態<?= sortIcon('status') ?></a></th>
                            <th   class="w-100">お客様</th>
                            <th   class="w-80">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($troubles as $trouble): ?>
                            <?php
                            $status = $trouble['status'] ?? '';
                            $statusClass = 'status-other';
                            switch ($status) {
                                case '未対応':
                                    $statusClass = 'status-pending';
                                    break;
                                case '対応中':
                                    $statusClass = 'status-in-progress';
                                    break;
                                case '保留':
                                    $statusClass = 'status-onhold';
                                    break;
                                case '完了':
                                    $statusClass = 'status-resolved';
                                    break;
                            }
                            ?>
                            <tr id="trouble-<?= $trouble['id'] ?>">
                                <?php if (canEdit()): ?>
                                <td><input type="checkbox" class="trouble-checkbox" value="<?php echo $trouble['id']; ?>"></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($trouble['date'] ?? ''); ?></td>
                                <td>
                                    <?php
                                    $pjNumber = $trouble['pj_number'] ?? $trouble['project_name'] ?? '';
                                    $projectInfo = null;

                                    if (!empty($pjNumber)):
                                        // P番号でプロジェクトマスタを検索（大文字小文字を無視）
                                        $projectInfo = null;
                                        foreach ($data['projects'] ?? array() as $proj) {
                                            if (strcasecmp($proj['id'], $pjNumber) === 0) {
                                                $projectInfo = $proj;
                                                break;
                                            }
                                        }
                                        // 見つからない場合、案件名で部分一致検索
                                        if (!$projectInfo && mb_strlen($pjNumber) > 5) {
                                            foreach ($data['projects'] ?? array() as $proj) {
                                                if (mb_strpos($proj['name'] ?? '', $pjNumber) !== false || mb_strpos($pjNumber, $proj['name'] ?? '') !== false) {
                                                    $projectInfo = $proj;
                                                    break;
                                                }
                                            }
                                        }
                                    ?>
                                        <?php if ($projectInfo): ?>
                                            <?php echo htmlspecialchars($pjNumber); ?>
                                        <?php else: ?>
                                            <span   class="text-red-f44">
                                                <?php echo htmlspecialchars($pjNumber); ?>
                                            </span>
                                            <br><small   class="text-red-f44">未登録</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($trouble['case_no'])): ?>
                                        <br><small   class="text-gray-666"><?php echo htmlspecialchars($trouble['case_no']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo nl2br(htmlspecialchars($trouble['trouble_content'] ?? '')); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($trouble['response_content'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($trouble['reporter'] ?? ''); ?></td>
                                <td>
                                    <?php if (canEdit()): ?>
                                        <?php if (empty($responders)): ?>
                                            <?php echo htmlspecialchars($trouble['responder'] ?? '未設定'); ?>
                                        <?php else: ?>
                                        <form method="POST"  class="m-0">
                                            <?= csrfTokenField() ?>
                                            <input type="hidden" name="change_responder" value="1">
                                            <input type="hidden" name="trouble_id" value="<?php echo $trouble['id']; ?>">
                                            <select name="new_responder"         class="responder-select rounded w-full p-05 text-13 bg-white border-gray-300">
                                                <option value="">未設定</option>
                                                <?php foreach ($responders as $r): ?>
                                                    <option value="<?php echo htmlspecialchars($r); ?>" <?php echo ($trouble['responder'] ?? '') === $r ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($r); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($trouble['responder'] ?? ''); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (canEdit()): ?>
                                        <form method="POST"  class="m-0">
                                            <?= csrfTokenField() ?>
                                            <input type="hidden" name="change_status" value="1">
                                            <input type="hidden" name="trouble_id" value="<?php echo $trouble['id']; ?>">
                                            <select name="new_status" class="status-select <?php echo $statusClass; ?>">
                                                <option value="未対応" <?php echo $status === '未対応' ? 'selected' : ''; ?>>未対応</option>
                                                <option value="対応中" <?php echo $status === '対応中' ? 'selected' : ''; ?>>対応中</option>
                                                <option value="保留" <?php echo $status === '保留' ? 'selected' : ''; ?>>保留</option>
                                                <option value="完了" <?php echo $status === '完了' ? 'selected' : ''; ?>>完了</option>
                                            </select>
                                        </form>
                                    <?php else: ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($trouble['company_name'])): ?>
                                        <?php echo htmlspecialchars($trouble['company_name']); ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($trouble['customer_name'])): ?>
                                        <small><?php echo htmlspecialchars($trouble['customer_name'] . ($trouble['honorific'] ?? '様')); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (canEdit()): ?>
                                    <button type="button" class="btn-edit" data-trouble='<?= json_encode($trouble, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>'>編集</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="troublePagination"></div>
            </div>
        <?php endif; ?>
    </div>

<script<?= nonceAttr() ?>>
document.addEventListener('DOMContentLoaded', function() {
    var table = document.getElementById('troubleTable');
    if (table && table.querySelector('tbody tr')) {
        new Paginator({
            container: '#troubleTable',
            itemSelector: 'tbody tr',
            perPage: 50,
            paginationTarget: '#troublePagination'
        });
    }
});
</script>

<?php if (canEdit()): ?>
<!-- 編集モーダル -->
<div id="editModal"        class="d-none align-center justify-center" class="modal-overlay">
    <div        class="p-3 overflow-y-auto bg-white rounded-12" style="max-width:700px; width:95%; box-shadow:0 8px 24px rgba(0,0,0,0.2); max-height:90vh">
        <div  class="d-flex justify-between align-center mb-2">
            <h3        class="m-0 text-11">トラブル対応編集</h3>
            <button type="button" id="closeEditModalBtn"        class="cursor-pointer p-05 text-999" style="background:none; border:none; font-size:1.2rem">✕</button>
        </div>
        <form id="editForm" method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="modal_edit" value="1">
            <input type="hidden" name="edit_id" id="edit_id">

            <div    class="mb-2 grid grid-cols-3 gap-075">
                <div>
                    <label    class="d-block text-sm font-semibold mb-05">日付<span   class="text-red-f44">*</span></label>
                    <input type="text" name="edit_date" id="edit_date" required        class="w-full p-1 text-09 box-border" class="input-base-simple">
                </div>
                <div>
                    <label    class="d-block text-sm font-semibold mb-05">対応期限</label>
                    <input type="date" name="edit_deadline" id="edit_deadline"        class="w-full p-1 text-09 box-border" class="input-base-simple">
                </div>
                <div>
                    <label    class="d-block text-sm font-semibold mb-05">コールNo</label>
                    <input type="text" name="edit_call_no" id="edit_call_no"        class="w-full p-1 text-09 box-border" class="input-base-simple">
                </div>
            </div>

            <div  class="mb-2">
                <label    class="d-block text-sm font-semibold mb-05">P番号<span   class="text-red-f44">*</span></label>
                <input type="text" name="edit_pj_number" id="edit_pj_number" required list="edit_pj_list"        class="w-full p-1 text-09 box-border" class="input-base-simple">
                <datalist id="edit_pj_list">
                    <?php foreach ($data['projects'] ?? array() as $proj): ?>
                        <option value="<?= htmlspecialchars($proj['id']) ?>"><?= htmlspecialchars($proj['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div  class="mb-2">
                <label    class="d-block text-sm font-semibold mb-05">トラブル内容<span   class="text-red-f44">*</span></label>
                <textarea name="edit_trouble_content" id="edit_trouble_content" required rows="3"        class="w-full p-1 text-09 box-border resize-vertical" class="input-base-simple"></textarea>
            </div>

            <div  class="mb-2">
                <label    class="d-block text-sm font-semibold mb-05">対応内容</label>
                <textarea name="edit_response_content" id="edit_response_content" rows="3"        class="w-full p-1 text-09 box-border resize-vertical" class="input-base-simple"></textarea>
            </div>

            <div  class="mb-2">
                <label    class="d-block text-sm font-semibold mb-05">再発防止策</label>
                <textarea name="edit_prevention_notes" id="edit_prevention_notes" rows="2"        class="w-full p-1 text-09 box-border resize-vertical" class="input-base-simple"></textarea>
            </div>

            <?php
            // 従業員マスタの名前リスト（記入者用）
            $employeeNames = array_map(function($e) { return $e['name'] ?? ''; }, $data['employees'] ?? []);
            // 記入者リストに従業員・トラブル担当者マスタ・既存データを統合
            $allReporters = array_unique(array_merge($employeeNames, $troubleRespondersMaster, $reporters));
            $allReporters = array_filter($allReporters, fn($n) => !empty($n));
            // 対応者はトラブル担当者マスタのみ使用
            $allResponders = $responders;
            sort($allReporters);
            ?>
            <div    class="mb-2 grid grid-cols-3 gap-075">
                <div>
                    <label    class="d-block text-sm font-semibold mb-05">記入者<span   class="text-red-f44">*</span></label>
                    <?php if (empty($allReporters)): ?>
                        <input type="text" name="edit_reporter" id="edit_reporter" required placeholder="名前を入力"        class="w-full p-1 text-09 box-border" class="input-base-simple">
                    <?php else: ?>
                    <select name="edit_reporter" id="edit_reporter" required        class="w-full p-1 text-09" class="input-base-simple">
                        <option value="">選択してください</option>
                        <?php foreach ($allReporters as $name): if (empty($name)) continue; ?>
                            <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
                <div>
                    <label    class="d-block text-sm font-semibold mb-05">対応者<span   class="text-red-f44">*</span></label>
                    <?php if (empty($allResponders)): ?>
                        <input type="text" name="edit_responder" id="edit_responder" required placeholder="名前を入力"        class="w-full p-1 text-09 box-border" class="input-base-simple">
                    <?php else: ?>
                    <select name="edit_responder" id="edit_responder" required        class="w-full p-1 text-09" class="input-base-simple">
                        <option value="">選択してください</option>
                        <?php foreach ($allResponders as $name): if (empty($name)) continue; ?>
                            <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
                <div>
                    <label    class="d-block text-sm font-semibold mb-05">状態<span   class="text-red-f44">*</span></label>
                    <select name="edit_status" id="edit_status" required        class="w-full p-1 text-09" class="input-base-simple">
                        <option value="未対応">未対応</option>
                        <option value="対応中">対応中</option>
                        <option value="保留">保留</option>
                        <option value="完了">完了</option>
                    </select>
                </div>
            </div>

            <div    class="mb-2 grid grid-cols-3 gap-075">
                <div>
                    <label    class="d-block text-sm font-semibold mb-05">案件No</label>
                    <input type="text" name="edit_case_no" id="edit_case_no"        class="w-full p-1 text-09 box-border" class="input-base-simple">
                </div>
                <div>
                    <label    class="d-block text-sm font-semibold mb-05">社名</label>
                    <input type="text" name="edit_company_name" id="edit_company_name"        class="w-full p-1 text-09 box-border" class="input-base-simple">
                </div>
                <div>
                    <label    class="d-block text-sm font-semibold mb-05">お客様お名前</label>
                    <input type="text" name="edit_customer_name" id="edit_customer_name"        class="w-full p-1 text-09 box-border" class="input-base-simple">
                </div>
            </div>

            <div        class="d-flex gap-1 justify-end mt-2">
                <button type="button"         class="btn cancel-edit-btn py-8 px-20" class="bg-f5">キャンセル</button>
                <button type="submit"       class="btn btn-success py-8 px-20">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 一括変更フローティングバー -->
<div id="bulkActionBar"        class="d-none align-center justify-center gap-2" style="position:fixed; bottom:0; left:0; right:0; background:#1e293b; color:white; padding:12px 24px; z-index:9999; box-shadow:0 -4px 12px rgba(0,0,0,0.2)">
    <span id="bulkSelectedCount"   class="font-semibold">0件選択中</span>
    <button type="button"  id="openBulkModalBtn"        class="btn btn-primary" style="padding:6px 20px">一括変更</button>
    <?php if (isAdmin()): ?>
    <button type="button"  id="bulkDeleteBtn"        class="btn" style="background:#dc2626; color:white; padding:6px 20px">一括削除</button>
    <?php endif; ?>
    <button type="button"  id="clearSelectionBtn"        class="btn" style="background:#475569; color:white; padding:6px 16px">選択解除</button>
</div>

<!-- 一括変更モーダル -->
<div id="bulkModal"        class="d-none align-center justify-center" class="modal-overlay">
    <div        class="p-3 bg-white max-w-400" style="border-radius:12px; width:90%; box-shadow:0 8px 24px rgba(0,0,0,0.2)">
        <h3     style="margin:0 0 16px; font-size:1.1rem">一括変更</h3>
        <form method="POST" id="bulkChangeForm">
            <?= csrfTokenField() ?>
            <input type="hidden" name="bulk_change" value="1">
            <div id="bulkIdsContainer"></div>

            <div  class="mb-2">
                <label        class="d-block font-semibold text-09" class="mb-05">対応者</label>
                <?php if (empty($responders)): ?>
                    <input type="text" name="bulk_responder" placeholder="対応者名を入力（空欄で変更しない）" value="__no_change__" onfocus="if(this.value==='__no_change__')this.value=''"        class="w-full p-1 text-09 box-border" class="input-base-simple">
                <?php else: ?>
                <select name="bulk_responder"        class="w-full p-1 text-09" class="input-base-simple">
                    <option value="__no_change__">変更しない</option>
                    <option value="">未設定</option>
                    <?php foreach ($responders as $r): ?>
                        <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

            <div     style="margin-bottom:20px">
                <label        class="d-block font-semibold text-09" class="mb-05">状態</label>
                <select name="bulk_status"        class="w-full p-1 text-09" class="input-base-simple">
                    <option value="__no_change__">変更しない</option>
                    <option value="未対応">未対応</option>
                    <option value="対応中">対応中</option>
                    <option value="保留">保留</option>
                    <option value="完了">完了</option>
                </select>
            </div>

            <div  class="d-flex gap-1 justify-end">
                <button type="button"         class="btn cancel-bulk-btn py-8 px-20" class="bg-f5">キャンセル</button>
                <button type="submit"       class="btn btn-primary py-8 px-20">変更を適用</button>
            </div>
        </form>
    </div>
</div>

<script<?= nonceAttr() ?>>
// ユーティリティ関数
function updateBulkBar() {
    const checked = document.querySelectorAll('.trouble-checkbox:checked');
    const bar = document.getElementById('bulkActionBar');
    if (checked.length > 0) {
        bar.style.display = 'flex';
        document.getElementById('bulkSelectedCount').textContent = checked.length + '件選択中';
    } else {
        bar.style.display = 'none';
    }
}

// イベントリスナー登録
document.addEventListener('DOMContentLoaded', function() {
    // フィルターボタン
    const filterButton = document.getElementById('filterButton');
    if (filterButton) {
        filterButton.addEventListener('click', function() {
            document.getElementById('filterModal').style.display = 'flex';
        });
    }

    // フィルターモーダルの閉じるボタンとバックドロップクリック
    const filterModal = document.getElementById('filterModal');
    if (filterModal) {
        filterModal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
        const filterCloseBtn = filterModal.querySelector('.modal-close-btn');
        if (filterCloseBtn) {
            filterCloseBtn.addEventListener('click', function() {
                filterModal.style.display = 'none';
            });
        }
    }

    // 全選択チェックボックス
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.trouble-checkbox').forEach(cb => {
                cb.checked = this.checked;
            });
            updateBulkBar();
        });
    }

    // 個別チェックボックス
    document.querySelectorAll('.trouble-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkBar);
    });

    // 対応者変更セレクト（自動送信）
    document.querySelectorAll('.responder-select').forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });

    // ステータス変更セレクト（自動送信）
    document.querySelectorAll('.status-select').forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });

    // 編集ボタン
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const troubleData = this.getAttribute('data-trouble');
            if (troubleData) {
                const trouble = JSON.parse(troubleData);
                openEditModal(trouble);
            }
        });
    });

    // 編集モーダルの閉じるボタン
    const closeEditModalBtn = document.getElementById('closeEditModalBtn');
    if (closeEditModalBtn) {
        closeEditModalBtn.addEventListener('click', closeEditModal);
    }

    const cancelEditBtns = document.querySelectorAll('.cancel-edit-btn');
    cancelEditBtns.forEach(btn => {
        btn.addEventListener('click', closeEditModal);
    });

    // 編集モーダルのバックドロップクリック
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
    }

    // 一括変更ボタン
    const openBulkModalBtn = document.getElementById('openBulkModalBtn');
    if (openBulkModalBtn) {
        openBulkModalBtn.addEventListener('click', openBulkModal);
    }

    // 一括削除ボタン
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', bulkDelete);
    }

    // 選択解除ボタン
    const clearSelectionBtn = document.getElementById('clearSelectionBtn');
    if (clearSelectionBtn) {
        clearSelectionBtn.addEventListener('click', function() {
            document.querySelectorAll('.trouble-checkbox').forEach(cb => {
                cb.checked = false;
            });
            const selectAll = document.getElementById('selectAll');
            if (selectAll) selectAll.checked = false;
            updateBulkBar();
        });
    }

    // 一括変更モーダルのキャンセルボタン
    const cancelBulkBtns = document.querySelectorAll('.cancel-bulk-btn');
    cancelBulkBtns.forEach(btn => {
        btn.addEventListener('click', closeBulkModal);
    });

    // 一括変更モーダルのバックドロップクリック
    const bulkModal = document.getElementById('bulkModal');
    if (bulkModal) {
        bulkModal.addEventListener('click', function(e) {
            if (e.target === this) closeBulkModal();
        });
    }

    // スプシ同期ボタン
    const syncBtn = document.getElementById('syncBtn');
    if (syncBtn) {
        syncBtn.addEventListener('click', syncFromSheet);
    }
});

// モーダル関連関数
function openEditModal(trouble) {
    document.getElementById('edit_id').value = trouble.id || '';
    document.getElementById('edit_date').value = trouble.date || '';
    document.getElementById('edit_deadline').value = trouble.deadline || '';
    document.getElementById('edit_call_no').value = trouble.call_no || '';
    document.getElementById('edit_pj_number').value = trouble.pj_number || trouble.project_name || '';
    document.getElementById('edit_trouble_content').value = trouble.trouble_content || '';
    document.getElementById('edit_response_content').value = trouble.response_content || '';
    document.getElementById('edit_prevention_notes').value = trouble.prevention_notes || '';
    document.getElementById('edit_reporter').value = trouble.reporter || '';
    document.getElementById('edit_responder').value = trouble.responder || '';
    document.getElementById('edit_status').value = trouble.status || '未対応';
    document.getElementById('edit_case_no').value = trouble.case_no || '';
    document.getElementById('edit_company_name').value = trouble.company_name || '';
    document.getElementById('edit_customer_name').value = trouble.customer_name || '';
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function openBulkModal() {
    const checked = document.querySelectorAll('.trouble-checkbox:checked');
    const container = document.getElementById('bulkIdsContainer');
    container.innerHTML = '';
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'trouble_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    document.getElementById('bulkModal').style.display = 'flex';
}

function closeBulkModal() {
    document.getElementById('bulkModal').style.display = 'none';
}

<?php if (isAdmin()): ?>
function bulkDelete() {
    const checked = document.querySelectorAll('.trouble-checkbox:checked');
    if (checked.length === 0) {
        alert('削除する項目を選択してください');
        return;
    }

    if (!confirm(`${checked.length}件のトラブル対応を削除しますか？\nこの操作は取り消せません。`)) {
        return;
    }

    // 削除フォームを作成して送信
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'troubles.php';

    // CSRFトークン
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?= generateCsrfToken() ?>';
    form.appendChild(csrfInput);

    // 削除フラグ
    const deleteInput = document.createElement('input');
    deleteInput.type = 'hidden';
    deleteInput.name = 'bulk_delete';
    deleteInput.value = '1';
    form.appendChild(deleteInput);

    // 選択されたID
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'trouble_ids[]';
        input.value = cb.value;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}
<?php endif; ?>
</script>
<?php endif; ?>

<?php if (isAdmin()): ?>
<script<?= nonceAttr() ?>>
const csrfToken = '<?= generateCsrfToken() ?>';

async function syncFromSheet() {
    const btn = document.getElementById('syncBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '同期中...';

    try {
        // まずPJ番号を大文字に正規化
        const normalizeRes = await fetch('/api/normalize-pj-numbers.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({})
        });
        const normalizeData = await normalizeRes.json();

        // スプシから同期
        const syncRes = await fetch('/api/sync-troubles.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ action: 'sync' })
        });
        const syncData = await syncRes.json();

        if (syncData.success) {
            let msg = syncData.message;
            if (normalizeData.updated > 0) {
                msg += `\n(PJ番号正規化: ${normalizeData.updated}件)`;
            }
            alert(msg);
            location.reload();
        } else {
            alert('エラー: ' + (syncData.error || '同期に失敗しました'));
        }
    } catch (e) {
        alert('通信エラー: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}
</script>
<?php endif; ?>

<style<?= nonceAttr() ?>>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
</body>
</html>
