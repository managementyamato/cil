<?php
/**
 * 経理ハブ
 * 損益 / 請求書確認 / 請求書作成依頼 を 1ページに統合 (?tab= でタブ切替)
 */
require_once __DIR__ . '/../api/auth.php';
if (!defined('IN_HUB_PAGE')) define('IN_HUB_PAGE', 'accounting');

$ACCOUNTING_TABS = [
    'finance' => [
        'label' => '損益',
        'file'  => 'finance.php',
        'perm'  => 'finance.php',
        'icon'  => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
    ],
    'confirm' => [
        'label' => '請求書確認',
        'file'  => 'invoice-confirm.php',
        'perm'  => 'invoice-confirm.php',
        'icon'  => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    ],
    'requests' => [
        'label' => '請求書作成依頼',
        'file'  => 'invoice-requests.php',
        'perm'  => 'invoice-requests.php',
        'icon'  => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
    ],
];

$activeTab = $_GET['tab'] ?? 'finance';
if (!isset($ACCOUNTING_TABS[$activeTab])) $activeTab = 'finance';

if (function_exists('getPageViewPermission') && function_exists('hasPermission')) {
    if (!hasPermission(getPageViewPermission($ACCOUNTING_TABS[$activeTab]['perm']))) {
        foreach ($ACCOUNTING_TABS as $k => $t) {
            if (hasPermission(getPageViewPermission($t['perm']))) { $activeTab = $k; break; }
        }
    }
}

$HUB_META = [
    'title'    => '経理',
    'subtitle' => '損益・請求書管理',
    'icon'     => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
];

require_once __DIR__ . '/../functions/header.php';
include __DIR__ . '/_hub-shell-top.php';
include __DIR__ . '/' . $ACCOUNTING_TABS[$activeTab]['file'];
include __DIR__ . '/_hub-shell-bottom.php';
