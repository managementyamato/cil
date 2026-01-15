<?php
require_once 'config.php';
require_once 'mf-attendance-api.php';

$data = getData();

$message = '';
$messageType = '';

// 社員コード自動生成
function generateEmployeeCode($employees) {
    $maxNumber = 0;
    foreach ($employees as $employee) {
        if (preg_match('/^YA-(\d+)$/', $employee['code'], $matches)) {
            $number = (int)$matches[1];
            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }
    }
    return 'YA-' . str_pad($maxNumber + 1, 3, '0', STR_PAD_LEFT);
}

// 従業員追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $name = trim($_POST['name'] ?? '');
    $area = trim($_POST['area'] ?? '');

    if ($name && $area) {
        $employeeCode = generateEmployeeCode($data['employees']);

        $newEmployee = array(
            'code' => $employeeCode,
            'name' => $name,
            'area' => $area,
            'email' => trim($_POST['email'] ?? ''),
            'memo' => trim($_POST['memo'] ?? '')
        );

        $data['employees'][] = $newEmployee;
        saveData($data);
        $message = '従業員を追加しました（社員コード: ' . $employeeCode . '）';
        $messageType = 'success';
    } else {
        $message = '氏名と担当エリアは必須です';
        $messageType = 'danger';
    }
}

// 従業員編集
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $code = $_POST['employee_code'];
    $name = trim($_POST['name'] ?? '');
    $area = trim($_POST['area'] ?? '');

    if ($name && $area) {
        foreach ($data['employees'] as $key => $employee) {
            if ($employee['code'] === $code) {
                $data['employees'][$key] = array(
                    'code' => $code,
                    'name' => $name,
                    'area' => $area,
                    'email' => trim($_POST['email'] ?? ''),
                    'memo' => trim($_POST['memo'] ?? '')
                );
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
    $deleteCode = $_POST['delete_employee'];
    $data['employees'] = array_values(array_filter($data['employees'], function($e) use ($deleteCode) {
        return $e['code'] !== $deleteCode;
    }));
    saveData($data);
    $message = '従業員を削除しました';
    $messageType = 'success';
}

// MF勤怠から従業員を同期
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_mf_attendance'])) {
    try {
        $mfClient = new MFAttendanceApiClient();

        if (!$mfClient->isAuthenticated()) {
            $message = 'MFクラウド勤怠と連携していません。先に認証を行ってください。';
            $messageType = 'danger';
        } else {
            $mfEmployees = $mfClient->getEmployees();
            $syncedCount = 0;

            if (isset($mfEmployees['employees'])) {
                $mfEmployees = $mfEmployees['employees'];
            }

            foreach ($mfEmployees as $mfEmp) {
                // 既存の従業員をチェック（MF勤怠IDで検索）
                $exists = false;
                foreach ($data['employees'] as $key => $emp) {
                    if (isset($emp['mf_attendance_id']) && $emp['mf_attendance_id'] === (string)$mfEmp['id']) {
                        $exists = true;
                        // 既存従業員の情報を更新
                        $data['employees'][$key]['mf_attendance_name'] = $mfEmp['name'];
                        $data['employees'][$key]['mf_attendance_email'] = $mfEmp['email'] ?? '';
                        break;
                    }
                }

                // 新規従業員を追加（従業員コードを自動生成）
                if (!$exists) {
                    $employeeCode = generateEmployeeCode($data['employees']);
                    $newEmployee = array(
                        'code' => $employeeCode,
                        'name' => $mfEmp['name'],
                        'area' => '',
                        'email' => $mfEmp['email'] ?? '',
                        'memo' => 'MFクラウド勤怠から同期',
                        'mf_attendance_id' => (string)$mfEmp['id'],
                        'mf_attendance_name' => $mfEmp['name'],
                        'mf_attendance_email' => $mfEmp['email'] ?? ''
                    );
                    $data['employees'][] = $newEmployee;
                    $syncedCount++;
                }
            }

            saveData($data);
            $message = "MFクラウド勤怠から {$syncedCount} 名の従業員を同期しました";
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = 'エラー: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

require_once 'header.php';
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
    <h1>👨‍💼 従業員マスタ</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="padding: 1rem; margin-bottom: 1rem; border-radius: 4px; background: <?= $messageType === 'success' ? '#c6f6d5' : '#fed7d7' ?>; color: <?= $messageType === 'success' ? '#22543d' : '#742a2a' ?>;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 class="card-title" style="margin: 0;">従業員一覧 （総件数: <?= count($data['employees']) ?>件）</h2>
            <div style="display: flex; gap: 0.5rem;">
                <?php
                if (MFAttendanceApiClient::isConfigured()):
                ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="sync_mf_attendance" class="btn btn-secondary">MF勤怠から同期</button>
                </form>
                <?php else: ?>
                <a href="mf-attendance-settings.php" class="btn btn-secondary" style="text-decoration: none; display: inline-block;">MF勤怠連携設定</a>
                <?php endif; ?>
                <button class="btn btn-primary" onclick="openAddModal()">従業員新規登録</button>
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
                    <th>MF連携</th>
                    <th>備考</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['employees'])): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #718096;">登録されている従業員はありません</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data['employees'] as $index => $employee): ?>
                        <tr>
                            <td>
                                <button class="btn btn-edit" onclick='openEditModal(<?= json_encode($employee) ?>)'>編集</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('この従業員を削除してもよろしいですか？');">
                                    <button type="submit" name="delete_employee" value="<?= htmlspecialchars($employee['code']) ?>" class="btn btn-danger">削除</button>
                                </form>
                            </td>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($employee['code']) ?></td>
                            <td><?= htmlspecialchars($employee['name']) ?></td>
                            <td><?= htmlspecialchars($employee['area']) ?></td>
                            <td><?= htmlspecialchars($employee['email']) ?></td>
                            <td>
                                <?php if (!empty($employee['mf_attendance_id'])): ?>
                                    <span style="background: #c6f6d5; color: #22543d; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">連携済み</span>
                                <?php else: ?>
                                    <span style="color: #a0aec0;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($employee['memo']) ?></td>
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
                <input type="email" name="email">
            </div>

            <div class="form-group">
                <label>備考</label>
                <textarea name="memo"></textarea>
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
            <input type="hidden" name="employee_code" id="edit_code">

            <div class="form-group">
                <label>社員コード</label>
                <input type="text" id="edit_code_display" disabled>
            </div>

            <div class="form-group">
                <label>氏名 <span class="required">*</span></label>
                <input type="text" name="name" id="edit_name" required>
            </div>

            <div class="form-group">
                <label>担当エリア <span class="required">*</span></label>
                <input type="text" name="area" id="edit_area" required>
            </div>

            <div class="form-group">
                <label>メールアドレス</label>
                <input type="email" name="email" id="edit_email">
            </div>

            <div class="form-group">
                <label>備考</label>
                <textarea name="memo" id="edit_memo"></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">キャンセル</button>
                <button type="submit" name="edit_employee" class="btn btn-primary">更新</button>
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
    document.getElementById('edit_code').value = employee.code;
    document.getElementById('edit_code_display').value = employee.code;
    document.getElementById('edit_name').value = employee.name;
    document.getElementById('edit_area').value = employee.area;
    document.getElementById('edit_email').value = employee.email || '';
    document.getElementById('edit_memo').value = employee.memo || '';

    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

// モーダル外クリックで閉じる
document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddModal();
    }
});

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

<?php require_once 'footer.php'; ?>
