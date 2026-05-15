<?php
/**
 * トラブル対応一覧ページ
 */
require_once '../api/auth.php';
require_once '../functions/api-middleware.php';
require_once '../functions/soft-delete.php';
// api-middleware.phpのエラーハンドラはAPIファイル専用のため、ページファイルではリセット
set_error_handler(null);
set_exception_handler(null);

// トラブルステータス定義（一元管理 - ここだけを編集すれば全箇所に反映される）
$TROUBLE_STATUSES = ['未対応', '対応中', '保留', '完了'];

// セキュリティヘッダーを設定（HTML出力前に実行）
setSecurityHeaders();

// ─────────────────────────────────────────
// 一括削除（master.php と同じ form-POST パターン）
// API 経由ではなくページ自身が処理し、リダイレクトで完了
// ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    verifyCsrfToken();
    if (!isAdmin()) {
        header('Location: /pages/troubles?error=no_delete_permission');
        exit;
    }

    $deleteIds = $_POST['trouble_ids'] ?? [];
    if (empty($deleteIds) || !is_array($deleteIds)) {
        header('Location: /pages/troubles?error=no_selection');
        exit;
    }

    try {
        $data = getData();
        $troublesAll = $data['troubles'] ?? [];

        $deletedCount = 0;
        $deletedRows  = [];
        foreach ($deleteIds as $did) {
            $deleted = softDelete($troublesAll, $did);
            if ($deleted) {
                $deletedCount++;
                $deletedRows[] = $deleted;
            }
        }

        if ($deletedCount > 0) {
            foreach ($deletedRows as $row) {
                saveEntityRow('troubles', $row);
            }
            writeAuditLog('bulk_delete', 'troubles', "トラブル一括削除: {$deletedCount}件", [
                'deleted_ids' => $deleteIds
            ]);
        }

        header("Location: /pages/troubles?bulk_deleted={$deletedCount}");
        exit;
    } catch (\Throwable $e) {
        error_log('[troubles.bulk_delete] ' . $e->getMessage());
        header('Location: /pages/troubles?error=' . urlencode($e->getMessage()));
        exit;
    }
}

$data = getData();
$troubles = $data['troubles'] ?? array();

$troubles = filterDeleted($data['troubles'] ?? array());

