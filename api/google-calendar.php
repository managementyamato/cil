<?php
/**
 * Google Calendar API クライアント
 */

class GoogleCalendarClient {
    private $configFile;
    private $tokenFile;
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    // API通信設定
    private $timeout = 3; // 秒（短縮）
    private $maxCalendars = 10; // 取得するカレンダーの最大数
    private $selectedCalendars = []; // 選択されたカレンダーID（空なら全て）

    // キャッシュ設定
    private $cacheEnabled = true;
    private $cacheTTL = 300; // 5分キャッシュ
    private $cachePrefix = 'gcal_cache_';

    public function __construct() {
        $this->configFile = __DIR__ . '/../config/google-config.json';
        $this->tokenFile = __DIR__ . '/../config/google-calendar-token.json';
        $this->loadConfig();

        // セッション開始（キャッシュ用）
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
     * カレンダーキャッシュをクリア
     */
    public function clearCache() {
        if (session_status() === PHP_SESSION_NONE) {
            return;
        }
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, $this->cachePrefix) === 0) {
                unset($_SESSION[$key]);
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

        // カレンダー設定を読み込み
        $calendarSettingsFile = __DIR__ . '/../config/calendar-settings.json';
        if (file_exists($calendarSettingsFile)) {
            $calSettings = json_decode(file_get_contents($calendarSettingsFile), true);
            $this->selectedCalendars = $calSettings['selected_calendars'] ?? [];
        }
    }

    /**
     * 選択されたカレンダーを取得
     */
    public function getSelectedCalendars() {
        return $this->selectedCalendars;
    }

    /**
     * 選択されたカレンダーを保存
     */
    public function saveSelectedCalendars($calendarIds) {
        $calendarSettingsFile = __DIR__ . '/../config/calendar-settings.json';
        $settings = ['selected_calendars' => $calendarIds];
        file_put_contents($calendarSettingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->selectedCalendars = $calendarIds;
        // キャッシュをクリア
        $this->clearCache();
    }

    /**
     * カレンダーが設定されているかチェック
     */
    public function isConfigured() {
        return !empty($this->clientId) && !empty($this->clientSecret) && file_exists($this->tokenFile);
    }

    /**
     * 認証URLを生成（カレンダースコープ付き）
     */
    public function getAuthUrl() {
        if (empty($this->clientId) || empty($this->redirectUri)) {
            return null;
        }

        $scopes = [
            'https://www.googleapis.com/auth/calendar.readonly'
        ];

        // CSRF対策: stateパラメータを生成
        $stateToken = bin2hex(random_bytes(16));
        $_SESSION['oauth_calendar_state'] = $stateToken;

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => str_replace('google-callback.php', 'google-calendar-callback.php', $this->redirectUri),
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
            throw new Exception('Calendar token not found. Please authorize first.');
        }

        $tokenData = json_decode(file_get_contents($this->tokenFile), true);

        if (empty($tokenData['access_token'])) {
            throw new Exception('Invalid token file');
        }

        // トークンが期限切れの場合はリフレッシュ
        if (isset($tokenData['expires_at']) && time() >= $tokenData['expires_at']) {
            if (empty($tokenData['refresh_token'])) {
                throw new Exception('Refresh token not found. Please re-authorize.');
            }
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
            'refresh_token' => $refreshToken, // リフレッシュトークンは変わらない
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
            'redirect_uri' => str_replace('google-callback.php', 'google-calendar-callback.php', $this->redirectUri),
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
     * ユーザーがアクセスできるカレンダー一覧を取得
     */
    public function getCalendarList() {
        // キャッシュをチェック
        $cacheKey = 'calendar_list';
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'calendars' => []];
        }

        $url = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';

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
            error_log('[Calendar] Failed to fetch calendar list - timeout or connection error');
            return ['error' => 'カレンダー一覧の取得に失敗しました', 'calendars' => []];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'API error';
            error_log('[Calendar] API error: ' . $errorMsg);
            // 認証エラーの場合はトークンファイルを削除
            if (($data['error']['code'] ?? 0) === 401) {
                @unlink($this->tokenFile);
                return ['error' => '認証の有効期限が切れました。再連携してください。', 'calendars' => []];
            }
            return ['error' => $errorMsg, 'calendars' => []];
        }

