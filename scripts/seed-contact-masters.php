<?php
/**
 * サーバー上でcontact_mastersを初期化するワンタイムスクリプト
 * ブラウザまたはCLIから実行可能
 * 実行後に削除すること
 */
require_once __DIR__ . '/../config/config.php';

$data = getData();
if (!empty($data['contact_masters'])) {
    echo "既に " . count($data['contact_masters']) . " 件登録済みです。スキップしました。";
    exit;
}

// 従業員マスタから初期化
$todayDate = date('Y-m-d');
$added = 0;
if (function_exists('decryptValue')) {
    // already loaded
} else {
    require_once __DIR__ . '/../functions/encryption.php';
}

foreach ($data['employees'] ?? [] as $emp) {
    if (!empty($emp['leave_date']) && $emp['leave_date'] <= $todayDate) continue;
    $email = $emp['email'] ?? '';
    if (is_string($email) && str_starts_with($email, 'enc:')) {
        try { $email = decryptValue($email); } catch (Exception $e) { continue; }
    }
    if (!$email) continue;
    $data['contact_masters'][] = [
        'id' => 'cm_' . uniqid(),
        'name' => $emp['name'] ?? '',
        'email' => $email,
        'department' => $emp['department'] ?? '',
        'phone' => $emp['phone'] ?? '',
        'notes' => '',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $added++;
}

saveData($data);
echo "完了: {$added}件追加。合計: " . count($data['contact_masters']) . "件";
