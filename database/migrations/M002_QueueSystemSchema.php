<?php
class M002_QueueSystemSchema extends BaseMigration {
    public function getVersion(): string { return '20240101002'; }
    public function getName(): string { return 'Queue Management System'; }

    public function up(): bool {
        $this->conn->query("CREATE TABLE IF NOT EXISTS `queue_tickets` (
            `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(11) DEFAULT NULL,
            `queue_number` VARCHAR(50) NOT NULL,
            `student_name` VARCHAR(255) DEFAULT NULL,
            `purpose` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('waiting', 'serving', 'completed', 'cancelled') NOT NULL DEFAULT 'waiting',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `served_at` TIMESTAMP NULL DEFAULT NULL,
            `alert_sent` TINYINT(1) DEFAULT 0,
            `serving_alert_sent` TINYINT(1) DEFAULT 0,
            `expiry_alert_sent` TINYINT(1) DEFAULT 0,
            KEY `idx_queue_user` (`user_id`)
        ) ENGINE=InnoDB");

        return true;
    }
}