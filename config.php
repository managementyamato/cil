<?php
// 設定ファイル

// データファイルのパス
define('DATA_FILE', __DIR__ . '/data.json');
define('USERS_FILE', __DIR__ . '/users.json');

// ログインユーザー情報を取得
function getUsers() {
    if (file_exists(USERS_FILE)) {
        $json = file_get_contents(USERS_FILE);
        $users = json_decode($json, true);
        if ($users) {
            return $users;
        }
    }
    // デフォルトユーザー（初回用）
    return array();
}

// ユーザー情報を保存
function saveUsers($users) {
    return file_put_contents(USERS_FILE, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// グローバル変数として読み込み（後方互換性のため）
$USERS = getUsers();

// 権限チェック関数
function hasPermission($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }

    $roleHierarchy = array('viewer' => 1, 'editor' => 2, 'admin' => 3);
    $userLevel = $roleHierarchy[$_SESSION['user_role']] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 999;

    return $userLevel >= $requiredLevel;
}

// 現在のユーザーが管理者かチェック
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// 現在のユーザーが編集可能かチェック
function canEdit() {
    return hasPermission('editor');
}

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
