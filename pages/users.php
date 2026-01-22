<?php
require_once '../config/config.php';

// ユーザー追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $users = getUsers();
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'viewer';
    $permissions = $_POST['permissions'] ?? array();

    if (!empty($email) && !empty($name) && !empty($password)) {
        if (!isset($users[$email])) {
            $userData = array(
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'role' => $role
            );

            // カスタム権限の場合は権限配列を保存
            if ($role === 'custom' && !empty($permissions)) {
                $userData['permissions'] = $permissions;
            }

            $users[$email] = $userData;
            saveUsers($users);
            header('Location: users.php?added=1');
            exit;
        } else {
            $error = 'このメールアドレスは既に登録されています。';
        }
    } else {
        $error = 'すべての項目を入力してください。';
    }
}

// ユーザー編集
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $users = getUsers();
    $originalEmail = $_POST['original_email'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'viewer';
    $permissions = $_POST['permissions'] ?? array();

    if (!empty($email) && !empty($name) && isset($users[$originalEmail])) {
        // 既存のパスワードを保持
        $existingPassword = $users[$originalEmail]['password'];

        // ユーザーデータを更新
        $userData = array(
            'password' => !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : $existingPassword,
            'name' => $name,
            'role' => $role
        );

        // カスタム権限の場合は権限配列を保存、それ以外は削除
        if ($role === 'custom' && !empty($permissions)) {
            $userData['permissions'] = $permissions;
        }

        // メールアドレスが変更された場合
        if ($email !== $originalEmail) {
            if (isset($users[$email])) {
                $error = 'このメールアドレスは既に使用されています。';
            } else {
                unset($users[$originalEmail]);
                $users[$email] = $userData;
                saveUsers($users);
                header('Location: users.php?updated=1');
                exit;
            }
        } else {
            $users[$email] = $userData;
            saveUsers($users);
            header('Location: users.php?updated=1');
            exit;
        }
    }
}

// ユーザー削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $users = getUsers();
    $email = $_POST['email'] ?? '';

    // 自分自身は削除できない
    if ($email === $_SESSION['user_email']) {
        $error = '自分自身のアカウントは削除できません。';
    } else if (isset($users[$email])) {
        unset($users[$email]);
        saveUsers($users);
        header('Location: users.php?deleted=1');
        exit;
    }
}

$users = getUsers();
require_once '../functions/header.php';
?>

<style>
.role-label {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}
.role-admin {
    background: #dbeafe;
    color: #1e40af;
}
.role-editor {
    background: #d1fae5;
    color: #065f46;
}
.role-viewer {
    background: #f3f4f6;
    color: #374151;
}
.role-custom {
    background: #fef3c7;
    color: #92400e;
}
</style>

