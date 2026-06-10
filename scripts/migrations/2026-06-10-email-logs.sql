-- email_logs テーブル: Gmail API 経由の送信メール履歴
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `from_address` VARCHAR(255) NOT NULL,
    `to_address` TEXT NOT NULL,
    `subject` VARCHAR(500) NOT NULL DEFAULT '',
    `body` TEXT,
    `gmail_message_id` VARCHAR(255) DEFAULT NULL,
    `sent_by` VARCHAR(255) DEFAULT NULL,
    `sent_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sent_at` (`sent_at`),
    INDEX `idx_sent_by` (`sent_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
