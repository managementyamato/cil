<?php
require_once 'config.php';
$data = getData();

$message = '';
$messageType = '';

// 顧客コード自動生成
function generateCustomerCode($customers) {
    $maxNumber = 0;
    foreach ($customers as $customer) {
        if (preg_match('/^CST-(\d+)$/', $customer['code'], $matches)) {
            $number = (int)$matches[1];
            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }
    }
    return 'CST-' . str_pad($maxNumber + 1, 5, '0', STR_PAD_LEFT);
}

// 顧客追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $companyName = trim($_POST['company_name'] ?? '');

    if ($companyName) {
        $customerCode = generateCustomerCode($data['customers']);

        $newCustomer = array(
            'code' => $customerCode,
            'companyName' => $companyName,
            'companyKana' => trim($_POST['company_kana'] ?? ''),
            'honorific' => trim($_POST['honorific'] ?? ''),
            'postalCode' => trim($_POST['postal_code'] ?? ''),
            'prefecture' => trim($_POST['prefecture'] ?? ''),
            'address1' => trim($_POST['address1'] ?? ''),
            'address2' => trim($_POST['address2'] ?? ''),
            'department' => trim($_POST['department'] ?? ''),
            'position' => trim($_POST['position'] ?? ''),
            'contactName' => trim($_POST['contact_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'ccEmail' => trim($_POST['cc_email'] ?? ''),
            'assignedTo' => trim($_POST['assigned_to'] ?? ''),
            'memo' => trim($_POST['memo'] ?? '')
        );

        $data['customers'][] = $newCustomer;
        saveData($data);
        $message = '顧客を追加しました（顧客コード: ' . $customerCode . '）';
        $messageType = 'success';
    } else {
        $message = '会社名は必須です';
        $messageType = 'danger';
    }
}

// 顧客編集
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_customer'])) {
    $code = $_POST['customer_code'];
    $companyName = trim($_POST['company_name'] ?? '');

    if ($companyName) {
        foreach ($data['customers'] as $key => $customer) {
            if ($customer['code'] === $code) {
                $data['customers'][$key] = array(
                    'code' => $code,
                    'companyName' => $companyName,
                    'companyKana' => trim($_POST['company_kana'] ?? ''),
                    'honorific' => trim($_POST['honorific'] ?? ''),
                    'postalCode' => trim($_POST['postal_code'] ?? ''),
                    'prefecture' => trim($_POST['prefecture'] ?? ''),
                    'address1' => trim($_POST['address1'] ?? ''),
                    'address2' => trim($_POST['address2'] ?? ''),
                    'department' => trim($_POST['department'] ?? ''),
                    'position' => trim($_POST['position'] ?? ''),
                    'contactName' => trim($_POST['contact_name'] ?? ''),
                    'phone' => trim($_POST['phone'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'ccEmail' => trim($_POST['cc_email'] ?? ''),
                    'assignedTo' => trim($_POST['assigned_to'] ?? ''),
                    'memo' => trim($_POST['memo'] ?? '')
                );
                saveData($data);
                $message = '顧客情報を更新しました';
                $messageType = 'success';
                break;
            }
        }
    } else {
        $message = '会社名は必須です';
        $messageType = 'danger';
    }
}

// 顧客削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    $deleteCode = $_POST['delete_customer'];
    $data['customers'] = array_values(array_filter($data['customers'], function($c) use ($deleteCode) {
        return $c['code'] !== $deleteCode;
    }));
    saveData($data);
    $message = '顧客を削除しました';
    $messageType = 'success';
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

.customer-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.customer-table th {
    background: #f7fafc;
    padding: 0.75rem;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
    font-weight: 600;
    color: #4a5568;
}

.customer-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}

.customer-table tr:hover {
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

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
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
    max-width: 800px;
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
    <h1>🏢 顧客マスタ</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="padding: 1rem; margin-bottom: 1rem; border-radius: 4px; background: <?= $messageType === 'success' ? '#c6f6d5' : '#fed7d7' ?>; color: <?= $messageType === 'success' ? '#22543d' : '#742a2a' ?>;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 class="card-title" style="margin: 0;">顧客一覧 （総件数: <?= count($data['customers']) ?>件）</h2>
            <button class="btn btn-primary" onclick="openAddModal()">顧客新規登録</button>
        </div>

        <table class="customer-table">
            <thead>
                <tr>
                    <th>操作</th>
                    <th>NO.</th>
                    <th>顧客コード</th>
                    <th>会社名</th>
                    <th>名称(カナ)</th>
                    <th>敬称</th>
                    <th>郵便番号</th>
                    <th>都道府県</th>
                    <th>住所1</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['customers'])): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: #718096;">登録されている顧客はありません</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data['customers'] as $index => $customer): ?>
                        <tr>
                            <td>
                                <button class="btn btn-edit" onclick='openEditModal(<?= json_encode($customer) ?>)'>編集</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('この顧客を削除してもよろしいですか？');">
                                    <button type="submit" name="delete_customer" value="<?= htmlspecialchars($customer['code']) ?>" class="btn btn-danger">削除</button>
                                </form>
                            </td>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($customer['code']) ?></td>
                            <td><?= htmlspecialchars($customer['companyName']) ?></td>
                            <td><?= htmlspecialchars($customer['companyKana']) ?></td>
                            <td><?= htmlspecialchars($customer['honorific']) ?></td>
                            <td><?= htmlspecialchars($customer['postalCode']) ?></td>
                            <td><?= htmlspecialchars($customer['prefecture']) ?></td>
                            <td><?= htmlspecialchars($customer['address1']) ?></td>
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
        <div class="modal-header">新規顧客マスタ登録</div>
        <form method="POST">
            <div class="form-group">
                <label>顧客コード（自動採番）</label>
                <input type="text" value="<?= generateCustomerCode($data['customers']) ?>" disabled>
            </div>

            <div class="form-group">
                <label>会社名 <span class="required">*</span></label>
                <input type="text" name="company_name" required>
            </div>

            <div class="form-group">
                <label>会社名(カナ)</label>
                <input type="text" name="company_kana">
            </div>

            <div class="form-group">
                <label>敬称</label>
                <input type="text" name="honorific" placeholder="例: 御中">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>郵便番号</label>
                    <input type="text" name="postal_code">
                </div>
                <div class="form-group">
                    <label>都道府県</label>
                    <input type="text" name="prefecture">
                </div>
            </div>

            <div class="form-group">
                <label>住所1</label>
                <input type="text" name="address1">
            </div>

            <div class="form-group">
                <label>住所2</label>
                <input type="text" name="address2">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>担当者部署</label>
                    <input type="text" name="department">
                </div>
                <div class="form-group">
                    <label>担当者役職</label>
                    <input type="text" name="position">
                </div>
            </div>

            <div class="form-group">
                <label>担当者氏名</label>
                <input type="text" name="contact_name">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>電話番号</label>
                    <input type="text" name="phone">
                </div>
                <div class="form-group">
                    <label>メールアドレス</label>
                    <input type="email" name="email">
                </div>
            </div>

            <div class="form-group">
                <label>CCメールアドレス</label>
                <input type="email" name="cc_email">
            </div>

            <div class="form-group">
                <label>自社担当者名</label>
                <select name="assigned_to">
                    <option value="">選択してください</option>
                    <?php foreach ($data['assignees'] as $assignee): ?>
                        <option value="<?= htmlspecialchars($assignee['name']) ?>"><?= htmlspecialchars($assignee['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>メモ</label>
                <textarea name="memo"></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">戻る</button>
                <button type="submit" name="add_customer" class="btn btn-primary">登録</button>
            </div>
        </form>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">顧客情報編集</div>
        <form method="POST" id="editForm">
            <input type="hidden" name="customer_code" id="edit_code">

            <div class="form-group">
                <label>顧客コード</label>
                <input type="text" id="edit_code_display" disabled>
            </div>

            <div class="form-group">
                <label>会社名 <span class="required">*</span></label>
                <input type="text" name="company_name" id="edit_company_name" required>
            </div>

            <div class="form-group">
                <label>会社名(カナ)</label>
                <input type="text" name="company_kana" id="edit_company_kana">
            </div>

            <div class="form-group">
                <label>敬称</label>
                <input type="text" name="honorific" id="edit_honorific" placeholder="例: 御中">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>郵便番号</label>
                    <input type="text" name="postal_code" id="edit_postal_code">
                </div>
                <div class="form-group">
                    <label>都道府県</label>
                    <input type="text" name="prefecture" id="edit_prefecture">
                </div>
            </div>

            <div class="form-group">
                <label>住所1</label>
                <input type="text" name="address1" id="edit_address1">
            </div>

            <div class="form-group">
                <label>住所2</label>
                <input type="text" name="address2" id="edit_address2">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>担当者部署</label>
                    <input type="text" name="department" id="edit_department">
                </div>
                <div class="form-group">
                    <label>担当者役職</label>
                    <input type="text" name="position" id="edit_position">
                </div>
            </div>

            <div class="form-group">
                <label>担当者氏名</label>
                <input type="text" name="contact_name" id="edit_contact_name">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>電話番号</label>
                    <input type="text" name="phone" id="edit_phone">
                </div>
                <div class="form-group">
                    <label>メールアドレス</label>
                    <input type="email" name="email" id="edit_email">
                </div>
            </div>

            <div class="form-group">
                <label>CCメールアドレス</label>
                <input type="email" name="cc_email" id="edit_cc_email">
            </div>

            <div class="form-group">
                <label>自社担当者名</label>
                <select name="assigned_to" id="edit_assigned_to">
                    <option value="">選択してください</option>
                    <?php foreach ($data['assignees'] as $assignee): ?>
                        <option value="<?= htmlspecialchars($assignee['name']) ?>"><?= htmlspecialchars($assignee['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>メモ</label>
                <textarea name="memo" id="edit_memo"></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">キャンセル</button>
                <button type="submit" name="edit_customer" class="btn btn-primary">更新</button>
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

function openEditModal(customer) {
    document.getElementById('edit_code').value = customer.code;
    document.getElementById('edit_code_display').value = customer.code;
    document.getElementById('edit_company_name').value = customer.companyName;
    document.getElementById('edit_company_kana').value = customer.companyKana || '';
    document.getElementById('edit_honorific').value = customer.honorific || '';
    document.getElementById('edit_postal_code').value = customer.postalCode || '';
    document.getElementById('edit_prefecture').value = customer.prefecture || '';
    document.getElementById('edit_address1').value = customer.address1 || '';
    document.getElementById('edit_address2').value = customer.address2 || '';
    document.getElementById('edit_department').value = customer.department || '';
    document.getElementById('edit_position').value = customer.position || '';
    document.getElementById('edit_contact_name').value = customer.contactName || '';
    document.getElementById('edit_phone').value = customer.phone || '';
    document.getElementById('edit_email').value = customer.email || '';
    document.getElementById('edit_cc_email').value = customer.ccEmail || '';
    document.getElementById('edit_assigned_to').value = customer.assignedTo || '';
    document.getElementById('edit_memo').value = customer.memo || '';

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
