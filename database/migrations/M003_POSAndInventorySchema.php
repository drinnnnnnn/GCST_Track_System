<?php
class M003_POSAndInventorySchema extends BaseMigration {
    public function getVersion(): string { return '20240101003'; }
    public function getName(): string { return 'POS and Product Metadata'; }

    public function up(): bool {
        // Main Transactions
        $this->conn->query("CREATE TABLE IF NOT EXISTS `cashier_transactions` ( 
            `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `transaction_number` VARCHAR(50) NOT NULL UNIQUE,
            `receipt_number` VARCHAR(100) DEFAULT NULL UNIQUE,
            `receipt_category` VARCHAR(100) DEFAULT NULL,
            `user_id` INT(11) DEFAULT NULL,
            `student_name` VARCHAR(255) DEFAULT NULL,
            `guest_school_id` VARCHAR(50) DEFAULT NULL,
            `guest_email` VARCHAR(255) DEFAULT NULL,
            `cashier_id` INT(11) NOT NULL,
            `transaction_type` ENUM('buy') NOT NULL,
            `items` TEXT NOT NULL,
            `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `payment_received` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `payment_status` ENUM('paid','pending','voided') NOT NULL DEFAULT 'pending',
            `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash',
            `check_number` VARCHAR(100) DEFAULT NULL,
            `is_expired` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY (`user_id`), KEY (`cashier_id`)
        ) ENGINE=InnoDB");
        if (!MigrationManager::columnExists($this->conn, 'cashier_transactions', 'payment_method')) {
            $this->conn->query("ALTER TABLE `cashier_transactions` ADD COLUMN `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash'");
        }
        if (!MigrationManager::columnExists($this->conn, 'cashier_transactions', 'check_number')) {
            $this->conn->query("ALTER TABLE `cashier_transactions` ADD COLUMN `check_number` VARCHAR(100) DEFAULT NULL");
        }
        if (!MigrationManager::columnExists($this->conn, 'cashier_transactions', 'receipt_category')) {
            $this->conn->query("ALTER TABLE `cashier_transactions` ADD COLUMN `receipt_category` VARCHAR(100) DEFAULT NULL");
        }
        // Transaction Items
        $this->conn->query("CREATE TABLE IF NOT EXISTS `transaction_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `cashier_transaction_id` INT(11) NOT NULL,
            `product_id` INT(11) NOT NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `item_type` ENUM('buy') NOT NULL,
            `quantity` DECIMAL(10,2) NOT NULL,
            `unit_price` DECIMAL(10,2) NOT NULL,
            `duration` INT(11) DEFAULT NULL,
            `duration_unit` VARCHAR(50) DEFAULT NULL,
            `unit_name` VARCHAR(50) DEFAULT NULL,
            `total_item_amount` DECIMAL(10,2) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_cashier_transaction` FOREIGN KEY (`cashier_transaction_id`) REFERENCES `cashier_transactions` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        // Product Enhancements (Books & Uniforms)
        $productMetadata = [
            'is_featured' => "TINYINT(1) DEFAULT 0",
            'book_author' => "VARCHAR(255)",
            'book_pages' => "INT",
            'book_course' => "VARCHAR(100)",
            'book_subject' => "VARCHAR(100)",
            'book_edition' => "VARCHAR(100)",
            'book_publisher' => "VARCHAR(255)",
            'book_isbn' => "VARCHAR(100)",
            'book_publication_year' => "INT",
            'uniform_course' => "VARCHAR(100)",
            'uniform_type' => "VARCHAR(100)",
            'uniform_upper_fabric' => "VARCHAR(100)",
            'uniform_lower_fabric' => "VARCHAR(100)",
            'uniform_min_yards' => "DECIMAL(10,2)",
            'uniform_material' => "VARCHAR(100)",
            'uniform_color' => "VARCHAR(100)"
        ];

        foreach ($productMetadata as $col => $def) {
            if (!MigrationManager::columnExists($this->conn, 'products', $col)) {
                $this->conn->query("ALTER TABLE `products` ADD COLUMN `$col` $def");
            }
        }

        // Ensure stock_count vs stock compatibility
        if (!MigrationManager::columnExists($this->conn, 'products', 'stock_count')) {
            $this->conn->query("ALTER TABLE `products` CHANGE COLUMN `stock` `stock_count` DECIMAL(10,2) DEFAULT 0.00");
        }

        return true;
    }
}