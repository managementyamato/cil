-- =====================================================================
-- 価格表 v2 (Phase 1): pl_products / pl_product_variants / pl_price_rules
-- 設計: docs/price-list-design.md
-- 安全: CREATE TABLE IF NOT EXISTS なので何度実行しても OK
-- =====================================================================

-- 製品マスタ
CREATE TABLE IF NOT EXISTS `pl_products` (
    `id`            VARCHAR(64)  NOT NULL PRIMARY KEY,
    `code`          VARCHAR(64)  DEFAULT NULL,
    `name`          VARCHAR(255) NOT NULL,
    `category`      VARCHAR(64)  DEFAULT NULL,
    `description`   TEXT         DEFAULT NULL,
    `display_order` INT          DEFAULT 0,
    `is_active`     TINYINT(1)   DEFAULT 1,
    `created_at`    DATETIME     DEFAULT NULL,
    `updated_at`    DATETIME     DEFAULT NULL,
    `deleted_at`    DATETIME     DEFAULT NULL,
    `deleted_by`    VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY `uq_code`     (`code`),
    INDEX `idx_category`     (`category`),
    INDEX `idx_active`       (`is_active`),
    INDEX `idx_deleted`      (`deleted_at`),
    INDEX `idx_display_order`(`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- バリアント (製品 × サイズ等)
CREATE TABLE IF NOT EXISTS `pl_product_variants` (
    `id`              VARCHAR(64)  NOT NULL PRIMARY KEY,
    `product_id`      VARCHAR(64)  NOT NULL,
    `size_label`      VARCHAR(64)  NOT NULL,
    `size_inch`       DECIMAL(6,2) DEFAULT NULL,
    `resolution`      VARCHAR(32)  DEFAULT NULL,
    `screen_area_m2`  DECIMAL(6,3) DEFAULT NULL,
    `attributes_json` JSON         DEFAULT NULL,
    `display_order`   INT          DEFAULT 0,
    `is_active`       TINYINT(1)   DEFAULT 1,
    `created_at`      DATETIME     DEFAULT NULL,
    `updated_at`      DATETIME     DEFAULT NULL,
    `deleted_at`      DATETIME     DEFAULT NULL,
    `deleted_by`      VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY `uq_product_size`  (`product_id`, `size_label`),
    INDEX `idx_product_id`        (`product_id`),
    INDEX `idx_size_inch`         (`size_inch`),
    INDEX `idx_active`            (`is_active`),
    INDEX `idx_deleted`           (`deleted_at`),
    INDEX `idx_display_order`     (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 価格ルール (本体価格)
CREATE TABLE IF NOT EXISTS `pl_price_rules` (
    `id`               BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `variant_id`       VARCHAR(64)  NOT NULL,
    `customer_rank`    ENUM('S','A','B') NOT NULL,
    `transaction_type` ENUM('sale','rental') NOT NULL,
    `price_label`      VARCHAR(64)  NOT NULL,
    `amount`           INT          NOT NULL,
    `notes`            VARCHAR(255) DEFAULT NULL,
    `display_order`    INT          DEFAULT 0,
    `created_at`       DATETIME     DEFAULT NULL,
    `updated_at`       DATETIME     DEFAULT NULL,
    `deleted_at`       DATETIME     DEFAULT NULL,
    `deleted_by`       VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY `uq_rule` (`variant_id`, `customer_rank`, `transaction_type`, `price_label`),
    INDEX `idx_variant_id`  (`variant_id`),
    INDEX `idx_rank`        (`customer_rank`),
    INDEX `idx_txn_type`    (`transaction_type`),
    INDEX `idx_deleted`     (`deleted_at`),
    INDEX `idx_display_order`(`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
