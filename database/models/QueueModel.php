<?php
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../migrations/MigrationManager.php';

class QueueModel {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
        $this->conn->query("SET time_zone = '+08:00'"); // Synchronize with Asia/Manila
        (new MigrationManager())->run();
    }

    public function getConnection() {
        return $this->conn;
    }

    private function ensureTableExists() {
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
        $stmt = $this->conn->prepare("SELECT q.*, u.student_id as school_id, u.email, u.first_name, u.last_name 
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
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN queue_type = 'priority' AND status = 'waiting' THEN 1 END) as priority_waiting
            FROM queue_tickets 
            WHERE DATE(created_at) = ?
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getAllActive() {
        // Fetch waiting/serving tickets plus today's completed tickets for real-time statistics
        $sql = "SELECT q.*, u.student_id as school_id 
                FROM queue_tickets q 
                LEFT JOIN users u ON q.user_id = u.id 
                WHERE q.status IN ('waiting', 'serving') OR (q.status = 'completed' AND DATE(q.created_at) = CURDATE())
                ORDER BY FIELD(q.status, 'serving', 'waiting', 'completed'), q.created_at ASC";
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
        $sql = "SELECT q.*, u.email, u.first_name, u.last_name 
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
        $sql = "SELECT q.*, u.email, u.first_name, u.last_name 
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

    public function updateStatus($id, $status, $windowNumber = null) {
        $this->conn->begin_transaction();
        try {
            // Prevent processing tickets that have already expired or been completed
            $current = $this->getById($id);
            if ($current && in_array($current['status'], ['cancelled', 'completed']) && $status === 'serving') {
                throw new Exception("Ticket #{$current['queue_number']} is already {$current['status']} and cannot be modified.");
            }

            // Logic: Only one ticket can be 'serving' per window at a time.
            if ($status === 'serving' && $windowNumber !== null) {
                $stmt_c = $this->conn->prepare("UPDATE queue_tickets SET status = 'completed', served_at = NOW() WHERE status = 'serving' AND window_number = ?");
                $stmt_c->bind_param("i", $windowNumber);
                $stmt_c->execute();
            }

            // Set served_at timestamp for processed tickets
            $servedAtPart = in_array($status, ['serving', 'completed', 'cancelled']) ? ", served_at = IFNULL(served_at, NOW())" : "";
            
            // Ensure windowNumber is an integer if provided
            $winVal = ($windowNumber !== null) ? intval($windowNumber) : null;
            $windowPart = ($winVal !== null) ? ", window_number = ?" : "";

            $sql = "UPDATE queue_tickets SET status = ? $servedAtPart $windowPart WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if ($winVal !== null) {
                $stmt->bind_param("sii", $status, $winVal, $id);
            } else {
                $stmt->bind_param("si", $status, $id);
            }

            $success = $stmt->execute();

            $this->conn->commit();
            return $success;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * Reassigns a currently serving ticket to a new window.
     * Ensures that the new window is cleared of any other serving tickets before reassigning.
     *
     * @param int $ticketId The ID of the ticket to reassign.
     * @param int $newWindowNumber The new window number to assign the ticket to.
     * @throws Exception If the ticket is not found, not serving, or already in the target window.
     * @return bool True on successful reassignment.
     */
    public function reassignTicket($ticketId, $newWindowNumber) {
        $this->conn->begin_transaction();
        try {
            $currentTicket = $this->getById($ticketId);

            if (!$currentTicket) {
                throw new Exception("Ticket not found.");
            }
            if ($currentTicket['status'] !== 'serving') {
                throw new Exception("Only serving tickets can be reassigned.");
            }
            if (intval($currentTicket['window_number']) === intval($newWindowNumber)) {
                throw new Exception("Ticket is already assigned to Window " . $newWindowNumber . ".");
            }

            // Use the existing updateStatus logic which handles completing other tickets in the new window
            // and updates the target ticket's window number.
            $success = $this->updateStatus($ticketId, 'serving', $newWindowNumber);

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
    public function create($userId, $queueNumber, $studentName, $purpose, $queueType = 'regular') {
        if ($queueNumber === null) {
            $queueNumber = $this->generateQueueNumber($queueType);
        }

        $stmt = $this->conn->prepare("INSERT INTO queue_tickets (user_id, queue_number, student_name, purpose, status, queue_type, created_at) VALUES (?, ?, ?, ?, 'waiting', ?, NOW())");
        $stmt->bind_param("issss", $userId, $queueNumber, $studentName, $purpose, $queueType);
        
        if ($stmt->execute()) {
            return ['id' => $this->conn->insert_id];
        }
        return false;
    }

    private function generateQueueNumber($type = 'regular') {
        $prefix = ($type === 'priority') ? 'PWD' : 'REG';
        $today = date('Y-m-d');
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM queue_tickets WHERE DATE(created_at) = ? AND queue_type = ?");
        $stmt->bind_param("ss", $today, $type);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $count = ($res['total'] ?? 0) + 1;
        
        return $prefix . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
?>