<?php
/**
 * 社内連絡先ページ
 * 閲覧: 全ユーザー / 追加・編集・削除: 管理部のみ
 */
require_once '../api/auth.php';
require_once '../functions/header.php';
require_once '../api/google-gmail.php';

$isAdmin   = isAdmin();
$csrfToken = generateCsrfToken();
$gmailConfigured = (new GoogleGmailClient())->isConfigured();

$data     = getData();
$contacts = array_values(array_filter($data['contacts'] ?? [], fn($r) => empty($r['deleted_at'])));
usort($contacts, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

// 従業員リスト（在籍中・メールあり）をメール送信用に取得
$todayDate = date('Y-m-d');
$employees = [];
foreach ($data['employees'] ?? [] as $emp) {
    if (!empty($emp['leave_date']) && $emp['leave_date'] <= $todayDate) continue;
    $empEmail = $emp['email'] ?? '';
    // 暗号化メールの復号
    if (is_string($empEmail) && str_starts_with($empEmail, 'enc:')) {
        require_once __DIR__ . '/../functions/encryption.php';
        try { $empEmail = decryptValue($empEmail); } catch (Exception $e) { continue; }
    }
    if (!$empEmail) continue;
    $employees[] = ['name' => $emp['name'] ?? '', 'email' => $empEmail, 'dept' => $emp['department'] ?? ''];
}
usort($employees, fn($a, $b) => strcmp($a['name'], $b['name']));
// メール→名前のマッピング
$emailToName = [];
foreach ($employees as $emp) {
    $emailToName[$emp['email']] = $emp['name'];
}

// カテゴリ一覧（表示順維持）
$categories = [];
foreach ($contacts as $c) {
    if (!in_array($c['category'], $categories, true)) {
        $categories[] = $c['category'];
    }
}
?>
<style<?= nonceAttr() ?>>
.ct-wrap { display: flex; min-height: calc(100vh - 60px); }

/* 左ナビ */
.ct-nav {
    width: 200px; flex-shrink: 0;
    background: #fff; border-right: 1px solid var(--gray-200);
    padding: 1.25rem 0;
    position: sticky; top: 60px; height: calc(100vh - 60px); overflow-y: auto;
}
.ct-nav-label { font-size: 0.7rem; font-weight: 700; color: var(--gray-400); padding: 0 1rem 0.5rem; letter-spacing: .06em; }
.ct-nav-item {
    display: block; padding: 0.5rem 1rem; font-size: 0.85rem; color: #374151;
    border-left: 3px solid transparent; text-decoration: none;
    transition: background .1s, border-color .1s;
}
.ct-nav-item:hover { background: var(--gray-50); }
.ct-nav-item.active { background: #eff6ff; border-left-color: var(--primary); color: var(--primary); font-weight: 600; }

/* メイン */
.ct-main { flex: 1; min-width: 0; padding: 1.5rem 2rem; max-width: 1200px; }
.ct-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
.ct-header h2 { font-size: 1.25rem; font-weight: 700; }

/* 検索 */
.ct-search {
    width: 100%; max-width: 360px; padding: 0.5rem 0.85rem;
    border: 1px solid var(--gray-200); border-radius: 8px;
    font-size: 0.875rem; outline: none; margin-bottom: 1.5rem;
    transition: border-color .15s;
}
.ct-search:focus { border-color: var(--primary); }

/* カテゴリセクション */
.ct-section { margin-bottom: 2rem; scroll-margin-top: 70px; }
.ct-section-head {
    display: flex; justify-content: space-between; align-items: center;
    background: var(--gray-50); border-left: 4px solid var(--primary);
    border-radius: 0 6px 6px 0; padding: 0.55rem 0.85rem; margin-bottom: 0.5rem;
}
.ct-section-head span { font-size: 0.95rem; font-weight: 700; color: var(--gray-800); }

/* テーブル */
.ct-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; background: #fff; border: 1px solid var(--gray-200); border-radius: 8px; overflow: hidden; }
.ct-table th { background: var(--gray-50); padding: 0.5rem 0.85rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
.ct-table td { padding: 0.6rem 0.85rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; white-space: nowrap; }
.ct-table tbody tr:last-child td { border-bottom: none; }
.ct-table tbody tr:hover { background: var(--gray-50); }
.ext-badge { font-family: monospace; font-size: 0.85rem; font-weight: 700; color: var(--primary); background: #eff6ff; padding: 0.15rem 0.45rem; border-radius: 4px; }
.note-cell { font-size: 0.8rem; color: var(--gray-500); }

/* 操作ボタン */
.row-btns { display: flex; gap: 0.25rem; }
.ibtn { background: none; border: 1px solid var(--gray-200); border-radius: 5px; padding: 0.2rem 0.4rem; cursor: pointer; color: var(--gray-500); transition: background .1s, color .1s; display: inline-flex; align-items: center; }
.ibtn:hover { background: var(--gray-100); color: var(--gray-800); }
.ibtn.del:hover { background: #fef2f2; color: var(--danger); border-color: var(--danger); }

/* 一括編集 */
.edit-mode .ct-search { display: none; }
.edit-mode .ct-section-head .add-in-cat { display: none; }
.edit-bar { display: none; align-items: center; gap: .75rem; padding: .75rem 1rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; margin-bottom: 1.25rem; font-size: .875rem; color: #92400e; }
.edit-bar svg { flex-shrink: 0; }
.edit-mode .edit-bar { display: flex; }
.edit-mode .addBtn-wrap { display: none; }
.ct-table .edit-mode-hide { }
.edit-mode .edit-mode-hide { display: none; }
.edit-mode .edit-mode-show { display: table-cell !important; }
.edit-mode .ct-table td { padding: .35rem .5rem; }
.cell-input {
    width: 100%; padding: .3rem .5rem;
    border: 1px solid var(--gray-200); border-radius: 5px;
    font-size: .85rem; outline: none; background: #fff;
    transition: border-color .15s;
    box-sizing: border-box;
}
.cell-input:focus { border-color: var(--primary); background: #fafffe; }

/* モーダル */
.modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1000; align-items: center; justify-content: center; }
.modal-backdrop.open { display: flex; }
.modal-box { background: #fff; border-radius: 12px; width: 90vw; max-width: 560px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
.modal-head { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200); }
.modal-head h3 { font-size: 1rem; font-weight: 700; }
.modal-close { background: none; border: none; cursor: pointer; font-size: 1.4rem; color: var(--gray-400); line-height: 1; padding: 0 .25rem; }
.modal-body { padding: 1.25rem 1.5rem; overflow-y: auto; }
.modal-foot { padding: 1rem 1.5rem; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: .75rem; }
.f-row { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
.f-group { margin-bottom: .85rem; }
.f-label { font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
.f-req { color: var(--danger); }
.f-input { width: 100%; padding: .5rem .75rem; border: 1px solid var(--gray-200); border-radius: 8px; font-size: .875rem; outline: none; transition: border-color .15s; box-sizing: border-box; }
.f-input:focus { border-color: var(--primary); }

/* 空状態 */
.ct-empty { text-align: center; padding: 4rem 0; color: var(--gray-400); font-size: .9rem; }

/* 電話番号リンク */
.tel-link { color: var(--primary); font-family: monospace; font-weight: 700; text-decoration: none; background: #eff6ff; padding: .15rem .45rem; border-radius: 4px; white-space: nowrap; }
.tel-link:hover { background: #dbeafe; }

/* メールリンク */
.mail-link { color: #059669; font-size: 0.8rem; text-decoration: none; background: #ecfdf5; padding: .15rem .45rem; border-radius: 4px; white-space: nowrap; display: inline-flex; align-items: center; gap: .25rem; }
.mail-link:hover { background: #d1fae5; }
.gmail-compose { cursor: pointer; }
.emp-tag { display: inline-flex; align-items: center; background: #e0e7ff; color: #3730a3; padding: .15rem .5rem; border-radius: 4px; font-size: .8rem; white-space: nowrap; }
.gmail-compose:hover .emp-tag { background: #c7d2fe; }

/* ハイライト */
mark.hl { background: #fef9c3; border-radius: 2px; }

/* ─── スマホ対応 ─── */
@media (max-width: 768px) {
    .ct-wrap { flex-direction: column; }
    .ct-nav {
        width: 100%; height: auto; position: static;
        border-right: none; border-bottom: 1px solid var(--gray-200);
        padding: .5rem; display: flex; flex-wrap: nowrap;
        overflow-x: auto; gap: .25rem;
    }
    .ct-nav-label { display: none; }
    .ct-nav-item {
        border-left: none; border-bottom: 2px solid transparent;
        white-space: nowrap; padding: .4rem .75rem; border-radius: 6px;
        background: var(--gray-50); flex-shrink: 0;
    }
    .ct-nav-item.active { background: #eff6ff; border-bottom-color: var(--primary); }
    .ct-main { padding: 1rem; }
    .ct-search { max-width: 100%; }

    /* テーブル → カード */
    .ct-table thead { display: none; }
    .ct-table, .ct-table tbody, .ct-table tr, .ct-table td { display: block; width: 100%; }
    .ct-table { border-radius: 8px; overflow: visible; border: none; background: transparent; }
    .ct-table tbody tr {
        background: #fff; border: 1px solid var(--gray-200);
        border-radius: 8px; margin-bottom: .6rem;
        padding: .75rem; position: relative;
        box-shadow: 0 1px 3px rgba(0,0,0,.05);
    }
    .ct-table td { border: none; padding: .2rem 0; }
    .ct-table td:first-child { font-weight: 600; font-size: .9rem; padding-bottom: .4rem; padding-right: 60px; }
    .ct-table td:nth-child(2)::before { content: '連絡先: '; font-size: .75rem; color: var(--gray-400); }
    .ct-table td:nth-child(3) { display: inline-block; margin-right: .5rem; }
    .ct-table td:nth-child(4) { display: inline-block; }
    .ct-table td:nth-child(5) { font-size: .8rem; color: var(--gray-500); }
    .ct-table td:last-child { position: absolute; top: .6rem; right: .6rem; width: auto; padding: 0; }
    /* 一括編集モード スマホ */
    .edit-mode .ct-table td { padding: .3rem 0; }
    .edit-mode .ct-table td:first-child { padding-right: 0; }
    .cell-input { font-size: .85rem; }
    /* ヘッダー */
    .ct-header h2 { font-size: 1.1rem; }
    .edit-bar { flex-wrap: wrap; font-size: .8rem; }
    .f-row { grid-template-columns: 1fr; }
}
</style>

<div class="ct-wrap">
    <!-- 左ナビ -->
    <nav class="ct-nav" id="ctNav">
        <div class="ct-nav-label">カテゴリ</div>
        <?php foreach ($categories as $i => $cat): ?>
        <a class="ct-nav-item <?= $i === 0 ? 'active' : '' ?>"
           href="#cat-<?= htmlspecialchars(urlencode($cat)) ?>"
           data-cat="<?= htmlspecialchars($cat) ?>">
            <?= htmlspecialchars($cat) ?>
        </a>
        <?php endforeach; ?>
        <?php if (empty($categories)): ?>
        <div style="padding:.5rem 1rem;font-size:.8rem;color:var(--gray-400);">未登録</div>
        <?php endif; ?>
    </nav>

    <!-- メイン -->
    <main class="ct-main">
        <div class="ct-header">
            <h2>社内連絡先</h2>
            <?php if ($isAdmin): ?>
            <div class="addBtn-wrap" style="display:flex;gap:.5rem;">
                <button type="button" class="btn btn-outline btn-sm" id="emailLogBtn">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:3px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>送信履歴
                </button>
                <button type="button" class="btn btn-outline btn-sm" id="bulkEditBtn">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:3px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>一括編集
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="addBtn">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:3px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>追加
                </button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($isAdmin): ?>
        <div class="edit-bar" id="editBar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            一括編集モード — 直接セルを編集して「保存」を押してください
            <div style="margin-left:auto;display:flex;gap:.5rem;">
                <button type="button" class="btn btn-sm btn-outline" id="bulkCancelBtn">キャンセル</button>
                <button type="button" class="btn btn-sm btn-primary" id="bulkSaveBtn">保存</button>
            </div>
        </div>
        <?php endif; ?>

        <input type="text" class="ct-search" id="ctSearch" placeholder="キーワードで検索（場面・部署・担当者など）" autocomplete="off">

        <div id="ctBody">
        <?php if (empty($contacts)): ?>
            <div class="ct-empty">
                <?php if ($isAdmin): ?>
                    「追加」ボタンから連絡先を登録してください
                <?php else: ?>
                    連絡先が登録されていません
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($categories as $cat):
                $rows = array_filter($contacts, fn($c) => $c['category'] === $cat);
            ?>
            <div class="ct-section" id="cat-<?= htmlspecialchars(urlencode($cat)) ?>" data-cat="<?= htmlspecialchars($cat) ?>">
                <div class="ct-section-head">
                    <span><?= htmlspecialchars($cat) ?></span>
                    <?php if ($isAdmin): ?>
                    <div style="display:flex;gap:.4rem;">
                        <button type="button" class="btn btn-sm btn-outline add-in-cat" data-cat="<?= htmlspecialchars($cat) ?>" style="font-size:.75rem;padding:.2rem .55rem;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> 追加
                        </button>
                        <button type="button" class="btn btn-sm btn-outline delete-cat edit-mode-only" data-cat="<?= htmlspecialchars($cat) ?>" style="display:none;font-size:.75rem;padding:.2rem .55rem;color:#ef4444;border-color:#fca5a5;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg> 削除
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <table class="ct-table">
                    <thead>
                        <tr>
                            <th>こんなとき</th>
                            <th>連絡先</th>
                            <th>電話番号</th>
                            <th>メールアドレス</th>
                            <th>備考</th>
                            <?php if ($isAdmin): ?><th class="edit-mode-hide" style="width:72px"></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr data-id="<?= htmlspecialchars($row['id']) ?>"
                            data-scene="<?= htmlspecialchars($row['scene']) ?>"
                            data-dept="<?= htmlspecialchars($row['dept']) ?>"
                            data-ext="<?= htmlspecialchars($row['ext'] ?? '') ?>"
                            data-email="<?= htmlspecialchars($row['email'] ?? '') ?>"
                            data-person="<?= htmlspecialchars($row['person'] ?? '') ?>"
                            data-note="<?= htmlspecialchars($row['note'] ?? '') ?>">
                            <td><span class="view-val"><?= htmlspecialchars($row['scene']) ?></span><input class="cell-input edit-val" style="display:none" value="<?= htmlspecialchars($row['scene']) ?>"></td>
                            <td><span class="view-val"><?= htmlspecialchars($row['dept']) ?></span><input class="cell-input edit-val" style="display:none" value="<?= htmlspecialchars($row['dept']) ?>"></td>
                            <td><span class="view-val"><?= $row['ext'] ? '<a class="tel-link" href="tel:' . htmlspecialchars($row['ext']) . '">' . htmlspecialchars($row['ext']) . '</a>' : '<span style="color:var(--gray-300)">—</span>' ?></span><input class="cell-input edit-val" style="display:none" value="<?= htmlspecialchars($row['ext'] ?? '') ?>" placeholder="電話番号"></td>
                            <?php $email = $row['email'] ?? ''; ?>
                            <td><span class="view-val"><?php if ($email) {
                                $addrs = array_filter(array_map('trim', explode(',', $email)));
                                echo '<span class="gmail-compose" data-email="' . htmlspecialchars($email) . '" data-scene="' . htmlspecialchars($row['scene']) . '" style="display:inline-flex;flex-wrap:wrap;gap:.3rem;cursor:pointer;">';
                                foreach ($addrs as $addr) {
                                    $label = $emailToName[$addr] ?? $addr;
                                    echo '<span class="emp-tag">' . htmlspecialchars($label) . '</span>';
                                }
                                echo '</span>';
                            } else {
                                echo '<span style="color:var(--gray-300)">—</span>';
                            } ?></span><input class="cell-input edit-val" style="display:none" value="<?= htmlspecialchars($email) ?>" placeholder="メールアドレス"></td>
                            <td><span class="view-val note-cell"><?= htmlspecialchars($row['note'] ?? '') ?></span><input class="cell-input edit-val" style="display:none" value="<?= htmlspecialchars($row['note'] ?? '') ?>" placeholder="備考"></td>
                            <?php if ($isAdmin): ?>
                            <td class="edit-mode-hide">
                                <div class="row-btns">
                                    <button class="ibtn edit-btn"
                                        data-id="<?= htmlspecialchars($row['id']) ?>"
                                        data-category="<?= htmlspecialchars($row['category']) ?>"
                                        data-scene="<?= htmlspecialchars($row['scene']) ?>"
                                        data-dept="<?= htmlspecialchars($row['dept']) ?>"
                                        data-ext="<?= htmlspecialchars($row['ext'] ?? '') ?>"
                                        data-email="<?= htmlspecialchars($row['email'] ?? '') ?>"
                                        data-person="<?= htmlspecialchars($row['person'] ?? '') ?>"
                                        data-note="<?= htmlspecialchars($row['note'] ?? '') ?>">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button class="ibtn del delete-btn"
                                        data-id="<?= htmlspecialchars($row['id']) ?>"
                                        data-label="<?= htmlspecialchars($row['scene']) ?>">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                    </button>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>

        <div class="ct-empty" id="noResults" style="display:none;">一致する連絡先が見つかりませんでした</div>

        <?php if ($isAdmin): ?>
        <!-- 送信履歴パネル -->
        <div id="emailLogPanel" style="display:none;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <h3 style="font-size:1.1rem;font-weight:700;margin:0;">メール送信履歴</h3>
                <button type="button" class="btn btn-outline btn-sm" id="emailLogBack">← 連絡先に戻る</button>
            </div>
            <div id="emailLogList"><p style="color:#9ca3af;">読み込み中...</p></div>
        </div>
        <?php endif; ?>
    </main>
</div>

<?php if ($isAdmin): ?>
<div class="modal-backdrop" id="modal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="modalTitle">連絡先を追加</h3>
            <button type="button" class="modal-close" id="modalClose">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="fId">
            <div class="f-group">
                <div class="f-label">カテゴリ <span class="f-req">*</span></div>
                <input type="text" id="fCategory" class="f-input" placeholder="例: 総務・庶務" list="catList">
                <datalist id="catList">
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="f-group">
                <div class="f-label">こんなとき（場面） <span class="f-req">*</span></div>
                <input type="text" id="fScene" class="f-input" placeholder="例: 社用車を予約したい">
            </div>
            <div class="f-row">
                <div class="f-group">
                    <div class="f-label">連絡先部署 <span class="f-req">*</span></div>
                    <input type="text" id="fDept" class="f-input" placeholder="例: 総務課">
                </div>
                <div class="f-group">
                    <div class="f-label">電話番号</div>
                    <input type="tel" id="fExt" class="f-input" placeholder="例: 090-1234-5678">
                </div>
            </div>
            <div class="f-group">
                <div class="f-label">メールアドレス</div>
                <input type="text" id="fEmail" class="f-input" placeholder="例: aaa@example.com, bbb@example.com（カンマ区切りで複数可）">
            </div>
            <div class="f-row">
                <div class="f-group" style="flex:1">
                    <div class="f-label">備考</div>
                    <input type="text" id="fNote" class="f-input" placeholder="例: 前日までに連絡">
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-outline" id="modalCancel">キャンセル</button>
            <button type="button" class="btn btn-primary" id="modalSave">保存</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- メール作成モーダル -->
<div class="modal-backdrop" id="mailModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>メール作成</h3>
            <button type="button" class="modal-close" id="mailModalClose">×</button>
        </div>
        <div class="modal-body">
            <?php if ($gmailConfigured): ?>
            <div class="f-group">
                <div class="f-label">宛先を追加</div>
                <select id="mailEmpSelect" class="f-input">
                    <option value="">— 従業員を選択 —</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= htmlspecialchars($emp['email']) ?>"><?= htmlspecialchars($emp['name']) ?><?= $emp['dept'] ? '（' . htmlspecialchars($emp['dept']) . '）' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="f-group">
                <div class="f-label">宛先</div>
                <div id="mailToTags" style="display:flex;flex-wrap:wrap;gap:.4rem;min-height:36px;padding:.4rem;border:1px solid var(--gray-200);border-radius:6px;background:#f9fafb;"></div>
                <input type="hidden" id="mailTo">
            </div>
            <div class="f-group">
                <div class="f-label">件名 <span class="f-req">*</span></div>
                <input type="text" id="mailSubject" class="f-input" placeholder="件名を入力">
            </div>
            <div class="f-group">
                <div class="f-label">本文</div>
                <textarea id="mailBody" class="f-input" rows="8" placeholder="本文を入力" style="resize:vertical;"></textarea>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:2rem 1rem;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" style="margin-bottom:1rem;">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                </svg>
                <p style="color:#6b7280;margin-bottom:1rem;">メール送信にはGmail連携が必要です</p>
                <a href="/pages/settings.php?tab=gmail" class="btn btn-primary">Gmail連携を設定</a>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($gmailConfigured): ?>
        <div class="modal-foot">
            <button type="button" class="btn btn-outline" id="mailModalCancel">キャンセル</button>
            <button type="button" class="btn btn-primary" id="mailSendBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;vertical-align:-2px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                送信
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script<?= nonceAttr() ?>>
(function () {
    const CSRF    = <?= json_encode($csrfToken) ?>;
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    const GMAIL_OK = <?= $gmailConfigured ? 'true' : 'false' ?>;

    // ─── ナビ アクティブ追従 ───────────────────────────────────────────────
    const navItems = document.querySelectorAll('.ct-nav-item');
    navItems.forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            navItems.forEach(n => n.classList.remove('active'));
            a.classList.add('active');
            const sec = document.getElementById('cat-' + encodeURIComponent(a.dataset.cat));
            if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // ─── 検索 ─────────────────────────────────────────────────────────────
    function esc(s) { return s.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

    document.getElementById('ctSearch').addEventListener('input', function () {
        const q = this.value.trim();
        if (!q) { resetSearch(); return; }
        const re = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
        const hi = s => esc(s).replace(re, m => `<mark class="hl">${m}</mark>`);
        let any = false;
        document.querySelectorAll('.ct-section').forEach(sec => {
            let vis = false;
            sec.querySelectorAll('tbody tr').forEach(tr => {
                const text = Array.from(tr.querySelectorAll('td')).map(c => c.textContent).join(' ');
                if (text.includes(q)) {
                    tr.style.display = '';
                    tr.querySelectorAll('td').forEach(td => { td.innerHTML = hi(td.textContent); });
                    vis = true; any = true;
                } else {
                    tr.style.display = 'none';
                }
            });
            sec.style.display = vis ? '' : 'none';
        });
        document.getElementById('noResults').style.display = any ? 'none' : '';
    });

    function resetSearch() {
        document.querySelectorAll('.ct-section').forEach(s => {
            s.style.display = '';
            s.querySelectorAll('tbody tr').forEach(tr => {
                tr.style.display = '';
                tr.querySelectorAll('td').forEach(td => { td.innerHTML = esc(td.textContent); });
            });
        });
        document.getElementById('noResults').style.display = 'none';
    }

    if (!IS_ADMIN) return;

    // ─── 一括編集 ─────────────────────────────────────────────────────────
    const body = document.querySelector('.ct-main');
    document.getElementById('bulkEditBtn').addEventListener('click', () => {
        body.classList.add('edit-mode');
        document.querySelectorAll('.view-val').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.edit-val').forEach(el => el.style.display = '');
        document.querySelectorAll('.edit-mode-only').forEach(el => el.style.display = '');
    });

    function exitEditMode() {
        body.classList.remove('edit-mode');
        document.querySelectorAll('.view-val').forEach(el => el.style.display = '');
        document.querySelectorAll('.edit-val').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.edit-mode-only').forEach(el => el.style.display = 'none');
    }

    document.getElementById('bulkCancelBtn').addEventListener('click', () => {
        // 元の値に戻す
        document.querySelectorAll('tr[data-id]').forEach(tr => {
            const inputs = tr.querySelectorAll('.edit-val');
            const fields = ['scene','dept','ext','email','note'];
            inputs.forEach((inp, i) => { inp.value = tr.dataset[fields[i]] || ''; });
        });
        exitEditMode();
    });

    document.getElementById('bulkSaveBtn').addEventListener('click', async () => {
        const items = [];
        document.querySelectorAll('tr[data-id]').forEach(tr => {
            const inputs = tr.querySelectorAll('.edit-val');
            items.push({
                id:     tr.dataset.id,
                scene:  inputs[0].value.trim(),
                dept:   inputs[1].value.trim(),
                ext:    inputs[2].value.trim(),
                email:  inputs[3].value.trim(),
                note:   inputs[4].value.trim(),
            });
        });
        try {
            await api({ action: 'bulk_update', items });
            location.reload();
        } catch (e) { alert(e.message); }
    });

    // ─── API ──────────────────────────────────────────────────────────────
    async function api(body) {
        const res  = await fetch('/api/contacts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(body),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'エラーが発生しました');
        return json;
    }

    // ─── モーダル ─────────────────────────────────────────────────────────
    const modal = document.getElementById('modal');
    function openModal() { modal.classList.add('open'); }
    function closeModal() { modal.classList.remove('open'); }
    document.getElementById('modalClose').addEventListener('click', closeModal);
    document.getElementById('modalCancel').addEventListener('click', closeModal);
    // 背景クリックでは閉じない（×ボタン・キャンセルのみ）

    function fillModal(data = {}) {
        document.getElementById('fId').value       = data.id       || '';
        document.getElementById('fCategory').value = data.category || '';
        document.getElementById('fScene').value    = data.scene    || '';
        document.getElementById('fDept').value     = data.dept     || '';
        document.getElementById('fExt').value      = data.ext      || '';
        document.getElementById('fEmail').value    = data.email    || '';
        document.getElementById('fNote').value     = data.note     || '';
        document.getElementById('modalTitle').textContent = data.id ? '連絡先を編集' : '連絡先を追加';
    }

    // ─── 追加ボタン ───────────────────────────────────────────────────────
    document.getElementById('addBtn').addEventListener('click', () => { fillModal(); openModal(); });
    document.querySelectorAll('.add-in-cat').forEach(btn => {
        btn.addEventListener('click', () => { fillModal({ category: btn.dataset.cat }); openModal(); });
    });

    // ─── 編集ボタン ───────────────────────────────────────────────────────
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => { fillModal(btn.dataset); openModal(); });
    });

    // ─── 保存 ─────────────────────────────────────────────────────────────
    document.getElementById('modalSave').addEventListener('click', async () => {
        const id       = document.getElementById('fId').value;
        const category = document.getElementById('fCategory').value.trim();
        const scene    = document.getElementById('fScene').value.trim();
        const dept     = document.getElementById('fDept').value.trim();
        if (!category || !scene || !dept) { alert('カテゴリ・こんなとき・連絡先は必須です'); return; }
        try {
            await api({
                action: id ? 'update' : 'create', id,
                category, scene, dept,
                ext:    document.getElementById('fExt').value.trim(),
                email:  document.getElementById('fEmail').value.trim(),
                note:   document.getElementById('fNote').value.trim(),
            });
            location.reload();
        } catch (e) { alert(e.message); }
    });

    // ─── メール作成モーダル ─────────────────────────────────────────────────
    const mailModal = document.getElementById('mailModal');
    // 宛先タグ管理
    const mailToTags = document.getElementById('mailToTags');
    const mailToHidden = document.getElementById('mailTo');
    const mailEmpSelect = document.getElementById('mailEmpSelect');
    let mailRecipients = []; // [{email, name}]

    function updateMailTo() {
        mailToHidden.value = mailRecipients.map(r => r.email).join(', ');
    }

    function addRecipient(email, name) {
        if (!email || mailRecipients.some(r => r.email === email)) return;
        mailRecipients.push({ email, name: name || email });
        renderTags();
    }

    function removeRecipient(email) {
        mailRecipients = mailRecipients.filter(r => r.email !== email);
        renderTags();
    }

    function renderTags() {
        if (!mailToTags) return;
        mailToTags.innerHTML = mailRecipients.map(r =>
            `<span style="display:inline-flex;align-items:center;gap:.3rem;background:#e0e7ff;color:#3730a3;padding:.15rem .5rem;border-radius:4px;font-size:.8rem;">
                ${esc(r.name)}
                <button type="button" data-email="${esc(r.email)}" style="background:none;border:none;cursor:pointer;color:#6366f1;font-size:1rem;line-height:1;padding:0;">×</button>
            </span>`
        ).join('');
        mailToTags.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => removeRecipient(btn.dataset.email));
        });
        updateMailTo();
    }

    if (mailEmpSelect) {
        mailEmpSelect.addEventListener('change', () => {
            const opt = mailEmpSelect.selectedOptions[0];
            if (opt && opt.value) {
                addRecipient(opt.value, opt.textContent.trim());
                mailEmpSelect.value = '';
            }
        });
    }

    function openMailModal(email, scene) {
        if (GMAIL_OK) {
            mailRecipients = [];
            if (email) {
                // カンマ区切りの複数アドレスを展開
                email.split(',').map(e => e.trim()).filter(Boolean).forEach(addr => {
                    // 従業員名を探す
                    const opt = mailEmpSelect ? mailEmpSelect.querySelector(`option[value="${CSS.escape(addr)}"]`) : null;
                    addRecipient(addr, opt ? opt.textContent.trim() : addr);
                });
            }
            document.getElementById('mailSubject').value = scene || '';
            document.getElementById('mailBody').value = '';
        }
        mailModal.classList.add('open');
    }
    function closeMailModal() { mailModal.classList.remove('open'); }
    document.getElementById('mailModalClose').addEventListener('click', closeMailModal);
    if (document.getElementById('mailModalCancel')) {
        document.getElementById('mailModalCancel').addEventListener('click', closeMailModal);
    }
    // 背景クリックでは閉じない（×ボタン・キャンセルのみ）

    document.querySelectorAll('.gmail-compose').forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            e.stopPropagation();
            openMailModal(a.dataset.email, a.dataset.scene);
        });
    });

    if (GMAIL_OK && document.getElementById('mailSendBtn')) {
        document.getElementById('mailSendBtn').addEventListener('click', async () => {
            const to = document.getElementById('mailTo').value;
            const subject = document.getElementById('mailSubject').value.trim();
            const body = document.getElementById('mailBody').value.trim();
            if (!subject) { alert('件名を入力してください'); return; }

            const btn = document.getElementById('mailSendBtn');
            btn.disabled = true;
            btn.textContent = '送信中...';

            try {
                const res = await fetch('/api/contacts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify({ action: 'send_email', to, subject, body }),
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'エラーが発生しました');
                closeMailModal();
                showToast('メールを送信しました');
            } catch (e) {
                alert(e.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;vertical-align:-2px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> 送信';
            }
        });
    }

    // ─── カテゴリ一括削除 ─────────────────────────────────────────────────
    document.querySelectorAll('.delete-cat').forEach(btn => {
        btn.addEventListener('click', async () => {
            const cat = btn.dataset.cat;
            const section = btn.closest('.ct-section');
            const ids = Array.from(section.querySelectorAll('tr[data-id]')).map(tr => tr.dataset.id);
            if (!confirm(`「${cat}」カテゴリの連絡先${ids.length}件をすべて削除しますか？`)) return;
            try {
                for (const id of ids) {
                    await api({ action: 'delete', id });
                }
                section.remove();
            } catch (e) { alert(e.message); }
        });
    });

    // ─── 削除 ─────────────────────────────────────────────────────────────
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm(`「${btn.dataset.label}」を削除しますか？`)) return;
            try {
                await api({ action: 'delete', id: btn.dataset.id });
                btn.closest('tr').remove();
            } catch (e) { alert(e.message); }
        });
    });

    // ─── 送信履歴（管理部のみ） ─────────────────────────────────────────────
    const logPanel = document.getElementById('emailLogPanel');
    const logList = document.getElementById('emailLogList');
    const ctBody = document.getElementById('ctBody');
    const ctSearch = document.getElementById('ctSearch');
    const editBar = document.getElementById('editBar');

    document.getElementById('emailLogBtn').addEventListener('click', async () => {
        ctBody.style.display = 'none';
        ctSearch.style.display = 'none';
        if (editBar) editBar.style.display = 'none';
        logPanel.style.display = '';
        logList.innerHTML = '<p style="color:#9ca3af;">読み込み中...</p>';

        try {
            const res = await fetch('/api/contacts.php?email_logs=1');
            const json = await res.json();
            if (!json.success) throw new Error(json.message);
            const logs = json.data || [];
            if (logs.length === 0) {
                logList.innerHTML = '<p style="color:#9ca3af;text-align:center;padding:2rem;">送信履歴はありません</p>';
                return;
            }
            logList.innerHTML = logs.map(log => `
                <div style="border:1px solid var(--gray-200);border-radius:8px;padding:1rem;margin-bottom:.75rem;background:#fff;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                        <div style="font-size:.8rem;color:#6b7280;">
                            <strong style="color:#374151;">${esc(log.from)}</strong> → <strong style="color:#374151;">${esc(log.to)}</strong>
                        </div>
                        <span style="font-size:.75rem;color:#9ca3af;">${esc(log.sent_at)}</span>
                    </div>
                    <div style="font-weight:600;margin-bottom:.4rem;font-size:.9rem;">${esc(log.subject)}</div>
                    <div style="font-size:.85rem;color:#6b7280;white-space:pre-wrap;line-height:1.5;">${esc(log.body || '（本文なし）')}</div>
                </div>
            `).join('');
        } catch (e) {
            logList.innerHTML = '<p style="color:#ef4444;">読み込みに失敗しました: ' + esc(e.message) + '</p>';
        }
    });

    document.getElementById('emailLogBack').addEventListener('click', () => {
        logPanel.style.display = 'none';
        ctBody.style.display = '';
        ctSearch.style.display = '';
    });
})();
</script>
