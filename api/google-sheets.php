<?php
/**
 * Google Sheets API クライアント
 * スプレッドシートの読み書き・書式設定をサポート
 */

require_once __DIR__ . '/../config/config.php';

class GoogleSheetsClient {
    private $configFile;
    private $tokenFile;
    private $clientId;
    private $clientSecret;
    private $spreadsheetId;

    // 借入金返済予定表のスプレッドシートID
    const LOAN_SPREADSHEET_ID = '1mQLXxo61eeRTof0fjU4qAr1qXcBK3JqkuyLA-4iWVo4';

    public function __construct() {
        $this->configFile = __DIR__ . '/../config/google-config.json';
        $this->tokenFile = __DIR__ . '/../config/google-drive-token.json'; // Driveと同じトークンを使用
        $this->spreadsheetId = self::LOAN_SPREADSHEET_ID;
        $this->loadConfig();
    }

    /**
     * 設定ファイルを読み込み
     */
    private function loadConfig() {
        if (!file_exists($this->configFile)) {
            return;
        }

        $config = json_decode(file_get_contents($this->configFile), true);
        $this->clientId = $config['client_id'] ?? null;
        $this->clientSecret = $config['client_secret'] ?? null;
    }

    /**
     * アクセストークンを取得
     */
    public function getAccessToken() {
        if (!file_exists($this->tokenFile)) {
            throw new Exception('Google連携が設定されていません');
        }

        $token = json_decode(file_get_contents($this->tokenFile), true);

        // トークンの有効期限をチェック
        $savedAt = strtotime($token['saved_at'] ?? '2000-01-01');
        $expiresIn = $token['expires_in'] ?? 3600;
        $expiresAt = $savedAt + $expiresIn - 300;

        if (time() > $expiresAt && isset($token['refresh_token'])) {
            $token = $this->refreshToken($token['refresh_token']);
        }

        return $token['access_token'] ?? null;
    }

    /**
     * リフレッシュトークンでアクセストークンを更新
     */
    private function refreshToken($refreshToken) {
        $tokenUrl = 'https://oauth2.googleapis.com/token';

        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];

        $options = [
            'http' => [
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($params),
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($tokenUrl, false, $context);

        if ($response === false) {
            throw new Exception('Failed to refresh token');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Token refresh error: ' . ($data['error_description'] ?? $data['error']));
        }

        if (!isset($data['refresh_token'])) {
            $data['refresh_token'] = $refreshToken;
        }

        $data['saved_at'] = date('Y-m-d H:i:s');
        file_put_contents($this->tokenFile, json_encode($data, JSON_PRETTY_PRINT));

        return $data;
    }

    /**
     * シート一覧を取得
     */
    public function getSheets() {
        $accessToken = $this->getAccessToken();

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}?fields=sheets(properties(sheetId,title))";

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
            throw new Exception('Failed to connect to Google Sheets API');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Sheets API error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        return $data['sheets'] ?? [];
    }

    /**
     * シートのデータを取得
     */
    public function getSheetData($sheetName, $range = null) {
        $accessToken = $this->getAccessToken();

        $fullRange = $sheetName;
        if ($range) {
            $fullRange .= '!' . $range;
        }

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/" . urlencode($fullRange);

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
            throw new Exception('Failed to connect to Google Sheets API');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Sheets API error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        return $data['values'] ?? [];
    }

    /**
     * 「現行」シートから銀行列の位置を特定
     * @return array 銀行名 => ['startCol' => 開始列インデックス, 'endCol' => 終了列インデックス]
     */
    public function getBankColumns() {
        // 1行目（借入先名）と7行目（ヘッダー）を取得
        $row1 = $this->getSheetData('現行', '1:1');
        $row7 = $this->getSheetData('現行', '7:7');

        $banks = [];
        $currentBank = null;
        $startCol = null;

        if (empty($row1) || empty($row1[0])) {
            return $banks;
        }

        foreach ($row1[0] as $colIndex => $cellValue) {
            $cellValue = trim($cellValue ?? '');

            // 「借入先」ヘッダーはスキップ
            if ($cellValue === '借入先' || $colIndex === 0) {
                continue;
            }

            // 新しい銀行名が見つかった
            if (!empty($cellValue) && $cellValue !== $currentBank) {
                // 前の銀行の終了位置を記録
                if ($currentBank !== null && $startCol !== null) {
                    $banks[$currentBank] = [
                        'startCol' => $startCol,
                        'endCol' => $colIndex - 1
                    ];
                }
                $currentBank = $cellValue;
                $startCol = $colIndex;
            }
        }

        // 最後の銀行
        if ($currentBank !== null && $startCol !== null) {
            $banks[$currentBank] = [
                'startCol' => $startCol,
                'endCol' => count($row1[0]) - 1
            ];
        }

        return $banks;
    }

