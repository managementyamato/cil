<?php
/**
 * Google Chat API クライアント
 * Workspaceのチャットスペースからメッセージ・画像を取得
 */

class GoogleChatClient {
    private $configFile;
    private $tokenFile;
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    // API通信設定
    private $timeout = 10; // 秒
    public $lastMembersError = null;

    public function __construct() {
        $this->configFile = __DIR__ . '/../config/google-config.json';
        $this->tokenFile = __DIR__ . '/../config/google-chat-token.json';
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
     * Chat APIが設定されているかチェック
     */
    public function isConfigured() {
        return !empty($this->clientId) && !empty($this->clientSecret) && file_exists($this->tokenFile);
    }

    /**
     * 認証URLを生成（Chat用スコープ）
     */
    public function getAuthUrl() {
        if (empty($this->clientId) || empty($this->redirectUri)) {
            return null;
        }

        $scopes = [
            'https://www.googleapis.com/auth/chat.spaces.readonly',
            'https://www.googleapis.com/auth/chat.spaces.create',  // スペース作成用
            'https://www.googleapis.com/auth/chat.messages.readonly',
            'https://www.googleapis.com/auth/chat.memberships.readonly',
            'https://www.googleapis.com/auth/chat.memberships'  // メンバー追加用
        ];

        // CSRF対策: stateパラメータを生成
        $stateToken = bin2hex(random_bytes(16));
        $_SESSION['oauth_chat_state'] = $stateToken;

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => str_replace('google-callback.php', 'google-chat-callback.php', $this->redirectUri),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $stateToken
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * アクセストークンを取得（必要に応じてリフレッシュ）
     */
    public function getAccessToken() {
        if (!file_exists($this->tokenFile)) {
            throw new Exception('Chat token not found. Please authorize first.');
        }

        $tokenData = json_decode(file_get_contents($this->tokenFile), true);

        // トークンが期限切れの場合はリフレッシュ
        if (isset($tokenData['expires_at']) && time() >= $tokenData['expires_at']) {
            $tokenData = $this->refreshToken($tokenData['refresh_token']);
        }

        return $tokenData['access_token'];
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

        // 新しいトークンを保存
        $tokenData = [
            'access_token' => $data['access_token'],
            'refresh_token' => $refreshToken,
            'expires_at' => time() + ($data['expires_in'] ?? 3600)
        ];

        file_put_contents($this->tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));

        return $tokenData;
    }

    /**
     * 認証コードをトークンに交換して保存
     */
    public function exchangeCodeForToken($code) {
        $tokenUrl = 'https://oauth2.googleapis.com/token';

        $params = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => str_replace('google-callback.php', 'google-chat-callback.php', $this->redirectUri),
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
            throw new Exception('Failed to exchange code for token (timeout or connection error)');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            throw new Exception('Token exchange error: ' . ($data['error_description'] ?? $data['error']));
        }

        // トークンを保存
        $tokenData = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => time() + ($data['expires_in'] ?? 3600)
        ];

