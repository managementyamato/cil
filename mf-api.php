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
            return array(
                'success' => true,
                'message' => '接続成功',
                'data' => $result
            );
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            // エラーメッセージを解析して、より分かりやすい説明を追加
            if (strpos($errorMsg, '401') !== false || strpos($errorMsg, 'token_rejected') !== false) {
                $errorMsg .= "\n\n【解決方法】\n";
                $errorMsg .= "1. アクセストークンが正しいか確認してください\n";
                $errorMsg .= "2. トークンの有効期限が切れている場合は、MF クラウド請求書で新しいトークンを発行してください\n";
                $errorMsg .= "3. MF クラウド「請求書」のトークンを使用していることを確認してください（会計ではありません）";
            } elseif (strpos($errorMsg, '403') !== false) {
                $errorMsg .= "\n\n【解決方法】権限が不足しています。MFクラウド請求書の管理者権限を持つアカウントでトークンを発行してください。";
            } elseif (strpos($errorMsg, '404') !== false) {
                $errorMsg .= "\n\n【解決方法】APIエンドポイントが見つかりません。システム管理者に連絡してください。";
            }

            return array('success' => false, 'message' => $errorMsg);
        }
    }

    /**
     * OAuth認証URLを生成
     */
    public static function getAuthorizationUrl($clientId, $redirectUri, $state = null) {
        if (!$state) {
            $state = bin2hex(random_bytes(16));
        }

        $params = array(
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
            'scope' => 'read write'
        );

        return 'https://invoice.moneyforward.com/oauth/authorize?' . http_build_query($params);
    }

    /**
     * 認証コードからアクセストークンを取得
     */
    public static function getAccessTokenFromCode($clientId, $clientSecret, $code, $redirectUri) {
        $tokenEndpoint = 'https://invoice.moneyforward.com/oauth/token';

        $params = array(
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenEndpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('トークン取得エラー: ' . $error);
        }

        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            throw new Exception('トークン取得失敗 (HTTP ' . $httpCode . '): ' . json_encode($errorData));
        }

        return json_decode($response, true);
    }

    /**
     * OAuth設定を保存（Client ID/Secret含む）
     */
    public static function saveOAuthConfig($clientId, $clientSecret, $accessToken = null, $refreshToken = null) {
        $config = array(
            'auth_type' => 'oauth',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'updated_at' => date('Y-m-d H:i:s')
        );

        $configFile = __DIR__ . '/mf-config.json';
        return file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