    /**
     * 指定した年月の行を特定
     * @param string $yearMonth 年月（例: "2024.12"）
     * @return int|null 行インデックス（0始まり）
     */
    public function findRowByYearMonth($yearMonth) {
        // B列のデータを取得（年月が入っている）
        $colB = $this->getSheetData('現行', 'B:B');

        // 複数のフォーマットで検索
        $searchPatterns = [
            $yearMonth,                         // "2025.12"
            str_replace('.', '/', $yearMonth),  // "2025/12"
            str_replace('.', '-', $yearMonth),  // "2025-12"
        ];

        // 年月を分解して他のフォーマットも追加
        if (preg_match('/^(\d{4})[.\/-](\d{1,2})$/', $yearMonth, $m)) {
            $year = $m[1];
            $month = intval($m[2]);
            $searchPatterns[] = "{$year}年{$month}月";           // "2025年12月"
            $searchPatterns[] = sprintf("%d.%d", $year, $month); // "2025.12" (ゼロなし)
            $searchPatterns[] = sprintf("%d/%d", $year, $month); // "2025/12" (ゼロなし)
        }

        foreach ($colB as $rowIndex => $row) {
            $cellValue = trim($row[0] ?? '');
            foreach ($searchPatterns as $pattern) {
                if ($cellValue === $pattern) {
                    return $rowIndex;
                }
            }
        }

        return null;
    }

    /**
     * B列のデータサンプルを取得（デバッグ用）
     * @return array B列の最初の20行のデータ
     */
    public function getColumnBSample() {
        $colB = $this->getSheetData('現行', 'B1:B30');
        $sample = [];
        foreach ($colB as $rowIndex => $row) {
            $value = trim($row[0] ?? '');
            if (!empty($value)) {
                $sample[] = [
                    'row' => $rowIndex + 1,
                    'value' => $value
                ];
            }
        }
        return $sample;
    }

