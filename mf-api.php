<?php
/**
 * マネーフォワード クラウド会計 API クライアント
 */

class MFApiClient {
    private $accessToken;
    private $refreshToken;
    private $clientId;
    private $clientSecret;
    private $tokenExpiresAt;
    private $apiEndpoint = 'https://invoice.moneyforward.com/api/v3';
    private $tokenUrl = 'https://api.biz.moneyforward.com/token';

    public function __construct($accessToken = null) {
        if ($accessToken) {
            $this->accessToken = $accessToken;
        } else {
            // 設定ファイルから読み込み
            $config = $this->loadConfig();
            $this->accessToken = $config['access_token'] ?? null;
            $this->refreshToken = $config['refresh_token'] ?? null;
            $this->clientId = $config['client_id'] ?? null;
            $this->clientSecret = $config['client_secret'] ?? null;

            // トークンの有効期限を計算
            if (isset($config['token_obtained_at']) && isset($config['expires_in'])) {
                $this->tokenExpiresAt = $config['token_obtained_at'] + $config['expires_in'];
            }
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
     * アクセストークンをリフレッシュ
     */
    private function refreshAccessToken() {
        if (!$this->refreshToken || !$this->clientId || !$this->clientSecret) {
            throw new Exception('リフレッシュトークンまたはクライアント認証情報がありません');
        }

        $postData = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        );

        $ch = curl_init($this->tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded'
        ));
        // SSL証明書の検証を無効化（開発環境のみ）
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('トークンのリフレッシュに失敗しました (HTTP ' . $httpCode . ')');
        }

        $tokenData = json_decode($response, true);
        $this->accessToken = $tokenData['access_token'];
        $this->refreshToken = $tokenData['refresh_token'] ?? $this->refreshToken;

        // 新しいトークンを保存
        $config = $this->loadConfig();
        $config['access_token'] = $this->accessToken;
        $config['refresh_token'] = $this->refreshToken;
        $config['expires_in'] = $tokenData['expires_in'] ?? 3600;
        $config['token_obtained_at'] = time();
        $config['updated_at'] = date('Y-m-d H:i:s');

        $configFile = __DIR__ . '/mf-config.json';
        file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 有効期限を更新
        $this->tokenExpiresAt = time() + $config['expires_in'];
    }

    /**
     * トークンが期限切れかチェック
     */
    private function isTokenExpired() {
        if (!$this->tokenExpiresAt) {
            return false; // 有効期限情報がない場合は期限切れではないと判断
        }
        // 5分の余裕を持ってチェック
        return time() >= ($this->tokenExpiresAt - 300);
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

        // トークンが期限切れの場合はリフレッシュ
        if ($this->isTokenExpired() && $this->refreshToken) {
            $this->refreshAccessToken();
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
        // SSL証明書の検証を無効化（開発環境のみ）
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

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
     * 請求書を作成
     */
    public function createInvoice($params) {
        $required = ['partner_code', 'billing_date', 'items'];
        foreach ($required as $field) {
            if (!isset($params[$field])) {
                throw new Exception("必須項目 {$field} が指定されていません");
            }
        }

        $invoiceData = array(
            'billing' => array(
                'partner_code' => $params['partner_code'],
                'billing_date' => $params['billing_date'],
                'due_date' => $params['due_date'] ?? null,
                'invoice_number' => $params['invoice_number'] ?? null,
                'title' => $params['title'] ?? '',
                'note' => $params['note'] ?? '',
                'items' => $params['items']
            )
        );

        return $this->request('POST', '/billings', $invoiceData);
    }

    /**
     * 請求書を更新
     */
    public function updateInvoice($billingId, $params) {
        return $this->request('PUT', '/billings/' . $billingId, array('billing' => $params));
    }

    /**
     * 請求書を削除
     */
    public function deleteInvoice($billingId) {
        return $this->request('DELETE', '/billings/' . $billingId);
    }

    /**
     * 請求書の詳細を取得
     */
    public function getInvoice($billingId) {
        return $this->request('GET', '/billings/' . $billingId);
    }

    /**
     * 取引先を検索
     */
    public function searchPartners($query) {
        return $this->request('GET', '/partners?q=' . urlencode($query));
    }

    /**
     * 取引先を作成
     */
    public function createPartner($params) {
        $required = ['name'];
        foreach ($required as $field) {
            if (!isset($params[$field])) {
                throw new Exception("必須項目 {$field} が指定されていません");
            }
        }

        $partnerData = array(
            'partner' => array(
                'name' => $params['name'],
                'code' => $params['code'] ?? null,
                'name_kana' => $params['name_kana'] ?? null,
                'email' => $params['email'] ?? null,
                'tel' => $params['tel'] ?? null,
                'address1' => $params['address1'] ?? null,
                'address2' => $params['address2'] ?? null
            )
        );

        return $this->request('POST', '/partners', $partnerData);
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
