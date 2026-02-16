<?php
/**
 * 操作ログ閲覧画面（管理者専用）
 */
require_once '../api/auth.php';

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

// フィルター
$filters = [
    'action' => $_GET['action'] ?? '',
    'target' => $_GET['target'] ?? '',
    'user' => $_GET['user'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];
$page = max(1, intval($_GET['page'] ?? 1));

$result = getFilteredAuditLogs($filters, $page, 50);

// アクション種別ラベル
$actionLabels = [
    'create' => ['作成', '#22c55e'],
    'update' => ['更新', '#3b82f6'],
    'delete' => ['削除', '#ef4444'],
    'bulk_update' => ['一括変更', '#8b5cf6'],
    'login' => ['ログイン', '#6b7280'],
    'settings' => ['設定変更', '#f59e0b'],
    'import' => ['インポート', '#06b6d4'],
    'export' => ['エクスポート', '#84cc16'],
    'rename' => ['リネーム', '#ec4899']
];

// ターゲットラベル
$targetLabels = [
    'project' => 'プロジェクト',
    'trouble' => 'トラブル',
    'employee' => '従業員',
    'settings' => '設定',
    'auth' => '認証',
    'finance' => '財務',
    'loan' => '借入金',
    'drive' => 'Google Drive',
    'alcohol' => 'アルコールチェック'
];

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
/* 設定詳細ヘッダー */
.settings-detail-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.settings-detail-header h2 {
    margin: 0;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.audit-count {
    font-size: 0.8rem;
    color: var(--gray-500);
    font-weight: normal;
}
/* フィルター */
.audit-filters {
    background: white;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    align-items: end;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.filter-group label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: uppercase;
}
.filter-group select, .filter-group input {
    padding: 0.4rem 0.6rem;
    border: 1px solid var(--gray-300);
    border-radius: 6px;
    font-size: 0.8rem;
}
.filter-group select { min-width: 100px; }
.filter-group input[type="text"] { width: 140px; }
.filter-group input[type="date"] { width: 140px; }
.btn-filter {
    background: var(--primary);
    color: white;
    border: none;
    padding: 0.4rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
}
.btn-filter:hover { opacity: 0.9; }
.btn-clear {
    background: var(--gray-100);
    color: var(--gray-600);
    border: none;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
}
.btn-clear:hover { background: var(--gray-200); }

/* ログテーブル */
.audit-table-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}
.audit-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}
.audit-table th {
    text-align: left;
    padding: 0.6rem 0.75rem;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.7rem;
    text-transform: uppercase;
    border-bottom: 2px solid var(--gray-200);
}
.audit-table td {
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.audit-table tr:hover td {
    background: var(--gray-50);
}
.log-timestamp {
    color: var(--gray-500);
    font-size: 0.75rem;
    white-space: nowrap;
}
.log-user {
    display: flex;
    flex-direction: column;
}
.log-user-name {
    font-weight: 500;
    color: var(--gray-800);
}
.log-user-email {
    font-size: 0.7rem;
    color: var(--gray-400);
}
.log-action-badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    color: white;
}
.log-target {
    font-size: 0.75rem;
    color: var(--gray-500);
    background: var(--gray-100);
    padding: 0.15rem 0.4rem;
    border-radius: 3px;
}
.log-description {
    max-width: 350px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ページネーション */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
}
.pagination a, .pagination span {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    text-decoration: none;
    color: var(--gray-700);
}
.pagination a:hover { background: var(--gray-100); }
.pagination .current {
    background: var(--primary);
    color: white;
}
.empty-log {
    text-align: center;
    padding: 3rem;
    color: var(--gray-400);
}
</style>

<div class="page-container">
<div class="settings-detail-header">
    <a href="settings.php" class="btn btn-secondary btn-sm">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        一覧に戻る
    </a>
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="w-24 h-24">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
        </svg>
        操作ログ
        <span class="audit-count">(<?= number_format($result['total']) ?>件)</span>
    </h2>
</div>

    <!-- フィルター -->
    <form class="audit-filters" method="get">
        <div class="filter-group">
            <label>操作種別</label>
            <select name="action">
                <option value="">すべて</option>
                <?php foreach ($actionLabels as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filters['action'] === $key ? 'selected' : '' ?>><?= $label[0] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>対象</label>
            <select name="target">
                <option value="">すべて</option>
                <?php foreach ($targetLabels as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filters['target'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>ユーザー</label>
            <input type="text" name="user" value="<?= htmlspecialchars($filters['user']) ?>" placeholder="名前 or メール">
        </div>
        <div class="filter-group">
            <label>期間（開始）</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
        </div>
        <div class="filter-group">
            <label>期間（終了）</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">
        </div>
        <button type="submit" class="btn-filter">検索</button>
        <a href="audit-log.php" class="btn-clear">クリア</a>
    </form>

    <!-- ログテーブル -->
    <div class="audit-table-card">
        <?php if (empty($result['logs'])): ?>
        <div class="empty-log">操作ログがありません</div>
        <?php else: ?>
        <div    class="overflow-x-auto">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>日時</th>
                        <th>ユーザー</th>
                        <th>操作</th>
                        <th>対象</th>
                        <th>説明</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['logs'] as $log): ?>
                    <?php
                        $actionInfo = $actionLabels[$log['action']] ?? [$log['action'], '#6b7280'];
                        $targetLabel = $targetLabels[$log['target']] ?? $log['target'];
                    ?>
                    <tr>
                        <td class="log-timestamp"><?= htmlspecialchars($log['timestamp']) ?></td>
                        <td>
                            <div class="log-user">
                                <span class="log-user-name"><?= htmlspecialchars($log['user_name'] ?? '') ?></span>
                                <span class="log-user-email"><?= htmlspecialchars($log['user_email'] ?? '') ?></span>
                            </div>
                        </td>
                        <td>
                            <span         class="log-action-badge" style="background:<?= $actionInfo[1] ?>">
                                <?= htmlspecialchars($actionInfo[0]) ?>
                            </span>
                        </td>
                        <td><span class="log-target"><?= htmlspecialchars($targetLabel) ?></span></td>
                        <td class="log-description" title="<?= htmlspecialchars($log['description'] ?? '') ?>">
                            <?= htmlspecialchars($log['description'] ?? '') ?>
                        </td>
                        <td     class="text-07-gray"><?= htmlspecialchars($log['ip'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ページネーション -->
        <?php if ($result['total_pages'] > 1): ?>
        <div class="pagination">
            <?php
            $queryParams = $filters;
            if ($result['page'] > 1):
                $queryParams['page'] = $result['page'] - 1;
            ?>
            <a href="?<?= http_build_query($queryParams) ?>">&laquo; 前へ</a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $result['page'] - 3);
            $endPage = min($result['total_pages'], $result['page'] + 3);
            for ($p = $startPage; $p <= $endPage; $p++):
                $queryParams['page'] = $p;
                if ($p === $result['page']):
            ?>
            <span class="current"><?= $p ?></span>
            <?php else: ?>
            <a href="?<?= http_build_query($queryParams) ?>"><?= $p ?></a>
            <?php
                endif;
            endfor;
            ?>

            <?php
            if ($result['page'] < $result['total_pages']):
                $queryParams['page'] = $result['page'] + 1;
            ?>
            <a href="?<?= http_build_query($queryParams) ?>">次へ &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div><!-- /.page-container -->

<?php require_once '../functions/footer.php'; ?>
