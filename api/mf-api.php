<?php
/**
 * マネーフォワード クラウド請求書 API クライアント
 * GASのMfInvoiceApiライブラリをPHPに移植
 */

class MFApiClient {
    /**
     * SSL設定を取得
     * 環境変数またはCA証明書ファイルが存在すれば検証を有効化
     * @return array SSL stream context options
     */
    private static function getSslOptions() {
        // 環境変数でSSL検証を明示的に無効化している場合（開発環境用）
        if (function_exists('env') && env('SSL_VERIFY_PEER', true) === false) {
            return [
                'verify_peer' => false,
                'verify_peer_name' => false
            ];
        }

        // CA証明書のパスを探索
        $caBundlePaths = [
            function_exists('env') ? env('SSL_CA_BUNDLE') : null,  // 環境変数で指定
            '/etc/ssl/certs/ca-certificates.crt',           // Debian/Ubuntu
            '/etc/pki/tls/certs/ca-bundle.crt',             // CentOS/RHEL
            '/etc/ssl/ca-bundle.pem',                       // OpenSUSE
            '/usr/local/share/certs/ca-root-nss.crt',       // FreeBSD
            'C:/xampp/apache/bin/curl-ca-bundle.crt',       // XAMPP Windows
            'C:/xampp/php/extras/ssl/cacert.pem',           // XAMPP Windows alt
            dirname(__DIR__) . '/config/cacert.pem',        // プロジェクト内配置
        ];

        foreach ($caBundlePaths as $path) {
            if ($path && file_exists($path)) {
                return [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'cafile' => $path
                ];
            }
        }

        // CA証明書が見つからない場合、PHP組み込みのCA検証を試行
        // ※ php.iniのopenssl.cafileが設定されていれば動作する
        if (ini_get('openssl.cafile') || ini_get('curl.cainfo')) {
            return [
                'verify_peer' => true,
                'verify_peer_name' => true
            ];
        }

        // フォールバック: 警告ログを出力して検証無効化（後方互換性のため）
        // 本番環境ではCA証明書を配置することを強く推奨
        if (function_exists('logWarning')) {
            logWarning('SSL certificate verification disabled: CA bundle not found. Please configure SSL_CA_BUNDLE or place cacert.pem in config/');
        }
        return [
            'verify_peer' => false,
            'verify_peer_name' => false
        ];
    }

    private $clientId;
    private $clientSecret;
    private $accessToken;
    private $refreshToken;
    private $apiEndpoint = 'https://invoice.moneyforward.com/api/v3';
    private $authEndpoint = 'https://api.biz.moneyforward.com/authorize';
    private $tokenEndpoint = 'https://api.biz.moneyforward.com/token';

    /** キャッシュディレクトリ */
    private static $cacheDir = null;
    /** デフォルトキャッシュ有効期間（秒） 1時間 */
    const CACHE_TTL_DEFAULT = 3600;

    public function __construct() {
        $config = $this->loadConfig();
        $this->clientId = $config['client_id'] ?? null;
        $this->clientSecret = $config['client_secret'] ?? null;
        $this->accessToken = $config['access_token'] ?? null;
        $this->refreshToken = $config['refresh_token'] ?? null;
    }

    // ========================================
    // キャッシュ機構
    // ========================================

    /**
     * キャッシュディレクトリを取得（なければ作成）
     */
    private static function getCacheDir() {
        if (self::$cacheDir === null) {
            self::$cacheDir = dirname(__DIR__) . '/data/cache';
        }
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        return self::$cacheDir;
    }

    /**
     * キャッシュキーを生成
     * @param string $type データ種別（invoices, partners等）
     * @param array $params パラメータ
     * @return string キャッシュファイルパス
     */
    private static function getCacheFilePath($type, $params = []) {
        $key = $type . '_' . md5(json_encode($params));
        return self::getCacheDir() . '/' . $key . '.json';
    }

