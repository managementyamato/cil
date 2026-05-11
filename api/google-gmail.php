<?php
/**
 * Google Gmail API クライアント
 * メール送信機能を提供
 */

class GoogleGmailClient {
    private $configFile;
    private $tokenFile;
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    private $timeout = 10;

    public function __construct() {
        $this->configFile = __DIR__ . '/../config/google-config.json';
        $this->tokenFile = __DIR__ . '/../config/google-gmail-token.json';
        $this->loadConfig();
    }

    private function loadConfig() {
        if (!file_exists($this->configFile)) {
            return;
        }
        $config = json_decode(file_get_contents($this->configFile), true);
        $this->clientId = $config['client_id'] ?? null;
        $this->clientSecret = $config['client_secret'] ?? null;
        $this->redirectUri = $config['redirect_uri'] ?? null;
    }

    public function isConfigured() {
        return !empty($this->clientId) && !empty($this->clientSecret) && file_exists($this->tokenFile);
    }

    public function getAuthUrl() {
        if (empty($this->clientId) || empty($this->redirectUri)) {
            return null;
        }

        $scopes = [
            'https://www.googleapis.com/auth/gmail.send'
        ];

        $stateToken = bin2hex(random_bytes(16));
        $_SESSION['oauth_gmail_state'] = $stateToken;

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => str_replace('google-callback.php', 'gmail-callback.php', $this->redirectUri),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $stateToken
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function getAccessToken() {
        if (!file_exists($this->tokenFile)) {
            throw new Exception('Gmail token not found. Please authorize first.');
        }

        $tokenData = json_decode(file_get_contents($this->tokenFile), true);

        if (empty($tokenData['access_token'])) {
            throw new Exception('Invalid token file');
        }

        if (isset($tokenData['expires_at']) && time() >= $tokenData['expires_at']) {
            if (empty($tokenData['refresh_token'])) {
                throw new Exception('Refresh token not found. Please re-authorize.');
            }
            $tokenData = $this->refreshToken($tokenData['refresh_token']);
        }

        return $tokenData['access_token'];
    }

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

        $tokenData = [
            'access_token' => $data['access_token'],
            'refresh_token' => $refreshToken,
            'expires_at' => time() + ($data['expires_in'] ?? 3600)
        ];

        file_put_contents($this->tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));

        return $tokenData;
    }

    public function exchangeCodeForToken($code) {
        $tokenUrl = 'https://oauth2.googleapis.com/token';

        $params = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => str_replace('google-callback.php', 'gmail-callback.php', $this->redirectUri),
            'grant_type' => 'authorization_code'
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
            throw new Exception('Failed to exchange code for token');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Token exchange error: ' . ($data['error_description'] ?? $data['error']));
        }

        $tokenData = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => time() + ($data['expires_in'] ?? 3600)
        ];

        file_put_contents($this->tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));

        return $tokenData;
    }

    /**
     * Gmail API でメール送信
     * RFC 2822形式のメールをbase64urlエンコードして送信
     */
    public function sendEmail($to, $subject, $body, $from = null) {
        // 非本番環境 / MAIL_DISABLED=true ならメール送信を抑止しログのみ
        if (function_exists('isMailDisabled') && isMailDisabled()) {
            error_log('[MAIL_DISABLED] To=' . $to . ' / Subject=' . $subject);
            return ['id' => 'mail_disabled_' . uniqid(), 'disabled' => true];
        }
        $accessToken = $this->getAccessToken();

        // RFC 2822形式のメールを作成
        $headers = [];
        if ($from) {
            $headers[] = "From: $from";
        }
        $headers[] = "To: $to";
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/plain; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: base64";
        $headers[] = "";
        $headers[] = base64_encode($body);

        $rawMessage = implode("\r\n", $headers);

        // base64url エンコード
        $encodedMessage = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');

        $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

        $options = [
            'http' => [
                'header'  => "Authorization: Bearer $accessToken\r\nContent-Type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode(['raw' => $encodedMessage]),
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
            throw new Exception('メール送信に失敗しました（接続エラー）');
        }

        $result = json_decode($response, true);

        if (isset($result['error'])) {
            $errorMsg = $result['error']['message'] ?? 'Unknown error';
            throw new Exception('メール送信エラー: ' . $errorMsg);
        }

        return $result;
    }

    public function disconnect() {
        if (file_exists($this->tokenFile)) {
            unlink($this->tokenFile);
        }
    }
}
