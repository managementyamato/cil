<?php
/**
 * 【ページ名】ページ
 *
 * 新規ページ作成時のテンプレート
 * 使い方: このファイルをコピーして pages/ に配置し、__PLACEHOLDER__ を置換する
 *
 * 置換一覧:
 *   __ENTITY__      → データキー名 (例: troubles, contacts)
 *   __PAGE_TITLE__  → ページタイトル (例: トラブル対応一覧)
 *
 * チェックリスト:
 *   1. このファイルを pages/xxx.php にコピー
 *   2. __ENTITY__ と __PAGE_TITLE__ を全て置換
 *   3. api/auth.php の $defaultPagePermissions に権限を追加
 *   4. functions/header.php のサイドバーにリンクを追加
 *   5. functions/data-schema.php にエンティティを追加（必要に応じて）
 *   6. テスト実行: C:\xampp\php\php.exe vendor/bin/phpunit
 */
require_once '../api/auth.php';
require_once '../functions/api-middleware.php';
// api-middleware.phpのエラーハンドラはAPIファイル専用のため、ページファイルではリセット
set_error_handler(null);
set_exception_handler(null);

// セキュリティヘッダーを設定（HTML出力前に実行）
setSecurityHeaders();

// --- データ読み込み ---
$data = getData();
$items = filterDeleted($data['__ENTITY__'] ?? []);

// --- ソート ---
$sortBy = $_GET['sort'] ?? 'created_at';
$sortDir = $_GET['dir'] ?? 'desc';
usort($items, function($a, $b) use ($sortBy, $sortDir) {
    $valA = $a[$sortBy] ?? '';
    $valB = $b[$sortBy] ?? '';
    $cmp = strcmp($valA, $valB);
    return $sortDir === 'asc' ? $cmp : -$cmp;
});

// --- フィルター ---
$searchKeyword = $_GET['search'] ?? '';
if (!empty($searchKeyword)) {
    $items = array_filter($items, function($item) use ($searchKeyword) {
        // TODO: 検索対象フィールドを指定
        return stripos($item['name'] ?? '', $searchKeyword) !== false;
    });
}

// --- POST処理（フォーム送信） ---
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        if (!canEditCurrentPage() || !canEdit()) {
            $message = '編集権限がありません';
            $messageType = 'danger';
        } else {
            $newItem = [
                'id' => uniqid(),
                // TODO: フィールドを追加
                'name' => trim($_POST['name'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['user_email'] ?? '',
            ];
            $data['__ENTITY__'][] = $newItem;
            saveData($data);
            $message = '追加しました';
            $messageType = 'success';
            // リダイレクト（二重送信防止）
            header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=' . urlencode($message));
            exit;
        }
    }

    if ($action === 'edit') {
        if (!canEditCurrentPage() || !canEdit()) {
            $message = '編集権限がありません';
            $messageType = 'danger';
        } else {
            $editId = $_POST['id'] ?? '';
            foreach ($data['__ENTITY__'] as &$item) {
                if ($item['id'] === $editId) {
                    // TODO: 更新フィールドを指定
                    $item['name'] = trim($_POST['name'] ?? '');
                    $item['updated_at'] = date('Y-m-d H:i:s');
                    $item['updated_by'] = $_SESSION['user_email'] ?? '';
                    break;
                }
            }
            unset($item);
            saveData($data);
            $message = '更新しました';
            $messageType = 'success';
            header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=' . urlencode($message));
            exit;
        }
    }

    if ($action === 'delete') {
        if (!canDelete()) {
            $message = '削除権限がありません';
            $messageType = 'danger';
        } else {
            $deleteId = $_POST['id'] ?? '';
            // ソフトデリート
            foreach ($data['__ENTITY__'] as &$item) {
                if ($item['id'] === $deleteId) {
                    $item['deleted_at'] = date('Y-m-d H:i:s');
                    $item['deleted_by'] = $_SESSION['user_email'] ?? '';
                    break;
                }
            }
            unset($item);
            saveData($data);
            $message = '削除しました';
            $messageType = 'success';
            header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=' . urlencode($message));
            exit;
        }
    }
}