    /**
     * キャッシュからデータを取得
     * @param string $type データ種別
     * @param array $params パラメータ
     * @param int $ttl 有効期間（秒）
     * @return array|null キャッシュデータ（期限切れ・未存在はnull）
     */
    public static function getCache($type, $params = [], $ttl = self::CACHE_TTL_DEFAULT) {
        $file = self::getCacheFilePath($type, $params);
        if (!file_exists($file)) {
            return null;
        }

        $mtime = filemtime($file);
        if ((time() - $mtime) > $ttl) {
            // キャッシュ期限切れ
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if ($data === null) {
            return null;
        }

        return $data;
    }

    /**
     * キャッシュにデータを保存
     * @param string $type データ種別
     * @param array $params パラメータ
     * @param mixed $data 保存するデータ
     */
    public static function setCache($type, $params, $data) {
        $file = self::getCacheFilePath($type, $params);
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * 特定のキャッシュを削除
     * @param string $type データ種別
     * @param array $params パラメータ
     */
    public static function clearCache($type = null, $params = null) {
        if ($type !== null && $params !== null) {
            $file = self::getCacheFilePath($type, $params);
            if (file_exists($file)) {
                unlink($file);
            }
            return;
        }

        // 全キャッシュ削除
        $dir = self::getCacheDir();
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') as $file) {
                unlink($file);
            }
        }
    }

    /**
     * キャッシュ情報を取得（デバッグ・UI表示用）
     * @param string $type データ種別
     * @param array $params パラメータ
     * @return array|null ['cached_at' => タイムスタンプ, 'age_seconds' => 経過秒数, 'ttl' => 有効期間]
     */
    public static function getCacheInfo($type, $params = [], $ttl = self::CACHE_TTL_DEFAULT) {
        $file = self::getCacheFilePath($type, $params);
        if (!file_exists($file)) {
            return null;
        }

        $mtime = filemtime($file);
        $age = time() - $mtime;
        return [
            'cached_at' => date('Y-m-d H:i:s', $mtime),
            'age_seconds' => $age,
            'ttl' => $ttl,
            'expired' => ($age > $ttl),
            'remaining_seconds' => max(0, $ttl - $age)
        ];
    }

    /**
     * 設定ファイルを読み込み
     * 環境変数を優先し、なければファイルから読み込む（後方互換性）
     */
    private function loadConfig() {
        $config = [];

        // 環境変数から読み込み（優先）
        $clientId = getenv('MF_CLIENT_ID');
        $clientSecret = getenv('MF_CLIENT_SECRET');
        $accessToken = getenv('MF_ACCESS_TOKEN');
        $refreshToken = getenv('MF_REFRESH_TOKEN');

        if ($clientId || $clientSecret || $accessToken || $refreshToken) {
            $config['client_id'] = $clientId ?: '';
            $config['client_secret'] = $clientSecret ?: '';
            $config['access_token'] = $accessToken ?: '';
            $config['refresh_token'] = $refreshToken ?: '';
            return $config;
        }

        // 環境変数がない場合はファイルから読み込み（後方互換性）
        $configFile = __DIR__ . '/../config/mf-config.json';
        if (file_exists($configFile)) {
            $json = file_get_contents($configFile);
            return json_decode($json, true) ?: array();
        }

        return array();
    }

