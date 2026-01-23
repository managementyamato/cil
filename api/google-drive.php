<?php
/**
 * Google Drive API クライアント
 * CSVファイルの読み込みをサポート
 */

require_once __DIR__ . '/../config/config.php';

class GoogleDriveClient {
    private $configFile;
    private $tokenFile;
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    // キャッシュ設定
    private $cacheEnabled = true;
    private $cacheTTL = 300; // 5分間キャッシュ
    private $cachePrefix = 'gdrive_cache_';

    public function __construct() {
        $this->configFile = __DIR__ . '/../config/google-config.json';
        $this->tokenFile = __DIR__ . '/../config/google-drive-token.json';
        $this->loadConfig();

        // セッション開始（まだ開始されていない場合）
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * キャッシュからデータを取得
     */
    private function getCache($key) {
        if (!$this->cacheEnabled) {
            return null;
        }

        $cacheKey = $this->cachePrefix . md5($key);

        if (isset($_SESSION[$cacheKey])) {
            $cached = $_SESSION[$cacheKey];
            if (time() < $cached['expires']) {
                return $cached['data'];
            }
            // 期限切れの場合は削除
            unset($_SESSION[$cacheKey]);
        }

        return null;
    }

    /**
     * キャッシュにデータを保存
     */
    private function setCache($key, $data) {
        if (!$this->cacheEnabled) {
            return;
        }

        $cacheKey = $this->cachePrefix . md5($key);
        $_SESSION[$cacheKey] = [
            'data' => $data,
            'expires' => time() + $this->cacheTTL
        ];
    }

    /**
     * 特定のキャッシュをクリア
     */
    public function clearCache($key = null) {
        if ($key !== null) {
            $cacheKey = $this->cachePrefix . md5($key);
            unset($_SESSION[$cacheKey]);
        } else {
            // 全キャッシュをクリア
            foreach ($_SESSION as $sessionKey => $value) {
                if (strpos($sessionKey, $this->cachePrefix) === 0) {
                    unset($_SESSION[$sessionKey]);
                }
            }
        }
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
        $this->redirectUri = $config['redirect_uri'] ?? null;
    }

    /**
     * Drive連携が設定されているかチェック
     */
    public function isConfigured() {
        return !empty($this->clientId) && !empty($this->clientSecret) && file_exists($this->tokenFile);
    }

    /**
     * トークンを保存
     */
    public function saveToken($tokenData) {
        $tokenData['saved_at'] = date('Y-m-d H:i:s');
        file_put_contents($this->tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));
    }

    /**
     * トークンを取得
     */
    public function getToken() {
        if (!file_exists($this->tokenFile)) {
            return null;
        }
        return json_decode(file_get_contents($this->tokenFile), true);
    }

