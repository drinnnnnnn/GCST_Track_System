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

try {
    require_once __DIR__ . '/../database/connection.php';
    $conn = Database::getConnection();

    // Handle POST actions (Delete Log / Retry)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $id = $input['id'] ?? 0;

        if ($action === 'delete_log' && $id) {
            $stmt = $conn->prepare("DELETE FROM email_notifications WHERE id = ?");
            $stmt->bind_param('i', $id);
            $success = $stmt->execute();
            echo json_encode(['success' => $success]);
            exit;
        }
        // Note: Retry logic would call functions from email_helpers.php
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
    
    if ($search) {
        $where[] = "(recipient LIKE ? OR subject LIKE ? OR notification_type LIKE ?)";
        $searchTerm = "%$search%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
        $types .= "sss";
    }

    $whereClause = implode(" AND ", $where);

    // 1. Fetch metrics for the dashboard cards
    $metricsSql = "SELECT 
        COUNT(CASE WHEN DATE(created_at) = CURDATE() AND status = 'sent' THEN 1 END) as sent_today,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_emails,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_emails,
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as total_emails_sent
        FROM email_notifications";
    $metrics = $conn->query($metricsSql)->fetch_assoc();

    // 2. Fetch unique notification types for the filter dropdown
    $typeResult = $conn->query("SELECT DISTINCT notification_type FROM email_notifications WHERE notification_type IS NOT NULL");
    $uniqueTypes = [];
    while ($row = $typeResult->fetch_assoc()) {
        $uniqueTypes[] = $row['notification_type'];
    }

    // 3. Fetch the actual logs
    $logsSql = "SELECT id, recipient, subject, notification_type, status, created_at, error_message 
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