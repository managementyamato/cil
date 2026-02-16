<?php
/**
 * 顧客データ受信API
 * POST: 顧客データを登録・更新
 * GET: 顧客データを取得
 */

require_once 'api-auth.php';
require_once __DIR__ . '/../../functions/encryption.php';

// CORS設定（許可されたオリジンのみ）
setIntegrationCorsHeaders();

// OPTIONSリクエスト（プリフライト）の処理
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 認証チェック
$auth = authenticateApiRequest();
if (!$auth['success']) {
    sendErrorResponse($auth['error'], $auth['code']);
}

$keyInfo = $auth['key_info'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetCustomers($keyInfo);
        break;
    case 'POST':
        handlePostCustomers($keyInfo);
        break;
    default:
        sendErrorResponse('許可されていないメソッドです', 405);
}

/**
 * 顧客データ取得
 */
function handleGetCustomers($keyInfo) {
    $data = getData();
    decryptCustomerData($data);
    $customers = $data['customers'] ?? array();

    // IDで絞り込み
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        foreach ($customers as $customer) {
            if ($customer['id'] === $id) {
                logApiRequest('get_customer', $keyInfo['name'], array('customer_id' => $id));
                sendSuccessResponse($customer, '顧客を取得しました');
            }
        }
        sendErrorResponse('顧客が見つかりません', 404);
    }

    // 外部IDで絞り込み
    if (isset($_GET['external_id'])) {
        $externalId = $_GET['external_id'];
        foreach ($customers as $customer) {
            if (isset($customer['external_id']) && $customer['external_id'] === $externalId) {
                logApiRequest('get_customer', $keyInfo['name'], array('external_id' => $externalId));
                sendSuccessResponse($customer, '顧客を取得しました');
            }
        }
        sendErrorResponse('顧客が見つかりません', 404);
    }

    // ページネーション（デフォルト100件、最大500件）
    $limit = min(500, max(1, intval($_GET['limit'] ?? 100)));
    $offset = max(0, intval($_GET['offset'] ?? 0));
    $total = count($customers);
    $paginatedCustomers = array_slice($customers, $offset, $limit);

    logApiRequest('get_customers', $keyInfo['name'], array('count' => count($paginatedCustomers), 'total' => $total));
    sendSuccessResponse(array(
        'customers' => $paginatedCustomers,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ), '顧客一覧を取得しました');
}

/**
 * 顧客データ登録・更新
 */
function handlePostCustomers($keyInfo) {
    // JSONデータを取得
    $input = file_get_contents('php://input');
    $requestData = json_decode($input, true);

    if (!$requestData) {
        sendErrorResponse('無効なJSONデータです', 400);
    }

    // 一括登録の場合
    if (isset($requestData['customers']) && is_array($requestData['customers'])) {
        $results = processMultipleCustomers($requestData['customers'], $keyInfo);
        logApiRequest('bulk_create_customers', $keyInfo['name'], array('count' => count($requestData['customers'])));
        sendSuccessResponse($results, '顧客データを一括処理しました');
    }

    // 単一登録の場合
    $result = processSingleCustomer($requestData, $keyInfo);
    sendSuccessResponse($result, $result['action'] === 'created' ? '顧客を登録しました' : '顧客を更新しました');
}

/**
 * 単一顧客を処理
 */
