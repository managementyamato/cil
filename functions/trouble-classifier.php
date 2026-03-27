<?php
/**
 * トラブル自動分類クラス（ハイブリッド方式）
 *
 * 分類フロー:
 *   1. ルールベース（キーワードマッチ）で分類・自信スコアを計算
 *   2. 自信スコア < $apiThreshold の場合のみ Claude API を呼び出す
 *   3. APIキー未設定・API失敗時はルールベース結果にフォールバック
 *
 * 対象: LEDディスプレイ・液晶サイネージのトラブル内容（日本語）
 */
class TroubleClassifier
{
    /** 分類カテゴリ一覧 */
    const CATEGORIES = ['電源系', '映像系', '音声系', 'ネットワーク系', 'ハードウェア', 'ソフトウェア', 'その他'];

    /** 優先度一覧（高い順） */
    const PRIORITIES = ['緊急', '高', '中', '低'];

    /** カテゴリ判定キーワード辞書 */
    private static array $categoryKeywords = [
        '電源系' => [
            '電源', '起動しない', '電源が入らない', 'ブレーカー', '通電', '停電', '電源落ちた',
            '電源つかない', 'onにならない', 'offにならない', '電力', 'ups', 'コンセント',
            '電源ケーブル', '電源断', '給電', 'poe', 'アダプタ', 'スイッチ入らない',
        ],
        '映像系' => [
            '映像', '画面', '表示されない', '表示されません', '暗い', '輝度', '色', 'にじむ',
            'チカチカ', 'ちらつく', '画素', '死亡ピクセル', '欠け', '白飛び', '黒くなる',
            'ブランク', '無表示', 'モジュール', 'パネル', '点灯しない', 'ライン状', '縦線', '横線',
            '半分', 'コーナー', '端', '滲み', 'コントラスト', 'ちらつき', 'フリッカー',
            '解像度', '映らない', '写らない', '黒い', '白い',
        ],
        '音声系' => [
            '音', 'スピーカー', '音が出ない', '音量', '雑音', 'ノイズ', '音声', '消音', 'ミュート',
            '音割れ', '音が小さい', '音響', 'サウンド', 'オーディオ',
        ],
        'ネットワーク系' => [
            'ネットワーク', 'lan', 'wi-fi', 'wifi', '接続できない', '通信', 'ip', 'コントローラー',
            'cms', '更新できない', 'ネット', '回線', 'ルーター', 'スイッチング', 'オフライン',
            'クラウド', 'リモート', 'vpn', 'ダウンロード', '通信エラー', 'コンテンツが更新',
            '繋がらない', 'ping', 'dhcp', '有線', '無線',
        ],
        'ハードウェア' => [
            '割れ', '破損', '欠け', '傷', '変形', '落下', '水濡れ', 'led破損',
            'キャビネット', 'フレーム', '曲がる', '凹', '衝突', '外れた', 'ずれ',
            '割れている', 'ひび', '損傷', '物理', '浸水', '破れ',
        ],
        'ソフトウェア' => [
            'ソフト', 'アプリ', '設定', 'プレーヤー', 'cms', 'バグ', 'エラーコード',
            'フリーズ', '再起動', 'ファームウェア', 'アップデート', '誤表示', '文字化け',
            'スケジュール', 'コンテンツ', 'ファイル', '不具合', 'エラー', 'クラッシュ',
            'ログイン', 'パスワード', 'ライセンス', 'バージョン',
        ],
    ];

    /** 優先度判定キーワード辞書 */
    private static array $priorityKeywords = [
        '緊急' => [
            '緊急', '至急', '全面', '全部が', 'すべて表示されない', '完全に', '営業に支障',
            '本番中', '今日中', 'すぐに', '即日', '開店前', '開業', 'イベント当日', '展示会',
            '全停止', '全画面', '完全停止', 'お客様が来る', '来客中',
        ],
        '高' => [
            '半分以上', '大部分', '重要', '早急', '目立つ', 'メインの', '大きな',
            '中心部', 'できるだけ早く', '早めに', '急ぎ', '大きく', '広範囲',
        ],
        '中' => [
            '一部', '数カ所', '点滅', '時々', 'たまに', '少し気になる', 'ところどころ',
            '端の方', '隅', 'ときどき', '不定期',
        ],
        '低' => [
            '軽微', '少し', '気になる程度', '余裕があれば', 'いつかで', '確認', '可能なら',
            'そのうち', '急ぎではない', 'いつでも',
        ],
    ];

    /**
     * テキストを分類する（メインエントリポイント）
     *
     * @param string $text       分類するトラブル内容
     * @param float  $apiThreshold この値より低い自信度の場合のみ API を呼ぶ（0.0〜1.0）
     * @return array{category: string, priority: string, confidence: float, source: string}
     */
    public static function classify(string $text, float $apiThreshold = 0.65): array
    {
        // ステップ1: ルールベース分類
        $ruleResult = self::classifyByRules($text);

        // ステップ2: 自信度が低い場合のみ API を呼ぶ
        if ($ruleResult['confidence'] < $apiThreshold) {
            $apiResult = self::classifyByApi($text);
            if ($apiResult !== null) {
                return array_merge($apiResult, ['source' => 'ai']);
            }
            // API 失敗時はルールベースにフォールバック
            return array_merge($ruleResult, ['source' => 'rule_fallback']);
        }

        return array_merge($ruleResult, ['source' => 'rule']);
    }

