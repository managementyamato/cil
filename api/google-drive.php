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
    private $cacheTTL = 3600; // 1時間キャッシュ（セッション）
    private $cachePrefix = 'gdrive_cache_';

    // ファイルキャッシュ設定
    private $fileCacheDir;
    private $fileCacheTTL = 300; // 5分（ファイル一覧用）

    // API通信設定
    private $timeout = 10; // 秒（通常API用）
    private $pdfTimeout = 30; // 秒（PDF処理用、OCRに時間がかかるため）

    public function __construct() {
        $this->configFile = __DIR__ . '/../config/google-config.json';
        $this->tokenFile = __DIR__ . '/../config/google-drive-token.json';
        $this->fileCacheDir = __DIR__ . '/../cache/drive';
        $this->loadConfig();

        // キャッシュディレクトリ作成
        if (!is_dir($this->fileCacheDir)) {
            @mkdir($this->fileCacheDir, 0755, true);
        }

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
            // ファイルキャッシュも削除
            $filePath = $this->fileCacheDir . '/' . md5($key) . '.json';
            @unlink($filePath);
        } else {
            // 全キャッシュをクリア
            foreach ($_SESSION as $sessionKey => $value) {
                if (strpos($sessionKey, $this->cachePrefix) === 0) {
                    unset($_SESSION[$sessionKey]);
                }
            }
            // ファイルキャッシュも全削除
            if (is_dir($this->fileCacheDir)) {
                foreach (glob($this->fileCacheDir . '/*.json') as $file) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * ファイルキャッシュからデータを取得
     */
    private function getFileCache($key, $ttl = null) {
        $ttl = $ttl ?? $this->fileCacheTTL;
        $filePath = $this->fileCacheDir . '/' . md5($key) . '.json';

        if (file_exists($filePath)) {
            $mtime = filemtime($filePath);
            if (time() - $mtime < $ttl) {
                $data = @json_decode(file_get_contents($filePath), true);
                if ($data !== null) {
                    return $data;
                }
            }
        }
        return null;
    }

    /**
     * ファイルキャッシュにデータを保存
     */
    private function setFileCache($key, $data) {
        $filePath = $this->fileCacheDir . '/' . md5($key) . '.json';
        @file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
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
                'ignore_errors' => true,
                'timeout' => $this->timeout
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($tokenUrl, false, $context);

        if ($response === false) {
            throw new Exception('Failed to refresh token (timeout or connection error)');
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
        // ファイルキャッシュをチェック（優先）
        $cacheKey = 'folders_' . ($parentId ?? 'root');
        $fileCached = $this->getFileCache($cacheKey);
        if ($fileCached !== null) {
            return $fileCached;
        }

        // セッションキャッシュをチェック
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            $this->setFileCache($cacheKey, $cached);
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
                'ignore_errors' => true,
                'timeout' => $this->timeout
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('Failed to connect to Google Drive API (timeout)');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Drive API error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        $result = $data['files'] ?? [];

        // キャッシュに保存（セッションとファイル両方）
        $this->setCache($cacheKey, $result);
        $this->setFileCache($cacheKey, $result);

        return $result;
    }

    /**
     * フォルダ内の全ファイル/サブフォルダを取得
     */
    public function listFolderContents($folderId) {
        // ファイルキャッシュをチェック（優先）
        $cacheKey = 'contents_' . $folderId;
        $fileCached = $this->getFileCache($cacheKey);
        if ($fileCached !== null) {
            return $fileCached;
        }

        // セッションキャッシュをチェック
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            // ファイルキャッシュにも保存
            $this->setFileCache($cacheKey, $cached);
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
                'ignore_errors' => true,
                'timeout' => $this->timeout
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('Failed to connect to Google Drive API (timeout)');
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

        // キャッシュに保存（セッションとファイル両方）
        $this->setCache($cacheKey, $result);
        $this->setFileCache($cacheKey, $result);

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
                'ignore_errors' => true,
                'timeout' => $this->timeout
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('Failed to connect to Google Drive API (timeout)');
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
                'ignore_errors' => true,
                'timeout' => $this->timeout
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('Failed to connect to Google Drive API (timeout)');
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
                'ignore_errors' => true,
                'timeout' => $this->timeout
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('Failed to download file from Google Drive (timeout)');
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
     * @param string $fileId PDFファイルのID
     * @param bool $returnTiming trueの場合、タイミング情報も返す
     * @param bool $useCache キャッシュを使用するか（デフォルト: true）
     * @return string|array テキスト、または['text' => ..., 'timing' => ...]
     */
    public function extractTextFromPdf($fileId, $returnTiming = false, $useCache = true) {
        $timing = ['start' => microtime(true)];

        // ファイルキャッシュをチェック
        $cacheDir = __DIR__ . '/../cache/pdf';
        $cacheFile = $cacheDir . '/' . md5($fileId) . '.json';

        if ($useCache && file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            // ファイルの更新日時をチェック（キャッシュが有効か）
            $fileInfo = $this->getFileInfo($fileId);
            $fileModified = $fileInfo['modifiedTime'] ?? null;

            if ($cached && isset($cached['modified_time']) && $cached['modified_time'] === $fileModified) {
                $timing['end'] = microtime(true);
                $this->lastPdfTiming = [
                    'total_ms' => round(($timing['end'] - $timing['start']) * 1000, 2),
                    'cached' => true
                ];

                if ($returnTiming) {
                    return [
                        'text' => $cached['text'],
                        'timing' => $this->lastPdfTiming
                    ];
                }
                return $cached['text'];
            }
        }

        $timing['token_start'] = microtime(true);
        $accessToken = $this->getAccessToken();
        $timing['token_end'] = microtime(true);

        // PDFをGoogle Docsとしてコピー（OCR変換）
        $timing['copy_start'] = microtime(true);
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
                'ignore_errors' => true,
                'timeout' => $this->pdfTimeout
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($copyUrl, false, $context);
        $timing['copy_end'] = microtime(true);

        if ($response === false) {
            throw new Exception('Failed to convert PDF (timeout)');
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
        $timing['export_start'] = microtime(true);
        $exportUrl = "https://www.googleapis.com/drive/v3/files/{$docId}/export?mimeType=text/plain";

        $exportOptions = [
            'http' => [
                'header'  => "Authorization: Bearer {$accessToken}\r\n",
                'method'  => 'GET',
                'ignore_errors' => true,
                'timeout' => $this->pdfTimeout
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $exportContext = stream_context_create($exportOptions);
        $text = @file_get_contents($exportUrl, false, $exportContext);
        $timing['export_end'] = microtime(true);

        // 一時ファイルを削除
        $timing['delete_start'] = microtime(true);
        $deleteUrl = "https://www.googleapis.com/drive/v3/files/{$docId}?supportsAllDrives=true";
        $deleteOptions = [
            'http' => [
                'header'  => "Authorization: Bearer {$accessToken}\r\n",
                'method'  => 'DELETE',
                'ignore_errors' => true,
                'timeout' => $this->timeout
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];
        $deleteContext = stream_context_create($deleteOptions);
        @file_get_contents($deleteUrl, false, $deleteContext);
        $timing['delete_end'] = microtime(true);

        $timing['end'] = microtime(true);

        // キャッシュに保存
        if ($useCache && !empty($text)) {
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            $fileInfo = $this->getFileInfo($fileId);
            $cacheData = [
                'file_id' => $fileId,
                'text' => $text,
                'amounts' => $this->extractAmountsFromText($text),
                'modified_time' => $fileInfo['modifiedTime'] ?? null,
                'cached_at' => time()
            ];
            file_put_contents($cacheFile, json_encode($cacheData, JSON_UNESCAPED_UNICODE));
        }

        // タイミング情報をログに保存
        $this->lastPdfTiming = [
            'total_ms' => round(($timing['end'] - $timing['start']) * 1000, 2),
            'token_ms' => round(($timing['token_end'] - $timing['token_start']) * 1000, 2),
            'copy_ocr_ms' => round(($timing['copy_end'] - $timing['copy_start']) * 1000, 2),
            'export_ms' => round(($timing['export_end'] - $timing['export_start']) * 1000, 2),
            'delete_ms' => round(($timing['delete_end'] - $timing['delete_start']) * 1000, 2),
            'cached' => false
        ];

        if ($returnTiming) {
            return [
                'text' => $text ?: '',
                'timing' => $this->lastPdfTiming
            ];
        }

        return $text ?: '';
    }

    /**
     * 最後のPDF処理のタイミング情報を取得
     */
    public function getLastPdfTiming() {
        return $this->lastPdfTiming ?? null;
    }

    private $lastPdfTiming = null;

    /**
     * テキストから金額を抽出
     */
    public function extractAmountsFromText($text) {
        $amounts = [];

        // テキスト前処理：カンマ+スペースをカンマのみに変換（カンマ区切り数字内のみ）
        $text = preg_replace('/,\s+(?=\d{3})/', ',', $text);
        // 注意：スペース区切りの数字連結は削除（巨大数値の原因になるため）

        // 日本円の金額パターン（カンマ区切り優先）
        $patterns = [
            // カンマ区切りの金額（最優先）
            '/([0-9]{1,3}(?:,[0-9]{3})+)円/u',     // 1,000,000円
            '/¥\s*([0-9]{1,3}(?:,[0-9]{3})+)/u',  // ¥1,000,000
            '/￥\s*([0-9]{1,3}(?:,[0-9]{3})+)/u', // ￥1,000,000
            '/(?<![0-9])([0-9]{1,3}(?:,[0-9]{3})+)(?![0-9,])/u',  // 単独のカンマ区切り数字
            // 円付きの数字（カンマなし、4-9桁）
            '/([0-9]{4,9})円/u',                   // 10000円〜999999999円
            // ¥付きの数字（カンマなし、4-9桁）
            '/¥\s*([0-9]{4,9})(?![0-9])/u',       // ¥10000
            '/￥\s*([0-9]{4,9})(?![0-9])/u',      // ￥10000
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $amount = intval(str_replace(',', '', $match));
                    // 1000円以上、10億円未満の現実的な金額のみ
                    if ($amount >= 1000 && $amount < 1000000000) {
                        $amounts[] = $amount;
                    }
                }
            }
        }

        // 重複除去してソート（降順）
        $amounts = array_unique($amounts);
        rsort($amounts);

        return $amounts;
    }

    /**
     * ファイル名を変更
     * @param string $fileId ファイルID
     * @param string $newName 新しいファイル名
     * @return array 更新後のファイル情報
     */
    public function renameFile($fileId, $newName) {
        $accessToken = $this->getAccessToken();

        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?supportsAllDrives=true";
        $body = json_encode(['name' => $newName]);

        $options = [
            'http' => [
                'header'  => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
                'method'  => 'PATCH',
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => $this->timeout
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('Failed to rename file (timeout)');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Rename error: ' . ($data['error']['message'] ?? json_encode($data['error'])));
        }

        // キャッシュをクリア
        $this->clearCache('fileinfo_' . $fileId);

        return $data;
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
