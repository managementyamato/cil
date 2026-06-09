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
    -- 営業情報統合（M2: 顧客マスター × AM連動）
    `customer_code` VARCHAR(64) DEFAULT NULL,       -- 顧客コード (例 C-00031)
    `customer_rank` VARCHAR(8) DEFAULT NULL,         -- 解決済みランク S/A/B/C/D（`rank` はMySQL予約語のため customer_rank）
    `rank_mode` VARCHAR(16) DEFAULT 'auto',          -- auto | manual
    `rank_manual` VARCHAR(8) DEFAULT NULL,           -- 手動上書き値（rank_mode=manual のとき優先）
    `am_employee_id` VARCHAR(36) DEFAULT NULL,       -- 主担当AM (employees.id)
    `industry` VARCHAR(255) DEFAULT NULL,            -- 業種
    `trade_start` DATE DEFAULT NULL,                 -- 取引開始
    `credit_limit` DECIMAL(15,2) DEFAULT NULL,       -- 与信限度
    `area` VARCHAR(64) DEFAULT NULL,                 -- エリア（担当営業ID等）
    -- アカウントマネジメント（戦略アカウント管理リスト由来）
    `am_number` VARCHAR(16) DEFAULT NULL,            -- AMナンバー (AM1..)
    `account_status` VARCHAR(16) DEFAULT NULL,       -- 既存 / 休眠
    `account_type` VARCHAR(64) DEFAULT NULL,         -- 種別 (ディーラー等)
    `account_type_memo` VARCHAR(255) DEFAULT NULL,   -- 種別メモ
    `hq_location` VARCHAR(255) DEFAULT NULL,         -- 本社所在地
    `priority` VARCHAR(32) DEFAULT NULL,             -- 優先度
    `rank_challenge` VARCHAR(8) DEFAULT NULL,        -- ランク（チャレンジ/目標 S/A/B）
    `am_person` VARCHAR(64) DEFAULT NULL,            -- 担当（営業担当者名）
    `am_memo` TEXT DEFAULT NULL,                     -- メモ
    INDEX `idx_company` (`companyName`),
    INDEX `idx_am_number` (`am_number`),
    INDEX `idx_account_status` (`account_status`),
    INDEX `idx_am` (`am_employee_id`),
    INDEX `idx_rank` (`customer_rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- customer_cc: 顧客ごとのCC候補（メール作成時に必ずCCに入れる候補）
DROP TABLE IF EXISTS `customer_cc`;
CREATE TABLE `customer_cc` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `customer_id` VARCHAR(36) NOT NULL,
    `employee_id` VARCHAR(36) DEFAULT NULL,          -- 任意: employees.id と紐付け
    `name` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `role_label` VARCHAR(255) DEFAULT NULL,          -- 例: 西井部長（契約・問題対応）
    `note` VARCHAR(255) DEFAULT NULL,                -- 用途メモ 例: 進捗案件のみ
    `sort_order` INT DEFAULT 0,
    `created_by` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    `deleted_by` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_cc_customer` (`customer_id`),
    INDEX `idx_cc_deleted` (`deleted_at`)
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
