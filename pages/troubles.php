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
        saveData($data);
        writeAuditLog('bulk_update', 'trouble', "トラブル一括変更: {$changed}件", [
            'ids' => $ids,
            'new_status' => $newStatus !== '__no_change__' ? $newStatus : null,
            'new_responder' => $newResponder !== '__no_change__' ? $newResponder : null
        ]);
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
    saveData($data);
    writeAuditLog('update', 'trouble', "トラブル対応者変更: ID {$troubleId} → {$newResponder}");
    header('Location: troubles.php?responder_updated=1');
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
        saveData($data);
        writeAuditLog('update', 'trouble', "トラブルステータス変更: ID {$troubleId} {$oldStatus}→{$newStatus}");
        header('Location: troubles.php?status_updated=1');
        exit;
    }
}

$troubles = $data['troubles'] ?? array();

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

// ユニークな記入者・対応者・PJ番号リスト
$reporters = array();
$responders = array();
$pjNumbers = array();
foreach ($data['troubles'] ?? array() as $t) {
    if (!empty($t['reporter'])) $reporters[] = $t['reporter'];
    if (!empty($t['responder'])) $responders[] = $t['responder'];
    $pj = $t['pj_number'] ?? $t['project_name'] ?? '';
    if (!empty($pj)) $pjNumbers[] = $pj;
}
$reporters = array_unique($reporters);
$responders = array_unique($responders);
$pjNumbers = array_unique($pjNumbers);
sort($reporters);
sort($responders);
sort($pjNumbers);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>トラブル対応一覧</title>
    <link rel="stylesheet" href="/style.css">
    <style>
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
        .status-resolved {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #ffebee;
            color: #c62828;
        }
        .status-in-progress {
            background: #fff3e0;
            color: #e65100;
        }
        .status-onhold {
            background: #fff9c4;
            color: #f57f17;
        }
        .status-resolved {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .status-other {
            background: #f5f5f5;
            color: #666;
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
            background: #ffebee;
            color: #c62828;
            border-color: #ef5350;
        }
        .status-select.status-in-progress {
            background: #fff3e0;
            color: #e65100;
            border-color: #ff9800;
        }
        .status-select.status-onhold {
            background: #fff9c4;
            color: #f57f17;
            border-color: #ffc107;
        }
        .status-select.status-resolved {
            background: #e8f5e9;
            color: #2e7d32;
            border-color: #4caf50;
        }
        .status-select:hover {
            opacity: 0.8;
        }
        .btn-edit {
            padding: 5px 12px;
            background: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
        }
        .btn-edit:hover {
            background: #1976D2;
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
                <a href="/forms/trouble-bulk-form.php" class="btn btn-primary">新規登録</a>
                <?php if (canEdit()): ?>
                    <a href="/pages/download-troubles-csv.php?status=<?= urlencode($filterStatus) ?>&pj_number=<?= urlencode($filterPjNumber) ?>&search=<?= urlencode($searchKeyword) ?>" class="btn btn-secondary">CSVダウンロード</a>
                <?php endif; ?>
                <button type="button" class="btn" style="background:#f5f5f5;color:#333;" onclick="document.getElementById('filterModal').style.display='flex'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                    フィルター<?php
                    $activeFilters = 0;
                    if (!empty($filterStatus)) $activeFilters++;
                    if (!empty($filterReporter)) $activeFilters++;
                    if (!empty($filterResponder)) $activeFilters++;
                    if (!empty($filterPjNumber)) $activeFilters++;
                    if (!empty($searchKeyword)) $activeFilters++;
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

        <div class="stats-row">
            <div class="stat-card" style="border-left: 4px solid #666;">
                <div class="stat-number"><?php echo $totalCount; ?></div>
                <div class="stat-label">総件数</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #f44336;">
                <div class="stat-number"><?php echo $pendingCount; ?></div>
                <div class="stat-label">未対応</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #ff9800;">
                <div class="stat-number"><?php echo $inProgressCount; ?></div>
                <div class="stat-label">対応中</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #ffc107;">
                <div class="stat-number"><?php echo $onHoldCount; ?></div>
                <div class="stat-label">保留</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #4caf50;">
                <div class="stat-number"><?php echo $completedCount; ?></div>
                <div class="stat-label">完了</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #2196f3;">
                <div class="stat-number"><?php echo $completionRate; ?>%</div>
                <div class="stat-label">完了率</div>
            </div>
        </div>

        <div class="stats-row" style="margin-top: 8px;">
            <div class="stat-card" style="border-left: 4px solid #9c27b0; flex: 0 0 auto; padding: 0.5rem 1rem;">
                <div style="font-size: 0.8rem; color: #666; margin-bottom: 4px;">対応割合（足本 / 曽我部）</div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-weight: 600;">足本 <span style="color: #1976d2;"><?php echo $ashimotoCount; ?>件 (<?php echo $ashimotoRate; ?>%)</span></span>
                    <span style="color: #999;">|</span>
                    <span style="font-weight: 600;">曽我部 <span style="color: #e65100;"><?php echo $sogabeCount; ?>件 (<?php echo $sogabeRate; ?>%)</span></span>
                    <span style="color: #999; font-size: 0.75rem;">計<?php echo $twoTotal; ?>件</span>
                </div>
                <?php if ($twoTotal > 0): ?>
                <div style="margin-top: 4px; background: #e0e0e0; border-radius: 4px; height: 6px; overflow: hidden;">
                    <div style="background: #1976d2; height: 100%; width: <?php echo $ashimotoRate; ?>%; float: left;"></div>
                    <div style="background: #e65100; height: 100%; width: <?php echo $sogabeRate; ?>%; float: left;"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- フィルターモーダル -->
        <div id="filterModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:10001; align-items:center; justify-content:center;">
            <div style="background:white; border-radius:12px; padding:24px; max-width:480px; width:90%; box-shadow:0 8px 24px rgba(0,0,0,0.2); max-height:90vh; overflow-y:auto;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h3 style="margin:0; font-size:1.1rem;">フィルター・並び替え</h3>
                    <button type="button" onclick="document.getElementById('filterModal').style.display='none'" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:#999; padding:4px;">✕</button>
                </div>
                <form method="GET">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                        <div style="grid-column:1/-1;">
                            <label style="display:block; font-weight:600; margin-bottom:4px; font-size:0.85rem;">PJ番号</label>
                            <input type="text" name="pj_number" value="<?php echo htmlspecialchars($filterPjNumber); ?>" placeholder="PJ番号で検索" list="pj-number-list" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; font-size:0.9rem; box-sizing:border-box;">
                            <datalist id="pj-number-list">
                                <?php foreach ($pjNumbers as $pj): ?>
                                    <option value="<?php echo htmlspecialchars($pj); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div style="grid-column:1/-1;">
                            <label style="display:block; font-weight:600; margin-bottom:4px; font-size:0.85rem;">キーワード検索</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="トラブル内容、現場名など" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; font-size:0.9rem; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:4px; font-size:0.85rem;">状態</label>
                            <select name="status" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; font-size:0.9rem;">
                                <option value="">すべて</option>
                                <option value="未対応" <?php echo $filterStatus === '未対応' ? 'selected' : ''; ?>>未対応</option>
                                <option value="対応中" <?php echo $filterStatus === '対応中' ? 'selected' : ''; ?>>対応中</option>
                                <option value="保留" <?php echo $filterStatus === '保留' ? 'selected' : ''; ?>>保留</option>
                                <option value="完了" <?php echo $filterStatus === '完了' ? 'selected' : ''; ?>>完了</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:4px; font-size:0.85rem;">記入者</label>
                            <select name="reporter" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; font-size:0.9rem;">
                                <option value="">すべて</option>
                                <?php foreach ($reporters as $reporter): ?>
                                    <option value="<?php echo htmlspecialchars($reporter); ?>" <?php echo $filterReporter === $reporter ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($reporter); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:4px; font-size:0.85rem;">対応者</label>
                            <select name="responder" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; font-size:0.9rem;">
                                <option value="">すべて</option>
                                <?php foreach ($responders as $responder): ?>
                                    <option value="<?php echo htmlspecialchars($responder); ?>" <?php echo $filterResponder === $responder ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($responder); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:4px; font-size:0.85rem;">並び替え</label>
                            <select name="sort" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; font-size:0.9rem;">
                                <option value="date" <?php echo $sortBy === 'date' ? 'selected' : ''; ?>>日付</option>
                                <option value="responder" <?php echo $sortBy === 'responder' ? 'selected' : ''; ?>>対応者</option>
                                <option value="reporter" <?php echo $sortBy === 'reporter' ? 'selected' : ''; ?>>記入者</option>
                                <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>状態</option>
                                <option value="pj_number" <?php echo $sortBy === 'pj_number' ? 'selected' : ''; ?>>P番号</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:4px; font-size:0.85rem;">順序</label>
                            <select name="dir" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; font-size:0.9rem;">
                                <option value="desc" <?php echo $sortDir === 'desc' ? 'selected' : ''; ?>>降順</option>
                                <option value="asc" <?php echo $sortDir === 'asc' ? 'selected' : ''; ?>>昇順</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:flex; gap:8px; justify-content:flex-end;">
                        <a href="troubles.php" class="btn" style="background:#f5f5f5;color:#333;padding:8px 20px;text-decoration:none;border-radius:6px;">クリア</a>
                        <button type="submit" class="btn btn-primary" style="padding:8px 20px;">適用</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
        document.getElementById('filterModal').addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
        </script>

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
                <table>
                    <thead>
                        <tr>
                            <?php if (canEdit()): ?>
                            <th style="width: 40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                            <?php endif; ?>
                            <th style="width: 80px;"><a href="<?= sortUrl('date') ?>" style="color:inherit;text-decoration:none;">日付<?= sortIcon('date') ?></a></th>
                            <th style="width: 150px;"><a href="<?= sortUrl('pj_number') ?>" style="color:inherit;text-decoration:none;">P番号<?= sortIcon('pj_number') ?></a></th>
                            <th>トラブル内容</th>
                            <th>対応内容</th>
                            <th style="width: 80px;"><a href="<?= sortUrl('reporter') ?>" style="color:inherit;text-decoration:none;">記入者<?= sortIcon('reporter') ?></a></th>
                            <th style="width: 80px;"><a href="<?= sortUrl('responder') ?>" style="color:inherit;text-decoration:none;">対応者<?= sortIcon('responder') ?></a></th>
                            <th style="width: 100px;"><a href="<?= sortUrl('status') ?>" style="color:inherit;text-decoration:none;">状態<?= sortIcon('status') ?></a></th>
                            <th style="width: 100px;">お客様</th>
                            <th style="width: 80px;">操作</th>
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
                            <tr>
                                <?php if (canEdit()): ?>
                                <td><input type="checkbox" class="trouble-checkbox" value="<?php echo $trouble['id']; ?>" onchange="updateBulkBar()"></td>
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
                                            <span style="color: #f44336;">
                                                <?php echo htmlspecialchars($pjNumber); ?>
                                            </span>
                                            <br><small style="color:#f44336;">未登録</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($trouble['case_no'])): ?>
                                        <br><small style="color:#666;"><?php echo htmlspecialchars($trouble['case_no']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo nl2br(htmlspecialchars($trouble['trouble_content'] ?? '')); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($trouble['response_content'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($trouble['reporter'] ?? ''); ?></td>
                                <td>
                                    <?php if (canEdit()): ?>
                                        <form method="POST" style="margin: 0;">
                                            <?= csrfTokenField() ?>
                                            <input type="hidden" name="change_responder" value="1">
                                            <input type="hidden" name="trouble_id" value="<?php echo $trouble['id']; ?>">
                                            <select name="new_responder" class="responder-select" onchange="this.form.submit()" style="padding: 4px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; width: 100%; background: white;">
                                                <option value="">未設定</option>
                                                <?php foreach ($responders as $r): ?>
                                                    <option value="<?php echo htmlspecialchars($r); ?>" <?php echo ($trouble['responder'] ?? '') === $r ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($r); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($trouble['responder'] ?? ''); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (canEdit()): ?>
                                        <form method="POST" style="margin: 0;">
                                            <?= csrfTokenField() ?>
                                            <input type="hidden" name="change_status" value="1">
                                            <input type="hidden" name="trouble_id" value="<?php echo $trouble['id']; ?>">
                                            <select name="new_status" class="status-select <?php echo $statusClass; ?>" onchange="this.form.submit()">
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
                                    <a href="../forms/trouble-form.php?id=<?php echo $trouble['id']; ?>" class="btn-edit">編集</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php if (canEdit()): ?>
<!-- 一括変更フローティングバー -->
<div id="bulkActionBar" style="display:none; position:fixed; bottom:0; left:0; right:0; background:#1e293b; color:white; padding:12px 24px; z-index:9999; box-shadow:0 -4px 12px rgba(0,0,0,0.2); display:none; align-items:center; justify-content:center; gap:16px;">
    <span id="bulkSelectedCount" style="font-weight:600;">0件選択中</span>
    <button type="button" class="btn btn-primary" onclick="openBulkModal()" style="padding:6px 20px;">一括変更</button>
    <button type="button" class="btn" onclick="clearSelection()" style="background:#475569; color:white; padding:6px 16px;">選択解除</button>
</div>

<!-- 一括変更モーダル -->
<div id="bulkModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:10001; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:12px; padding:24px; max-width:400px; width:90%; box-shadow:0 8px 24px rgba(0,0,0,0.2);">
        <h3 style="margin:0 0 16px; font-size:1.1rem;">一括変更</h3>
        <form method="POST" id="bulkChangeForm">
            <?= csrfTokenField() ?>
            <input type="hidden" name="bulk_change" value="1">
            <div id="bulkIdsContainer"></div>

            <div style="margin-bottom:16px;">
                <label style="display:block; font-weight:600; margin-bottom:6px; font-size:0.9rem;">対応者</label>
                <select name="bulk_responder" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; font-size:0.9rem;">
                    <option value="__no_change__">変更しない</option>
                    <option value="">未設定</option>
                    <?php foreach ($responders as $r): ?>
                        <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-weight:600; margin-bottom:6px; font-size:0.9rem;">状態</label>
                <select name="bulk_status" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; font-size:0.9rem;">
                    <option value="__no_change__">変更しない</option>
                    <option value="未対応">未対応</option>
                    <option value="対応中">対応中</option>
                    <option value="保留">保留</option>
                    <option value="完了">完了</option>
                </select>
            </div>

            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button type="button" class="btn" onclick="closeBulkModal()" style="background:#f5f5f5; color:#333; padding:8px 20px;">キャンセル</button>
                <button type="submit" class="btn btn-primary" style="padding:8px 20px;">変更を適用</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSelectAll(el) {
    document.querySelectorAll('.trouble-checkbox').forEach(cb => { cb.checked = el.checked; });
    updateBulkBar();
}

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

function clearSelection() {
    document.querySelectorAll('.trouble-checkbox').forEach(cb => { cb.checked = false; });
    document.getElementById('selectAll').checked = false;
    updateBulkBar();
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

document.getElementById('bulkModal').addEventListener('click', function(e) {
    if (e.target === this) closeBulkModal();
});
</script>
<?php endif; ?>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
</body>
</html>
