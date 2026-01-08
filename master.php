<?php
require_once 'config.php';
$data = getData();

$message = '';
$messageType = '';

// PJ追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pj'])) {
    $pjNumber = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['pj_number'] ?? ''));
    $pjName = trim($_POST['pj_name'] ?? '');

    if ($pjNumber && $pjName) {
        // 重複チェック
        $exists = false;
        foreach ($data['projects'] as $p) {
            if ($p['id'] === $pjNumber) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            $message = 'このPJ番号は既に登録されています';
            $messageType = 'danger';
        } else {
            $data['projects'][] = ['id' => $pjNumber, 'name' => $pjName];
            saveData($data);
            header('Location: master.php?added=1');
            exit;
        }
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

require_once 'header.php';
?>

<?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success">PJを追加しました</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">PJを削除しました</div>
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

<!-- PJマスタ -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">PJマスタ <span style="font-size: 0.875rem; color: var(--gray-500);">（<?= count($data['projects']) ?>件）</span></h2>
        <button type="button" class="btn btn-primary" onclick="showAddModal()" style="font-size: 0.875rem; padding: 0.5rem 1rem;">新規登録</button>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>PJ番号</th>
                        <th>現場名</th>
                        <th>トラブル件数</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['projects'] as $pj): ?>
                        <?php
                        $troubleCount = count(array_filter($data['troubles'], function($t) use ($pj) {
                            return $t['pjNumber'] === $pj['id'];
                        }));
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($pj['id']) ?></strong></td>
                            <td><?= htmlspecialchars($pj['name']) ?></td>
                            <td><?= $troubleCount ?>件</td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('削除しますか？');">
                                    <input type="hidden" name="delete_pj" value="<?= htmlspecialchars($pj['id']) ?>">
                                    <button type="submit" class="btn-icon" title="削除">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($data['projects'])): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--gray-500);">データがありません</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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

<!-- PJ追加モーダル -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>PJ登録</h3>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="add_pj" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label for="pj_number">PJ番号 *</label>
                    <input type="text" class="form-input" id="pj_number" name="pj_number" placeholder="001" required>
                    <small style="color: var(--gray-500);">英数字のみ（記号は自動削除されます）</small>
                </div>

                <div class="form-group">
                    <label for="pj_name">現場名 *</label>
                    <input type="text" class="form-input" id="pj_name" name="pj_name" placeholder="現場名を入力" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">キャンセル</button>
                <button type="submit" class="btn btn-primary">登録</button>
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

<script>
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
</script>

<?php require_once 'footer.php'; ?>
