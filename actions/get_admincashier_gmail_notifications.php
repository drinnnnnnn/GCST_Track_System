<?php
/**
 * Fetches email notification logs and metrics for the Admin Cashier dashboard.
 * Handles filtering by status, type, date range, and search query.
 */
header('Content-Type: application/json');
ini_set('display_errors', '0'); // Ensure no HTML errors break JSON parsing

require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admin', 'admincashier', 'superadmin']);

// Load configuration for API keys
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
}

try {
    require_once __DIR__ . '/../database/connection.php';
    $conn = Database::getConnection();
    require_once __DIR__ . '/email_helpers.php';

    // Ensure table structure matches code requirements
    $conn->query("CREATE TABLE IF NOT EXISTS email_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient VARCHAR(255) NOT NULL,
        subject VARCHAR(255),
        notification_type VARCHAR(50),
        phone_number VARCHAR(20) DEFAULT NULL, -- NEW: Add phone_number column
        status VARCHAR(20),
        error_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Handle POST actions (Delete Log / Retry)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $id = $input['id'] ?? 0;

        if ($action === 'delete_log' && $id) {
            $stmt = $conn->prepare("DELETE FROM email_notifications WHERE id = ?");
            $stmt->bind_param('i', $id);
            $success = $stmt->execute(); // Consider adding error handling here
            echo json_encode(['success' => $success]);
            exit;
        } elseif ($action === 'send_email') {
            $res = sendEmailWithLog($conn, $input['recipient'], $input['subject'], $input['message'], $input['email_type'], [], $input['phone_number'] ?? null);
            echo json_encode($res);
            exit;
        } elseif ($action === 'retry_email' && $id) {
            $stmt = $conn->prepare("SELECT * FROM email_notifications WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $log = $stmt->get_result()->fetch_assoc();
            if ($log) {
                $res = sendEmailWithLog($conn, $log['recipient'], $log['subject'], $log['email_body'], $log['notification_type'], [], $log['phone_number']);
                echo json_encode($res);
            } else {
                echo json_encode(['success' => false, 'error' => 'Log not found']);
            }
            exit;
        }
    }

    // Handle GET parameters for filtering
    $status = $_GET['status'] ?? '';
    $email_type = $_GET['email_type'] ?? ''; // Mapped from frontend filter
    $from_date = $_GET['from_date'] ?? '';
    $to_date = $_GET['to_date'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = ["1=1"];
    $params = [];
    $types = "";

    if ($status) { $where[] = "status = ?"; $params[] = $status; $types .= "s"; }
    if ($email_type) { $where[] = "notification_type = ?"; $params[] = $email_type; $types .= "s"; }
    if ($from_date) { $where[] = "DATE(created_at) >= ?"; $params[] = $from_date; $types .= "s"; }
    if ($to_date) { $where[] = "DATE(created_at) <= ?"; $params[] = $to_date; $types .= "s"; }
    
    if ($search) { // Updated search to include phone_number
        $where[] = "(recipient LIKE ? OR phone_number LIKE ? OR subject LIKE ? OR notification_type LIKE ?)";
        $searchTerm = "%$search%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        $types .= "ssss";
    }

    $whereClause = implode(" AND ", $where);

    // 1. Fetch metrics for the dashboard cards
    // These metrics are:
    // - sent_today: Number of emails with status 'sent' created today.
    // - failed_emails: Total number of emails with status 'failed'.
    // - pending_emails: Total number of emails with status 'pending'.
    // - total_emails_sent: Total number of emails with status 'sent'.
    $metricsSql = "SELECT 
        COUNT(CASE WHEN DATE(created_at) = CURDATE() AND status = 'sent' THEN 1 END) as sent_today,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_emails,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_emails,
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as total_emails_sent
        FROM email_notifications";
    $metrics = $conn->query($metricsSql)->fetch_assoc();

    // 2. Fetch unique notification types for the filter dropdown
    // The column name for notification type is 'notification_type'.
    $typeResult = $conn->query("SELECT DISTINCT notification_type FROM email_notifications WHERE notification_type IS NOT NULL");
    $uniqueTypes = [];
    while ($row = $typeResult->fetch_assoc()) {
        $uniqueTypes[] = $row['notification_type'];
    }

    // 3. Fetch the actual logs
    $logsSql = "SELECT id, recipient, phone_number, subject, notification_type, status, created_at, error_message, email_body as message 
                FROM email_notifications 
                WHERE $whereClause 
                ORDER BY created_at DESC LIMIT 100";
    $stmt = $conn->prepare($logsSql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => array_merge($metrics, [
            'email_logs' => $logs,
            'email_types' => $uniqueTypes
        ])
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}