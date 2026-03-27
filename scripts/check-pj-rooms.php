<?php
require __DIR__ . '/../config/config.php';
$d = getData();
$pjs = array_filter($d['projects'] ?? [], fn($p) => empty($p['deleted_at']));
$withRoom = array_filter($pjs, fn($p) => !empty($p['internal_chat_room_id']));
$without  = array_filter($pjs, fn($p) => empty($p['internal_chat_room_id']));
echo 'PJ総数: ' . count($pjs) . PHP_EOL;
echo 'チャットルームあり: ' . count($withRoom) . PHP_EOL;
echo 'チャットルームなし: ' . count($without) . PHP_EOL;
foreach ($without as $p) {
    echo '  - ' . $p['id'] . ' ' . ($p['name'] ?? '') . PHP_EOL;
}
