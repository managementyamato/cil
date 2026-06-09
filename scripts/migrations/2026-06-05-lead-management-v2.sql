-- =====================================================================
-- リード管理 v2 (Phase 1)
--   1. business_cards (新規)   - 名刺の素データ
--   2. lead_activities (新規)  - リードのタイムライン
--   3. leads (ALTER)           - リード本体の項目追加
-- 設計: docs/lead-management-design.md
-- 安全: CREATE TABLE IF NOT EXISTS / ALTER は重複追加でエラーにならない手順
-- =====================================================================

-- ── 名刺 (素データ) ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `business_cards` (
    `id`                       VARCHAR(36)  NOT NULL PRIMARY KEY,
    `company_name`             VARCHAR(255) DEFAULT NULL,
    `person_name`              VARCHAR(255) DEFAULT NULL,
    `title`                    VARCHAR(255) DEFAULT NULL,
    `department`               VARCHAR(255) DEFAULT NULL,
    `phone`                    VARCHAR(64)  DEFAULT NULL,
    `mobile`                   VARCHAR(64)  DEFAULT NULL,
    `email`                    VARCHAR(255) DEFAULT NULL,
    `fax`                      VARCHAR(64)  DEFAULT NULL,
    `website`                  VARCHAR(512) DEFAULT NULL,
    `address`                  TEXT         DEFAULT NULL,
    `business_card_image_path` VARCHAR(512) DEFAULT NULL,
    `exchanged_at`             DATE         DEFAULT NULL,
    `ocr_source`               VARCHAR(32)  DEFAULT 'manual',
    `ocr_confidence`           TINYINT      DEFAULT NULL,
    `registered_by`            VARCHAR(255) DEFAULT NULL,
    `promoted_lead_id`         VARCHAR(36)  DEFAULT NULL,
    `notes`                    TEXT         DEFAULT NULL,
    `created_at`               DATETIME     DEFAULT NULL,
    `updated_at`               DATETIME     DEFAULT NULL,
    `deleted_at`               DATETIME     DEFAULT NULL,
    `deleted_by`               VARCHAR(255) DEFAULT NULL,
    INDEX `idx_company`        (`company_name`),
    INDEX `idx_person`         (`person_name`),
    INDEX `idx_exchanged_at`   (`exchanged_at`),
    INDEX `idx_registered_by`  (`registered_by`),
    INDEX `idx_promoted_lead`  (`promoted_lead_id`),
    INDEX `idx_deleted`        (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── リードのタイムライン ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lead_activities` (
    `id`              BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `lead_id`         VARCHAR(36)  NOT NULL,
    `type`            ENUM('status_change','manual_note','promotion','meeting','quote','system') NOT NULL DEFAULT 'manual_note',
    `from_status`     VARCHAR(32)  DEFAULT NULL,
    `to_status`       VARCHAR(32)  DEFAULT NULL,
    `title`           VARCHAR(255) DEFAULT NULL,
    `body`            TEXT         DEFAULT NULL,
    `occurred_at`     DATETIME     NOT NULL,
    `created_by`      VARCHAR(255) DEFAULT NULL,
    `created_by_name` VARCHAR(255) DEFAULT NULL,
    `created_at`      DATETIME     DEFAULT NULL,
    `deleted_at`      DATETIME     DEFAULT NULL,
    `deleted_by`      VARCHAR(255) DEFAULT NULL,
    INDEX `idx_lead_occurred` (`lead_id`, `occurred_at` DESC),
    INDEX `idx_type`          (`type`),
    INDEX `idx_created_by`    (`created_by`),
    INDEX `idx_deleted`       (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── leads テーブル拡張 (ALTER) ────────────────────────────────────
-- 重複追加でエラーにならないよう、INFORMATION_SCHEMA で存在確認してから ALTER。
-- 各カラムごとに SP 不要のシンプルな PROCEDURE で安全に流す。

DROP PROCEDURE IF EXISTS pl_add_col_if_missing;
DELIMITER $$
CREATE PROCEDURE pl_add_col_if_missing(
    IN p_table   VARCHAR(64),
    IN p_column  VARCHAR(64),
    IN p_defsql  TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND COLUMN_NAME  = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_defsql);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL pl_add_col_if_missing('leads', 'customer_id',          'VARCHAR(36) DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'business_card_id',     'VARCHAR(36) DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'dealer_name',          'VARCHAR(255) DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'dealer_branch',        'VARCHAR(255) DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'end_user_company',     'VARCHAR(255) DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'site_name',            'VARCHAR(255) DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'prefecture',           'VARCHAR(64) DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'product_name',         'VARCHAR(128) DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'product_size',         'VARCHAR(64) DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'transaction_type',     'VARCHAR(32) DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'confidence',           'TINYINT DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'quote_status',         'VARCHAR(32) DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'expected_close_date',  'DATE DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'last_activity_at',     'DATETIME DEFAULT NULL');
CALL pl_add_col_if_missing('leads', 'assigned_to',          'VARCHAR(255) DEFAULT NULL');

DROP PROCEDURE pl_add_col_if_missing;

-- ── 追加インデックス (重複は無視させる) ───────────────────────────
DROP PROCEDURE IF EXISTS pl_add_idx_if_missing;
DELIMITER $$
CREATE PROCEDURE pl_add_idx_if_missing(
    IN p_table   VARCHAR(64),
    IN p_index   VARCHAR(64),
    IN p_defsql  TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND INDEX_NAME   = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` ', p_defsql);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL pl_add_idx_if_missing('leads', 'idx_customer_id',         '(`customer_id`)');
CALL pl_add_idx_if_missing('leads', 'idx_assigned_to',         '(`assigned_to`)');
CALL pl_add_idx_if_missing('leads', 'idx_status_last_activity','(`status`, `last_activity_at`)');
CALL pl_add_idx_if_missing('leads', 'idx_confidence_status',   '(`confidence`, `status`)');

DROP PROCEDURE pl_add_idx_if_missing;
