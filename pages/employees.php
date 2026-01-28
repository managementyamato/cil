<?php
require_once '../api/auth.php';

$data = getData();

$message = '';
$messageType = '';

// 社員コード自動生成
function generateEmployeeCode($employees) {
    $maxNumber = 0;
    foreach ($employees as $employee) {
        $code = $employee['code'] ?? '';
        if (preg_match('/^YA-(\d+)$/', $code, $matches)) {
            $number = (int)$matches[1];
            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }
    }
    return 'YA-' . str_pad($maxNumber + 1, 3, '0', STR_PAD_LEFT);
}

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// 従業員追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $name = trim($_POST['name'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $vehicle_number = trim($_POST['vehicle_number'] ?? '');

    if ($name && $area) {
        $employeeCode = generateEmployeeCode($data['employees']);

        $newEmployee = array(
            'code' => $employeeCode,
            'name' => $name,
            'area' => $area,
            'email' => $email,
            'memo' => trim($_POST['memo'] ?? ''),
            'vehicle_number' => $vehicle_number
        );

        // 権限情報を追加
        if (!empty($role)) {
            $newEmployee['role'] = $role;
        }

        // ID生成（photo-uploadで使用）
        if (empty($data['employees'])) {
            $newEmployee['id'] = 1;
        } else {
            $maxId = 0;
            foreach ($data['employees'] as $emp) {
                if (isset($emp['id']) && $emp['id'] > $maxId) {
                    $maxId = $emp['id'];
                }
            }
            $newEmployee['id'] = $maxId + 1;
        }

        $data['employees'][] = $newEmployee;
        saveData($data);
        $message = '従業員を追加しました（社員コード: ' . $employeeCode . '）';
        $messageType = 'success';
    } else {
        $message = '氏名と担当エリアは必須です';
        $messageType = 'danger';
    }
}

// 従業員一括登録
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add_employees'])) {
    $names = $_POST['bulk_name'] ?? [];
    $areas = $_POST['bulk_area'] ?? [];
    $emails = $_POST['bulk_email'] ?? [];
    $vehicles = $_POST['bulk_vehicle_number'] ?? [];
    $roles = $_POST['bulk_role'] ?? [];
    $memos = $_POST['bulk_memo'] ?? [];

    $addedCount = 0;
    for ($i = 0; $i < count($names); $i++) {
        $name = trim($names[$i] ?? '');
        $area = trim($areas[$i] ?? '');
        if (empty($name) || empty($area)) continue;

        $employeeCode = generateEmployeeCode($data['employees']);
        $newEmployee = array(
            'code' => $employeeCode,
            'name' => $name,
            'area' => $area,
            'email' => trim($emails[$i] ?? ''),
            'vehicle_number' => trim($vehicles[$i] ?? ''),
            'memo' => trim($memos[$i] ?? ''),
        );
        if (!empty($roles[$i] ?? '')) {
            $newEmployee['role'] = $roles[$i];
        }
        $maxId = 0;
        foreach ($data['employees'] as $emp) {
            $empId = (int)($emp['id'] ?? 0);
            if ($empId > $maxId) {
                $maxId = $empId;
            }
        }
        $newEmployee['id'] = $maxId + 1;
        $data['employees'][] = $newEmployee;
        $addedCount++;
    }

    if ($addedCount > 0) {
        saveData($data);
        $message = "{$addedCount}名の従業員を一括登録しました";
        $messageType = 'success';
    } else {
        $message = '有効なデータがありません（氏名と担当エリアは必須）';
        $messageType = 'danger';
    }
}

// 従業員編集
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $code = $_POST['employee_code'];
    $employeeId = $_POST['employee_id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $vehicle_number = trim($_POST['vehicle_number'] ?? '');
    $chat_user_id = trim($_POST['chat_user_id'] ?? '');

    if ($name) {
        foreach ($data['employees'] as $key => $employee) {
            // codeまたはidでマッチング
            $matched = false;
            if (!empty($code) && isset($employee['code']) && $employee['code'] === $code) {
                $matched = true;
            } elseif (!empty($employeeId) && isset($employee['id']) && $employee['id'] === $employeeId) {
                $matched = true;
            }
            if ($matched) {
                $updatedEmployee = array(
                    'id' => $employee['id'] ?? $key + 1,
                    'name' => $name,
                    'area' => $area,
                    'email' => $email,
                    'memo' => trim($_POST['memo'] ?? ''),
                    'vehicle_number' => $vehicle_number,
                    'chat_user_id' => $chat_user_id
                );

                // codeがある場合のみ保持
                if (!empty($employee['code'])) {
                    $updatedEmployee['code'] = $employee['code'];
                } elseif (!empty($code)) {
                    $updatedEmployee['code'] = $code;
                }

                // Google OAuth自動登録の情報を保持
                if (isset($employee['created_by'])) {
                    $updatedEmployee['created_by'] = $employee['created_by'];
                }
                if (isset($employee['created_at'])) {
                    $updatedEmployee['created_at'] = $employee['created_at'];
                }

                // 権限情報を更新
                if (!empty($role)) {
                    $updatedEmployee['role'] = $role;
                }

                $data['employees'][$key] = $updatedEmployee;
                saveData($data);
                $message = '従業員情報を更新しました';
                $messageType = 'success';
                break;
            }
        }
    } else {
        $message = '氏名と担当エリアは必須です';
        $messageType = 'danger';
    }
}

