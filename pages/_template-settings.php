<?php
/**
 * テンプレート: 設定フォーム型ページ
 *
 * 使い方:
 *   1. このファイルを pages/<新ページ名>.php にコピー
 *   2. 先頭の die() ブロックと「TEMPLATE:」コメントを削除
 *   3. <NEW_PAGE_TITLE> / <NEW_PAGE_ICON_SVG> / <CONFIG_KEY> を置換
 *   4. api/auth.php の $defaultPagePermissions に新ページのキーを追加
 *   5. pages/user-permissions.php の対象キーリストに新ページを追加
 *      (feedback_new_page_permissions.md)
 *
 * 形式統一の必須ルール (CLAUDE.md / docs/patterns.md):
 *   - <input>/<select>/<textarea> は class="form-input" + <div class="form-group">
 *   - 設定セクションは <div class="setting-card"> でまとめる
 *   - メッセージは .alert .alert-success / .alert-error
 *   - POST は verifyCsrfToken() 必須
 */

// ── テンプレート直接アクセス防止 (コピー後は削除する) ──
if (basename($_SERVER['PHP_SELF']) === '_template-settings.php') {
    http_response_code(404);
    exit('Template file. Copy to pages/<your-page>.php before use.');
}

require_once '../api/auth.php';

// 管理者のみアクセス可能にする場合
// if (!isAdmin()) { header('Location: index.php'); exit; }

$message = '';
$error   = '';

// ── POST 処理 ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    if (!canEditCurrentPage()) {
        $error = '編集権限がありません';
    } elseif (isset($_POST['save_settings'])) {
        $config = [
            'enabled'      => isset($_POST['enabled']),
            'option_text'  => trim($_POST['option_text'] ?? ''),
            'option_email' => trim($_POST['option_email'] ?? ''),
            'option_num'   => (int)($_POST['option_num'] ?? 0),
        ];
        // TODO: save<EntityConfig>($config);
        $message = '設定を保存しました';
    }
}

// TODO: get<EntityConfig>() に差し替え
$config = [
    'enabled'      => false,
    'option_text'  => '',
    'option_email' => '',
    'option_num'   => 0,
];

require_once '../functions/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h2>設定</h2>
        <a href="settings.php" class="btn btn-secondary btn-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            一覧に戻る
        </a>
    </div>

    <div class="settings-detail-header">
        <h2>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-24 h-24" aria-hidden="true">
                <!-- <NEW_PAGE_ICON_SVG> -->
                <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
            <NEW_PAGE_TITLE>
        </h2>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrfTokenField() ?>

        <!-- 基本設定 -->
        <div class="setting-card">
            <h3>基本設定</h3>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="enabled" <?= !empty($config['enabled']) ? 'checked' : '' ?>>
                    <span>機能を有効にする</span>
                </label>
            </div>
        </div>

        <!-- 詳細設定 -->
        <div class="setting-card">
            <h3>詳細設定</h3>

            <div class="form-group">
                <label for="option_text">テキスト項目</label>
                <input type="text" id="option_text" name="option_text" class="form-input"
                       value="<?= htmlspecialchars($config['option_text']) ?>">
                <p class="help-text">補足説明をここに書く</p>
            </div>

            <div class="form-group">
                <label for="option_email">メールアドレス</label>
                <input type="email" id="option_email" name="option_email" class="form-input"
                       value="<?= htmlspecialchars($config['option_email']) ?>"
                       placeholder="user@example.com">
            </div>

            <div class="form-group">
                <label for="option_num">数値項目</label>
                <input type="number" id="option_num" name="option_num" class="form-input"
                       value="<?= htmlspecialchars((string)$config['option_num']) ?>" min="0">
            </div>
        </div>

        <button type="submit" name="save_settings" class="btn btn-primary">設定を保存</button>
    </form>
</div><!-- /.page-container -->

<?php require_once '../functions/footer.php'; ?>
