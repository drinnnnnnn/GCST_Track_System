<?php
require_once __DIR__ . '/../connection.php';

class QueueModel {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
        $this->conn->query("SET time_zone = '+08:00'"); // Synchronize with Asia/Manila
        $this->ensureTableExists();
    }

    public function getConnection() {
        return $this->conn;
    }

    private function ensureTableExists() {
        $tableName = 'queue_tickets'; // Use a variable for consistency
        $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) DEFAULT NULL,
            `queue_number` VARCHAR(50) NOT NULL,
            `student_name` VARCHAR(255) DEFAULT NULL,
            `purpose` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('waiting', 'serving', 'completed', 'cancelled') NOT NULL DEFAULT 'waiting',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `served_at` TIMESTAMP NULL DEFAULT NULL,
            `alert_sent` TINYINT(1) DEFAULT 0,
            `serving_alert_sent` TINYINT(1) DEFAULT 0,
            `expiry_alert_sent` TINYINT(1) DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_queue_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            // Attempt to create the table. If it exists, this does nothing.
            // If it doesn't exist and can't be created, mysqli_report should make this throw an exception.
            $this->conn->query($sql);

            // Ensure alert_sent column exists for existing tables (Fixes: Unknown column 'q.alert_sent')
            $res = $this->conn->query("SHOW COLUMNS FROM `$tableName` LIKE 'alert_sent'");
            if ($res && $res->num_rows === 0) {
                $this->conn->query("ALTER TABLE `$tableName` ADD COLUMN `alert_sent` TINYINT(1) DEFAULT 0 AFTER `served_at` ");
            }

            // Ensure expiry_alert_sent column exists
            $res = $this->conn->query("SHOW COLUMNS FROM `$tableName` LIKE 'expiry_alert_sent'");
            if ($res && $res->num_rows === 0) {
                $this->conn->query("ALTER TABLE `$tableName` ADD COLUMN `expiry_alert_sent` TINYINT(1) DEFAULT 0 AFTER `alert_sent` ");
            }

            // Ensure serving_alert_sent column exists for the new notification feature
            $res = $this->conn->query("SHOW COLUMNS FROM `$tableName` LIKE 'serving_alert_sent'");
            if ($res && $res->num_rows === 0) {
                $this->conn->query("ALTER TABLE `$tableName` ADD COLUMN `serving_alert_sent` TINYINT(1) DEFAULT 0 AFTER `alert_sent` ");
            }

            error_log("QueueModel: Attempted to ensure table '$tableName' structure is correct.");

            // Verify table existence immediately after the CREATE IF NOT EXISTS statement
            $checkSql = "SHOW TABLES LIKE '$tableName'";
            $result = $this->conn->query($checkSql);

            if ($result === false) {
                error_log("QueueModel: Failed to execute SHOW TABLES query for '$tableName': " . $this->conn->error);
                throw new Exception("Database error: Could not verify table '$tableName' existence.");
            }

            if ($result->num_rows === 0) {
                error_log("QueueModel: Critical error: Table '$tableName' does not exist after attempted creation. Check database permissions or connection.");
                throw new Exception("Critical database error: Required table '$tableName' is missing or could not be created.");
            }
            error_log("QueueModel: Table '$tableName' confirmed to exist.");
        } catch (mysqli_sql_exception $e) {
            error_log("QueueModel: mysqli_sql_exception during table '$tableName' check/creation: " . $e->getMessage());
            throw new Exception("Database schema initialization failed for table '$tableName': " . $e->getMessage());
        } catch (Exception $e) {
            error_log("QueueModel: General exception during table '$tableName' check/creation: " . $e->getMessage());
            throw $e; // Re-throw to propagate
        }
    }

    public function getTicketsByUserId($userId) {
        $stmt = $this->conn->prepare("SELECT * FROM queue_tickets WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM queue_tickets WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getByIdWithDetails($id) {
        $stmt = $this->conn->prepare("SELECT q.*, u.student_id as school_id, u.email, u.phone, u.contact_number, u.first_name, u.last_name 
                                     FROM queue_tickets q 
                                     LEFT JOIN users u ON q.user_id = u.id 
                                     WHERE q.id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Alias for getAllActive to maintain compatibility with action scripts.
     */
    public function getActiveQueues() {
        return $this->getAllActive();
    }

    /**
     * Checks if a user has generated a ticket within the last X seconds.
     * Used for backend rate-limiting/cooldown enforcement.
     */
    public function isUserOnCooldown($userId, $seconds = 120) {
        if (!$userId) return false;
        $stmt = $this->conn->prepare("
            SELECT id FROM queue_tickets 
            WHERE user_id = ? 
            AND created_at > (NOW() - INTERVAL ? SECOND) 
            LIMIT 1
        ");
        $stmt->bind_param("ii", $userId, $seconds);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    /**
     * Fetches counts for today's queue tickets.
     */
    public function getQueueCounts() {
        $today = date('Y-m-d');
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'waiting' THEN 1 END) as waiting,
                COUNT(CASE WHEN status = 'serving' THEN 1 END) as serving,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
            FROM queue_tickets 
            WHERE DATE(created_at) = ?
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getAllActive() {
        $sql = "SELECT q.*, u.student_id as school_id 
                FROM queue_tickets q 
                LEFT JOIN users u ON q.user_id = u.id 
                WHERE q.status IN ('waiting', 'serving') 
                ORDER BY q.status ASC, q.created_at ASC";
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Automatically cancels tickets that have been waiting or serving for more than 2 hours.
     */
    public function expireTickets() {
        // Use SQL intervals to ensure consistency with the database clock
        $sql = "UPDATE queue_tickets 
                SET status = 'cancelled' 
                WHERE status IN ('waiting', 'serving') 
                AND (
                    (status = 'waiting' AND created_at < (NOW() - INTERVAL 2 HOUR)) OR 
                    (status = 'serving' AND served_at < (NOW() - INTERVAL 1 HOUR))
                )";
        $this->conn->query($sql);
        return $this->conn->affected_rows;
    }

    /**
     * Retrieves the next person in line who hasn't been alerted yet.
     */
    public function getNextToNotify() {
        $sql = "SELECT q.*, u.email, u.phone, u.contact_number, u.first_name, u.last_name 
                FROM queue_tickets q 
                LEFT JOIN users u ON q.user_id = u.id 
                WHERE q.status = 'waiting' AND q.alert_sent = 0 
                ORDER BY q.created_at ASC LIMIT 1";
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }

    /**
     * Retrieves the person currently being served who hasn't been emailed yet.
     */
    public function getServingToNotify() {
        $sql = "SELECT q.*, u.email, u.phone, u.contact_number, u.first_name, u.last_name 
                FROM queue_tickets q 
                LEFT JOIN users u ON q.user_id = u.id 
                WHERE q.status = 'serving' AND q.serving_alert_sent = 0 
                ORDER BY q.served_at DESC LIMIT 1";
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }

    /**
     * Marks a ticket as having been notified.
     */
    public function markAlertSent($id) {
        $stmt = $this->conn->prepare("UPDATE queue_tickets SET alert_sent = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /**
     * Marks a ticket as having been notified that they are being served.
     */
    public function markServingAlertSent($id) {
        $stmt = $this->conn->prepare("UPDATE queue_tickets SET serving_alert_sent = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /**
     * Retrieves waiting tickets that are nearing their 2-hour expiration.
     * Threshold: minutes since creation (e.g., 110 mins for a 10-min warning).
     */
    public function getTicketsNearingExpiry($minutesThreshold = 110) {
        $sql = "SELECT q.*, u.email 
                FROM queue_tickets q 
                LEFT JOIN users u ON q.user_id = u.id 
                WHERE q.status = 'waiting' 
                AND q.expiry_alert_sent = 0 
                AND q.created_at < (NOW() - INTERVAL ? MINUTE)";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        
        $stmt->bind_param("i", $minutesThreshold);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Marks a ticket as having been notified about upcoming expiration.
     */
    public function markExpiryAlertSent($id) {
        $stmt = $this->conn->prepare("UPDATE queue_tickets SET expiry_alert_sent = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function updateStatus($id, $status) {
        $this->conn->begin_transaction();
        try {
            // Prevent processing tickets that have already expired or been completed
            $current = $this->getById($id);
            if ($current && in_array($current['status'], ['cancelled', 'completed']) && in_array($status, ['serving', 'completed'])) {
                throw new Exception("Ticket #{$current['queue_number']} is already {$current['status']} and cannot be modified.");
            }

            // Logic: Only one ticket can be 'serving' at a time.
            // If we start serving a new one, mark others as completed.
            if ($status === 'serving') {
                $this->conn->query("UPDATE queue_tickets SET status = 'completed', served_at = NOW() WHERE status = 'serving'");
            }

            // Set served_at timestamp for processed tickets
            $servedAtPart = in_array($status, ['serving', 'completed', 'cancelled']) ? ", served_at = IFNULL(served_at, NOW())" : "";

            $stmt = $this->conn->prepare("UPDATE queue_tickets SET status = ? $servedAtPart WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            $success = $stmt->execute();

            $this->conn->commit();
            return $success;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function getLatestGenerated() {
        $result = $this->conn->query("SELECT * FROM queue_tickets ORDER BY id DESC LIMIT 1");
        return $result->fetch_assoc();
    }

    /**
     * Creates a new queue ticket.
     * @param int|null $userId Numeric user ID
     * @param string|null $queueNumber Manual number or null for auto-gen
     */
    public function create($userId, $queueNumber, $studentName, $purpose) {
        if ($queueNumber === null) {
            $queueNumber = $this->generateQueueNumber();
        }

        $stmt = $this->conn->prepare("INSERT INTO queue_tickets (user_id, queue_number, student_name, purpose, status, created_at) VALUES (?, ?, ?, ?, 'waiting', NOW())");
        $stmt->bind_param("isss", $userId, $queueNumber, $studentName, $purpose);
        
        if ($stmt->execute()) {
            return ['id' => $this->conn->insert_id];
        }
        return false;
    }

    private function generateQueueNumber() {
        $today = date('Y-m-d');
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM queue_tickets WHERE DATE(created_at) = ?");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $count = ($res['total'] ?? 0) + 1;
        
        return "Q-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
?>