// GETパラメータからのメッセージ表示（リダイレクト後）
if (!empty($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = 'success';
}

// 権限変数
$isAdmin = isAdmin();
$canEditPage = canEditCurrentPage() && canEdit();
$canDel = canDelete();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>__PAGE_TITLE__</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">
    <link rel="stylesheet" href="/style.css?v=20260206">
    <link rel="stylesheet" href="/css/components.css?v=20260211">
    <style<?= nonceAttr() ?>>
        /* ページ固有のスタイル */
    </style>
</head>
<body>
<?php renderSidebar(); ?>

<div class="main-content">
<div class="page-container">

    <!-- ページヘッダー -->
    <div class="page-header">
        <h2>__PAGE_TITLE__</h2>
        <div class="page-header-actions">
            <?php if ($canEditPage): ?>
            <button type="button" class="btn btn-primary" data-action="openAddModal">新規追加</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- メッセージ表示 -->
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- 検索・フィルター -->
    <div class="filters" style="background:white; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); margin-bottom:20px;">
        <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <input type="text" name="search" class="form-input" placeholder="キーワード検索..."
                   value="<?= htmlspecialchars($searchKeyword) ?>" style="max-width:300px;">
            <!-- TODO: フィルター用のselectを追加 -->
            <button type="submit" class="btn btn-primary">検索</button>
            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">リセット</a>
        </form>
    </div>

    <!-- データテーブル -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <!-- TODO: テーブルヘッダーを追加 -->
                    <th>名前</th>
                    <th>作成日</th>
                    <?php if ($canEditPage): ?>
                    <th>操作</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="3" class="text-center text-muted p-2rem">
                        データがありません
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <!-- TODO: テーブルデータを追加 -->
                    <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['created_at'] ?? '') ?></td>
                    <?php if ($canEditPage): ?>
                    <td>
                        <button class="btn btn-sm btn-secondary" data-action="edit"
                                data-id="<?= htmlspecialchars($item['id']) ?>"
                                data-item='<?= htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE)) ?>'>
                            編集
                        </button>
                        <?php if ($canDel): ?>
                        <button class="btn btn-sm btn-danger" data-action="delete"
                                data-id="<?= htmlspecialchars($item['id']) ?>">
                            削除
                        </button>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</div>

<!-- 追加モーダル -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>新規追加</h3>
            <button type="button" class="close" data-close-modal="addModal">&times;</button>
        </div>
        <form id="addForm" method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <!-- TODO: フォームフィールドを追加 -->
                <div class="form-group">
                    <label for="addName">名前 <span class="required">*</span></label>
                    <input type="text" id="addName" name="name" class="form-input" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="addModal">キャンセル</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>編集</h3>
            <button type="button" class="close" data-close-modal="editModal">&times;</button>
        </div>
        <form id="editForm" method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="modal-body">
                <!-- TODO: フォームフィールドを追加（addModalと同じ構成） -->
                <div class="form-group">
                    <label for="editName">名前 <span class="required">*</span></label>
                    <input type="text" id="editName" name="name" class="form-input" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="editModal">キャンセル</button>
                <button type="submit" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 削除確認モーダル -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>削除確認</h3>
            <button type="button" class="close" data-close-modal="deleteModal">&times;</button>
        </div>
        <form id="deleteForm" method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <div class="modal-body">
                <p>本当に削除しますか？この操作は取り消せません。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="deleteModal">キャンセル</button>
                <button type="submit" class="btn btn-danger">削除</button>
            </div>
        </form>
    </div>
</div>

<script<?= nonceAttr() ?>>
// --- イベントデリゲーション ---
document.addEventListener('click', e => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;

    switch (action) {
        case 'openAddModal':
            document.getElementById('addForm').reset();
            openModal('addModal');
            break;
        case 'edit': {
            const item = JSON.parse(btn.dataset.item);
            document.getElementById('editId').value = item.id;
            // TODO: 編集フォームにデータをセット
            document.getElementById('editName').value = item.name || '';
            openModal('editModal');
            break;
        }
        case 'delete':
            document.getElementById('deleteId').value = btn.dataset.id;
            openModal('deleteModal');
            break;
    }
});

// --- モーダル制御 ---
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById(btn.dataset.closeModal).classList.remove('active');
    });
});

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) modal.classList.remove('active');
    });
});

// --- escapeHtml ---
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

</body>
</html>
