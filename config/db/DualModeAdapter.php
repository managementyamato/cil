<?php
/**
 * DualModeAdapter
 *
 * data.json と MySQL の dual mode 運用に関わる「モード判定」と
 * 「エンティティ分類（テーブル / system_meta）」を集約する。
 *
 * 責務:
 *   - 現在の DB モード判定（json / db / dual）
 *   - エンティティが system_meta 行に格納されるべきか、独立テーブルか の判定
 *
 * 公開 API:
 *   - getMode(): string
 *   - isEnabled(): bool
 *   - isMetaEntity(string): bool
 *   - isTableEntity(string): bool
 *   - metaEntities(): array
 *   - tableEntities(): array
 *
 * Database クラスは本クラスに delegate する形でモード判定を行う。
 * 公開 API の挙動は Database::getMode() / Database::isEnabled() と完全一致させること。
 *
 * 抽出元: config/database.php (Sprint 1, 2026-05-18)
 */

class DualModeAdapter
{
    /** system_meta に格納するエンティティ */
    private static array $metaEntities = [
        'assignees',
        'productCategories',
        'settings',
        'mf_sync_timestamp',
        'customers_sync_timestamp',
        'mf_sync_history',
        'finance',
        'troubleResponders',
        'areas',
        'mf_partners_sync_timestamp',
        'comments',
        'report_comments',
        'admin_messages',
        'email_logs',
        'contact_masters',
        'customer_ranks',
    ];

    /** テーブルとして存在するエンティティ */
    private static array $tableEntities = [
        'projects', 'troubles', 'customers', 'partners', 'employees',
        'manufacturers', 'invoices', 'mf_invoices', 'loans', 'repayments',
        'invoice_templates', 'invoice_excel_templates', 'scheduled_invoices',
        'tasks', 'announcements', 'memos',
        'slides', 'company_rules', 'contacts', 'leads',
        'morning_todos', 'weekly_reports', 'discount_approvals',
        'slide_confirmations',
        'workflow_requests', 'reminders',
        'manuals',
        'invoice_confirmations',
        'invoice_requests', 'monthly_profits',
    ];

    /**
     * 現在の DB モードを取得
     *   json = data.json のみ
     *   db   = MySQL のみ
     *   dual = 両方に書き込み、読み込みは MySQL
     */
    public static function getMode(): string
    {
        return env('DB_MODE', 'json');
    }

    /**
     * DB モード（MySQL 側）が有効か（db / dual のいずれか）
     */
    public static function isEnabled(): bool
    {
        return in_array(self::getMode(), ['db', 'dual'], true);
    }

    public static function isMetaEntity(string $entity): bool
    {
        return in_array($entity, self::$metaEntities, true);
    }

    public static function isTableEntity(string $entity): bool
    {
        return in_array($entity, self::$tableEntities, true);
    }

    public static function metaEntities(): array
    {
        return self::$metaEntities;
    }

    public static function tableEntities(): array
    {
        return self::$tableEntities;
    }
}
