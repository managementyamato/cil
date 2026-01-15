<?php
require_once 'config.php';
$data = getData();

$message = '';
$messageType = '';

// パートナーID自動生成
function generatePartnerCode($partners) {
    $maxNumber = 0;
    foreach ($partners as $partner) {
        if (preg_match('/^PT-(\d+)$/', $partner['id'], $matches)) {
            $number = (int)$matches[1];
            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }
    }
    return 'PT-' . str_pad($maxNumber + 1, 4, '0', STR_PAD_LEFT);
}

// パートナー追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_partner'])) {
    $companyName = trim($_POST['company_name'] ?? '');

    if ($companyName) {
        $partnerId = generatePartnerCode($data['partners']);

        $newPartner = array(
            'id' => $partnerId,
            'companyName' => $companyName,
            'prefecture' => trim($_POST['prefecture'] ?? ''),
            'contactName' => trim($_POST['contact_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'tobiLicense' => trim($_POST['tobi_license'] ?? ''),
            'electricianLicense' => trim($_POST['electrician_license'] ?? ''),
            'memo' => trim($_POST['memo'] ?? ''),
            'attitudeRating' => trim($_POST['attitude_rating'] ?? '未評価'),
            'skillRating' => trim($_POST['skill_rating'] ?? '未評価'),
            'responseRating' => trim($_POST['response_rating'] ?? '未評価')
        );

        $data['partners'][] = $newPartner;
        saveData($data);
        $message = 'パートナーを追加しました（ID: ' . $partnerId . '）';
        $messageType = 'success';
    } else {
        $message = '会社名は必須です';
        $messageType = 'danger';
    }
}

// パートナー編集
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_partner'])) {
    $id = $_POST['partner_id'];
    $companyName = trim($_POST['company_name'] ?? '');

    if ($companyName) {
        foreach ($data['partners'] as $key => $partner) {
            if ($partner['id'] === $id) {
                $data['partners'][$key] = array(
                    'id' => $id,
                    'companyName' => $companyName,
                    'prefecture' => trim($_POST['prefecture'] ?? ''),
                    'contactName' => trim($_POST['contact_name'] ?? ''),
                    'phone' => trim($_POST['phone'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'address' => trim($_POST['address'] ?? ''),
                    'tobiLicense' => trim($_POST['tobi_license'] ?? ''),
                    'electricianLicense' => trim($_POST['electrician_license'] ?? ''),
                    'memo' => trim($_POST['memo'] ?? ''),
                    'attitudeRating' => trim($_POST['attitude_rating'] ?? '未評価'),
                    'skillRating' => trim($_POST['skill_rating'] ?? '未評価'),
                    'responseRating' => trim($_POST['response_rating'] ?? '未評価')
                );
                saveData($data);
                $message = 'パートナー情報を更新しました';
                $messageType = 'success';
                break;
            }
        }
    } else {
        $message = '会社名は必須です';
        $messageType = 'danger';
    }
}

// パートナー削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_partner'])) {
    $deleteId = $_POST['delete_partner'];
    $data['partners'] = array_values(array_filter($data['partners'], function($p) use ($deleteId) {
        return $p['id'] !== $deleteId;
    }));
    saveData($data);
    $message = 'パートナーを削除しました';
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

.partner-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.partner-table th {
    background: #f7fafc;
    padding: 0.75rem;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
    font-weight: 600;
    color: #4a5568;
}

.partner-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
}

.partner-table tr:hover {
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

.form-row-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
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

.section-divider {
    border-top: 2px solid #e2e8f0;
    margin: 1.5rem 0;
    padding-top: 1rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 1rem;
}
</style>

<div class="master-container">
    <h1>👥 パートナーマスタ</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="padding: 1rem; margin-bottom: 1rem; border-radius: 4px; background: <?= $messageType === 'success' ? '#c6f6d5' : '#fed7d7' ?>; color: <?= $messageType === 'success' ? '#22543d' : '#742a2a' ?>;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 class="card-title" style="margin: 0;">パートナー一覧 （総件数: <?= count($data['partners']) ?>件）</h2>
            <button class="btn btn-primary" onclick="openAddModal()">パートナー新規登録</button>
        </div>

        <table class="partner-table">
            <thead>
                <tr>
                    <th>操作</th>
                    <th>ID</th>
                    <th>都道府県</th>
                    <th>会社名</th>
                    <th>担当者名</th>
                    <th>電話番号</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['partners'])): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #718096;">登録されているパートナーはありません</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data['partners'] as $partner): ?>
                        <tr>
                            <td>
                                <button class="btn btn-edit" onclick='openEditModal(<?= json_encode($partner) ?>)'>編集</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('このパートナーを削除してもよろしいですか？');">
                                    <button type="submit" name="delete_partner" value="<?= htmlspecialchars($partner['id']) ?>" class="btn btn-danger">削除</button>
                                </form>
                            </td>
                            <td><?= htmlspecialchars($partner['id']) ?></td>
                            <td><?= htmlspecialchars($partner['prefecture']) ?></td>
                            <td><?= htmlspecialchars($partner['companyName']) ?></td>
                            <td><?= htmlspecialchars($partner['contactName']) ?></td>
                            <td><?= htmlspecialchars($partner['phone']) ?></td>
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
        <div class="modal-header">新規パートナー登録</div>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>会社名 <span class="required">*</span></label>
                    <input type="text" name="company_name" required>
                </div>
                <div class="form-group">
                    <label>都道府県</label>
                    <input type="text" name="prefecture" placeholder="例: 大阪府">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" name="contact_name">
                </div>
                <div class="form-group">
                    <label>電話番号</label>
                    <input type="text" name="phone" placeholder="例: 06-1234-5678">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>メールアドレス</label>
                    <input type="email" name="email">
                </div>
                <div class="form-group">
                    <label>住所</label>
                    <input type="text" name="address">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>とび資格</label>
                    <input type="text" name="tobi_license">
                </div>
                <div class="form-group">
                    <label>電工資格</label>
                    <input type="text" name="electrician_license">
                </div>
            </div>

            <div class="form-group">
                <label>備考</label>
                <textarea name="memo"></textarea>
            </div>

            <div class="section-divider"></div>
            <div class="section-title">評価情報</div>

            <div class="form-row-3">
                <div class="form-group">
                    <label>態度評価</label>
                    <select name="attitude_rating">
                        <option value="未評価">未評価</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>技術評価</label>
                    <select name="skill_rating">
                        <option value="未評価">未評価</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>対応評価</label>
                    <select name="response_rating">
                        <option value="未評価">未評価</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">戻る</button>
                <button type="submit" name="add_partner" class="btn btn-primary">登録</button>
            </div>
        </form>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">パートナー情報編集</div>
        <form method="POST" id="editForm">
            <input type="hidden" name="partner_id" id="edit_id">

            <div class="form-row">
                <div class="form-group">
                    <label>会社名 <span class="required">*</span></label>
                    <input type="text" name="company_name" id="edit_company_name" required>
                </div>
                <div class="form-group">
                    <label>都道府県</label>
                    <input type="text" name="prefecture" id="edit_prefecture" placeholder="例: 大阪府">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" name="contact_name" id="edit_contact_name">
                </div>
                <div class="form-group">
                    <label>電話番号</label>
                    <input type="text" name="phone" id="edit_phone" placeholder="例: 06-1234-5678">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>メールアドレス</label>
                    <input type="email" name="email" id="edit_email">
                </div>
                <div class="form-group">
                    <label>住所</label>
                    <input type="text" name="address" id="edit_address">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>とび資格</label>
                    <input type="text" name="tobi_license" id="edit_tobi_license">
                </div>
                <div class="form-group">
                    <label>電工資格</label>
                    <input type="text" name="electrician_license" id="edit_electrician_license">
                </div>
            </div>

            <div class="form-group">
                <label>備考</label>
                <textarea name="memo" id="edit_memo"></textarea>
            </div>

            <div class="section-divider"></div>
            <div class="section-title">評価情報</div>

            <div class="form-row-3">
                <div class="form-group">
                    <label>態度評価</label>
                    <select name="attitude_rating" id="edit_attitude_rating">
                        <option value="未評価">未評価</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>技術評価</label>
                    <select name="skill_rating" id="edit_skill_rating">
                        <option value="未評価">未評価</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>対応評価</label>
                    <select name="response_rating" id="edit_response_rating">
                        <option value="未評価">未評価</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">キャンセル</button>
                <button type="submit" name="edit_partner" class="btn btn-primary">更新</button>
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

function openEditModal(partner) {
    document.getElementById('edit_id').value = partner.id;
    document.getElementById('edit_company_name').value = partner.companyName;
    document.getElementById('edit_prefecture').value = partner.prefecture || '';
    document.getElementById('edit_contact_name').value = partner.contactName || '';
    document.getElementById('edit_phone').value = partner.phone || '';
    document.getElementById('edit_email').value = partner.email || '';
    document.getElementById('edit_address').value = partner.address || '';
    document.getElementById('edit_tobi_license').value = partner.tobiLicense || '';
    document.getElementById('edit_electrician_license').value = partner.electricianLicense || '';
    document.getElementById('edit_memo').value = partner.memo || '';
    document.getElementById('edit_attitude_rating').value = partner.attitudeRating || '未評価';
    document.getElementById('edit_skill_rating').value = partner.skillRating || '未評価';
    document.getElementById('edit_response_rating').value = partner.responseRating || '未評価';

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
