<?php
/**
 * 写真アップロードシステムの診断テスト
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/photo-attendance-functions.php';

echo "=== 写真アップロードシステム診断 ===\n\n";

// 1. 従業員データの確認
echo "1. 従業員データ確認\n";
$employees = getEmployees();
echo "   従業員数: " . count($employees) . "\n";
foreach ($employees as $emp) {
    echo "   - {$emp['name']} (ID: {$emp['id']}, Email: {$emp['email']})\n";
    if (!isset($emp['id'])) {
        echo "     ⚠️ 警告: IDが設定されていません\n";
    }
}
echo "\n";

// 2. ユーザー認証情報の確認
echo "2. ユーザー認証情報確認\n";
$users = getUsers();
foreach ($users as $email => $user) {
    echo "   - {$user['name']} ({$email})\n";
    echo "     Role: {$user['role']}\n";
    echo "     Employee ID: " . ($user['employee_id'] ?? '未設定') . "\n";
    if (!isset($user['employee_id'])) {
        echo "     ⚠️ 警告: Employee IDが設定されていません\n";
    }
}
echo "\n";

// 3. アップロードディレクトリの確認
echo "3. アップロードディレクトリ確認\n";
echo "   パス: " . PHOTO_UPLOAD_DIR . "\n";
if (file_exists(PHOTO_UPLOAD_DIR)) {
    echo "   ✓ ディレクトリ存在\n";
    if (is_writable(PHOTO_UPLOAD_DIR)) {
        echo "   ✓ 書き込み可能\n";
    } else {
        echo "   ⚠️ 書き込み不可\n";
    }
} else {
    echo "   ⚠️ ディレクトリが存在しません\n";
    echo "   作成を試みます...\n";
    if (mkdir(PHOTO_UPLOAD_DIR, 0755, true)) {
        echo "   ✓ ディレクトリ作成成功\n";
    } else {
        echo "   ✗ ディレクトリ作成失敗\n";
    }
}
echo "\n";

// 4. セッションシミュレーション
echo "4. ログインセッションシミュレーション\n";
foreach ($users as $email => $user) {
    echo "   {$user['name']} ({$email}) でログインした場合:\n";

    // セッション変数をシミュレート
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_id'] = $user['employee_id'] ?? null;

    echo "     user_id: " . ($_SESSION['user_id'] ?? 'null') . "\n";

    // 従業員検索
    $userId = $_SESSION['user_id'];
    $found = false;
    foreach ($employees as $emp) {
        if ($emp['id'] == $userId) {
            echo "     ✓ 従業員マッチング成功: {$emp['name']}\n";
            $found = true;
            break;
        }
    }

    if (!$found) {
        echo "     ⚠️ 従業員マッチング失敗\n";
    }
    echo "\n";
}

// 5. 写真アップロードデータの確認
echo "5. 写真アップロードデータ確認\n";
$photoData = getPhotoAttendanceData();
echo "   総レコード数: " . count($photoData) . "\n";
if (count($photoData) > 0) {
    echo "   最新5件:\n";
    $recent = array_slice($photoData, -5);
    foreach ($recent as $record) {
        echo "     - {$record['upload_date']} {$record['upload_type']}: Employee ID {$record['employee_id']}\n";
    }
}
echo "\n";

echo "=== 診断完了 ===\n";
echo "問題が見つかった場合は、上記の警告を確認してください。\n";
