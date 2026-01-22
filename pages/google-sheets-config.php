<?php
/**
 * Google Sheets API設定
 *
 * セットアップ手順:
 * 1. Google Cloud Consoleでプロジェクトを作成
 * 2. Google Sheets APIを有効化
 * 3. サービスアカウントを作成してJSONキーをダウンロード
 * 4. credentials.jsonとして保存
 * 5. スプレッドシートをサービスアカウントのメールアドレスと共有
 */

// Google APIクライアントライブラリのオートロード
// Composerでインストール: composer require google/apiclient
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Google Sheets APIクライアントを取得
 */
function getGoogleSheetsClient() {
    $credentialsPath = __DIR__ . '/credentials.json';

    if (!file_exists($credentialsPath)) {
        throw new Exception('credentials.jsonが見つかりません。Google Cloud Consoleからサービスアカウントキーをダウンロードしてください。');
    }

    $client = new Google_Client();
    $client->setApplicationName('トラブル対応管理システム');
    $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
    $client->setAuthConfig($credentialsPath);

    return $client;
}

/**
 * Google Sheetsサービスを取得
 */
function getGoogleSheetsService() {
    $client = getGoogleSheetsClient();
    return new Google_Service_Sheets($client);
}

/**
 * スプレッドシートIDをURLから抽出
 */
function extractSpreadsheetId($url) {
    // https://docs.google.com/spreadsheets/d/{SPREADSHEET_ID}/edit...
    if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
        return $matches[1];
    }
    // すでにIDの場合はそのまま返す
    return $url;
}

/**
 * スプレッドシートからトラブル対応データを読み込み
 *
 * @param string $spreadsheetUrl スプレッドシートのURLまたはID
 * @param string $range 読み取り範囲（例: 'シート1!A2:M'）
 * @return array トラブル対応データの配列
 */
function fetchTroublesFromSheet($spreadsheetUrl, $range = 'シート1!A3:M') {
    try {
        $service = getGoogleSheetsService();
        $spreadsheetId = extractSpreadsheetId($spreadsheetUrl);

        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();

        if (empty($values)) {
            return array();
        }

        $troubles = array();
        $id = 1;

        foreach ($values as $row) {
            // 空行をスキップ
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }

            $troubles[] = array(
                'id' => $id++,
                'project_name' => $row[0] ?? '',      // A: 現場名 or プロジェクト番号
                'trouble_content' => $row[1] ?? '',   // B: トラブル内容
                'response_content' => $row[2] ?? '',  // C: 対応内容
                'reporter' => $row[3] ?? '',          // D: 記入者
                'responder' => $row[4] ?? '',         // E: 対応者
                'status' => $row[5] ?? '',            // F: 状態
                'date' => $row[6] ?? '',              // G: 日付
                'call_no' => $row[7] ?? '',           // H: コールNo
                'project_contact' => ($row[8] ?? '') === 'TRUE', // I: プロジェクトコンタクト
                'case_no' => $row[9] ?? '',           // J: 案件No
                'company_name' => $row[10] ?? '',     // K: 社名
                'customer_name' => $row[11] ?? '',    // L: お客様お名前
                'honorific' => $row[12] ?? '様',      // M: 様
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            );
        }

        return $troubles;

    } catch (Exception $e) {
        error_log('Google Sheets API Error: ' . $e->getMessage());
        throw new Exception('スプレッドシートの読み込みに失敗しました: ' . $e->getMessage());
    }
}

/**
 * スプレッドシートのデータをシステムに同期
 *
 * @param string $spreadsheetUrl スプレッドシートのURL
 * @return array 同期結果 ['success' => bool, 'count' => int, 'message' => string]
 */
function syncTroublesFromSheet($spreadsheetUrl) {
    try {
        require_once __DIR__ . '/../config/config.php';

        $troubles = fetchTroublesFromSheet($spreadsheetUrl);

        if (empty($troubles)) {
            return array(
                'success' => false,
                'count' => 0,
                'message' => 'スプレッドシートにデータが見つかりませんでした'
            );
        }

        // 既存のデータを読み込み
        $data = getData();

        // トラブルデータを上書き（既存のIDと重複しないように調整）
        $maxId = 0;
        if (!empty($data['troubles'])) {
            foreach ($data['troubles'] as $trouble) {
                if (isset($trouble['id']) && $trouble['id'] > $maxId) {
                    $maxId = $trouble['id'];
                }
            }
        }

        // スプレッドシートのデータのIDを調整
        foreach ($troubles as &$trouble) {
            $trouble['id'] = ++$maxId;
        }

        // 既存のトラブルデータに追加
        if (!isset($data['troubles'])) {
            $data['troubles'] = array();
        }
        $data['troubles'] = array_merge($data['troubles'], $troubles);

        // データを保存
        saveData($data);

        return array(
            'success' => true,
            'count' => count($troubles),
            'message' => count($troubles) . '件のトラブル対応データを同期しました'
        );

    } catch (Exception $e) {
        return array(
            'success' => false,
            'count' => 0,
            'message' => 'エラー: ' . $e->getMessage()
        );
    }
}
