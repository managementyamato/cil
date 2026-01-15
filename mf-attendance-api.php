<?php
/**
 * マネーフォワード クラウド勤怠 API クライアント
 * API KEY方式による認証
 */

class MFAttendanceApiClient {
    private $apiKey;
    private $apiEndpoint = 'https://attendance.moneyforward.com/api/external/v1';

    public function __construct() {
        $config = $this->loadConfig();
        $this->apiKey = $config['api_key'] ?? null;
    }

    /**
     * 設定ファイルを読み込み
     */
    private function loadConfig() {
        $configFile = __DIR__ . '/mf-attendance-config.json';
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
        $configFile = __DIR__ . '/mf-attendance-config.json';
        $data['updated_at'] = date('Y-m-d H:i:s');
        return file_put_contents($configFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * APIリクエストを実行
     */
    private function request($method, $endpoint, $data = null) {
        if (!$this->apiKey) {
            throw new Exception('API KEYが設定されていません。先に設定を完了してください。');
        }

        $url = $this->apiEndpoint . $endpoint;

        $headers = "Authorization: Bearer " . $this->apiKey . "\r\n" .
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

        // HTTPステータスコードを取得
        $status_line = $http_response_header[0];
        preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
        $httpCode = intval($match[1]);

        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            throw new Exception('APIリクエスト失敗 (HTTP ' . $httpCode . '): ' . $response);
        }

        return json_decode($response, true);
    }

    /**
     * 従業員一覧を取得
     * @param int $page ページ番号（デフォルト: 1）
     * @param int $perPage 1ページあたりの件数（デフォルト: 100）
     * @return array 従業員データ
     */
    public function getEmployees($page = 1, $perPage = 100) {
        $params = array(
            'page' => $page,
            'per_page' => $perPage
        );

        $endpoint = '/employees?' . http_build_query($params);
        return $this->request('GET', $endpoint);
    }

    /**
     * 全従業員を取得（ページネーション対応）
     * @return array 全従業員データ
     */
    public function getAllEmployees() {
        $allEmployees = array();
        $page = 1;
        $perPage = 100;

        do {
            $response = $this->getEmployees($page, $perPage);

            $employees = $response['employees'] ?? $response['data'] ?? array();
            $allEmployees = array_merge($allEmployees, $employees);

            $hasMore = count($employees) === $perPage;
            $page++;
        } while ($hasMore);

        return $allEmployees;
    }

    /**
     * 勤怠データを取得
     * @param string $from 開始日（YYYY-MM-DD）
     * @param string $to 終了日（YYYY-MM-DD）
     * @param int|null $employeeId 従業員ID（オプション）
     * @return array 勤怠データ
     */
    public function getAttendances($from, $to, $employeeId = null) {
        $params = array(
            'from' => $from,
            'to' => $to
        );

        if ($employeeId !== null) {
            $params['employee_id'] = $employeeId;
        }

        $endpoint = '/attendances?' . http_build_query($params);
        return $this->request('GET', $endpoint);
    }

    /**
     * 全勤怠データを取得（ページネーション対応）
     * @param string $from 開始日（YYYY-MM-DD）
     * @param string $to 終了日（YYYY-MM-DD）
     * @param int|null $employeeId 従業員ID（オプション）
     * @return array 全勤怠データ
     */
    public function getAllAttendances($from, $to, $employeeId = null) {
        $allAttendances = array();
        $page = 1;
        $perPage = 100;

        do {
            $params = array(
                'from' => $from,
                'to' => $to,
                'page' => $page,
                'per_page' => $perPage
            );

            if ($employeeId !== null) {
                $params['employee_id'] = $employeeId;
            }

            $endpoint = '/attendances?' . http_build_query($params);
            $response = $this->request('GET', $endpoint);

            $attendances = $response['attendances'] ?? $response['data'] ?? array();
            $allAttendances = array_merge($allAttendances, $attendances);

            $hasMore = count($attendances) === $perPage;
            $page++;
        } while ($hasMore);

        return $allAttendances;
    }

    /**
     * 特定の従業員の勤怠データを取得
     * @param int $employeeId 従業員ID
     * @param string $from 開始日（YYYY-MM-DD）
     * @param string $to 終了日（YYYY-MM-DD）
     * @return array 勤怠データ
     */
    public function getEmployeeAttendance($employeeId, $from, $to) {
        return $this->getAttendances($from, $to, $employeeId);
    }

    /**
     * 認証済みかどうか
     */
    public static function isConfigured() {
        $configFile = __DIR__ . '/mf-attendance-config.json';
        if (!file_exists($configFile)) {
            return false;
        }
        $config = json_decode(file_get_contents($configFile), true);
        return !empty($config['api_key']);
    }

    /**
     * API KEYを保存
     */
    public static function saveApiKey($apiKey) {
        $configFile = __DIR__ . '/mf-attendance-config.json';
        $config = array();
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true) ?: array();
        }

        $config['api_key'] = $apiKey;
        $config['updated_at'] = date('Y-m-d H:i:s');

        return file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * 設定を取得
     */
    public static function getConfig() {
        $configFile = __DIR__ . '/mf-attendance-config.json';
        if (!file_exists($configFile)) {
            return array();
        }
        return json_decode(file_get_contents($configFile), true) ?: array();
    }
}
