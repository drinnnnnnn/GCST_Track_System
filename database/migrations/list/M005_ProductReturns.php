<?php
class M005_ProductReturns extends BaseMigration {
    public function getVersion(): string { return '20261118002'; }
    public function getName(): string { return 'Damaged product returns'; }

    public function up(): bool {
        return $this->conn->query("CREATE TABLE IF NOT EXISTS `product_return_requests` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `cashier_transaction_id` INT(11) NOT NULL,
            `transaction_item_id` INT(11) NOT NULL,
            `product_id` INT(11) NOT NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `quantity` DECIMAL(10,2) NOT NULL,
            `unit_name` VARCHAR(50) DEFAULT NULL,
            `unit_price` DECIMAL(10,2) NOT NULL,
            `refund_amount` DECIMAL(10,2) NOT NULL,
            `request_type` ENUM('refund','replacement') NOT NULL,
            `damage_description` TEXT NOT NULL,
            `photo_filename` VARCHAR(100) NOT NULL,
            `status` ENUM('pending','refund_pending','refunded','replacement_pending','completed','rejected') NOT NULL DEFAULT 'pending',
            `reviewed_by` INT(11) DEFAULT NULL,
            `reviewed_at` DATETIME DEFAULT NULL,
            `fulfilled_by` INT(11) DEFAULT NULL,
            `fulfilled_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_product_returns_user` (`user_id`, `created_at`),
            KEY `idx_product_returns_status` (`status`, `created_at`),
            KEY `idx_product_returns_product_status` (`product_id`, `status`),
            KEY `idx_product_returns_transaction_item` (`transaction_item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") === true;
    }
}
