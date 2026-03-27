<?php
require_once '../api/auth.php';
require_once '../functions/encryption.php';

// 内部エンコーディングをUTF-8に設定
mb_internal_encoding('UTF-8');

// タブ切り替え（空の場合は一覧表示）
$activeTab = $_GET['tab'] ?? '';

// 顧客タブはcustomers.phpにリダイレクト
if ($activeTab === 'customers') {
    header('Location: customers.php');
    exit;
}

$data = getData();
decryptCustomerData($data);

$message = '';
$messageType = '';

// POST処理時のCSRF検証・編集権限チェック
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    // 削除以外の追加・編集処理は編集権限が必要
    $deleteActions = ['delete_customer', 'delete_assignee', 'delete_partner', 'delete_category', 'delete_manufacturer', 'delete_trouble_responder', 'delete_prefecture', 'delete_general_contractor', 'delete_area'];
    $isDeleteAction = false;
    foreach ($deleteActions as $act) {
        if (isset($_POST[$act])) { $isDeleteAction = true; break; }
    }
    if (!$isDeleteAction && !canEdit()) {
        http_response_code(403);
        exit('権限がありません');
    }
}

// ===== 顧客マスタ処理（互換性のため残す - POSTはcustomers.phpで処理） =====

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
        encryptCustomerData($data);
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

    encryptCustomerData($data);
    saveData($data);
    $message = '顧客情報を更新しました';
    $messageType = 'success';
    $activeTab = 'customers';
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

        $message = '顧客を削除しました';
        $messageType = 'success';
    }
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
            encryptCustomerData($data);
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

    encryptCustomerData($data);
    saveData($data);
    $message = '担当者情報を更新しました';
    $messageType = 'success';
    $activeTab = 'assignees';
}

// 担当者削除（管理部のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignee'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $assigneeId = $_POST['assignee_id'] ?? '';

        $data['assignees'] = array_values(array_filter($data['assignees'] ?? [], function($a) use ($assigneeId) {
            return $a['id'] !== $assigneeId;
        }));

        encryptCustomerData($data);
    saveData($data);
        $message = '担当者を削除しました';
        $messageType = 'success';
    }
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
            encryptCustomerData($data);
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

    encryptCustomerData($data);
    saveData($data);
    $message = 'パートナー情報を更新しました';
    $messageType = 'success';
    $activeTab = 'partners';
}

// パートナー削除（管理部のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_partner'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $partnerId = $_POST['partner_id'] ?? '';

        $data['partners'] = array_values(array_filter($data['partners'] ?? [], function($p) use ($partnerId) {
            return $p['id'] !== $partnerId;
        }));

        encryptCustomerData($data);
    saveData($data);
        $message = 'パートナーを削除しました';
        $messageType = 'success';
    }
    $activeTab = 'partners';
}

// ===== 製品名初期データ追加 =====
$defaultProducts = ['モニたろう', 'モニすけ', 'モニまる', 'ゲンバルジャー', 'PICLES', 'モニんじゃ'];
$existingNames = array_column($data['productCategories'] ?? [], 'name');
$added = false;
foreach ($defaultProducts as $productName) {
    if (!in_array($productName, $existingNames)) {
        $data['productCategories'][] = [
            'id' => 'cat_' . uniqid(),
            'name' => $productName,
            'notes' => '',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $added = true;
    }
}
if ($added) {
    saveData($data);
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
                'tag_name' => trim($_POST['category_tag_name'] ?? ''),
                'maker_ids' => array_values(array_filter($_POST['category_maker_ids'] ?? [])),
                'notes' => trim($_POST['category_notes'] ?? ''),
                'created_at' => date('Y-m-d H:i:s')
            ];

            if (!isset($data['productCategories'])) {
                $data['productCategories'] = [];
            }
            $data['productCategories'][] = $newCategory;
            encryptCustomerData($data);
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
            $category['tag_name'] = trim($_POST['category_tag_name'] ?? '');
            $category['maker_ids'] = array_values(array_filter($_POST['category_maker_ids'] ?? []));
            $category['notes'] = trim($_POST['category_notes'] ?? '');
            $category['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($category);

    encryptCustomerData($data);
    saveData($data);
    $message = '商品カテゴリを更新しました';
    $messageType = 'success';
    $activeTab = 'categories';
}

// 商品カテゴリ削除（管理部のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $categoryId = $_POST['category_id'] ?? '';

        $data['productCategories'] = array_values(array_filter($data['productCategories'] ?? [], function($c) use ($categoryId) {
            // idがある場合はidで比較、なければnameで比較（後方互換性）
            return ($c['id'] ?? $c['name']) !== $categoryId;
        }));

        encryptCustomerData($data);
    saveData($data);
        $message = '商品カテゴリを削除しました';
        $messageType = 'success';
    }
    $activeTab = 'categories';
}

// ===== メーカーマスタ処理 =====

// メーカー追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manufacturer'])) {
    $makerName = trim($_POST['manufacturer_name'] ?? '');

    if ($makerName) {
        // 重複チェック
        $exists = false;
        foreach ($data['manufacturers'] ?? [] as $m) {
            if ($m['name'] === $makerName) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $newManufacturer = [
                'id' => 'maker_' . uniqid(),
                'name' => $makerName,
                'contact' => trim($_POST['manufacturer_contact'] ?? ''),
                'phone' => trim($_POST['manufacturer_phone'] ?? ''),
                'email' => trim($_POST['manufacturer_email'] ?? ''),
                'notes' => trim($_POST['manufacturer_notes'] ?? ''),
                'created_at' => date('Y-m-d H:i:s')
            ];

            if (!isset($data['manufacturers'])) {
                $data['manufacturers'] = [];
            }
            $data['manufacturers'][] = $newManufacturer;
            encryptCustomerData($data);
    saveData($data);
            $message = 'メーカーを追加しました';
            $messageType = 'success';
        } else {
            $message = 'このメーカー名は既に登録されています';
            $messageType = 'danger';
        }
        $activeTab = 'manufacturers';
    } else {
        $message = 'メーカー名は必須です';
        $messageType = 'danger';
    }
}

