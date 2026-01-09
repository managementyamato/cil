<?php
require_once 'config.php';
require_once 'mf-api.php';

// 編集権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

// MF連携チェック
if (!MFApiClient::isConfigured()) {
    header('Location: list.php?error=mf_not_configured');
    exit;
}

$data = getData();
$message = '';
$error = '';
$troubleId = $_GET['trouble_id'] ?? null;
$trouble = null;

// トラブル情報を取得
if ($troubleId) {
    foreach ($data['troubles'] as $t) {
        if ($t['id'] == $troubleId) {
            $trouble = $t;
            break;
        }
    }

    if (!$trouble) {
        header('Location: list.php?error=trouble_not_found');
        exit;
    }
}

// 取引先一覧を取得
$client = new MFApiClient();
$partners = array();
try {
    $partnersData = $client->getPartners(1, 100);
    $partners = $partnersData['partners'] ?? array();
} catch (Exception $e) {
    $error = 'MF取引先情報の取得に失敗しました: ' . $e->getMessage();
}

// 請求書作成処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    try {
        $partnerCode = $_POST['partner_code'] ?? '';
        $billingDate = $_POST['billing_date'] ?? date('Y-m-d');
        $dueDate = $_POST['due_date'] ?? null;
        $title = $_POST['title'] ?? '';
        $note = $_POST['note'] ?? '';

        // 明細アイテム
        $items = array();
        $itemNames = $_POST['item_name'] ?? array();
        $itemQuantities = $_POST['item_quantity'] ?? array();
        $itemPrices = $_POST['item_price'] ?? array();

        for ($i = 0; $i < count($itemNames); $i++) {
            if (!empty($itemNames[$i])) {
                $items[] = array(
                    'name' => $itemNames[$i],
                    'quantity' => floatval($itemQuantities[$i] ?? 1),
                    'unit_price' => floatval($itemPrices[$i] ?? 0),
                    'excise' => 'ten_percent'
                );
            }
        }

        if (empty($items)) {
            throw new Exception('少なくとも1つの明細を追加してください');
        }

        $invoiceParams = array(
            'partner_code' => $partnerCode,
            'billing_date' => $billingDate,
            'due_date' => $dueDate,
            'title' => $title,
            'note' => $note,
            'items' => $items
        );

        $result = $client->createInvoice($invoiceParams);

        // トラブル案件にMF請求書IDを紐付け
        if ($troubleId && isset($result['billing']['id'])) {
            foreach ($data['troubles'] as &$t) {
                if ($t['id'] == $troubleId) {
                    if (!isset($t['mf_invoices'])) {
                        $t['mf_invoices'] = array();
                    }
                    $t['mf_invoices'][] = array(
                        'billing_id' => $result['billing']['id'],
                        'billing_number' => $result['billing']['billing_number'] ?? '',
                        'title' => $title,
                        'amount' => $result['billing']['total_price'] ?? 0,
                        'created_at' => date('Y-m-d H:i:s')
                    );
                    break;
                }
            }
            saveData($data);
        }

        header('Location: list.php?invoice_created=1');
        exit;

    } catch (Exception $e) {
        $error = '請求書の作成に失敗しました: ' . $e->getMessage();
    }
}

require_once 'header.php';
?>

<style>
.invoice-form-container {
    max-width: 900px;
}

.form-section {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}

.form-section h3 {
    margin: 0 0 1.5rem 0;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--gray-200);
    color: var(--gray-700);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-grid-full {
    grid-column: 1 / -1;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1rem;
}

.items-table th,
.items-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
}

.items-table th {
    background: var(--gray-100);
    font-weight: 600;
    color: var(--gray-700);
}

.items-table input {
    width: 100%;
}

.add-item-btn {
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px dashed var(--gray-400);
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
}

.add-item-btn:hover {
    background: var(--gray-200);
}

.trouble-info-box {
    background: #dbeafe;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.trouble-info-box h4 {
    margin: 0 0 0.5rem 0;
    color: #1e40af;
}
</style>

