<?php
/**
 * データスキーマ定義
 *
 * data.json の構造を一元管理し、スキーマ変更時の影響範囲を限定する。
 * 新しいフィールドやエンティティを追加する場合は、このファイルのみを修正する。
 */

/**
 * スキーマバージョン
 * データ構造を変更した場合はインクリメントする
 */
define('DATA_SCHEMA_VERSION', 1);

/**
 * データスキーマ定義クラス
 */
class DataSchema {

    /**
     * 全エンティティのスキーマ定義
     *
     * 各エンティティは以下の構造:
     * - 'default' => デフォルト値（空配列 or 連想配列）
     * - 'fields' => フィールド定義（オプション、バリデーション用）
     */
    private static $schema = [
        // 案件
        'projects' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
                'occurrence_date' => ['type' => 'date', 'required' => false],
                'transaction_type' => ['type' => 'string', 'required' => false],
                'sales_assignee' => ['type' => 'string', 'required' => false],
                'customer_name' => ['type' => 'string', 'required' => false],
                'dealer_name' => ['type' => 'string', 'required' => false],
                'general_contractor' => ['type' => 'string', 'required' => false],
                'postal_code' => ['type' => 'string', 'required' => false],
                'prefecture' => ['type' => 'string', 'required' => false],
                'address' => ['type' => 'string', 'required' => false],
                'shipping_address' => ['type' => 'string', 'required' => false],
                'product_category' => ['type' => 'string', 'required' => false],
                'product_series' => ['type' => 'string', 'required' => false],
                'product_name' => ['type' => 'string', 'required' => false],
                'product_spec' => ['type' => 'string', 'required' => false],
                'install_partner' => ['type' => 'string', 'required' => false],
                'remove_partner' => ['type' => 'string', 'required' => false],
                'contract_date' => ['type' => 'date', 'required' => false],
                'install_schedule_date' => ['type' => 'date', 'required' => false],
                'install_complete_date' => ['type' => 'date', 'required' => false],
                'shipping_date' => ['type' => 'date', 'required' => false],
                'install_request_date' => ['type' => 'date', 'required' => false],
                'install_date' => ['type' => 'date', 'required' => false],
                'remove_schedule_date' => ['type' => 'date', 'required' => false],
                'remove_request_date' => ['type' => 'date', 'required' => false],
                'remove_date' => ['type' => 'date', 'required' => false],
                'remove_inspection_date' => ['type' => 'date', 'required' => false],
                'warranty_end_date' => ['type' => 'date', 'required' => false],
                'status' => ['type' => 'string', 'required' => false],
                'memo' => ['type' => 'string', 'required' => false],
                'chat_url' => ['type' => 'string', 'required' => false],
                'chat_space_id' => ['type' => 'string', 'required' => false],
                'pending_chat_space' => ['type' => 'string', 'required' => false],
                'invoice_ids' => ['type' => 'array', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],

        // 担当者（旧形式、互換性のため維持）
        'assignees' => [
            'default' => [],
        ],

        // トラブル
        'troubles' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'project_id' => ['type' => 'string', 'required' => false],
                'project_name' => ['type' => 'string', 'required' => false],
                'title' => ['type' => 'string', 'required' => true],
                'description' => ['type' => 'string', 'required' => false],
                'status' => ['type' => 'string', 'required' => false],
                'priority' => ['type' => 'string', 'required' => false],
                'responder' => ['type' => 'string', 'required' => false],
                'deadline' => ['type' => 'date', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],

        // 顧客
        'customers' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'companyName' => ['type' => 'string', 'required' => true],
                'aliases' => ['type' => 'array', 'required' => false],
                'branches' => ['type' => 'array', 'required' => false],  // 営業所リスト
                'contact' => ['type' => 'string', 'required' => false],
                'phone' => ['type' => 'string', 'required' => false],
                'email' => ['type' => 'string', 'required' => false],
                'address' => ['type' => 'string', 'required' => false],
                'notes' => ['type' => 'string', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],

        // 協力会社
        'partners' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
                'contact' => ['type' => 'string', 'required' => false],
                'phone' => ['type' => 'string', 'required' => false],
                'email' => ['type' => 'string', 'required' => false],
                'address' => ['type' => 'string', 'required' => false],
                'notes' => ['type' => 'string', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],

        // 従業員
        'employees' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
                'email' => ['type' => 'string', 'required' => false],
                'department' => ['type' => 'string', 'required' => false],
                'role' => ['type' => 'string', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],

        // 商品カテゴリ
        'productCategories' => [
            'default' => [],
        ],

        // 設定
        'settings' => [
            'default' => [
                'spreadsheet_url' => ''
            ],
        ],

        // 請求書（スプレッドシートから同期）
        'invoices' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'invoice_number' => ['type' => 'string', 'required' => false],
                'customer_name' => ['type' => 'string', 'required' => false],
                'amount' => ['type' => 'number', 'required' => false],
                'issue_date' => ['type' => 'date', 'required' => false],
                'due_date' => ['type' => 'date', 'required' => false],
                'status' => ['type' => 'string', 'required' => false],
                'project_id' => ['type' => 'string', 'required' => false],
                'notes' => ['type' => 'string', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],

        // MF請求書（マネーフォワードから同期）
        'mf_invoices' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'mf_id' => ['type' => 'string', 'required' => false],
                'customer_name' => ['type' => 'string', 'required' => false],
                'amount' => ['type' => 'number', 'required' => false],
                'issue_date' => ['type' => 'date', 'required' => false],
                'due_date' => ['type' => 'date', 'required' => false],
                'status' => ['type' => 'string', 'required' => false],
            ]
        ],

        // 借入金
        'loans' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'bank_name' => ['type' => 'string', 'required' => true],
                'loan_name' => ['type' => 'string', 'required' => false],
                'principal' => ['type' => 'number', 'required' => false],
                'balance' => ['type' => 'number', 'required' => false],
                'interest_rate' => ['type' => 'number', 'required' => false],
                'start_date' => ['type' => 'date', 'required' => false],
                'end_date' => ['type' => 'date', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],

        // 返済履歴
        'repayments' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'loan_id' => ['type' => 'string', 'required' => true],
                'amount' => ['type' => 'number', 'required' => false],
                'principal_amount' => ['type' => 'number', 'required' => false],
                'interest_amount' => ['type' => 'number', 'required' => false],
                'payment_date' => ['type' => 'date', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],

        // MF同期タイムスタンプ
        'mf_sync_timestamp' => [
            'default' => null,
            'type' => 'datetime',
        ],

        // 顧客同期タイムスタンプ
        'customers_sync_timestamp' => [
            'default' => null,
            'type' => 'datetime',
        ],

        // 作成予定請求書（指定請求書）
        'scheduled_invoices' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'mf_template_id' => ['type' => 'string', 'required' => true],  // MFテンプレート請求書ID
                'partner_name' => ['type' => 'string', 'required' => false],    // 取引先名
                'partner_code' => ['type' => 'string', 'required' => false],    // 取引先コード
                'title' => ['type' => 'string', 'required' => false],           // 件名
                'target_month' => ['type' => 'string', 'required' => true],     // 対象月 (Y-m)
                'billing_date' => ['type' => 'date', 'required' => false],      // 請求日
                'due_date' => ['type' => 'date', 'required' => false],          // 支払期限
                'closing_type' => ['type' => 'string', 'required' => false],    // 締め日タイプ (20日〆/15日〆/末日〆)
                'status' => ['type' => 'string', 'required' => false],          // pending, created, error
                'mf_billing_id' => ['type' => 'string', 'required' => false],   // 作成後のMF請求書ID
                'error_message' => ['type' => 'string', 'required' => false],   // エラーメッセージ
                'created_by' => ['type' => 'string', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],
        // コメント・メモ
        'comments' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'entity_type' => ['type' => 'string', 'required' => true],  // projects, troubles, customers 等
                'entity_id' => ['type' => 'string', 'required' => true],    // 対象レコードのID
                'body' => ['type' => 'string', 'required' => true],         // コメント本文
                'author_email' => ['type' => 'string', 'required' => false],
                'author_name' => ['type' => 'string', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],
    ];

    /**
     * 全エンティティのキー一覧を取得
     */
    public static function getEntityKeys(): array {
        return array_keys(self::$schema);
    }

    /**
     * エンティティのデフォルト値を取得
     */
    public static function getDefault(string $key) {
        return self::$schema[$key]['default'] ?? null;
    }

    /**
     * エンティティのフィールド定義を取得
     */
    public static function getFields(string $key): ?array {
        return self::$schema[$key]['fields'] ?? null;
    }

    /**
     * エンティティが存在するかチェック
     */
    public static function hasEntity(string $key): bool {
        return isset(self::$schema[$key]);
    }

    /**
     * 初期データ構造を生成
     */
    public static function getInitialData(): array {
        $data = [];
        foreach (self::$schema as $key => $config) {
            $data[$key] = $config['default'];
        }
        return $data;
    }

    /**
     * データにスキーマを適用（不足キーを追加）
     *
     * @param array $data 既存データ
     * @return array スキーマ適用後のデータ
     */
    public static function ensureSchema(array $data): array {
        foreach (self::$schema as $key => $config) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $config['default'];
            }
        }
        return $data;
    }

    /**
     * フィールドが編集可能かチェック
     *
     * @param string $entity エンティティ名
     * @param string $field フィールド名
     * @return bool
     */
    public static function isFieldEditable(string $entity, string $field): bool {
        if (!isset(self::$schema[$entity]['fields'])) {
            return true; // フィールド定義がない場合は全て編集可能
        }

        $fields = self::$schema[$entity]['fields'];
        if (!isset($fields[$field])) {
            return false; // 定義にないフィールドは編集不可
        }

        // id, created_at は編集不可
        if (in_array($field, ['id', 'created_at'])) {
            return false;
        }

        return true;
    }

    /**
     * エンティティの全フィールド名を取得
     */
    public static function getFieldNames(string $entity): array {
        if (!isset(self::$schema[$entity]['fields'])) {
            return [];
        }
        return array_keys(self::$schema[$entity]['fields']);
    }

    /**
     * 必須フィールドを取得
     */
    public static function getRequiredFields(string $entity): array {
        if (!isset(self::$schema[$entity]['fields'])) {
            return [];
        }

        $required = [];
        foreach (self::$schema[$entity]['fields'] as $name => $config) {
            if (!empty($config['required'])) {
                $required[] = $name;
            }
        }
        return $required;
    }
}
