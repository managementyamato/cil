<?php
require_once 'config.php';
$data = getData();

// 一括削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $deleteIds = $_POST['delete_ids'] ?? [];
    if (!empty($deleteIds)) {
        $data['troubles'] = array_values(array_filter($data['troubles'], function($t) use ($deleteIds) {
            return !in_array($t['id'], $deleteIds);
        }));
        saveData($data);
        header('Location: list.php?deleted=' . count($deleteIds));
        exit;
    }
}

// 単一削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    $data['troubles'] = array_values(array_filter($data['troubles'], fn($t) => $t['id'] !== $deleteId));
    saveData($data);
    header('Location: list.php?deleted=1');
    exit;
}

// フィルタリング
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$deviceFilter = $_GET['device'] ?? '';

$troubles = $data['troubles'];

if ($search) {
    $troubles = array_filter($troubles, function($t) use ($search) {
        return stripos($t['pjNumber'], $search) !== false ||
               stripos($t['pjName'] ?? '', $search) !== false ||
               stripos($t['content'], $search) !== false;
    });
}

if ($statusFilter) {
    $troubles = array_filter($troubles, fn($t) => $t['status'] === $statusFilter);
}

if ($deviceFilter) {
    $troubles = array_filter($troubles, fn($t) => $t['deviceType'] === $deviceFilter);
}

require_once 'header.php';
?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">
        <?= (int)$_GET['deleted'] ?>件のトラブルを削除しました
    </div>
<?php endif; ?>

<div class="filter-bar">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; width: 100%;">
        <input type="text" class="form-input" name="search" placeholder="🔍 PJ番号・内容で検索" value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 200px;">
        <select class="form-select" name="status" style="min-width: 150px;">
            <option value="">すべてのステータス</option>
            <option value="未対応" <?= $statusFilter === '未対応' ? 'selected' : '' ?>>未対応</option>
            <option value="対応中" <?= $statusFilter === '対応中' ? 'selected' : '' ?>>対応中</option>
            <option value="完了" <?= $statusFilter === '完了' ? 'selected' : '' ?>>完了</option>
        </select>
        <select class="form-select" name="device" style="min-width: 150px;">
            <option value="">すべての機器</option>
            <option value="モニたろう" <?= $deviceFilter === 'モニたろう' ? 'selected' : '' ?>>モニたろう</option>
            <option value="モニすけ" <?= $deviceFilter === 'モニすけ' ? 'selected' : '' ?>>モニすけ</option>
            <option value="モニまる" <?= $deviceFilter === 'モニまる' ? 'selected' : '' ?>>モニまる</option>
            <option value="モニんじゃ" <?= $deviceFilter === 'モニんじゃ' ? 'selected' : '' ?>>モニんじゃ</option>
            <option value="ゲンバルジャー" <?= $deviceFilter === 'ゲンバルジャー' ? 'selected' : '' ?>>ゲンバルジャー</option>
            <option value="その他" <?= $deviceFilter === 'その他' ? 'selected' : '' ?>>その他</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">検索</button>
    </form>
</div>

