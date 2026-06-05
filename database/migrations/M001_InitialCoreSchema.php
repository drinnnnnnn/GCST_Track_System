<?php
class M001_InitialCoreSchema extends BaseMigration {
    public function getVersion(): string { return '20240101001'; }
    public function getName(): string { return 'Core User and Admin Accounts'; }

    public function up(): bool {
        // Super Admins
        $this->conn->query("CREATE TABLE IF NOT EXISTS `superadmins` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `first_name` VARCHAR(100) NOT NULL,
            `last_name` VARCHAR(100) NOT NULL,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `security_pin_hash` VARCHAR(255) NOT NULL,
            `status` ENUM('active', 'inactive', 'suspended', 'locked') NOT NULL DEFAULT 'active',
            `failed_login_attempts` TINYINT UNSIGNED DEFAULT 0,
            `lockout_until` DATETIME DEFAULT NULL,
            `last_login_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // Security Logs
        $this->conn->query("CREATE TABLE IF NOT EXISTS `security_logs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `admin_id` INT UNSIGNED,
            `event_type` VARCHAR(50) NOT NULL,
            `identifier` VARCHAR(100) NOT NULL,
            `ip_address` VARCHAR(45),
            `user_agent` TEXT,
            `details` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // Students/Users
        $this->conn->query("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_id` VARCHAR(50) NOT NULL UNIQUE,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `first_name` VARCHAR(100) NOT NULL,
            `middle_name` VARCHAR(100),
            `last_name` VARCHAR(100) NOT NULL,
            `course` VARCHAR(100),
            `year_level` INT DEFAULT 1,
            `balance` DECIMAL(10,2) DEFAULT 0.00,
            `status` ENUM('pending', 'active', 'rejected', 'suspended') DEFAULT 'pending',
            `last_login` DATETIME,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // Admin/Cashier Accounts
        $this->conn->query("CREATE TABLE IF NOT EXISTS `admincashier_acc` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `first_name` VARCHAR(100) NOT NULL,
            `last_name` VARCHAR(100) NOT NULL,
            `middle_name` VARCHAR(100),
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `role` VARCHAR(50) DEFAULT 'admincashier',
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `last_login` DATETIME,
            `login_attempts` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // Email Notifications Log
        $this->conn->query("CREATE TABLE IF NOT EXISTS `email_notifications` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `recipient` VARCHAR(100) NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `notification_type` VARCHAR(50) NOT NULL,
            `status` ENUM('sent', 'failed', 'pending') NOT NULL DEFAULT 'pending',
            `error_message` TEXT,
            `email_body` LONGTEXT,
            `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`recipient`)
        ) ENGINE=InnoDB");

        // Password Resets
        $this->conn->query("CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(100) NOT NULL,
            `token` VARCHAR(10) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`email`), INDEX (`token`)
        ) ENGINE=InnoDB");

        return true;
    }
}