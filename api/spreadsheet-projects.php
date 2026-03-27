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

            // 必要な列を取得
            $cols = $this->sourceConfig['columns'];
            $projectNumberCol = $cols['project_number'] ?? 'B';
            $salesAssigneeCol = $cols['sales_assignee'] ?? 'D';
            $siteNameCol = $cols['site_name'] ?? 'H';
            $dealerCol = $cols['dealer_name'] ?? 'I';
            $officeCol = $cols['office_name'] ?? 'J';
            $makerCol = $cols['maker'] ?? 'M';
            $ledSizeCol = $cols['led_size'] ?? 'S';
            $lcdSizeCol = $cols['lcd_size'] ?? 'X';
            $cmsPlayerCol = $cols['cms_player'] ?? 'Y';
            $startRow = $this->sourceConfig['data_start_row'] ?? 2;

            // B列からY列までのデータを取得
            $range = "{$sheetName}!{$projectNumberCol}{$startRow}:{$cmsPlayerCol}";

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
            $baseIndex = $this->columnToIndex($projectNumberCol);

            foreach ($data['values'] ?? [] as $row) {
                $projectNumber = trim($row[0] ?? '');  // B列（最初の列）
                $salesAssignee = trim($row[$this->columnToIndex($salesAssigneeCol) - $baseIndex] ?? '');
                $siteName = trim($row[$this->columnToIndex($siteNameCol) - $baseIndex] ?? '');
                $dealer = trim($row[$this->columnToIndex($dealerCol) - $baseIndex] ?? '');
                $office = trim($row[$this->columnToIndex($officeCol) - $baseIndex] ?? '');
                $maker = trim($row[$this->columnToIndex($makerCol) - $baseIndex] ?? '');
                $ledSize = trim($row[$this->columnToIndex($ledSizeCol) - $baseIndex] ?? '');
                $lcdSize = trim($row[$this->columnToIndex($lcdSizeCol) - $baseIndex] ?? '');
                $cmsPlayer = trim($row[$this->columnToIndex($cmsPlayerCol) - $baseIndex] ?? '');

                // LEDパネル枚数形式（例: 4×3, 9x6）をインチ数に変換
                $panelToInch = [
                    '4x3' => '59',  '4×3' => '59',
                    '6x4' => '90',  '6×4' => '90',
                    '7x4' => '100', '7×4' => '100',
                    '9x6' => '140', '9×6' => '140',
                    '7x10' => '150', '7×10' => '150',
                    '10x7' => '150', '10×7' => '150',
                ];
                if ($ledSize !== '' && isset($panelToInch[$ledSize])) {
                    $ledSize = $panelToInch[$ledSize];
                } elseif ($ledSize !== '' && preg_match('/^(\d+)\s*[×x×]\s*(\d+)$/u', $ledSize, $pm)) {
                    $key1 = $pm[1] . 'x' . $pm[2];
                    $key2 = $pm[1] . '×' . $pm[2];
                    if (isset($panelToInch[$key1])) $ledSize = $panelToInch[$key1];
                    elseif (isset($panelToInch[$key2])) $ledSize = $panelToInch[$key2];
                }

                // 案件番号と現場名の両方が入っている行のみ取得
                if (!empty($projectNumber) && !empty($siteName)) {
                    $projects[] = [
                        'project_number' => $projectNumber,
                        'sales_assignee' => $salesAssignee,
                        'site_name' => $siteName,
                        'dealer_name' => $dealer,
                        'office_name' => $office,
                        'maker' => $maker,
                        'led_size' => $ledSize,
                        'lcd_size' => $lcdSize,
                        'cms_player' => $cmsPlayer
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
     * 現場名からタグを抽出
     */
    private function extractTagFromSiteName($siteName) {
        if (preg_match('/^【レ】/', $siteName)) {
            return 'レンタル';
        } elseif (preg_match('/^【売】/', $siteName)) {
            return '販売';
        } else {
            return '';
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

        // メーカー名 → 製品カテゴリ名 のマッピングを構築
        $makerNameToId = [];
        foreach ($data['manufacturers'] ?? [] as $m) {
            if (!empty($m['name']) && !empty($m['id']) && empty($m['deleted_at'])) {
                $makerNameToId[trim($m['name'])] = $m['id'];
            }
        }
        $makerIdToCategory = [];
        foreach ($data['productCategories'] ?? [] as $cat) {
            if (empty($cat['name'])) continue;
            foreach ($cat['maker_ids'] ?? [] as $mid) {
                $makerIdToCategory[$mid] = $cat['name'];
            }
        }

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

            if (isset($existingMap[$projectNumber])) {
                // 重複IDの場合はスキップ（データ不整合を防止）
                if (isset($duplicateIds[$projectNumber])) {
                    $skipped++;
                } elseif ($mode === 'merge' || $mode === 'update') {
                    // 既存プロジェクトを更新
                    $index = $existingMap[$projectNumber];
                    $hasChanges = false;

                    // ソフトデリートされているか確認
                    $wasDeleted = !empty($data['projects'][$index]['deleted_at']);

                    // 各フィールドを更新（空でない場合のみ）
                    if (!empty($sheetProject['site_name'])) {
                        // 手動変更された名前を保護:
                        // synced_name（前回スプシ同期時の値）と現在のnameが一致している場合のみ上書き
                        // synced_nameがない（手動追加 or 旧データ）場合はname変更しない
                        $currentName   = $data['projects'][$index]['name'] ?? '';
                        $lastSyncedName = $data['projects'][$index]['synced_name'] ?? null;
                        $nameManuallyEdited = ($lastSyncedName !== null && $currentName !== $lastSyncedName);

                        if ($lastSyncedName === null || !$nameManuallyEdited) {
                            // 初回同期 or 手動変更なし → スプシ値で更新
                            $data['projects'][$index]['name'] = $sheetProject['site_name'];
                            $data['projects'][$index]['synced_name'] = $sheetProject['site_name'];
                            // タグを自動抽出して設定
                            $tag = $this->extractTagFromSiteName($sheetProject['site_name']);
                            if (!empty($tag)) {
                                $data['projects'][$index]['tag'] = $tag;
                            }
                        } else {
                            // 手動変更あり → nameは保護、synced_nameだけ最新スプシ値に更新
                            $data['projects'][$index]['synced_name'] = $sheetProject['site_name'];
                        }
                        $hasChanges = true;
                    }
                    if (!empty($sheetProject['sales_assignee'])) {
                        $data['projects'][$index]['sales_assignee'] = $sheetProject['sales_assignee'];
                        $hasChanges = true;
                    }
                    if (!empty($sheetProject['dealer_name'])) {
                        $data['projects'][$index]['dealer_name'] = $sheetProject['dealer_name'];
                        $hasChanges = true;
                    }
                    if (!empty($sheetProject['office_name'])) {
                        $data['projects'][$index]['office_name'] = $sheetProject['office_name'];
                        $hasChanges = true;
                    }
                    if (!empty($sheetProject['maker'])) {
                        $data['projects'][$index]['maker'] = $sheetProject['maker'];
                        // メーカーから製品カテゴリを自動設定（未設定の場合のみ）
                        if (empty($data['projects'][$index]['product_category'])) {
                            $mid = $makerNameToId[trim($sheetProject['maker'])] ?? null;
                            if ($mid && isset($makerIdToCategory[$mid])) {
                                $data['projects'][$index]['product_category'] = $makerIdToCategory[$mid];
                            }
                        }
                        $hasChanges = true;
                    }
                    if (!empty($sheetProject['led_size'])) {
                        $data['projects'][$index]['led_size'] = $sheetProject['led_size'];
                        $hasChanges = true;
                    }
                    if (!empty($sheetProject['lcd_size'])) {
                        $data['projects'][$index]['lcd_size'] = $sheetProject['lcd_size'];
                        $hasChanges = true;
                    }
                    if (!empty($sheetProject['cms_player'])) {
                        $data['projects'][$index]['cms_player'] = $sheetProject['cms_player'];
                        $hasChanges = true;
                    }

                    if ($wasDeleted) {
                        // ソフトデリートを解除して復元（スプレッドシートに存在するなら再表示）
                        unset($data['projects'][$index]['deleted_at']);
                        unset($data['projects'][$index]['deleted_by']);
                        $data['projects'][$index]['updated_at'] = date('Y-m-d H:i:s');
                        $added++;
                    } elseif ($hasChanges) {
                        $data['projects'][$index]['updated_at'] = date('Y-m-d H:i:s');
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
                    // タグを自動抽出
                    $tag = $this->extractTagFromSiteName($sheetProject['site_name']);

                    // 新規PJ: メーカーから製品カテゴリを自動決定
                    $newMaker = trim($sheetProject['maker'] ?? '');
                    $newMakerId = $makerNameToId[$newMaker] ?? null;
                    $newProductCategory = ($newMakerId && isset($makerIdToCategory[$newMakerId]))
                        ? $makerIdToCategory[$newMakerId]
                        : '';

                    $newProject = [
                        'id' => $projectNumber,
                        'name' => $sheetProject['site_name'],
                        'synced_name' => $sheetProject['site_name'],  // 手動変更保護用
                        'tag' => $tag,
                        'sales_assignee' => $sheetProject['sales_assignee'] ?? '',
                        'dealer_name' => $sheetProject['dealer_name'] ?? '',
                        'office_name' => $sheetProject['office_name'] ?? '',
                        'maker' => $newMaker,
                        'product_category' => $newProductCategory,
                        'led_size' => $sheetProject['led_size'] ?? '',
                        'lcd_size' => $sheetProject['lcd_size'] ?? '',
                        'cms_player' => $sheetProject['cms_player'] ?? '',
                        'status' => '',
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

    // 認証チェック（管理部のみ）
    if (!isset($_SESSION['user_email']) || !isAdmin()) {
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
