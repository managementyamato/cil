<?php
/**
 * ユーザー管理と従業員マスタの統合マイグレーション
 * users.jsonのユーザーデータを従業員データに統合します
 */

require_once __DIR__ . '/config.php';

echo "=== ユーザーと従業員の統合マイグレーション ===\n\n";

// データ読み込み
$data = getData();
$users = array();

if (file_exists(USERS_FILE)) {
    $json = file_get_contents(USERS_FILE);
    $users = json_decode($json, true);
}

if (empty($users)) {
    echo "users.jsonにデータがありません。\n";
    echo "従業員データのみ確認します...\n\n";
}

// 既存従業員にIDを付与
echo "1. 既存従業員にIDを付与...\n";
$maxId = 0;
foreach ($data['employees'] as $key => $employee) {
    if (!isset($employee['id'])) {
        $maxId++;
        $data['employees'][$key]['id'] = $maxId;
        echo "  - {$employee['name']} (ID: {$maxId})\n";
    } else {
        if ($employee['id'] > $maxId) {
            $maxId = $employee['id'];
        }
    }
}

if ($maxId == 0) {
    echo "  IDを付与する従業員がいませんでした\n";
}
echo "\n";

// users.jsonのユーザーを従業員データにマージ
if (!empty($users)) {
    echo "2. users.jsonのユーザーを従業員データに統合...\n";

    foreach ($users as $email => $user) {
        // 既存の従業員でメールアドレスまたは名前が一致するものを探す
        $found = false;
        foreach ($data['employees'] as $key => $employee) {
            if ($employee['email'] === $email || $employee['name'] === $user['name']) {
                echo "  - {$user['name']} ({$email}): 既存従業員に認証情報を追加\n";
                $data['employees'][$key]['email'] = $email;
                $data['employees'][$key]['username'] = $email;
                $data['employees'][$key]['password'] = $user['password'];
                $data['employees'][$key]['role'] = $user['role'];
                $found = true;
                break;
            }
        }

        // 一致する従業員がいない場合、新規従業員として追加
        if (!$found) {
            $maxId++;
            $employeeCode = 'YA-' . str_pad($maxId, 3, '0', STR_PAD_LEFT);

            $newEmployee = array(
                'id' => $maxId,
                'code' => $employeeCode,
                'name' => $user['name'],
                'area' => '本社',
                'email' => $email,
                'memo' => 'users.jsonから移行',
                'username' => $email,
                'password' => $user['password'],
                'role' => $user['role']
            );

            $data['employees'][] = $newEmployee;
            echo "  - {$user['name']} ({$email}): 新規従業員として追加 (ID: {$maxId}, Code: {$employeeCode})\n";
        }
    }
    echo "\n";
}

// データを保存
echo "3. データを保存...\n";
saveData($data);
echo "  完了しました\n\n";

// 結果表示
echo "=== 統合結果 ===\n";
echo "従業員総数: " . count($data['employees']) . "名\n";

$withAuth = 0;
$withoutAuth = 0;
foreach ($data['employees'] as $employee) {
    if (!empty($employee['username']) && !empty($employee['password']) && !empty($employee['role'])) {
        $withAuth++;
    } else {
        $withoutAuth++;
    }
}

echo "  - 認証情報あり: {$withAuth}名\n";
echo "  - 認証情報なし: {$withoutAuth}名\n";
echo "\n";

echo "=== 完了 ===\n";
echo "統合が完了しました。従業員マスタ (employees.php) から確認してください。\n";
