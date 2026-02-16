<?php
/**
 * 顧客マスタ管理
 * MF請求書の取引先から顧客を同期
 */
require_once '../api/auth.php';
require_once '../api/mf-api.php';
require_once '../functions/encryption.php';

// 編集権限チェック
if (!canEdit()) {
    header('Location: index.php');
    exit;
}

/**
 * 会社名を正規化する（グループ化用のキーを生成）
 * @param string $name 会社名
 * @return array ['normalized' => 正規化名, 'parent' => 親会社名, 'branch' => 支店名, 'isBranch' => 支店かどうか]
 */
function normalizeCompanyName($name) {
    $original = $name;

    // 1. 前後のスペースを除去
    $name = trim($name);

    // 2. 全角スペースを半角に統一
    $name = str_replace('　', ' ', $name);

    // 3. 連続するスペースを1つに
    $name = preg_replace('/\s+/', ' ', $name);

    // 4. 支店・営業所パターンを検出
    // 末尾が「営業所」「支店」などで終わる場合のみ支店とみなす
    $branchEndPatterns = [
        '営業所', '支店', '支社', '事業所', '出張所', '工場',
        'サプライセンター'
    ];

    // 除外パターン（これらで終わる場合は支店ではない）
    $excludePatterns = [
        '工事', 'チーム', '組合', '協会', '委員会', '施設'
    ];

    // 法人格パターン
    $corpPatterns = [
        '/^株式会社\s*/u',
        '/\s*株式会社$/u',
        '/^有限会社\s*/u',
        '/\s*有限会社$/u',
        '/^合同会社\s*/u',
        '/\s*合同会社$/u',
        '/^\(株\)\s*/u',
        '/\s*\(株\)$/u',
        '/^（株）\s*/u',
        '/\s*（株）$/u',
    ];

    $isBranch = false;
    $branchName = null;
    $parentPart = $name;

    // 除外パターンで終わっていないかチェック
    $isExcluded = false;
    foreach ($excludePatterns as $ep) {
        if (mb_substr($name, -mb_strlen($ep)) === $ep) {
            $isExcluded = true;
            break;
        }
    }

    // 除外されていない場合のみ、支店パターンをチェック
    if (!$isExcluded) {
        // 末尾が支店パターンで終わるかチェック
        foreach ($branchEndPatterns as $bp) {
            if (mb_substr($name, -mb_strlen($bp)) === $bp) {
                // 会社名部分を抽出（法人格を含む部分を親会社とする）
                // パターン: 「株式会社〇〇 △△営業所」or「株式会社〇〇△△営業所」

                // スペースで分割を試みる
                $parts = preg_split('/\s+/', $name, 2);
                if (count($parts) === 2 && mb_substr($parts[1], -mb_strlen($bp)) === $bp) {
                    $parentPart = trim($parts[0]);
                    $branchName = trim($parts[1]);
                    $isBranch = true;
                } else {
                    // スペースがない場合、法人格の後ろから支店パターンの前までを親会社名とする
                    // 例: 「株式会社レンタルのニッケン大分営業所」→「株式会社レンタルのニッケン」+「大分営業所」
                    if (preg_match('/^(.*?(?:株式会社|有限会社|合同会社).+?)([^株有合]+' . preg_quote($bp, '/') . ')$/u', $name, $matches)) {
                        $parentPart = trim($matches[1]);
                        $branchName = trim($matches[2]);
                        $isBranch = true;
                    }
                }
                break;
            }
        }
    }

    // 親会社部分から法人格を除去して正規化
    $normalized = $parentPart;
    foreach ($corpPatterns as $pattern) {
        $normalized = preg_replace($pattern, '', $normalized);
    }
    $normalized = trim($normalized);

    if ($isBranch) {
        return [
            'normalized' => $normalized,
            'parent' => $normalized,
            'branch' => $branchName,
            'isBranch' => true,
            'original' => $original
        ];
    }

    // 支店パターンがない場合（法人格を除去した名前で正規化）
    $normalizedFull = $name;
    foreach ($corpPatterns as $pattern) {
        $normalizedFull = preg_replace($pattern, '', $normalizedFull);
    }
    $normalizedFull = trim($normalizedFull);

    return [
        'normalized' => $normalizedFull,
        'parent' => $normalizedFull,
        'branch' => null,
        'isBranch' => false,
        'original' => $original
    ];
}

/**
 * 顧客リストをスマートにグループ化
 * @param array $customers 顧客リスト
 * @return array グループ化された顧客リスト
 */
function groupCustomers($customers) {
    $groups = [];

    foreach ($customers as $customer) {
        $companyName = $customer['companyName'] ?? '';
        if (empty($companyName)) continue;

        $info = normalizeCompanyName($companyName);
        $groupKey = $info['normalized'];

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'parent' => null,
                'branches' => [],
                'displayName' => $groupKey // グループの表示名
            ];
        }

        if ($info['isBranch']) {
            // 支店・営業所パターンがある場合は支店として追加
            $customer['_branchName'] = $info['branch'];
            $customer['_parentKey'] = $groupKey;
            $groups[$groupKey]['branches'][] = $customer;
        } else {
            // 親会社として登録
            if ($groups[$groupKey]['parent'] === null) {
                $groups[$groupKey]['parent'] = $customer;
            }
            // 既にparentがある場合、同じ正規化名の別表記（株式会社の有無など）は
            // 重複とみなしてスキップ（支店ではない）
        }
    }

    // 支店のみで親がないグループには、最も短い名前を親として設定
    foreach ($groups as $key => &$group) {
        if ($group['parent'] === null && !empty($group['branches'])) {
            // 支店の中で最も短い名前を持つものを親候補に
            usort($group['branches'], function($a, $b) {
                return mb_strlen($a['companyName']) - mb_strlen($b['companyName']);
            });
        }

        // 支店を名前順でソート
        usort($group['branches'], function($a, $b) {
            return strcmp($a['_branchName'] ?? '', $b['_branchName'] ?? '');
        });
    }
    unset($group);

    // グループをキー（正規化名）でソート
    ksort($groups);

    return $groups;
}

