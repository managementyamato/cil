<?php
/**
 * JsonColumnHandler
 *
 * data.json → MySQL 移行で発生する「PHP 配列 ←→ DB カラム」の型変換を集約する。
 *
 * 責務:
 *   - JSON カラム / bool カラム / DATE/DATETIME カラムの定義を保持
 *   - PHP 値 → DB 値の変換（rowToDb 相当のロジック）
 *   - DB 値 → PHP 値の変換（dbToRow 相当のロジック）
 *
 * 注意: 本クラスは Database クラスのファサード内部から呼ばれる純粋ヘルパー。
 *       公開 API 自体は Database::rowToDb() / Database::dbToRow() のまま。
 *       挙動を変更しないこと（CLAUDE.md 最重要 #0 / refactor-policy.md）。
 *
 * 抽出元: config/database.php (Sprint 1, 2026-05-18)
 */

class JsonColumnHandler
{
    /** JSON カラムを持つエンティティの定義 */
    private static array $jsonColumns = [
        'projects'           => ['invoice_ids'],
        'customers'          => ['aliases', 'branches'],
        'tasks'              => ['subtasks', 'mentions'],
        'announcements'      => ['read_by'],
        'memos'              => ['tags'],
        'slides'             => ['required_for'],
        'weekly_reports'     => ['private_recipients'],
        'workflow_requests'  => ['approvers'],
        'mf_invoices'        => ['tag_names', 'items'],
        'invoices'           => ['tag_names'],
        'invoice_requests'   => ['items'],
        'manuals'            => ['tags', 'visible_to'],
    ];

    /** bool カラムを持つエンティティの定義 */
    private static array $boolColumns = [
        'announcements' => ['pinned'],
        'memos'         => ['pinned'],
        'employees'     => ['chat_member'],
    ];

    /** DATE/DATETIME カラム定義（空文字 ↔ NULL 変換用） */
    private static array $dateColumns = [
        'created_at', 'updated_at', 'deleted_at', 'confirmed_at', 'submitted_at',
        'reviewed_at', 'synced_at', 'last_read_at', 'email_token_expires_at', 'email_token_used_at',
        'due_date', 'deadline', 'occurrence_date', 'start_date', 'end_date',
        'billing_date', 'issue_date', 'sales_date', 'closing_date', 'payment_date',
        'join_date', 'leave_date', 'expires_at', 'meeting_date', 'week_start', 'week_end',
        'trade_start',
    ];

    public static function jsonColumnsFor(string $entity): array
    {
        return self::$jsonColumns[$entity] ?? [];
    }

    public static function boolColumnsFor(string $entity): array
    {
        return self::$boolColumns[$entity] ?? [];
    }

    public static function dateColumns(): array
    {
        return self::$dateColumns;
    }

    /**
     * PHP配列 → DB行（JSON/boolカラムをエンコード）
     */
    public static function rowToDb(string $entity, array $row): array
    {
        $jsonCols = self::$jsonColumns[$entity] ?? [];
        $boolCols = self::$boolColumns[$entity] ?? [];

        $dbRow = [];
        foreach ($row as $key => $value) {
            if (in_array($key, $jsonCols, true)) {
                $dbRow[$key] = $value !== null ? json_encode($value, JSON_UNESCAPED_UNICODE) : null;
            } elseif (in_array($key, $boolCols, true)) {
                $dbRow[$key] = $value ? 1 : 0;
            } elseif (in_array($key, self::$dateColumns, true)) {
                // 空文字はNULLに変換（MySQL DATE型が0000-00-00になるのを防止）
                $dbRow[$key] = ($value === '' || $value === null) ? null : $value;
            } elseif (is_array($value)) {
                // $jsonColumnsに未登録でも配列はJSON変換（Array to string conversion防止）
                $dbRow[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                $dbRow[$key] = $value;
            }
        }
        return $dbRow;
    }

    /**
     * DB行 → PHP配列（JSON/boolカラムをデコード）
     */
    public static function dbToRow(string $entity, array $row): array
    {
        // Note: カラム名の大文字/小文字はCREATE TABLE定義のまま返される
        // array_change_key_case は companyName 等の camelCase を破壊するため使用しない

        $jsonCols = self::$jsonColumns[$entity] ?? [];
        $boolCols = self::$boolColumns[$entity] ?? [];

        foreach ($row as $key => $value) {
            if (in_array($key, $jsonCols, true) && $value !== null) {
                $decoded = json_decode($value, true);
                $row[$key] = $decoded !== null ? $decoded : [];
            } elseif (in_array($key, $boolCols, true)) {
                $row[$key] = (bool)$value;
            } elseif (in_array($key, self::$dateColumns, true)) {
                // 0000-00-00 は空文字に変換（data.json互換）
                if ($value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                    $row[$key] = '';
                }
            }
        }
        return $row;
    }
}
