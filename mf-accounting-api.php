<?php
/**
 * マネーフォワード クラウド会計 API クライアント
 * OAuth2認証方式（MF請求書と同じ認証基盤を使用）
 */

class MFAccountingApiClient {
    private $clientId;
    private $clientSecret;
    private $accessToken;
    private $refreshToken;
    private $apiEndpoint = 'https://accounting.moneyforward.com/api/external/v1';
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
        $configFile = __DIR__ . '/mf-accounting-config.json';
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
        $configFile = __DIR__ . '/mf-accounting-config.json';
        $data['updated_at'] = date('Y-m-d H:i:s');
        return file_put_contents($configFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * OAuth認証URLを生成
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
            'scope' => 'office.accounting.read office.accounting.write'
        );

        return $this->authEndpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * 認証コードからアクセストークンを取得
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
     * 事業所一覧を取得
     */
    public function getOffices() {
        return $this->request('GET', '/offices');
    }

    /**
     * 取引先一覧を取得
     */
    public function getPartners($page = 1, $perPage = 100) {
        $params = array(
            'page' => $page,
            'per_page' => $perPage
        );

        $endpoint = '/partners?' . http_build_query($params);
        return $this->request('GET', $endpoint);
    }

    /**
     * 勘定科目一覧を取得
     */
    public function getAccounts($officeId) {
        $endpoint = '/offices/' . $officeId . '/accounts';
        return $this->request('GET', $endpoint);
    }

    /**
     * 仕訳一覧を取得
     */
    public function getJournals($officeId, $from = null, $to = null, $page = 1, $perPage = 100) {
        $params = array(
            'page' => $page,
            'per_page' => $perPage
        );

        if ($from) $params['from'] = $from;
        if ($to) $params['to'] = $to;

        $endpoint = '/offices/' . $officeId . '/journals?' . http_build_query($params);
        return $this->request('GET', $endpoint);
    }

    /**
     * 仕訳を作成
     */
    public function createJournal($officeId, $data) {
        $endpoint = '/offices/' . $officeId . '/journals';
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * 認証済みかどうか
     */
    public static function isConfigured() {
        $configFile = __DIR__ . '/mf-accounting-config.json';
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
        $configFile = __DIR__ . '/mf-accounting-config.json';
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
