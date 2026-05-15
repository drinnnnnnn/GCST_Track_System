<?php
require_once __DIR__ . '/../connection.php';

class QueueModel {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
        $this->ensureTableExists();
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
            PRIMARY KEY (`id`),
            KEY `idx_queue_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            // Attempt to create the table. If it exists, this does nothing.
            // If it doesn't exist and can't be created, mysqli_report should make this throw an exception.
            $this->conn->query($sql);
            error_log("QueueModel: Attempted to ensure table '$tableName' exists.");

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