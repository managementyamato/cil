<?php
/**
 * MFクラウド請求書から取引先マスタを同期するAPI
 * 部門は親会社の「営業所」として紐付けて登録
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/mf-api.php';
require_once __DIR__ . '/../functions/encryption.php';

header('Content-Type: application/json; charset=utf-8');

// 認証チェック
if (!isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です']);
    exit;
}

// 編集権限チェック
if (!canEdit()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '編集権限が必要です']);
    exit;
}

// CSRF検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

try {
    // MF APIが設定されているか確認
    if (!MFApiClient::isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'MFクラウド請求書APIが設定されていません。設定画面から認証してください。']);
        exit;
    }

    $client = new MFApiClient();

    // MFから取引先一覧を取得（部門情報付き）
    $partners = $client->getPartnersWithDepartments();

    // デバッグ: 取引先と部門情報をログに保存
    $debugFile = __DIR__ . '/../logs/partners-debug.json';
    $debugDir = dirname($debugFile);
    if (!is_dir($debugDir)) {
        mkdir($debugDir, 0755, true);
    }
    file_put_contents($debugFile, json_encode($partners, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    if (empty($partners)) {
        echo json_encode([
            'success' => true,
            'message' => 'MFに取引先が登録されていません',
            'synced' => 0,
            'new' => 0,
            'skip' => 0
        ]);
        exit;
    }

    // データ取得
    $data = getData();
    decryptCustomerData($data);
    if (!isset($data['customers'])) {
        $data['customers'] = [];
    }

    // 既存の会社名→インデックスのマップを作成
    $existingNameToIndex = [];
    foreach ($data['customers'] as $idx => $c) {
        $existingNameToIndex[$c['companyName'] ?? ''] = $idx;
    }

    $newCount = 0;
    $skipCount = 0;
    $updatedCount = 0;
    $branchCount = 0;

    // まず取引先を会社名でグループ化（person_deptを営業所として扱う）
    $partnersByCompany = [];
    foreach ($partners as $partner) {
        $partnerName = $partner['name'] ?? '';
        if (empty($partnerName)) {
            continue;
        }

        if (!isset($partnersByCompany[$partnerName])) {
            $partnersByCompany[$partnerName] = [
                'main' => null,
                'branches' => []
            ];
        }

        $personDept = $partner['person_dept'] ?? '';

        if (empty($personDept)) {
            // 部門がない場合は本社として扱う
            if ($partnersByCompany[$partnerName]['main'] === null) {
                $partnersByCompany[$partnerName]['main'] = $partner;
            }
        } else {
            // 部門がある場合は営業所として扱う
            $partnersByCompany[$partnerName]['branches'][] = [
                'name' => $personDept,
                'partner' => $partner
            ];
            // mainがまだない場合は最初の取引先をmainに
            if ($partnersByCompany[$partnerName]['main'] === null) {
                $partnersByCompany[$partnerName]['main'] = $partner;
            }
        }
    }

    foreach ($partnersByCompany as $partnerName => $group) {
        $mainPartner = $group['main'];
        $branchList = $group['branches'];

        if ($mainPartner === null && !empty($branchList)) {
            // mainがない場合は最初の営業所のpartnerを使う
            $mainPartner = $branchList[0]['partner'];
        }

        if ($mainPartner === null) {
            continue;
        }

        // 親会社が既に存在するかチェック
        $parentIndex = $existingNameToIndex[$partnerName] ?? null;

        if ($parentIndex === null) {
            // 親会社が存在しない場合は新規作成
            $address = '';
            if (!empty($mainPartner['prefecture'])) $address .= $mainPartner['prefecture'];
            if (!empty($mainPartner['address1'])) $address .= $mainPartner['address1'];
            if (!empty($mainPartner['address2'])) $address .= $mainPartner['address2'];

            $newCustomer = [
                'id' => 'c_' . uniqid(),
                'companyName' => $partnerName,
                'aliases' => [],
                'branches' => [],
                'contactPerson' => $mainPartner['person_name'] ?? '',
                'phone' => $mainPartner['tel'] ?? '',
                'email' => $mainPartner['email'] ?? '',
                'address' => $address,
                'zipcode' => $mainPartner['zip'] ?? '',
                'notes' => '',
                'mf_partner_id' => $mainPartner['id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'source' => 'mf_partners'
            ];

            $data['customers'][] = $newCustomer;
            $parentIndex = count($data['customers']) - 1;
            $existingNameToIndex[$partnerName] = $parentIndex;
            $newCount++;
        } else {
            // 既存の親会社を更新
            $existing = &$data['customers'][$parentIndex];

            // 空の項目のみ更新
            if (empty($existing['phone']) && !empty($mainPartner['tel'])) {
                $existing['phone'] = $mainPartner['tel'];
                $updatedCount++;
            }
            if (empty($existing['email']) && !empty($mainPartner['email'])) {
                $existing['email'] = $mainPartner['email'];
                $updatedCount++;
            }
            if (empty($existing['contactPerson']) && !empty($mainPartner['person_name'])) {
                $existing['contactPerson'] = $mainPartner['person_name'];
                $updatedCount++;
            }
            if (empty($existing['address'])) {
                $address = '';
                if (!empty($mainPartner['prefecture'])) $address .= $mainPartner['prefecture'];
                if (!empty($mainPartner['address1'])) $address .= $mainPartner['address1'];
                if (!empty($mainPartner['address2'])) $address .= $mainPartner['address2'];
                if (!empty($address)) {
                    $existing['address'] = $address;
                    $updatedCount++;
                }
            }

            $existing['mf_partner_id'] = $mainPartner['id'] ?? null;
            $existing['updated_at'] = date('Y-m-d H:i:s');

            // branchesフィールドがなければ初期化
            if (!isset($existing['branches'])) {
                $existing['branches'] = [];
            }

            $skipCount++;
            unset($existing);
        }

        // 営業所（person_dept）がある場合は親会社に紐付け
        if (!empty($branchList)) {
            $parentCustomer = &$data['customers'][$parentIndex];

            // branchesフィールドがなければ初期化
            if (!isset($parentCustomer['branches'])) {
                $parentCustomer['branches'] = [];
            }

            // 既存の営業所名リスト（名前→インデックス）
            $existingBranchNames = [];
            foreach ($parentCustomer['branches'] as $bIdx => $b) {
                $existingBranchNames[$b['name'] ?? ''] = $bIdx;
            }

            foreach ($branchList as $branchInfo) {
                $branchName = $branchInfo['name'];
                $branchPartner = $branchInfo['partner'];

                if (empty($branchName)) {
                    continue;
                }

                // 住所を構築
                $branchAddress = '';
                if (!empty($branchPartner['prefecture'])) $branchAddress .= $branchPartner['prefecture'];
                if (!empty($branchPartner['address1'])) $branchAddress .= $branchPartner['address1'];
                if (!empty($branchPartner['address2'])) $branchAddress .= $branchPartner['address2'];

                // 既存の営業所かチェック（名前で判定）
                if (isset($existingBranchNames[$branchName])) {
                    // 既存の営業所を更新
                    $bIdx = $existingBranchNames[$branchName];
                    $existingBranch = &$parentCustomer['branches'][$bIdx];

                    // 空の項目のみ更新
                    if (empty($existingBranch['contact']) && !empty($branchPartner['person_name'])) {
                        $existingBranch['contact'] = $branchPartner['person_name'];
                    }
                    if (empty($existingBranch['phone']) && !empty($branchPartner['tel'])) {
                        $existingBranch['phone'] = $branchPartner['tel'];
                    }
                    if (empty($existingBranch['address']) && !empty($branchAddress)) {
                        $existingBranch['address'] = $branchAddress;
                    }
                    $existingBranch['mf_partner_id'] = $branchPartner['id'] ?? null;
                    $existingBranch['updated_at'] = date('Y-m-d H:i:s');
                    unset($existingBranch);
                } else {
                    // 新規営業所として追加
                    $parentCustomer['branches'][] = [
                        'id' => 'br_' . uniqid(),
                        'name' => $branchName,
                        'contact' => $branchPartner['person_name'] ?? '',
                        'phone' => $branchPartner['tel'] ?? '',
                        'email' => $branchPartner['email'] ?? '',
                        'address' => $branchAddress,
                        'zipcode' => $branchPartner['zip'] ?? '',
                        'mf_partner_id' => $branchPartner['id'] ?? null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'source' => 'mf_partners'
                    ];
                    $existingBranchNames[$branchName] = count($parentCustomer['branches']) - 1;
                    $branchCount++;
                }
            }

            $parentCustomer['updated_at'] = date('Y-m-d H:i:s');
            unset($parentCustomer);
        }
    }

    // 同期時刻を記録
    $data['customers_sync_timestamp'] = date('Y-m-d H:i:s');
    $data['mf_partners_sync_timestamp'] = date('Y-m-d H:i:s');

    encryptCustomerData($data);
    saveData($data);

    $message = "取引先マスタを同期: 新規{$newCount}件";
    if ($branchCount > 0) {
        $message .= "、営業所{$branchCount}件";
    }
    if ($skipCount > 0) {
        $message .= "、既存{$skipCount}件";
    }
    if ($updatedCount > 0) {
        $message .= "（{$updatedCount}項目を補完）";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'synced' => count($partners),
        'new' => $newCount,
        'skip' => $skipCount,
        'updated' => $updatedCount,
        'branches' => $branchCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
