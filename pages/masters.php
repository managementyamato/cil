<?php
require_once '../api/auth.php';

// 内部エンコーディングをUTF-8に設定
mb_internal_encoding('UTF-8');

$data = getData();

$message = '';
$messageType = '';

// POST処理時のCSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// タブ切り替え
$activeTab = $_GET['tab'] ?? 'customers';

// ===== 顧客マスタ処理 =====

// 顧客追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $companyName = trim($_POST['company_name'] ?? '');

    if ($companyName) {
        $newCustomer = [
            'id' => 'c_' . uniqid(),
            'companyName' => $companyName,
            'contactPerson' => trim($_POST['contact_person'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $data['customers'][] = $newCustomer;
        saveData($data);
        $message = '顧客を追加しました';
        $messageType = 'success';
        $activeTab = 'customers';
    } else {
        $message = '会社名は必須です';
        $messageType = 'danger';
    }
}

// 顧客更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    $customerId = $_POST['customer_id'] ?? '';

    foreach ($data['customers'] as &$customer) {
        if ($customer['id'] === $customerId) {
            $customer['companyName'] = trim($_POST['company_name'] ?? '');
            $customer['contactPerson'] = trim($_POST['contact_person'] ?? '');
            $customer['phone'] = trim($_POST['phone'] ?? '');
            $customer['email'] = trim($_POST['email'] ?? '');
            $customer['address'] = trim($_POST['address'] ?? '');
            $customer['notes'] = trim($_POST['notes'] ?? '');
            $customer['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($customer);

    saveData($data);
    $message = '顧客情報を更新しました';
    $messageType = 'success';
    $activeTab = 'customers';
}

// 顧客削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    $customerId = $_POST['customer_id'] ?? '';

    $data['customers'] = array_values(array_filter($data['customers'], function($c) use ($customerId) {
        return $c['id'] !== $customerId;
    }));

    saveData($data);
    $message = '顧客を削除しました';
    $messageType = 'success';
    $activeTab = 'customers';
}

// ===== 担当者マスタ処理 =====

// 担当者追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignee'])) {
    $name = trim($_POST['assignee_name'] ?? '');

    if ($name) {
        // 重複チェック
        $exists = false;
        foreach ($data['assignees'] ?? [] as $a) {
            if ($a['name'] === $name) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $newAssignee = [
                'id' => 'a_' . uniqid(),
                'name' => $name,
                'email' => trim($_POST['assignee_email'] ?? ''),
                'phone' => trim($_POST['assignee_phone'] ?? ''),
                'notes' => trim($_POST['assignee_notes'] ?? ''),
                'created_at' => date('Y-m-d H:i:s')
            ];

            if (!isset($data['assignees'])) {
                $data['assignees'] = [];
            }
            $data['assignees'][] = $newAssignee;
            saveData($data);
            $message = '担当者を追加しました';
            $messageType = 'success';
        } else {
            $message = 'この担当者名は既に登録されています';
            $messageType = 'danger';
        }
        $activeTab = 'assignees';
    } else {
        $message = '担当者名は必須です';
        $messageType = 'danger';
    }
}

// 担当者更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assignee'])) {
    $assigneeId = $_POST['assignee_id'] ?? '';

    foreach ($data['assignees'] as &$assignee) {
        if ($assignee['id'] === $assigneeId) {
            $assignee['name'] = trim($_POST['assignee_name'] ?? '');
            $assignee['email'] = trim($_POST['assignee_email'] ?? '');
            $assignee['phone'] = trim($_POST['assignee_phone'] ?? '');
            $assignee['notes'] = trim($_POST['assignee_notes'] ?? '');
            $assignee['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($assignee);

    saveData($data);
    $message = '担当者情報を更新しました';
    $messageType = 'success';
    $activeTab = 'assignees';
}

// 担当者削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignee'])) {
    $assigneeId = $_POST['assignee_id'] ?? '';

    $data['assignees'] = array_values(array_filter($data['assignees'] ?? [], function($a) use ($assigneeId) {
        return $a['id'] !== $assigneeId;
    }));

    saveData($data);
    $message = '担当者を削除しました';
    $messageType = 'success';
    $activeTab = 'assignees';
}

// ===== パートナーマスタ処理 =====

// パートナー追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_partner'])) {
    $companyName = trim($_POST['partner_company_name'] ?? '');

    if ($companyName) {
        // 重複チェック
        $exists = false;
        foreach ($data['partners'] ?? [] as $p) {
            if ($p['companyName'] === $companyName) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $newPartner = [
                'id' => 'pt_' . uniqid(),
                'companyName' => $companyName,
                'contactPerson' => trim($_POST['partner_contact_person'] ?? ''),
                'phone' => trim($_POST['partner_phone'] ?? ''),
                'email' => trim($_POST['partner_email'] ?? ''),
                'address' => trim($_POST['partner_address'] ?? ''),
                'notes' => trim($_POST['partner_notes'] ?? ''),
                'created_at' => date('Y-m-d H:i:s')
            ];

            if (!isset($data['partners'])) {
                $data['partners'] = [];
            }
            $data['partners'][] = $newPartner;
            saveData($data);
            $message = 'パートナーを追加しました';
            $messageType = 'success';
        } else {
            $message = 'このパートナー名は既に登録されています';
            $messageType = 'danger';
        }
        $activeTab = 'partners';
    } else {
        $message = '会社名は必須です';
        $messageType = 'danger';
    }
}

// パートナー更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_partner'])) {
    $partnerId = $_POST['partner_id'] ?? '';

    foreach ($data['partners'] as &$partner) {
        if ($partner['id'] === $partnerId) {
            $partner['companyName'] = trim($_POST['partner_company_name'] ?? '');
            $partner['contactPerson'] = trim($_POST['partner_contact_person'] ?? '');
            $partner['phone'] = trim($_POST['partner_phone'] ?? '');
            $partner['email'] = trim($_POST['partner_email'] ?? '');
            $partner['address'] = trim($_POST['partner_address'] ?? '');
            $partner['notes'] = trim($_POST['partner_notes'] ?? '');
            $partner['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($partner);

    saveData($data);
    $message = 'パートナー情報を更新しました';
    $messageType = 'success';
    $activeTab = 'partners';
}

// パートナー削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_partner'])) {
    $partnerId = $_POST['partner_id'] ?? '';

    $data['partners'] = array_values(array_filter($data['partners'] ?? [], function($p) use ($partnerId) {
        return $p['id'] !== $partnerId;
    }));

    saveData($data);
    $message = 'パートナーを削除しました';
    $messageType = 'success';
    $activeTab = 'partners';
}

// ===== 商品カテゴリマスタ処理 =====

// 商品カテゴリ追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $categoryName = trim($_POST['category_name'] ?? '');

    if ($categoryName) {
        // 重複チェック
        $exists = false;
        foreach ($data['productCategories'] ?? [] as $c) {
            if ($c['name'] === $categoryName) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $newCategory = [
                'id' => 'cat_' . uniqid(),
                'name' => $categoryName,
                'notes' => trim($_POST['category_notes'] ?? ''),
                'created_at' => date('Y-m-d H:i:s')
            ];

            if (!isset($data['productCategories'])) {
                $data['productCategories'] = [];
            }
            $data['productCategories'][] = $newCategory;
            saveData($data);
            $message = '商品カテゴリを追加しました';
            $messageType = 'success';
        } else {
            $message = 'このカテゴリ名は既に登録されています';
            $messageType = 'danger';
        }
        $activeTab = 'categories';
    } else {
        $message = 'カテゴリ名は必須です';
        $messageType = 'danger';
    }
}

// 商品カテゴリ更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $categoryId = $_POST['category_id'] ?? '';

    foreach ($data['productCategories'] as &$category) {
        // idがある場合はidで比較、なければnameで比較（後方互換性）
        if (($category['id'] ?? $category['name']) === $categoryId) {
            // idがなければ付与
            if (!isset($category['id'])) {
                $category['id'] = 'cat_' . uniqid();
            }
            $category['name'] = trim($_POST['category_name'] ?? '');
            $category['notes'] = trim($_POST['category_notes'] ?? '');
            $category['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($category);

    saveData($data);
    $message = '商品カテゴリを更新しました';
    $messageType = 'success';
    $activeTab = 'categories';
}

// 商品カテゴリ削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $categoryId = $_POST['category_id'] ?? '';

    $data['productCategories'] = array_values(array_filter($data['productCategories'] ?? [], function($c) use ($categoryId) {
        // idがある場合はidで比較、なければnameで比較（後方互換性）
        return ($c['id'] ?? $c['name']) !== $categoryId;
    }));

    saveData($data);
    $message = '商品カテゴリを削除しました';
    $messageType = 'success';
    $activeTab = 'categories';
}

// 既存データにidがない場合は自動付与（商品カテゴリ）
$needsSave = false;
foreach ($data['productCategories'] ?? [] as &$cat) {
    if (!isset($cat['id'])) {
        $cat['id'] = 'cat_' . uniqid();
        $needsSave = true;
    }
}
unset($cat);
if ($needsSave) {
    saveData($data);
}

// 顧客データをソート（会社名順）
$customers = $data['customers'] ?? [];
usort($customers, function($a, $b) {
    return strcmp($a['companyName'] ?? '', $b['companyName'] ?? '');
});

// 会社名からベース会社名を抽出する関数
function extractBaseCompanyName($companyName) {
    // 「株式会社」「有限会社」などの法人格を保持しつつ、支店・部署名を分離
    // パターン1: "○○株式会社　支店名　部署名" → "○○株式会社"
    // パターン2: "株式会社○○　支店名" → "株式会社○○"（スペースの前まで）
    // パターン3: "株式会社アクティオ　四国支店 坂出営業所" → "株式会社アクティオ"

    // 法人格の位置を特定
    $corporateTypes = ['株式会社', '有限会社', '合同会社', '合資会社', '合名会社'];

    foreach ($corporateTypes as $type) {
        $pos = mb_strpos($companyName, $type, 0, 'UTF-8');
        if ($pos !== false) {
            if ($pos === 0) {
                // 法人格が先頭にある場合（例: "株式会社○○ 支店"）
                // 法人格の後の最初のスペースまでをベース名とする
                $afterType = mb_substr($companyName, mb_strlen($type, 'UTF-8'), null, 'UTF-8');
                // 全角スペース、半角スペースで分割
                $parts = preg_split('/[\s　]+/u', trim($afterType), 2);
                return $type . $parts[0];
            } else {
                // 法人格が後ろにある場合（例: "○○株式会社 支店"）
                $endPos = $pos + mb_strlen($type, 'UTF-8');
                return mb_substr($companyName, 0, $endPos, 'UTF-8');
            }
        }
    }

    // 法人格がない場合は元の名前をそのまま返す
    return $companyName;
}

// 顧客をベース会社名でグループ化
$customerGroups = [];
foreach ($customers as $customer) {
    $baseName = extractBaseCompanyName($customer['companyName'] ?? '');
    if (!isset($customerGroups[$baseName])) {
        $customerGroups[$baseName] = [];
    }
    $customerGroups[$baseName][] = $customer;
}

// グループをソート
ksort($customerGroups);

// 担当者データをソート（名前順）
$assignees = $data['assignees'] ?? [];
usort($assignees, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

// パートナーデータをソート（会社名順）
$partners = $data['partners'] ?? [];
usort($partners, function($a, $b) {
    return strcmp($a['companyName'] ?? '', $b['companyName'] ?? '');
});

// 商品カテゴリデータをソート（名前順）
$categories = $data['productCategories'] ?? [];
usort($categories, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

require_once '../functions/header.php';
?>

<style>
/* タブナビゲーション */
.tabs {
    display: flex;
    border-bottom: 2px solid var(--gray-200);
    margin-bottom: 1.5rem;
    gap: 0;
}
.tab {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--gray-600);
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}
.tab:hover {
    color: var(--gray-900);
    background: var(--gray-50);
}
.tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}
.tab svg {
    width: 18px;
    height: 18px;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}

/* リスト形式レイアウト */
.master-list {
    display: flex;
    flex-direction: column;
    gap: 0;
    background: white;
    border-radius: 12px;
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.master-list-item {
    display: grid;
    grid-template-columns: 1fr minmax(120px, 180px) minmax(150px, 220px) minmax(100px, 200px) 80px;
    align-items: center;
    padding: 0.875rem 1rem;
    border-bottom: 1px solid var(--gray-100);
    transition: background 0.15s;
    gap: 1rem;
}
.master-list-item:last-child {
    border-bottom: none;
}
.master-list-item:hover {
    background: var(--gray-50);
}
.master-list-header {
    background: var(--gray-50);
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.75rem 1rem;
}
.master-list-header:hover {
    background: var(--gray-50);
}
.master-list-name {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
    color: var(--gray-800);
    min-width: 0;
}
.master-list-name span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.master-list-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.master-list-icon svg {
    width: 16px;
    height: 16px;
}
.master-list-icon.customer { background: var(--primary-light); color: var(--primary); }
.master-list-icon.partner { background: var(--success-light); color: var(--success); }
.master-list-icon.assignee { background: var(--purple-light); color: var(--purple); }
.master-list-icon.category { background: var(--warning-light); color: var(--warning); }
.master-list-contact {
    font-size: 0.85rem;
    color: var(--gray-600);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.master-list-email {
    font-size: 0.85rem;
    color: var(--gray-500);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.master-list-email a {
    color: var(--primary);
    text-decoration: none;
}
.master-list-email a:hover {
    text-decoration: underline;
}
.master-list-address {
    font-size: 0.8rem;
    color: var(--gray-500);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.master-list-actions {
    display: flex;
    gap: 0.25rem;
    justify-content: flex-end;
}

/* レスポンシブ対応 */
@media (max-width: 900px) {
    .master-list-item {
        grid-template-columns: 1fr 100px 60px;
    }
    .master-list-email,
    .master-list-address {
        display: none;
    }
    .master-list-header .master-list-email,
    .master-list-header .master-list-address {
        display: none;
    }
}

/* ボタン */
.btn-icon {
    padding: 0.375rem;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 6px;
    color: var(--gray-400);
    transition: all 0.2s;
}
.btn-icon:hover {
    background: var(--gray-100);
    color: var(--gray-700);
}
.btn-icon.danger:hover {
    background: #fef2f2;
    color: #dc2626;
}

/* カウントバッジ */
.count-badge {
    background: var(--gray-100);
    color: var(--gray-600);
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    margin-left: 0.5rem;
}

/* 検索ボックス */
.search-filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: white;
    border-radius: 12px;
    border: 1px solid var(--gray-200);
    flex-wrap: wrap;
    gap: 1rem;
}
.search-box {
    flex: 1;
    min-width: 200px;
    max-width: 400px;
}
.search-box input {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    font-size: 0.9rem;
    background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat 0.75rem center;
}
.search-box input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
}

/* 空状態 */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--gray-500);
}
.empty-state svg {
    width: 64px;
    height: 64px;
    color: var(--gray-300);
    margin-bottom: 1rem;
}

/* 支店表示 */
.branch-badge {
    display: inline-block;
    background: #dbeafe;
    color: #1d4ed8;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    margin-left: 0.5rem;
}

@media (max-width: 768px) {
    .search-filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .search-box {
        max-width: 100%;
    }
    .master-list-item {
        grid-template-columns: 1fr 80px !important;
    }
    .master-list-contact,
    .master-list-email,
    .master-list-address {
        display: none !important;
    }
}

/* 顧客グループ表示 */
.customer-group-list {
    background: white;
    border-radius: 12px;
    border: 1px solid var(--gray-200);
    overflow: hidden;
}
.customer-group, .customer-single {
    border-bottom: 1px solid var(--gray-100);
}
.customer-group:last-child, .customer-single:last-child {
    border-bottom: none;
}

/* グループヘッダー */
.customer-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1rem;
    cursor: pointer;
    transition: background 0.15s;
}
.customer-group-header:hover {
    background: var(--gray-50);
}
.customer-group-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.customer-group-name {
    font-weight: 600;
    color: var(--gray-800);
}
.expand-icon {
    color: var(--gray-400);
    font-size: 0.7rem;
    transition: transform 0.2s;
    width: 1rem;
    text-align: center;
}
.expand-icon.expanded {
    transform: rotate(90deg);
}
.branch-count {
    color: var(--gray-500);
    font-size: 0.8rem;
    font-weight: normal;
    background: var(--gray-100);
    padding: 0.15rem 0.5rem;
    border-radius: 10px;
}

/* グループ内アイテム */
.customer-group-items {
    background: var(--gray-50);
    border-top: 1px solid var(--gray-100);
}
.customer-group-item {
    display: grid;
    grid-template-columns: 1fr minmax(100px, 150px) minmax(120px, 200px) 70px;
    align-items: center;
    padding: 0.625rem 1rem 0.625rem 2.5rem;
    border-bottom: 1px solid var(--gray-100);
    gap: 0.75rem;
    font-size: 0.9rem;
}
.customer-group-item:last-child {
    border-bottom: none;
}
.customer-group-item:hover {
    background: var(--gray-100);
}
.customer-item-name {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--gray-700);
}
.branch-indent {
    display: none;
}
.customer-item-contact {
    color: var(--gray-600);
    font-size: 0.85rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.customer-item-email {
    font-size: 0.85rem;
    color: var(--gray-500);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.customer-item-email a {
    color: var(--primary);
    text-decoration: none;
}
.customer-item-email a:hover {
    text-decoration: underline;
}
.customer-item-actions {
    display: flex;
    gap: 0.25rem;
    justify-content: flex-end;
}

/* 単独顧客 */
.customer-single-content {
    display: grid;
    grid-template-columns: 1fr minmax(100px, 150px) minmax(120px, 200px) 70px;
    align-items: center;
    padding: 0.875rem 1rem;
    gap: 0.75rem;
    transition: background 0.15s;
}
.customer-single-content:hover {
    background: var(--gray-50);
}
.customer-single-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.customer-single-name {
    font-weight: 500;
    color: var(--gray-800);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.customer-single-contact {
    color: var(--gray-600);
    font-size: 0.85rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.customer-single-email {
    font-size: 0.85rem;
    color: var(--gray-500);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.customer-single-email a {
    color: var(--primary);
    text-decoration: none;
}
.customer-single-email a:hover {
    text-decoration: underline;
}
.customer-single-actions {
    display: flex;
    gap: 0.25rem;
    justify-content: flex-end;
}

/* レスポンシブ（顧客グループ） */
@media (max-width: 768px) {
    .customer-group-item,
    .customer-single-content {
        grid-template-columns: 1fr 60px;
    }
    .customer-item-contact,
    .customer-item-email,
    .customer-single-contact,
    .customer-single-email {
        display: none;
    }
}
</style>

<div class="page-header">
    <h1>マスタ管理</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 1rem;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="tabs">
        <button class="tab <?= $activeTab === 'customers' ? 'active' : '' ?>" onclick="switchTab('customers')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            顧客 <span class="count-badge"><?= count($customers) ?></span>
        </button>
        <button class="tab <?= $activeTab === 'assignees' ? 'active' : '' ?>" onclick="switchTab('assignees')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            担当者 <span class="count-badge"><?= count($assignees) ?></span>
        </button>
        <button class="tab <?= $activeTab === 'partners' ? 'active' : '' ?>" onclick="switchTab('partners')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            パートナー <span class="count-badge"><?= count($partners) ?></span>
        </button>
        <button class="tab <?= $activeTab === 'categories' ? 'active' : '' ?>" onclick="switchTab('categories')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            カテゴリ <span class="count-badge"><?= count($categories) ?></span>
        </button>
    </div>

    <!-- 顧客タブ -->
    <div id="tab-customers" class="tab-content <?= $activeTab === 'customers' ? 'active' : '' ?>">
        <div class="search-filter-bar">
            <div class="search-box">
                <input type="text" id="customerSearch" placeholder="顧客名で検索..." oninput="filterCustomers()">
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="customers.php" class="btn btn-secondary" title="MF請求書から顧客を同期できます" style="background: #fef3c7; border-color: #f59e0b; color: #92400e;">
                    📥 MFから取得
                </a>
                <button class="btn btn-primary" onclick="openModal('addCustomerModal')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.25rem;"><path d="M12 5v14M5 12h14"/></svg>
                    顧客追加
                </button>
            </div>
        </div>

        <?php if (empty($customers)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <p>顧客が登録されていません</p>
                <p style="font-size: 0.875rem; margin-top: 0.5rem;">「MFから取得」でマネーフォワード請求書から顧客を同期できます</p>
            </div>
        <?php else: ?>
            <div class="customer-group-list" id="customersTable">
                <?php
                $groupIndex = 0;
                foreach ($customerGroups as $baseName => $groupCustomers):
                    $groupIndex++;
                    $groupId = 'group_' . $groupIndex;
                    $hasMultiple = count($groupCustomers) > 1;
                    $firstCustomer = $groupCustomers[0];
                ?>
                <?php if ($hasMultiple): ?>
                <!-- グループ（複数の支店・部署がある場合） -->
                <div class="customer-group" data-name="<?= htmlspecialchars(strtolower($baseName)) ?>">
                    <div class="customer-group-header" onclick="toggleGroup('<?= $groupId ?>')">
                        <div class="customer-group-left">
                            <div class="master-list-icon customer">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            </div>
                            <span class="customer-group-name"><?= htmlspecialchars($baseName) ?></span>
                            <span class="branch-count">(<?= count($groupCustomers) ?>件)</span>
                        </div>
                    </div>
                    <div class="customer-group-items" id="<?= $groupId ?>" style="display:none;">
                        <?php foreach ($groupCustomers as $customer):
                            // 支店・部署名を取得（ベース名の後にある部分）
                            $fullName = $customer['companyName'] ?? '';
                            // ベース名をpreg_quoteでエスケープして正規表現で除去
                            $pattern = '/^' . preg_quote($baseName, '/') . '[\s　]*/u';
                            $branchName = preg_replace($pattern, '', $fullName);
                            $displayName = ($branchName && $branchName !== $fullName) ? $branchName : '(本社)';
                        ?>
                        <div class="customer-group-item" data-name="<?= htmlspecialchars(strtolower($customer['companyName'] ?? '')) ?>">
                            <div class="customer-item-name">
                                <span class="branch-indent"></span>
                                <span><?= htmlspecialchars($displayName) ?></span>
                            </div>
                            <div class="customer-item-contact"><?= htmlspecialchars($customer['contactPerson'] ?? '-') ?></div>
                            <div class="customer-item-email">
                                <?php if (!empty($customer['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($customer['email']) ?>"><?= htmlspecialchars($customer['email']) ?></a>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </div>
                            <div class="customer-item-actions">
                                <button class="btn-icon" onclick='event.stopPropagation(); editCustomer(<?= json_encode($customer, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)' title="編集">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button class="btn-icon danger" onclick="event.stopPropagation(); deleteCustomer('<?= $customer['id'] ?>', '<?= htmlspecialchars($customer['companyName'], ENT_QUOTES) ?>')" title="削除">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <!-- 単独の顧客 -->
                <div class="customer-single" data-name="<?= htmlspecialchars(strtolower($firstCustomer['companyName'] ?? '')) ?>">
                    <div class="customer-single-content">
                        <div class="customer-single-left">
                            <div class="master-list-icon customer">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            </div>
                            <span class="customer-single-name"><?= htmlspecialchars($firstCustomer['companyName'] ?? '') ?></span>
                        </div>
                        <div class="customer-single-contact"><?= htmlspecialchars($firstCustomer['contactPerson'] ?? '-') ?></div>
                        <div class="customer-single-email">
                            <?php if (!empty($firstCustomer['email'])): ?>
                            <a href="mailto:<?= htmlspecialchars($firstCustomer['email']) ?>"><?= htmlspecialchars($firstCustomer['email']) ?></a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </div>
                        <div class="customer-single-actions">
                            <button class="btn-icon" onclick='editCustomer(<?= json_encode($firstCustomer, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)' title="編集">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="btn-icon danger" onclick="deleteCustomer('<?= $firstCustomer['id'] ?>', '<?= htmlspecialchars($firstCustomer['companyName'], ENT_QUOTES) ?>')" title="削除">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 担当者タブ -->
    <div id="tab-assignees" class="tab-content <?= $activeTab === 'assignees' ? 'active' : '' ?>">
        <div class="search-filter-bar">
            <div class="search-box">
                <input type="text" id="assigneeSearch" placeholder="担当者名で検索..." oninput="filterAssignees()">
            </div>
            <button class="btn btn-primary" onclick="openModal('addAssigneeModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.25rem;"><path d="M12 5v14M5 12h14"/></svg>
                担当者追加
            </button>
        </div>

        <?php if (empty($assignees)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <p>担当者が登録されていません</p>
            </div>
        <?php else: ?>
            <div class="master-list" id="assigneesTable">
                <div class="master-list-item master-list-header">
                    <div class="master-list-name">担当者名</div>
                    <div class="master-list-contact">電話番号</div>
                    <div class="master-list-email">メールアドレス</div>
                    <div class="master-list-address">備考</div>
                    <div class="master-list-actions"></div>
                </div>
                <?php foreach ($assignees as $assignee): ?>
                <div class="master-list-item" data-name="<?= htmlspecialchars(strtolower($assignee['name'] ?? '')) ?>">
                    <div class="master-list-name">
                        <div class="master-list-icon assignee">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <span><?= htmlspecialchars($assignee['name'] ?? '') ?></span>
                    </div>
                    <div class="master-list-contact"><?= htmlspecialchars($assignee['phone'] ?? '-') ?></div>
                    <div class="master-list-email">
                        <?php if (!empty($assignee['email'])): ?>
                        <a href="mailto:<?= htmlspecialchars($assignee['email']) ?>"><?= htmlspecialchars($assignee['email']) ?></a>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </div>
                    <div class="master-list-address"><?= htmlspecialchars($assignee['notes'] ?? '-') ?></div>
                    <div class="master-list-actions">
                        <button class="btn-icon" onclick='editAssignee(<?= json_encode($assignee, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="編集">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="btn-icon danger" onclick="deleteAssignee('<?= $assignee['id'] ?>', '<?= htmlspecialchars($assignee['name'], ENT_QUOTES) ?>')" title="削除">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- パートナータブ -->
    <div id="tab-partners" class="tab-content <?= $activeTab === 'partners' ? 'active' : '' ?>">
        <div class="search-filter-bar">
            <div class="search-box">
                <input type="text" id="partnerSearch" placeholder="パートナー名で検索..." oninput="filterPartners()">
            </div>
            <button class="btn btn-primary" onclick="openModal('addPartnerModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.25rem;"><path d="M12 5v14M5 12h14"/></svg>
                パートナー追加
            </button>
        </div>

        <?php if (empty($partners)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <p>パートナーが登録されていません</p>
                <p style="font-size: 0.875rem; margin-top: 0.5rem;">設置業者・撤去業者などを登録してください</p>
            </div>
        <?php else: ?>
            <div class="master-list" id="partnersTable">
                <div class="master-list-item master-list-header">
                    <div class="master-list-name">会社名</div>
                    <div class="master-list-contact">担当者</div>
                    <div class="master-list-email">メールアドレス</div>
                    <div class="master-list-address">住所</div>
                    <div class="master-list-actions"></div>
                </div>
                <?php foreach ($partners as $partner): ?>
                <div class="master-list-item" data-name="<?= htmlspecialchars(strtolower($partner['companyName'] ?? '')) ?>">
                    <div class="master-list-name">
                        <div class="master-list-icon partner">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <span><?= htmlspecialchars($partner['companyName'] ?? '') ?></span>
                    </div>
                    <div class="master-list-contact"><?= htmlspecialchars($partner['contactPerson'] ?? '-') ?></div>
                    <div class="master-list-email">
                        <?php if (!empty($partner['email'])): ?>
                        <a href="mailto:<?= htmlspecialchars($partner['email']) ?>"><?= htmlspecialchars($partner['email']) ?></a>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </div>
                    <div class="master-list-address"><?= htmlspecialchars($partner['address'] ?? '-') ?></div>
                    <div class="master-list-actions">
                        <button class="btn-icon" onclick='editPartner(<?= json_encode($partner, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)' title="編集">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="btn-icon danger" onclick="deletePartner('<?= $partner['id'] ?>', '<?= htmlspecialchars($partner['companyName'], ENT_QUOTES) ?>')" title="削除">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 商品カテゴリタブ -->
    <div id="tab-categories" class="tab-content <?= $activeTab === 'categories' ? 'active' : '' ?>">
        <div class="search-filter-bar">
            <div class="search-box">
                <input type="text" id="categorySearch" placeholder="カテゴリ名で検索..." oninput="filterCategories()">
            </div>
            <button class="btn btn-primary" onclick="openModal('addCategoryModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.25rem;"><path d="M12 5v14M5 12h14"/></svg>
                カテゴリ追加
            </button>
        </div>

        <?php if (empty($categories)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                <p>商品カテゴリが登録されていません</p>
                <p style="font-size: 0.875rem; margin-top: 0.5rem;">案件登録時の商品大分類として使用されます</p>
            </div>
        <?php else: ?>
            <div class="master-list" id="categoriesTable">
                <div class="master-list-item master-list-header" style="grid-template-columns: 1fr 1fr 80px;">
                    <div class="master-list-name">カテゴリ名</div>
                    <div class="master-list-contact">備考</div>
                    <div class="master-list-actions"></div>
                </div>
                <?php foreach ($categories as $category): ?>
                <div class="master-list-item" data-name="<?= htmlspecialchars(strtolower($category['name'] ?? '')) ?>" style="grid-template-columns: 1fr 1fr 80px;">
                    <div class="master-list-name">
                        <div class="master-list-icon category">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                        </div>
                        <span><?= htmlspecialchars($category['name'] ?? '') ?></span>
                    </div>
                    <div class="master-list-contact"><?= htmlspecialchars($category['notes'] ?? '-') ?></div>
                    <div class="master-list-actions">
                        <button class="btn-icon" onclick='editCategory(<?= json_encode($category, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)' title="編集">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="btn-icon danger" onclick="deleteCategory('<?= $category['id'] ?? htmlspecialchars($category['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>')" title="削除">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 顧客追加モーダル -->
<div id="addCustomerModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3>顧客追加</h3>
            <span class="close" onclick="closeModal('addCustomerModal')">&times;</span>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>会社名 <span style="color:#dc2626;">*</span></label>
                    <input type="text" class="form-input" name="company_name" required>
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="contact_person">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>電話番号</label>
                        <input type="tel" class="form-input" name="phone">
                    </div>
                    <div class="form-group">
                        <label>メール</label>
                        <input type="email" class="form-input" name="email">
                    </div>
                </div>
                <div class="form-group">
                    <label>住所</label>
                    <input type="text" class="form-input" name="address">
                </div>
                <div class="form-group">
                    <label>備考</label>
                    <textarea class="form-input" name="notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCustomerModal')">キャンセル</button>
                <button type="submit" name="add_customer" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- 顧客編集モーダル -->
<div id="editCustomerModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3>顧客編集</h3>
            <span class="close" onclick="closeModal('editCustomerModal')">&times;</span>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="customer_id" id="edit_customer_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>会社名 <span style="color:#dc2626;">*</span></label>
                    <input type="text" class="form-input" name="company_name" id="edit_company_name" required>
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="contact_person" id="edit_contact_person">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>電話番号</label>
                        <input type="tel" class="form-input" name="phone" id="edit_phone">
                    </div>
                    <div class="form-group">
                        <label>メール</label>
                        <input type="email" class="form-input" name="email" id="edit_email">
                    </div>
                </div>
                <div class="form-group">
                    <label>住所</label>
                    <input type="text" class="form-input" name="address" id="edit_address">
                </div>
                <div class="form-group">
                    <label>備考</label>
                    <textarea class="form-input" name="notes" id="edit_notes" rows="2"></textarea>
                </div>
                <div class="form-group" id="edit_aliases_group" style="display:none;">
                    <label>別名（支店・営業所等）</label>
                    <div id="edit_aliases_list" style="background:#f8fafc; padding:0.75rem; border-radius:6px; border:1px solid var(--gray-200); font-size:0.875rem; color:var(--gray-600);"></div>
                    <p style="font-size:0.75rem; color:var(--gray-500); margin-top:0.25rem;">※ 別名は自動で紐付けられます</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editCustomerModal')">キャンセル</button>
                <button type="submit" name="update_customer" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 顧客削除フォーム -->
<form id="deleteCustomerForm" method="POST" style="display:none;">
    <?= csrfTokenField() ?>
    <input type="hidden" name="customer_id" id="delete_customer_id">
    <input type="hidden" name="delete_customer" value="1">
</form>

<!-- 担当者追加モーダル -->
<div id="addAssigneeModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3>担当者追加</h3>
            <span class="close" onclick="closeModal('addAssigneeModal')">&times;</span>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>担当者名 <span style="color:#dc2626;">*</span></label>
                    <input type="text" class="form-input" name="assignee_name" required>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>メール</label>
                        <input type="email" class="form-input" name="assignee_email">
                    </div>
                    <div class="form-group">
                        <label>電話番号</label>
                        <input type="tel" class="form-input" name="assignee_phone">
                    </div>
                </div>
                <div class="form-group">
                    <label>備考</label>
                    <textarea class="form-input" name="assignee_notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addAssigneeModal')">キャンセル</button>
                <button type="submit" name="add_assignee" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- 担当者編集モーダル -->
<div id="editAssigneeModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3>担当者編集</h3>
            <span class="close" onclick="closeModal('editAssigneeModal')">&times;</span>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="assignee_id" id="edit_assignee_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>担当者名 <span style="color:#dc2626;">*</span></label>
                    <input type="text" class="form-input" name="assignee_name" id="edit_assignee_name" required>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>メール</label>
                        <input type="email" class="form-input" name="assignee_email" id="edit_assignee_email">
                    </div>
                    <div class="form-group">
                        <label>電話番号</label>
                        <input type="tel" class="form-input" name="assignee_phone" id="edit_assignee_phone">
                    </div>
                </div>
                <div class="form-group">
                    <label>備考</label>
                    <textarea class="form-input" name="assignee_notes" id="edit_assignee_notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editAssigneeModal')">キャンセル</button>
                <button type="submit" name="update_assignee" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 担当者削除フォーム -->
<form id="deleteAssigneeForm" method="POST" style="display:none;">
    <?= csrfTokenField() ?>
    <input type="hidden" name="assignee_id" id="delete_assignee_id">
    <input type="hidden" name="delete_assignee" value="1">
</form>

<!-- パートナー追加モーダル -->
<div id="addPartnerModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3>パートナー追加</h3>
            <span class="close" onclick="closeModal('addPartnerModal')">&times;</span>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>会社名 <span style="color:#dc2626;">*</span></label>
                    <input type="text" class="form-input" name="partner_company_name" required>
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="partner_contact_person">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>電話番号</label>
                        <input type="tel" class="form-input" name="partner_phone">
                    </div>
                    <div class="form-group">
                        <label>メール</label>
                        <input type="email" class="form-input" name="partner_email">
                    </div>
                </div>
                <div class="form-group">
                    <label>住所</label>
                    <input type="text" class="form-input" name="partner_address">
                </div>
                <div class="form-group">
                    <label>備考</label>
                    <textarea class="form-input" name="partner_notes" rows="2" placeholder="設置業者、撤去業者など"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPartnerModal')">キャンセル</button>
                <button type="submit" name="add_partner" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- パートナー編集モーダル -->
<div id="editPartnerModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3>パートナー編集</h3>
            <span class="close" onclick="closeModal('editPartnerModal')">&times;</span>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="partner_id" id="edit_partner_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>会社名 <span style="color:#dc2626;">*</span></label>
                    <input type="text" class="form-input" name="partner_company_name" id="edit_partner_company_name" required>
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="partner_contact_person" id="edit_partner_contact_person">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label>電話番号</label>
                        <input type="tel" class="form-input" name="partner_phone" id="edit_partner_phone">
                    </div>
                    <div class="form-group">
                        <label>メール</label>
                        <input type="email" class="form-input" name="partner_email" id="edit_partner_email">
                    </div>
                </div>
                <div class="form-group">
                    <label>住所</label>
                    <input type="text" class="form-input" name="partner_address" id="edit_partner_address">
                </div>
                <div class="form-group">
                    <label>備考</label>
                    <textarea class="form-input" name="partner_notes" id="edit_partner_notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editPartnerModal')">キャンセル</button>
                <button type="submit" name="update_partner" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- パートナー削除フォーム -->
<form id="deletePartnerForm" method="POST" style="display:none;">
    <?= csrfTokenField() ?>
    <input type="hidden" name="partner_id" id="delete_partner_id">
    <input type="hidden" name="delete_partner" value="1">
</form>

<!-- 商品カテゴリ追加モーダル -->
<div id="addCategoryModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3>商品カテゴリ追加</h3>
            <span class="close" onclick="closeModal('addCategoryModal')">&times;</span>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>カテゴリ名 <span style="color:#dc2626;">*</span></label>
                    <input type="text" class="form-input" name="category_name" required placeholder="例：トイレ、浄化槽、仮設ハウスなど">
                </div>
                <div class="form-group">
                    <label>備考</label>
                    <textarea class="form-input" name="category_notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCategoryModal')">キャンセル</button>
                <button type="submit" name="add_category" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- 商品カテゴリ編集モーダル -->
<div id="editCategoryModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3>商品カテゴリ編集</h3>
            <span class="close" onclick="closeModal('editCategoryModal')">&times;</span>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="category_id" id="edit_category_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>カテゴリ名 <span style="color:#dc2626;">*</span></label>
                    <input type="text" class="form-input" name="category_name" id="edit_category_name" required>
                </div>
                <div class="form-group">
                    <label>備考</label>
                    <textarea class="form-input" name="category_notes" id="edit_category_notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editCategoryModal')">キャンセル</button>
                <button type="submit" name="update_category" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 商品カテゴリ削除フォーム -->
<form id="deleteCategoryForm" method="POST" style="display:none;">
    <?= csrfTokenField() ?>
    <input type="hidden" name="category_id" id="delete_category_id">
    <input type="hidden" name="delete_category" value="1">
</form>

<script>
function switchTab(tabName) {
    // タブボタン
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');

    // タブコンテンツ
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');

    // URL更新
    history.replaceState(null, '', '?tab=' + tabName);
}

function openModal(id) {
    document.getElementById(id).style.display = 'block';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

// 顧客編集
function editCustomer(customer) {
    document.getElementById('edit_customer_id').value = customer.id;
    document.getElementById('edit_company_name').value = customer.companyName || '';
    document.getElementById('edit_contact_person').value = customer.contactPerson || '';
    document.getElementById('edit_phone').value = customer.phone || '';
    document.getElementById('edit_email').value = customer.email || '';
    document.getElementById('edit_address').value = customer.address || '';
    document.getElementById('edit_notes').value = customer.notes || '';

    // 別名（支店・営業所等）を表示
    const aliasesGroup = document.getElementById('edit_aliases_group');
    const aliasesList = document.getElementById('edit_aliases_list');
    const aliases = customer.aliases || [];

    if (aliases.length > 0) {
        aliasesList.innerHTML = aliases.map(a => '<div style="padding:0.25rem 0; border-bottom:1px dashed var(--gray-200);">' + escapeHtml(a) + '</div>').join('');
        aliasesGroup.style.display = 'block';
    } else {
        aliasesGroup.style.display = 'none';
    }

    openModal('editCustomerModal');
}

// HTMLエスケープ
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 顧客削除
function deleteCustomer(id, name) {
    if (confirm('「' + name + '」を削除しますか？')) {
        document.getElementById('delete_customer_id').value = id;
        document.getElementById('deleteCustomerForm').submit();
    }
}

// 担当者編集
function editAssignee(assignee) {
    document.getElementById('edit_assignee_id').value = assignee.id;
    document.getElementById('edit_assignee_name').value = assignee.name || '';
    document.getElementById('edit_assignee_email').value = assignee.email || '';
    document.getElementById('edit_assignee_phone').value = assignee.phone || '';
    document.getElementById('edit_assignee_notes').value = assignee.notes || '';
    openModal('editAssigneeModal');
}

// 担当者削除
function deleteAssignee(id, name) {
    if (confirm('「' + name + '」を削除しますか？')) {
        document.getElementById('delete_assignee_id').value = id;
        document.getElementById('deleteAssigneeForm').submit();
    }
}

// グループ展開/折りたたみ
function toggleGroup(groupId) {
    const container = document.getElementById(groupId);
    if (container) {
        const isHidden = container.style.display === 'none';
        container.style.display = isHidden ? 'block' : 'none';
    }
}

// 顧客検索
function filterCustomers() {
    const query = document.getElementById('customerSearch').value.toLowerCase();

    // グループを検索
    document.querySelectorAll('#customersTable .customer-group').forEach(group => {
        const groupName = group.dataset.name || '';
        const items = group.querySelectorAll('.customer-group-item');
        let hasMatch = groupName.includes(query);

        // 子アイテムも検索
        items.forEach(item => {
            const itemName = item.dataset.name || '';
            if (itemName.includes(query)) {
                hasMatch = true;
                item.style.display = '';
            } else {
                item.style.display = query ? 'none' : '';
            }
        });

        group.style.display = hasMatch ? '' : 'none';

        // マッチがあれば展開
        if (hasMatch && query) {
            const groupId = group.querySelector('.customer-group-items')?.id;
            if (groupId) {
                document.getElementById(groupId).style.display = 'block';
            }
        }
    });

    // 単独顧客を検索
    document.querySelectorAll('#customersTable .customer-single').forEach(single => {
        const name = single.dataset.name || '';
        single.style.display = name.includes(query) ? '' : 'none';
    });
}

// 担当者検索
function filterAssignees() {
    const query = document.getElementById('assigneeSearch').value.toLowerCase();
    const items = document.querySelectorAll('#assigneesTable .master-list-item:not(.master-list-header)');
    items.forEach(item => {
        const name = item.dataset.name || '';
        item.style.display = name.includes(query) ? '' : 'none';
    });
}

// パートナー編集
function editPartner(partner) {
    document.getElementById('edit_partner_id').value = partner.id;
    document.getElementById('edit_partner_company_name').value = partner.companyName || '';
    document.getElementById('edit_partner_contact_person').value = partner.contactPerson || '';
    document.getElementById('edit_partner_phone').value = partner.phone || '';
    document.getElementById('edit_partner_email').value = partner.email || '';
    document.getElementById('edit_partner_address').value = partner.address || '';
    document.getElementById('edit_partner_notes').value = partner.notes || '';
    openModal('editPartnerModal');
}

// パートナー削除
function deletePartner(id, name) {
    if (confirm('「' + name + '」を削除しますか？\n\n※ 案件で使用中の場合、参照が残る可能性があります')) {
        document.getElementById('delete_partner_id').value = id;
        document.getElementById('deletePartnerForm').submit();
    }
}

// パートナー検索
function filterPartners() {
    const query = document.getElementById('partnerSearch').value.toLowerCase();
    const items = document.querySelectorAll('#partnersTable .master-list-item:not(.master-list-header)');
    items.forEach(item => {
        const name = item.dataset.name || '';
        item.style.display = name.includes(query) ? '' : 'none';
    });
}

// 商品カテゴリ編集
function editCategory(category) {
    // idがない場合はnameをIDとして使用（後方互換性）
    document.getElementById('edit_category_id').value = category.id || category.name;
    document.getElementById('edit_category_name').value = category.name || '';
    document.getElementById('edit_category_notes').value = category.notes || '';
    openModal('editCategoryModal');
}

// 商品カテゴリ削除
function deleteCategory(id, name) {
    if (confirm('「' + name + '」を削除しますか？\n\n※ 案件で使用中の場合、参照が残る可能性があります')) {
        document.getElementById('delete_category_id').value = id;
        document.getElementById('deleteCategoryForm').submit();
    }
}

// 商品カテゴリ検索
function filterCategories() {
    const query = document.getElementById('categorySearch').value.toLowerCase();
    const items = document.querySelectorAll('#categoriesTable .master-list-item:not(.master-list-header)');
    items.forEach(item => {
        const name = item.dataset.name || '';
        item.style.display = name.includes(query) ? '' : 'none';
    });
}

// モーダル外クリックで閉じる
window.onclick = function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
}
</script>

<?php require_once '../functions/footer.php'; ?>
