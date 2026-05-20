-- ============================================================
-- yamato_mgt テーブル定義（data.json から自動生成）
-- Generated: 2026-04-06 02:42:36
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
    `customer_name` VARCHAR(255) DEFAULT NULL,
    `sales_assignee` VARCHAR(255) DEFAULT NULL,
    `dealer_name` VARCHAR(255) DEFAULT NULL,
    `office_name` VARCHAR(255) DEFAULT NULL,
    `maker` VARCHAR(255) DEFAULT NULL,
    `led_size` VARCHAR(255) DEFAULT NULL,
    `lcd_size` VARCHAR(255) DEFAULT NULL,
    `cms_player` VARCHAR(255) DEFAULT NULL,
    `memo` TEXT DEFAULT NULL,
    `chat_url` TEXT DEFAULT NULL,
    `chat_space_id` VARCHAR(255) DEFAULT NULL,
    `pending_chat_space` VARCHAR(255) DEFAULT NULL,
    `invoice_ids` JSON DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `tag` VARCHAR(255) DEFAULT NULL,
    `product_category` VARCHAR(255) DEFAULT NULL,
    `occurrence_date` DATE DEFAULT NULL,
    `transaction_type` VARCHAR(255) DEFAULT NULL,
    `general_contractor` VARCHAR(255) DEFAULT NULL,
    `postal_code` VARCHAR(255) DEFAULT NULL,
    `prefecture` VARCHAR(255) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `shipping_address` TEXT DEFAULT NULL,
    `product_series` VARCHAR(255) DEFAULT NULL,
    `product_name` VARCHAR(255) DEFAULT NULL,
    `product_spec` VARCHAR(255) DEFAULT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(255) DEFAULT NULL,
    -- PJ管理台帳統合（2026-05-07: pj-ledger.json から projects に統合）
    `no` INT DEFAULT NULL,
    `space` VARCHAR(50) DEFAULT NULL,
    `invoice_number` VARCHAR(255) DEFAULT NULL,
    `sales_dept` VARCHAR(255) DEFAULT NULL,
    `ya_person` VARCHAR(255) DEFAULT NULL,
    `branch_name` VARCHAR(255) DEFAULT NULL,
    `contact_email` VARCHAR(255) DEFAULT NULL,
    `indoor_outdoor` VARCHAR(50) DEFAULT NULL,
    `pitch` VARCHAR(50) DEFAULT NULL,
    `mic1` VARCHAR(255) DEFAULT NULL,
    `mic2` VARCHAR(255) DEFAULT NULL,
    `orientation` VARCHAR(50) DEFAULT NULL,
    `color` VARCHAR(50) DEFAULT NULL,
    `router` VARCHAR(255) DEFAULT NULL,
    `construction_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `warranty_end_date` DATE DEFAULT NULL,
    `rental_days` INT DEFAULT NULL,
    `sales_working_days` INT DEFAULT NULL,
    `period_months` DECIMAL(10,2) DEFAULT NULL,
    `horizontal_panels` INT DEFAULT NULL,
    `vertical_panels` INT DEFAULT NULL,
    `total_panels` INT DEFAULT NULL,
    `total_sales_estimate` DECIMAL(15,2) DEFAULT NULL,
    `actual_invoice_amount` DECIMAL(15,2) DEFAULT NULL,
    `monthly_rental_sales` DECIMAL(15,2) DEFAULT NULL,
    `additional_sales` DECIMAL(15,2) DEFAULT NULL,
    `initial_cost` DECIMAL(15,2) DEFAULT NULL,
    `discount_amount` DECIMAL(15,2) DEFAULT NULL,
    `additional_material_cost` DECIMAL(15,2) DEFAULT NULL,
    `support_material_cost` DECIMAL(15,2) DEFAULT NULL,
    `expenses` DECIMAL(15,2) DEFAULT NULL,
    `profit` DECIMAL(15,2) DEFAULT NULL,
    `deviation_rate` DECIMAL(10,6) DEFAULT NULL,
    `profit_rate` DECIMAL(10,6) DEFAULT NULL,
    `tech_cost_ratio_estimate` DECIMAL(10,6) DEFAULT NULL,
    `tech_cost_ratio_actual` DECIMAL(10,6) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `legacy_ledger_id` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- monthly_profits（月次利益・PJ管理台帳から統合）
DROP TABLE IF EXISTS `monthly_profits`;
CREATE TABLE `monthly_profits` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `project_id` VARCHAR(255) DEFAULT NULL,
    `month` VARCHAR(20) DEFAULT NULL,
    `amount` DECIMAL(15,2) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_month` (`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- troubles
DROP TABLE IF EXISTS `troubles`;
CREATE TABLE `troubles` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `project_id` VARCHAR(255) DEFAULT NULL,
    `project_name` VARCHAR(255) DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `priority` VARCHAR(255) DEFAULT NULL,
    `responder` VARCHAR(255) DEFAULT NULL,
    `deadline` DATE DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `pj_number` VARCHAR(255) DEFAULT NULL,
    `trouble_content` TEXT DEFAULT NULL,
    `response_content` TEXT DEFAULT NULL,
    `reporter` VARCHAR(255) DEFAULT NULL,
    `date` VARCHAR(255) DEFAULT NULL,
    `call_no` VARCHAR(255) DEFAULT NULL,
    `project_contact` VARCHAR(255) DEFAULT NULL,
    `case_no` VARCHAR(255) DEFAULT NULL,
    `company_name` VARCHAR(255) DEFAULT NULL,
    `customer_name` VARCHAR(255) DEFAULT NULL,
    `honorific` VARCHAR(255) DEFAULT NULL,
    `synced_from_sheet` VARCHAR(255) DEFAULT NULL,
    `prevention_notes` TEXT DEFAULT NULL,
    `status` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- customers
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `companyName` VARCHAR(255) DEFAULT NULL,
    `aliases` JSON DEFAULT NULL,
    `branches` JSON DEFAULT NULL,
    `contact` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `contactPerson` VARCHAR(255) DEFAULT NULL,
    `zipcode` VARCHAR(255) DEFAULT NULL,
    `mf_partner_id` VARCHAR(255) DEFAULT NULL,
    `source` VARCHAR(255) DEFAULT NULL,
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
    `email` VARCHAR(255) DEFAULT NULL,
    `department` VARCHAR(255) DEFAULT NULL,
    `role` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `area` VARCHAR(255) DEFAULT NULL,
    `memo` TEXT DEFAULT NULL,
    `vehicle_number` VARCHAR(255) DEFAULT NULL,
    `chat_user_id` VARCHAR(255) DEFAULT NULL,
    `qualifications` VARCHAR(255) DEFAULT NULL,
    `join_date` DATE DEFAULT NULL,
    `leave_date` DATE DEFAULT NULL,
    `chat_member` TINYINT(1) NOT NULL DEFAULT 0,
    `code` VARCHAR(255) DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- manufacturers
DROP TABLE IF EXISTS `manufacturers`;
CREATE TABLE `manufacturers` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `contact` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `name` VARCHAR(255) DEFAULT NULL
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
    `items` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `synced_at` DATETIME DEFAULT NULL,
    `mf_id` VARCHAR(255) DEFAULT NULL,
    `customer_name` VARCHAR(255) DEFAULT NULL,
    `amount` DECIMAL(15,2) DEFAULT NULL,
    `issue_date` DATE DEFAULT NULL,
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
    `due_date` DATE DEFAULT NULL,
    `subtasks` JSON DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `mentions` JSON DEFAULT NULL,
    `status` VARCHAR(255) DEFAULT NULL,
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
    `email` VARCHAR(255) DEFAULT NULL,
    `person` VARCHAR(255) DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `sort_order` INT DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `chat_room_id` VARCHAR(255) DEFAULT NULL,
    `chat_room_title` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- leads (営業リード／名刺OCR・手入力で登録される見込み顧客)
DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `company_name` VARCHAR(255) NOT NULL,
    `person_name` VARCHAR(255) DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `department` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(64) DEFAULT NULL,
    `mobile` VARCHAR(64) DEFAULT NULL,
    `fax` VARCHAR(64) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `website` VARCHAR(512) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `status` VARCHAR(32) DEFAULT '新規',
    `source` VARCHAR(32) DEFAULT 'manual',
    `business_card_image_path` VARCHAR(512) DEFAULT NULL,
    `am` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_company` (`company_name`),
    INDEX `idx_status` (`status`),
    INDEX `idx_deleted` (`deleted_at`),
    INDEX `idx_created` (`created_at`)
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
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(255) DEFAULT NULL,
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
    `sec_role` MEDIUMTEXT DEFAULT NULL,
    `sec_report` MEDIUMTEXT DEFAULT NULL,
    `sec_issues` MEDIUMTEXT DEFAULT NULL,
    `sec_next_goals` MEDIUMTEXT DEFAULT NULL,
    `sec_second_area` MEDIUMTEXT DEFAULT NULL,
    `sec_misc` MEDIUMTEXT DEFAULT NULL,
    `private_message` TEXT DEFAULT NULL,
    `private_recipients` JSON DEFAULT NULL,
    `submitted_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(255) DEFAULT NULL,
    `confirmed_at` DATETIME DEFAULT NULL,
    `confirmed_by` VARCHAR(255) DEFAULT NULL,
    `confirmed_by_name` VARCHAR(255) DEFAULT NULL,
    `confirm_token` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_user` (`user_email`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- discount_approvals
DROP TABLE IF EXISTS `discount_approvals`;
CREATE TABLE `discount_approvals` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `project_name` VARCHAR(255) DEFAULT NULL,
    `rental_period` VARCHAR(255) DEFAULT NULL,
    `sales_amount` VARCHAR(255) DEFAULT NULL,
    `original_amount` DECIMAL(15,2) DEFAULT NULL,
    `discount_amount` DECIMAL(15,2) DEFAULT NULL,
    `reason` TEXT DEFAULT NULL,
    `applicant_email` VARCHAR(255) DEFAULT NULL,
    `applicant_name` VARCHAR(255) DEFAULT NULL,
    `reviewed_by` VARCHAR(255) DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `review_comment` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `email_action_token` VARCHAR(255) DEFAULT NULL,
    `email_token_expires_at` DATETIME DEFAULT NULL,
    `email_token_used_at` DATETIME DEFAULT NULL,
    `status` VARCHAR(255) DEFAULT NULL,
    `drive_file_id` VARCHAR(255) DEFAULT NULL,
    `drive_view_link` TEXT DEFAULT NULL,
    `drive_download_link` TEXT DEFAULT NULL,
    `drive_file_name` VARCHAR(500) DEFAULT NULL,
    `original_name` VARCHAR(500) DEFAULT NULL,
    `last_resent_at` DATETIME DEFAULT NULL,
    `last_resent_by` VARCHAR(255) DEFAULT NULL,
    `resend_count` INT DEFAULT 0,
    `resubmitted_at` DATETIME DEFAULT NULL,
    `resubmit_count` INT DEFAULT 0,
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

-- workflow_requests
DROP TABLE IF EXISTS `workflow_requests`;
CREATE TABLE `workflow_requests` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    `approvers` JSON DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- reminders
DROP TABLE IF EXISTS `reminders`;
CREATE TABLE `reminders` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- invoice_confirmations（MF請求書の確認記録）
DROP TABLE IF EXISTS `invoice_confirmations`;
CREATE TABLE `invoice_confirmations` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `mf_invoice_id` VARCHAR(255) NOT NULL,
    `status` VARCHAR(50) DEFAULT 'pending',
    `confirmed_by` VARCHAR(255) DEFAULT NULL,
    `confirmed_at` DATETIME DEFAULT NULL,
    `requested_by` VARCHAR(255) NOT NULL,
    `requested_by_name` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_ic_mf_invoice` (`mf_invoice_id`),
    INDEX `idx_ic_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- manuals（営業向け トラブル解決マニュアルリンク集 / Google スライド等のリンクを登録）
DROP TABLE IF EXISTS `manuals`;
CREATE TABLE `manuals` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `url` TEXT NOT NULL,
    `description` TEXT DEFAULT NULL,
    `search_keywords` TEXT DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `tags` JSON DEFAULT NULL,
    `visible_to` JSON DEFAULT NULL,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_category` (`category`),
    INDEX `idx_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- invoice_requests（営業部からの請求書作成依頼）
DROP TABLE IF EXISTS `invoice_requests`;
CREATE TABLE `invoice_requests` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    -- 取込元情報
    `source` VARCHAR(50) DEFAULT 'manual',
    `source_row_id` VARCHAR(255) DEFAULT NULL,
    `source_timestamp` DATETIME DEFAULT NULL,
    -- 基本情報
    `requester_name` VARCHAR(255) DEFAULT NULL,
    `attached_file_id` VARCHAR(255) DEFAULT NULL,
    `pj_number` VARCHAR(50) DEFAULT NULL,
    `subject` TEXT DEFAULT NULL,
    -- 請求先情報
    `partner_name` VARCHAR(255) DEFAULT NULL,
    `partner_department` VARCHAR(255) DEFAULT NULL,
    `mf_partner_id` VARCHAR(50) DEFAULT NULL,
    `billing_method_1` VARCHAR(50) DEFAULT NULL,
    `billing_method_2` VARCHAR(50) DEFAULT NULL,
    -- 依頼種別
    `request_type` VARCHAR(100) DEFAULT NULL,
    -- レンタル詳細
    `billing_start_date` DATE DEFAULT NULL,
    `payment_due_date` DATE DEFAULT NULL,
    `closing_day` VARCHAR(50) DEFAULT NULL,
    `rental_period` VARCHAR(50) DEFAULT NULL,
    `auto_renew` TINYINT(1) DEFAULT 0,
    `has_prorated` TINYINT(1) DEFAULT 0,
    -- 品目（JSON配列）
    `items` JSON DEFAULT NULL,
    -- メモ
    `notes` TEXT DEFAULT NULL,
    `special_notes` TEXT DEFAULT NULL,
    -- ステータスとMF連携
    `status` VARCHAR(50) DEFAULT 'pending',
    `mf_initial_billing_id` VARCHAR(50) DEFAULT NULL,
    `mf_initial_billing_url` TEXT DEFAULT NULL,
    `mf_recurring_billing_id` VARCHAR(50) DEFAULT NULL,
    `mf_sent_at` DATETIME DEFAULT NULL,
    `mf_sent_by` VARCHAR(255) DEFAULT NULL,
    `mf_error_message` TEXT DEFAULT NULL,
    -- メタデータ
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_pj_number` (`pj_number`),
    INDEX `idx_source_row` (`source_row_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
