<?php
/**
 * ハブ共通シェル (トップ部分)
 *
 * 使い方:
 *   - 呼び出し元 (accounting-hub.php / daily-hub.php / internal-hub.php / master-hub.php) で
 *     以下の変数を定義してから include する:
 *       $HUB_META   = ['title' => '...', 'subtitle' => '...', 'icon' => '<svg中身>'];
 *       $activeTab  = 現在のタブキー
 *       $XXX_TABS / $ACCOUNTING_TABS / 等 = タブ配列  → render 直前に $hubTabs に代入
 *   - bottom シェルで </div> を閉じる
 *
 * sales-tools.php と同じデザイン (.st-header + .st-tabs + .st-tab) を流用する。
 * sales-tools の CSS は sales-tools/_styles.php にあるが、ここでは必要部分だけ再定義する。
 */

// 呼び出し元から $hubTabs を渡されている前提
// (例: $hubTabs = $INTERNAL_TABS; を呼び出し元で行う)
if (!isset($hubTabs)) {
    // 呼び出し元の変数を自動推測
    foreach (['INTERNAL_TABS', 'ACCOUNTING_TABS', 'DAILY_TABS', 'MASTER_TABS'] as $varName) {
        if (isset($$varName)) { $hubTabs = $$varName; break; }
    }
}
if (!isset($hubTabs)) $hubTabs = [];
if (!isset($HUB_META)) $HUB_META = ['title' => '', 'subtitle' => '', 'icon' => ''];
?>

<style<?= function_exists('nonceAttr') ? nonceAttr() : '' ?>>
/* ハブ共通: 営業ツール (.st-*) と同じデザインを踏襲 */
.hub-page { max-width: 1280px; margin: 0 auto; padding: 0.5rem 0 2rem; }

/* ヘッダー */
.hub-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.hub-header-icon {
    width: 56px; height: 56px;
    border-radius: 12px;
    background: var(--danger-light);
    display: flex; align-items: center; justify-content: center;
    color: var(--danger);
    flex-shrink: 0;
}
.hub-header-text h2 {
    font-size: 1.6rem; font-weight: 700;
    color: var(--gray-900); margin: 0 0 0.25rem; line-height: 1.2;
}
.hub-header-text .hub-subtitle {
    font-size: 0.875rem; color: var(--gray-700); margin: 0;
}

/* タブ */
.hub-tabstrip-2 {
    display: flex; gap: 0.25rem;
    border-bottom: 1px solid var(--gray-200);
    margin-bottom: 1.5rem;
    overflow-x: auto; flex-wrap: nowrap;
}
.hub-tab-2 {
    padding: 0.75rem 1.125rem;
    background: transparent; border: none;
    border-bottom: 2px solid transparent;
    color: var(--gray-700);
    font-size: 0.9375rem; font-weight: 500;
    cursor: pointer;
    display: inline-flex; align-items: center; gap: 0.4rem;
    text-decoration: none; white-space: nowrap;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
    border-radius: 6px 6px 0 0;
}
.hub-tab-2:hover { color: var(--gray-900); background: var(--gray-50); }
.hub-tab-2.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    font-weight: 600;
}
.hub-tab-2 svg { flex-shrink: 0; }
</style>

<div class="hub-page">
    <!-- ハブヘッダー (アイコン + タイトル + サブタイトル) -->
    <div class="hub-header">
        <div class="hub-header-icon" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <?= $HUB_META['icon'] ?>
            </svg>
        </div>
        <div class="hub-header-text">
            <h2><?= htmlspecialchars($HUB_META['title']) ?></h2>
            <?php if (!empty($HUB_META['subtitle'])): ?>
            <p class="hub-subtitle"><?= htmlspecialchars($HUB_META['subtitle']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- タブ -->
    <nav class="hub-tabstrip-2" role="tablist">
        <?php foreach ($hubTabs as $k => $t):
            $hasView = !function_exists('hasPermission') || !function_exists('getPageViewPermission')
                || hasPermission(getPageViewPermission($t['perm']));
            if (!$hasView) continue;
        ?>
        <a href="?tab=<?= htmlspecialchars($k) ?>"
           class="hub-tab-2 <?= $activeTab === $k ? 'active' : '' ?>"
           role="tab"
           aria-current="<?= $activeTab === $k ? 'page' : 'false' ?>">
            <?php if (!empty($t['icon'])): ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <?= $t['icon'] ?>
            </svg>
            <?php endif; ?>
            <?= htmlspecialchars($t['label']) ?>
        </a>
        <?php endforeach; ?>
        <?php
        // ハブ固有の追加 (外部リンク等) を $hubExtraTabs として渡せば挿入される
        // 各要素に 'perm' を持たせれば閲覧権限による出し分けも可能
        if (!empty($hubExtraTabs)):
            foreach ($hubExtraTabs as $ext):
                if (!empty($ext['perm'])
                    && function_exists('hasPermission')
                    && function_exists('getPageViewPermission')
                    && !hasPermission(getPageViewPermission($ext['perm']))) {
                    continue;
                }
        ?>
        <a href="<?= htmlspecialchars($ext['url']) ?>"<?= !empty($ext['target']) ? ' target="' . htmlspecialchars($ext['target']) . '" rel="noopener"' : '' ?>
           class="hub-tab-2" role="tab">
            <?php if (!empty($ext['icon'])): ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <?= $ext['icon'] ?>
            </svg>
            <?php endif; ?>
            <?= htmlspecialchars($ext['label']) ?>
        </a>
        <?php
            endforeach;
        endif;
        ?>
    </nav>

    <!-- アクティブタブのコンテンツ -->
    <div class="hub-content">