$data = getData();
decryptCustomerData($data);
$customers = $data['customers'] ?? [];

// MF連携状態
$mfConfigured = MFApiClient::isConfigured();
$mfInvoicesCount = count($data['mf_invoices'] ?? []);

// MF請求書から取引先名を抽出
$mfPartners = [];
foreach ($data['mf_invoices'] ?? [] as $inv) {
    $name = trim($inv['partner_name'] ?? '');
    // 空、短すぎる名前は除外
    if (!empty($name) && mb_strlen($name) >= 3 && !in_array($name, $mfPartners)) {
        $mfPartners[] = $name;
    }
}
sort($mfPartners);

// MFに存在しない顧客を検出
$orphanCustomers = [];
foreach ($data['customers'] ?? [] as $c) {
    $companyName = $c['companyName'] ?? '';
    if (!empty($companyName) && !in_array($companyName, $mfPartners)) {
        $orphanCustomers[] = $c;
    }
}

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// 顧客追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $companyName = trim($_POST['company_name'] ?? '');

    if (!empty($companyName)) {
        // 重複チェック
        $exists = false;
        foreach ($customers as $c) {
            if ($c['companyName'] === $companyName) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $data['customers'][] = [
                'id' => 'c_' . uniqid(),
                'companyName' => $companyName,
                'aliases' => [],
                'contactPerson' => trim($_POST['contact_person'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'address' => trim($_POST['address'] ?? ''),
                'notes' => trim($_POST['notes'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'source' => 'manual'
            ];
            encryptCustomerData($data);
            saveData($data);
            header('Location: customers.php?added=1');
            exit;
        } else {
            header('Location: customers.php?error=duplicate');
            exit;
        }
    }
}

// 顧客更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    $customerId = $_POST['customer_id'] ?? '';

    foreach ($data['customers'] as &$c) {
        if ($c['id'] === $customerId) {
            $c['companyName'] = trim($_POST['company_name'] ?? $c['companyName']);
            $c['contactPerson'] = trim($_POST['contact_person'] ?? '');
            $c['phone'] = trim($_POST['phone'] ?? '');
            $c['email'] = trim($_POST['email'] ?? '');
            $c['address'] = trim($_POST['address'] ?? '');
            $c['notes'] = trim($_POST['notes'] ?? '');
            $c['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($c);
    encryptCustomerData($data);
    saveData($data);
    header('Location: customers.php?updated=1');
    exit;
}

// 顧客削除（管理部のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $customerId = $_POST['customer_id'] ?? '';

        // 論理削除
        $deletedCustomer = softDelete($data['customers'], $customerId);

        if ($deletedCustomer) {
            encryptCustomerData($data);
            saveData($data);
            auditDelete('customers', $customerId, '顧客を削除: ' . ($deletedCustomer['companyName'] ?? ''), $deletedCustomer);
        }

        header('Location: customers.php?deleted=1');
        exit;
    }
}

// 営業所追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_branch'])) {
    $customerId = $_POST['customer_id'] ?? '';
    $branchName = trim($_POST['branch_name'] ?? '');

    if (!empty($customerId) && !empty($branchName)) {
        foreach ($data['customers'] as &$c) {
            if ($c['id'] === $customerId) {
                if (!isset($c['branches'])) {
                    $c['branches'] = [];
                }
                // 重複チェック
                $exists = false;
                foreach ($c['branches'] as $b) {
                    if ($b['name'] === $branchName) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $c['branches'][] = [
                        'id' => 'br_' . uniqid(),
                        'name' => $branchName,
                        'contact' => trim($_POST['branch_contact'] ?? ''),
                        'phone' => trim($_POST['branch_phone'] ?? ''),
                        'address' => trim($_POST['branch_address'] ?? ''),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    $c['updated_at'] = date('Y-m-d H:i:s');
                    encryptCustomerData($data);
                    saveData($data);
                    header('Location: customers.php?branch_added=1#customer-' . $customerId);
                    exit;
                }
                break;
            }
        }
        unset($c);
    }
    header('Location: customers.php');
    exit;
}

// 営業所更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_branch'])) {
    $customerId = $_POST['customer_id'] ?? '';
    $branchId = $_POST['branch_id'] ?? '';

    if (!empty($customerId) && !empty($branchId)) {
        foreach ($data['customers'] as &$c) {
            if ($c['id'] === $customerId && isset($c['branches'])) {
                foreach ($c['branches'] as &$b) {
                    if ($b['id'] === $branchId) {
                        $b['name'] = trim($_POST['branch_name'] ?? $b['name']);
                        $b['contact'] = trim($_POST['branch_contact'] ?? '');
                        $b['phone'] = trim($_POST['branch_phone'] ?? '');
                        $b['address'] = trim($_POST['branch_address'] ?? '');
                        $b['updated_at'] = date('Y-m-d H:i:s');
                        break;
                    }
                }
                unset($b);
                $c['updated_at'] = date('Y-m-d H:i:s');
                encryptCustomerData($data);
                saveData($data);
                header('Location: customers.php?branch_updated=1#customer-' . $customerId);
                exit;
            }
        }
        unset($c);
    }
    header('Location: customers.php');
    exit;
}

// 営業所削除（管理部のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_branch'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $customerId = $_POST['customer_id'] ?? '';
        $branchId = $_POST['branch_id'] ?? '';

        if (!empty($customerId) && !empty($branchId)) {
            foreach ($data['customers'] as &$c) {
                if ($c['id'] === $customerId && isset($c['branches'])) {
                    // 削除対象の営業所を記録
                    $deletedBranch = null;
                    foreach ($c['branches'] as &$b) {
                        if ($b['id'] === $branchId) {
                            // 論理削除
                            $b['deleted_at'] = date('Y-m-d H:i:s');
                            $b['deleted_by'] = $_SESSION['user_email'] ?? 'unknown';
                            $deletedBranch = $b;
                            break;
                        }
                    }
                    unset($b);

                    $c['updated_at'] = date('Y-m-d H:i:s');
                    encryptCustomerData($data);
                    saveData($data);

                    if ($deletedBranch) {
                        auditDelete('customer_branches', $branchId, '営業所を削除: ' . ($deletedBranch['name'] ?? '') . ' (顧客: ' . ($c['companyName'] ?? '') . ')', $deletedBranch);
                    }

                    header('Location: customers.php?branch_deleted=1#customer-' . $customerId);
                    exit;
                }
            }
            unset($c);
        }
    }
    header('Location: customers.php');
    exit;
}

