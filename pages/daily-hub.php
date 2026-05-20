<?php
/**
 * 日常業務ハブ
 * 借入金 / 給与仕訳 / アルコールチェック / HP更新 を 1ページに統合
 */
require_once __DIR__ . '/../api/auth.php';
if (!defined('IN_HUB_PAGE')) define('IN_HUB_PAGE', 'daily');

$DAILY_TABS = [
    'loans' => [
        'label' => '借入金',
        'file'  => 'loans.php',
        'perm'  => 'loans.php',
        'icon'  => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
    ],
    'payroll' => [
        'label' => '給与仕訳',
        'file'  => 'payroll-journal.php',
        'perm'  => 'payroll-journal.php',
        'icon'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><line x1="7" y1="9" x2="17" y2="9"/><line x1="7" y1="13" x2="17" y2="13"/><line x1="7" y1="17" x2="11" y2="17"/>',
    ],
    'alcohol' => [
        'label' => 'アルコールチェック',
        'file'  => 'photo-attendance.php',
        'perm'  => 'photo-attendance.php',
        'icon'  => '<path d="M14 2h-4l-1 8a4 4 0 0 0 6 0z"/><path d="M9 22h6"/><line x1="12" y1="13" x2="12" y2="22"/>',
    ],
    'cms' => [
        'label' => 'HP更新',
        'file'  => 'cms-news.php',
        'perm'  => 'cms-news.php',
        'icon'  => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
    ],
];

$activeTab = $_GET['tab'] ?? 'loans';
if (!isset($DAILY_TABS[$activeTab])) $activeTab = 'loans';

if (function_exists('getPageViewPermission') && function_exists('hasPermission')) {
    if (!hasPermission(getPageViewPermission($DAILY_TABS[$activeTab]['perm']))) {
        foreach ($DAILY_TABS as $k => $t) {
            if (hasPermission(getPageViewPermission($t['perm']))) { $activeTab = $k; break; }
        }
    }
}

$HUB_META = [
    'title'    => '日常業務',
    'subtitle' => '借入金・給与・アルコール・HP更新',
    'icon'     => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
];

require_once __DIR__ . '/../functions/header.php';
include __DIR__ . '/_hub-shell-top.php';
include __DIR__ . '/' . $DAILY_TABS[$activeTab]['file'];
include __DIR__ . '/_hub-shell-bottom.php';
