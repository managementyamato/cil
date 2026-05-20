<?php
/**
 * 社内ハブ
 * 連絡先 / 規則 / マニュアル を 1ページに統合 (?tab= でタブ切替)
 * 営業ツール (sales-tools.php) と同じ表記 (ヘッダー + アイコン付きタブ)
 */
require_once __DIR__ . '/../api/auth.php';
if (!defined('IN_HUB_PAGE')) define('IN_HUB_PAGE', 'internal');

// タブ定義 (icon は SVG inner content)
$INTERNAL_TABS = [
    'contacts' => [
        'label' => '連絡先',
        'file'  => 'contacts.php',
        'perm'  => 'contacts.php',
        'icon'  => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
    ],
    'rules' => [
        'label' => '規則',
        'file'  => 'company-rules.php',
        'perm'  => 'company-rules.php',
        'icon'  => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
    ],
    'manuals' => [
        'label' => 'マニュアル',
        'file'  => 'manuals.php',
        'perm'  => 'manuals.php',
        'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
    ],
];

$activeTab = $_GET['tab'] ?? 'contacts';
if (!isset($INTERNAL_TABS[$activeTab])) $activeTab = 'contacts';

if (function_exists('getPageViewPermission') && function_exists('hasPermission')) {
    if (!hasPermission(getPageViewPermission($INTERNAL_TABS[$activeTab]['perm']))) {
        foreach ($INTERNAL_TABS as $k => $t) {
            if (hasPermission(getPageViewPermission($t['perm']))) { $activeTab = $k; break; }
        }
    }
}

// ハブ自身のメタ
$HUB_META = [
    'title'    => '社内',
    'subtitle' => '連絡先・規則・マニュアル',
    'icon'     => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
    'color'    => 'var(--primary)',
];

require_once __DIR__ . '/../functions/header.php';
include __DIR__ . '/_hub-shell-top.php';
include __DIR__ . '/' . $INTERNAL_TABS[$activeTab]['file'];
include __DIR__ . '/_hub-shell-bottom.php';
