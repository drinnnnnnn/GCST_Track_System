﻿<?php
/**
 * Always return JSON, even on fatal errors.
 */
header('Content-Type: application/json');
ini_set('display_errors', '0');

// Buffer output to prevent accidental leaks/warnings from corrupting JSON
if (ob_get_level() === 0) ob_start();

/**
 * Shutdown handler to catch fatal errors.
 */
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fatal Server Error: ' . $error['message']]);
    }
});

session_start();
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/QueueModel.php';

try {
    // Enable strict error reporting for mysqli to catch connection issues
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = Database::getConnection();
    if (!$conn) throw new Exception('Database connection failed.');

    $student_id = $_SESSION['student_id'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    if (!$student_id && !$userId) {
        echo json_encode(['success' => true, 'tickets' => []]);
        exit;
    }

    // 1. Resolve internal user ID from alphanumeric student_id if not in session
    if (!$userId && $student_id) {
        $lookup = $conn->prepare('SELECT id FROM users WHERE student_id = ? LIMIT 1');
        $lookup->bind_param('s', $student_id);
        $lookup->execute();
        $lookup->bind_result($userId);
        $userFound = $lookup->fetch();
        if ($userFound) $_SESSION['user_id'] = $userId;
        $lookup->close();

        if (!$userFound) {
            echo json_encode(['success' => true, 'tickets' => []]);
            exit;
        }
    }

    // 2. Fetch tickets using the model
    $queueModel = new QueueModel();
    $rawTickets = $queueModel->getTicketsByUserId($userId);

    $tickets = [];
    foreach ($rawTickets as $row) {
        $status = strtolower($row['status'] ?? 'waiting');
        $displayStatus = ($status === 'cancelled') ? 'expired' : $status;

        $tickets[] = [
            'id' => $row['id'],
            'ticket_number' => $row['queue_number'], // Unified naming
            'queue_number' => $row['queue_number'],  // Legacy support
            'status' => $status,
            'display_status' => ucfirst($displayStatus),
            'window_number' => $row['window_number'],
            'queue_type' => $row['queue_type'],
            'cashier_name' => $row['cashier_name'] ?? 'Assigning...',
            'created_at' => $row['created_at'],
            'served_at' => $row['served_at'],
            'student_name' => $row['student_name'],
            'purpose' => $row['purpose']
        ];
    }

    // Ensure data is UTF-8 encoded to prevent json_encode failure
    array_walk_recursive($tickets, function(&$item) {
        if (is_string($item)) $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
    });

    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true, 'tickets' => $tickets]);

} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    error_log("Error fetching user tickets: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