// MFに存在しない顧客を一括削除（管理部のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete_orphans'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $customerIds = $_POST['customer_ids'] ?? [];

        if (!empty($customerIds) && is_array($customerIds)) {
            // 論理削除
            $deleted = 0;
            $deletedNames = [];
            foreach ($customerIds as $cid) {
                $deletedItem = softDelete($data['customers'], $cid);
                if ($deletedItem) {
                    $deleted++;
                    $deletedNames[] = $deletedItem['companyName'] ?? '';
                }
            }

            if ($deleted > 0) {
                encryptCustomerData($data);
                saveData($data);

                writeAuditLog('bulk_delete', 'customers', "MF未登録顧客を一括削除 ({$deleted}件)", [
                    'deleted_count' => $deleted,
                    'deleted_names' => $deletedNames
                ]);
            }

            header('Location: customers.php?bulk_deleted=' . $deleted);
            exit;
        }
    }
}

// 全顧客を削除（管理部のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_customers'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $confirmText = trim($_POST['confirm_text'] ?? '');

        if ($confirmText === '全削除') {
            // アクティブな顧客のみカウント（既に削除済みは除外）
            $activeCustomers = filterDeleted($data['customers'] ?? []);
            $deleted = count($activeCustomers);

            writeAuditLog('bulk_delete', 'customers', "全顧客を削除 ({$deleted}件)", [
                'deleted_count' => $deleted,
                'action' => 'delete_all'
            ]);

            // 全アクティブ顧客を論理削除
            foreach ($data['customers'] as &$c) {
                if (empty($c['deleted_at'])) {
                    $c['deleted_at'] = date('Y-m-d H:i:s');
                    $c['deleted_by'] = $_SESSION['user_email'] ?? 'system';
                }
            }
            unset($c);
            $data['customers_sync_timestamp'] = null;
            encryptCustomerData($data);
            saveData($data);

            header('Location: customers.php?all_deleted=' . $deleted);
            exit;
        } else {
            header('Location: customers.php?error=confirm_failed');
            exit;
        }
    }
}

// 再読み込み（削除済みを除外）
$customers = filterDeleted($data['customers'] ?? []);

// 検索フィルタ
$searchQuery = trim($_GET['q'] ?? '');
if (!empty($searchQuery)) {
    $customers = array_filter($customers, function($c) use ($searchQuery) {
        return stripos($c['companyName'] ?? '', $searchQuery) !== false ||
               stripos($c['contactPerson'] ?? '', $searchQuery) !== false ||
               stripos($c['notes'] ?? '', $searchQuery) !== false;
    });
}

