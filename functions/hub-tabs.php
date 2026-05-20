<?php
/**
 * ハブタブストリップ共通コンポーネント
 *
 * 「請求書ハブ」「日常業務ハブ」のように、複数の独立したページを
 * 1つの "グループ" として見せるためのタブナビゲーション。
 *
 * 使い方:
 *   require_once __DIR__ . '/hub-tabs.php';
 *   renderHubTabs('invoice');   // 請求書ハブのタブ
 *   renderHubTabs('daily');     // 日常業務ハブのタブ
 *
 *   タブ定義を増やす場合は $HUB_TAB_GROUPS に追加するだけ。
 */

// グループ定義 (key = group_id, value = [label, tabs])
// 各タブ: ['label' => 表示名, 'page' => ファイル名, 'perm' => 権限ページ名(オプション)]
$HUB_TAB_GROUPS = [
    'accounting' => [
        'label' => '経理',
        // accounting-hub.php に統合 (?tab= でタブ切替)
        'tabs'  => [
            ['label' => '損益',       'page' => 'finance.php',          'url' => '/pages/accounting-hub.php?tab=finance'],
            ['label' => '請求書確認', 'page' => 'invoice-confirm.php',  'url' => '/pages/accounting-hub.php?tab=confirm'],
            ['label' => '作成依頼',   'page' => 'invoice-requests.php', 'url' => '/pages/accounting-hub.php?tab=requests'],
        ],
    ],
    'daily' => [
        'label' => '日常業務',
        // daily-hub.php に統合 (?tab= でタブ切替)
        'tabs'  => [
            ['label' => '借入金',             'page' => 'loans.php',            'url' => '/pages/daily-hub.php?tab=loans'],
            ['label' => '給与仕訳',           'page' => 'payroll-journal.php',  'url' => '/pages/daily-hub.php?tab=payroll'],
            ['label' => 'アルコールチェック', 'page' => 'photo-attendance.php', 'url' => '/pages/daily-hub.php?tab=alcohol'],
            ['label' => 'HP更新',             'page' => 'cms-news.php',         'url' => '/pages/daily-hub.php?tab=cms'],
        ],
    ],
    'internal' => [
        'label' => '社内',
        // internal-hub.php に統合 (?tab= でタブ切替)
        'tabs'  => [
            ['label' => '連絡先',     'page' => 'contacts.php',      'url' => '/pages/internal-hub.php?tab=contacts'],
            ['label' => '規則',       'page' => 'company-rules.php', 'url' => '/pages/internal-hub.php?tab=rules'],
            ['label' => 'マニュアル', 'page' => 'manuals.php',       'url' => '/pages/internal-hub.php?tab=manuals'],
        ],
    ],
    'master' => [
        'label' => 'マスタ',
        // master-hub.php に統合 (?tab= でタブ切替)
        'tabs'  => [
            ['label' => 'マスタ管理',   'page' => 'masters.php',        'url' => '/pages/master-hub.php?tab=masters'],
            ['label' => '価格表マスタ', 'page' => 'price-master.php',   'url' => '/pages/master-hub.php?tab=price',   'perm' => 'price-master.php'],
            ['label' => '外部リンク',   'page' => 'external-links.php', 'url' => '/pages/master-hub.php?tab=links',   'perm' => 'external-links.php'],
            // デバイス管理は別ドメイン (inventory.yamato-mgt.com)。新規タブで開く
            ['label' => 'デバイス管理', 'url'  => 'https://inventory.yamato-mgt.com/', 'target' => '_blank'],
        ],
    ],
];

/**
 * ハブタブストリップを描画
 *
 * @param string $groupId 'invoice' / 'daily' 等のグループID
 */
function renderHubTabs(string $groupId): void {
    global $HUB_TAB_GROUPS;
    if (!isset($HUB_TAB_GROUPS[$groupId])) return;

    $group   = $HUB_TAB_GROUPS[$groupId];
    $current = basename($_SERVER['PHP_SELF']);
    $tabs    = $group['tabs'];

    // 権限フィルタ
    $visible = [];
    foreach ($tabs as $tab) {
        $perm = $tab['perm'] ?? null;
        if ($perm && function_exists('getPageViewPermission')
            && !hasPermission(getPageViewPermission($perm))) continue;
        $visible[] = $tab;
    }
    if (count($visible) === 0) return;

    $nonceAttr = function_exists('nonceAttr') ? nonceAttr() : '';

    echo '<style' . $nonceAttr . '>';
    // 申請・報告ページの hub-tabs スタイルに統一した下線タブ
    echo '.hub-tabstrip { display: flex; gap: 4px; border-bottom: 2px solid var(--gray-200); margin: 0 0 1.25rem; overflow-x: auto; align-items: stretch; }';
    echo '.hub-tabstrip .hub-group-label { font-size: 0.72rem; color: var(--gray-400); font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; padding: 0.65rem 0.5rem 0.6rem 0; display: inline-flex; align-items: center; white-space: nowrap; }';
    echo '.hub-tabstrip a { padding: 0.6rem 1.2rem; font-size: 0.85rem; font-weight: 600; color: var(--gray-500); border: none; background: none; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; white-space: nowrap; text-decoration: none; transition: color 0.15s, border-color 0.15s; display: inline-flex; align-items: center; gap: 0.3rem; }';
    echo '.hub-tabstrip a:hover { color: var(--gray-700); }';
    echo '.hub-tabstrip a.active { color: var(--primary); border-bottom-color: var(--primary); }';
    echo '.hub-tabstrip a .hub-ext-icon { width: 12px; height: 12px; opacity: 0.6; }';
    echo '</style>';

    echo '<nav class="hub-tabstrip" aria-label="' . htmlspecialchars($group['label']) . 'ハブ">';
    echo '<span class="hub-group-label">' . htmlspecialchars($group['label']) . '</span>';
    foreach ($visible as $tab) {
        if (!empty($tab['url'])) {
            // 外部URLタブ（新規タブで開く + 外部リンクアイコン付与）
            $target = !empty($tab['target']) ? ' target="' . htmlspecialchars($tab['target']) . '" rel="noopener"' : '';
            echo '<a href="' . htmlspecialchars($tab['url']) . '"' . $target . '>'
               . htmlspecialchars($tab['label'])
               . '<svg class="hub-ext-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>'
               . '</a>';
            continue;
        }
        $isActive = (($tab['page'] ?? '') === $current);
        echo '<a href="/pages/' . htmlspecialchars($tab['page']) . '"'
           . ($isActive ? ' class="active" aria-current="page"' : '')
           . '>' . htmlspecialchars($tab['label']) . '</a>';
    }
    echo '</nav>';
}

/**
 * ハブのデフォルト（最初の）ページパスを返す
 * サイドバーリンクで使う。
 */
function getHubDefaultPage(string $groupId): string {
    global $HUB_TAB_GROUPS;
    if (!isset($HUB_TAB_GROUPS[$groupId])) return '/pages/index.php';
    return '/pages/' . $HUB_TAB_GROUPS[$groupId]['tabs'][0]['page'];
}
