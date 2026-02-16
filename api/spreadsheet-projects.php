<?php
/**
 * スプレッドシートから案件データを取得・同期するAPI
 */

require_once __DIR__ . '/google-sheets.php';
require_once __DIR__ . '/../config/config.php';

class SpreadsheetProjectsClient extends GoogleSheetsClient {
    private $sourceConfig;

    public function __construct() {
        parent::__construct();
        $this->loadSourceConfig();
    }

    /**
     * スプレッドシートソース設定を読み込み
     */
    private function loadSourceConfig() {
        $configFile = __DIR__ . '/../config/spreadsheet-sources.json';
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            $this->sourceConfig = $config['project_master'] ?? null;
        }
    }

    /**
     * 設定されているか確認
     */
    public function isConfigured() {
        return !empty($this->sourceConfig) && !empty($this->sourceConfig['spreadsheet_id']);
    }

    /**
     * スプレッドシートIDを取得
     */
    public function getSpreadsheetId() {
        return $this->sourceConfig['spreadsheet_id'] ?? null;
    }

    /**
     * シート名を取得（gidからシート名を解決）
     */
    public function resolveSheetName() {
        if (!empty($this->sourceConfig['sheet_name'])) {
            return $this->sourceConfig['sheet_name'];
        }

        // sheet_idからシート名を取得
        $sheetId = $this->sourceConfig['sheet_id'] ?? null;
        if (!$sheetId) {
            return null;
        }

        try {
            $accessToken = $this->getAccessToken();
            $spreadsheetId = $this->sourceConfig['spreadsheet_id'];

            $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}?fields=sheets(properties(sheetId,title))";

            $options = [
                'http' => [
                    'header'  => "Authorization: Bearer {$accessToken}\r\n",
                    'method'  => 'GET',
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ];

            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);

            foreach ($data['sheets'] ?? [] as $sheet) {
                if (($sheet['properties']['sheetId'] ?? '') == $sheetId) {
                    $sheetName = $sheet['properties']['title'];
                    // 設定に保存
                    $this->sourceConfig['sheet_name'] = $sheetName;
                    $this->saveSourceConfig();
                    return $sheetName;
                }
            }
        } catch (Exception $e) {
            // エラーは無視
        }

        return null;
    }

    /**
     * ソース設定を保存
     */
    private function saveSourceConfig() {
        $configFile = __DIR__ . '/../config/spreadsheet-sources.json';
        $config = [];
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true) ?? [];
        }
        $config['project_master'] = $this->sourceConfig;
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * 列文字を数値インデックスに変換（A=0, B=1, ...）
     */
    private function columnToIndex($column) {
        $column = strtoupper($column);
        $index = 0;
        $length = strlen($column);

        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    /**
     * スプレッドシートからプロジェクト一覧を取得
     */
    public function getProjectsFromSheet() {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'スプレッドシート設定がありません'];
        }

        try {
            $accessToken = $this->getAccessToken();
            $spreadsheetId = $this->sourceConfig['spreadsheet_id'];
            $sheetName = $this->resolveSheetName();

            if (!$sheetName) {
                return ['success' => false, 'message' => 'シート名を特定できませんでした'];
            }

            // B列とH列を取得
            $projectNumberCol = $this->sourceConfig['columns']['project_number'] ?? 'B';
            $siteNameCol = $this->sourceConfig['columns']['site_name'] ?? 'H';
            $startRow = $this->sourceConfig['data_start_row'] ?? 2;

            // B列とH列のデータを取得
            $range = "{$sheetName}!{$projectNumberCol}{$startRow}:{$siteNameCol}";

            $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . urlencode($range);

            $options = [
                'http' => [
                    'header'  => "Authorization: Bearer {$accessToken}\r\n",
                    'method'  => 'GET',
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ];

            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return ['success' => false, 'message' => 'Google Sheets APIへの接続に失敗しました'];
            }

            $data = json_decode($response, true);

            if (isset($data['error'])) {
                return ['success' => false, 'message' => 'APIエラー: ' . ($data['error']['message'] ?? json_encode($data['error']))];
            }

            // データを解析
            $projects = [];
            $projectNumberIndex = $this->columnToIndex($projectNumberCol);
            $siteNameIndex = $this->columnToIndex($siteNameCol);
            $baseIndex = $this->columnToIndex($projectNumberCol);

            foreach ($data['values'] ?? [] as $row) {
                $projectNumber = trim($row[0] ?? '');  // B列（最初の列）
                $siteName = trim($row[$siteNameIndex - $baseIndex] ?? '');  // H列

                // 案件番号と現場名の両方が入っている行のみ取得
                if (!empty($projectNumber) && !empty($siteName)) {
                    $projects[] = [
                        'project_number' => $projectNumber,
                        'site_name' => $siteName
                    ];
                }
            }

            return [
                'success' => true,
                'projects' => $projects,
                'count' => count($projects),
                'sheet_name' => $sheetName
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * スプレッドシートのデータで案件マスタを同期
     */
    public function syncWithMaster($mode = 'merge') {
        $sheetResult = $this->getProjectsFromSheet();

        if (!$sheetResult['success']) {
            return $sheetResult;
        }

        $sheetProjects = $sheetResult['projects'];
        $data = getData();
        $existingProjects = $data['projects'] ?? [];

        // 既存のプロジェクトIDをマップ化（最初の出現のみ使用）
        $existingMap = [];
        $duplicateIds = [];
        foreach ($existingProjects as $index => $project) {
            if (isset($existingMap[$project['id']])) {
                // 重複IDを検出
                $duplicateIds[$project['id']] = true;
            } else {
                $existingMap[$project['id']] = $index;
            }
        }

        $added = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($sheetProjects as $sheetProject) {
            $projectNumber = $sheetProject['project_number'];
            $siteName = $sheetProject['site_name'];

            if (isset($existingMap[$projectNumber])) {
                // 重複IDの場合はスキップ（データ不整合を防止）
                if (isset($duplicateIds[$projectNumber])) {
                    $skipped++;
                } elseif ($mode === 'merge' || $mode === 'update') {
                    // 既存プロジェクトを更新
                    $index = $existingMap[$projectNumber];
                    // 現場名が空でない場合のみ更新
                    // 比較前に空白を正規化して一致判定
                    $existingName = trim(preg_replace('/\s+/u', ' ', $data['projects'][$index]['name'] ?? ''));
                    $newName = trim(preg_replace('/\s+/u', ' ', $siteName));
                    if (!empty($newName) && $existingName !== $newName) {
                        $data['projects'][$index]['name'] = $siteName;
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            } else {
                // 新規プロジェクトを追加
                if ($mode === 'merge' || $mode === 'add') {
                    $newProject = [
                        'id' => $projectNumber,
                        'name' => $siteName,
                        'occurrence_date' => '',
                        'transaction_type' => '',
                        'sales_assignee' => '',
                        'customer_name' => '',
                        'dealer_name' => '',
                        'general_contractor' => '',
                        'postal_code' => '',
                        'prefecture' => '',
                        'address' => '',
                        'shipping_address' => '',
                        'product_category' => '',
                        'product_series' => '',
                        'product_name' => '',
                        'product_spec' => '',
                        'install_partner' => '',
                        'remove_partner' => '',
                        'contract_date' => '',
                        'install_schedule_date' => '',
                        'install_complete_date' => '',
                        'shipping_date' => '',
                        'install_request_date' => '',
                        'install_date' => '',
                        'remove_schedule_date' => '',
                        'remove_request_date' => '',
                        'remove_date' => '',
                        'remove_inspection_date' => '',
                        'warranty_end_date' => '',
                        'memo' => '',
                        'chat_url' => '',
                        'created_at' => date('Y-m-d H:i:s'),
                        'synced_from' => 'spreadsheet'
                    ];
                    $data['projects'][] = $newProject;
                    $added++;
                } else {
                    $skipped++;
                }
            }
        }

        saveData($data);

        $message = "同期完了: 追加 {$added}件, 更新 {$updated}件, スキップ {$skipped}件";
        if (!empty($duplicateIds)) {
            $message .= "（重複ID: " . implode(', ', array_keys($duplicateIds)) . " はスキップ）";
        }

        return [
            'success' => true,
            'added' => $added,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_in_sheet' => count($sheetProjects),
            'message' => $message,
            'duplicate_ids' => array_keys($duplicateIds),
        ];
    }
}

// API呼び出し用
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');

    // 認証チェック
    if (!isset($_SESSION['user_email']) || !canEdit()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '権限がありません']);
        exit;
    }

    // POST時はCSRF検証
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrfToken();
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $client = new SpreadsheetProjectsClient();

    switch ($action) {
        case 'get':
            // スプレッドシートからプロジェクト一覧を取得
            $result = $client->getProjectsFromSheet();
            break;

        case 'sync':
            // 案件マスタと同期
            $mode = $_GET['mode'] ?? $_POST['mode'] ?? 'merge';
            $result = $client->syncWithMaster($mode);
            break;

        case 'clear':
            // 同期したデータを削除
            $data = getData();
            $originalCount = count($data['projects']);

            $data['projects'] = array_values(array_filter($data['projects'], function($project) {
                return !isset($project['synced_from']) || $project['synced_from'] !== 'spreadsheet';
            }));

            $newCount = count($data['projects']);
            $deletedCount = $originalCount - $newCount;

            saveData($data);

            $result = [
                'success' => true,
                'deleted' => $deletedCount,
                'remaining' => $newCount,
                'message' => "同期データを{$deletedCount}件削除しました"
            ];
            break;

        case 'status':
            // 設定状態を確認
            $result = [
                'configured' => $client->isConfigured(),
                'spreadsheet_id' => $client->getSpreadsheetId()
            ];
            break;

        default:
            $result = ['success' => false, 'message' => '不明なアクション'];
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
