<?php
require_once __DIR__ . '/../config/db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = 'superadmin';
$email = 'aldrinbautista0425@gmail.com';
$password = 'Admin123!'; // Default password - change this after your first login
$pin = '0425';
$first_name = 'Aldrin';
$last_name = 'Bautista';
$status = 'active';

$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$hashed_pin = password_hash($pin, PASSWORD_DEFAULT);

// 1. Ensure the superadmins table exists with security features
$tableSql = "CREATE TABLE IF NOT EXISTS `superadmins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `security_pin_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `status` ENUM('active', 'suspended', 'locked') DEFAULT 'active',
    `failed_login_attempts` TINYINT UNSIGNED DEFAULT 0,
    `lockout_until` DATETIME NULL,
    `last_login_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$conn->query($tableSql);

// 2. Insert or update the superadmin account
$sql = "INSERT INTO superadmins (username, email, password_hash, security_pin_hash, first_name, last_name, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        password_hash = VALUES(password_hash), 
        security_pin_hash = VALUES(security_pin_hash), 
        status = VALUES(status)";

$stmt = $conn->prepare($sql);
$stmt->bind_param('sssssss', $username, $email, $hashed_password, $hashed_pin, $first_name, $last_name, $status);

if ($stmt->execute()) {
    echo "Superadmin account '$username' ($email) created/updated successfully with PIN: $pin";
} else {
    echo "Error creating account: " . $stmt->error;
}

$stmt->close();
$conn->close();