// ソート（会社名順）
usort($customers, function($a, $b) {
    return strcmp($a['companyName'] ?? '', $b['companyName'] ?? '');
});

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
.customers-container {
    max-width: 1400px;
    padding: 1.5rem;
    margin: 0 auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-header h1 {
    margin: 0;
    font-size: 1.5rem;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.sync-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.sync-card h3 {
    margin: 0 0 1rem 0;
    font-size: 1.125rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sync-info {
    display: flex;
    gap: 2rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.sync-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sync-info-item .value {
    font-weight: 600;
    color: var(--primary);
}

.search-box {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.search-box form {
    display: flex;
    gap: 0.5rem;
}

.search-box input {
    flex: 1;
    padding: 0.5rem 1rem;
    border: 1px solid var(--gray-300);
    border-radius: 6px;
}

.customers-table-wrapper {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.customers-table {
    width: 100%;
    border-collapse: collapse;
}

.customers-table th,
.customers-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
}

.customers-table th {
    background: var(--gray-50);
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--gray-700);
}

.customers-table tr:hover {
    background: var(--gray-50);
}

.source-badge {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.source-badge.manual {
    background: #dbeafe;
    color: #1e40af;
}

.source-badge.mf {
    background: #fef3c7;
    color: #92400e;
}

.action-buttons {
    display: flex;
    gap: 0.25rem;
}

.btn-icon {
    padding: 0.375rem 0.5rem;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 4px;
    color: var(--gray-600);
}

.btn-icon:hover {
    background: var(--gray-100);
}

.btn-icon.danger:hover {
    background: var(--danger-light);
    color: var(--danger);
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--gray-500);
}

.stats-bar {
    display: flex;
    gap: 2rem;
    padding: 1rem;
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.875rem;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stat-value {
    font-weight: 600;
    color: var(--primary);
}

/* モーダル */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.modal-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--gray-200);
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.close {
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--gray-500);
}

.close:hover {
    color: var(--gray-700);
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.25rem;
    font-weight: 500;
    font-size: 0.875rem;
}

.form-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--gray-300);
    border-radius: 6px;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.not-synced-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    margin-top: 1rem;
}

.not-synced-item {
    padding: 0.5rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.875rem;
}

.not-synced-item:last-child {
    border-bottom: none;
}

.not-synced-item.checkbox-item {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: background 0.15s;
}

.not-synced-item.checkbox-item:hover {
    background: var(--gray-50);
}

.not-synced-item.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.orphan-warning-card {
    background: var(--warning-light);
    border: 1px solid var(--warning);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.orphan-warning-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    color: #E65100;
}

.orphan-warning-text {
    margin: 0.5rem 0 0 0;
    font-size: 0.875rem;
    color: #E65100;
}

.partner-group {
    border-bottom: 1px solid var(--gray-200);
    padding-bottom: 0.25rem;
    margin-bottom: 0.25rem;
}

.partner-group:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.partner-group-header {
    padding: 0.5rem 1rem;
    background: var(--gray-50);
    font-size: 0.875rem;
    color: var(--gray-700);
}

.branch-item {
    padding-left: 2rem !important;
    background: white;
}

.branch-item span {
    color: var(--gray-600);
}

.group-header-row {
    background: var(--gray-100);
}

.group-header-row td {
    padding: 0.5rem 1rem;
    border-bottom: none;
}

.branch-row td:first-child {
    padding-left: 1.5rem;
}

/* 展開可能な親会社行 */
.parent-row.has-branches:hover,
.group-header-row.has-branches:hover {
    background: var(--gray-100);
}

.branch-toggle {
    display: inline-block;
    width: 1rem;
    color: var(--gray-500);
    font-size: 0.75rem;
    transition: transform 0.2s;
}

.branch-toggle.expanded {
    transform: rotate(90deg);
}

.branch-count {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: normal;
}

.branch-row {
    background: var(--gray-50);
}

.branch-row:hover {
    background: var(--gray-100) !important;
}

/* 展開可能な会社グループ */
.company-group {
    border-bottom: 1px solid var(--gray-100);
}

.company-group:last-child {
    border-bottom: none;
}

.company-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.expand-btn {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border: 1px solid var(--gray-300);
    background: var(--gray-50);
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.75rem;
    color: var(--gray-600);
    transition: all 0.15s;
    margin-right: 0.5rem;
}

.expand-btn:hover {
    background: var(--gray-100);
    border-color: var(--gray-400);
}

.expand-btn.expanded {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.expand-btn.expanded .expand-icon {
    transform: rotate(45deg);
}

.expand-count {
    font-weight: 500;
}

.expand-icon {
    font-weight: bold;
    transition: transform 0.2s;
}

.branch-list {
    background: var(--gray-50);
    border-top: 1px solid var(--gray-200);
    padding: 0.5rem 0;
}

.branch-list .branch-item {
    padding: 0.375rem 1rem 0.375rem 2.5rem;
    font-size: 0.8125rem;
    color: var(--gray-600);
    border-bottom: none;
}

.branch-list .branch-item:before {
    content: "└ ";
    color: var(--gray-400);
}

/* 登録済み営業所行 */
.registered-branch {
    background: #eff6ff;
}

.registered-branch:hover {
    background: #dbeafe !important;
}
</style>

<div class="customers-container">
    <div class="page-header">
        <h1>顧客マスタ管理</h1>
        <div class="header-actions">
            <button class="btn btn-primary" data-action="openModal" data-modal="addModal">+ 顧客追加</button>
            <?php if (MFApiClient::isConfigured()): ?>
            <button class="btn btn-secondary" data-action="syncFromPartners" id="syncPartnersBtn" title="MF取引先マスタから住所・電話番号などを取得">
                <span   class="mr-05">📋</span> 取引先マスタ同期
            </button>
            <?php endif; ?>
            <?php if (canDelete() && count($data['customers'] ?? []) > 0): ?>
            <button class="btn btn-danger" data-action="openModal" data-modal="bulkDeleteAllModal">全削除</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['added'])): ?>
    <div   class="alert alert-success mb-2">顧客を追加しました</div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
    <div   class="alert alert-success mb-2">顧客情報を更新しました</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div   class="alert alert-success mb-2">顧客を削除しました</div>
    <?php endif; ?>
    <?php if (isset($_GET['synced'])): ?>
    <div   class="alert alert-success mb-2">同期が完了しました</div>
    <?php endif; ?>
    <?php if (isset($_GET['bulk_added'])): ?>
    <div   class="alert alert-success mb-2"><?= (int)$_GET['bulk_added'] ?>件の顧客を追加しました</div>
    <?php endif; ?>
    <?php if (isset($_GET['bulk_deleted'])): ?>
    <div   class="alert alert-success mb-2"><?= (int)$_GET['bulk_deleted'] ?>件の顧客を削除しました</div>
    <?php endif; ?>
    <?php if (isset($_GET['all_deleted'])): ?>
    <div   class="alert alert-success mb-2">全ての顧客（<?= (int)$_GET['all_deleted'] ?>件）を削除しました</div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'confirm_failed'): ?>
    <div   class="alert alert-danger mb-2">確認テキストが一致しません。「全削除」と入力してください。</div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'duplicate'): ?>
    <div   class="alert alert-danger mb-2">同じ会社名の顧客が既に存在します</div>
    <?php endif; ?>
    <?php if (isset($_GET['branch_added'])): ?>
    <div   class="alert alert-success mb-2">営業所を追加しました</div>
    <?php endif; ?>
    <?php if (isset($_GET['branch_updated'])): ?>
    <div   class="alert alert-success mb-2">営業所情報を更新しました</div>
    <?php endif; ?>
    <?php if (isset($_GET['branch_deleted'])): ?>
    <div   class="alert alert-success mb-2">営業所を削除しました</div>
    <?php endif; ?>

    <?php if ($mfInvoicesCount === 0): ?>
    <div class="sync-card">
        <h3>ℹ️ MF請求書データがありません</h3>
        <p>顧客を同期するには、先に損益管理でマネーフォワードからデータを同期してください。</p>
        <a href="finance.php" class="btn btn-primary">損益管理へ</a>
    </div>
    <?php endif; ?>

    <!-- MFに存在しない顧客の警告 -->
    <?php if (count($orphanCustomers) > 0): ?>
    <div class="orphan-warning-card">
        <div class="orphan-warning-header">
            <span>⚠️ MF請求書に存在しない顧客 (<?= count($orphanCustomers) ?>件)</span>
            <?php if (canDelete()): ?>
            <button type="button" class="btn btn-sm btn-outline" data-action="openModal" data-modal="orphanModal">確認・削除</button>
            <?php endif; ?>
        </div>
        <p class="orphan-warning-text">以下の顧客はMF請求書の取引先に存在しません。不要であれば削除できます。</p>
    </div>
    <?php endif; ?>

    <!-- 検索ボックス -->
    <div class="search-box">
        <form method="GET" action="">
            <input type="text" name="q" placeholder="会社名・担当者・メモで検索..." value="<?= htmlspecialchars($searchQuery) ?>">
            <button type="submit" class="btn btn-secondary">検索</button>
            <?php if (!empty($searchQuery)): ?>
            <a href="customers.php" class="btn btn-outline">クリア</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- 顧客一覧 -->
    <div class="customers-table-wrapper">
        <div class="stats-bar">
            <div class="stat-item">
                <span>登録顧客数:</span>
                <span class="stat-value"><?= count($data['customers'] ?? []) ?>件</span>
            </div>
            <?php if (!empty($searchQuery)): ?>
            <div class="stat-item">
                <span>検索結果:</span>
                <span class="stat-value"><?= count($customers) ?>件</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($data['customers_sync_timestamp'])): ?>
            <div class="stat-item">
                <span>最終同期:</span>
                <span><?= htmlspecialchars($data['customers_sync_timestamp']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($customers)): ?>
        <div class="empty-state">
            <p>顧客データがありません</p>
            <p>「顧客追加」または「MFから同期」で顧客を登録してください</p>
        </div>
        <?php else: ?>
        <?php
        // 顧客リストをスマートにグループ化（正規化関数を使用）
        $groupedCustomers = groupCustomers($customers);
        ?>
        <table class="customers-table" id="customersTable">
            <thead>
                <tr>
                    <th>会社名</th>
                    <th>担当者</th>
                    <th>電話番号</th>
                    <th>メールアドレス</th>
                    <th>メモ</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groupedCustomers as $parentName => $group): ?>
                    <?php
                    $hasMfBranches = !empty($group['branches']);
                    $groupId = 'group_' . md5($parentName);
                    ?>
                    <?php if ($group['parent']): ?>
                    <?php
                    $customer = $group['parent'];
                    // 削除済み営業所を除外
                    $customerBranches = array_filter($customer['branches'] ?? [], function($b) {
                        return !isset($b['deleted_at']);
                    });
                    $hasBranches = $hasMfBranches || !empty($customerBranches);
                    $branchCount = count($group['branches']) + count($customerBranches);
                    ?>
                    <!-- 親会社（単独または支店の親） -->
                    <tr class="parent-row <?= $hasBranches ? 'has-branches' : '' ?>" id="customer-<?= htmlspecialchars($customer['id']) ?>" data-group="<?= $groupId ?>" <?= $hasBranches ? 'data-action="toggleTableBranches" data-group-id="' . $groupId . '"  class="cursor-pointer"' : '' ?>>
                        <td>
                            <div  class="d-flex align-center gap-1">
                                <?php if ($hasBranches): ?>
                                <span class="branch-toggle" id="toggle_<?= $groupId ?>">▶</span>
                                <?php endif; ?>
                                <strong><?= htmlspecialchars($customer['companyName'] ?? '') ?></strong>
                                <?php if ($hasBranches): ?>
                                <span class="branch-count">(<?= $branchCount ?>営業所)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($customer['contactPerson'] ?? '') ?></td>
                        <td><?= htmlspecialchars($customer['phone'] ?? '') ?></td>
                        <td><?= htmlspecialchars($customer['email'] ?? '') ?></td>
                        <td><?= htmlspecialchars(mb_substr($customer['notes'] ?? '', 0, 30)) ?><?= mb_strlen($customer['notes'] ?? '') > 30 ? '...' : '' ?></td>
                        <td>
                            <div class="action-buttons" data-stop-propagation="true">
                                <button class="btn-icon" data-action="openAddBranchModal" data-customer-id="<?= htmlspecialchars($customer['id']) ?>" data-customer-name="<?= htmlspecialchars($customer['companyName']) ?>" title="営業所追加">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/></svg>
                                </button>
                                <button class="btn-icon" data-action="editCustomer" data-customer='<?= htmlspecialchars(json_encode($customer)) ?>' title="編集">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <?php if (canDelete()): ?>
                                <button class="btn-icon danger" data-action="confirmDelete" data-customer-id="<?= htmlspecialchars($customer['id']) ?>" data-customer-name="<?= htmlspecialchars($customer['companyName']) ?>" title="削除">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <?php foreach ($customerBranches as $branch): ?>
                    <!-- 登録済み営業所（デフォルト非表示） -->
                    <tr class="branch-row registered-branch <?= $groupId ?>" class="d-none">
                        <td>
                            <span        class="mr-1 text-primary ml-15">└</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"   class="align-middle mr-05"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/></svg>
                            <?= htmlspecialchars($branch['name'] ?? '') ?>
                        </td>
                        <td><?= htmlspecialchars($branch['contact'] ?? '') ?></td>
                        <td><?= htmlspecialchars($branch['phone'] ?? '') ?></td>
                        <td></td>
                        <td><?= htmlspecialchars(mb_substr($branch['address'] ?? '', 0, 20)) ?><?= mb_strlen($branch['address'] ?? '') > 20 ? '...' : '' ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon" data-action="editBranch" data-customer-id="<?= htmlspecialchars($customer['id']) ?>" data-branch='<?= htmlspecialchars(json_encode($branch)) ?>' title="編集">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <?php if (canDelete()): ?>
                                <button class="btn-icon danger" data-action="confirmDeleteBranch" data-customer-id="<?= htmlspecialchars($customer['id']) ?>" data-branch-id="<?= htmlspecialchars($branch['id']) ?>" data-branch-name="<?= htmlspecialchars($branch['name']) ?>" title="削除">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php foreach ($group['branches'] as $mfBranch): ?>
                    <!-- MF支店・営業所（デフォルト非表示） -->
                    <tr class="branch-row mf-branch <?= $groupId ?>" class="d-none">
                        <td>
                            <span        class="mr-1" class="text-gray-ml">└</span>
                            <?= htmlspecialchars($mfBranch['_branchName'] ?? $mfBranch['companyName']) ?>
                        </td>
                        <td><?= htmlspecialchars($mfBranch['contactPerson'] ?? '') ?></td>
                        <td><?= htmlspecialchars($mfBranch['phone'] ?? '') ?></td>
                        <td><?= htmlspecialchars($mfBranch['email'] ?? '') ?></td>
                        <td><?= htmlspecialchars(mb_substr($mfBranch['notes'] ?? '', 0, 30)) ?><?= mb_strlen($mfBranch['notes'] ?? '') > 30 ? '...' : '' ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon" data-action="editCustomer" data-customer='<?= htmlspecialchars(json_encode($mfBranch)) ?>' title="編集">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button class="btn-icon danger" data-action="confirmDelete" data-customer-id="<?= htmlspecialchars($mfBranch['id']) ?>" data-customer-name="<?= htmlspecialchars($mfBranch['companyName']) ?>" title="削除">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php elseif ($hasMfBranches): ?>
                    <!-- 親会社がない場合（支店のみ）はヘッダー行を表示 -->
                    <tr class="group-header-row has-branches" data-action="toggleTableBranches" data-group-id="<?= $groupId ?>" class="cursor-pointer">
                        <td colspan="6">
                            <div  class="d-flex align-center gap-1">
                                <span class="branch-toggle" id="toggle_<?= $groupId ?>">▶</span>
                                <strong><?= htmlspecialchars($parentName) ?></strong>
                                <span class="branch-count">(<?= count($group['branches']) ?>件)</span>
                            </div>
                        </td>
                    </tr>
                    <?php foreach ($group['branches'] as $mfBranch): ?>
                    <!-- MF支店・営業所（デフォルト非表示） -->
                    <tr class="branch-row <?= $groupId ?>" class="d-none">
                        <td>
                            <span        class="mr-1" class="text-gray-ml">└</span>
                            <?= htmlspecialchars($mfBranch['_branchName'] ?? $mfBranch['companyName']) ?>
                        </td>
                        <td><?= htmlspecialchars($mfBranch['contactPerson'] ?? '') ?></td>
                        <td><?= htmlspecialchars($mfBranch['phone'] ?? '') ?></td>
                        <td><?= htmlspecialchars($mfBranch['email'] ?? '') ?></td>
                        <td><?= htmlspecialchars(mb_substr($mfBranch['notes'] ?? '', 0, 30)) ?><?= mb_strlen($mfBranch['notes'] ?? '') > 30 ? '...' : '' ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon" data-action="editCustomer" data-customer='<?= htmlspecialchars(json_encode($mfBranch)) ?>' title="編集">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button class="btn-icon danger" data-action="confirmDelete" data-customer-id="<?= htmlspecialchars($mfBranch['id']) ?>" data-customer-name="<?= htmlspecialchars($mfBranch['companyName']) ?>" title="削除">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div id="customerTablePagination"></div>
        <?php endif; ?>
    </div>
