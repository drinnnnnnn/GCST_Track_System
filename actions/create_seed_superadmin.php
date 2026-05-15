<?php
require_once __DIR__ . '/../database/models/SuperAdminModel.php';
require_once __DIR__ . '/../database/connection.php';

$username = 'superadmin';
$email = 'aldrinbautista0425@gmail.com';
$password = 'Admin123!'; 
$pin = '0425';
$first_name = 'Aldrin';
$last_name = 'Bautista';

$conn = Database::getConnection();
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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

// 2. Use the SuperAdminModel to register the account
// This ensures all model-level validations (like the 4-digit PIN check) are performed.
$superAdminModel = new SuperAdminModel();
$result = $superAdminModel->register($first_name, $last_name, $username, $email, $password, $pin);

if ($result['success']) {
    echo "Superadmin account '$username' ($email) created/updated successfully with PIN: $pin";
} else {
    echo "Action failed: " . ($result['error'] ?? 'Unknown error occurred (the account might already exist).');
}

$conn->close();
?>