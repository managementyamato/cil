<?php
require_once 'config.php';
$data = getData();

$message = '';
$messageType = '';

// カテゴリ追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $categoryName = trim($_POST['category_name'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');

    if ($categoryName && $productName) {
        // カテゴリIDを自動生成
        $maxId = 0;
        foreach ($data['productCategories'] as $cat) {
            if ($cat['id'] > $maxId) $maxId = $cat['id'];
        }

        $newCategory = array(
            'id' => $maxId + 1,
            'categoryName' => $categoryName,
            'products' => array(
                array('name' => $productName)
            )
        );

        $data['productCategories'][] = $newCategory;
        saveData($data);
        $message = 'カテゴリを追加しました';
        $messageType = 'success';
    } else {
        $message = 'カテゴリ名と商品名は必須です';
        $messageType = 'danger';
    }
}

// 商品追加（既存カテゴリに）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $categoryId = (int)$_POST['category_id'];
    $productName = trim($_POST['product_name'] ?? '');

    if ($productName) {
        foreach ($data['productCategories'] as $key => $category) {
            if ($category['id'] === $categoryId) {
                $data['productCategories'][$key]['products'][] = array('name' => $productName);
                saveData($data);
                $message = '商品を追加しました';
                $messageType = 'success';
                break;
            }
        }
    } else {
        $message = '商品名は必須です';
        $messageType = 'danger';
    }
}

// カテゴリ編集
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $categoryId = (int)$_POST['category_id'];
    $categoryName = trim($_POST['category_name'] ?? '');

    if ($categoryName) {
        foreach ($data['productCategories'] as $key => $category) {
            if ($category['id'] === $categoryId) {
                $data['productCategories'][$key]['categoryName'] = $categoryName;
                saveData($data);
                $message = 'カテゴリ名を更新しました';
                $messageType = 'success';
                break;
            }
        }
    } else {
        $message = 'カテゴリ名は必須です';
        $messageType = 'danger';
    }
}

// 商品編集
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $categoryId = (int)$_POST['category_id'];
    $productIndex = (int)$_POST['product_index'];
    $productName = trim($_POST['product_name'] ?? '');

    if ($productName) {
        foreach ($data['productCategories'] as $catKey => $category) {
            if ($category['id'] === $categoryId) {
                $data['productCategories'][$catKey]['products'][$productIndex]['name'] = $productName;
                saveData($data);
                $message = '商品名を更新しました';
                $messageType = 'success';
                break;
            }
        }
    } else {
        $message = '商品名は必須です';
        $messageType = 'danger';
    }
}

// カテゴリ削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $deleteId = (int)$_POST['delete_category'];
    $data['productCategories'] = array_values(array_filter($data['productCategories'], function($c) use ($deleteId) {
        return $c['id'] !== $deleteId;
    }));
    saveData($data);
    $message = 'カテゴリを削除しました';
    $messageType = 'success';
}

// 商品削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $categoryId = (int)$_POST['category_id'];
    $productIndex = (int)$_POST['product_index'];

    foreach ($data['productCategories'] as $catKey => $category) {
        if ($category['id'] === $categoryId) {
            array_splice($data['productCategories'][$catKey]['products'], $productIndex, 1);
            saveData($data);
            $message = '商品を削除しました';
            $messageType = 'success';
            break;
        }
    }
}

require_once 'header.php';
?>

<style>
.master-container {
    max-width: 1200px;
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

.category-item {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.2s;
}

.category-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.category-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.category-badge {
    background: #667eea;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.category-name {
    font-size: 1.1rem;
    font-weight: 600;
    flex: 1;
}

.category-actions {
    display: flex;
    gap: 0.5rem;
}

.product-list {
    margin-top: 0.5rem;
    padding-left: 2rem;
}

.product-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem;
    margin: 0.25rem 0;
    border-radius: 4px;
}