</div>

<script<?= nonceAttr() ?>>
const csrfToken = '<?= generateCsrfToken() ?>';

// ページネーション初期化
document.addEventListener('DOMContentLoaded', function() {
    var table = document.getElementById('customersTable');
    if (table && table.querySelector('tbody tr')) {
        new Paginator({
            container: '#customersTable',
            itemSelector: 'tbody tr.parent-row, tbody tr.group-header-row',
            perPage: 50,
            paginationTarget: '#customerTablePagination',
            groupAttribute: 'data-group'
        });
    }

    // イベントリスナー登録

    // モーダル閉じるボタン
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal(this.dataset.closeModal);
        });
    });

    // data-action属性を持つボタンのイベントリスナー
    document.querySelectorAll('[data-action]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const action = this.dataset.action;

            // stopPropagation処理
            if (this.closest('[data-stop-propagation]')) {
                e.stopPropagation();
            }

            switch(action) {
                case 'openModal':
                    openModal(this.dataset.modal);
                    break;
                case 'closeModal':
                    closeModal(this.dataset.modal);
                    break;
                case 'syncFromPartners':
                    syncFromPartners();
                    break;
                case 'editCustomer':
                    editCustomer(JSON.parse(this.dataset.customer));
                    break;
                case 'confirmDelete':
                    confirmDelete(this.dataset.customerId, this.dataset.customerName);
                    break;
                case 'openAddBranchModal':
                    openAddBranchModal(this.dataset.customerId, this.dataset.customerName);
                    break;
                case 'editBranch':
                    editBranch(this.dataset.customerId, JSON.parse(this.dataset.branch));
                    break;
                case 'confirmDeleteBranch':
                    confirmDeleteBranch(this.dataset.customerId, this.dataset.branchId, this.dataset.branchName);
                    break;
                case 'toggleTableBranches':
                    toggleTableBranches(this.dataset.groupId);
                    break;
                case 'toggleOrphanCheckboxes':
                    toggleOrphanCheckboxes(this.dataset.checked === 'true');
                    break;
            }
        });
    });

    // モーダル外クリックで閉じる
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // 全削除フォームの確認入力
    const confirmInput = document.getElementById('confirmDeleteText');
    const deleteAllBtn = document.getElementById('deleteAllButton');
    if (confirmInput && deleteAllBtn) {
        confirmInput.addEventListener('input', function() {
            deleteAllBtn.disabled = this.value !== '全削除';
        });
    }

    // 削除フォーム送信処理
    const deleteForm = document.getElementById('bulkDeleteForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('#bulkDeleteForm input[type="checkbox"]:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('削除する顧客を選択してください');
                return;
            }
            if (!confirm(checkboxes.length + '件の顧客を削除しますか？\nこの操作は取り消せません。')) {
                e.preventDefault();
                return;
            }
            const btn = document.getElementById('bulkDeleteButton');
            if (btn) {
                btn.disabled = true;
                btn.textContent = '削除中...';
            }
        });

        deleteForm.addEventListener('change', function(e) {
            if (e.target.type === 'checkbox') {
                updateOrphanSelectedCount();
            }
        });
    }

    // URLパラメータクリーンアップ
    (function() {
        const url = new URL(window.location.href);
        const cleanParams = ['added', 'updated', 'deleted', 'synced', 'bulk_added', 'bulk_deleted', 'all_deleted', 'branch_added', 'branch_updated', 'branch_deleted', 'error'];
        let needsCleanup = false;
        cleanParams.forEach(param => {
            if (url.searchParams.has(param)) {
                needsCleanup = true;
                url.searchParams.delete(param);
            }
        });
        if (needsCleanup) {
            window.history.replaceState({}, '', url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : ''));
        }
    })();
});

