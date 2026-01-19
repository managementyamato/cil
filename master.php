<?php
require_once 'config.php';
$data = getData();

$message = '';
$messageType = '';

// PJ追加（詳細情報対応）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pj'])) {
    // 基本情報
    $occurrenceDate = trim($_POST['occurrence_date'] ?? '');
    $transactionType = trim($_POST['transaction_type'] ?? '');

    // 担当・取引先情報
    $salesAssignee = trim($_POST['sales_assignee'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? '');
    $dealerName = trim($_POST['dealer_name'] ?? '');
    $generalContractor = trim($_POST['general_contractor'] ?? '');

    // 現場情報
    $siteName = trim($_POST['site_name'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $prefecture = trim($_POST['prefecture'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $shippingAddress = trim($_POST['shipping_address'] ?? '');

    // 商品情報
    $productCategory = trim($_POST['product_category'] ?? '');
    $productSeries = trim($_POST['product_series'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $productSpec = trim($_POST['product_spec'] ?? '');

    // パートナー情報
    $installPartner = trim($_POST['install_partner'] ?? '');
    $removePartner = trim($_POST['remove_partner'] ?? '');

    // 関連日付
    $contractDate = trim($_POST['contract_date'] ?? '');
    $installScheduleDate = trim($_POST['install_schedule_date'] ?? '');
    $installCompleteDate = trim($_POST['install_complete_date'] ?? '');
    $shippingDate = trim($_POST['shipping_date'] ?? '');
    $installRequestDate = trim($_POST['install_request_date'] ?? '');
    $installDate = trim($_POST['install_date'] ?? '');
    $removeScheduleDate = trim($_POST['remove_schedule_date'] ?? '');
    $removeRequestDate = trim($_POST['remove_request_date'] ?? '');
    $removeDate = trim($_POST['remove_date'] ?? '');
    $removeInspectionDate = trim($_POST['remove_inspection_date'] ?? '');
    $warrantyEndDate = trim($_POST['warranty_end_date'] ?? '');

    // メモ・連絡先
    $memo = trim($_POST['memo'] ?? '');
    $chatUrl = trim($_POST['chat_url'] ?? '');

    // 必須項目チェック（最低限の情報）
    if ($siteName && $customerName) {
        // PJ番号を自動生成（日付ベース）
        $pjNumber = date('Ymd') . '-' . sprintf('%03d', count($data['projects']) + 1);

        $newProject = array(
            'id' => $pjNumber,
            'name' => $siteName,
            // 基本情報
            'occurrence_date' => $occurrenceDate,
            'transaction_type' => $transactionType,
            // 担当・取引先
            'sales_assignee' => $salesAssignee,
            'customer_name' => $customerName,
            'dealer_name' => $dealerName,
            'general_contractor' => $generalContractor,
            // 現場情報
            'postal_code' => $postalCode,
            'prefecture' => $prefecture,
            'address' => $address,
            'shipping_address' => $shippingAddress,
            // 商品情報
            'product_category' => $productCategory,
            'product_series' => $productSeries,
            'product_name' => $productName,
            'product_spec' => $productSpec,
            // パートナー情報
            'install_partner' => $installPartner,
            'remove_partner' => $removePartner,
            // 関連日付
            'contract_date' => $contractDate,
            'install_schedule_date' => $installScheduleDate,
            'install_complete_date' => $installCompleteDate,
            'shipping_date' => $shippingDate,
            'install_request_date' => $installRequestDate,
            'install_date' => $installDate,
            'remove_schedule_date' => $removeScheduleDate,
            'remove_request_date' => $removeRequestDate,
            'remove_date' => $removeDate,
            'remove_inspection_date' => $removeInspectionDate,
            'warranty_end_date' => $warrantyEndDate,
            // メモ・連絡先
            'memo' => $memo,
            'chat_url' => $chatUrl,
            'created_at' => date('Y-m-d H:i:s')
        );

        $data['projects'][] = $newProject;
        saveData($data);
        header('Location: master.php?added=1');
        exit;
    } else {
        $message = '現場名と顧客名は必須です';
        $messageType = 'danger';
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
    <div class="alert alert-success">案件を登録しました</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">案件を削除しました</div>
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

<!-- 案件マスタ -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">案件マスタ <span style="font-size: 0.875rem; color: var(--gray-500);">（<?= count($data['projects']) ?>件）</span></h2>
        <button type="button" class="btn btn-primary" onclick="showAddModal()" style="font-size: 0.875rem; padding: 0.5rem 1rem;">新規登録</button>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>案件番号</th>
                        <th>現場名</th>
                        <th>顧客名</th>
                        <th>営業担当</th>
                        <th>設置予定日</th>
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
                            <td><?= htmlspecialchars($pj['customer_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($pj['sales_assignee'] ?? '-') ?></td>
                            <td><?= !empty($pj['install_schedule_date']) ? date('Y/m/d', strtotime($pj['install_schedule_date'])) : '-' ?></td>
                            <td><?= $troubleCount ?>件</td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('削除しますか？');">
                                    <input type="hidden" name="delete_pj" value="<?= htmlspecialchars($pj['id']) ?>">
                                    <button type="submit" class="btn-icon" title="削除">削除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($data['projects'])): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--gray-500);">データがありません</td>
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

<!-- 案件登録モーダル（詳細版） -->
<div id="addModal" class="modal">
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3>案件登録</h3>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="add_pj" value="1">
            <div class="modal-body">

                <!-- 基本情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">基本情報</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="occurrence_date">案件発生日 *</label>
                            <input type="date" class="form-input" id="occurrence_date" name="occurrence_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="transaction_type">取引形態 *</label>
                            <select class="form-select" id="transaction_type" name="transaction_type" required>
                                <option value="">選択してください</option>
                                <option value="販売">販売</option>
                                <option value="レンタル">レンタル</option>
                                <option value="保守">保守</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 担当・取引先情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">担当・取引先情報</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="sales_assignee">営業担当 *</label>
                            <select class="form-select" id="sales_assignee" name="sales_assignee" required>
                                <option value="">選択してください</option>
                                <?php foreach ($data['assignees'] as $a): ?>
                                    <option value="<?= htmlspecialchars($a['name']) ?>"><?= htmlspecialchars($a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="customer_name">顧客名 *</label>
                            <select class="form-select" id="customer_name" name="customer_name" required>
                                <option value="">選択してください</option>
                                <?php foreach ($data['customers'] as $c): ?>
                                    <option value="<?= htmlspecialchars($c['companyName']) ?>"><?= htmlspecialchars($c['companyName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="dealer_name">ディーラー担当者名</label>
                            <input type="text" class="form-input" id="dealer_name" name="dealer_name">
                        </div>
                        <div class="form-group">
                            <label for="general_contractor">ゼネコン名</label>
                            <input type="text" class="form-input" id="general_contractor" name="general_contractor">
                        </div>
                    </div>
                </div>

                <!-- 現場情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">現場情報</h4>
                    <div class="form-group">
                        <label for="site_name">現場名 *</label>
                        <input type="text" class="form-input" id="site_name" name="site_name" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="postal_code">郵便番号</label>
                            <input type="text" class="form-input" id="postal_code" name="postal_code" placeholder="例: 1000001">
                        </div>
                        <div class="form-group">
                            <label for="prefecture">設置場所（都道府県）</label>
                            <input type="text" class="form-input" id="prefecture" name="prefecture">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">設置場所住所</label>
                        <input type="text" class="form-input" id="address" name="address">
                    </div>
                    <div class="form-group">
                        <label for="shipping_address">発送先住所</label>
                        <input type="text" class="form-input" id="shipping_address" name="shipping_address">
                    </div>
                </div>

                <!-- 商品情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: #2563eb;">商品情報</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="product_category">商品カテゴリ（大分類）</label>
                            <select class="form-select" id="product_category" name="product_category">
                                <option value="">カテゴリを選択</option>
                                <?php foreach ($data['productCategories'] as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="product_series">製品シリーズ（中分類）</label>
                            <select class="form-select" id="product_series" name="product_series">
                                <option value="">シリーズを選択</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="product_name">本体商品名（小分類）</label>
                            <select class="form-select" id="product_name" name="product_name">
                                <option value="">商品を選択</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="product_spec">製品仕様（自由記述）</label>
                            <input type="text" class="form-input" id="product_spec" name="product_spec" placeholder="自由に入力してください">
                        </div>
                    </div>
                </div>

                <!-- パートナー情報 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">パートナー情報</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="install_partner">設置時パートナー</label>
                            <select class="form-select" id="install_partner" name="install_partner">
                                <option value="">選択してください</option>
                                <?php foreach ($data['partners'] as $p): ?>
                                    <option value="<?= htmlspecialchars($p['companyName']) ?>"><?= htmlspecialchars($p['companyName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="remove_partner">撤去時パートナー</label>
                            <select class="form-select" id="remove_partner" name="remove_partner">
                                <option value="">選択してください</option>
                                <?php foreach ($data['partners'] as $p): ?>
                                    <option value="<?= htmlspecialchars($p['companyName']) ?>"><?= htmlspecialchars($p['companyName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 関連日付 -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">関連日付</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="contract_date">成約日</label>
                            <input type="date" class="form-input" id="contract_date" name="contract_date">
                        </div>
                        <div class="form-group">
                            <label for="install_schedule_date">設置予定日</label>
                            <input type="date" class="form-input" id="install_schedule_date" name="install_schedule_date">
                        </div>
                        <div class="form-group">
                            <label for="install_complete_date">設定作業完了日</label>
                            <input type="date" class="form-input" id="install_complete_date" name="install_complete_date">
                        </div>
                        <div class="form-group">
                            <label for="shipping_date">発送日</label>
                            <input type="date" class="form-input" id="shipping_date" name="shipping_date">
                        </div>
                        <div class="form-group">
                            <label for="install_request_date">設置業務依頼日</label>
                            <input type="date" class="form-input" id="install_request_date" name="install_request_date">
                        </div>
                        <div class="form-group">
                            <label for="install_date">設置日</label>
                            <input type="date" class="form-input" id="install_date" name="install_date">
                        </div>
                        <div class="form-group">
                            <label for="remove_schedule_date">撤去予定日</label>
                            <input type="date" class="form-input" id="remove_schedule_date" name="remove_schedule_date">
                        </div>
                        <div class="form-group">
                            <label for="remove_request_date">撤去業務依頼日</label>
                            <input type="date" class="form-input" id="remove_request_date" name="remove_request_date">
                        </div>
                        <div class="form-group">
                            <label for="remove_date">撤去日</label>
                            <input type="date" class="form-input" id="remove_date" name="remove_date">
                        </div>
                        <div class="form-group">
                            <label for="remove_inspection_date">撤去後検品日</label>
                            <input type="date" class="form-input" id="remove_inspection_date" name="remove_inspection_date">
                        </div>
                        <div class="form-group">
                            <label for="warranty_end_date">販売時保証終了日</label>
                            <input type="date" class="form-input" id="warranty_end_date" name="warranty_end_date">
                        </div>
                    </div>
                </div>

                <!-- メモ -->
                <div style="border-bottom: 2px solid var(--gray-200); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">メモ</h4>
                    <div class="form-group">
                        <textarea class="form-input" id="memo" name="memo" rows="4" placeholder="特記事項など"></textarea>
                    </div>
                </div>

                <!-- 連絡用チャットURL -->
                <div>
                    <h4 style="margin-bottom: 1rem; color: var(--gray-900);">連絡用チャットURL</h4>
                    <div class="form-group">
                        <input type="url" class="form-input" id="chat_url" name="chat_url" placeholder="https://slack.com/... など連絡用リンクがあれば入力してください">
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">キャンセル</button>
                <button type="submit" class="btn btn-primary">確認画面へ</button>
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