// 従業員削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employee'])) {
    $deleteKey = $_POST['delete_employee'];
    $data['employees'] = array_values(array_filter($data['employees'], function($e) use ($deleteKey) {
        // code または id で削除判定
        if (isset($e['code']) && $e['code'] === $deleteKey) {
            return false;
        }
        if (isset($e['id']) && $e['id'] === $deleteKey) {
            return false;
        }
        return true;
    }));
    saveData($data);
    $message = '従業員を削除しました';
    $messageType = 'success';
}

require_once '../functions/header.php';
?>

<style>
.master-container {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.card-title {
    font-size: 1.25rem;
    font-weight: bold;
    margin-bottom: 1rem;
    color: #2d3748;
}

.employee-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.employee-table th {
    background: #f7fafc;
    padding: 0.75rem;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
    font-weight: 600;
    color: #4a5568;
}

.employee-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}

.employee-table tr:hover {
    background: #f7fafc;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.btn-primary {
    background: #3182ce;
    color: white;
}

.btn-primary:hover {
    background: #2c5282;
}

.btn-danger {
    background: #e53e3e;
    color: white;
}

.btn-danger:hover {
    background: #c53030;
}

.btn-edit {
    background: #48bb78;
    color: white;
    margin-right: 0.5rem;
}

.btn-edit:hover {
    background: #38a169;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #2d3748;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #cbd5e0;
    border-radius: 4px;
    font-size: 0.875rem;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.required {
    color: #e53e3e;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 8px;
    padding: 2rem;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 1.5rem;
}

.modal-footer {
    margin-top: 1.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.btn-secondary {
    background: #718096;
    color: white;
}

.btn-secondary:hover {
    background: #4a5568;
}
</style>

<div class="master-container">
    <h1>従業員マスタ</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="padding: 1rem; margin-bottom: 1rem; border-radius: 4px; background: <?= $messageType === 'success' ? '#c6f6d5' : '#fed7d7' ?>; color: <?= $messageType === 'success' ? '#22543d' : '#742a2a' ?>;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 class="card-title" style="margin: 0;">従業員一覧 （総件数: <?= count($data['employees']) ?>件）</h2>
            <div style="display: flex; gap: 0.5rem;">
                <button class="btn btn-primary" onclick="openAddModal()">新規登録</button>
                <button class="btn btn-edit" onclick="openBulkAddModal()">一括登録</button>
            </div>
        </div>

        <table class="employee-table">
            <thead>
                <tr>
                    <th>操作</th>
                    <th>NO.</th>
                    <th>従業員コード</th>
                    <th>氏名</th>
                    <th>担当エリア</th>
                    <th>メールアドレス</th>
                    <th>車両ナンバー</th>
                    <th>ユーザー権限</th>
                    <th>備考</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['employees'])): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: #718096;">登録されている従業員はありません</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data['employees'] as $index => $employee): ?>
                        <?php $deleteKey = $employee['code'] ?? $employee['id'] ?? ''; ?>
                        <tr>
                            <td>
                                <button class="btn btn-edit" onclick='openEditModal(<?= json_encode($employee) ?>)'>編集</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('この従業員を削除してもよろしいですか？');">
                                    <?= csrfTokenField() ?>
                                    <button type="submit" name="delete_employee" value="<?= htmlspecialchars($deleteKey) ?>" class="btn btn-danger">削除</button>
                                </form>
                            </td>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($employee['code'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($employee['name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($employee['area'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($employee['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($employee['vehicle_number'] ?? '') ?></td>
                            <td>
                                <?php if (!empty($employee['role'])): ?>
                                    <?php
                                    $roleLabels = array('admin' => '管理部', 'product' => '製品管理部', 'sales' => '営業部');
                                    $roleLabel = $roleLabels[$employee['role']] ?? $employee['role'];
                                    $roleColors = array('admin' => '#dbeafe', 'product' => '#d1fae5', 'sales' => '#fef3c7');
                                    $roleTextColors = array('admin' => '#1e40af', 'product' => '#065f46', 'sales' => '#92400e');
                                    $bg = $roleColors[$employee['role']] ?? '#f3f4f6';
                                    $color = $roleTextColors[$employee['role']] ?? '#374151';
                                    ?>
                                    <span style="background: <?= $bg ?>; color: <?= $color ?>; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;"><?= htmlspecialchars($roleLabel) ?></span>
                                <?php else: ?>
                                    <span style="color: #a0aec0;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($employee['memo'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 新規登録モーダル -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">新規従業員登録</div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="form-group">
                <label>社員コード（自動採番）</label>
                <input type="text" value="<?= generateEmployeeCode($data['employees']) ?>" disabled>
                <small style="color: #718096;">※既存の番号から自動で割り振られます</small>
            </div>

            <div class="form-group">
                <label>氏名 <span class="required">*</span></label>
                <input type="text" name="name" required>
            </div>

            <div class="form-group">
                <label>担当エリア <span class="required">*</span></label>
                <input type="text" name="area" required>
            </div>

            <div class="form-group">
                <label>メールアドレス</label>
                <input type="email" name="email" id="add_email">
                <small style="color: #718096;">Googleログイン時にこのメールアドレスで照合されます</small>
            </div>

            <div class="form-group">
                <label>車両ナンバー</label>
                <input type="text" name="vehicle_number" id="add_vehicle_number" placeholder="例: 品川 500 あ 1234">
                <small style="color: #718096;">アルコールチェック管理で使用します</small>
            </div>

            <div class="form-group">
                <label>備考</label>
                <textarea name="memo"></textarea>
            </div>

            <div class="form-group">
                <label>権限</label>
                <select class="form-select" name="role" id="add_role">
                    <option value="">設定しない</option>
                    <option value="sales">営業部</option>
                    <option value="product">製品管理部</option>
                    <option value="admin">管理部</option>
                </select>
                <small style="color: #718096;">Googleログイン時に適用される権限です</small>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">戻る</button>
                <button type="submit" name="add_employee" class="btn btn-primary">登録</button>
            </div>
        </form>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">従業員情報編集</div>
        <form method="POST" id="editForm">
            <?= csrfTokenField() ?>
            <input type="hidden" name="employee_code" id="edit_code">
            <input type="hidden" name="employee_id" id="edit_id">

            <div class="form-group">
                <label>社員コード</label>
                <input type="text" id="edit_code_display" disabled>
            </div>

            <div class="form-group">
                <label>氏名 <span class="required">*</span></label>
                <input type="text" name="name" id="edit_name" required>
            </div>

            <div class="form-group">
                <label>担当エリア</label>
                <input type="text" name="area" id="edit_area">
            </div>

            <div class="form-group">
                <label>メールアドレス</label>
                <input type="email" name="email" id="edit_email">
                <small style="color: #718096;">Googleログイン時にこのメールアドレスで照合されます</small>
            </div>

            <div class="form-group">
                <label>車両ナンバー</label>
                <input type="text" name="vehicle_number" id="edit_vehicle_number" placeholder="例: 品川 500 あ 1234">
                <small style="color: #718096;">アルコールチェック管理で使用します</small>
            </div>

            <div class="form-group">
                <label>備考</label>
                <textarea name="memo" id="edit_memo"></textarea>
            </div>

            <div class="form-group">
                <label>権限</label>
                <select class="form-select" name="role" id="edit_role">
                    <option value="">設定しない</option>
                    <option value="sales">営業部</option>
                    <option value="product">製品管理部</option>
                    <option value="admin">管理部</option>
                </select>
                <small style="color: #718096;">Googleログイン時に適用される権限です</small>
            </div>

            <div class="form-group">
                <label>Google Chat User ID</label>
                <input type="text" name="chat_user_id" id="edit_chat_user_id" placeholder="例: users/123456789012345678901">
                <small style="color: #718096;">アルコールチェック写真の自動紐付けに使用します。<br>アルコールチェック画面の「Chat連携」で同期後、未紐付け画像の送信者情報から確認できます。</small>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">キャンセル</button>
                <button type="submit" name="edit_employee" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 一括登録モーダル -->
<div id="bulkAddModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">従業員一括登録</div>
        <form method="POST" id="bulkAddForm">
            <?= csrfTokenField() ?>
            <div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #718096; font-size: 0.875rem;">氏名と担当エリアは必須です。空行はスキップされます。</span>
                <button type="button" class="btn btn-primary" onclick="addBulkRow()" style="padding: 0.25rem 0.75rem; font-size: 0.8rem;">+ 行追加</button>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;" id="bulkTable">
                    <thead>
                        <tr style="background: #f7fafc;">
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">No.</th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 2px solid #e2e8f0;">氏名 <span class="required">*</span></th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 2px solid #e2e8f0;">担当エリア <span class="required">*</span></th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 2px solid #e2e8f0;">メール</th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 2px solid #e2e8f0;">車両ナンバー</th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 2px solid #e2e8f0;">権限</th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 2px solid #e2e8f0;">備考</th>
                            <th style="padding: 0.5rem; border-bottom: 2px solid #e2e8f0;"></th>
                        </tr>
                    </thead>
                    <tbody id="bulkTableBody">
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBulkAddModal()">キャンセル</button>
                <button type="submit" name="bulk_add_employees" class="btn btn-primary">一括登録</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

function openEditModal(employee) {
    document.getElementById('edit_code').value = employee.code || '';
    document.getElementById('edit_id').value = employee.id || '';
    document.getElementById('edit_code_display').value = employee.code || '（自動登録）';
    document.getElementById('edit_name').value = employee.name || '';
    document.getElementById('edit_area').value = employee.area || '';
    document.getElementById('edit_email').value = employee.email || '';
    document.getElementById('edit_vehicle_number').value = employee.vehicle_number || '';
    document.getElementById('edit_memo').value = employee.memo || '';
    document.getElementById('edit_role').value = employee.role || '';
    document.getElementById('edit_chat_user_id').value = employee.chat_user_id || '';

    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

function openBulkAddModal() {
    const tbody = document.getElementById('bulkTableBody');
    tbody.innerHTML = '';
    for (let i = 0; i < 5; i++) addBulkRow();
    document.getElementById('bulkAddModal').classList.add('active');
}

function closeBulkAddModal() {
    document.getElementById('bulkAddModal').classList.remove('active');
}

let bulkRowCount = 0;
function addBulkRow() {
    bulkRowCount++;
    const tbody = document.getElementById('bulkTableBody');
    const tr = document.createElement('tr');
    tr.id = 'bulkRow' + bulkRowCount;
    const inputStyle = 'width:100%;padding:4px 6px;border:1px solid #cbd5e0;border-radius:4px;font-size:0.85rem;box-sizing:border-box;';
    tr.innerHTML = `
        <td style="padding:4px;color:#718096;text-align:center;" class="bulk-row-num"></td>
        <td style="padding:4px;"><input type="text" name="bulk_name[]" style="${inputStyle}" placeholder="氏名"></td>
        <td style="padding:4px;"><input type="text" name="bulk_area[]" style="${inputStyle}" placeholder="エリア"></td>
        <td style="padding:4px;"><input type="email" name="bulk_email[]" style="${inputStyle}" placeholder="email"></td>
        <td style="padding:4px;"><input type="text" name="bulk_vehicle_number[]" style="${inputStyle}" placeholder="車両"></td>
        <td style="padding:4px;">
            <select name="bulk_role[]" style="${inputStyle}">
                <option value="">-</option>
                <option value="sales">営業部</option>
                <option value="product">製品管理部</option>
                <option value="admin">管理部</option>
            </select>
        </td>
        <td style="padding:4px;"><input type="text" name="bulk_memo[]" style="${inputStyle}" placeholder="備考"></td>
        <td style="padding:4px;"><button type="button" onclick="removeBulkRow('bulkRow${bulkRowCount}')" style="background:none;border:none;color:#e53e3e;cursor:pointer;font-size:1.1rem;">✕</button></td>
    `;
    tbody.appendChild(tr);
    renumberBulkRows();
}

function removeBulkRow(id) {
    const row = document.getElementById(id);
    if (row) row.remove();
    renumberBulkRows();
}

function renumberBulkRows() {
    document.querySelectorAll('#bulkTableBody tr').forEach((tr, i) => {
        tr.querySelector('.bulk-row-num').textContent = i + 1;
    });
}

// モーダル外クリックで閉じる
['addModal', 'editModal', 'bulkAddModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
</script>

<?php require_once '../functions/footer.php'; ?>
