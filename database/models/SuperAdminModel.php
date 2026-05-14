<?php
require_once __DIR__ . '/../../config/db_connect.php';
// Ensure the core connection file is available for the Database class
require_once __DIR__ . '/../../database/connection.php';

class SuperAdminModel {
    private $conn;

    public function __construct() {
        // Use the centralized Database class instead of global variables
        $this->conn = Database::getConnection();
    }

    public function authenticate($identifier, $password) {
        $stmt = $this->conn->prepare("SELECT * FROM superadmins WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();

        if (!$admin) return false;

        // Check for temporary lockout
        if ($admin['lockout_until'] && strtotime($admin['lockout_until']) > time()) {
            return ['locked' => true, 'until' => $admin['lockout_until']];
        }

        if (password_verify($password, $admin['password_hash'])) {
            $this->resetFailedAttempts($admin['id']);
            return $admin;
        }

        $this->incrementFailedAttempts($admin['id']);
        return false;
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