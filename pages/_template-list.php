<?php
/**
 * テンプレート: 一覧 / 検索 / CRUD 型ページ
 *
 * 使い方:
 *   1. このファイルを pages/<新ページ名>.php にコピー
 *   2. 先頭の die() ブロックと「TEMPLATE:」コメントを削除
 *   3. <NEW_PAGE_TITLE> / <NEW_PAGE_ICON_SVG> / <ENTITY> を置換
 *   4. api/auth.php の $defaultPagePermissions に新ページのキーを追加
 *   5. pages/user-permissions.php の対象キーリストに新ページを追加
 *      (feedback_new_page_permissions.md)
 *   6. functions/header.php のサイドバーリンクを追加
 *
 * 形式統一の必須ルール (CLAUDE.md / docs/patterns.md / docs/ui-legacy-classes.md):
 *   - <input>/<select>/<textarea> は class="form-input" + <div class="form-group">
 *   - 表は class="data-table" (独自 *-table クラスは新規禁止)
 *   - ボタンは btn / btn-primary / btn-secondary / btn-danger + 必要なら btn-sm
 *   - 行アクションは iconButton() を使用 (onclick 禁止・data 属性で受ける)
 *   - 削除は canDelete()・更新は canEdit() で必ず権限チェック
 *   - POST を受ける場合は verifyCsrfToken() 必須
 */

// ── テンプレート直接アクセス防止 (コピー後は削除する) ──
if (basename($_SERVER['PHP_SELF']) === '_template-list.php') {
    http_response_code(404);
    exit('Template file. Copy to pages/<your-page>.php before use.');
}

require_once '../api/auth.php';

// ページ閲覧権限チェック (auth.php が自動でやる場合は不要)
// if (!canViewCurrentPage()) { header('Location: index.php'); exit; }

$message = '';
$messageType = '';

// ── POST 処理 ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    // 作成 / 更新
    if (isset($_POST['save_item'])) {
        if (!canEditCurrentPage()) {
            $message = '編集権限がありません';
            $messageType = 'danger';
        } else {
            // TODO: バリデーション + saveData() で保存
            // $data = getData();
            // $data['<ENTITY>'][] = [...];
            // saveData($data);
            $message = '保存しました';
            $messageType = 'success';
        }
    }

    // 削除 (論理削除)
    if (isset($_POST['delete_item'])) {
        if (!canDelete()) {
            $message = '削除権限がありません';
            $messageType = 'danger';
        } else {
            // TODO: 論理削除 (deleted_at をセット)
            $message = '削除しました';
            $messageType = 'success';
        }
    }
}