// ヘルパー関数
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function editCustomer(customer) {
    document.getElementById('edit_customer_id').value = customer.id;
    document.getElementById('edit_company_name').value = customer.companyName || '';
    document.getElementById('edit_contact_person').value = customer.contactPerson || '';
    document.getElementById('edit_phone').value = customer.phone || '';
    document.getElementById('edit_email').value = customer.email || '';
    document.getElementById('edit_address').value = customer.address || '';
    document.getElementById('edit_notes').value = customer.notes || '';
    openModal('editModal');
}

function confirmDelete(id, name) {
    document.getElementById('delete_customer_id').value = id;
    document.getElementById('delete_customer_name').textContent = name;
    openModal('deleteModal');
}

function openAddBranchModal(customerId, customerName) {
    document.getElementById('add_branch_customer_id').value = customerId;
    document.getElementById('add_branch_customer_name').textContent = customerName;
    openModal('addBranchModal');
}

function editBranch(customerId, branch) {
    document.getElementById('edit_branch_customer_id').value = customerId;
    document.getElementById('edit_branch_id').value = branch.id;
    document.getElementById('edit_branch_name').value = branch.name || '';
    document.getElementById('edit_branch_contact').value = branch.contact || '';
    document.getElementById('edit_branch_phone').value = branch.phone || '';
    document.getElementById('edit_branch_address').value = branch.address || '';
    openModal('editBranchModal');
}

