<?php
/**
 * マネーフォワード クラウド請求書 API クライアント
 * GASのMfInvoiceApiライブラリをPHPに移植
 */

class MFApiClient {
    private $clientId;
    private $clientSecret;
    private $accessToken;
    private $refreshToken;
    private $apiEndpoint = 'https://invoice.moneyforward.com/api/v3';
    private $authEndpoint = 'https://api.biz.moneyforward.com/authorize';
    private $tokenEndpoint = 'https://api.biz.moneyforward.com/token';

    public function __construct() {
        $config = $this->loadConfig();
        $this->clientId = $config['client_id'] ?? null;
        $this->clientSecret = $config['client_secret'] ?? null;
        $this->accessToken = $config['access_token'] ?? null;
        $this->refreshToken = $config['refresh_token'] ?? null;
    }

    /**
     * 設定ファイルを読み込み
     */
    private function loadConfig() {
        $configFile = __DIR__ . '/mf-config.json';
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
        $configFile = __DIR__ . '/mf-config.json';
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
            'scope' => 'mfc/invoice/data.read mfc/invoice/data.write'
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

        $options = array(
            'http' => array(
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                             "Accept: application/json\r\n",
                'method'  => 'POST',
                'content' => http_build_query($params),
                'ignore_errors' => true
            )
        );

        $context = stream_context_create($options);
        $response = file_get_contents($this->tokenEndpoint, false, $context);

        if ($response === false) {
            throw new Exception('トークン取得エラー: HTTPリクエストが失敗しました');
        }

        // HTTPステータスコードを取得
        $status_line = $http_response_header[0];
        preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
        $httpCode = $match[1];

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

        $options = array(
            'http' => array(
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($params),
                'ignore_errors' => true
            )
        );

        $context = stream_context_create($options);
        $response = file_get_contents($this->tokenEndpoint, false, $context);

        if ($response === false) {
            throw new Exception('トークンのリフレッシュに失敗しました');
        }

        $status_line = $http_response_header[0];
        preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
        $httpCode = $match[1];

        if ($httpCode !== '200') {
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
    private function request($method, $endpoint, $data = null) {
        if (!$this->accessToken) {
            throw new Exception('アクセストークンがありません。先にOAuth認証を完了してください。');
        }

        $url = $this->apiEndpoint . $endpoint;

        $headers = "Authorization: Bearer " . $this->accessToken . "\r\n" .
                   "Content-Type: application/json\r\n" .
                   "Accept: application/json\r\n";

        $options = array(
            'http' => array(
                'header'  => $headers,
                'method'  => $method,
                'ignore_errors' => true
            )
        );

        if ($method === 'POST' || $method === 'PUT') {
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('APIリクエスト失敗: HTTPリクエストが失敗しました');
        }

        $status_line = $http_response_header[0];
        preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
        $httpCode = intval($match[1]);

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
        if ($from) $params['from'] = $from;
        if ($to) $params['to'] = $to;

        $endpoint = '/billings?' . http_build_query($params);
        return $this->request('GET', $endpoint);
    }

    /**
     * 請求書一覧を全ページ取得
     */
    public function getAllInvoices($from = null, $to = null) {
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
        $debugFile = __DIR__ . '/mf-api-debug.json';
        file_put_contents($debugFile, json_encode($debugLog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

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
     * 認証済みかどうか
     */
    public static function isConfigured() {
        $configFile = __DIR__ . '/mf-config.json';
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
        $configFile = __DIR__ . '/mf-config.json';
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