// ── 一覧データ取得 ────────────────────────────────────────
$filters = [
    'keyword'   => trim($_GET['keyword'] ?? ''),
    'status'    => $_GET['status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to'   => $_GET['date_to'] ?? '',
];
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;

// TODO: 実データ取得関数に差し替え
// $result = getFiltered<ENTITY>($filters, $page, $perPage);
$result = ['items' => [], 'total' => 0, 'page' => $page, 'total_pages' => 1];

require_once '../functions/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h2>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <!-- <NEW_PAGE_ICON_SVG> -->
                <rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/>
            </svg>
            <NEW_PAGE_TITLE>
            <span class="page-count">(<?= number_format($result['total']) ?>件)</span>
        </h2>
        <?php if (canEditCurrentPage()): ?>
        <button type="button" class="btn btn-primary btn-sm" data-action="open-create-modal">
            新規追加
        </button>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- 検索フィルター -->
    <form class="filter-bar" method="get">
        <div class="form-group">
            <label for="keyword">キーワード</label>
            <input type="text" id="keyword" name="keyword" class="form-input"
                   value="<?= htmlspecialchars($filters['keyword']) ?>" placeholder="名称・コード等">
        </div>
        <div class="form-group">
            <label for="status">ステータス</label>
            <select id="status" name="status" class="form-input">
                <option value="">すべて</option>
                <option value="active"   <?= $filters['status'] === 'active'   ? 'selected' : '' ?>>有効</option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>無効</option>
            </select>
        </div>
        <div class="form-group">
            <label for="date_from">期間 (開始)</label>
            <input type="date" id="date_from" name="date_from" class="form-input"
                   value="<?= htmlspecialchars($filters['date_from']) ?>">
        </div>
        <div class="form-group">
            <label for="date_to">期間 (終了)</label>
            <input type="date" id="date_to" name="date_to" class="form-input"
                   value="<?= htmlspecialchars($filters['date_to']) ?>">
        </div>
        <div class="form-group filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">検索</button>
            <a href="?" class="btn btn-secondary btn-sm">クリア</a>
        </div>
    </form>

    <!-- 一覧テーブル -->
    <div class="data-table-wrapper">
        <?php if (empty($result['items'])): ?>
            <div class="empty-state">該当データがありません</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>名称</th>
                        <th>ステータス</th>
                        <th>更新日</th>
                        <th class="data-table-actions">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['items'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['status'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['updated_at'] ?? '') ?></td>
                        <td class="data-table-actions">
                            <?php
                            // 行アクションは onclick 禁止 (CLAUDE.md) → data 属性 + 末尾のデリゲーションで受ける
                            $itemJson = htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                            ?>
                            <?php if (canEditCurrentPage()): ?>
                            <button type="button" class="btn btn-sm btn-secondary"
                                    data-action="edit"
                                    data-id="<?= htmlspecialchars($item['id'] ?? '') ?>"
                                    data-item="<?= $itemJson ?>"
                                    title="編集">編集</button>
                            <?php endif; ?>
                            <?php if (canDelete()): ?>
                            <button type="button" class="btn btn-sm btn-danger"
                                    data-action="delete"
                                    data-id="<?= htmlspecialchars($item['id'] ?? '') ?>"
                                    title="削除">削除</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- ページネーション -->
            <?php if ($result['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="ページネーション">
                <?php
                $qp = $filters;
                if ($result['page'] > 1):
                    $qp['page'] = $result['page'] - 1;
                ?>
                <a href="?<?= http_build_query($qp) ?>">&laquo; 前へ</a>
                <?php endif; ?>

                <span class="current"><?= $result['page'] ?> / <?= $result['total_pages'] ?></span>

                <?php if ($result['page'] < $result['total_pages']):
                    $qp['page'] = $result['page'] + 1;
                ?>
                <a href="?<?= http_build_query($qp) ?>">次へ &raquo;</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div><!-- /.page-container -->

<!-- 作成/編集モーダル (canonical: .modal + .modal-content + .modal-header/body/footer) -->
<!-- 表示は .modal.active で切り替え、JS は js/common-utils.js の openModal()/closeModal() を使う -->
<div class="modal" id="item-modal" role="dialog" aria-modal="true" aria-labelledby="item-modal-title">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="item-modal-title">新規追加</h3>
            <button type="button" class="modal-close" data-action="close-modal" data-target="item-modal" aria-label="閉じる">&times;</button>
        </div>
        <form method="POST" action="" id="item-form">
            <?= csrfTokenField() ?>
            <input type="hidden" name="id" id="item-id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="m-name">名称</label>
                    <input type="text" id="m-name" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="m-status">ステータス</label>
                    <select id="m-status" name="status" class="form-input">
                        <option value="active">有効</option>
                        <option value="inactive">無効</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-action="close-modal" data-target="item-modal">キャンセル</button>
                <button type="submit" name="save_item" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 削除確認モーダル -->
<form class="modal" id="delete-modal" role="dialog" aria-modal="true" method="POST" action="">
    <div class="modal-content">
        <div class="modal-header">
            <h3>削除確認</h3>
            <button type="button" class="modal-close" data-action="close-modal" data-target="delete-modal" aria-label="閉じる">&times;</button>
        </div>
        <div class="modal-body">
            <?= csrfTokenField() ?>
            <input type="hidden" name="delete_item" id="delete-id" value="">
            <p>本当に削除しますか？この操作は取り消せません。</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-action="close-modal" data-target="delete-modal">キャンセル</button>
            <button type="submit" class="btn btn-danger">削除</button>
        </div>
    </div>
</form>

<script<?= nonceAttr() ?>>
// モーダル制御 (onclick 禁止: data-action のイベントデリゲーション)
// openModal / closeModal は js/common-utils.js のグローバル関数 (header.php で全ページに自動ロード)
document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;

    switch (action) {
        case 'open-create-modal':
            document.getElementById('item-form').reset();
            document.getElementById('item-id').value = '';
            document.getElementById('item-modal-title').textContent = '新規追加';
            openModal('item-modal');
            break;

        case 'edit': {
            const item = JSON.parse(trigger.dataset.item || '{}');
            document.getElementById('item-id').value = item.id || '';
            document.getElementById('m-name').value = item.name || '';
            document.getElementById('m-status').value = item.status || 'active';
            document.getElementById('item-modal-title').textContent = '編集';
            openModal('item-modal');
            break;
        }

        case 'delete':
            document.getElementById('delete-id').value = trigger.dataset.id || '';
            openModal('delete-modal');
            break;

        case 'close-modal':
            closeModal(trigger.dataset.target);
            break;
    }
});

// 規約: モーダルは × / キャンセル / 保存 ボタンの明示操作でしか閉じない
//   - 背景クリックでは閉じない (入力途中で誤操作消失を防ぐ。js/common-utils.js:38-41 参照)
//   - Esc キーでも閉じない (同上)
// この方針に逆らうハンドラをここに書かないこと
</script>

<?php require_once '../functions/footer.php'; ?>