function confirmDeleteBranch(customerId, branchId, branchName) {
    document.getElementById('delete_branch_customer_id').value = customerId;
    document.getElementById('delete_branch_id').value = branchId;
    document.getElementById('delete_branch_name').textContent = branchName;
    openModal('deleteBranchModal');
}

async function syncFromPartners() {
    const btn = document.getElementById('syncPartnersBtn');
    const originalText = btn.innerHTML;

    if (!confirm('MF取引先マスタから顧客情報を同期しますか？\n\n・新規取引先は追加されます\n・既存顧客の住所・電話番号などが補完されます')) {
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span   class="mr-05">⏳</span> 同期中...';

    try {
        const response = await fetch('/api/sync-partners.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            }
        });

        const data = await response.json();

        if (data.success) {
            alert('✓ ' + data.message);
            window.location.reload();
        } else {
            alert('❌ エラー: ' + (data.error || '同期に失敗しました'));
        }
    } catch (e) {
        alert('❌ エラー: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function toggleOrphanCheckboxes(checked) {
    const checkboxes = document.querySelectorAll('#bulkDeleteForm input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = checked);
    updateOrphanSelectedCount();
}

function updateOrphanSelectedCount() {
    const checkboxes = document.querySelectorAll('#bulkDeleteForm input[type="checkbox"]:checked');
    const countEl = document.getElementById('orphanSelectedCount');
    if (countEl) {
        countEl.textContent = checkboxes.length;
    }
}

function toggleTableBranches(groupId) {
    const branchRows = document.querySelectorAll('.' + groupId);
    const toggle = document.getElementById('toggle_' + groupId);

    if (branchRows.length > 0) {
        const isHidden = branchRows[0].style.display === 'none';

        branchRows.forEach(row => {
            row.style.display = isHidden ? 'table-row' : 'none';
        });

        if (toggle) {
            toggle.classList.toggle('expanded', isHidden);
        }
    }
}
</script>

<!-- 顧客追加モーダル -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>顧客追加</h3>
            <button type="button" class="close" data-close-modal="addModal">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfTokenField() ?>
            <input type="hidden" name="add_customer" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label>会社名 *</label>
                    <input type="text" class="form-input" name="company_name" required>
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="contact_person">
                </div>
                <div class="form-group">
                    <label>電話番号</label>
                    <input type="tel" class="form-input" name="phone">
                </div>
                <div class="form-group">
                    <label>メールアドレス</label>
                    <input type="email" class="form-input" name="email">
                </div>
                <div class="form-group">
                    <label>住所</label>
                    <input type="text" class="form-input" name="address">
                </div>
                <div class="form-group">
                    <label>メモ</label>
                    <textarea class="form-input" name="notes" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="addModal">キャンセル</button>
                <button type="submit" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- 顧客編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>顧客編集</h3>
            <button type="button" class="close" data-close-modal="editModal">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfTokenField() ?>
            <input type="hidden" name="update_customer" value="1">
            <input type="hidden" name="customer_id" id="edit_customer_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>会社名 *</label>
                    <input type="text" class="form-input" name="company_name" id="edit_company_name" required>
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="contact_person" id="edit_contact_person">
                </div>
                <div class="form-group">
                    <label>電話番号</label>
                    <input type="tel" class="form-input" name="phone" id="edit_phone">
                </div>
                <div class="form-group">
                    <label>メールアドレス</label>
                    <input type="email" class="form-input" name="email" id="edit_email">
                </div>
                <div class="form-group">
                    <label>住所</label>
                    <input type="text" class="form-input" name="address" id="edit_address">
                </div>
                <div class="form-group">
                    <label>メモ</label>
                    <textarea class="form-input" name="notes" id="edit_notes" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal('editModal')">キャンセル</button>
                <button type="submit" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 削除確認モーダル -->
<div id="deleteModal" class="modal">
    <div       class="modal-content max-w-400">
        <div class="modal-header">
            <h3>削除確認</h3>
            <button type="button" class="close" data-close-modal="deleteModal">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfTokenField() ?>
            <input type="hidden" name="delete_customer" value="1">
            <input type="hidden" name="customer_id" id="delete_customer_id">
            <div class="modal-body">
                <p><strong id="delete_customer_name"></strong> を削除しますか？</p>
                <p     class="text-14 text-danger">この操作は取り消せません。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal('deleteModal')">キャンセル</button>
                <button type="submit" class="btn btn-danger">削除</button>
            </div>
        </form>
    </div>
</div>

<!-- MFに存在しない顧客削除モーダル -->
<div id="orphanModal" class="modal">
    <div         class="modal-content max-w-600">
        <form method="POST" id="bulkDeleteForm">
            <?= csrfTokenField() ?>
            <input type="hidden" name="bulk_delete_orphans" value="1">
            <div class="modal-header">
                <h3>MFに存在しない顧客</h3>
                <button type="button" class="close" data-close-modal="orphanModal">&times;</button>
            </div>
            <div class="modal-body">
                <p    class="mb-2 text-gray-600">
                    以下の顧客はMF請求書の取引先に存在しません。<br>
                    選択した顧客を削除できます。
                </p>

                <?php if (count($orphanCustomers) > 0): ?>
                <div  class="d-flex justify-between align-center mb-1">
                    <p  class="m-0"><strong>対象顧客 (<?= count($orphanCustomers) ?>件):</strong></p>
                    <div>
                        <button type="button" class="btn btn-sm btn-secondary" data-action="toggleOrphanCheckboxes" data-checked="true">全選択</button>
                        <button type="button" class="btn btn-sm btn-secondary" data-action="toggleOrphanCheckboxes" data-checked="false">全解除</button>
                    </div>
                </div>
                <div         class="not-synced-list overflow-y-auto max-h-300">
                    <?php foreach ($orphanCustomers as $orphan): ?>
                    <label class="not-synced-item checkbox-item">
                        <input type="checkbox" name="customer_ids[]" value="<?= htmlspecialchars($orphan['id']) ?>" checked>
                        <span><?= htmlspecialchars($orphan['companyName']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p    class="mt-1 text-14 text-gray-600">
                    選択件数: <strong id="orphanSelectedCount"><?= count($orphanCustomers) ?></strong>件
                </p>
                <?php endif; ?>

                <p        class="mt-2 text-14 p-075" class="bg-fee">
                    ⚠️ 削除した顧客データは復元できません
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal('orphanModal')">キャンセル</button>
                <?php if (count($orphanCustomers) > 0): ?>
                <button type="submit" class="btn btn-danger" id="bulkDeleteButton">選択した顧客を削除</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- 営業所追加モーダル -->
<div id="addBranchModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🏢 営業所追加</h3>
            <button type="button" class="close" data-close-modal="addBranchModal">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfTokenField() ?>
            <input type="hidden" name="add_branch" value="1">
            <input type="hidden" name="customer_id" id="add_branch_customer_id">
            <div class="modal-body">
                <p        class="mb-2 p-1 bg-gray-50 rounded">
                    親会社: <strong id="add_branch_customer_name"></strong>
                </p>
                <div class="form-group">
                    <label>営業所名 *</label>
                    <input type="text" class="form-input" name="branch_name" required placeholder="例: 大阪営業所">
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="branch_contact">
                </div>
                <div class="form-group">
                    <label>電話番号</label>
                    <input type="tel" class="form-input" name="branch_phone">
                </div>
                <div class="form-group">
                    <label>住所</label>
                    <input type="text" class="form-input" name="branch_address">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal('addBranchModal')">キャンセル</button>
                <button type="submit" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- 営業所編集モーダル -->
<div id="editBranchModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🏢 営業所編集</h3>
            <button type="button" class="close" data-close-modal="editBranchModal">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfTokenField() ?>
            <input type="hidden" name="update_branch" value="1">
            <input type="hidden" name="customer_id" id="edit_branch_customer_id">
            <input type="hidden" name="branch_id" id="edit_branch_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>営業所名 *</label>
                    <input type="text" class="form-input" name="branch_name" id="edit_branch_name" required>
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="branch_contact" id="edit_branch_contact">
                </div>
                <div class="form-group">
                    <label>電話番号</label>
                    <input type="tel" class="form-input" name="branch_phone" id="edit_branch_phone">
                </div>
                <div class="form-group">
                    <label>住所</label>
                    <input type="text" class="form-input" name="branch_address" id="edit_branch_address">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal('editBranchModal')">キャンセル</button>
                <button type="submit" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 営業所削除確認モーダル -->
<div id="deleteBranchModal" class="modal">
    <div       class="modal-content max-w-400">
        <div class="modal-header">
            <h3>営業所削除確認</h3>
            <button type="button" class="close" data-close-modal="deleteBranchModal">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfTokenField() ?>
            <input type="hidden" name="delete_branch" value="1">
            <input type="hidden" name="customer_id" id="delete_branch_customer_id">
            <input type="hidden" name="branch_id" id="delete_branch_id">
            <div class="modal-body">
                <p>営業所 <strong id="delete_branch_name"></strong> を削除しますか？</p>
                <p     class="text-14 text-danger">この操作は取り消せません。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal('deleteBranchModal')">キャンセル</button>
                <button type="submit" class="btn btn-danger">削除</button>
            </div>
        </form>
    </div>
</div>

<!-- 全削除確認モーダル -->
<div id="bulkDeleteAllModal" class="modal">
    <div         class="modal-content max-w-450">
        <form method="POST" id="deleteAllForm">
            <?= csrfTokenField() ?>
            <input type="hidden" name="delete_all_customers" value="1">
            <div class="modal-header">
                <h3>⚠️ 全顧客削除</h3>
                <button type="button" class="close" data-close-modal="bulkDeleteAllModal">&times;</button>
            </div>
            <div class="modal-body">
                <p  class="mb-2">
                    <strong    class="text-danger">登録されている全ての顧客（<?= count($data['customers'] ?? []) ?>件）を削除します。</strong>
                </p>
                <p        class="mb-2 text-14 p-075" class="bg-fee">
                    ⚠️ この操作は取り消せません。削除したデータは復元できません。
                </p>
                <div class="form-group">
                    <label>確認のため「全削除」と入力してください</label>
                    <input type="text" class="form-input" name="confirm_text" id="confirmDeleteText" placeholder="全削除" autocomplete="off" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal('bulkDeleteAllModal')">キャンセル</button>
                <button type="submit" class="btn btn-danger" id="deleteAllButton" disabled>全顧客を削除</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../functions/footer.php'; ?>
