CREATE DATABASE IF NOT EXISTS `gcst_tracking_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gcst_tracking_db`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admincashier_acc` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) DEFAULT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(25) DEFAULT NULL,
  `signature_image` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('admincashier','cashier','superadmin') NOT NULL DEFAULT 'admincashier',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `products` (
  `product_id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_name` VARCHAR(150) NOT NULL,
  `product_author` VARCHAR(100) DEFAULT 'Unknown',
  `product_category` VARCHAR(50) DEFAULT NULL,
  `product_description` TEXT DEFAULT NULL,
  `product_image` VARCHAR(255) DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `rent_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock` INT(11) NOT NULL DEFAULT 0,
  `product_status` ENUM('available','unavailable') NOT NULL DEFAULT 'available',
  `barcode` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transactions` (
  `transaction_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `type` ENUM('buy','rent') NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 1,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `transaction_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`transaction_id`),
  KEY `idx_transactions_user` (`user_id`),
  KEY `idx_transactions_product` (`product_id`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_transactions_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `queue` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `queue_number` VARCHAR(20) NOT NULL,
  `status` ENUM('waiting','serving','completed','cancelled') NOT NULL DEFAULT 'waiting',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `served_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_queue_status` (`status`),
  CONSTRAINT `fk_queue_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tuition_fees` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `total_fees` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('Unpaid','Partial','Paid') NOT NULL DEFAULT 'Unpaid',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_tuition_fees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