.product-item:hover {
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

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.btn-primary {
    background: #3182ce;
    color: white;
}

.btn-primary:hover {
    background: #2c5282;
}

.btn-success {
    background: #48bb78;
    color: white;
}

.btn-success:hover {
    background: #38a169;
}

.btn-danger {
    background: #e53e3e;
    color: white;
}

.btn-danger:hover {
    background: #c53030;
}

.btn-edit {
    background: #4299e1;
    color: white;
}

.btn-edit:hover {
    background: #3182ce;
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
    max-width: 500px;
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

.help-text {
    font-size: 0.75rem;
    color: #718096;
    margin-top: 0.25rem;
}
</style>

<div class="master-container">
    <h1>カテゴリマスタ管理</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="padding: 1rem; margin-bottom: 1rem; border-radius: 4px; background: <?= $messageType === 'success' ? '#c6f6d5' : '#fed7d7' ?>; color: <?= $messageType === 'success' ? '#22543d' : '#742a2a' ?>;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 class="card-title" style="margin: 0;">カテゴリ一覧</h2>
            <button class="btn btn-primary" onclick="openAddCategoryModal()">+ 大分類を追加</button>
        </div>

        <?php if (empty($data['productCategories'])): ?>
            <p style="text-align: center; color: #718096; padding: 2rem;">登録されているカテゴリはありません</p>
        <?php else: ?>
            <?php foreach ($data['productCategories'] as $category): ?>
                <div class="category-item">
                    <div class="category-header">
                        <span class="category-badge">LARGE</span>
                        <span class="category-name"><?= htmlspecialchars($category['categoryName']) ?></span>
                        <div class="category-actions">
                            <button class="btn btn-sm btn-success" onclick='openAddProductModal(<?= $category['id'] ?>, "<?= htmlspecialchars($category['categoryName']) ?>")'>+</button>
                            <button class="btn btn-sm btn-edit" onclick='openEditCategoryModal(<?= json_encode($category) ?>)'>編集</button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('このカテゴリと配下の商品を全て削除してもよろしいですか？');">
                                <button type="submit" name="delete_category" value="<?= $category['id'] ?>" class="btn btn-sm btn-danger">削除</button>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($category['products'])): ?>
                        <div class="product-list">
                            <?php foreach ($category['products'] as $index => $product): ?>
                                <div class="product-item">
                                    <span style="flex: 1;">• <?= htmlspecialchars($product['name']) ?></span>
                                    <button class="btn btn-sm btn-edit" onclick='openEditProductModal(<?= $category['id'] ?>, <?= $index ?>, "<?= htmlspecialchars($product['name']) ?>")'>編集</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('この商品を削除してもよろしいですか？');">
                                        <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                        <input type="hidden" name="product_index" value="<?= $index ?>">
                                        <button type="submit" name="delete_product" class="btn btn-sm btn-danger">削除</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- カテゴリ追加モーダル -->
<div id="addCategoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">大分類を追加</div>
        <form method="POST">
            <div class="form-group">
                <label>カテゴリ名 <span class="required">*</span></label>
                <input type="text" name="category_name" placeholder="例: ハード" required>
                <p class="help-text">製品の種類（ハード、ソフトなど）</p>
            </div>

            <div class="form-group">
                <label>商品名 <span class="required">*</span></label>
                <input type="text" name="product_name" placeholder="例: モニたろう" required>
                <p class="help-text">商品ブランド名</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddCategoryModal()">キャンセル</button>
                <button type="submit" name="add_category" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- カテゴリ編集モーダル -->
<div id="editCategoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">カテゴリ編集</div>
        <form method="POST">
            <input type="hidden" name="category_id" id="edit_category_id">

            <div class="form-group">
                <label>カテゴリ名 <span class="required">*</span></label>
                <input type="text" name="category_name" id="edit_category_name" required>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditCategoryModal()">キャンセル</button>
                <button type="submit" name="edit_category" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 商品追加モーダル -->
<div id="addProductModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">商品を追加</div>
        <form method="POST">
            <input type="hidden" name="category_id" id="add_product_category_id">

            <div class="form-group">
                <label>カテゴリ</label>
                <input type="text" id="add_product_category_name" disabled>
            </div>

            <div class="form-group">
                <label>商品名 <span class="required">*</span></label>
                <input type="text" name="product_name" placeholder="例: モニたろう" required>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddProductModal()">キャンセル</button>
                <button type="submit" name="add_product" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- 商品編集モーダル -->
<div id="editProductModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">商品編集</div>
        <form method="POST">
            <input type="hidden" name="category_id" id="edit_product_category_id">
            <input type="hidden" name="product_index" id="edit_product_index">

            <div class="form-group">
                <label>商品名 <span class="required">*</span></label>
                <input type="text" name="product_name" id="edit_product_name" required>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditProductModal()">キャンセル</button>
                <button type="submit" name="edit_product" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddCategoryModal() {
    document.getElementById('addCategoryModal').classList.add('active');
}

function closeAddCategoryModal() {
    document.getElementById('addCategoryModal').classList.remove('active');
}

function openEditCategoryModal(category) {
    document.getElementById('edit_category_id').value = category.id;
    document.getElementById('edit_category_name').value = category.categoryName;
    document.getElementById('editCategoryModal').classList.add('active');
}

function closeEditCategoryModal() {
    document.getElementById('editCategoryModal').classList.remove('active');
}

function openAddProductModal(categoryId, categoryName) {
    document.getElementById('add_product_category_id').value = categoryId;
    document.getElementById('add_product_category_name').value = categoryName;
    document.getElementById('addProductModal').classList.add('active');
}

function closeAddProductModal() {
    document.getElementById('addProductModal').classList.remove('active');
}

function openEditProductModal(categoryId, productIndex, productName) {
    document.getElementById('edit_product_category_id').value = categoryId;
    document.getElementById('edit_product_index').value = productIndex;
    document.getElementById('edit_product_name').value = productName;
    document.getElementById('editProductModal').classList.add('active');
}

function closeEditProductModal() {
    document.getElementById('editProductModal').classList.remove('active');
}

// モーダル外クリックで閉じる
document.querySelectorAll('.modal').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php require_once 'footer.php'; ?>
