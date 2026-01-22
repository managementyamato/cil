<?php
/**
 * トラブル対応一覧ページ
 */
require_once '../api/auth.php';
require_once '../functions/notification-functions.php';

$data = getData();
$troubles = $data['troubles'] ?? array();

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
        header('Location: troubles.php?status_updated=1');
        exit;
    }
}

$troubles = $data['troubles'] ?? array();

// 降順に並び替え（新しいものが上）
usort($troubles, function($a, $b) {
    $dateA = strtotime($a['date'] ?? '1970-01-01');
    $dateB = strtotime($b['date'] ?? '1970-01-01');
    return $dateB - $dateA;
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
        return $pjNumber === $filterPjNumber;
    });
}

if (!empty($searchKeyword)) {
    $troubles = array_filter($troubles, function($t) use ($searchKeyword) {
        return stripos($t['trouble_content'] ?? '', $searchKeyword) !== false
            || stripos($t['response_content'] ?? '', $searchKeyword) !== false
            || stripos($t['project_name'] ?? '', $searchKeyword) !== false
            || stripos($t['company_name'] ?? '', $searchKeyword) !== false;
    });
}

// ユニークな記入者・対応者リスト
$reporters = array();
$responders = array();
foreach ($data['troubles'] ?? array() as $t) {
    if (!empty($t['reporter'])) $reporters[] = $t['reporter'];
    if (!empty($t['responder'])) $responders[] = $t['responder'];
}
$reporters = array_unique($reporters);
$responders = array_unique($responders);
sort($reporters);
sort($responders);
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
                <?php if (isAdmin()): ?>
                    <a href="/pages/sync-troubles.php" class="btn btn-success">スプシ同期</a>
                <?php endif; ?>
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

        <div class="filters">
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>キーワード検索</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="トラブル内容、現場名など">
                    </div>
                    <div class="filter-group">
                        <label>状態</label>
                        <select name="status">
                            <option value="">すべて</option>
                            <option value="未対応" <?php echo $filterStatus === '未対応' ? 'selected' : ''; ?>>未対応</option>
                            <option value="対応中" <?php echo $filterStatus === '対応中' ? 'selected' : ''; ?>>対応中</option>
                            <option value="保留" <?php echo $filterStatus === '保留' ? 'selected' : ''; ?>>保留</option>
                            <option value="完了" <?php echo $filterStatus === '完了' ? 'selected' : ''; ?>>完了</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>記入者</label>
                        <select name="reporter">
                            <option value="">すべて</option>
                            <?php foreach ($reporters as $reporter): ?>
                                <option value="<?php echo htmlspecialchars($reporter); ?>" <?php echo $filterReporter === $reporter ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($reporter); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>対応者</label>
                        <select name="responder">
                            <option value="">すべて</option>
                            <?php foreach ($responders as $responder): ?>
                                <option value="<?php echo htmlspecialchars($responder); ?>" <?php echo $filterResponder === $responder ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($responder); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">フィルター適用</button>
                <a href="troubles.php" class="btn" style="background:#f5f5f5;color:#333;">クリア</a>
            </form>
        </div>

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
                            <th style="width: 80px;">日付</th>
                            <th style="width: 150px;">P番号</th>
                            <th>トラブル内容</th>
                            <th>対応内容</th>
                            <th style="width: 80px;">記入者</th>
                            <th style="width: 80px;">対応者</th>
                            <th style="width: 100px;">状態</th>
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
                                <td><?php echo htmlspecialchars($trouble['date'] ?? ''); ?></td>
                                <td>
                                    <?php
                                    $pjNumber = $trouble['pj_number'] ?? $trouble['project_name'] ?? '';
                                    $projectInfo = null;

                                    if (!empty($pjNumber)):
                                        // P番号でプロジェクトマスタを検索
                                        foreach ($data['projects'] ?? array() as $proj) {
                                            if ($proj['id'] === $pjNumber) {
                                                $projectInfo = $proj;
                                                break;
                                            }
                                        }
                                    ?>
                                        <?php if ($projectInfo): ?>
                                            <a href="/pages/master.php?project=<?php echo urlencode($pjNumber); ?>"
                                               style="color: #2196F3; text-decoration: none; font-weight: bold;">
                                                <?php echo htmlspecialchars($pjNumber); ?>
                                            </a>
                                            <br><small style="color:#666;"><?php echo htmlspecialchars($projectInfo['name'] ?? ''); ?></small>
                                        <?php else: ?>
                                            <span style="color: #f44336; font-weight: bold;">
                                                <?php echo htmlspecialchars($pjNumber); ?>
                                            </span>
                                            <br><small style="color:#f44336;">未登録</small>
                                            <?php if (canEdit()): ?>
                                                <br><a href="/pages/master.php?new_from_trouble=<?php echo urlencode($pjNumber); ?>"
                                                       style="font-size: 11px; color: #2196F3; text-decoration: underline;">
                                                    現場を登録
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($trouble['case_no'])): ?>
                                        <br><small style="color:#666;"><?php echo htmlspecialchars($trouble['case_no']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo nl2br(htmlspecialchars($trouble['trouble_content'] ?? '')); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($trouble['response_content'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($trouble['reporter'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($trouble['responder'] ?? ''); ?></td>
                                <td>
                                    <?php if (canEdit()): ?>
                                        <form method="POST" style="margin: 0;">
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
</body>
</html>
