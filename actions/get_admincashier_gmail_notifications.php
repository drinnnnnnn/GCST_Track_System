<?php
require_once __DIR__ . '/security.php'; // Ensure security.php is included first
header('Content-Type: application/json');
require_once 'admincashier_report_helpers.php';
require_once __DIR__ . '/email_helpers.php'; // Use SMTP helper

$conn = connectAdminCashierDb();
if (!$conn) {
    echo json_encode(['error' => 'Connection failed']);
    exit;
}

$emailTable = 'email_logs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = trim($input['action'] ?? '');

    switch ($action) {
        case 'send_email':
            handleSendEmail($conn, $emailTable, $input);
            break;
        case 'retry_email':
            handleRetryEmail($conn, $emailTable, $input);
            break;
        case 'delete_log':
            handleDeleteLog($conn, $emailTable, $input);
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    $conn->close();
    exit;
}

$filters = [
    'status' => strtolower(trim($_GET['status'] ?? '')),
    'email_type' => trim($_GET['email_type'] ?? ''),
    'from_date' => sanitizeDate($_GET['from_date'] ?? ''),
    'to_date' => sanitizeDate($_GET['to_date'] ?? ''),
    'search' => trim($_GET['search'] ?? '')
];

$response = [
    'sent_today' => getEmailsSentToday($conn, $emailTable),
    'failed_emails' => getFailedEmails($conn, $emailTable),
    'pending_emails' => getPendingEmails($conn, $emailTable),
    'total_emails_sent' => getTotalEmailsSent($conn, $emailTable),
    'email_logs' => getEmailLogs($conn, $emailTable, 100, $filters),
    'email_types' => getEmailTypes($conn, $emailTable)
];

echo json_encode($response);
$conn->close();

function handleSendEmail($conn, $table, $data) {
    $recipient = trim($data['recipient'] ?? '');
    $subject = trim($data['subject'] ?? '');
    $message = trim($data['message'] ?? '');
    $emailType = trim($data['email_type'] ?? 'General');

    if (!$recipient || !$subject) {
        echo json_encode(['error' => 'Recipient and subject are required']);
        return;
    }

    $result = sendEmailWithLog($conn, $recipient, $subject, $message, $emailType);
    
    if ($result['status'] === 'sent') {
        echo json_encode(['success' => true, 'status' => 'sent']);
    } else {
        echo json_encode(['error' => $result['message']]);
    }
}

function handleRetryEmail($conn, $table, $data) {
    $id = intval($data['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid log ID']);
        return;
    }

    $stmt = $conn->prepare("UPDATE `$table` SET status = 'sent', sent_at = NOW() WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['error' => 'Unable to prepare retry']);
        return;
    }
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Unable to update email status']);
    }
    $stmt->close();
}

function handleDeleteLog($conn, $table, $data) {
    $id = intval($data['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid log ID']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM `$table` WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['error' => 'Unable to prepare delete']);
        return;
    }
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Unable to delete email log']);
    }
    $stmt->close();
}

function getEmailsSentToday($conn, $table) {
    $sql = "SELECT COUNT(*) as count FROM `$table` WHERE DATE(sent_at) = CURDATE() AND status = 'sent'";
    $result = $conn->query($sql);
    return $result ? intval($result->fetch_assoc()['count'] ?? 0) : 0;
}

function getFailedEmails($conn, $table) {
    $sql = "SELECT COUNT(*) as count FROM `$table` WHERE status = 'failed'";
    $result = $conn->query($sql);
    return $result ? intval($result->fetch_assoc()['count'] ?? 0) : 0;
}

function getPendingEmails($conn, $table) {
    $sql = "SELECT COUNT(*) as count FROM `$table` WHERE status = 'pending'";
    $result = $conn->query($sql);
    return $result ? intval($result->fetch_assoc()['count'] ?? 0) : 0;
}

function getTotalEmailsSent($conn, $table) {
    $sql = "SELECT COUNT(*) as count FROM `$table` WHERE status = 'sent'";
    $result = $conn->query($sql);
    return $result ? intval($result->fetch_assoc()['count'] ?? 0) : 0;
}

function getEmailLogs($conn, $table, $limit = 100, $filters = []) {
    $conditions = [];

    if (!empty($filters['status']) && in_array($filters['status'], ['sent', 'failed', 'pending'], true)) {
        $conditions[] = "status = '" . $conn->real_escape_string($filters['status']) . "'";
    }
    if ($filters['email_type'] !== '') {
        $conditions[] = "email_type = '" . $conn->real_escape_string($filters['email_type']) . "'";
    }
    if ($filters['from_date']) {
        $conditions[] = "DATE(sent_at) >= '" . $conn->real_escape_string($filters['from_date']) . "'";
    }
    if ($filters['to_date']) {
        $conditions[] = "DATE(sent_at) <= '" . $conn->real_escape_string($filters['to_date']) . "'";
    }
    if ($filters['search'] !== '') {
        $search = $conn->real_escape_string($filters['search']);
        $conditions[] = "(recipient LIKE '%$search%' OR subject LIKE '%$search%' OR email_type LIKE '%$search%')";
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $sql = "SELECT id, recipient, subject, email_type, status, sent_at, message FROM `$table` $whereClause ORDER BY sent_at DESC LIMIT ?";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    $logs = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $logs[] = [
                'id' => $row['id'],
                'recipient' => $row['recipient'],
                'subject' => $row['subject'],
                'email_type' => $row['email_type'],
                'status' => $row['status'],
                'timestamp' => $row['sent_at'],
                'message' => $row['message'] ?? ''
            ];
        }
    }

    if ($stmt) {
        $stmt->close();
    }

    return $logs;
}

function getEmailTypes($conn, $table) {
    $types = [];
    $sql = "SELECT DISTINCT email_type FROM `$table` ORDER BY email_type ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $types[] = $row['email_type'];
        }
    }
    return $types;
}
?>