-- ============================================================
-- yamato_mgt テーブル定義（data.json から自動生成）
-- Generated: 2026-04-01 02:47:18
-- カラム名は data.json のキー名と完全一致
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- system_meta: キーバリューストア
DROP TABLE IF EXISTS `system_meta`;
CREATE TABLE `system_meta` (
    `meta_key`    VARCHAR(100) NOT NULL PRIMARY KEY,
    `meta_value`  LONGTEXT DEFAULT NULL,
    `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- projects
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `tag` VARCHAR(255) DEFAULT NULL,
    `sales_assignee` VARCHAR(255) DEFAULT NULL,
    `dealer_name` VARCHAR(255) DEFAULT NULL,
    `office_name` VARCHAR(255) DEFAULT NULL,
    `maker` VARCHAR(255) DEFAULT NULL,
    `led_size` VARCHAR(255) DEFAULT NULL,
    `lcd_size` VARCHAR(255) DEFAULT NULL,
    `cms_player` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(255) DEFAULT NULL,
    `memo` TEXT DEFAULT NULL,
    `chat_url` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `synced_from` VARCHAR(255) DEFAULT NULL,
    `product_category` VARCHAR(255) DEFAULT NULL,
    `occurrence_date` DATE DEFAULT NULL,
    `transaction_type` VARCHAR(255) DEFAULT NULL,
    `customer_name` VARCHAR(255) DEFAULT NULL,
    `general_contractor` VARCHAR(255) DEFAULT NULL,
    `postal_code` VARCHAR(255) DEFAULT NULL,
    `prefecture` VARCHAR(255) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `shipping_address` TEXT DEFAULT NULL,
    `product_series` VARCHAR(255) DEFAULT NULL,
    `product_name` VARCHAR(255) DEFAULT NULL,
    `product_spec` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `synced_name` VARCHAR(255) DEFAULT NULL,
    `internal_chat_room_id` VARCHAR(255) DEFAULT NULL,
    `chat_space_id` VARCHAR(255) DEFAULT NULL,
    `pending_chat_space` VARCHAR(255) DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `invoice_ids` JSON DEFAULT NULL,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- troubles
DROP TABLE IF EXISTS `troubles`;
CREATE TABLE `troubles` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `pj_number` VARCHAR(255) DEFAULT NULL,
    `trouble_content` TEXT DEFAULT NULL,
    `response_content` TEXT DEFAULT NULL,
    `reporter` VARCHAR(255) DEFAULT NULL,
    `responder` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(255) DEFAULT NULL,
    `date` VARCHAR(255) DEFAULT NULL,
    `call_no` VARCHAR(255) DEFAULT NULL,
    `project_contact` VARCHAR(255) DEFAULT NULL,
    `case_no` VARCHAR(255) DEFAULT NULL,
    `company_name` VARCHAR(255) DEFAULT NULL,
    `customer_name` VARCHAR(255) DEFAULT NULL,
    `honorific` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `synced_from_sheet` VARCHAR(255) DEFAULT NULL,
    `deadline` DATE DEFAULT NULL,
    `prevention_notes` TEXT DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- customers
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `companyName` VARCHAR(255) DEFAULT NULL,
    `aliases` JSON DEFAULT NULL,
    `branches` JSON DEFAULT NULL,
    `contactPerson` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `zipcode` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `mf_partner_id` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `source` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_company` (`companyName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- partners
DROP TABLE IF EXISTS `partners`;
CREATE TABLE `partners` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- employees
DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `area` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `memo` TEXT DEFAULT NULL,
    `vehicle_number` VARCHAR(255) DEFAULT NULL,
    `chat_user_id` VARCHAR(255) DEFAULT NULL,
    `qualifications` VARCHAR(255) DEFAULT NULL,
    `join_date` DATE DEFAULT NULL,
    `leave_date` DATE DEFAULT NULL,
    `chat_member` VARCHAR(255) DEFAULT NULL,
    `code` VARCHAR(255) DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `role` VARCHAR(255) DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- manufacturers
DROP TABLE IF EXISTS `manufacturers`;
CREATE TABLE `manufacturers` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `name` VARCHAR(255) DEFAULT NULL,
    `contact` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- invoices
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mf_invoices
DROP TABLE IF EXISTS `mf_invoices`;
CREATE TABLE `mf_invoices` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `billing_number` VARCHAR(255) DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `partner_name` VARCHAR(255) DEFAULT NULL,
    `billing_date` DATE DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `sales_date` DATE DEFAULT NULL,
    `subtotal` DECIMAL(15,2) DEFAULT NULL,
    `tax` DECIMAL(15,2) DEFAULT NULL,
    `total_amount` DECIMAL(15,2) DEFAULT NULL,
    `payment_status` VARCHAR(255) DEFAULT NULL,
    `posting_status` VARCHAR(255) DEFAULT NULL,
    `email_status` VARCHAR(255) DEFAULT NULL,
    `memo` TEXT DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `tag_names` TEXT DEFAULT NULL,
    `project_id` VARCHAR(255) DEFAULT NULL,
    `assignee` VARCHAR(255) DEFAULT NULL,
    `closing_date` DATE DEFAULT NULL,
    `pdf_url` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `synced_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- loans
DROP TABLE IF EXISTS `loans`;
CREATE TABLE `loans` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- repayments
DROP TABLE IF EXISTS `repayments`;
CREATE TABLE `repayments` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- invoice_templates
DROP TABLE IF EXISTS `invoice_templates`;
CREATE TABLE `invoice_templates` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- invoice_excel_templates
DROP TABLE IF EXISTS `invoice_excel_templates`;
CREATE TABLE `invoice_excel_templates` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- scheduled_invoices
DROP TABLE IF EXISTS `scheduled_invoices`;
CREATE TABLE `scheduled_invoices` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tasks
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `status` VARCHAR(255) DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `subtasks` JSON DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `mentions` JSON DEFAULT NULL,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- announcements
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `read_by` JSON DEFAULT NULL,
    `pinned` TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- memos
DROP TABLE IF EXISTS `memos`;
CREATE TABLE `memos` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `title` VARCHAR(255) DEFAULT NULL,
    `content` TEXT DEFAULT NULL,
    `pinned` TINYINT(1) DEFAULT 0,
    `tags` JSON DEFAULT NULL,
    `user_email` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_user` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- chat_rooms
DROP TABLE IF EXISTS `chat_rooms`;
CREATE TABLE `chat_rooms` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `type` VARCHAR(255) DEFAULT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `members` JSON DEFAULT NULL,
    `is_default` TINYINT(1) DEFAULT 0,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- chat_messages
DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `room_id` VARCHAR(255) DEFAULT NULL,
    `content` TEXT DEFAULT NULL,
    `mentions` JSON DEFAULT NULL,
    `user_email` VARCHAR(255) DEFAULT NULL,
    `user_name` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    INDEX `idx_room` (`room_id`),
    INDEX `idx_user` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- chat_read_status
DROP TABLE IF EXISTS `chat_read_status`;
CREATE TABLE `chat_read_status` (
    `user_email` VARCHAR(255) NOT NULL,
    `room_id` VARCHAR(36) NOT NULL,
    `last_read_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`user_email`, `room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- slides
DROP TABLE IF EXISTS `slides`;
CREATE TABLE `slides` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `title` VARCHAR(255) DEFAULT NULL,
    `google_docs_url` TEXT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `required_for` JSON DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- company_rules
DROP TABLE IF EXISTS `company_rules`;
CREATE TABLE `company_rules` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `chapter_number` INT DEFAULT NULL,
    `chapter_title` VARCHAR(255) DEFAULT NULL,
    `content` TEXT DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- contacts
DROP TABLE IF EXISTS `contacts`;
CREATE TABLE `contacts` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `category` VARCHAR(255) DEFAULT NULL,
    `scene` VARCHAR(255) DEFAULT NULL,
    `dept` VARCHAR(255) DEFAULT NULL,
    `ext` VARCHAR(255) DEFAULT NULL,
    `person` VARCHAR(255) DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `sort_order` INT DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `chat_room_id` VARCHAR(255) DEFAULT NULL,
    `chat_room_title` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- leads
DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- morning_todos
DROP TABLE IF EXISTS `morning_todos`;
CREATE TABLE `morning_todos` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `meeting_date` DATE DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `assignee` VARCHAR(255) DEFAULT NULL,
    `assignee_email` VARCHAR(255) DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `status` VARCHAR(255) DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- weekly_reports
DROP TABLE IF EXISTS `weekly_reports`;
CREATE TABLE `weekly_reports` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `user_email` VARCHAR(255) DEFAULT NULL,
    `user_name` VARCHAR(255) DEFAULT NULL,
    `week_start` DATE DEFAULT NULL,
    `week_end` DATE DEFAULT NULL,
    `status` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `sec_role` MEDIUMTEXT DEFAULT NULL,
    `sec_report` MEDIUMTEXT DEFAULT NULL,
    `sec_issues` MEDIUMTEXT DEFAULT NULL,
    `sec_next_goals` MEDIUMTEXT DEFAULT NULL,
    `sec_second_area` MEDIUMTEXT DEFAULT NULL,
    `sec_misc` MEDIUMTEXT DEFAULT NULL,
    `private_message` TEXT DEFAULT NULL,
    `private_recipients` JSON DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_user` (`user_email`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- discount_approvals
DROP TABLE IF EXISTS `discount_approvals`;
CREATE TABLE `discount_approvals` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `project_name` VARCHAR(255) DEFAULT NULL,
    `original_amount` DECIMAL(15,2) DEFAULT NULL,
    `discount_amount` DECIMAL(15,2) DEFAULT NULL,
    `reason` TEXT DEFAULT NULL,
    `status` VARCHAR(255) DEFAULT NULL,
    `applicant_email` VARCHAR(255) DEFAULT NULL,
    `applicant_name` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `reviewed_by` VARCHAR(255) DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `review_comment` TEXT DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `email_action_token` VARCHAR(255) DEFAULT NULL,
    `email_token_expires_at` DATETIME DEFAULT NULL,
    `email_token_used_at` DATETIME DEFAULT NULL,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- slide_confirmations
DROP TABLE IF EXISTS `slide_confirmations`;
CREATE TABLE `slide_confirmations` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `slide_id` VARCHAR(255) DEFAULT NULL,
    `user_email` VARCHAR(255) DEFAULT NULL,
    `confirmed_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_slide` (`slide_id`),
    INDEX `idx_user` (`user_email`),
    UNIQUE KEY `uq_slide_user` (`slide_id`, `user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