    /**
     * ルールベース分類
     *
     * @return array{category: string, priority: string, confidence: float}
     */
    public static function classifyByRules(string $text): array
    {
        $normalizedText = mb_strtolower($text);

        // カテゴリスコア計算
        $categoryScores = [];
        foreach (self::$categoryKeywords as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (mb_strpos($normalizedText, $keyword) !== false) {
                    $score++;
                }
            }
            $categoryScores[$category] = $score;
        }

        // 優先度スコア計算
        $priorityScores = [];
        foreach (self::$priorityKeywords as $priority => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (mb_strpos($normalizedText, $keyword) !== false) {
                    $score++;
                }
            }
            $priorityScores[$priority] = $score;
        }

        // 最高スコアのカテゴリを選択
        arsort($categoryScores);
        $topCategory      = array_key_first($categoryScores);
        $topCategoryScore = $categoryScores[$topCategory] ?? 0;

        // 最高スコアの優先度を選択
        arsort($priorityScores);
        $topPriority      = array_key_first($priorityScores);
        $topPriorityScore = $priorityScores[$topPriority] ?? 0;

        // キーワード未ヒット時のデフォルト
        $category = ($topCategoryScore > 0) ? $topCategory : 'その他';
        $priority = ($topPriorityScore > 0) ? $topPriority : '中';

        // 自信スコア: 3キーワード以上ヒットで 1.0
        $confidence = min(1.0, $topCategoryScore / 3.0);

        return [
            'category'   => $category,
            'priority'   => $priority,
            'confidence' => round($confidence, 2),
        ];
    }

    /**
     * Claude API による分類
     * config/ai-config.json に claude_api_key が設定されている場合のみ動作
     *
     * @return array|null 成功時は ['category', 'priority', 'confidence']、失敗時は null
     */
    public static function classifyByApi(string $text): ?array
    {
        $configFile = __DIR__ . '/../config/ai-config.json';
        if (!file_exists($configFile)) {
            return null;
        }

        $config = @json_decode(@file_get_contents($configFile), true);
        $apiKey = $config['claude_api_key'] ?? '';
        if (empty($apiKey)) {
            return null;
        }

        $categories = implode(', ', self::CATEGORIES);
        $priorities  = implode(', ', self::PRIORITIES);

        $prompt = <<<PROMPT
以下はLEDディスプレイ・液晶サイネージのトラブル報告テキストです。
カテゴリと優先度を判定してください。

カテゴリ選択肢: {$categories}
優先度選択肢: {$priorities}（高い順）

トラブル内容:
{$text}

回答は必ず以下のJSONのみで返してください（説明文は不要）:
{"category":"カテゴリ名","priority":"優先度名","confidence":0.0から1.0の数値}
PROMPT;

        try {
            $payload = json_encode([
                'model'      => 'claude-haiku-20240307',
                'max_tokens' => 100,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            $context = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => implode("\r\n", [
                        'Content-Type: application/json',
                        'x-api-key: ' . $apiKey,
                        'anthropic-version: 2023-06-01',
                        'Content-Length: ' . strlen($payload),
                    ]),
                    'content'       => $payload,
                    'timeout'       => 5,
                    'ignore_errors' => true,
                ],
            ]);

            $response = @file_get_contents('https://api.anthropic.com/v1/messages', false, $context);
            if (!$response) {
                return null;
            }

            $data    = json_decode($response, true);
            $content = $data['content'][0]['text'] ?? '';

            // JSON 部分だけを抽出（前後のテキストを除去）
            if (preg_match('/\{[^}]+\}/', $content, $matches)) {
                $result = json_decode($matches[0], true);
            } else {
                $result = json_decode($content, true);
            }

            if (
                isset($result['category']) && in_array($result['category'], self::CATEGORIES, true) &&
                isset($result['priority']) && in_array($result['priority'], self::PRIORITIES, true)
            ) {
                return [
                    'category'   => $result['category'],
                    'priority'   => $result['priority'],
                    'confidence' => min(1.0, max(0.0, (float)($result['confidence'] ?? 0.8))),
                ];
            }
        } catch (Exception $e) {
            error_log('TroubleClassifier API error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * AI 設定ファイルが存在し API キーが設定されているか確認
     */
    public static function isApiConfigured(): bool
    {
        $configFile = __DIR__ . '/../config/ai-config.json';
        if (!file_exists($configFile)) return false;
        $config = @json_decode(@file_get_contents($configFile), true);
        return !empty($config['claude_api_key']);
    }
}
