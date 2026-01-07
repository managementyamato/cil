<?php
// 設定ファイル

// データファイルのパス
define('DATA_FILE', __DIR__ . '/data.json');

// 初期データ
function getInitialData() {
    return array(
        'projects' => array(),
        'assignees' => array(),
        'troubles' => array(),
        'settings' => array(
            'spreadsheet_url' => '',
            'auto_clear_before_import' => false
        )
    );
}

// データ読み込み
function getData() {
    if (file_exists(DATA_FILE)) {
        $json = file_get_contents(DATA_FILE);
        $data = json_decode($json, true);
        if ($data) {
            // 設定が存在しない場合は初期化
            if (!isset($data['settings'])) {
                $data['settings'] = array(
                    'spreadsheet_url' => '',
                    'auto_clear_before_import' => false
                );
            }
            return $data;
        }
    }
    return getInitialData();
}

// データ保存
function saveData($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// セッション開始
session_start();
