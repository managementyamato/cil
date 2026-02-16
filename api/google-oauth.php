<?php
/**
 * Google OAuth 2.0 認証クライアント
 */

class GoogleOAuthClient {
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $allowedDomains;
    private $configFile;

    public function __construct() {
        $this->configFile = __DIR__ . '/../config/google-config.json';
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
        // Auto-detect redirect URI based on environment
        $isLocal = isset($_SERVER['HTTP_HOST']) && (
            str_contains($_SERVER['HTTP_HOST'], 'localhost') || str_contains($_SERVER['HTTP_HOST'], '127.0.0.1')
        );
        if ($isLocal) {
            $this->redirectUri = $config['redirect_uri'] ?? null;
        } else {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $this->redirectUri = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'yamato-mgt.com') . '/api/google-callback.php';
        }
        $this->allowedDomains = $config['allowed_domains'] ?? [];
    }

    /**
     * Google OAuth が設定されているかチェック
     */
    public function isConfigured() {
        return !empty($this->clientId) && !empty($this->clientSecret) && !empty($this->redirectUri);
    }

    /**
     * 認証URLを生成
     */
    public function getAuthUrl($includeDriveScope = false) {
        if (!$this->isConfigured()) {
            return null;
        }

        // 基本スコープ
        $scopes = ['openid', 'email', 'profile'];

        // Drive APIスコープを追加する場合
        if ($includeDriveScope) {
            $scopes[] = 'https://www.googleapis.com/auth/drive.readonly';
        }

        // CSRF対策: stateパラメータを生成しセッションに保存
        $stateValue = $includeDriveScope ? 'drive_connect' : 'login';
        $stateToken = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $stateToken;
        $_SESSION['oauth_state_purpose'] = $stateValue;
        $state = $stateValue . '_' . $stateToken;

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => $includeDriveScope ? 'offline' : 'online',  // Drive連携時はリフレッシュトークン取得
            'prompt' => $includeDriveScope ? 'consent' : 'select_account',
            'state' => $state
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * 認証コードをアクセストークンに交換
     */
    public function getAccessToken($code) {
        if (!$this->isConfigured()) {
            throw new Exception('Google OAuth is not configured');
        }

        $tokenUrl = 'https://oauth2.googleapis.com/token';

        $params = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
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
            throw new Exception('Failed to connect to Google OAuth server');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('OAuth error: ' . ($data['error_description'] ?? $data['error']));
        }

        return $data;
    }

    /**
     * アクセストークンからユーザー情報を取得
     */
    public function getUserInfo($accessToken) {
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';

        $options = [
            'http' => [
                'header'  => "Authorization: Bearer " . $accessToken . "\r\n",
                'method'  => 'GET',
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($userInfoUrl, false, $context);

        if ($response === false) {
            throw new Exception('Failed to connect to Google API');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Failed to get user info: ' . ($data['error']['message'] ?? $data['error']));
        }

        return $data;
    }

    /**
     * 設定情報を取得
     */
    public function getConfig() {
        return [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'allowed_domains' => $this->allowedDomains,
            'is_configured' => $this->isConfigured()
        ];
    }

    /**
     * 許可ドメインを取得
     */
    public function getAllowedDomains() {
        return $this->allowedDomains;
    }

    /**
     * メールアドレスのドメインが許可されているかチェック
     */
    public function isEmailDomainAllowed($email) {
        // ドメイン制限が設定されていない場合は全て許可
        if (empty($this->allowedDomains)) {
            return true;
        }

        $emailDomain = strtolower(substr(strrchr($email, '@'), 1));

        foreach ($this->allowedDomains as $allowedDomain) {
            if (strtolower($allowedDomain) === $emailDomain) {
                return true;
            }
        }

        return false;
    }
}