// メーカー更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_manufacturer'])) {
    $makerId = $_POST['manufacturer_id'] ?? '';

    foreach ($data['manufacturers'] as &$manufacturer) {
        if ($manufacturer['id'] === $makerId) {
            $manufacturer['name'] = trim($_POST['manufacturer_name'] ?? '');
            $manufacturer['contact'] = trim($_POST['manufacturer_contact'] ?? '');
            $manufacturer['phone'] = trim($_POST['manufacturer_phone'] ?? '');
            $manufacturer['email'] = trim($_POST['manufacturer_email'] ?? '');
            $manufacturer['notes'] = trim($_POST['manufacturer_notes'] ?? '');
            $manufacturer['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($manufacturer);

    encryptCustomerData($data);
    saveData($data);
    $message = 'メーカー情報を更新しました';
    $messageType = 'success';
    $activeTab = 'manufacturers';
}

// メーカー削除（管理部のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_manufacturer'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $makerId = $_POST['manufacturer_id'] ?? '';

        // 論理削除
        $deletedManufacturer = softDelete($data['manufacturers'], $makerId);

        if ($deletedManufacturer) {
            encryptCustomerData($data);
            saveData($data);
            auditDelete('manufacturers', $makerId, 'メーカーを削除: ' . ($deletedManufacturer['name'] ?? ''), $deletedManufacturer);
        }

        $message = 'メーカーを削除しました';
        $messageType = 'success';
    }
    $activeTab = 'manufacturers';
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
    encryptCustomerData($data);
    saveData($data);
}

// 顧客データをソート（会社名順）- 削除済みを除外
$customers = filterDeleted($data['customers'] ?? []);
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

// パートナーデータをソート（会社名順）- 削除済みを除外
$partners = filterDeleted($data['partners'] ?? []);
usort($partners, function($a, $b) {
    return strcmp($a['companyName'] ?? '', $b['companyName'] ?? '');
});

// 商品カテゴリデータをソート（名前順）
$categories = $data['productCategories'] ?? [];
usort($categories, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

// メーカーデータをソート（名前順）- 削除済みを除外
$manufacturers = filterDeleted($data['manufacturers'] ?? []);
usort($manufacturers, function($a, $b) {
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

require_once '../functions/header.php';
?>

<style<?= nonceAttr() ?>>
/* マスタ選択グリッド */
.master-select-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}
.master-select-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
}
.master-select-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.master-select-icon {
    width: 48px;
    height: 48px;
    background: var(--gray-100);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.master-select-icon svg {
    width: 24px;
    height: 24px;
    color: var(--gray-600);
}
.master-select-card:hover .master-select-icon {
    background: var(--primary-light);
}
.master-select-card:hover .master-select-icon svg {
    color: var(--primary);
}
.master-select-info {
    flex: 1;
}
.master-select-name {
    font-weight: 600;
    font-size: 1rem;
    color: var(--gray-900);
}
.master-select-count {
    font-size: 0.875rem;
    color: var(--gray-500);
    margin-top: 0.25rem;
}
.master-select-arrow {
    width: 20px;
    height: 20px;
    color: var(--gray-400);
    flex-shrink: 0;
}
.master-select-card:hover .master-select-arrow {
    color: var(--primary);
}



/* シンプルマスタリスト */
.simple-master-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.simple-master-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--gray-100);
}
.simple-master-item:last-child {
    border-bottom: none;
}
.simple-master-item:hover {
    background: var(--gray-50);
}
.simple-master-name {
    font-weight: 500;
}
.simple-master-add {
    display: flex;
    gap: 0.5rem;
    padding: 1rem;
    border-top: 1px solid var(--gray-200);
    background: var(--gray-50);
}
.simple-master-add input {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--gray-300);
    border-radius: 6px;
    font-size: 0.875rem;
}

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

<?php
// 追加マスタの処理

// トラブル担当者の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_trouble_responder'])) {
    $name = trim($_POST['responder_name'] ?? '');
    if ($name) {
        $exists = false;
        foreach ($data['troubleResponders'] ?? [] as $r) {
            if ($r['name'] === $name) { $exists = true; break; }
        }
        if (!$exists) {
            if (!isset($data['troubleResponders'])) $data['troubleResponders'] = [];
            $data['troubleResponders'][] = [
                'id' => 'tr_' . uniqid(),
                'name' => $name,
                'created_at' => date('Y-m-d H:i:s')
            ];
            encryptCustomerData($data);
    saveData($data);
            $message = 'トラブル担当者を追加しました';
            $messageType = 'success';
        } else {
            $message = 'この担当者名は既に登録されています';
            $messageType = 'danger';
        }
        $activeTab = 'trouble_responders';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_trouble_responder'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $id = $_POST['responder_id'] ?? '';
        $data['troubleResponders'] = array_values(array_filter($data['troubleResponders'] ?? [], fn($r) => $r['id'] !== $id));
        encryptCustomerData($data);
    saveData($data);
        $message = 'トラブル担当者を削除しました';
        $messageType = 'success';
    }
    $activeTab = 'trouble_responders';
}

// 都道府県の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_prefecture'])) {
    $name = trim($_POST['prefecture_name'] ?? '');
    if ($name) {
        $exists = false;
        foreach ($data['prefectures'] ?? [] as $p) {
            if ($p['name'] === $name) { $exists = true; break; }
        }
        if (!$exists) {
            if (!isset($data['prefectures'])) $data['prefectures'] = [];
            $data['prefectures'][] = [
                'id' => 'pref_' . uniqid(),
                'name' => $name,
                'created_at' => date('Y-m-d H:i:s')
            ];
            encryptCustomerData($data);
    saveData($data);
            $message = '都道府県を追加しました';
            $messageType = 'success';
        } else {
            $message = 'この都道府県は既に登録されています';
            $messageType = 'danger';
        }
        $activeTab = 'prefectures';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_prefecture'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $id = $_POST['prefecture_id'] ?? '';
        $data['prefectures'] = array_values(array_filter($data['prefectures'] ?? [], fn($p) => $p['id'] !== $id));
        encryptCustomerData($data);
    saveData($data);
        $message = '都道府県を削除しました';
        $messageType = 'success';
    }
    $activeTab = 'prefectures';
}

// ゼネコンの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_general_contractor'])) {
    $name = trim($_POST['contractor_name'] ?? '');
    if ($name) {
        $exists = false;
        foreach ($data['generalContractors'] ?? [] as $g) {
            if ($g['name'] === $name) { $exists = true; break; }
        }
        if (!$exists) {
            if (!isset($data['generalContractors'])) $data['generalContractors'] = [];
            $data['generalContractors'][] = [
                'id' => 'gc_' . uniqid(),
                'name' => $name,
                'created_at' => date('Y-m-d H:i:s')
            ];
            encryptCustomerData($data);
    saveData($data);
            $message = 'ゼネコンを追加しました';
            $messageType = 'success';
        } else {
            $message = 'このゼネコン名は既に登録されています';
            $messageType = 'danger';
        }
        $activeTab = 'general_contractors';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_general_contractor'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $id = $_POST['contractor_id'] ?? '';
        $data['generalContractors'] = array_values(array_filter($data['generalContractors'] ?? [], fn($g) => $g['id'] !== $id));
        encryptCustomerData($data);
    saveData($data);
        $message = 'ゼネコンを削除しました';
        $messageType = 'success';
    }
    $activeTab = 'general_contractors';
}