    /**
     * 設定ファイルを保存
     */
    private function saveConfig($data) {
        $configFile = __DIR__ . '/../config/mf-config.json';
        $data['updated_at'] = date('Y-m-d H:i:s');
        return file_put_contents($configFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * OAuth認証URLを生成
     * GASのshowMfApiAuthDialog相当
     */
    public function getAuthorizationUrl($redirectUri, $state = null) {
        if (!$state) {
            $state = bin2hex(random_bytes(16));
        }

        $params = array(
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
            // 請求書 + 会計 + 管理の全スコープ
            'scope' => 'mfc/invoice/data.read mfc/invoice/data.write mfc/accounting/journals.read mfc/accounting/accounts.read mfc/admin/tenant.read'
        );

        return $this->authEndpoint . '?' . http_build_query($params);
    }

    /**
     * 認証コードからアクセストークンを取得
     * GASのmfCallback相当
     */
    public function handleCallback($code, $redirectUri) {
        $params = array(
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        );

        // file_get_contentsを使用
        $options = array(
            'http' => array(
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                             "Accept: application/json\r\n",
                'method'  => 'POST',
                'content' => http_build_query($params),
                'ignore_errors' => true
            ),
            'ssl' => self::getSslOptions()
        );

        $context = stream_context_create($options);
        $response = @file_get_contents($this->tokenEndpoint, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new Exception('トークン取得エラー: HTTPリクエストが失敗しました - ' . ($error['message'] ?? '不明なエラー'));
        }

        // HTTPステータスコードを取得
        if (isset($http_response_header[0])) {
            preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
            $httpCode = isset($match[1]) ? intval($match[1]) : 0;
        } else {
            $httpCode = 0;
        }

        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            throw new Exception('トークン取得失敗 (HTTP ' . $httpCode . '): ' . json_encode($errorData));
        }

        $tokenData = json_decode($response, true);

        // トークンを保存
        $config = $this->loadConfig();
        $config['access_token'] = $tokenData['access_token'];
        $config['refresh_token'] = $tokenData['refresh_token'] ?? null;
        $config['expires_in'] = $tokenData['expires_in'] ?? 3600;
        $config['token_obtained_at'] = time();
        // 取得したスコープを保存（デバッグ用）
        $config['granted_scope'] = $tokenData['scope'] ?? null;
        $this->saveConfig($config);

        $this->accessToken = $tokenData['access_token'];
        $this->refreshToken = $tokenData['refresh_token'] ?? null;

        return $tokenData;
    }

    /**
     * アクセストークンをリフレッシュ
     */
    public function refreshAccessToken() {
        if (!$this->refreshToken) {
            throw new Exception('リフレッシュトークンがありません');
        }

        $params = array(
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token'
        );

        // file_get_contentsを使用
        $options = array(
            'http' => array(
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($params),
                'ignore_errors' => true,
                'timeout' => 30  // 30秒タイムアウト
            ),
            'ssl' => self::getSslOptions()
        );

        $context = stream_context_create($options);
        $response = @file_get_contents($this->tokenEndpoint, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new Exception('トークンのリフレッシュに失敗しました - ' . ($error['message'] ?? '不明なエラー'));
        }

        if (isset($http_response_header[0])) {
            preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
            $httpCode = isset($match[1]) ? intval($match[1]) : 0;
        } else {
            $httpCode = 0;
        }

        if ($httpCode !== 200 && $httpCode !== 0) {
            throw new Exception('トークンのリフレッシュに失敗しました (HTTP ' . $httpCode . ')');
        }

        $tokenData = json_decode($response, true);

        // 新しいトークンを保存
        $config = $this->loadConfig();
        $config['access_token'] = $tokenData['access_token'];
        $config['refresh_token'] = $tokenData['refresh_token'] ?? $this->refreshToken;
        $config['expires_in'] = $tokenData['expires_in'] ?? 3600;
        $config['token_obtained_at'] = time();
        $this->saveConfig($config);

        $this->accessToken = $tokenData['access_token'];
        $this->refreshToken = $tokenData['refresh_token'] ?? $this->refreshToken;

        return $tokenData;
    }

    /**
     * APIリクエストを実行
     */
    public function request($method, $endpoint, $data = null) {
        if (!$this->accessToken) {
            throw new Exception('アクセストークンがありません。先にOAuth認証を完了してください。');
        }

        $url = $this->apiEndpoint . $endpoint;

        // file_get_contentsを使用
        $headers = "Authorization: Bearer " . $this->accessToken . "\r\n" .
                   "Content-Type: application/json\r\n" .
                   "Accept: application/json\r\n";

        $options = array(
            'http' => array(
                'header'  => $headers,
                'method'  => $method,
                'ignore_errors' => true,
                'timeout' => 30  // 30秒タイムアウト
            ),
            'ssl' => self::getSslOptions()
        );

        if ($method === 'POST' || $method === 'PUT') {
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            $errorMsg = $error['message'] ?? '不明なエラー';

            // タイムアウトの場合は分かりやすいメッセージを返す
            if (strpos($errorMsg, 'timed out') !== false || strpos($errorMsg, 'timeout') !== false) {
                throw new Exception('MF APIがタイムアウトしました（30秒以上応答なし）。MFクラウドが混雑している可能性があります。');
            }

            throw new Exception('APIリクエスト失敗: HTTPリクエストが失敗しました - ' . $errorMsg);
        }

        if (isset($http_response_header[0])) {
            preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
            $httpCode = isset($match[1]) ? intval($match[1]) : 0;
        } else {
            $httpCode = 0;
        }

        // 401エラーの場合、トークンをリフレッシュして再試行
        if ($httpCode === 401 && $this->refreshToken) {
            $this->refreshAccessToken();
            return $this->request($method, $endpoint, $data);
        }

        if ($httpCode >= 400) {
            throw new Exception('APIリクエスト失敗 (HTTP ' . $httpCode . '): ' . $response);
        }

        return json_decode($response, true);
    }

    /**
     * 請求書一覧を取得
     */
    public function getInvoices($from = null, $to = null) {
        $params = array();
        if ($from) {
            $params['from'] = $from;
            $params['range_key'] = 'billing_date'; // 請求日で範囲検索
        }
        if ($to) {
            $params['to'] = $to;
            if (!isset($params['range_key'])) {
                $params['range_key'] = 'billing_date';
            }
        }

        $endpoint = '/billings?' . http_build_query($params);
        return $this->request('GET', $endpoint);
    }

    /**
     * 請求書詳細を取得
     *
     * @param string $invoiceId 請求書ID
     * @return array 請求書詳細
     */
    public function getInvoiceDetail($invoiceId) {
        $endpoint = '/billings/' . $invoiceId;
        return $this->request('GET', $endpoint);
    }

    /**
     * 請求書一覧を全ページ取得（キャッシュ対応）
     * @param string|null $from 開始日
     * @param string|null $to 終了日
     * @param bool $forceRefresh trueの場合キャッシュを無視して再取得
     * @return array 請求書一覧
     */
    public function getAllInvoices($from = null, $to = null, $forceRefresh = false) {
        $cacheParams = ['from' => $from, 'to' => $to];

        // キャッシュチェック（強制リフレッシュでない場合）
        if (!$forceRefresh) {
            $cached = self::getCache('invoices', $cacheParams);
            if ($cached !== null) {
                return $cached;
            }
        }

        $allInvoices = array();
        $page = 1;
        $perPage = 100;
        $debugLog = array();

        do {
            $params = array('page' => $page, 'per_page' => $perPage);

            // fromとtoを指定する場合、range_keyも必須
            if ($from && $to) {
                $params['from'] = $from;
                $params['to'] = $to;
                $params['range_key'] = 'billing_date'; // 請求日で範囲検索
            }

            $endpoint = '/billings?' . http_build_query($params);
            $response = $this->request('GET', $endpoint);

            // デバッグ用にレスポンスを記録
            $debugLog[] = array(
                'page' => $page,
                'endpoint' => $endpoint,
                'full_url' => $this->apiEndpoint . $endpoint,
                'response_keys' => array_keys($response),
                'response' => $response
            );

            // レスポンス構造を確認（'data'キーまたは'billings'キー）
            $invoiceData = null;
            if (isset($response['data']) && is_array($response['data'])) {
                $invoiceData = $response['data'];
            } elseif (isset($response['billings']) && is_array($response['billings'])) {
                $invoiceData = $response['billings'];
            }

            if ($invoiceData !== null) {
                $allInvoices = array_merge($allInvoices, $invoiceData);
                $hasMore = count($invoiceData) === $perPage;
            } else {
                $hasMore = false;
            }

            $page++;
        } while ($hasMore);

        // デバッグログをファイルに保存
        $debugFile = dirname(__DIR__) . '/mf-api-debug.json';
        file_put_contents($debugFile, json_encode($debugLog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // キャッシュに保存
        self::setCache('invoices', $cacheParams, $allInvoices);

        return $allInvoices;
    }

    /**
     * 見積書一覧を取得
     */
    public function getQuotes($from = null, $to = null) {
        $params = array();
        if ($from) $params['from'] = $from;
        if ($to) $params['to'] = $to;

        $endpoint = '/quotes?' . http_build_query($params);
        return $this->request('GET', $endpoint);
    }

    /**
     * 請求書を作成
     */
    public function createInvoice($data) {
        return $this->request('POST', '/billings', $data);
    }

    /**
     * 請求書のタグ一覧を取得
     * @param string $invoiceId 請求書ID
     * @return array タグ一覧
     */
    public function getInvoiceTags($invoiceId) {
        $detail = $this->getInvoiceDetail($invoiceId);
        return $detail['tags'] ?? [];
    }

    /**
     * 全請求書をタグ付きで取得
     * @param string|null $from 開始日
     * @param string|null $to 終了日
     * @return array 請求書一覧（タグ含む）
     */
    public function getAllInvoicesWithTags($from = null, $to = null) {
        $invoices = $this->getAllInvoices($from, $to);

        // 各請求書の詳細を取得してタグ情報を追加
        foreach ($invoices as &$invoice) {
            try {
                $detail = $this->getInvoiceDetail($invoice['id']);
                $invoice['tags'] = $detail['tags'] ?? [];
            } catch (Exception $e) {
                $invoice['tags'] = [];
            }
        }

        return $invoices;
    }

    /**
     * PJ番号（タグ）で請求書を検索
     * @param string $pjNumber PJ番号（例: P849）
     * @param string|null $from 開始日
     * @param string|null $to 終了日
     * @return array|null 一致した請求書
     */
    public function findInvoiceByPjTag($pjNumber, $from = null, $to = null) {
        $invoices = $this->getAllInvoices($from, $to);

        // PJ番号を正規化（Pで始まる数字）
        $normalizedPj = strtoupper(trim($pjNumber));
        if (!preg_match('/^P\d+$/', $normalizedPj)) {
            return null;
        }

        foreach ($invoices as $invoice) {
            try {
                $detail = $this->getInvoiceDetail($invoice['id']);
                $tags = $detail['tags'] ?? [];

                foreach ($tags as $tag) {
                    $tagName = strtoupper(trim($tag['name'] ?? ''));
                    if ($tagName === $normalizedPj) {
                        return array_merge($invoice, ['tags' => $tags]);
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * タグ名一覧でグループ化した請求書マップを取得
     * @param string|null $from 開始日
     * @param string|null $to 終了日
     * @return array ['P123' => [invoice1, invoice2], ...]
     */
    public function getInvoicesGroupedByPjTag($from = null, $to = null) {
        $invoices = $this->getAllInvoices($from, $to);
        $grouped = [];

        foreach ($invoices as $invoice) {
            try {
                $detail = $this->getInvoiceDetail($invoice['id']);
                $tags = $detail['tags'] ?? [];

                foreach ($tags as $tag) {
                    $tagName = strtoupper(trim($tag['name'] ?? ''));
                    // Pで始まる数字のタグのみ対象
                    if (preg_match('/^P\d+$/', $tagName)) {
                        if (!isset($grouped[$tagName])) {
                            $grouped[$tagName] = [];
                        }
                        $invoiceWithTags = array_merge($invoice, ['tags' => $tags]);
                        $grouped[$tagName][] = $invoiceWithTags;
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $grouped;
    }

    /**
     * 取引先一覧を取得
     * @return array 取引先一覧
     */
    public function getPartners() {
        $allPartners = array();
        $page = 1;
        $perPage = 100;

        do {
            $params = array('page' => $page, 'per_page' => $perPage);
            $endpoint = '/partners?' . http_build_query($params);
            $response = $this->request('GET', $endpoint);

            // レスポンス構造を確認
            $partnerData = null;
            if (isset($response['data']) && is_array($response['data'])) {
                $partnerData = $response['data'];
            } elseif (isset($response['partners']) && is_array($response['partners'])) {
                $partnerData = $response['partners'];
            }

            if ($partnerData !== null) {
                $allPartners = array_merge($allPartners, $partnerData);
                $hasMore = count($partnerData) === $perPage;
            } else {
                $hasMore = false;
            }

            $page++;
        } while ($hasMore);

        return $allPartners;
    }

    /**
     * 取引先詳細を取得（部門情報含む）
     * @param string $partnerId 取引先ID
     * @return array 取引先詳細
     */
    public function getPartnerDetail($partnerId) {
        $endpoint = '/partners/' . $partnerId;
        return $this->request('GET', $endpoint);
    }

    /**
     * 取引先の部門一覧を取得
     * @param string $partnerId 取引先ID
     * @return array 部門一覧
     */
    public function getPartnerDepartments($partnerId) {
        $endpoint = '/partners/' . $partnerId . '/departments';
        $response = $this->request('GET', $endpoint);

        // レスポンス構造を確認
        if (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        } elseif (isset($response['departments']) && is_array($response['departments'])) {
            return $response['departments'];
        }

        return [];
    }

    /**
     * 取引先一覧を部門情報付きで取得
     * @return array 取引先一覧（部門情報含む）
     */
    public function getPartnersWithDepartments() {
        $partners = $this->getPartners();

        // 各取引先の部門情報を取得
        foreach ($partners as &$partner) {
            try {
                $partnerId = $partner['id'] ?? null;
                if ($partnerId) {
                    $partner['departments'] = $this->getPartnerDepartments($partnerId);
                } else {
                    $partner['departments'] = [];
                }
            } catch (Exception $e) {
                $partner['departments'] = [];
            }
        }

        return $partners;
    }

    /**
     * 認証済みかどうか
     */
    public static function isConfigured() {
        $configFile = __DIR__ . '/../config/mf-config.json';
        if (!file_exists($configFile)) {
            return false;
        }
        $config = json_decode(file_get_contents($configFile), true);
        return !empty($config['access_token']);
    }

    /**
     * Client ID/Secretを保存
     */
    public static function saveCredentials($clientId, $clientSecret) {
        $configFile = __DIR__ . '/../config/mf-config.json';
        $config = array();
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true) ?: array();
        }

        $config['client_id'] = $clientId;
        $config['client_secret'] = $clientSecret;
        $config['updated_at'] = date('Y-m-d H:i:s');

        return file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