<div class="invoice-form-container">
    <h2>MF請求書作成</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($trouble): ?>
        <div class="trouble-info-box">
            <h4>トラブル案件情報</h4>
            <p style="margin: 0;">
                <strong>PJ番号:</strong> <?= htmlspecialchars($trouble['pjNumber']) ?><br>
                <strong>案件名:</strong> <?= htmlspecialchars($trouble['pjName'] ?? '') ?><br>
                <strong>機器:</strong> <?= htmlspecialchars($trouble['deviceType']) ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="invoice-form">
        <input type="hidden" name="create_invoice" value="1">

        <div class="form-section">
            <h3>基本情報</h3>

            <div class="form-grid">
                <div class="form-group">
                    <label for="partner_code">取引先 *</label>
                    <select class="form-input" id="partner_code" name="partner_code" required>
                        <option value="">選択してください</option>
                        <?php foreach ($partners as $partner): ?>
                            <option value="<?= htmlspecialchars($partner['code']) ?>">
                                <?= htmlspecialchars($partner['name']) ?>
                                <?= $partner['code'] ? ' (' . htmlspecialchars($partner['code']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="billing_date">請求日 *</label>
                    <input type="date" class="form-input" id="billing_date" name="billing_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="due_date">支払期限</label>
                    <input type="date" class="form-input" id="due_date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                </div>

                <div class="form-group form-grid-full">
                    <label for="title">件名 *</label>
                    <input type="text" class="form-input" id="title" name="title"
                           value="<?= $trouble ? htmlspecialchars($trouble['pjNumber'] . ' - ' . ($trouble['pjName'] ?? '')) : '' ?>"
                           placeholder="例: トラブル対応費用" required>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>明細</h3>

            <table class="items-table" id="items-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">品目・サービス *</th>
                        <th style="width: 15%;">数量 *</th>
                        <th style="width: 20%;">単価 *</th>
                        <th style="width: 20%;">金額</th>
                        <th style="width: 5%;"></th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    <tr class="item-row">
                        <td><input type="text" class="form-input" name="item_name[]" placeholder="トラブル対応費用" required></td>
                        <td><input type="number" class="form-input item-quantity" name="item_quantity[]" value="1" step="0.01" required></td>
                        <td><input type="number" class="form-input item-price" name="item_price[]" value="0" step="0.01" required></td>
                        <td class="item-amount">¥0</td>
                        <td><button type="button" class="btn-icon" onclick="removeItem(this)" title="削除">🗑️</button></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right; font-weight: 600;">小計:</td>
                        <td id="subtotal" style="font-weight: 600;">¥0</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align: right; font-weight: 600;">消費税(10%):</td>
                        <td id="tax" style="font-weight: 600;">¥0</td>
                        <td></td>
                    </tr>
                    <tr style="background: #f9fafb;">
                        <td colspan="3" style="text-align: right; font-weight: 700; font-size: 1.1rem;">合計:</td>
                        <td id="total" style="font-weight: 700; font-size: 1.1rem; color: var(--primary);">¥0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <button type="button" class="add-item-btn" onclick="addItem()">+ 明細を追加</button>
        </div>

        <div class="form-section">
            <h3>備考</h3>

            <div class="form-group">
                <label for="note">備考・特記事項</label>
                <textarea class="form-input" id="note" name="note" rows="4"
                          placeholder="支払い方法、その他特記事項など"><?= $trouble ? htmlspecialchars($trouble['content']) : '' ?></textarea>
            </div>
        </div>

        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
            <a href="list.php" class="btn btn-secondary">キャンセル</a>
            <button type="submit" class="btn btn-primary">請求書を作成</button>
        </div>
    </form>
</div>

<script>
function addItem() {
    const tbody = document.getElementById('items-body');
    const newRow = document.createElement('tr');
    newRow.className = 'item-row';
    newRow.innerHTML = `
        <td><input type="text" class="form-input" name="item_name[]" placeholder="品目・サービス名" required></td>
        <td><input type="number" class="form-input item-quantity" name="item_quantity[]" value="1" step="0.01" required></td>
        <td><input type="number" class="form-input item-price" name="item_price[]" value="0" step="0.01" required></td>
        <td class="item-amount">¥0</td>
        <td><button type="button" class="btn-icon" onclick="removeItem(this)" title="削除">🗑️</button></td>
    `;
    tbody.appendChild(newRow);

    // イベントリスナーを追加
    newRow.querySelectorAll('.item-quantity, .item-price').forEach(input => {
        input.addEventListener('input', calculateTotals);
    });

    calculateTotals();
}

function removeItem(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length > 1) {
        btn.closest('tr').remove();
        calculateTotals();
    } else {
        alert('少なくとも1つの明細が必要です');
    }
}

function calculateTotals() {
    const rows = document.querySelectorAll('.item-row');
    let subtotal = 0;

    rows.forEach(row => {
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const amount = quantity * price;

        row.querySelector('.item-amount').textContent = '¥' + amount.toLocaleString('ja-JP');
        subtotal += amount;
    });

    const tax = Math.floor(subtotal * 0.1);
    const total = subtotal + tax;

    document.getElementById('subtotal').textContent = '¥' + subtotal.toLocaleString('ja-JP');
    document.getElementById('tax').textContent = '¥' + tax.toLocaleString('ja-JP');
    document.getElementById('total').textContent = '¥' + total.toLocaleString('ja-JP');
}

// イベントリスナーを設定
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.item-quantity, .item-price').forEach(input => {
        input.addEventListener('input', calculateTotals);
    });
    calculateTotals();
});
</script>

<?php require_once 'footer.php'; ?>