    /**
     * アクセストークンを取得（必要に応じてリフレッシュ）
     */
    public function getAccessToken() {
        $token = $this->getToken();
        if (!$token) {
            throw new Exception('Google Drive連携が設定されていません');
        }

        // トークンの有効期限をチェック（expires_inは秒単位）
        $savedAt = strtotime($token['saved_at'] ?? '2000-01-01');
        $expiresIn = $token['expires_in'] ?? 3600;
        $expiresAt = $savedAt + $expiresIn - 300; // 5分前にリフレッシュ

        if (time() > $expiresAt && isset($token['refresh_token'])) {
            // トークンをリフレッシュ
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

        // リフレッシュトークンは返却されない場合があるので保持
        if (!isset($data['refresh_token'])) {
            $data['refresh_token'] = $refreshToken;
        }

        $this->saveToken($data);
        return $data;
    }

    /**
     * フォルダ一覧を取得
     */
    public function listFolders($parentId = null) {
        // キャッシュをチェック
        $cacheKey = 'folders_' . ($parentId ?? 'root');
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $accessToken = $this->getAccessToken();

        $params = [
            'pageSize' => 100,
            'fields' => 'files(id,name,mimeType,modifiedTime,parents)',
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true'
        ];

        // 検索クエリを構築
        $q = ["mimeType='application/vnd.google-apps.folder'", "trashed=false"];
        if ($parentId) {
            $q[] = "'{$parentId}' in parents";
        }

        $params['q'] = implode(' and ', $q);

        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query($params);

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
            throw new Exception('Failed to connect to Google Drive API');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Drive API error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        $result = $data['files'] ?? [];

        // キャッシュに保存
        $this->setCache($cacheKey, $result);

        return $result;
    }

    /**
     * フォルダ内の全ファイル/サブフォルダを取得
     */
    public function listFolderContents($folderId) {
        // キャッシュをチェック
        $cacheKey = 'contents_' . $folderId;
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $accessToken = $this->getAccessToken();

        $params = [
            'pageSize' => 100,
            'fields' => 'files(id,name,mimeType,modifiedTime,size,parents)',
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true'
        ];

        $q = ["'{$folderId}' in parents", "trashed=false"];
        $params['q'] = implode(' and ', $q);

        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query($params);

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
            throw new Exception('Failed to connect to Google Drive API');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Drive API error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        // フォルダとファイルを分類
        $result = ['folders' => [], 'files' => []];
        foreach ($data['files'] ?? [] as $item) {
            if ($item['mimeType'] === 'application/vnd.google-apps.folder') {
                $result['folders'][] = $item;
            } else {
                $result['files'][] = $item;
            }
        }

        // 名前順にソート
        usort($result['folders'], fn($a, $b) => strcmp($a['name'], $b['name']));
        usort($result['files'], fn($a, $b) => strcmp($a['name'], $b['name']));

        // キャッシュに保存
        $this->setCache($cacheKey, $result);

        return $result;
    }

    /**
     * ファイル/フォルダの詳細情報を取得
     */
    public function getFileInfo($fileId) {
        // キャッシュをチェック
        $cacheKey = 'fileinfo_' . $fileId;
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $accessToken = $this->getAccessToken();

        $fields = 'id,name,mimeType,modifiedTime,createdTime,size,parents,webViewLink,description,shortcutDetails';
        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?fields={$fields}&supportsAllDrives=true";

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
            throw new Exception('Failed to connect to Google Drive API');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Drive API error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        // キャッシュに保存
        $this->setCache($cacheKey, $data);

        return $data;
    }

    /**
     * ファイル一覧を取得
     */
    public function listFiles($query = null, $folderId = null) {
        $accessToken = $this->getAccessToken();

        $params = [
            'pageSize' => 100,
            'fields' => 'files(id,name,mimeType,modifiedTime,size)'
        ];

        // 検索クエリを構築
        $q = [];
        if ($folderId) {
            $q[] = "'{$folderId}' in parents";
        }
        if ($query) {
            $q[] = "name contains '{$query}'";
        }
        // CSVファイルのみ
        $q[] = "(mimeType='text/csv' or name contains '.csv')";
        $q[] = "trashed=false";

        if (!empty($q)) {
            $params['q'] = implode(' and ', $q);
        }

        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query($params);

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
            throw new Exception('Failed to connect to Google Drive API');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Drive API error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        return $data['files'] ?? [];
    }

    /**
     * 連携フォルダ設定を保存
     */
    public function saveSyncFolder($folderId, $folderName) {
        $configFile = __DIR__ . '/../config/loans-drive-config.json';
        $config = [];
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true) ?: [];
        }
        $config['sync_folder_id'] = $folderId;
        $config['sync_folder_name'] = $folderName;
        $config['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * 連携フォルダ設定を取得
     */
    public function getSyncFolder() {
        $configFile = __DIR__ . '/../config/loans-drive-config.json';
        if (!file_exists($configFile)) {
            return null;
        }
        $config = json_decode(file_get_contents($configFile), true);
        if (!empty($config['sync_folder_id'])) {
            return [
                'id' => $config['sync_folder_id'],
                'name' => $config['sync_folder_name'] ?? ''
            ];
        }
        return null;
    }

    /**
     * ファイルの内容を取得
     */
    public function getFileContent($fileId) {
        $accessToken = $this->getAccessToken();

        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media";

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
            throw new Exception('Failed to download file from Google Drive');
        }

        return $response;
    }

