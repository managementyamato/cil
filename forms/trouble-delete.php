<?php
/**
 * トラブル対応削除処理
 */
require_once '../api/auth.php';

// 権限チェック
if (!canEdit()) {
    header('Location: /pages/troubles.php');
    exit;
}

// POSTリクエストのみ許可（CSRF対策）
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/troubles.php');
    exit;
}

// POST処理時のCSRF検証
verifyCsrfToken();

if (!isset($_POST['id'])) {
    header('Location: /pages/troubles.php');
    exit;
}

$deleteId = (int)$_POST['id'];
$data = getData();

// トラブルを削除
$newTroubles = array();
$deleted = false;

foreach ($data['troubles'] ?? array() as $trouble) {
    if ($trouble['id'] !== $deleteId) {
        $newTroubles[] = $trouble;
    } else {
        $deleted = true;
    }
}

if ($deleted) {
    $data['troubles'] = $newTroubles;
    saveData($data);
    header('Location: /pages/troubles.php?message=' . urlencode('トラブル対応を削除しました'));
} else {
    header('Location: /pages/troubles.php?message=' . urlencode('削除対象が見つかりませんでした'));
}
exit;