        file_put_contents($this->tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));

        return $tokenData;
    }

    /**
     * チャットスペース一覧を取得
     */
    public function getSpaces() {
        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'spaces' => []];
        }

        $url = 'https://chat.googleapis.com/v1/spaces?pageSize=100';

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
            return ['error' => 'Failed to fetch spaces (timeout)', 'spaces' => []];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'API error';
            $errorCode = $data['error']['code'] ?? '';
            return ['error' => "{$errorMsg} (code: {$errorCode})", 'spaces' => []];
        }

        $spaces = [];
        foreach ($data['spaces'] ?? [] as $space) {
            $spaces[] = [
                'name' => $space['name'],
                'displayName' => $space['displayName'] ?? '(名前なし)',
                'type' => $space['type'] ?? 'ROOM',
                'spaceType' => $space['spaceType'] ?? 'SPACE'
            ];
        }

        return ['spaces' => $spaces, 'error' => null];
    }

    /**
     * 指定スペースのメッセージを取得
     * @param string $spaceName スペース名 (spaces/xxx)
     * @param int $pageSize 取得件数（最大1000）
     * @param string|null $filter フィルター条件（例: 'createTime > "2026-01-27T00:00:00Z"'）
     * @param string|null $pageToken ページネーショントークン
     */
    public function getMessages($spaceName, $pageSize = 100, $filter = null, $pageToken = null) {
        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'messages' => []];
        }

        // Google Chat APIのメッセージ一覧
        $params = [
            'pageSize' => $pageSize,
            'showDeleted' => 'false'
        ];

        // フィルターが指定されている場合
        if ($filter) {
            $params['filter'] = $filter;
        }

        // ページネーショントークンが指定されている場合
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        $url = "https://chat.googleapis.com/v1/{$spaceName}/messages?" . http_build_query($params);

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
            return ['error' => 'Failed to fetch messages (timeout)', 'messages' => []];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return ['error' => $data['error']['message'] ?? 'API error', 'messages' => []];
        }

        return [
            'messages' => $data['messages'] ?? [],
            'nextPageToken' => $data['nextPageToken'] ?? null,
            'error' => null,
            'raw_response_keys' => array_keys($data)
        ];
    }

    /**
     * 指定スペースのメッセージを全ページ取得（対象日のメッセージを見つけるまで）
     * @param string $spaceName スペース名 (spaces/xxx)
     * @param string|null $targetDate 対象日（YYYY-MM-DD形式）この日付のメッセージが見つかるまで取得
     * @param int $maxPages 最大ページ数
     */
    public function getAllMessagesForDate($spaceName, $targetDate, $maxPages = 100) {
        $allMessages = [];
        $pageToken = null;
        $pageCount = 0;
        $newestDateSeen = null;
        $oldestDateSeen = null;
        $debugInfo = [
            'pages_fetched' => 0,
            'total_messages_scanned' => 0,
            'date_range_seen' => [],
            'filter_used' => null
        ];

        // 日付フィルターを作成（対象日の0:00から翌日の0:00まで）
        // Google Chat APIのフィルター形式: createTime > "2026-01-27T00:00:00+09:00" AND createTime < "2026-01-28T00:00:00+09:00"
        $filter = null;
        if ($targetDate) {
            $startTime = $targetDate . 'T00:00:00+09:00';
            $endDate = date('Y-m-d', strtotime($targetDate . ' +1 day'));
            $endTime = $endDate . 'T00:00:00+09:00';
            $filter = 'createTime > "' . $startTime . '" AND createTime < "' . $endTime . '"';
            $debugInfo['filter_used'] = $filter;
        }

        while ($pageCount < $maxPages) {
            $result = $this->getMessages($spaceName, 100, $filter, $pageToken);

            if (!empty($result['error'])) {
                return [
                    'error' => $result['error'],
                    'messages' => $allMessages,
                    'debug' => $debugInfo
                ];
            }

            $messages = $result['messages'] ?? [];
            if (empty($messages)) {
                break;
            }

            $debugInfo['total_messages_scanned'] += count($messages);

            // フィルターを使用している場合、返されるメッセージは全て対象日のもの
            // フィルターなしの場合のみ日付チェックが必要
            foreach ($messages as $msg) {
                $msgTime = $msg['createTime'] ?? '';
                if ($msgTime) {
                    $msgDate = date('Y-m-d', strtotime($msgTime));

                    // 見た日付の範囲を記録
                    if ($oldestDateSeen === null || $msgDate < $oldestDateSeen) {
                        $oldestDateSeen = $msgDate;
                    }
                    if ($newestDateSeen === null || $msgDate > $newestDateSeen) {
                        $newestDateSeen = $msgDate;
                    }

                    // フィルター使用時は全メッセージを追加、フィルターなしの場合は日付チェック
                    if ($filter !== null || $msgDate === $targetDate) {
                        $allMessages[] = $msg;
                    }
                }
            }

            // 次のページトークンがなければ終了
            $pageToken = $result['nextPageToken'] ?? null;
            if (!$pageToken) {
                break;
            }

            $pageCount++;
        }

        $debugInfo['pages_fetched'] = $pageCount + 1;
        $debugInfo['date_range_seen'] = [
            'oldest' => $oldestDateSeen,
            'newest' => $newestDateSeen
        ];

        return [
            'messages' => $allMessages,
            'error' => null,
            'debug' => $debugInfo
        ];
    }

    /**
     * メッセージの添付ファイル一覧を取得
     */
    public function getMessageAttachments($messageName) {
        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'attachments' => []];
        }

        $url = "https://chat.googleapis.com/v1/{$messageName}/attachments";

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
            return ['error' => 'Failed to fetch attachments (timeout)', 'attachments' => []];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return ['error' => $data['error']['message'] ?? 'API error', 'attachments' => []];
        }

        return [
            'attachments' => $data['attachments'] ?? [],
            'error' => null
        ];
    }

    /**
     * 単一の添付ファイル情報を取得
     */
    public function getAttachment($attachmentName) {
        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'attachment' => null];
        }

        $url = "https://chat.googleapis.com/v1/{$attachmentName}";

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
            return ['error' => 'Failed to fetch attachment info (timeout)', 'attachment' => null];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return ['error' => $data['error']['message'] ?? 'API error', 'attachment' => null];
        }

        return [
            'attachment' => $data,
            'error' => null
        ];
    }

    /**
     * 添付ファイルをダウンロード
     */
    public function downloadAttachment($attachmentName) {
        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        // 添付ファイル名の形式に応じてURLを構築
        // 形式1: spaces/xxx/messages/xxx/attachments/xxx
        // 形式2: media/xxx
        if (strpos($attachmentName, 'spaces/') === 0) {
            $url = "https://chat.googleapis.com/v1/{$attachmentName}";
        } elseif (strpos($attachmentName, 'media/') === 0) {
            $url = "https://chat.googleapis.com/v1/{$attachmentName}?alt=media";
        } else {
            // フルパスが渡された場合
            $url = "https://chat.googleapis.com/v1/{$attachmentName}";
        }

        $options = [
            'http' => [
                'header'  => "Authorization: Bearer {$accessToken}\r\n",
                'method'  => 'GET',
                'ignore_errors' => true,
                'timeout' => 30 // 添付ファイルダウンロードは長めに
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return ['success' => false, 'error' => 'Failed to download attachment (timeout)'];
        }

        // HTTPステータスコードをチェック
        $statusCode = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $header, $matches)) {
                $statusCode = (int)$matches[1];
            }
        }

        // エラーレスポンスの場合
        if ($statusCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? "HTTP {$statusCode}";
            return ['success' => false, 'error' => $errorMsg];
        }

        // レスポンスヘッダーからContent-Typeを取得
        $contentType = 'application/octet-stream';
        foreach ($http_response_header ?? [] as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = trim(substr($header, 13));
                break;
            }
        }

        // JSONレスポンスの場合はエラー
        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode($response, true);
            if (isset($data['error'])) {
                return ['success' => false, 'error' => $data['error']['message'] ?? 'API error'];
            }
            // 添付ファイル情報が返ってきた場合、downloadUriを試す
            if (isset($data['downloadUri'])) {
                return $this->downloadFromUri($data['downloadUri']);
            }
            if (isset($data['attachmentDataRef']['resourceName'])) {
                // リソース名を使って再度ダウンロード試行
                $resourceName = $data['attachmentDataRef']['resourceName'];
                return $this->downloadFromResourceName($resourceName);
            }
            return ['success' => false, 'error' => 'Unexpected JSON response'];
        }

        return [
            'success' => true,
            'data' => $response,
            'contentType' => $contentType
        ];
    }

    /**
     * URIから直接ダウンロード（public版）
     */
    public function downloadFromUrl($url) {
        return $this->downloadFromUri($url);
    }

    /**
     * Media APIから添付ファイルをダウンロード（public版）
     * @param string $resourceName "spaces/xxx/messages/xxx/attachments/xxx" 形式
     */
    public function downloadFromMediaApi($resourceName) {
        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        // Media APIエンドポイント
        $url = "https://chat.googleapis.com/v1/media/{$resourceName}?alt=media";

        $options = [
            'http' => [
                'header'  => "Authorization: Bearer {$accessToken}\r\n",
                'method'  => 'GET',
                'ignore_errors' => true,
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return ['success' => false, 'error' => 'Failed to download from Media API (timeout)'];
        }

        // HTTPステータスコードをチェック
        $statusCode = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $header, $matches)) {
                $statusCode = (int)$matches[1];
            }
        }

        if ($statusCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? "HTTP {$statusCode}";
            return ['success' => false, 'error' => "Media API error: {$errorMsg}"];
        }

        // Content-Typeを取得
        $contentType = 'application/octet-stream';
        foreach ($http_response_header ?? [] as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = trim(substr($header, 13));
                break;
            }
        }

        // JSONレスポンスの場合はエラー
        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode($response, true);
            if (isset($data['error'])) {
                return ['success' => false, 'error' => 'Media API: ' . ($data['error']['message'] ?? 'Unknown error')];
            }
            return ['success' => false, 'error' => 'Unexpected JSON response from Media API'];
        }

        return [
            'success' => true,
            'data' => $response,
            'contentType' => $contentType
        ];
    }

    /**
     * URIから直接ダウンロード
     */
    private function downloadFromUri($uri) {
        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $options = [
            'http' => [
                'header'  => "Authorization: Bearer {$accessToken}\r\n",
                'method'  => 'GET',
                'ignore_errors' => true,
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($uri, false, $context);

        if ($response === false) {
            return ['success' => false, 'error' => 'Failed to download from URI'];
        }

        $contentType = 'application/octet-stream';
        foreach ($http_response_header ?? [] as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = trim(substr($header, 13));
                break;
            }
        }

        return [
            'success' => true,
            'data' => $response,
            'contentType' => $contentType
        ];
    }

    /**
     * リソース名から添付ファイルをダウンロード（Media API使用）
     */
    private function downloadFromResourceName($resourceName) {
        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        // Media APIのエンドポイント
        // resourceNameは "spaces/xxx/messages/xxx/attachments/xxx" の形式
        $url = "https://chat.googleapis.com/v1/media/{$resourceName}?alt=media";

        $options = [
            'http' => [
                'header'  => "Authorization: Bearer {$accessToken}\r\n",
                'method'  => 'GET',
                'ignore_errors' => true,
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return ['success' => false, 'error' => 'Failed to download from resource name'];
        }

        $contentType = 'application/octet-stream';
        foreach ($http_response_header ?? [] as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = trim(substr($header, 13));
                break;
            }
        }

        return [
            'success' => true,
            'data' => $response,
            'contentType' => $contentType
        ];
    }

    /**
     * ユーザー情報を取得（Google People API または Directory API）
     * Chat User ID (users/xxxxx) からメールアドレスなどを取得
     * @param string $userId "users/123456789" 形式
     * @return array ['email' => '...', 'name' => '...', 'error' => null] または ['error' => '...']
     */
    public function getUserInfo($userId) {
        // キャッシュをチェック（7日間有効）
        $cacheFile = __DIR__ . '/../config/chat-user-cache.json';
        $cache = [];
        if (file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        if (isset($cache[$userId]) && (time() - ($cache[$userId]['cached_at'] ?? 0)) < 604800) {
            return $cache[$userId]['data'];
        }

        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }

        // userIdからIDを抽出 (users/123456789 → 123456789)
        $numericId = str_replace('users/', '', $userId);
        if (empty($numericId)) {
            return ['error' => 'Invalid user ID'];
        }

        // Google People API を使用してユーザー情報を取得
        // 注意: このAPIは同じWorkspace組織内のユーザーにのみ機能する
        $url = "https://people.googleapis.com/v1/people/{$numericId}?personFields=emailAddresses,names";

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
            return ['error' => 'Failed to fetch user info (timeout)'];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            // People APIで失敗した場合、Admin Directory API を試す
            $dirResult = $this->getUserInfoFromDirectory($numericId, $accessToken);
            // キャッシュに保存（エラーでもキャッシュして無駄なAPI呼び出しを防止）
            if (empty($dirResult['error'])) {
                $cache[$userId] = ['data' => $dirResult, 'cached_at' => time()];
                file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
            return $dirResult;
        }

        // メールアドレスと名前を抽出
        $email = null;
        $name = null;

        if (isset($data['emailAddresses']) && is_array($data['emailAddresses'])) {
            foreach ($data['emailAddresses'] as $emailData) {
                if (!empty($emailData['value'])) {
                    $email = $emailData['value'];
                    break;
                }
            }
        }

        if (isset($data['names']) && is_array($data['names'])) {
            foreach ($data['names'] as $nameData) {
                if (!empty($nameData['displayName'])) {
                    $name = $nameData['displayName'];
                    break;
                }
            }
        }

        $result = [
            'email' => $email,
            'name' => $name,
            'error' => null
        ];

        // キャッシュに保存（7日間）
        $cache[$userId] = ['data' => $result, 'cached_at' => time()];
        file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $result;
    }

    /**
     * Admin Directory API でユーザー情報を取得
     * @param string $numericId ユーザーのnumeric ID
     * @param string $accessToken アクセストークン
     */
    private function getUserInfoFromDirectory($numericId, $accessToken) {
        // Admin Directory API を使用
        $url = "https://admin.googleapis.com/admin/directory/v1/users/{$numericId}";

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
            return ['error' => 'Failed to fetch user from Directory API'];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return ['error' => $data['error']['message'] ?? 'Directory API error'];
        }

        return [
            'email' => $data['primaryEmail'] ?? null,
            'name' => $data['name']['fullName'] ?? null,
            'error' => null
        ];
    }

    /**
     * 複数のユーザーIDからメールアドレスを一括取得（キャッシュ付き）
     * @param array $userIds ["users/123", "users/456", ...] 形式の配列
     * @return array ['users/123' => ['email' => '...', 'name' => '...'], ...]
     */
    public function getUserInfoBatch($userIds) {
        $results = [];
        $cacheFile = __DIR__ . '/../config/chat-user-cache.json';

        // キャッシュを読み込み
        $cache = [];
        if (file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), true) ?: [];
        }

        $cacheExpiry = 86400 * 7; // 7日間キャッシュ
        $cacheUpdated = false;

        foreach ($userIds as $userId) {
            if (empty($userId)) continue;

            // キャッシュをチェック
            if (isset($cache[$userId]) && isset($cache[$userId]['cached_at'])) {
                if (time() - $cache[$userId]['cached_at'] < $cacheExpiry) {
                    $results[$userId] = $cache[$userId];
                    continue;
                }
            }

            // APIから取得
            $info = $this->getUserInfo($userId);
            if (empty($info['error'])) {
                $info['cached_at'] = time();
                $cache[$userId] = $info;
                $results[$userId] = $info;
                $cacheUpdated = true;
            } else {
                // エラーの場合もキャッシュ（リトライを減らす）
                $cache[$userId] = [
                    'email' => null,
                    'name' => null,
                    'error' => $info['error'],
                    'cached_at' => time()
                ];
                $results[$userId] = $cache[$userId];
                $cacheUpdated = true;
            }

            // API制限を避けるため少し待機
            usleep(100000); // 0.1秒
        }

        // キャッシュを保存
        if ($cacheUpdated) {
            file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $results;
    }

    /**
     * スペースのメンバー一覧を取得してUser ID→メールアドレスのマッピングを構築
     * Chat API の spaces.members.list を使用（chat.memberships.readonlyスコープ）
     * @param string $spaceId スペースID（spaces/xxx形式）
     * @return array ['users/123' => ['email' => '...', 'name' => '...'], ...]
     */
    public function getSpaceMembersMap($spaceId) {
        // キャッシュをチェック（1日間有効）
        $cacheFile = __DIR__ . '/../config/chat-members-cache.json';
        $cache = [];
        if (file_exists($cacheFile)) {
            $cache = json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        if (isset($cache[$spaceId]) && (time() - ($cache[$spaceId]['cached_at'] ?? 0)) < 86400) {
            return $cache[$spaceId]['members'] ?? [];
        }

        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return [];
        }

        $members = [];
        $pageToken = '';
        $this->lastMembersError = null;

        do {
            $url = "https://chat.googleapis.com/v1/{$spaceId}/members?pageSize=100";
            if (!empty($pageToken)) {
                $url .= '&pageToken=' . urlencode($pageToken);
            }

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
                $this->lastMembersError = 'Request failed (timeout). URL: ' . $url;
                break;
            }

            $data = json_decode($response, true);
            if (isset($data['error'])) {
                $this->lastMembersError = ($data['error']['message'] ?? json_encode($data['error'])) . ' | URL: ' . $url . ' | Status: ' . ($data['error']['code'] ?? 'unknown');
                break;
            }

            foreach ($data['memberships'] ?? [] as $membership) {
                $member = $membership['member'] ?? [];
                $userId = $member['name'] ?? '';  // "users/123456789"
                $memberType = $member['type'] ?? '';

                // HUMANタイプのメンバーのみ処理（BOTを除外）
                if ($memberType !== 'HUMAN' || empty($userId)) continue;

                $email = $member['email'] ?? '';
                $displayName = $member['displayName'] ?? '';

                if (!empty($email)) {
                    $members[$userId] = [
                        'email' => $email,
                        'name' => $displayName
                    ];
                }
            }

            $pageToken = $data['nextPageToken'] ?? '';
        } while (!empty($pageToken));

        // キャッシュに保存（メンバーが取得できた場合のみ）
        if (!empty($members)) {
            $cache[$spaceId] = [
                'members' => $members,
                'cached_at' => time()
            ];
            file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return $members;
    }

    /**
     * スペースメンバー情報からUser IDでユーザー情報を取得
     * People API/Directory APIが使えない場合のメイン方式
     * @param string $userId "users/123456789" 形式
     * @param string $spaceId "spaces/xxx" 形式
     * @return array ['email' => '...', 'name' => '...', 'error' => null]
     */
    public function getUserInfoFromMembers($userId, $spaceId) {
        $membersMap = $this->getSpaceMembersMap($spaceId);
        if (isset($membersMap[$userId])) {
            return [
                'email' => $membersMap[$userId]['email'],
                'name' => $membersMap[$userId]['name'],
                'error' => null
            ];
        }
        return ['error' => 'User not found in space members', 'email' => null, 'name' => null];
    }

    /**
     * スペースにメンバーを追加
     * @param string $spaceId スペースID（spaces/xxx形式）
     * @param string $email 追加するユーザーのメールアドレス
     * @return array ['success' => bool, 'error' => string|null, 'membership' => array|null]
     */
    public function addMember($spaceId, $email) {
        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'membership' => null];
        }

        $url = "https://chat.googleapis.com/v1/{$spaceId}/members";

        $body = [
            'member' => [
                'name' => "users/{$email}",
                'type' => 'HUMAN'
            ]
        ];

        $options = [
            'http' => [
                'header'  => "Authorization: Bearer {$accessToken}\r\n" .
                            "Content-Type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($body),
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
            return ['success' => false, 'error' => 'Failed to add member (timeout)', 'membership' => null];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'API error';
            $errorCode = $data['error']['code'] ?? '';
            // 既にメンバーの場合はエラーではなく成功扱い
            if (strpos($errorMsg, 'already exists') !== false || $errorCode == 409) {
                return ['success' => true, 'error' => null, 'membership' => null, 'already_member' => true];
            }
            return ['success' => false, 'error' => "{$errorMsg} (code: {$errorCode})", 'membership' => null];
        }

        return ['success' => true, 'error' => null, 'membership' => $data];
    }

    /**
     * スペースに複数メンバーを一括追加
     * @param string $spaceId スペースID（spaces/xxx形式）
     * @param array $emails メールアドレスの配列
     * @return array ['success' => int, 'failed' => int, 'already_member' => int, 'errors' => array]
     */
    public function addMembers($spaceId, $emails) {
        $result = [
            'success' => 0,
            'failed' => 0,
            'already_member' => 0,
            'errors' => []
        ];

        foreach ($emails as $email) {
            $email = trim($email);
            if (empty($email)) continue;

            $addResult = $this->addMember($spaceId, $email);

            if ($addResult['success']) {
                if (!empty($addResult['already_member'])) {
                    $result['already_member']++;
                } else {
                    $result['success']++;
                }
            } else {
                $result['failed']++;
                $result['errors'][] = [
                    'email' => $email,
                    'error' => $addResult['error']
                ];
            }

            // API制限を避けるため少し待機
            usleep(200000); // 0.2秒
        }

        return $result;
    }

    /**
     * 新しいスペースを作成
     * @param string $displayName スペース名
     * @param string $spaceType スペースタイプ（SPACE or GROUP_CHAT）
     * @return array ['success' => bool, 'space' => array|null, 'error' => string|null]
     */
    public function createSpace($displayName, $spaceType = 'SPACE') {
        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['success' => false, 'space' => null, 'error' => $e->getMessage()];
        }

        $url = 'https://chat.googleapis.com/v1/spaces';

        $body = [
            'spaceType' => $spaceType,
            'displayName' => $displayName,
            'singleUserBotDm' => false
        ];

        $options = [
            'http' => [
                'header'  => "Authorization: Bearer {$accessToken}\r\n" .
                            "Content-Type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($body),
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
            return ['success' => false, 'space' => null, 'error' => 'Failed to create space (timeout)'];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'API error';
            $errorCode = $data['error']['code'] ?? '';
            return ['success' => false, 'space' => null, 'error' => "{$errorMsg} (code: {$errorCode})"];
        }

        return [
            'success' => true,
            'space' => $data,
            'error' => null
        ];
    }

    /**
     * スペースを作成してメンバーを追加
     * @param string $displayName スペース名
     * @param array $memberEmails 追加するメンバーのメールアドレス配列
     * @return array ['success' => bool, 'space' => array|null, 'members_result' => array|null, 'error' => string|null]
     */
    public function createSpaceWithMembers($displayName, $memberEmails = []) {
        // スペースを作成
        $createResult = $this->createSpace($displayName);

        if (!$createResult['success']) {
            return [
                'success' => false,
                'space' => null,
                'members_result' => null,
                'error' => $createResult['error']
            ];
        }

        $space = $createResult['space'];
        $spaceId = $space['name'] ?? '';

        // メンバーを追加
        $membersResult = null;
        if (!empty($memberEmails) && !empty($spaceId)) {
            $membersResult = $this->addMembers($spaceId, $memberEmails);
        }

        return [
            'success' => true,
            'space' => $space,
            'members_result' => $membersResult,
            'error' => null
        ];
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