        $calendars = [];
        foreach ($data['items'] ?? [] as $item) {
            $calendars[] = [
                'id' => $item['id'],
                'name' => $item['summary'] ?? '(名前なし)',
                'backgroundColor' => $item['backgroundColor'] ?? '#4285f4',
                'primary' => $item['primary'] ?? false,
                'accessRole' => $item['accessRole'] ?? 'reader'
            ];
        }

        $result = ['calendars' => $calendars, 'error' => null];

        // キャッシュに保存
        $this->setCache($cacheKey, $result);

        return $result;
    }

    /**
     * 指定カレンダーから今日の予定を取得
     */
    private function getEventsFromCalendar($accessToken, $calendarId, $calendarName, $calendarColor) {
        // 今日の開始と終了
        $today = new DateTime('today', new DateTimeZone('Asia/Tokyo'));
        $tomorrow = new DateTime('tomorrow', new DateTimeZone('Asia/Tokyo'));

        $params = [
            'timeMin' => $today->format('c'),
            'timeMax' => $tomorrow->format('c'),
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'maxResults' => 20
        ];

        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendarId) . '/events?' . http_build_query($params);

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
            return [];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return [];
        }

        $events = [];
        foreach ($data['items'] ?? [] as $item) {
            $start = $item['start']['dateTime'] ?? $item['start']['date'] ?? null;
            $end = $item['end']['dateTime'] ?? $item['end']['date'] ?? null;

            // 終日イベントかどうか
            $isAllDay = isset($item['start']['date']);

            $events[] = [
                'id' => $item['id'],
                'title' => $item['summary'] ?? '(タイトルなし)',
                'start' => $start,
                'end' => $end,
                'isAllDay' => $isAllDay,
                'location' => $item['location'] ?? null,
                'description' => $item['description'] ?? null,
                'htmlLink' => $item['htmlLink'] ?? null,
                'calendarName' => $calendarName,
                'calendarColor' => $calendarColor
            ];
        }

        return $events;
    }

    /**
     * 今日の予定を取得（選択されたカレンダーから、または全カレンダーから）
     */
    public function getTodayEvents() {
        // キャッシュをチェック
        $today = date('Y-m-d');
        $cacheKey = 'today_events_' . $today . '_' . md5(json_encode($this->selectedCalendars));
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $accessToken = $this->getAccessToken();
        } catch (Exception $e) {
            return ['error' => $e->getMessage(), 'events' => []];
        }

        // カレンダー一覧を取得
        $calendarListResult = $this->getCalendarList();
        if (!empty($calendarListResult['error'])) {
            return ['error' => $calendarListResult['error'], 'events' => []];
        }

        $allEvents = [];
        $calendars = $calendarListResult['calendars'];

        // 選択されたカレンダーがある場合はフィルタリング
        if (!empty($this->selectedCalendars)) {
            $calendars = array_filter($calendars, function($cal) {
                return in_array($cal['id'], $this->selectedCalendars);
            });
        } else {
            // 選択がない場合はプライマリカレンダーを優先し、最大数を制限
            usort($calendars, function($a, $b) {
                if ($a['primary'] && !$b['primary']) return -1;
                if (!$a['primary'] && $b['primary']) return 1;
                return 0;
            });
            $calendars = array_slice($calendars, 0, $this->maxCalendars);
        }

        // 各カレンダーから予定を取得
        foreach ($calendars as $calendar) {
            $events = $this->getEventsFromCalendar(
                $accessToken,
                $calendar['id'],
                $calendar['name'],
                $calendar['backgroundColor']
            );
            $allEvents = array_merge($allEvents, $events);
        }

        // 開始時間でソート（終日イベントは先頭に）
        usort($allEvents, function($a, $b) {
            // 終日イベントを先頭に
            if ($a['isAllDay'] && !$b['isAllDay']) return -1;
            if (!$a['isAllDay'] && $b['isAllDay']) return 1;

            // 同じタイプなら開始時間でソート
            return strcmp($a['start'], $b['start']);
        });

        $result = ['events' => $allEvents, 'error' => null];

        // キャッシュに保存
        $this->setCache($cacheKey, $result);

        return $result;
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
