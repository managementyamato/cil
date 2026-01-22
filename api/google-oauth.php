<?php
/**
 * Google OAuth 2.0 認証クライアント
 */

class GoogleOAuthClient {
    private $clientId;
    private $clientSecret;
    private $redirectUri;
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
        $this->redirectUri = $config['redirect_uri'] ?? null;
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
    public function getAuthUrl() {
        if (!$this->isConfigured()) {
            return null;
        }

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account'
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

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to get access token: ' . $response);
        }

        return json_decode($response, true);
    }

    /**
     * アクセストークンからユーザー情報を取得
     */
    public function getUserInfo($accessToken) {
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to get user info: ' . $response);
        }

        return json_decode($response, true);
    }

    /**
     * 設定情報を取得
     */
    public function getConfig() {
        return [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'is_configured' => $this->isConfigured()
        ];
    }
}
