<?php
/**
 * 従業員マスタから社内連絡先マスタを初期化
 * 在籍中でメールアドレスがある従業員を contact_masters に追加
 *
 * 使い方: php scripts/init-contact-masters.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions/encryption.php';

$data = getData();
$todayDate = date('Y-m-d');

// 既存の contact_masters を確認
$existing = $data['contact_masters'] ?? [];
$existingEmails = array_map(fn($c) => $c['email'] ?? '', $existing);

$added = 0;
foreach ($data['employees'] ?? [] as $emp) {
    // 退職者はスキップ
    if (!empty($emp['leave_date']) && $emp['leave_date'] <= $todayDate) continue;

    $email = $emp['email'] ?? '';
    // 暗号化メールの復号
    if (is_string($email) && str_starts_with($email, 'enc:')) {
        try { $email = decryptValue($email); } catch (Exception $e) { continue; }
    }
    if (!$email) continue;

    // 既に登録済みならスキップ
    if (in_array($email, $existingEmails, true)) continue;

    $data['contact_masters'][] = [
        'id' => 'cm_' . uniqid(),
        'name' => $emp['name'] ?? '',
        'email' => $email,
        'department' => $emp['department'] ?? '',
        'phone' => $emp['phone'] ?? '',
        'notes' => '',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $existingEmails[] = $email;
    $added++;
}

if ($added > 0) {
    saveData($data);
    echo "完了: {$added}件の従業員を社内連絡先マスタに追加しました\n";
} else {
    echo "追加対象はありませんでした（全員登録済み or メールなし）\n";
}

echo "合計: " . count($data['contact_masters']) . "件\n";