function processSingleCustomer($customerData, $keyInfo) {
    $data = getData();
    decryptCustomerData($data);

    // 必須フィールドチェック
    if (empty($customerData['name'])) {
        sendErrorResponse('顧客名は必須です', 400);
    }

    // 外部IDで既存チェック
    $existingIndex = null;
    if (!empty($customerData['external_id'])) {
        foreach ($data['customers'] as $index => $cust) {
            if (isset($cust['external_id']) && $cust['external_id'] === $customerData['external_id']) {
                $existingIndex = $index;
                break;
            }
        }
    }

    // 更新または新規作成
    if ($existingIndex !== null) {
        // 更新
        $existingCustomer = $data['customers'][$existingIndex];
        $updatedCustomer = array_merge($existingCustomer, array(
            'name' => $customerData['name'],
            'code' => $customerData['code'] ?? $existingCustomer['code'] ?? '',
            'contact_name' => $customerData['contact_name'] ?? $existingCustomer['contact_name'] ?? '',
            'phone' => $customerData['phone'] ?? $existingCustomer['phone'] ?? '',
            'email' => $customerData['email'] ?? $existingCustomer['email'] ?? '',
            'address' => $customerData['address'] ?? $existingCustomer['address'] ?? '',
            'memo' => $customerData['memo'] ?? $existingCustomer['memo'] ?? '',
            'external_id' => $customerData['external_id'],
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'API:' . $keyInfo['name']
        ));

        $data['customers'][$existingIndex] = $updatedCustomer;
        encryptCustomerData($data);
        saveData($data);

        logApiRequest('update_customer', $keyInfo['name'], array(
            'customer_id' => $existingCustomer['id'],
            'external_id' => $customerData['external_id']
        ));

        return array('action' => 'updated', 'id' => $existingCustomer['id'], 'external_id' => $customerData['external_id']);
    } else {
        // 新規作成
        $newId = 'cust_' . date('YmdHis') . '_' . substr(uniqid(), -4);
        $newCustomer = array(
            'id' => $newId,
            'name' => $customerData['name'],
            'code' => $customerData['code'] ?? '',
            'contact_name' => $customerData['contact_name'] ?? '',
            'phone' => $customerData['phone'] ?? '',
            'email' => $customerData['email'] ?? '',
            'address' => $customerData['address'] ?? '',
            'memo' => $customerData['memo'] ?? '',
            'external_id' => $customerData['external_id'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 'API:' . $keyInfo['name']
        );

        $data['customers'][] = $newCustomer;
        encryptCustomerData($data);
        saveData($data);

        logApiRequest('create_customer', $keyInfo['name'], array(
            'customer_id' => $newId,
            'external_id' => $customerData['external_id'] ?? ''
        ));

        return array('action' => 'created', 'id' => $newId, 'external_id' => $customerData['external_id'] ?? '');
    }
}

/**
 * 複数顧客を処理
 */
function processMultipleCustomers($customersData, $keyInfo) {
    $results = array(
        'created' => 0,
        'updated' => 0,
        'errors' => array()
    );

    foreach ($customersData as $index => $customerData) {
        try {
            if (empty($customerData['name'])) {
                $results['errors'][] = array('index' => $index, 'error' => '顧客名は必須です');
                continue;
            }
            $result = processSingleCustomerInternal($customerData, $keyInfo);
            if ($result['action'] === 'created') {
                $results['created']++;
            } else {
                $results['updated']++;
            }
        } catch (Exception $e) {
            $results['errors'][] = array('index' => $index, 'error' => $e->getMessage());
        }
    }

    return $results;
}

/**
 * 単一顧客を処理（内部用・エラーをthrow）
 */
function processSingleCustomerInternal($customerData, $keyInfo) {
    $data = getData();
    decryptCustomerData($data);

    // 外部IDで既存チェック
    $existingIndex = null;
    if (!empty($customerData['external_id'])) {
        foreach ($data['customers'] as $index => $cust) {
            if (isset($cust['external_id']) && $cust['external_id'] === $customerData['external_id']) {
                $existingIndex = $index;
                break;
            }
        }
    }

    if ($existingIndex !== null) {
        // 更新
        $existingCustomer = $data['customers'][$existingIndex];
        $updatedCustomer = array_merge($existingCustomer, array(
            'name' => $customerData['name'],
            'code' => $customerData['code'] ?? $existingCustomer['code'] ?? '',
            'contact_name' => $customerData['contact_name'] ?? $existingCustomer['contact_name'] ?? '',
            'phone' => $customerData['phone'] ?? $existingCustomer['phone'] ?? '',
            'email' => $customerData['email'] ?? $existingCustomer['email'] ?? '',
            'address' => $customerData['address'] ?? $existingCustomer['address'] ?? '',
            'memo' => $customerData['memo'] ?? $existingCustomer['memo'] ?? '',
            'external_id' => $customerData['external_id'],
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => 'API:' . $keyInfo['name']
        ));

        $data['customers'][$existingIndex] = $updatedCustomer;
        encryptCustomerData($data);
        saveData($data);

        return array('action' => 'updated', 'id' => $existingCustomer['id']);
    } else {
        // 新規作成
        $newId = 'cust_' . date('YmdHis') . '_' . substr(uniqid(), -4);
        $newCustomer = array(
            'id' => $newId,
            'name' => $customerData['name'],
            'code' => $customerData['code'] ?? '',
            'contact_name' => $customerData['contact_name'] ?? '',
            'phone' => $customerData['phone'] ?? '',
            'email' => $customerData['email'] ?? '',
            'address' => $customerData['address'] ?? '',
            'memo' => $customerData['memo'] ?? '',
            'external_id' => $customerData['external_id'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 'API:' . $keyInfo['name']
        );

        $data['customers'][] = $newCustomer;
        encryptCustomerData($data);
        saveData($data);

        return array('action' => 'created', 'id' => $newId);
    }
}
