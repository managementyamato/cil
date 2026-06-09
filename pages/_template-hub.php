<?php
/**
 * テンプレート: ハブ型 (タブ集約) ページ
 *
 * 「サブページ群を 1 つの URL のタブで切り替える」型のページ用骨格。
 * 例: master-hub.php / accounting-hub.php / internal-hub.php / daily-hub.php / reports-hub.php
 *
 * 使い方:
 *   1. このファイルを pages/<新ハブ名>-hub.php にコピー
 *   2. 先頭の die() ブロックと「TEMPLATE:」コメントを削除
 *   3. $XXX_TABS の名前 (= 配列変数名) を一意のものに変更
 *      _hub-shell-top.php は $INTERNAL_TABS / $ACCOUNTING_TABS / $DAILY_TABS / $MASTER_TABS
 *      のいずれかを自動検出する。新ハブ名はそのリスト (または明示的に $hubTabs = ... ) で渡す
 *   4. 各タブの 'file' に切替先 .php を、'perm' に "<new-hub>.php#<tab>" を指定
 *   5. api/auth.php の $defaultPagePermissions に新ハブのキーを追加
 *      (タブ単位の権限は user-permissions.php で "<new-hub>.php#<tab>" として個別設定)
 *   6. pages/user-permissions.php の対象キーリストに新ハブを追加
 *      (feedback_new_page_permissions.md)
 *   7. functions/header.php のサイドバーリンクを追加
 *
 * 形式統一の必須ルール:
 *   - シェルは _hub-shell-top.php / _hub-shell-bottom.php を必ず include
 *   - 独自 hub-modal / hub-tabstrip クラスを新規追加しない
 *     (docs/ui-legacy-classes.md の非推奨例)
 */

// ── テンプレート直接アクセス防止 (コピー後は削除する) ──
if (basename($_SERVER['PHP_SELF']) === '_template-hub.php') {
    http_response_code(404);
    exit('Template file. Copy to pages/<your-page>.php before use.');
}

require_once __DIR__ . '/../api/auth.php';

// IN_HUB_PAGE はサイドバーのアクティブ判定等で使われる
if (!defined('IN_HUB_PAGE')) define('IN_HUB_PAGE', '<new-hub>');

// ── タブ定義 ─────────────────────────────────────────────
// 配列名は _hub-shell-top.php が自動検出する 4 種類のいずれか、または明示 $hubTabs
$NEW_HUB_TABS = [
    'overview' => [
        'label' => '概要',
        'file'  => '<new-hub>-overview.php',
        'perm'  => '<new-hub>.php#overview',
        'icon'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/>',
    ],
    'detail' => [
        'label' => '詳細',
        'file'  => '<new-hub>-detail.php',
        'perm'  => '<new-hub>.php#detail',
        'icon'  => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>',
    ],
    // 必要に応じて追加
];

// ハブ外の追加リンク (外部ドメイン等) を出したい場合のみ定義
// $hubExtraTabs = [
//     [
//         'label'  => 'デバイス管理',
//         'url'    => 'https://example.com/',
//         'target' => '_blank',
//         'perm'   => '<new-hub>.php#external',
//         'icon'   => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
//     ],
// ];

// ── アクティブタブ判定 + 権限フォールバック ──
$activeTab = $_GET['tab'] ?? array_key_first($NEW_HUB_TABS);
if (!isset($NEW_HUB_TABS[$activeTab])) $activeTab = array_key_first($NEW_HUB_TABS);

if (function_exists('getPageViewPermission') && function_exists('hasPermission')) {
    if (!hasPermission(getPageViewPermission($NEW_HUB_TABS[$activeTab]['perm']))) {
        $found = false;
        foreach ($NEW_HUB_TABS as $k => $t) {
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

// ── ハブヘッダー (アイコン / タイトル / サブタイトル) ──
$HUB_META = [
    'title'    => '<NEW_HUB_TITLE>',
    'subtitle' => '<NEW_HUB_SUBTITLE>',
    'icon'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/>',
];

// _hub-shell-top.php に $hubTabs として渡す
$hubTabs = $NEW_HUB_TABS;

require_once __DIR__ . '/../functions/header.php';
include __DIR__ . '/_hub-shell-top.php';
include __DIR__ . '/' . $NEW_HUB_TABS[$activeTab]['file'];
include __DIR__ . '/_hub-shell-bottom.php';
