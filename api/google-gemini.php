<?php
/**
 * Google Gemini API クライアント
 *
 * Google AI Studio で取得した API キー (.env の GEMINI_API_KEY) を利用し、
 * Gemini モデルへテキスト/画像つきリクエストを送る薄いラッパー。
 *
 * 用途例:
 *  - 名刺OCR（画像 → JSON抽出）
 *  - 議事録要約
 *  - その他テキスト生成
 */

require_once __DIR__ . '/../config/config.php';

class GoogleGeminiClient {
    private string $apiKey;
    private string $model;
    private int    $timeout = 30; // 秒（OCRは多少時間がかかる）

    public function __construct(?string $model = null) {
        $this->apiKey = (string) env('GEMINI_API_KEY', '');
        $this->model  = $model ?: (string) env('GEMINI_MODEL', 'gemini-2.5-flash');
    }

    public function isConfigured(): bool {
        return $this->apiKey !== '';
    }

    public function getModel(): string {
        return $this->model;
    }

    /**
     * Gemini API に generateContent リクエストを送り、レスポンス JSON を返す。
     *
     * @param array $parts  contents.parts 配列 (text / inline_data 等)
     * @param array $options 追加設定:
     *  - generation_config => 配列（response_mime_type, temperature など）
     *  - system_instruction => 文字列（system instruction）
     *
     * @return array Gemini API レスポンス全体（連想配列）
     * @throws Exception
     */
    public function generateContent(array $parts, array $options = []): array {
        if (!$this->isConfigured()) {
            throw new Exception('GEMINI_API_KEY が設定されていません');
        }

        $payload = [
            'contents' => [
                [ 'parts' => $parts ],
            ],
        ];
        if (!empty($options['system_instruction'])) {
            $payload['systemInstruction'] = [
                'parts' => [ ['text' => (string)$options['system_instruction']] ],
            ];
        }
        if (!empty($options['generation_config'])) {
            $payload['generationConfig'] = $options['generation_config'];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/"
             . rawurlencode($this->model)
             . ":generateContent?key=" . rawurlencode($this->apiKey);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new Exception('リクエストペイロードのJSON生成に失敗しました');
        }

        $opts = [
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content'       => $body,
                'ignore_errors' => true,
                'timeout'       => $this->timeout,
            ],
            'ssl' => [ 'verify_peer' => true, 'verify_peer_name' => true ],
        ];
        $ctx  = stream_context_create($opts);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            throw new Exception('Gemini API への接続に失敗しました');
        }

        $code = 0;
        if (isset($http_response_header[0]) && preg_match('{HTTP/\S*\s(\d{3})}', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }
        $json = json_decode($resp, true);
        if (!is_array($json)) {
            throw new Exception('Gemini API レスポンスのJSON解析に失敗しました (HTTP ' . $code . ')');
        }
        if ($code >= 400) {
            $msg = $json['error']['message'] ?? ('Gemini API エラー (HTTP ' . $code . ')');
            throw new Exception('Gemini API エラー: ' . $msg);
        }
        return $json;
    }

    /**
     * generateContent のレスポンスから最初の text を取り出す。
     */
    public function extractText(array $response): string {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $p) {
            if (isset($p['text'])) return (string)$p['text'];
        }
        return '';
    }
}
