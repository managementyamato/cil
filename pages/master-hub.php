<?php
/**
 * マスタハブ
 * マスタ管理 / 価格表マスタ / 製品マスタ / 外部リンク を 1ページに統合 (+ デバイス管理は別ドメインへの外部リンク)
 */
require_once __DIR__ . '/../api/auth.php';
if (!defined('IN_HUB_PAGE')) define('IN_HUB_PAGE', 'master');

// 各タブの perm は user-permissions.php の "master-hub.php#<tab>" サブキーを参照
// → user-permissions 画面でタブ単位に view/edit を個別設定可能
$MASTER_TABS = [
    'masters' => [
        'label' => 'マスタ管理',
        'file'  => 'masters.php',
        'perm'  => 'master-hub.php#masters',
        'icon'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    ],
    'price' => [
        'label' => '価格表マスタ (旧)',
        'file'  => 'price-master.php',
        'perm'  => 'master-hub.php#price',
        'icon'  => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
    ],
    'price-list' => [
        'label' => '価格表 v2',
        'file'  => 'price-list.php',
        'perm'  => 'master-hub.php#price-list',
        'icon'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="3" x2="9" y2="21"/>',
    ],
    'products' => [
        'label' => '製品マスタ',
        'file'  => 'product-master.php',
        'perm'  => 'master-hub.php#products',
        'icon'  => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
    ],
    'links' => [
        'label' => '外部リンク',
        'file'  => 'external-links.php',
        'perm'  => 'master-hub.php#links',
        'icon'  => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    ],
];

// 外部リンク (デバイス管理) ― perm 付きにし、_hub-shell-top.php 側で表示判定する想定
$hubExtraTabs = [
    [
        'label'  => 'デバイス管理',
        'url'    => 'https://inventory.yamato-mgt.com/',
        'target' => '_blank',
        'perm'   => 'master-hub.php#devices',
        'icon'   => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
    ],
];

$activeTab = $_GET['tab'] ?? 'masters';
if (!isset($MASTER_TABS[$activeTab])) $activeTab = 'masters';

// アクセス可能タブの判定 + フォールバック
if (function_exists('getPageViewPermission') && function_exists('hasPermission')) {
    if (!hasPermission(getPageViewPermission($MASTER_TABS[$activeTab]['perm']))) {
        $found = false;
        foreach ($MASTER_TABS as $k => $t) {
            if (hasPermission(getPageViewPermission($t['perm']))) {
                $activeTab = $k;
                $found = true;
                break;
            }
        }
        // どのタブも閲覧不可ならダッシュボードへ
        if (!$found) {
            header('Location: /pages/index.php');
            exit;
        }
    }
}

$HUB_META = [
    'title'    => 'マスタ',
    'subtitle' => '各種マスタデータの管理',
    'icon'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
];

require_once __DIR__ . '/../functions/header.php';
include __DIR__ . '/_hub-shell-top.php';
include __DIR__ . '/' . $MASTER_TABS[$activeTab]['file'];
include __DIR__ . '/_hub-shell-bottom.php';
