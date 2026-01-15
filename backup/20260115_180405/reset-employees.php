<?php
require_once __DIR__ . '/config.php';

$data = getData();
$data['employees'] = [
    [
        'code' => 'YA-001',
        'name' => '管理部',
        'area' => '本社',
        'email' => '',
        'memo' => ''
    ]
];
saveData($data);
echo "Employees reset to original state\n";
