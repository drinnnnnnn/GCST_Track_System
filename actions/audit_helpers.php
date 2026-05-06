<?php
// actions/audit_helpers.php

/**
 * Logs an audit event to the database.
 *
 * @param mysqli $conn The database connection object.
 * @param string $actorType The type of actor (e.g., 'student', 'admincashier', 'superadmin').
 * @param string|int $actorId The ID of the actor.
 * @param string $action The action performed (e.g., 'login', 'checkout', 'profile_update').
 * @param string $details Additional details about the action.
 * @return void
 */
function logAudit(mysqli $conn, string $actorType, string|int $actorId, string $action, string $details = ''): void {
    // Ensure the audit_logs table exists
    $createTableSql = "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        actor_type VARCHAR(50) NOT NULL,
        actor_id VARCHAR(100) NOT NULL,
        action VARCHAR(128) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createTableSql);

    // Check for error_message column in audit_logs and add if missing
    $checkColumn = $conn->query("SHOW COLUMNS FROM `audit_logs` LIKE 'error_message'");
    if ($checkColumn && $checkColumn->num_rows === 0) {
        $conn->query("ALTER TABLE `audit_logs` ADD COLUMN `error_message` TEXT DEFAULT NULL AFTER `details`");
    }

    $stmt = $conn->prepare('INSERT INTO audit_logs (actor_type, actor_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
    if ($stmt) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $actorIdStr = (string)$actorId; // Ensure actor_id is treated as string for VARCHAR column
        $stmt->bind_param('ssssss', $actorType, $actorIdStr, $action, $details, $ipAddress, $userAgent);
        $stmt->execute();
        $stmt->close();
    }
}
?>