// 日付フォーマット統一（ハイフン→スラッシュ）
foreach ($troubles as &$t) {
    if (!empty($t['date'])) {
        $t['date'] = str_replace('-', '/', $t['date']);
    }
}
unset($t);

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
        .trouble-table tr.detail-row:hover {
            background: #f0f7ff;
            cursor: pointer;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending { background: var(--danger-light); color: #C62828; }
        .status-in-progress { background: var(--warning-light); color: #E65100; }
        .status-onhold { background: var(--purple-light); color: #6A1B9A; }
        .status-resolved { background: var(--success-light); color: #2E7D32; }
        .status-other { background: var(--gray-100); color: var(--gray-700); }
        .status-select {
            padding: 6px 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .status-select.status-pending { background: var(--danger-light); color: #C62828; border-color: var(--danger); }
        .status-select.status-in-progress { background: var(--warning-light); color: #E65100; border-color: var(--warning); }
        .status-select.status-onhold { background: var(--purple-light); color: #6A1B9A; border-color: var(--purple); }
        .status-select.status-resolved { background: var(--success-light); color: #2E7D32; border-color: var(--success); }
        .status-select:hover { opacity: 0.8; }
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
        .btn-edit:hover { background: var(--primary-dark); }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .detail-label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            margin-bottom: 4px;
        }
        .detail-box {
            background: #f5f5f5;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <?php include '../functions/header.php'; ?>

    <div class="page-container">
        <div class="page-header mb-2">
            <h2>トラブル対応一覧</h2>

            <?php if (isset($_GET['bulk_deleted'])): ?>
                <div class="alert alert-success">
                    <?= (int)$_GET['bulk_deleted'] ?>件のトラブル対応を削除しました
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars(urldecode($_GET['error'])) ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="header-buttons">
                <?php if (canEdit()): ?>
                <a href="/forms/trouble-bulk-form.php" class="btn btn-primary">新規登録</a>
                <?php endif; ?>
                <?php if (canEdit()): ?>
                    <a href="/pages/download-troubles-csv.php?status=<?= urlencode($filterStatus) ?>&pj_number=<?= urlencode($filterPjNumber) ?>&search=<?= urlencode($searchKeyword) ?>" class="btn btn-secondary">CSVダウンロード</a>
                <?php endif; ?>
                <button type="button"         class="btn bg-f5" id="filterButton">
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
        $activeTroubles = filterDeleted($data['troubles'] ?? array());
        $totalCount = count($activeTroubles);
        $pendingCount = count(array_filter($activeTroubles, function($t) {
            return ($t['status'] ?? '') === '未対応';
        }));
        $inProgressCount = count(array_filter($activeTroubles, function($t) {
            return ($t['status'] ?? '') === '対応中';
        }));
        $onHoldCount = count(array_filter($activeTroubles, function($t) {
            return ($t['status'] ?? '') === '保留';
        }));
        $completedCount = count(array_filter($activeTroubles, function($t) {
            return ($t['status'] ?? '') === '完了';
        }));
        $completionRate = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0;

        // 担当者別の対応割合（マスタから動的に取得）
        $responderCounts = [];
        $allTroubles = $activeTroubles;
        foreach ($troubleRespondersMaster as $responderName) {
            $responderCounts[$responderName] = count(array_filter($allTroubles, function($t) use ($responderName) {
                return ($t['responder'] ?? '') === $responderName;
            }));
        }
        // 対応割合は足本・曽我部のみ表示
        $displayResponders = ['足本', '曽我部'];
        $responderCounts = array_filter($responderCounts, fn($name) => in_array($name, $displayResponders), ARRAY_FILTER_USE_KEY);
        $responderTotal = array_sum($responderCounts);
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
                <?php if (!empty($responderCounts)): ?>
                <div  class="d-flex align-center gap-3 mb-1 flex-wrap">
                    <?php
                    $barColors = ['#555', '#999', '#777', '#aaa', '#333', '#bbb'];
                    $colorIndex = 0;
                    foreach ($responderCounts as $rName => $rCount):
                        $rRate = $responderTotal > 0 ? round(($rCount / $responderTotal) * 100, 1) : 0;
                    ?>
                    <span     class="text-085"><?php echo htmlspecialchars($rName); ?> <strong     class="text-base"><?php echo $rCount; ?>件</strong> <span   class="text-gray-666">(<?php echo $rRate; ?>%)</span></span>
                    <?php $colorIndex++; endforeach; ?>
                    <span     class="text-13 text-999">計<?php echo $responderTotal; ?>件</span>
                </div>
                <?php if ($responderTotal > 0): ?>
                <div        class="rounded max-w-400" style="background: #e0e0e0; height: 10px; overflow: hidden">
                    <?php
                    $colorIndex = 0;
                    foreach ($responderCounts as $rName => $rCount):
                        $rRate = $responderTotal > 0 ? round(($rCount / $responderTotal) * 100, 1) : 0;
                        $barColor = $barColors[$colorIndex % count($barColors)];
                    ?>
                    <div     style="background: <?php echo htmlspecialchars($barColor); ?>; height: 100%; width: <?php echo $rRate; ?>%; float: left"></div>
                    <?php $colorIndex++; endforeach; ?>
                </div>
                <?php endif; ?>
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
        <div id="filterModal" class="modal">
            <div class="modal-content" style="max-width:480px;">
                <div class="modal-header">
                    <h3>フィルター・並び替え</h3>
                    <button type="button" class="modal-close modal-close-btn">&times;</button>
                </div>
                <form method="GET">
                    <?php if (!empty($filterPjNumber)): ?><input type="hidden" name="pj_number" value="<?= htmlspecialchars($filterPjNumber) ?>"><?php endif; ?>
                    <?php if (!empty($searchKeyword)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($searchKeyword) ?>"><?php endif; ?>
                    <div class="modal-body">
                        <div class="grid grid-cols-2 gap-075">
                            <div class="form-group">
                                <label class="form-label">状態</label>
                                <select name="status" class="form-input">
                                    <option value="">すべて</option>
                                    <?php foreach ($TROUBLE_STATUSES as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">記入者</label>
                                <select name="reporter" class="form-input">
                                    <option value="">すべて</option>
                                    <?php foreach ($reporters as $reporter): ?>
                                        <option value="<?php echo htmlspecialchars($reporter); ?>" <?php echo $filterReporter === $reporter ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($reporter); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">対応者</label>
                                <select name="responder" class="form-input">
                                    <option value="">すべて</option>
                                    <?php foreach ($responders as $responder): ?>
                                        <option value="<?php echo htmlspecialchars($responder); ?>" <?php echo $filterResponder === $responder ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($responder); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">並び替え</label>
                                <select name="sort" class="form-input">
                                    <option value="date" <?php echo $sortBy === 'date' ? 'selected' : ''; ?>>日付</option>
                                    <option value="responder" <?php echo $sortBy === 'responder' ? 'selected' : ''; ?>>対応者</option>
                                    <option value="reporter" <?php echo $sortBy === 'reporter' ? 'selected' : ''; ?>>記入者</option>
                                    <option value="status" <?php echo $sortBy === 'status' ? 'selected' : ''; ?>>状態</option>
                                    <option value="pj_number" <?php echo $sortBy === 'pj_number' ? 'selected' : ''; ?>>P番号</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">順序</label>
                                <select name="dir" class="form-input">
                                    <option value="desc" <?php echo $sortDir === 'desc' ? 'selected' : ''; ?>>降順</option>
                                    <option value="asc" <?php echo $sortDir === 'asc' ? 'selected' : ''; ?>>昇順</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="troubles.php" class="btn btn-secondary">クリア</a>
                        <button type="submit" class="btn btn-primary">適用</button>
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
                    <h3>トラブル対応データがありません</h3>
                    <p>新規登録してください</p>
                </div>
            </div>
        <?php else: ?>
            <!-- 一括削除用の隠しフォーム (テーブル内に form を入れ子にできないため外出し) -->
            <form id="bulkDeleteForm" method="POST" action="/pages/troubles" class="d-none">
                <?= csrfTokenField() ?>
                <input type="hidden" name="bulk_delete" value="1">
                <div id="bulkDeleteIdsContainer"></div>
            </form>

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
                            <tr id="trouble-<?= $trouble['id'] ?>" class="detail-row" data-trouble='<?= json_encode($trouble, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>'>
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
                                <td><?php echo htmlspecialchars($trouble['responder'] ?? ''); ?></td>
                                <td>
                                    <?php if (canEdit()): ?>
                                        <select class="status-select <?php echo $statusClass; ?>" data-trouble-id="<?php echo htmlspecialchars($trouble['id']); ?>">
                                            <?php foreach ($TROUBLE_STATUSES as $s): ?>
                                            <option value="<?= htmlspecialchars($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                            <?php endforeach; ?>
                                        </select>
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

<script<?= nonceAttr() ?>>
// 詳細モーダル（全ユーザー共通）
let currentDetailTrouble = null;

function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function openDetailModal(trouble) {
    currentDetailTrouble = trouble;

    const statusClassMap = {
        '未対応': 'status-pending',
        '対応中': 'status-in-progress',
        '保留': 'status-onhold',
        '完了': 'status-resolved'
    };
    const badge = document.getElementById('detailStatusBadge');
    badge.textContent = trouble.status || '';
    badge.className = 'status-badge ' + (statusClassMap[trouble.status] || 'status-other');

    const metaItems = [
        trouble.date,
        trouble.deadline ? '期限: ' + trouble.deadline : '',
        trouble.pj_number || trouble.project_name || ''
    ].filter(Boolean);

    let html = `<div class="mb-2 d-flex flex-wrap gap-2 text-13 text-gray-666">${metaItems.map(escapeHtml).join('<span class="mx-05 text-gray-300">|</span>')}</div>`;

    html += `<div class="mb-2"><div class="detail-label">トラブル内容</div><div class="detail-box">${escapeHtml(trouble.trouble_content || '')}</div></div>`;

    if (trouble.response_content) {
        html += `<div class="mb-2"><div class="detail-label">対応内容</div><div class="detail-box">${escapeHtml(trouble.response_content)}</div></div>`;
    }

    if (trouble.prevention_notes) {
        html += `<div class="mb-2"><div class="detail-label">再発防止策</div><div class="detail-box">${escapeHtml(trouble.prevention_notes)}</div></div>`;
    }

    const customer = (trouble.company_name || '') + (trouble.customer_name ? (trouble.company_name ? '<br>' : '') + escapeHtml(trouble.customer_name + (trouble.honorific || '様')) : '');
    html += `<div class="mb-2 grid grid-cols-3 gap-075">
        <div><div class="detail-label">記入者</div><div>${escapeHtml(trouble.reporter || '-')}</div></div>
        <div><div class="detail-label">対応者</div><div>${escapeHtml(trouble.responder || '-')}</div></div>
        <div><div class="detail-label">お客様</div><div>${escapeHtml(trouble.company_name || '')}${trouble.customer_name ? (trouble.company_name ? '<br>' : '') + escapeHtml(trouble.customer_name + (trouble.honorific || '様')) : ''}</div></div>
    </div>`;

    if (trouble.call_no || trouble.case_no) {
        html += `<div class="mb-2 d-flex gap-3">
            ${trouble.call_no ? `<div><div class="detail-label">コールNo</div><div>${escapeHtml(trouble.call_no)}</div></div>` : ''}
            ${trouble.case_no ? `<div><div class="detail-label">案件No</div><div>${escapeHtml(trouble.case_no)}</div></div>` : ''}
        </div>`;
    }

    document.getElementById('detailContent').innerHTML = html;
    document.getElementById('detailModal').classList.add('active');
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function() {
    // 行クリックで詳細モーダルを開く
    document.querySelectorAll('.detail-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.closest('input, select, button, a')) return;
            const troubleData = this.getAttribute('data-trouble');
            if (troubleData) openDetailModal(JSON.parse(troubleData));
        });
    });

    document.getElementById('closeDetailModalBtn')?.addEventListener('click', closeDetailModal);
    document.getElementById('detailCloseBtn')?.addEventListener('click', closeDetailModal);

    document.getElementById('detailEditBtn')?.addEventListener('click', function() {
        closeDetailModal();
        if (currentDetailTrouble) openEditModal(currentDetailTrouble);
    });
});
</script>

<!-- 詳細モーダル（全ユーザー表示可） -->
<div id="detailModal" class="modal">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <div class="d-flex align-center gap-2">
                <h3>トラブル対応詳細</h3>
                <span id="detailStatusBadge" class="status-badge"></span>
            </div>
            <button type="button" id="closeDetailModalBtn" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="detailContent"></div>
        <div class="modal-footer">
            <button type="button" id="detailCloseBtn" class="btn btn-secondary">閉じる</button>
            <?php if (canEdit()): ?>
            <button type="button" id="detailEditBtn" class="btn btn-primary">編集</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (canEdit()): ?>
<!-- 編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content" style="max-width:700px;">
        <form id="editForm" method="POST">
            <div class="modal-header">
                <h3>トラブル対応編集</h3>
                <button type="button" id="closeEditModalBtn" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <?= csrfTokenField() ?>
                <input type="hidden" name="modal_edit" value="1">
                <input type="hidden" name="edit_id" id="edit_id">

                <div class="grid grid-cols-3 gap-075">
                    <div class="form-group">
                        <label class="form-label">日付<span class="required">*</span></label>
                        <input type="text" name="edit_date" id="edit_date" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">対応期限</label>
                        <input type="date" name="edit_deadline" id="edit_deadline" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">コールNo</label>
                        <input type="text" name="edit_call_no" id="edit_call_no" class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">P番号<span class="required">*</span></label>
                    <input type="text" name="edit_pj_number" id="edit_pj_number" required list="edit_pj_list" class="form-input">
                    <datalist id="edit_pj_list">
                        <?php foreach ($data['projects'] ?? array() as $proj): ?>
                            <option value="<?= htmlspecialchars($proj['id']) ?>"><?= htmlspecialchars($proj['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label class="form-label">トラブル内容<span class="required">*</span></label>
                    <textarea name="edit_trouble_content" id="edit_trouble_content" required rows="3" class="form-input"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">対応内容</label>
                    <textarea name="edit_response_content" id="edit_response_content" rows="3" class="form-input"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">再発防止策</label>
                    <textarea name="edit_prevention_notes" id="edit_prevention_notes" rows="2" class="form-input"></textarea>
                </div>

                <?php
                $allReporters = array_filter($troubleRespondersMaster, fn($n) => !empty($n));
                $allResponders = $responders;
                sort($allReporters);
                ?>
                <div class="grid grid-cols-3 gap-075">
                    <div class="form-group">
                        <label class="form-label">記入者<span class="required">*</span></label>
                        <?php if (empty($allReporters)): ?>
                            <input type="text" name="edit_reporter" id="edit_reporter" required placeholder="名前を入力" class="form-input">
                        <?php else: ?>
                        <select name="edit_reporter" id="edit_reporter" required class="form-input">
                            <option value="">選択してください</option>
                            <?php foreach ($allReporters as $name): if (empty($name)) continue; ?>
                                <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">対応者<span class="required">*</span></label>
                        <?php if (empty($allResponders)): ?>
                            <input type="text" name="edit_responder" id="edit_responder" required placeholder="名前を入力" class="form-input">
                        <?php else: ?>
                        <select name="edit_responder" id="edit_responder" required class="form-input">
                            <option value="">選択してください</option>
                            <?php foreach ($allResponders as $name): if (empty($name)) continue; ?>
                                <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">状態<span class="required">*</span></label>
                        <select name="edit_status" id="edit_status" required class="form-input">
                            <?php foreach ($TROUBLE_STATUSES as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-075">
                    <div class="form-group">
                        <label class="form-label">案件No</label>
                        <input type="text" name="edit_case_no" id="edit_case_no" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">社名</label>
                        <input type="text" name="edit_company_name" id="edit_company_name" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">お客様お名前</label>
                        <input type="text" name="edit_customer_name" id="edit_customer_name" class="form-input">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary cancel-edit-btn">キャンセル</button>
                <button type="submit" class="btn btn-success">更新</button>
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
<div id="bulkModal" class="modal">
    <div class="modal-content max-w-400">
        <form method="POST" id="bulkChangeForm">
            <div class="modal-header">
                <h3>一括変更</h3>
                <button type="button" class="modal-close cancel-bulk-btn">&times;</button>
            </div>
            <div class="modal-body">
                <?= csrfTokenField() ?>
                <input type="hidden" name="bulk_change" value="1">
                <div id="bulkIdsContainer"></div>

                <div class="form-group">
                    <label class="form-label">対応者</label>
                    <?php if (empty($responders)): ?>
                        <input type="text" name="bulk_responder" placeholder="対応者名を入力（空欄で変更しない）" value="__no_change__" onfocus="if(this.value==='__no_change__')this.value=''" class="form-input">
                    <?php else: ?>
                    <select name="bulk_responder" class="form-input">
                        <option value="__no_change__">変更しない</option>
                        <option value="">未設定</option>
                        <?php foreach ($responders as $r): ?>
                            <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">状態</label>
                    <select name="bulk_status" class="form-input">
                        <option value="__no_change__">変更しない</option>
                        <?php foreach ($TROUBLE_STATUSES as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary cancel-bulk-btn">キャンセル</button>
                <button type="submit" class="btn btn-primary">変更を適用</button>
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
            document.getElementById('filterModal').classList.add('active');
        });
    }

    // フィルターモーダルの閉じるボタン（背景クリックでは閉じない）
    const filterModal = document.getElementById('filterModal');
    if (filterModal) {
        const filterCloseBtn = filterModal.querySelector('.modal-close-btn');
        if (filterCloseBtn) {
            filterCloseBtn.addEventListener('click', function() {
                filterModal.classList.remove('active');
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

    // ステータス変更セレクト（fetch送信）
    document.querySelectorAll('.status-select').forEach(select => {
        select.addEventListener('change', async function() {
            const troubleId = this.dataset.troubleId;
            const newStatus = this.value;
            const csrfToken = document.querySelector('#bulkDeleteForm [name="csrf_token"]').value;
            const body = new URLSearchParams({
                action: 'change_status',
                trouble_id: troubleId,
                new_status: newStatus,
                csrf_token: csrfToken
            });
            try {
                const d = await (await fetch('/api/troubles.php', { method: 'POST', body })).json();
                if (d.success) {
                    location.reload();
                } else {
                    alert('エラー: ' + (d.error || '変更に失敗しました'));
                }
            } catch {
                alert('通信エラーが発生しました');
            }
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

    // 編集フォーム（fetch送信）
    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.set('action', 'modal_edit');
            try {
                const d = await (await fetch('/api/troubles.php', { method: 'POST', body: new URLSearchParams(formData) })).json();
                if (d.success) {
                    location.reload();
                } else if (d.errors) {
                    alert('入力エラー:\n' + d.errors.join('\n'));
                } else {
                    alert('エラー: ' + (d.error || '更新に失敗しました'));
                }
            } catch {
                alert('通信エラーが発生しました');
            }
        });
    }

    // 編集モーダルの閉じるボタン
    const closeEditModalBtn = document.getElementById('closeEditModalBtn');
    if (closeEditModalBtn) {
        closeEditModalBtn.addEventListener('click', closeEditModal);
    }

    const cancelEditBtns = document.querySelectorAll('.cancel-edit-btn');
    cancelEditBtns.forEach(btn => {
        btn.addEventListener('click', closeEditModal);
    });

    // 背景クリックでは閉じない（×ボタン・キャンセルのみで閉じる）

    // 一括変更ボタン
    const openBulkModalBtn = document.getElementById('openBulkModalBtn');
    if (openBulkModalBtn) {
        openBulkModalBtn.addEventListener('click', openBulkModal);
    }

    // 一括変更フォーム（fetch送信）
    const bulkChangeForm = document.getElementById('bulkChangeForm');
    if (bulkChangeForm) {
        bulkChangeForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.set('action', 'bulk_change');
            try {
                const d = await (await fetch('/api/troubles.php', { method: 'POST', body: new URLSearchParams(formData) })).json();
                if (d.success) {
                    location.reload();
                } else {
                    alert('エラー: ' + (d.error || '変更に失敗しました'));
                    closeBulkModal();
                }
            } catch {
                alert('通信エラーが発生しました');
            }
        });
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

    // 背景クリックでは閉じない（×ボタン・キャンセルのみで閉じる）


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
    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
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
    document.getElementById('bulkModal').classList.add('active');
}

function closeBulkModal() {
    document.getElementById('bulkModal').classList.remove('active');
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
    // テーブル外の隠しフォームに id 群を流し込んで submit
    const container = document.getElementById('bulkDeleteIdsContainer');
    container.innerHTML = '';
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'trouble_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    document.getElementById('bulkDeleteForm').submit();
}
<?php endif; ?>
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