// エリアの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_area'])) {
    $name = trim($_POST['area_name'] ?? '');
    if ($name) {
        $exists = false;
        foreach ($data['areas'] ?? [] as $a) {
            if ($a['name'] === $name) { $exists = true; break; }
        }
        if (!$exists) {
            if (!isset($data['areas'])) $data['areas'] = [];
            $data['areas'][] = [
                'id' => 'area_' . uniqid(),
                'name' => $name,
                'created_at' => date('Y-m-d H:i:s')
            ];
            encryptCustomerData($data);
    saveData($data);
            $message = 'エリアを追加しました';
            $messageType = 'success';
        } else {
            $message = 'このエリア名は既に登録されています';
            $messageType = 'danger';
        }
        $activeTab = 'areas';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_area'])) {
    if (!canDelete()) {
        $message = '削除権限がありません';
        $messageType = 'danger';
    } else {
        $id = $_POST['area_id'] ?? '';
        $data['areas'] = array_values(array_filter($data['areas'] ?? [], fn($a) => $a['id'] !== $id));
        encryptCustomerData($data);
    saveData($data);
        $message = 'エリアを削除しました';
        $messageType = 'success';
    }
    $activeTab = 'areas';
}

// 47都道府県の初期化
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['init_prefectures'])) {
    $allPrefectures = [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
        '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
        '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
        '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
    ];

    if (!isset($data['prefectures'])) $data['prefectures'] = [];
    $existingNames = array_column($data['prefectures'], 'name');
    $added = 0;

    foreach ($allPrefectures as $pref) {
        if (!in_array($pref, $existingNames)) {
            $data['prefectures'][] = [
                'id' => 'pref_' . uniqid(),
                'name' => $pref,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $added++;
        }
    }

    encryptCustomerData($data);
    saveData($data);
    $message = $added > 0 ? "{$added}件の都道府県を追加しました" : '既に全ての都道府県が登録されています';
    $messageType = 'success';
    $activeTab = 'prefectures';
}

// 各マスタデータを取得・ソート
$troubleResponders = $data['troubleResponders'] ?? [];
usort($troubleResponders, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

$prefectures = $data['prefectures'] ?? [];
usort($prefectures, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

$generalContractors = $data['generalContractors'] ?? [];
usort($generalContractors, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

$areas = $data['areas'] ?? [];
usort($areas, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

// マスタ一覧の定義
$masterTypes = [
    'customers' => ['name' => '顧客', 'count' => count($customers), 'icon' => '<rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/>'],
    'assignees' => ['name' => '営業担当者', 'count' => count($assignees), 'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    'partners' => ['name' => 'パートナー', 'count' => count($partners), 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    'manufacturers' => ['name' => 'メーカー', 'count' => count($manufacturers), 'icon' => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>'],
    'categories' => ['name' => '製品名', 'count' => count($categories), 'icon' => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>'],
    'trouble_responders' => ['name' => 'トラブル担当者', 'count' => count($troubleResponders), 'icon' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
];
?>

<div class="page-container">
<div class="page-header">
    <h2>マスタ管理</h2>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> mb-2">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if (empty($activeTab) || !isset($masterTypes[$activeTab])): ?>
<!-- マスタ選択画面 -->
<div class="master-select-grid">
    <?php foreach ($masterTypes as $key => $master): ?>
    <?php
    // 顧客はcustomers.phpに直接リンク
    $cardLink = ($key === 'customers') ? 'customers.php' : '?tab=' . $key;
    ?>
    <a href="<?= $cardLink ?>" class="master-select-card">
        <div class="master-select-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $master['icon'] ?></svg>
        </div>
        <div class="master-select-info">
            <div class="master-select-name"><?= htmlspecialchars($master['name']) ?></div>
            <div class="master-select-count"><?= $master['count'] ?>件</div>
        </div>
        <svg class="master-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- マスタ詳細画面 -->
<div class="master-detail-header">
    <h2  class="m-0 d-flex align-center gap-1">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="w-24 h-24"><?= $masterTypes[$activeTab]['icon'] ?></svg>
        <?= htmlspecialchars($masterTypes[$activeTab]['name']) ?>
        <span class="count-badge"><?= $masterTypes[$activeTab]['count'] ?></span>
    </h2>
    <a href="masters.php" class="btn btn-secondary btn-sm">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        一覧に戻る
    </a>
</div>

<div class="card">
<?php if (in_array($activeTab, ['customers', 'assignees', 'partners', 'categories', 'manufacturers'])): ?>
    <!-- 顧客タブの内容（既存のコードを維持）-->
    <div   class="tabs d-none">
        <button class="tab active" data-tab="customers">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            カテゴリ <span class="count-badge"><?= count($categories) ?></span>
        </button>
    </div>

    <!-- 顧客タブ -->
    <div id="tab-customers" class="tab-content <?= $activeTab === 'customers' ? 'active' : '' ?>">
        <div class="search-filter-bar">
            <div class="search-box">
                <input type="text" id="customerSearch" placeholder="顧客名で検索...">
            </div>
            <div  class="d-flex gap-1">
                <a href="customers.php"  title="MF請求書から顧客を同期できます"        class="btn btn-secondary text-924 bg-warning-light border-warning">
                    📥 MFから取得
                </a>
                <?php if (canEdit()): ?>
                <button class="btn btn-primary" data-modal="addCustomerModal">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M12 5v14M5 12h14"/></svg>
                    顧客追加
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($customers)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/></svg>
                <p>顧客が登録されていません</p>
                <p    class="mt-1 text-14">「MFから取得」でマネーフォワード請求書から顧客を同期できます</p>
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
                    <div class="customer-group-header" data-group-id="<?= $groupId ?>">
                        <div class="customer-group-left">
                            <div class="master-list-icon customer">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/></svg>
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
                                <?php if (canEdit()): ?>
                                <button class="btn-icon edit-customer-btn" data-customer='<?= json_encode($customer, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>' title="編集">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <?php endif; ?>
                                <?php if (canDelete()): ?>
                                <button class="btn-icon danger delete-customer-btn" data-id="<?= $customer['id'] ?>" data-name="<?= htmlspecialchars($customer['companyName'], ENT_QUOTES) ?>" title="削除">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                                <?php endif; ?>
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
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/></svg>
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
                            <?php if (canEdit()): ?>
                            <button class="btn-icon edit-customer-btn" data-customer='<?= json_encode($firstCustomer, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>' title="編集">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <?php endif; ?>
                            <?php if (canDelete()): ?>
                            <button class="btn-icon danger delete-customer-btn" data-id="<?= $firstCustomer['id'] ?>" data-name="<?= htmlspecialchars($firstCustomer['companyName'], ENT_QUOTES) ?>" title="削除">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                            <?php endif; ?>
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
                <input type="text" id="assigneeSearch" placeholder="担当者名で検索...">
            </div>
            <?php if (canEdit()): ?>
            <button class="btn btn-primary" data-modal="addAssigneeModal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M12 5v14M5 12h14"/></svg>
                担当者追加
            </button>
            <?php endif; ?>
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
                        <?php if (canEdit()): ?>
                        <button class="btn-icon edit-assignee-btn" data-assignee='<?= json_encode($assignee, JSON_HEX_APOS | JSON_HEX_QUOT) ?>' title="編集">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <?php endif; ?>
                        <?php if (canDelete()): ?>
                        <button class="btn-icon danger delete-assignee-btn" data-id="<?= $assignee['id'] ?>" data-name="<?= htmlspecialchars($assignee['name'], ENT_QUOTES) ?>" title="削除">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                        <?php endif; ?>
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
                <input type="text" id="partnerSearch" placeholder="パートナー名で検索...">
            </div>
            <?php if (canEdit()): ?>
            <button class="btn btn-primary" data-modal="addPartnerModal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M12 5v14M5 12h14"/></svg>
                パートナー追加
            </button>
            <?php endif; ?>
        </div>

        <?php if (empty($partners)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <p>パートナーが登録されていません</p>
                <p    class="mt-1 text-14">設置業者・撤去業者などを登録してください</p>
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
                        <?php if (canEdit()): ?>
                        <button class="btn-icon edit-partner-btn" data-partner='<?= json_encode($partner, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>' title="編集">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <?php endif; ?>
                        <?php if (canDelete()): ?>
                        <button class="btn-icon danger delete-partner-btn" data-id="<?= $partner['id'] ?>" data-name="<?= htmlspecialchars($partner['companyName'], ENT_QUOTES) ?>" title="削除">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 製品名タブ -->
    <div id="tab-categories" class="tab-content <?= $activeTab === 'categories' ? 'active' : '' ?>">
        <div class="search-filter-bar">
            <div class="search-box">
                <input type="text" id="categorySearch" placeholder="製品名で検索...">
            </div>
            <?php if (canEdit()): ?>
            <button class="btn btn-primary" data-modal="addCategoryModal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M12 5v14M5 12h14"/></svg>
                製品名追加
            </button>
            <?php endif; ?>
        </div>

        <?php if (empty($categories)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                <p>製品名が登録されていません</p>
                <p    class="mt-1 text-14">案件登録時の製品名として使用されます</p>
            </div>
        <?php else: ?>
            <?php
            // メーカーをid=>nameのマップに変換
            $manufacturerMap = [];
            foreach (filterDeleted($data['manufacturers'] ?? []) as $m) {
                $manufacturerMap[$m['id']] = $m['name'];
            }
            ?>
            <div class="master-list" id="categoriesTable">
                <div         class="master-list-item master-list-header grid-cols-1-1-80">
                    <div class="master-list-name">製品名</div>
                    <div class="master-list-contact">紐づきメーカー</div>
                    <div class="master-list-actions"></div>
                </div>
                <?php foreach ($categories as $category): ?>
                <div class="master-list-item grid-cols-1-1-80" data-name="<?= htmlspecialchars(strtolower($category['name'] ?? '')) ?>">
                    <div class="master-list-name">
                        <div class="master-list-icon category">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                        </div>
                        <span><?= htmlspecialchars($category['name'] ?? '') ?></span>
                    </div>
                    <div class="master-list-contact"><?php
                        $makerIds = $category['maker_ids'] ?? (isset($category['maker_id']) && $category['maker_id'] ? [$category['maker_id']] : []);
                        $makerNames = array_filter(array_map(fn($id) => $manufacturerMap[$id] ?? null, $makerIds));
                        echo htmlspecialchars($makerNames ? implode('、', $makerNames) : '-');
                        if (!empty($category['tag_name'])):
                    ?><br><span style="font-size:0.72rem;color:var(--gray-400);">タグ: <?= htmlspecialchars($category['tag_name']) ?></span><?php endif; ?></div>
                    <div class="master-list-actions">
                        <?php if (canEdit()): ?>
                        <button class="btn-icon edit-category-btn" data-category='<?= json_encode($category, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>' title="編集">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <?php endif; ?>
                        <?php if (canDelete()): ?>
                        <button class="btn-icon danger delete-category-btn" data-id="<?= htmlspecialchars($category['id'] ?? $category['name'], ENT_QUOTES) ?>" data-name="<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>" title="削除">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- メーカータブ -->
    <div id="tab-manufacturers" class="tab-content <?= $activeTab === 'manufacturers' ? 'active' : '' ?>">
        <div class="search-filter-bar">
            <div class="search-box">
                <input type="text" id="manufacturerSearch" placeholder="メーカー名で検索...">
            </div>
            <?php if (canEdit()): ?>
            <button class="btn btn-primary" data-modal="addManufacturerModal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M12 5v14M5 12h14"/></svg>
                メーカー追加
            </button>
            <?php endif; ?>
        </div>

        <?php if (empty($manufacturers)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                <p>メーカーが登録されていません</p>
                <p    class="mt-1 text-14">LED・LCDなどのメーカーを登録してください</p>
            </div>
        <?php else: ?>
            <div class="master-list" id="manufacturersTable">
                <div class="master-list-item master-list-header">
                    <div class="master-list-name">メーカー名</div>
                    <div class="master-list-contact">担当者</div>
                    <div class="master-list-email">メールアドレス</div>
                    <div class="master-list-actions"></div>
                </div>
                <?php foreach ($manufacturers as $manufacturer): ?>
                <div class="master-list-item" data-name="<?= htmlspecialchars(strtolower($manufacturer['name'] ?? '')) ?>">
                    <div class="master-list-name">
                        <div class="master-list-icon manufacturer">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        </div>
                        <span><?= htmlspecialchars($manufacturer['name'] ?? '') ?></span>
                    </div>
                    <div class="master-list-contact"><?= htmlspecialchars($manufacturer['contact'] ?? '-') ?></div>
                    <div class="master-list-email"><?= htmlspecialchars($manufacturer['email'] ?? '-') ?></div>
                    <div class="master-list-actions">
                        <?php if (canEdit()): ?>
                        <button class="btn-icon edit-manufacturer-btn" data-manufacturer='<?= json_encode($manufacturer, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>' title="編集">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <?php endif; ?>
                        <?php if (canDelete()): ?>
                        <button class="btn-icon danger delete-manufacturer-btn" data-id="<?= htmlspecialchars($manufacturer['id'], ENT_QUOTES) ?>" data-name="<?= htmlspecialchars($manufacturer['name'], ENT_QUOTES) ?>" title="削除">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="manufacturersPagination" class="pagination-container"></div>
        <?php endif; ?>
    </div>

<?php elseif ($activeTab === 'trouble_responders'): ?>
    <!-- トラブル担当者 -->
    <div class="search-filter-bar">
        <div class="search-box">
            <input type="text" id="troubleResponderSearch" placeholder="担当者名で検索...">
        </div>
        <?php if (canEdit()): ?>
        <button class="btn btn-primary" data-modal="addTroubleResponderModal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M12 5v14M5 12h14"/></svg>
            担当者追加
        </button>
        <?php endif; ?>
    </div>
    <?php if (empty($troubleResponders)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <p>トラブル担当者が登録されていません</p>
        </div>
    <?php else: ?>
        <div class="simple-master-list" id="troubleRespondersTable">
            <?php foreach ($troubleResponders as $r): ?>
            <div class="simple-master-item" data-name="<?= htmlspecialchars(strtolower($r['name'])) ?>">
                <span class="simple-master-name"><?= htmlspecialchars($r['name']) ?></span>
                <?php if (canDelete()): ?>
                <form method="POST"  class="d-inline delete-form">
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="responder_id" value="<?= $r['id'] ?>">
                    <button type="submit" name="delete_trouble_responder" class="btn-icon danger" title="削除">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($activeTab === 'prefectures'): ?>
    <!-- 都道府県 -->
    <div class="search-filter-bar">
        <div class="search-box">
            <input type="text" id="prefectureSearch" placeholder="都道府県名で検索...">
        </div>
        <?php if (canEdit()): ?>
        <button class="btn btn-primary" data-modal="addPrefectureModal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M12 5v14M5 12h14"/></svg>
            追加
        </button>
        <button class="btn btn-secondary" id="initPrefecturesBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
            47都道府県を初期化
        </button>
        <?php endif; ?>
    </div>
    <?php if (empty($prefectures)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <p>都道府県が登録されていません</p>
            <p    class="mt-1 text-14">「47都道府県を初期化」ボタンで一括登録できます</p>
        </div>
    <?php else: ?>
        <div  id="prefecturesTable"        class="simple-master-list grid grid-cols-auto-150 gap-0">
            <?php foreach ($prefectures as $p): ?>
            <div class="simple-master-item border-right-gray-100" data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>">
                <span class="simple-master-name"><?= htmlspecialchars($p['name']) ?></span>
                <?php if (canDelete()): ?>
                <form method="POST"  class="d-inline delete-form">
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="prefecture_id" value="<?= $p['id'] ?>">
                    <button type="submit" name="delete_prefecture"  title="削除"        class="btn-icon danger btn-pad-025">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($activeTab === 'general_contractors'): ?>
    <!-- ゼネコン -->
    <div class="search-filter-bar">
        <div class="search-box">
            <input type="text" id="contractorSearch" placeholder="ゼネコン名で検索...">
        </div>
        <?php if (canEdit()): ?>
        <button class="btn btn-primary" data-modal="addContractorModal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M12 5v14M5 12h14"/></svg>
            ゼネコン追加
        </button>
        <?php endif; ?>
    </div>
    <?php if (empty($generalContractors)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="6" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            <p>ゼネコンが登録されていません</p>
        </div>
    <?php else: ?>
        <div class="simple-master-list" id="contractorsTable">
            <?php foreach ($generalContractors as $g): ?>
            <div class="simple-master-item" data-name="<?= htmlspecialchars(strtolower($g['name'])) ?>">
                <span class="simple-master-name"><?= htmlspecialchars($g['name']) ?></span>
                <?php if (canDelete()): ?>
                <form method="POST"  class="d-inline delete-form">
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="contractor_id" value="<?= $g['id'] ?>">
                    <button type="submit" name="delete_general_contractor" class="btn-icon danger" title="削除">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($activeTab === 'areas'): ?>
    <!-- エリア -->
    <div class="search-filter-bar">
        <div class="search-box">
            <input type="text" id="areaSearch" placeholder="エリア名で検索...">
        </div>
        <?php if (canEdit()): ?>
        <button class="btn btn-primary" data-modal="addAreaModal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"   class="mr-05"><path d="M12 5v14M5 12h14"/></svg>
            エリア追加
        </button>
        <?php endif; ?>
    </div>
    <?php if (empty($areas)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            <p>エリアが登録されていません</p>
        </div>
    <?php else: ?>
        <div class="simple-master-list" id="areasTable">
            <?php foreach ($areas as $a): ?>
            <div class="simple-master-item" data-name="<?= htmlspecialchars(strtolower($a['name'])) ?>">
                <span class="simple-master-name"><?= htmlspecialchars($a['name']) ?></span>
                <?php if (canDelete()): ?>
                <form method="POST"  class="d-inline delete-form">
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="area_id" value="<?= $a['id'] ?>">
                    <button type="submit" name="delete_area" class="btn-icon danger" title="削除">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>
</div>
<?php endif; ?>

<!-- 顧客追加モーダル -->
<div id="addCustomerModal" class="modal">
    <div     class="modal-content max-w-500">
        <div class="modal-header">
            <h3>顧客追加</h3>
            <button type="button" class="close" data-close-modal="addCustomerModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>会社名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="company_name" required>
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="contact_person">
                </div>
                <div    class="gap-2 grid grid-cols-2">
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
                <button type="button" class="btn btn-secondary" data-close-modal="addCustomerModal">キャンセル</button>
                <button type="submit" name="add_customer" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- 顧客編集モーダル -->
<div id="editCustomerModal" class="modal">
    <div     class="modal-content max-w-500">
        <div class="modal-header">
            <h3>顧客編集</h3>
            <button type="button" class="close" data-close-modal="editCustomerModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="customer_id" id="edit_customer_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>会社名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="company_name" id="edit_company_name" required>
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="contact_person" id="edit_contact_person">
                </div>
                <div    class="gap-2 grid grid-cols-2">
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
                <div  id="edit_aliases_group"  class="form-group d-none">
                    <label>別名（支店・営業所等）</label>
                    <div id="edit_aliases_list"       class="text-14 text-gray-600 p-075 border-gray bg-f8fafc rounded"></div>
                    <p    class="text-xs mt-05 text-gray-500">※ 別名は自動で紐付けられます</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="editCustomerModal">キャンセル</button>
                <button type="submit" name="update_customer" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 顧客削除フォーム -->
<form id="deleteCustomerForm" method="POST"  class="d-none">
    <?= csrfTokenField() ?>
    <input type="hidden" name="customer_id" id="delete_customer_id">
    <input type="hidden" name="delete_customer" value="1">
</form>

<!-- 担当者追加モーダル -->
<div id="addAssigneeModal" class="modal">
    <div     class="modal-content max-w-500">
        <div class="modal-header">
            <h3>担当者追加</h3>
            <button type="button" class="close" data-close-modal="addAssigneeModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>担当者名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="assignee_name" required>
                </div>
                <div    class="gap-2 grid grid-cols-2">
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
                <button type="button" class="btn btn-secondary" data-close-modal="addAssigneeModal">キャンセル</button>
                <button type="submit" name="add_assignee" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- 担当者編集モーダル -->
<div id="editAssigneeModal" class="modal">
    <div     class="modal-content max-w-500">
        <div class="modal-header">
            <h3>担当者編集</h3>
            <button type="button" class="close" data-close-modal="editAssigneeModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="assignee_id" id="edit_assignee_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>担当者名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="assignee_name" id="edit_assignee_name" required>
                </div>
                <div    class="gap-2 grid grid-cols-2">
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
                <button type="button" class="btn btn-secondary" data-close-modal="editAssigneeModal">キャンセル</button>
                <button type="submit" name="update_assignee" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 担当者削除フォーム -->
<form id="deleteAssigneeForm" method="POST"  class="d-none">
    <?= csrfTokenField() ?>
    <input type="hidden" name="assignee_id" id="delete_assignee_id">
    <input type="hidden" name="delete_assignee" value="1">
</form>

<!-- パートナー追加モーダル -->
<div id="addPartnerModal" class="modal">
    <div     class="modal-content max-w-500">
        <div class="modal-header">
            <h3>パートナー追加</h3>
            <button type="button" class="close" data-close-modal="addPartnerModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>会社名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="partner_company_name" required>
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="partner_contact_person">
                </div>
                <div    class="gap-2 grid grid-cols-2">
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
                <button type="button" class="btn btn-secondary" data-close-modal="addPartnerModal">キャンセル</button>
                <button type="submit" name="add_partner" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- パートナー編集モーダル -->
<div id="editPartnerModal" class="modal">
    <div     class="modal-content max-w-500">
        <div class="modal-header">
            <h3>パートナー編集</h3>
            <button type="button" class="close" data-close-modal="editPartnerModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="partner_id" id="edit_partner_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>会社名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="partner_company_name" id="edit_partner_company_name" required>
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="partner_contact_person" id="edit_partner_contact_person">
                </div>
                <div    class="gap-2 grid grid-cols-2">
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
                <button type="button" class="btn btn-secondary" data-close-modal="editPartnerModal">キャンセル</button>
                <button type="submit" name="update_partner" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- パートナー削除フォーム -->
<form id="deletePartnerForm" method="POST"  class="d-none">
    <?= csrfTokenField() ?>
    <input type="hidden" name="partner_id" id="delete_partner_id">
    <input type="hidden" name="delete_partner" value="1">
</form>

<!-- 製品名追加モーダル -->
<div id="addCategoryModal" class="modal">
    <div     class="modal-content max-w-500">
        <div class="modal-header">
            <h3>製品名追加</h3>
            <button type="button" class="close" data-close-modal="addCategoryModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>製品名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="category_name" required placeholder="例：モニたろう、ゲンバルジャーなど">
                </div>
                <div class="form-group">
                    <label>請求書タグ名
                        <span style="font-size:0.72rem;color:var(--gray-400);font-weight:400;">（PJ未紐付き請求書の識別用）</span>
                    </label>
                    <input type="text" class="form-input" name="category_tag_name" placeholder="例：PICLES、ゲンバルジャー">
                </div>
                <div class="form-group">
                    <label>紐づきメーカー</label>
                    <div style="border:1px solid var(--gray-200);border-radius:6px;padding:8px;max-height:160px;overflow-y:auto;">
                        <?php foreach (filterDeleted($data['manufacturers'] ?? []) as $m): ?>
                        <label style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer;">
                            <input type="checkbox" name="category_maker_ids[]" value="<?= htmlspecialchars($m['id']) ?>">
                            <?= htmlspecialchars($m['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="addCategoryModal">キャンセル</button>
                <button type="submit" name="add_category" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- 製品名編集モーダル -->
<div id="editCategoryModal" class="modal">
    <div     class="modal-content max-w-500">
        <div class="modal-header">
            <h3>製品名編集</h3>
            <button type="button" class="close" data-close-modal="editCategoryModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="category_id" id="edit_category_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>製品名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="category_name" id="edit_category_name" required>
                </div>
                <div class="form-group">
                    <label>請求書タグ名
                        <span style="font-size:0.72rem;color:var(--gray-400);font-weight:400;">（PJ未紐付き請求書の識別用）</span>
                    </label>
                    <input type="text" class="form-input" name="category_tag_name" id="edit_category_tag_name" placeholder="例：PICLES、ゲンバルジャー">
                </div>
                <div class="form-group">
                    <label>紐づきメーカー</label>
                    <div id="edit_category_maker_ids" style="border:1px solid var(--gray-200);border-radius:6px;padding:8px;max-height:160px;overflow-y:auto;">
                        <?php foreach (filterDeleted($data['manufacturers'] ?? []) as $m): ?>
                        <label style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer;">
                            <input type="checkbox" name="category_maker_ids[]" value="<?= htmlspecialchars($m['id']) ?>">
                            <?= htmlspecialchars($m['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="editCategoryModal">キャンセル</button>
                <button type="submit" name="update_category" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 商品カテゴリ削除フォーム -->
<form id="deleteCategoryForm" method="POST"  class="d-none">
    <?= csrfTokenField() ?>
    <input type="hidden" name="category_id" id="delete_category_id">
    <input type="hidden" name="delete_category" value="1">
</form>

<!-- メーカー追加モーダル -->
<div id="addManufacturerModal" class="modal">
    <div     class="modal-content max-w-500">
        <div class="modal-header">
            <h3>メーカー追加</h3>
            <button type="button" class="close" data-close-modal="addManufacturerModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>メーカー名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="manufacturer_name" required placeholder="例：Samsung、Philips">
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="manufacturer_contact">
                </div>
                <div    class="gap-2 grid grid-cols-2">
                    <div class="form-group">
                        <label>電話番号</label>
                        <input type="tel" class="form-input" name="manufacturer_phone">
                    </div>
                    <div class="form-group">
                        <label>メール</label>
                        <input type="email" class="form-input" name="manufacturer_email">
                    </div>
                </div>
                <div class="form-group">
                    <label>備考</label>
                    <textarea class="form-input" name="manufacturer_notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal='addManufacturerModal'>キャンセル</button>
                <button type="submit" name="add_manufacturer" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- メーカー編集モーダル -->
<div id="editManufacturerModal" class="modal">
    <div     class="modal-content max-w-500">
        <div class="modal-header">
            <h3>メーカー編集</h3>
            <button type="button" class="close" data-close-modal="editManufacturerModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <input type="hidden" name="manufacturer_id" id="edit_manufacturer_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>メーカー名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="manufacturer_name" id="edit_manufacturer_name" required>
                </div>
                <div class="form-group">
                    <label>担当者名</label>
                    <input type="text" class="form-input" name="manufacturer_contact" id="edit_manufacturer_contact">
                </div>
                <div    class="gap-2 grid grid-cols-2">
                    <div class="form-group">
                        <label>電話番号</label>
                        <input type="tel" class="form-input" name="manufacturer_phone" id="edit_manufacturer_phone">
                    </div>
                    <div class="form-group">
                        <label>メール</label>
                        <input type="email" class="form-input" name="manufacturer_email" id="edit_manufacturer_email">
                    </div>
                </div>
                <div class="form-group">
                    <label>備考</label>
                    <textarea class="form-input" name="manufacturer_notes" id="edit_manufacturer_notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal='editManufacturerModal'>キャンセル</button>
                <button type="submit" name="update_manufacturer" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- メーカー削除フォーム -->
<form id="deleteManufacturerForm" method="POST"  class="d-none">
    <?= csrfTokenField() ?>
    <input type="hidden" name="manufacturer_id" id="delete_manufacturer_id">
    <input type="hidden" name="delete_manufacturer" value="1">
</form>

<!-- トラブル担当者追加モーダル -->
<div id="addTroubleResponderModal" class="modal">
    <div       class="modal-content max-w-400">
        <div class="modal-header">
            <h3>トラブル担当者追加</h3>
            <button type="button" class="close" data-close-modal="addTroubleResponderModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>担当者名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="responder_name" required placeholder="例：田中太郎">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="addTroubleResponderModal">キャンセル</button>
                <button type="submit" name="add_trouble_responder" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- 都道府県追加モーダル -->
<div id="addPrefectureModal" class="modal">
    <div       class="modal-content max-w-400">
        <div class="modal-header">
            <h3>都道府県追加</h3>
            <button type="button" class="close" data-close-modal="addPrefectureModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>都道府県名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="prefecture_name" required placeholder="例：東京都">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="addPrefectureModal">キャンセル</button>
                <button type="submit" name="add_prefecture" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- ゼネコン追加モーダル -->
<div id="addContractorModal" class="modal">
    <div       class="modal-content max-w-400">
        <div class="modal-header">
            <h3>ゼネコン追加</h3>
            <button type="button" class="close" data-close-modal="addContractorModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>ゼネコン名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="contractor_name" required placeholder="例：大林組">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="addContractorModal">キャンセル</button>
                <button type="submit" name="add_general_contractor" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- エリア追加モーダル -->
<div id="addAreaModal" class="modal">
    <div       class="modal-content max-w-400">
        <div class="modal-header">
            <h3>エリア追加</h3>
            <button type="button" class="close" data-close-modal="addAreaModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrfTokenField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>エリア名 <span   class="text-red">*</span></label>
                    <input type="text" class="form-input" name="area_name" required placeholder="例：関東エリア">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="addAreaModal">キャンセル</button>
                <button type="submit" name="add_area" class="btn btn-primary">追加</button>
            </div>
        </form>
    </div>
</div>

<!-- 都道府県初期化フォーム -->
<form id="initPrefecturesForm" method="POST"  class="d-none">
    <?= csrfTokenField() ?>
    <input type="hidden" name="init_prefectures" value="1">
</form>

<script<?= nonceAttr() ?>>
function switchTab(tabName) {
    // タブボタン
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    const activeTab = document.querySelector('.tab[data-tab="' + tabName + '"]');
    if (activeTab) {
        activeTab.classList.add('active');
    }

    // タブコンテンツ
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');

    // URL更新
    history.replaceState(null, '', '?tab=' + tabName);
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
        aliasesList.innerHTML = aliases.map(a => '<div     class="py-025 border-bottom-dashed-gray">' + escapeHtml(a) + '</div>').join('');
        aliasesGroup.style.display = 'block';
    } else {
        aliasesGroup.style.display = 'none';
    }

    openModal('editCustomerModal');
}

// escapeHtml は js/common-utils.js で定義済み

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
    document.getElementById('edit_category_id').value = category.id || category.name;
    document.getElementById('edit_category_name').value = category.name || '';
    document.getElementById('edit_category_tag_name').value = category.tag_name || '';
    // チェックボックスの状態をセット（後方互換: maker_idが文字列の場合も対応）
    const makerIds = category.maker_ids || (category.maker_id ? [category.maker_id] : []);
    document.querySelectorAll('#edit_category_maker_ids input[type="checkbox"]').forEach(cb => {
        cb.checked = makerIds.includes(cb.value);
    });
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
    if (window._masterPaginators && window._masterPaginators['categories']) {
        window._masterPaginators['categories'].currentPage = 1;
        window._masterPaginators['categories'].refresh();
    }
}

// メーカー編集
function editManufacturer(manufacturer) {
    document.getElementById('edit_manufacturer_id').value = manufacturer.id || '';
    document.getElementById('edit_manufacturer_name').value = manufacturer.name || '';
    document.getElementById('edit_manufacturer_contact').value = manufacturer.contact || '';
    document.getElementById('edit_manufacturer_phone').value = manufacturer.phone || '';
    document.getElementById('edit_manufacturer_email').value = manufacturer.email || '';
    document.getElementById('edit_manufacturer_notes').value = manufacturer.notes || '';
    openModal('editManufacturerModal');
}

// メーカー削除
function deleteManufacturer(id, name) {
    if (confirm('「' + name + '」を削除しますか？\n\n※ 案件で使用中の場合、参照が残る可能性があります')) {
        document.getElementById('delete_manufacturer_id').value = id;
        document.getElementById('deleteManufacturerForm').submit();
    }
}

// メーカー検索
function filterManufacturers() {
    const query = document.getElementById('manufacturerSearch').value.toLowerCase();
    const items = document.querySelectorAll('#manufacturersTable .master-list-item:not(.master-list-header)');
    items.forEach(item => {
        const name = item.dataset.name || '';
        item.style.display = name.includes(query) ? '' : 'none';
    });
    if (window._masterPaginators && window._masterPaginators['manufacturers']) {
        window._masterPaginators['manufacturers'].currentPage = 1;
        window._masterPaginators['manufacturers'].refresh();
    }
}

// シンプルリスト検索
function filterSimpleList(inputId, tableId) {
    const query = document.getElementById(inputId).value.toLowerCase();
    const items = document.querySelectorAll('#' + tableId + ' .simple-master-item');
    items.forEach(item => {
        const name = item.dataset.name || '';
        item.style.display = name.includes(query) ? '' : 'none';
    });
    var keyMap = {
        'troubleRespondersTable': 'trouble_responders',
        'prefecturesTable': 'prefectures',
        'contractorsTable': 'general_contractors',
        'areasTable': 'areas'
    };
    var key = keyMap[tableId];
    if (key && window._masterPaginators && window._masterPaginators[key]) {
        window._masterPaginators[key].currentPage = 1;
        window._masterPaginators[key].refresh();
    }
}

// 47都道府県を初期化
function initPrefectures() {
    if (confirm('47都道府県を一括で登録しますか？\n既に登録されている都道府県は重複しません。')) {
        document.getElementById('initPrefecturesForm').submit();
    }
}

// 背景クリックでは閉じない（×ボタン・キャンセルのみで閉じる）

// ページネーション初期化
document.addEventListener('DOMContentLoaded', function() {
    var paginatorConfigs = {
        'customers': {
            container: '#customersTable',
            itemSelector: '.customer-group, .customer-single',
            paginationTarget: '#customersMasterPagination',
            perPage: 50
        },
        'assignees': {
            container: '#assigneesTable',
            itemSelector: '.master-list-item:not(.master-list-header)',
            paginationTarget: '#assigneesPagination',
            perPage: 50
        },
        'partners': {
            container: '#partnersTable',
            itemSelector: '.master-list-item:not(.master-list-header)',
            paginationTarget: '#partnersPagination',
            perPage: 50
        },
        'categories': {
            container: '#categoriesTable',
            itemSelector: '.master-list-item:not(.master-list-header)',
            paginationTarget: '#categoriesPagination',
            perPage: 50
        },
        'manufacturers': {
            container: '#manufacturersTable',
            itemSelector: '.master-list-item:not(.master-list-header)',
            paginationTarget: '#manufacturersPagination',
            perPage: 50
        },
        'trouble_responders': {
            container: '#troubleRespondersTable',
            itemSelector: '.simple-master-item',
            paginationTarget: '#troubleRespondersPagination',
            perPage: 50
        },
        'prefectures': {
            container: '#prefecturesTable',
            itemSelector: '.simple-master-item',
            paginationTarget: '#prefecturesPagination',
            perPage: 50
        },
        'general_contractors': {
            container: '#contractorsTable',
            itemSelector: '.simple-master-item',
            paginationTarget: '#contractorsPagination',
            perPage: 50
        },
        'areas': {
            container: '#areasTable',
            itemSelector: '.simple-master-item',
            paginationTarget: '#areasPagination',
            perPage: 50
        }
    };

    window._masterPaginators = {};
    Object.keys(paginatorConfigs).forEach(function(key) {
        var config = paginatorConfigs[key];
        var el = document.querySelector(config.container);
        if (el && el.querySelector(config.itemSelector)) {
            window._masterPaginators[key] = new Paginator(config);
        }
    });

    // イベントリスナー登録

    // タブ切り替え
    document.querySelectorAll('.tab[data-tab]').forEach(tab => {
        tab.addEventListener('click', function(e) {
            switchTab(this.dataset.tab);
        });
    });

    // モーダル開くボタン
    document.querySelectorAll('[data-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            openModal(this.dataset.modal);
        });
    });

    // モーダル閉じるボタン
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal(this.dataset.closeModal);
        });
    });

    // 顧客グループ展開/折りたたみ
    document.querySelectorAll('.customer-group-header[data-group-id]').forEach(header => {
        header.addEventListener('click', function() {
            toggleGroup(this.dataset.groupId);
        });
    });

    // 顧客編集ボタン
    document.querySelectorAll('.edit-customer-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const customer = JSON.parse(this.dataset.customer);
            editCustomer(customer);
        });
    });

    // 顧客削除ボタン
    document.querySelectorAll('.delete-customer-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            deleteCustomer(this.dataset.id, this.dataset.name);
        });
    });

    // 担当者編集ボタン
    document.querySelectorAll('.edit-assignee-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const assignee = JSON.parse(this.dataset.assignee);
            editAssignee(assignee);
        });
    });

    // 担当者削除ボタン
    document.querySelectorAll('.delete-assignee-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            deleteAssignee(this.dataset.id, this.dataset.name);
        });
    });

    // パートナー編集ボタン
    document.querySelectorAll('.edit-partner-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const partner = JSON.parse(this.dataset.partner);
            editPartner(partner);
        });
    });

    // パートナー削除ボタン
    document.querySelectorAll('.delete-partner-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            deletePartner(this.dataset.id, this.dataset.name);
        });
    });

    // カテゴリ編集ボタン
    document.querySelectorAll('.edit-category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const category = JSON.parse(this.dataset.category);
            editCategory(category);
        });
    });

    // カテゴリ削除ボタン
    document.querySelectorAll('.delete-category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            deleteCategory(this.dataset.id, this.dataset.name);
        });
    });

    // メーカー編集ボタン
    document.querySelectorAll('.edit-manufacturer-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const manufacturer = JSON.parse(this.dataset.manufacturer);
            editManufacturer(manufacturer);
        });
    });

    // メーカー削除ボタン
    document.querySelectorAll('.delete-manufacturer-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            deleteManufacturer(this.dataset.id, this.dataset.name);
        });
    });

    // 47都道府県初期化ボタン
    const initPrefBtn = document.getElementById('initPrefecturesBtn');
    if (initPrefBtn) {
        initPrefBtn.addEventListener('click', function() {
            initPrefectures();
        });
    }

    // 削除フォーム確認
    document.querySelectorAll('form.delete-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('削除しますか？')) {
                e.preventDefault();
            }
        });
    });

    // 検索フィルター
    const customerSearch = document.getElementById('customerSearch');
    if (customerSearch) {
        customerSearch.addEventListener('input', filterCustomers);
    }

    const assigneeSearch = document.getElementById('assigneeSearch');
    if (assigneeSearch) {
        assigneeSearch.addEventListener('input', filterAssignees);
    }

    const partnerSearch = document.getElementById('partnerSearch');
    if (partnerSearch) {
        partnerSearch.addEventListener('input', filterPartners);
    }

    const categorySearch = document.getElementById('categorySearch');
    if (categorySearch) {
        categorySearch.addEventListener('input', filterCategories);
    }

    const manufacturerSearch = document.getElementById('manufacturerSearch');
    if (manufacturerSearch) {
        manufacturerSearch.addEventListener('input', filterManufacturers);
    }

    const troubleResponderSearch = document.getElementById('troubleResponderSearch');
    if (troubleResponderSearch) {
        troubleResponderSearch.addEventListener('input', function() {
            filterSimpleList('troubleResponderSearch', 'troubleRespondersTable');
        });
    }

    const prefectureSearch = document.getElementById('prefectureSearch');
    if (prefectureSearch) {
        prefectureSearch.addEventListener('input', function() {
            filterSimpleList('prefectureSearch', 'prefecturesTable');
        });
    }

    const contractorSearch = document.getElementById('contractorSearch');
    if (contractorSearch) {
        contractorSearch.addEventListener('input', function() {
            filterSimpleList('contractorSearch', 'contractorsTable');
        });
    }

    const areaSearch = document.getElementById('areaSearch');
    if (areaSearch) {
        areaSearch.addEventListener('input', function() {
            filterSimpleList('areaSearch', 'areasTable');
        });
    }
});
</script>

</div><!-- /.page-container -->

<?php require_once '../functions/footer.php'; ?>
