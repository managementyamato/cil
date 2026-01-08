<?php
// 設定ファイル

// データファイルのパス
define('DATA_FILE', __DIR__ . '/data.json');

// 認証設定（ユーザー名とパスワード）
// 複数のユーザーを登録可能
$USERS = array(
    'admin' => 'password123',  // ユーザー名 => パスワード（変更してください）
    // 追加のユーザーを登録する場合は下記のように追加
    // 'user2' => 'password456',
);

// 初期データ
function getInitialData() {
    return array(
        'projects' => array(),
        'assignees' => array(),
        'troubles' => array(),
        'customers' => array(),
        'partners' => array(),
        'employees' => array(),
        'productCategories' => array(),
        'settings' => array(
            'spreadsheet_url' => ''
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
                    'spreadsheet_url' => ''
                );
            }
            // 新しいマスタが存在しない場合は初期化
            if (!isset($data['customers'])) $data['customers'] = array();
            if (!isset($data['partners'])) $data['partners'] = array();
            if (!isset($data['employees'])) $data['employees'] = array();
            if (!isset($data['productCategories'])) $data['productCategories'] = array();
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