<?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success">ユーザーを追加しました</div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">ユーザーを更新しました</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">ユーザーを削除しました</div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (empty($users)): ?>
    <div class="card">
        <div class="card-header">
            <h2>初期ユーザー登録</h2>
        </div>
        <div class="card-body">
            <p style="margin-bottom: 1.5rem; color: var(--gray-700);">
                システムにユーザーが登録されていません。最初の管理者アカウントを作成してください。
            </p>
            <form method="POST" action="">
                <input type="hidden" name="add_user" value="1">
                <input type="hidden" name="role" value="admin">

                <div class="form-group">
                    <label for="email">メールアドレス *</label>
                    <input type="email" class="form-input" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="name">名前 *</label>
                    <input type="text" class="form-input" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="password">パスワード *</label>
                    <input type="password" class="form-input" id="password" name="password" required minlength="6">
                    <small style="color: var(--gray-500);">6文字以上で入力してください</small>
                </div>

                <button type="submit" class="btn btn-primary">管理者アカウントを作成</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0;">ユーザー管理</h2>
            <button type="button" class="btn btn-primary" onclick="showAddModal()" style="font-size: 0.875rem; padding: 0.5rem 1rem;">新規登録</button>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>メールアドレス</th>
                            <th>名前</th>
                            <th>権限</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $email => $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($email) ?></td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td>
                                    <?php
                                    $roleClass = 'role-' . $user['role'];
                                    $roleLabels = array('admin' => '管理者', 'editor' => '編集者', 'viewer' => '閲覧者', 'custom' => 'カスタム');
                                    $roleLabel = $roleLabels[$user['role']] ?? $user['role'];
                                    ?>
                                    <span class="role-label <?= $roleClass ?>"><?= htmlspecialchars($roleLabel) ?></span>
                                    <?php if ($user['role'] === 'custom' && isset($user['permissions']) && !empty($user['permissions'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--gray-600); margin-top: 0.25rem;">
                                            <?php
                                            $permLabels = array(
                                                'view_dashboard' => 'ダッシュボード',
                                                'view_list' => '一覧',
                                                'edit_troubles' => '編集',
                                                'manage_projects' => 'プロジェクト',
                                                'manage_finance' => '財務',
                                                'manage_masters' => 'マスタ',
                                                'manage_users' => 'ユーザー',
                                                'manage_mf' => 'MF'
                                            );
                                            $perms = array_map(function($p) use ($permLabels) {
                                                return $permLabels[$p] ?? $p;
                                            }, $user['permissions']);
                                            echo htmlspecialchars(implode(', ', $perms));
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-icon" onclick='showEditModal(<?= json_encode($email) ?>, <?= json_encode($user) ?>)' title="編集">編集</button>
                                        <?php if ($email !== $_SESSION['user_email']): ?>
                                            <button type="button" class="btn-icon" onclick='confirmDelete(<?= json_encode($email) ?>, <?= json_encode($user['name']) ?>)' title="削除">削除</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 追加モーダル -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>ユーザー追加</h3>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="add_user" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label for="add_email">メールアドレス *</label>
                    <input type="email" class="form-input" id="add_email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="add_name">名前 *</label>
                    <input type="text" class="form-input" id="add_name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="add_password">パスワード *</label>
                    <input type="password" class="form-input" id="add_password" name="password" required minlength="6">
                    <small style="color: var(--gray-500);">6文字以上で入力してください</small>
                </div>

                <div class="form-group">
                    <label for="add_role">基本権限 *</label>
                    <select class="form-select" id="add_role" name="role" required onchange="updatePermissionPreset(this.value, 'add')">
                        <option value="viewer">閲覧者</option>
                        <option value="editor">編集者</option>
                        <option value="admin">管理者</option>
                        <option value="custom">カスタム</option>
                    </select>
                    <small style="color: var(--gray-500);">カスタムを選択すると個別に権限を設定できます</small>
                </div>

                <div id="add_custom_permissions" style="display: none;">
                    <h4 style="margin: 1rem 0 0.5rem; font-size: 0.95rem; color: var(--gray-700);">詳細権限設定</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; padding: 1rem; background: #f9fafb; border-radius: 6px;">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="view_dashboard"> ダッシュボード表示
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="view_list"> 一覧表示
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="edit_troubles"> トラブル編集
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="manage_projects"> プロジェクト管理
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="manage_finance"> 損益管理
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="manage_masters"> マスタ管理
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="manage_users"> ユーザー管理
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="manage_mf"> MF設定
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">キャンセル</button>
                <button type="submit" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>ユーザー編集</h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" id="edit_original_email" name="original_email">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_email">メールアドレス *</label>
                    <input type="email" class="form-input" id="edit_email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="edit_name">名前 *</label>
                    <input type="text" class="form-input" id="edit_name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="edit_password">パスワード</label>
                    <input type="password" class="form-input" id="edit_password" name="password" minlength="6">
                    <small style="color: var(--gray-500);">変更する場合のみ入力してください</small>
                </div>

                <div class="form-group">
                    <label for="edit_role">基本権限 *</label>
                    <select class="form-select" id="edit_role" name="role" required onchange="updatePermissionPreset(this.value, 'edit')">
                        <option value="viewer">閲覧者</option>
                        <option value="editor">編集者</option>
                        <option value="admin">管理者</option>
                        <option value="custom">カスタム</option>
                    </select>
                    <small style="color: var(--gray-500);">カスタムを選択すると個別に権限を設定できます</small>
                </div>

                <div id="edit_custom_permissions" style="display: none;">
                    <h4 style="margin: 1rem 0 0.5rem; font-size: 0.95rem; color: var(--gray-700);">詳細権限設定</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; padding: 1rem; background: #f9fafb; border-radius: 6px;">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="view_dashboard"> ダッシュボード表示
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="view_list"> 一覧表示
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="edit_troubles"> トラブル編集
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="manage_projects"> プロジェクト管理
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="manage_finance"> 損益管理
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="manage_masters"> マスタ管理
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="manage_users"> ユーザー管理
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="permissions[]" value="manage_mf"> MF設定
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">キャンセル</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 削除フォーム -->
<form id="deleteForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="delete_user" value="1">
    <input type="hidden" id="delete_email" name="email">
</form>

<script>
// 権限プリセット定義
const permissionPresets = {
    viewer: ['view_dashboard', 'view_list'],
    editor: ['view_dashboard', 'view_list', 'edit_troubles', 'manage_projects', 'manage_finance', 'manage_masters'],
    admin: ['view_dashboard', 'view_list', 'edit_troubles', 'manage_projects', 'manage_finance', 'manage_masters', 'manage_users', 'manage_mf'],
    custom: []
};

function updatePermissionPreset(role, mode) {
    const container = document.getElementById(mode + '_custom_permissions');
    const checkboxes = container.querySelectorAll('input[type="checkbox"]');

    if (role === 'custom') {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
        // プリセット権限を設定
        const preset = permissionPresets[role] || [];
        checkboxes.forEach(cb => {
            cb.checked = preset.includes(cb.value);
        });
    }
}

function showAddModal() {
    document.getElementById('addModal').style.display = 'block';
    document.getElementById('add_role').value = 'viewer';
    updatePermissionPreset('viewer', 'add');
}

function showEditModal(email, user) {
    document.getElementById('edit_original_email').value = email;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_role').value = user.role || 'viewer';

    // カスタム権限があれば設定
    if (user.permissions && user.permissions.length > 0) {
        document.getElementById('edit_role').value = 'custom';
        const checkboxes = document.querySelectorAll('#edit_custom_permissions input[type="checkbox"]');
        checkboxes.forEach(cb => {
            cb.checked = user.permissions.includes(cb.value);
        });
        updatePermissionPreset('custom', 'edit');
    } else {
        updatePermissionPreset(user.role || 'viewer', 'edit');
    }

    document.getElementById('editModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function confirmDelete(email, name) {
    if (confirm('ユーザー「' + name + '」を削除してもよろしいですか？')) {
        document.getElementById('delete_email').value = email;
        document.getElementById('deleteForm').submit();
    }
}

// モーダル外クリックで閉じる
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php require_once '../functions/footer.php'; ?>
