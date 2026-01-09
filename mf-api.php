<?php
/**
 * マネーフォワード クラウド会計 API クライアント
 */

class MFApiClient {
    private $accessToken;
    private $apiEndpoint = 'https://invoice.moneyforward.com/api/v3';

    public function __construct($accessToken = null) {
        if ($accessToken) {
            $this->accessToken = $accessToken;
        } else {
            // 設定ファイルから読み込み
            $config = $this->loadConfig();
            $this->accessToken = $config['access_token'] ?? null;
        }
    }

    /**
     * MF API設定を読み込み
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
     * MF API設定を保存
     */
    public static function saveConfig($accessToken, $officeId = null) {
        $config = array(
            'access_token' => $accessToken,
            'office_id' => $officeId,
            'updated_at' => date('Y-m-d H:i:s')
        );

        $configFile = __DIR__ . '/mf-config.json';
        return file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * API設定が存在するかチェック
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
     * APIリクエストを実行
     */
    private function request($method, $endpoint, $data = null) {
        if (!$this->accessToken) {
            throw new Exception('アクセストークンが設定されていません');
        }

        $url = $this->apiEndpoint . $endpoint;

        $headers = array(
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('APIリクエストエラー: ' . $error);
        }

        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMessage = isset($errorData['errors']) ? json_encode($errorData['errors']) : $response;
            throw new Exception('APIエラー (HTTP ' . $httpCode . '): ' . $errorMessage);
        }

        return json_decode($response, true);
    }

    /**
     * 取引先一覧を取得
     */
    public function getPartners($page = 1, $perPage = 100) {
        return $this->request('GET', '/partners?page=' . $page . '&per_page=' . $perPage);
    }

    /**
     * 請求書一覧を取得
     */
    public function getInvoices($from = null, $to = null, $page = 1) {
        $params = array('page' => $page);
        if ($from) $params['from'] = $from;
        if ($to) $params['to'] = $to;

        $query = http_build_query($params);
        return $this->request('GET', '/billings?' . $query);
    }

    /**
     * 見積書一覧を取得
     */
    public function getQuotes($from = null, $to = null, $page = 1) {
        $params = array('page' => $page);
        if ($from) $params['from'] = $from;
        if ($to) $params['to'] = $to;

        $query = http_build_query($params);
        return $this->request('GET', '/quotes?' . $query);
    }

    /**
     * 取引データから財務データを抽出
     */
    public function extractFinanceData($invoices, $quotes = array()) {
        $financeData = array();

        // 請求書から売上を集計
        if (isset($invoices['billings'])) {
            foreach ($invoices['billings'] as $invoice) {
                $projectName = $invoice['title'] ?? '';
                $amount = floatval($invoice['total_price'] ?? 0);
                $date = $invoice['billing_date'] ?? '';

                $financeData[] = array(
                    'type' => 'invoice',
                    'project_name' => $projectName,
                    'revenue' => $amount,
                    'date' => $date,
                    'partner' => $invoice['partner_name'] ?? '',
                    'status' => $invoice['status'] ?? ''
                );
            }
        }

        // 見積書から案件情報を取得
        if (isset($quotes['quotes'])) {
            foreach ($quotes['quotes'] as $quote) {
                $projectName = $quote['title'] ?? '';
                $amount = floatval($quote['total_price'] ?? 0);
                $date = $quote['quote_date'] ?? '';

                $financeData[] = array(
                    'type' => 'quote',
                    'project_name' => $projectName,
                    'revenue' => $amount,
                    'date' => $date,
                    'partner' => $quote['partner_name'] ?? '',
                    'status' => $quote['status'] ?? ''
                );
            }
        }

        return $financeData;
    }

    /**
     * 接続テスト
     */
    public function testConnection() {
        try {
            $result = $this->getPartners(1, 1);
            return array('success' => true, 'message' => '接続成功');
        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
}
