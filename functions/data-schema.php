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
                'customer_name' => ['type' => 'string', 'required' => false],
                'sales_assignee' => ['type' => 'string', 'required' => false],
                'dealer_name' => ['type' => 'string', 'required' => false],
                'office_name' => ['type' => 'string', 'required' => false],
                'maker' => ['type' => 'string', 'required' => false],
                'led_size' => ['type' => 'string', 'required' => false],  // LED計数→LEDサイズに変更
                'lcd_size' => ['type' => 'string', 'required' => false],
                'cms_player' => ['type' => 'string', 'required' => false],
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
                // 営業情報統合（M2: 顧客マスター × AM連動）
                'customer_code' => ['type' => 'string', 'required' => false],
                'customer_rank' => ['type' => 'string', 'required' => false],  // 解決済みランク（rank は予約語のため customer_rank）
                'rank_mode' => ['type' => 'string', 'required' => false],      // auto | manual
                'rank_manual' => ['type' => 'string', 'required' => false],
                'am_employee_id' => ['type' => 'string', 'required' => false], // 主担当AM (employees.id)
                'industry' => ['type' => 'string', 'required' => false],
                'trade_start' => ['type' => 'date', 'required' => false],
                'credit_limit' => ['type' => 'number', 'required' => false],
                'area' => ['type' => 'string', 'required' => false],
                // アカウントマネジメント（戦略アカウント管理リスト由来）
                'am_number' => ['type' => 'string', 'required' => false],
                'account_status' => ['type' => 'string', 'required' => false],   // 既存/休眠
                'account_type' => ['type' => 'string', 'required' => false],
                'account_type_memo' => ['type' => 'string', 'required' => false],
                'hq_location' => ['type' => 'string', 'required' => false],
                'priority' => ['type' => 'string', 'required' => false],
                'rank_challenge' => ['type' => 'string', 'required' => false],   // 目標ランク
                'am_person' => ['type' => 'string', 'required' => false],        // 担当者名
                'am_memo' => ['type' => 'string', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],

        // 顧客CC候補（M2: メール作成時に必ずCCに入れる候補）
        'customer_cc' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'customer_id' => ['type' => 'string', 'required' => true],
                'employee_id' => ['type' => 'string', 'required' => false],
                'name' => ['type' => 'string', 'required' => false],
                'email' => ['type' => 'string', 'required' => false],
                'role_label' => ['type' => 'string', 'required' => false],
                'note' => ['type' => 'string', 'required' => false],
                'sort_order' => ['type' => 'number', 'required' => false],
                'created_by' => ['type' => 'string', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
                'deleted_at' => ['type' => 'datetime', 'required' => false],
                'deleted_by' => ['type' => 'string', 'required' => false],
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

        // メーカー
        'manufacturers' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
                'contact' => ['type' => 'string', 'required' => false],
                'phone' => ['type' => 'string', 'required' => false],
                'email' => ['type' => 'string', 'required' => false],
                'notes' => ['type' => 'string', 'required' => false],
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
                'billing_number' => ['type' => 'string', 'required' => false],
                'title' => ['type' => 'string', 'required' => false],
                'partner_name' => ['type' => 'string', 'required' => false],
                'billing_date' => ['type' => 'date', 'required' => false],
                'due_date' => ['type' => 'date', 'required' => false],
                'sales_date' => ['type' => 'date', 'required' => false],
                'subtotal' => ['type' => 'number', 'required' => false],
                'tax' => ['type' => 'number', 'required' => false],
                'total_amount' => ['type' => 'number', 'required' => false],
                'payment_status' => ['type' => 'string', 'required' => false],
                'posting_status' => ['type' => 'string', 'required' => false],
                'email_status' => ['type' => 'string', 'required' => false],
                'memo' => ['type' => 'string', 'required' => false],
                'note' => ['type' => 'string', 'required' => false],
                'tag_names' => ['type' => 'array', 'required' => false],
                'project_id' => ['type' => 'string', 'required' => false],
                'assignee' => ['type' => 'string', 'required' => false],
                'closing_date' => ['type' => 'date', 'required' => false],
                'pdf_url' => ['type' => 'string', 'required' => false],
                'items' => ['type' => 'array', 'required' => false],
                'mf_id' => ['type' => 'string', 'required' => false],
                'customer_name' => ['type' => 'string', 'required' => false],
                'amount' => ['type' => 'number', 'required' => false],
                'issue_date' => ['type' => 'date', 'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'synced_at' => ['type' => 'datetime', 'required' => false],
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

        // 指定請求書HTMLテンプレート
        'invoice_templates' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],      // テンプレート名
                'partner_id' => ['type' => 'string', 'required' => false], // MF取引先ID
                'partner_name' => ['type' => 'string', 'required' => false], // 取引先名（表示用キャッシュ）
                'html_template' => ['type' => 'string', 'required' => true], // HTMLテンプレート本文
                'created_at' => ['type' => 'datetime', 'required' => false],
                'created_by' => ['type' => 'string', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],

        // 指定請求書Excelテンプレート（セルマッピング設定）
        'invoice_excel_templates' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],          // テンプレート名（例: アクティオ指定書式）
                'partner_id' => ['type' => 'string', 'required' => false],   // MF取引先ID
                'partner_name' => ['type' => 'string', 'required' => false], // 取引先名
                'filename' => ['type' => 'string', 'required' => true],      // アップロードされたExcelファイル名
                'sheet_name' => ['type' => 'string', 'required' => false],   // 書き込み対象シート名（空=最初のシート）
                'mapping' => ['type' => 'string', 'required' => false],      // JSON: セルマッピング設定
                'item_row_start' => ['type' => 'string', 'required' => false], // 明細開始行番号
                'item_row_count' => ['type' => 'string', 'required' => false], // 明細行数
                'created_at' => ['type' => 'datetime', 'required' => false],
                'created_by' => ['type' => 'string', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
            ]
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

        // マイワークスペース: タスク（全ユーザー共有、作成者・adminが編集可）
        'tasks' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'title' => ['type' => 'string', 'required' => true],
                'description' => ['type' => 'string', 'required' => false],
                'status' => ['type' => 'string', 'required' => false],   // 未着手|進行中|完了
                'due_date' => ['type' => 'date', 'required' => false],
                'subtasks' => ['type' => 'array', 'required' => false],  // [{id, title, done}]
                'created_by' => ['type' => 'string', 'required' => true],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
                'deleted_at' => ['type' => 'datetime', 'required' => false],
                'deleted_by' => ['type' => 'string', 'required' => false],
            ]
        ],

        // 全体お知らせ掲示板（作成: admin、閲覧: 全ユーザー）
        'announcements' => [
            'default' => [],
            'fields'  => [
                'id'         => ['type' => 'string',   'required' => true],
                'title'      => ['type' => 'string',   'required' => true],
                'content'    => ['type' => 'string',   'required' => true],
                'priority'   => ['type' => 'string',   'required' => false],  // info|warning|urgent
                'pinned'     => ['type' => 'bool',     'required' => false],
                'read_by'    => ['type' => 'array',    'required' => false],
                'expires_at' => ['type' => 'date',     'required' => false],
                'created_by' => ['type' => 'string',   'required' => true],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
                'deleted_at' => ['type' => 'datetime', 'required' => false],
                'deleted_by' => ['type' => 'string',   'required' => false],
            ],
        ],

        // マイワークスペース: メモ（個人専用・完全プライベート）
        'memos' => [
            'default' => [],
            'fields' => [
                'id' => ['type' => 'string', 'required' => true],
                'title' => ['type' => 'string', 'required' => true],
                'content' => ['type' => 'string', 'required' => false],  // Markdown
                'pinned' => ['type' => 'bool', 'required' => false],
                'tags' => ['type' => 'array', 'required' => false],
                'user_email' => ['type' => 'string', 'required' => true], // 所有者（プライバシー識別子）
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
                'deleted_at' => ['type' => 'datetime', 'required' => false],
                'deleted_by' => ['type' => 'string', 'required' => false],
            ]
        ],

        // スライド（社内マニュアル）: 管理者が登録、全員が閲覧・確認
        'slides' => [
            'default' => [],
            'fields' => [
                'id'           => ['type' => 'string',   'required' => true],
                'title'        => ['type' => 'string',   'required' => true],
                'google_docs_url' => ['type' => 'string', 'required' => true],  // Google Docs URL
                'description'  => ['type' => 'string',   'required' => false], // 説明・概要
                'required_for' => ['type' => 'array',    'required' => false], // ["sales","product","admin"]
                'due_date'     => ['type' => 'date',     'required' => false], // 確認期限（null=無期限）
                'created_by'   => ['type' => 'string',   'required' => true],
                'created_at'   => ['type' => 'datetime', 'required' => false],
                'updated_at'   => ['type' => 'datetime', 'required' => false],
                'deleted_at'   => ['type' => 'datetime', 'required' => false],
                'deleted_by'   => ['type' => 'string',   'required' => false],
            ]
        ],

        // 社内規則（admin が章単位で管理、全員が閲覧・検索）
        'company_rules' => [
            'default' => [],
            'fields' => [
                'id'              => ['type' => 'string',   'required' => true],
                'chapter_number'  => ['type' => 'number',   'required' => true],   // 章番号 (1〜12)
                'chapter_title'   => ['type' => 'string',   'required' => true],   // 例: 総則
                'content'         => ['type' => 'string',   'required' => false],  // 本文
                'created_by'      => ['type' => 'string',   'required' => true],
                'created_at'      => ['type' => 'datetime', 'required' => false],
                'updated_at'      => ['type' => 'datetime', 'required' => false],
                'deleted_at'      => ['type' => 'datetime', 'required' => false],
                'deleted_by'      => ['type' => 'string',   'required' => false],
            ]
        ],

        // 社内連絡先（閲覧: 全員、編集: admin）
        'contacts' => [
            'default' => [],
            'fields'  => [
                'id'         => ['type' => 'string',   'required' => true],
                'category'   => ['type' => 'string',   'required' => true],
                'scene'      => ['type' => 'string',   'required' => true],
                'dept'       => ['type' => 'string',   'required' => true],
                'ext'        => ['type' => 'string',   'required' => false],
                'email'      => ['type' => 'string',   'required' => false],
                'person'     => ['type' => 'string',   'required' => false],
                'note'       => ['type' => 'string',   'required' => false],
                'sort_order' => ['type' => 'number',   'required' => false],
                'created_by' => ['type' => 'string',   'required' => false],
                'created_at' => ['type' => 'datetime', 'required' => false],
                'updated_at' => ['type' => 'datetime', 'required' => false],
                'deleted_at' => ['type' => 'datetime', 'required' => false],
                'deleted_by' => ['type' => 'string',   'required' => false],
            ],
        ],

        // 顧客ランク定義（営業ツール 価格表より）
        // 閲覧: sales / 編集: sales 以上 / 削除: admin
        'customer_ranks' => [
            'default' => [],
            'fields'  => [
                'id'          => ['type' => 'string',   'required' => true],
                'rank'        => ['type' => 'string',   'required' => true],  // A / B / C / D
                'deal_type'   => ['type' => 'string',   'required' => false], // 例「ディーラー販売・レンタル」
                'condition'   => ['type' => 'string',   'required' => false], // 例「売上上位3社ディーラー」
                'companies'   => ['type' => 'array',    'required' => false], // 該当先企業
                'note'        => ['type' => 'string',   'required' => false], // 注記
                'sort_order'  => ['type' => 'number',   'required' => false],
                'created_by'  => ['type' => 'string',   'required' => false],
                'created_at'  => ['type' => 'datetime', 'required' => false],
                'updated_at'  => ['type' => 'datetime', 'required' => false],
            ],
        ],

        // 営業リード（名刺OCR・手入力で登録される見込み顧客）
        // 閲覧・編集: sales 全員（営業ツール内のサブ機能）
        'leads' => [
            'default' => [],
            'fields'  => [
                'id'                       => ['type' => 'string',   'required' => true],
                'company_name'             => ['type' => 'string',   'required' => true],
                'person_name'              => ['type' => 'string',   'required' => false],
                'title'                    => ['type' => 'string',   'required' => false],
                'department'               => ['type' => 'string',   'required' => false],
                'phone'                    => ['type' => 'string',   'required' => false],
                'mobile'                   => ['type' => 'string',   'required' => false],
                'fax'                      => ['type' => 'string',   'required' => false],
                'email'                    => ['type' => 'string',   'required' => false],
                'website'                  => ['type' => 'string',   'required' => false],
                'address'                  => ['type' => 'string',   'required' => false],
                'status'                   => ['type' => 'string',   'required' => false], // 新規/接触済/商談中/成約/失注
                'source'                   => ['type' => 'string',   'required' => false], // business_card / manual
                'business_card_image_path' => ['type' => 'string',   'required' => false],
                'am'                       => ['type' => 'string',   'required' => false], // 担当営業
                'notes'                    => ['type' => 'string',   'required' => false],
                'created_by'               => ['type' => 'string',   'required' => false],
                'created_at'               => ['type' => 'datetime', 'required' => false],
                'updated_at'               => ['type' => 'datetime', 'required' => false],
                'deleted_at'               => ['type' => 'datetime', 'required' => false],
                'deleted_by'               => ['type' => 'string',   'required' => false],
            ],
        ],

        // ワークフロー申請
        'workflow_requests' => [
            'default' => [],
            'fields' => [
                'id'                => ['type' => 'string',   'required' => true],
                'workflow_type'     => ['type' => 'string',   'required' => true],
                'title'             => ['type' => 'string',   'required' => true],
                'description'       => ['type' => 'string',   'required' => false],
                'amount'            => ['type' => 'number',   'required' => false],
                'details'           => ['type' => 'string',   'required' => false],
                'approvers'         => ['type' => 'array',    'required' => false],
                'current_step'      => ['type' => 'number',   'required' => false],
                'status'            => ['type' => 'string',   'required' => false],
                'submitted_by'      => ['type' => 'string',   'required' => true],
                'submitted_by_name' => ['type' => 'string',   'required' => false],
                'submitted_at'      => ['type' => 'datetime', 'required' => false],
                'created_at'        => ['type' => 'datetime', 'required' => false],
                'updated_at'        => ['type' => 'datetime', 'required' => false],
                'deleted_at'        => ['type' => 'datetime', 'required' => false],
                'deleted_by'        => ['type' => 'string',   'required' => false],
            ]
        ],

        // リマインダー
        'reminders' => [
            'default' => [],
            'fields' => [
                'id'            => ['type' => 'string',   'required' => true],
                'title'         => ['type' => 'string',   'required' => true],
                'description'   => ['type' => 'string',   'required' => false],
                'due_date'      => ['type' => 'date',     'required' => true],
                'due_time'      => ['type' => 'string',   'required' => false],
                'remind_before' => ['type' => 'string',   'required' => false],
                'target_type'   => ['type' => 'string',   'required' => false],
                'target_value'  => ['type' => 'string',   'required' => false],
                'source_type'   => ['type' => 'string',   'required' => false],
                'source_id'     => ['type' => 'string',   'required' => false],
                'status'        => ['type' => 'string',   'required' => false],
                'created_by'    => ['type' => 'string',   'required' => true],
                'created_at'    => ['type' => 'datetime', 'required' => false],
                'updated_at'    => ['type' => 'datetime', 'required' => false],
                'deleted_at'    => ['type' => 'datetime', 'required' => false],
                'deleted_by'    => ['type' => 'string',   'required' => false],
            ]
        ],

        // スライド確認記録（誰がいつ確認したか）
        'slide_confirmations' => [
            'default' => [],
            'fields' => [
                'id'           => ['type' => 'string',   'required' => true],
                'slide_id'     => ['type' => 'string',   'required' => true],
                'user_email'   => ['type' => 'string',   'required' => true],
                'confirmed_at' => ['type' => 'datetime', 'required' => false],
            ]
        ],

        // 請求書確認（MF請求書の確認記録）
        'invoice_confirmations' => [
            'default' => [],
            'fields' => [
                'id'                => ['type' => 'string',   'required' => true],
                'mf_invoice_id'     => ['type' => 'string',   'required' => true],     // MF請求書ID
                'status'            => ['type' => 'string',   'required' => false],    // pending / confirmed
                'confirmed_by'      => ['type' => 'string',   'required' => false],    // 確認者メール
                'confirmed_at'      => ['type' => 'datetime', 'required' => false],    // 確認日時
                'requested_by'      => ['type' => 'string',   'required' => true],     // 登録者メール
                'requested_by_name' => ['type' => 'string',   'required' => false],    // 登録者名
                'created_at'        => ['type' => 'datetime', 'required' => false],
                'updated_at'        => ['type' => 'datetime', 'required' => false],
                'deleted_at'        => ['type' => 'datetime', 'required' => false],
                'deleted_by'        => ['type' => 'string',   'required' => false],
            ]
        ],

        // 週報コメント
        'report_comments' => [
            'default' => [],
        ],

        // マニュアル一覧（営業がトラブル時に検索→Google スライド等のリンクを開いて自己解決）
        // 閲覧: sales 以上 / 作成・編集: product 以上 / 削除: admin のみ
        'manuals' => [
            'default' => [],
            'fields' => [
                'id'              => ['type' => 'string',   'required' => true],
                'title'           => ['type' => 'string',   'required' => true],   // マニュアルタイトル
                'url'             => ['type' => 'string',   'required' => true],   // Google スライド/ドキュメント等のリンクURL
                'description'     => ['type' => 'string',   'required' => false],  // 概要（カードに表示）
                'search_keywords' => ['type' => 'string',   'required' => false],  // 検索キーワード（症状・別名・略語等を自由記述）
                'category'        => ['type' => 'string',   'required' => false],  // カテゴリ
                'tags'            => ['type' => 'array',    'required' => false],  // タグ配列
                'visible_to'      => ['type' => 'array',    'required' => false],  // 公開範囲。空=全員 / ["product","admin"]=製品技術部以上 / ["admin"]=管理部のみ
                'created_by'      => ['type' => 'string',   'required' => false],
                'created_at'      => ['type' => 'datetime', 'required' => false],
                'updated_at'      => ['type' => 'datetime', 'required' => false],
                'deleted_at'      => ['type' => 'datetime', 'required' => false],
                'deleted_by'      => ['type' => 'string',   'required' => false],
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