    /**
     * CSVファイルを読み込んでパース
     */
    public function parseCSV($fileId) {
        $content = $this->getFileContent($fileId);

        // BOMを除去
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = explode("\n", $content);
        $data = [];
        $headers = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // CSVパース（日本語対応）
            $row = str_getcsv($line);

            if ($headers === null) {
                $headers = $row;
            } else {
                $rowData = [];
                foreach ($headers as $i => $header) {
                    $rowData[$header] = $row[$i] ?? '';
                }
                $data[] = $rowData;
            }
        }

        return [
            'headers' => $headers,
            'data' => $data
        ];
    }

    /**
     * PDFからテキストを抽出（Google Docs変換経由）
     */
    public function extractTextFromPdf($fileId) {
        $accessToken = $this->getAccessToken();

        // PDFをGoogle Docsとしてコピー（OCR変換）
        $copyUrl = 'https://www.googleapis.com/drive/v3/files/' . $fileId . '/copy?supportsAllDrives=true';
        $copyData = json_encode([
            'mimeType' => 'application/vnd.google-apps.document',
            'name' => 'temp_ocr_' . time()
        ]);

        $options = [
            'http' => [
                'header'  => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
                'method'  => 'POST',
                'content' => $copyData,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($copyUrl, false, $context);

        if ($response === false) {
            throw new Exception('Failed to convert PDF');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('PDF conversion error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        $docId = $data['id'] ?? null;
        if (!$docId) {
            throw new Exception('Failed to get converted document ID');
        }

        // Google Docsからテキストをエクスポート
        $exportUrl = "https://www.googleapis.com/drive/v3/files/{$docId}/export?mimeType=text/plain";

        $exportOptions = [
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

        $exportContext = stream_context_create($exportOptions);
        $text = @file_get_contents($exportUrl, false, $exportContext);

        // 一時ファイルを削除
        $deleteUrl = "https://www.googleapis.com/drive/v3/files/{$docId}?supportsAllDrives=true";
        $deleteOptions = [
            'http' => [
                'header'  => "Authorization: Bearer {$accessToken}\r\n",
                'method'  => 'DELETE',
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];
        $deleteContext = stream_context_create($deleteOptions);
        @file_get_contents($deleteUrl, false, $deleteContext);

        return $text ?: '';
    }

    /**
     * テキストから金額を抽出
     */
    public function extractAmountsFromText($text) {
        $amounts = [];

        // テキスト前処理：カンマ+スペースをカンマのみに変換
        $text = preg_replace('/,\s+/', ',', $text);
        // スペース区切りの数字を結合（1 142 598 → 1142598）
        $text = preg_replace('/(\d)\s+(\d)/', '$1$2', $text);

        // 日本円の金額パターン（カンマ区切り、円付き等）
        $patterns = [
            '/([0-9]{1,3}(?:,[0-9]{3})+)円/u',  // 1,000,000円
            '/([0-9]+)円/u',                      // 100000円
            '/¥([0-9]{1,3}(?:,[0-9]{3})+)/u',   // ¥1,000,000
            '/¥([0-9]+)/u',                       // ¥100000
            // 単独の大きな数字（6桁以上）
            '/(?<![0-9])([0-9]{1,3}(?:,[0-9]{3})+)(?![0-9])/u',  // 1,142,598
            '/(?<![0-9,])([0-9]{6,})(?![0-9])/u',  // 1142598（6桁以上の数字）
            '/([0-9]{1,3}(?:,[0-9]{3})+)\s*(?:円|yen)/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $amount = intval(str_replace(',', '', $match));
                    if ($amount >= 1000) { // 1000円以上のみ
                        $amounts[] = $amount;
                    }
                }
            }
        }

        // 重複除去してソート
        $amounts = array_unique($amounts);
        rsort($amounts);

        return $amounts;
    }

    /**
     * トークンを削除（連携解除）
     */
    public function disconnect() {
        if (file_exists($this->tokenFile)) {
            unlink($this->tokenFile);
        }
    }
}