<!-- 一括削除フォーム -->
<form method="POST" id="bulk-delete-form">
    <input type="hidden" name="bulk_delete" value="1">

    <!-- 一括削除ボタン -->
    <div style="margin-bottom: 1rem; display: flex; gap: 0.5rem; align-items: center;">
        <button type="button" class="btn btn-secondary btn-sm" id="bulk-delete-btn" style="display: none;" onclick="confirmBulkDelete()">
            <span id="selected-count">0</span>件を削除
        </button>
        <button type="button" class="btn btn-secondary btn-sm" id="clear-selection-btn" style="display: none;" onclick="clearSelection()">
            選択解除
        </button>
    </div>

    <!-- Desktop Table -->
    <div class="card desktop-only">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="select-all-checkbox" onchange="toggleAllCheckboxes(this)">
                        </th>
                        <th>ID</th>
                        <th>PJ番号</th>
                        <th>機器</th>
                        <th>内容 / 解決方法</th>
                        <th>対応者</th>
                        <th>ステータス</th>
                        <th>登録日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($troubles as $t): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="trouble-checkbox" name="delete_ids[]" value="<?= $t['id'] ?>" onchange="updateBulkDeleteButton()">
                            </td>
                            <td>#<?= $t['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($t['pjNumber']) ?></strong><br>
                                <small><?= htmlspecialchars($t['pjName'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($t['deviceType']) ?></td>
                            <td style="max-width: 250px;">
                                <div style="margin-bottom: 0.5rem;">
                                    <strong>内容:</strong><br>
                                    <?= nl2br(htmlspecialchars($t['content'])) ?>
                                </div>
                                <?php if (!empty($t['solution'])): ?>
                                    <div style="color: var(--success);">
                                        <strong>解決:</strong><br>
                                        <?= nl2br(htmlspecialchars($t['solution'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($t['assignee'] ?? '-') ?></td>
                            <td>
                                <?php
                                $statusClass = match($t['status']) {
                                    '未対応' => 'status-pending',
                                    '対応中' => 'status-in-progress',
                                    '完了' => 'status-completed',
                                    default => ''
                                };
                                ?>
                                <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($t['status']) ?></span>
                            </td>
                            <td><?= date('n/j', strtotime($t['createdAt'])) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit.php?id=<?= $t['id'] ?>" class="btn-icon" title="編集">✏️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($troubles)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--gray-500);">データがありません</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mobile Cards -->
    <div class="mobile-only">
        <?php foreach ($troubles as $t): ?>
            <div class="trouble-card">
                <div class="trouble-card-header">
                    <input type="checkbox" class="trouble-checkbox" name="delete_ids[]" value="<?= $t['id'] ?>" onchange="updateBulkDeleteButton()" style="margin-right: 0.5rem;">
                    <div style="flex: 1;">
                        <div class="trouble-card-pj"><?= htmlspecialchars($t['pjNumber']) ?></div>
                        <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($t['pjName'] ?? '') ?></div>
                    </div>
                    <?php
                    $statusClass = match($t['status']) {
                        '未対応' => 'status-pending',
                        '対応中' => 'status-in-progress',
                        '完了' => 'status-completed',
                        default => ''
                    };
                    ?>
                    <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($t['status']) ?></span>
                </div>
                <div class="trouble-card-body">
                    <div style="margin-bottom: 0.5rem;"><strong><?= htmlspecialchars($t['deviceType']) ?></strong></div>
                    <div style="margin-bottom: 0.5rem;">
                        <strong>内容:</strong><br>
                        <?= nl2br(htmlspecialchars($t['content'])) ?>
                    </div>
                    <?php if (!empty($t['solution'])): ?>
                        <div style="color: var(--success);">
                            <strong>解決:</strong><br>
                            <?= nl2br(htmlspecialchars($t['solution'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="trouble-card-meta">
                    <span>👤 <?= htmlspecialchars($t['assignee'] ?? '未割当') ?></span>
                    <span>📅 <?= date('n/j', strtotime($t['createdAt'])) ?></span>
                    <a href="edit.php?id=<?= $t['id'] ?>" class="btn-icon" title="編集" style="margin-left: auto;">✏️</a>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($troubles)): ?>
            <div class="card" style="text-align: center; color: var(--gray-500);">データがありません</div>
        <?php endif; ?>
    </div>
</form>

<script>
function updateBulkDeleteButton() {
    const checkboxes = document.querySelectorAll('.trouble-checkbox');
    const checkedBoxes = document.querySelectorAll('.trouble-checkbox:checked');
    const count = checkedBoxes.length;

    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const clearSelectionBtn = document.getElementById('clear-selection-btn');
    const selectedCountSpan = document.getElementById('selected-count');
    const selectAllCheckbox = document.getElementById('select-all-checkbox');

    if (count > 0) {
        bulkDeleteBtn.style.display = 'block';
        clearSelectionBtn.style.display = 'block';
        selectedCountSpan.textContent = count;
    } else {
        bulkDeleteBtn.style.display = 'none';
        clearSelectionBtn.style.display = 'none';
    }

    // 全選択チェックボックスの状態更新
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = checkboxes.length > 0 && count === checkboxes.length;
        selectAllCheckbox.indeterminate = count > 0 && count < checkboxes.length;
    }
}

function toggleAllCheckboxes(source) {
    const checkboxes = document.querySelectorAll('.trouble-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateBulkDeleteButton();
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.trouble-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    document.getElementById('select-all-checkbox').checked = false;
    updateBulkDeleteButton();
}

function confirmBulkDelete() {
    const count = document.querySelectorAll('.trouble-checkbox:checked').length;
    if (count === 0) return;

    if (confirm(`選択した ${count} 件のトラブルを削除しますか？\n\nこの操作は取り消せません。`)) {
        document.getElementById('bulk-delete-form').submit();
    }
}
</script>

<?php require_once 'footer.php'; ?>
