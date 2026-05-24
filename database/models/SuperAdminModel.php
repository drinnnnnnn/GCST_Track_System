<?php
require_once __DIR__ . '/../../config/config.php';
// Ensure the core connection file is available for the Database class
require_once __DIR__ . '/../../database/connection.php';

class SuperAdminModel {
    private $conn;

    public function __construct() {
        // Use the centralized Database class instead of global variables
        $this->conn = Database::getConnection();
        $this->ensureTableExists();
    }

    /**
     * Automatically creates the superadmins table if it doesn't exist.
     * Ensures all fields for the 4-digit PIN and security lockout are properly initialized.
     */
    private function ensureTableExists() {
        $sql = "CREATE TABLE IF NOT EXISTS `superadmins` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `first_name` VARCHAR(100) NOT NULL,
            `last_name` VARCHAR(100) NOT NULL,
            `username` VARCHAR(50) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `security_pin_hash` VARCHAR(255) NOT NULL,
            `status` ENUM('active', 'inactive', 'suspended', 'locked') NOT NULL DEFAULT 'active',
            `failed_login_attempts` TINYINT UNSIGNED DEFAULT 0,
            `lockout_until` DATETIME DEFAULT NULL,
            `last_login_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_username` (`username`),
            UNIQUE KEY `uniq_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $sqlLogs = "CREATE TABLE IF NOT EXISTS `security_logs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `admin_id` INT UNSIGNED DEFAULT NULL,
            `event_type` VARCHAR(50) NOT NULL,
            `identifier` VARCHAR(100) NOT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` TEXT DEFAULT NULL,
            `details` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if (!$this->conn->query($sql)) {
            error_log("SuperAdminModel: superadmins table init failed: " . $this->conn->error);
        }
        if (!$this->conn->query($sqlLogs)) {
            error_log("SuperAdminModel: security_logs table init failed: " . $this->conn->error);
        }
    }

    /**
     * Registers a new Super Admin account with secure hashing and validation.
     * This logic ensures that only authorized accounts can be created with the correct role attributes.
     * 
     * @param string $firstName
     * @param string $lastName
     * @param string $username
     * @param string $email
     * @param string $password
     * @param string $pin 4-digit security PIN
     * @return array result status and error message if applicable
     */
    public function register($firstName, $lastName, $username, $email, $password, $pin) {
        // 1. Validation: Ensure the PIN is exactly 4 digits
        if (!preg_match('/^\d{4}$/', $pin)) {
            return ['success' => false, 'error' => 'Security PIN must be exactly 4 digits.'];
        }

        // 2. Check for existing users to prevent credential overlap
        $check = $this->conn->prepare("SELECT id FROM superadmins WHERE username = ? OR email = ? LIMIT 1");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $check->close();
            return ['success' => false, 'error' => 'Username or Email is already registered.'];
        }
        $check->close();

        // 3. Hash credentials and insert with an 'active' status for immediate authorization
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $pinHash = password_hash($pin, PASSWORD_BCRYPT);
        $status = 'active';

        $stmt = $this->conn->prepare("INSERT INTO superadmins (first_name, last_name, username, email, password_hash, security_pin_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $firstName, $lastName, $username, $email, $passwordHash, $pinHash, $status);
        $success = $stmt->execute();
        $stmt->close();
        return ['success' => $success];
    }

    /**
     * Authenticates a superadmin using email/username, password, and security PIN.
     * 
     * @param string $identifier Email or Username
     * @param string $password
     * @param string $pin 4-digit security PIN
     * @return array|false Returns admin data, lockout info, or error status
     */
    public function authenticate($identifier, $password, $pin) {
        $stmt = $this->conn->prepare("SELECT * FROM superadmins WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();

        if (!$admin) return false;

        // Check for temporary lockout
        if ($admin['lockout_until'] && strtotime($admin['lockout_until']) > time()) {
            return ['locked' => true, 'id' => $admin['id'], 'until' => $admin['lockout_until']];
        }

        // 1. Verify Password
        $passwordMatch = password_verify($password, $admin['password_hash']);
        
        // 2. Verify Security PIN (only if password is correct)
        $pinMatch = false;
        if ($passwordMatch && !empty($admin['security_pin_hash'])) {
            $pinMatch = password_verify($pin, $admin['security_pin_hash']);
        }

        if ($passwordMatch && $pinMatch) {
            $this->resetFailedAttempts($admin['id']);
            return $admin;
        }

        // Increment failures if password was wrong OR PIN was wrong
        $this->incrementFailedAttempts($admin['id']);

        // Return specific error for incorrect PIN to guide the UI
        if ($passwordMatch && !$pinMatch) {
            return ['error' => 'invalid_pin', 'id' => $admin['id']];
        }

        return false;
    }

    /**
     * Records a security event to the database.
     * 
     * @param int|null $adminId The ID of the admin if found
     * @param string $eventType e.g., 'login_success', 'login_failed', 'account_locked'
     * @param string $identifier The email or username attempted
     * @param string|null $ip The visitor's IP address
     * @param string|null $ua The visitor's User Agent
     */
    public function logEvent($adminId, $eventType, $identifier, $ip, $ua, $details = null) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO security_logs (admin_id, event_type, identifier, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("isssss", $adminId, $eventType, $identifier, $ip, $ua, $details);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            // Log failure to the system error log to avoid breaking the UI/Workflow
            error_log("Security Logging Failed: " . $e->getMessage());
        }
    }

    /**
     * Records a successful Admin/Cashier registration event.
     */
    public function logStaffRegistration($actorId, $targetEmail, $targetName, $targetRole) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $details = "Action: Admin/Cashier Account Registered | New Account: $targetName | Role: $targetRole";
        
        $this->logEvent($actorId, 'staff_registration', $targetEmail, $ip, $ua, $details);
    }

    /**
     * Securely updates the 4-digit security PIN with hashing.
     */
    public function updateSecurityPin($id, $pin) {
        if (!preg_match('/^\d{4}$/', $pin)) return false;
        
        $hash = password_hash($pin, PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare("UPDATE superadmins SET security_pin_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    private function incrementFailedAttempts($id) {
        // Lock for 15 minutes after 5 failed attempts
        $stmt = $this->conn->prepare("
            UPDATE superadmins 
            SET failed_login_attempts = failed_login_attempts + 1, 
                lockout_until = IF(failed_login_attempts >= 4, DATE_ADD(NOW(), INTERVAL 15 MINUTE), lockout_until) 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    private function resetFailedAttempts($id) {
        $stmt = $this->conn->prepare("UPDATE superadmins SET failed_login_attempts = 0, lockout_until = NULL, last_login_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}