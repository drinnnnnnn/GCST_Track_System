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

// Ensure configuration and database connection are loaded once
if (file_exists(__DIR__ . '/../config/config.php')) require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/migrations/MigrationManager.php';

try {
    $conn = Database::getConnection();
    require_once __DIR__ . '/email_helpers.php';

    // Run migrations to ensure email_notifications exists
    (new MigrationManager())->run();

    // Handle POST actions (Delete Log / Retry)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?: [];
        $action = $input['action'] ?? '';
        $id = (int)($input['id'] ?? 0);

        if ($action === 'delete_log' && $id > 0) {
            $stmt = $conn->prepare("DELETE FROM email_notifications WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $success = $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => $success]);
                exit;
            }
            echo json_encode(['success' => false, 'error' => 'Failed to prepare delete statement']);
            exit;
        } elseif ($action === 'send_email') {
            $res = sendEmailWithLog(
                $conn,
                $input['recipient'] ?? '',
                $input['subject'] ?? '',
                $input['message'] ?? '',
                $input['email_type'] ?? 'Manual Notification',
                []
            );
            echo json_encode($res);
            exit;
        } elseif ($action === 'retry_email' && $id > 0) {
            $stmt = $conn->prepare("SELECT recipient, subject, notification_type, email_body FROM email_notifications WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $log = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($log) {
                    $res = sendEmailWithLog(
                        $conn,
                        $log['recipient'] ?? '',
                        $log['subject'] ?? '',
                        $log['email_body'] ?? '',
                        $log['notification_type'] ?? 'Manual Notification',
                        []
                    );
                    echo json_encode($res);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Log not found']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to load notification log']);
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
    
    if ($search) {
        $where[] = "(recipient LIKE ? OR subject LIKE ? OR notification_type LIKE ?)";
        $searchTerm = "%$search%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
        $types .= "sss";
    }

    $whereClause = implode(" AND ", $where);

    // 1. Fetch metrics for the dashboard cards
    // These metrics are:
    // - sent_today: Number of emails with status 'sent' created today.
    // - failed_emails: Total number of emails with status 'failed'.
    // - pending_emails: Total number of emails with status 'pending'.
    // - total_emails_sent: Total number of emails with status 'sent'.
    $metricsSql = "SELECT 
        COUNT(CASE WHEN DATE(sent_at) = CURDATE() AND status = 'sent' THEN 1 END) as sent_today,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_emails,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_emails,
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as total_emails_sent
        FROM email_notifications";
    $metrics = $conn->query($metricsSql)->fetch_assoc();

    // 2. Fetch unique notification types for the filter dropdown
    $typeResult = $conn->query("SELECT DISTINCT notification_type FROM email_notifications WHERE notification_type IS NOT NULL AND notification_type <> ''");
    $uniqueTypes = [];
    while ($row = $typeResult->fetch_assoc()) {
        $uniqueTypes[] = $row['notification_type'];
    }

    // 3. Fetch the actual logs
    $logsSql = "SELECT id, recipient, subject, notification_type, status, sent_at AS created_at, error_message, email_body 
                FROM email_notifications 
                WHERE $whereClause 
                ORDER BY sent_at DESC LIMIT 100";
    $stmt = $conn->prepare($logsSql);
    if ($stmt === false) {
        throw new RuntimeException('Unable to prepare email log query.');
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

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