    /**
     * セルの背景色を変更
     * @param int $sheetId シートID
     * @param int $startRow 開始行（0始まり）
     * @param int $endRow 終了行（0始まり、排他）
     * @param int $startCol 開始列（0始まり）
     * @param int $endCol 終了列（0始まり、排他）
     * @param array $color RGB色 ['red' => 0-1, 'green' => 0-1, 'blue' => 0-1]
     */
    public function setCellBackgroundColor($sheetId, $startRow, $endRow, $startCol, $endCol, $color) {
        $accessToken = $this->getAccessToken();

        $requestBody = [
            'requests' => [
                [
                    'repeatCell' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'startRowIndex' => $startRow,
                            'endRowIndex' => $endRow,
                            'startColumnIndex' => $startCol,
                            'endColumnIndex' => $endCol
                        ],
                        'cell' => [
                            'userEnteredFormat' => [
                                'backgroundColor' => $color
                            ]
                        ],
                        'fields' => 'userEnteredFormat.backgroundColor'
                    ]
                ]
            ]
        ];

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}:batchUpdate";

        $options = [
            'http' => [
                'header'  => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($requestBody),
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
            throw new Exception('Failed to connect to Google Sheets API');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Sheets API error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        return true;
    }

    /**
     * 「現行」シートのIDを取得
     */
    public function getCurrentSheetId() {
        $sheets = $this->getSheets();

        foreach ($sheets as $sheet) {
            if (($sheet['properties']['title'] ?? '') === '現行') {
                return $sheet['properties']['sheetId'];
            }
        }

        throw new Exception('「現行」シートが見つかりません');
    }

    /**
     * PDF金額と一致する銀行のセルに色を付ける
     * @param int $amount 照合する金額（元金+利息）
     * @param string $yearMonth 対象年月（例: "2024.12"）
     * @param string|null $bankName 銀行名（指定があれば優先的に照合）
     * @return array 結果情報
     */
    public function markMatchingCell($amount, $yearMonth, $bankName = null) {
        // シートIDを取得
        $sheetId = $this->getCurrentSheetId();

        // 対象行を特定
        $rowIndex = $this->findRowByYearMonth($yearMonth);
        if ($rowIndex === null) {
            throw new Exception("年月「{$yearMonth}」の行が見つかりません");
        }

        // 銀行列情報を取得
        $bankColumns = $this->getBankColumns();

        // 対象行のデータを取得
        $rowNum = $rowIndex + 1; // 1始まりの行番号
        $rowData = $this->getSheetData('現行', "{$rowNum}:{$rowNum}");

        if (empty($rowData) || empty($rowData[0])) {
            throw new Exception("行データが取得できません");
        }

        $row = $rowData[0];
        $matchedBank = null;
        $matchedCols = null;

        // 銀行名が指定されている場合、その銀行を優先的に確認
        if ($bankName !== null && isset($bankColumns[$bankName])) {
            $cols = $bankColumns[$bankName];
            $principal = $this->parseAmount($row[$cols['startCol']] ?? '0');
            $interest = $this->parseAmount($row[$cols['startCol'] + 1] ?? '0');
            $total = $principal + $interest;

            if ($total === $amount) {
                $matchedBank = $bankName;
                $matchedCols = $cols;
            }
        }

        // 指定銀行で見つからなかった場合、全銀行を検索（元金+利息で照合）
        if ($matchedBank === null) {
            foreach ($bankColumns as $bank => $cols) {
                $principal = $this->parseAmount($row[$cols['startCol']] ?? '0');
                $interest = $this->parseAmount($row[$cols['startCol'] + 1] ?? '0');
                $total = $principal + $interest;

                if ($total === $amount) {
                    $matchedBank = $bank;
                    $matchedCols = $cols;
                    break;
                }
            }
        }

        // まだ見つからない場合、行全体から金額を検索（単一セルに合計が入っている場合）
        if ($matchedBank === null) {
            foreach ($row as $colIndex => $cellValue) {
                $cellAmount = $this->parseAmount($cellValue);
                if ($cellAmount === $amount && $cellAmount > 0) {
                    // この列が属する銀行を特定
                    foreach ($bankColumns as $bank => $cols) {
                        if ($colIndex >= $cols['startCol'] && $colIndex <= $cols['endCol']) {
                            $matchedBank = $bank;
                            $matchedCols = $cols;
                            break 2;
                        }
                    }
                    // 銀行列に属さない場合でも、その列周辺に色を付ける
                    if ($matchedBank === null) {
                        $matchedBank = '(列' . ($colIndex + 1) . ')';
                        $matchedCols = [
                            'startCol' => max(0, $colIndex - 1),
                            'endCol' => $colIndex + 1
                        ];
                        break;
                    }
                }
            }
        }

        if ($matchedBank === null) {
            return [
                'success' => false,
                'message' => "金額 ¥" . number_format($amount) . " に一致する銀行が見つかりません"
            ];
        }

        // 緑色の背景を設定（確認済みの意味）
        $greenColor = [
            'red' => 0.85,
            'green' => 0.95,
            'blue' => 0.85
        ];

        // 元金・利息・残高の3列に色を付ける
        $this->setCellBackgroundColor(
            $sheetId,
            $rowIndex,
            $rowIndex + 1,
            $matchedCols['startCol'],
            $matchedCols['startCol'] + 3, // 元金、利息、残高の3列
            $greenColor
        );

        return [
            'success' => true,
            'bank' => $matchedBank,
            'yearMonth' => $yearMonth,
            'amount' => $amount,
            'message' => "「{$matchedBank}」の {$yearMonth} 行に色を付けました"
        ];
    }

    /**
     * 金額文字列をintに変換
     */
    private function parseAmount($value) {
        // カンマや円記号を除去して数値化
        $value = str_replace([',', '¥', '￥', ' '], '', $value);
        return intval($value);
    }
}
