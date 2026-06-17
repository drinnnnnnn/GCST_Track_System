<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "gcst_tracking_db";

$mysqli = new mysqli($host, $username, $password);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$mysqli->select_db($dbname);
$mysqli->set_charset('utf8mb4');

function addColumnIfNotExists($conn, $table, $column, $definition) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$result || $result->num_rows === 0) {
        return $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
    return true;
}

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `student_id` VARCHAR(50) NOT NULL UNIQUE,
        `first_name` VARCHAR(100) NOT NULL,
        `last_name` VARCHAR(100) NOT NULL,
        `middle_name` VARCHAR(100) DEFAULT NULL,
        `email` VARCHAR(150) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `sex` ENUM('Male','Female') DEFAULT NULL,
        `course` VARCHAR(100) DEFAULT NULL,
        `year_section` VARCHAR(100) DEFAULT NULL,
        `contact_number` VARCHAR(25) DEFAULT NULL,
        `phone` VARCHAR(25) DEFAULT NULL,
        `address` VARCHAR(255) DEFAULT NULL,
        `role` ENUM('user','admin','cashier','admincashier','superadmin') NOT NULL DEFAULT 'user',
        `status` ENUM('Active','Inactive','Suspended') NOT NULL DEFAULT 'Active',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `admincashier_acc` (
        `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(100) DEFAULT NULL,
        `last_name` VARCHAR(100) NOT NULL,
        `first_name` VARCHAR(100) NOT NULL,
        `middle_name` VARCHAR(100) DEFAULT NULL,
        `email` VARCHAR(150) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('admincashier','cashier','superadmin') NOT NULL DEFAULT 'admincashier',
        `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `login_attempts` INT DEFAULT 0,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `products` (
        `product_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `product_name` VARCHAR(150) NOT NULL,
        `product_author` VARCHAR(100) DEFAULT 'Unknown',
        `product_category` VARCHAR(50) DEFAULT NULL,
        `product_description` TEXT DEFAULT NULL,
        `product_image` VARCHAR(255) DEFAULT NULL,
        `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `rent_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `stock` INT(11) NOT NULL DEFAULT 0,
        `stock_count` INT(11) NOT NULL DEFAULT 0,
        `product_status` ENUM('available','unavailable') NOT NULL DEFAULT 'available',
        `barcode` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `transactions` (
        `transaction_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(11) NOT NULL,
        `product_id` INT(11) NOT NULL,
        `type` ENUM('buy','rent') NOT NULL,
        `quantity` INT(11) NOT NULL DEFAULT 1,
        `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `transaction_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `queue` (
        `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(11) DEFAULT NULL,
        `student_name` VARCHAR(255) DEFAULT NULL,
        `purpose` VARCHAR(255) DEFAULT NULL,
        `queue_number` VARCHAR(20) NOT NULL,
        `status` ENUM('waiting','serving','completed','cancelled') NOT NULL DEFAULT 'waiting',
        `queue_type` ENUM('regular', 'priority') NOT NULL DEFAULT 'regular',
        `window_number` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `served_at` TIMESTAMP NULL DEFAULT NULL,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `queue_tickets` (
        `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(11) DEFAULT NULL,
        `queue_number` VARCHAR(50) NOT NULL,
        `student_name` VARCHAR(255) DEFAULT NULL,
        `purpose` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('waiting', 'serving', 'completed', 'cancelled') NOT NULL DEFAULT 'waiting',
        `queue_type` ENUM('regular', 'priority') NOT NULL DEFAULT 'regular',
        `window_number` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `served_at` TIMESTAMP NULL DEFAULT NULL,
        `alert_sent` TINYINT(1) DEFAULT 0,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `notification_preferences` (
        `student_id` VARCHAR(50) NOT NULL,
        `preferences` JSON NOT NULL,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`student_id`),
        FOREIGN KEY (`student_id`) REFERENCES `users`(`student_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `user_carts` (
        `student_id` VARCHAR(50) NOT NULL,
        `cart_items` JSON NOT NULL,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`student_id`),
        FOREIGN KEY (`student_id`) REFERENCES `users`(`student_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `tuition_fees` (
        `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(11) NOT NULL,
        `total_fees` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `total_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `payment_status` ENUM('Unpaid','Partial','Paid') NOT NULL DEFAULT 'Unpaid',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `active_rentals` (
        `rental_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `transaction_number` VARCHAR(50) NOT NULL,
        `student_id` VARCHAR(50) NOT NULL,
        `product_id` INT(11) NOT NULL,
        `quantity` INT(11) NOT NULL DEFAULT 1,
        `rental_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `return_date` DATETIME NOT NULL,
        `rejection_reason` TEXT DEFAULT NULL,
        `status` ENUM('active','returned','overdue','pending_renewal') NOT NULL DEFAULT 'active',
        KEY `idx_active_rentals_student` (`student_id`),
        KEY `idx_active_rentals_transaction` (`transaction_number`),
        CONSTRAINT `fk_active_rentals_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_active_rentals_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`student_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `cashier_transactions` ( 
        `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `transaction_number` VARCHAR(50) NOT NULL,
        `receipt_number` VARCHAR(100) DEFAULT NULL,
        `user_id` INT(11) DEFAULT NULL,
        `student_name` VARCHAR(255) DEFAULT NULL,
        `cashier_id` INT(11) NOT NULL,
        `transaction_type` ENUM('buy','rent','mixed') NOT NULL,
        `items` TEXT NOT NULL,
        `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `payment_received` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `payment_status` ENUM('paid','pending','voided') NOT NULL DEFAULT 'pending',
        `is_expired` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_transaction_number` (`transaction_number`),
        KEY `idx_cashier_transactions_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `email_notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `recipient` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `notification_type` VARCHAR(100) DEFAULT NULL,
        `status` ENUM('sent','failed') NOT NULL,
        `error_message` TEXT DEFAULT NULL,
        `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$mysqli->query(
    "CREATE TABLE IF NOT EXISTS `superadmins` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `first_name` VARCHAR(50) NOT NULL,
        `last_name` VARCHAR(50) NOT NULL,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `security_pin_hash` VARCHAR(255) NOT NULL,
        `status` ENUM('active', 'pending', 'suspended', 'rejected') DEFAULT 'pending',
        `failed_login_attempts` INT DEFAULT 0,
        `lockout_until` DATETIME DEFAULT NULL,
        `last_login_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// Add missing columns or alter column types in existing schemas.
addColumnIfNotExists($mysqli, 'users', 'role', "ENUM('user','admin','cashier','admincashier','superadmin') NOT NULL DEFAULT 'user'");
addColumnIfNotExists($mysqli, 'users', 'phone', "VARCHAR(25) DEFAULT NULL");
addColumnIfNotExists($mysqli, 'users', 'created_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
addColumnIfNotExists($mysqli, 'users', 'updated_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
addColumnIfNotExists($mysqli, 'admincashier_acc', 'role', "ENUM('admincashier','cashier','superadmin') NOT NULL DEFAULT 'admincashier'");
addColumnIfNotExists($mysqli, 'admincashier_acc', 'pin', "VARCHAR(10) DEFAULT NULL AFTER `password` ");
addColumnIfNotExists($mysqli, 'admincashier_acc', 'status', "ENUM('active','inactive') NOT NULL DEFAULT 'active'");
addColumnIfNotExists($mysqli, 'admincashier_acc', 'login_attempts', "INT DEFAULT 0 AFTER `status` ");
addColumnIfNotExists($mysqli, 'admincashier_acc', 'created_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
addColumnIfNotExists($mysqli, 'admincashier_acc', 'updated_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
addColumnIfNotExists($mysqli, 'products', 'rent_price', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
addColumnIfNotExists($mysqli, 'products', 'stock', "INT(11) NOT NULL DEFAULT 0");
addColumnIfNotExists($mysqli, 'products', 'product_status', "ENUM('available','unavailable') NOT NULL DEFAULT 'available'");
addColumnIfNotExists($mysqli, 'products', 'barcode', "VARCHAR(50) DEFAULT NULL");
addColumnIfNotExists($mysqli, 'queue', 'queue_number', "VARCHAR(20) NOT NULL");
addColumnIfNotExists($mysqli, 'queue', 'served_at', "TIMESTAMP NULL DEFAULT NULL");
addColumnIfNotExists($mysqli, 'queue', 'user_id', "INT(11) DEFAULT NULL");
addColumnIfNotExists($mysqli, 'queue', 'student_name', "VARCHAR(255) DEFAULT NULL AFTER `user_id` ");
addColumnIfNotExists($mysqli, 'queue', 'purpose', "VARCHAR(255) DEFAULT NULL AFTER `student_name` ");
addColumnIfNotExists($mysqli, 'queue', 'status', "ENUM('waiting','serving','completed','cancelled') NOT NULL DEFAULT 'waiting'");
addColumnIfNotExists($mysqli, 'queue', 'queue_type', "ENUM('regular', 'priority') NOT NULL DEFAULT 'regular' AFTER `status` ");
addColumnIfNotExists($mysqli, 'queue', 'window_number', "INT(11) DEFAULT NULL AFTER `queue_type` ");
addColumnIfNotExists($mysqli, 'queue_tickets', 'alert_sent', "TINYINT(1) DEFAULT 0 AFTER `served_at` ");
addColumnIfNotExists($mysqli, 'queue_tickets', 'queue_type', "ENUM('regular', 'priority') NOT NULL DEFAULT 'regular' AFTER `status` ");
addColumnIfNotExists($mysqli, 'queue_tickets', 'window_number', "INT(11) DEFAULT NULL AFTER `queue_type` ");

// Ensure critical columns exist in active_rentals for the renewal system
addColumnIfNotExists($mysqli, 'active_rentals', 'rental_date', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `quantity` ");
addColumnIfNotExists($mysqli, 'active_rentals', 'transaction_number', "VARCHAR(50) NOT NULL AFTER `rental_id` ");
addColumnIfNotExists($mysqli, 'active_rentals', 'status', "ENUM('active','returned','overdue','pending_renewal') NOT NULL DEFAULT 'active'");
addColumnIfNotExists($mysqli, 'active_rentals', 'rejection_reason', "TEXT DEFAULT NULL AFTER `status` ");
addColumnIfNotExists($mysqli, 'active_rentals', 'overdue_charge', "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `status` ");
addColumnIfNotExists($mysqli, 'cashier_transactions', 'payment_status', "ENUM('paid','pending','voided') NOT NULL DEFAULT 'pending'");

// Ensure email log columns match the helper logic
addColumnIfNotExists($mysqli, 'email_notifications', 'notification_type', "VARCHAR(100) DEFAULT NULL AFTER `subject` ");
addColumnIfNotExists($mysqli, 'email_notifications', 'error_message', "TEXT DEFAULT NULL AFTER `status` ");
addColumnIfNotExists($mysqli, 'email_notifications', 'phone_number', "VARCHAR(20) DEFAULT NULL AFTER `recipient` "); // NEW: Add phone_number column
addColumnIfNotExists($mysqli, 'email_notifications', 'email_body', "LONGTEXT DEFAULT NULL AFTER `error_message` ");

// If the queue_number column exists as INT, ensure it is text-capable.
$columnInfo = $mysqli->query("SHOW COLUMNS FROM `queue` LIKE 'queue_number'");
if ($columnInfo && $columnInfo->num_rows > 0) {
    $info = $columnInfo->fetch_assoc();
    if (strpos($info['Type'], 'int') !== false) {
        $mysqli->query("ALTER TABLE `queue` MODIFY `queue_number` VARCHAR(20) NOT NULL");
    }
}

$columnInfo = $mysqli->query("SHOW COLUMNS FROM `queue` LIKE 'status'");
if ($columnInfo && $columnInfo->num_rows > 0) {
    $info = $columnInfo->fetch_assoc();
    if (strpos($info['Type'], "'cancelled'") === false) {
        $mysqli->query("ALTER TABLE `queue` MODIFY `status` ENUM('waiting','serving','completed','cancelled') NOT NULL DEFAULT 'waiting'");
    }
}

// Avoid generated columns for compatibility; use aliases in queries instead.

// Indexes for queue table.
$columnInfo = $mysqli->query("SHOW INDEX FROM `queue` WHERE Key_name = 'idx_queue_status'");
if ($columnInfo && $columnInfo->num_rows === 0) {
    $mysqli->query("ALTER TABLE `queue` ADD INDEX idx_queue_status (`status`)");
}

$columnInfo = $mysqli->query("SHOW INDEX FROM `transactions` WHERE Key_name = 'idx_transactions_user'");
if ($columnInfo && $columnInfo->num_rows === 0) {
    $mysqli->query("ALTER TABLE `transactions` ADD INDEX idx_transactions_user (`user_id`)");
}

$columnInfo = $mysqli->query("SHOW INDEX FROM `transactions` WHERE Key_name = 'idx_transactions_product'");
if ($columnInfo && $columnInfo->num_rows === 0) {
    $mysqli->query("ALTER TABLE `transactions` ADD INDEX idx_transactions_product (`product_id`)");
}

echo "Database and tables created or updated successfully.";
$mysqli->close();
?>
