<?php
/**
 * Action: Cancel Queue Ticket
 * Handles secure ticket cancellation by students or administrators.
 */
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/QueueModel.php';
require_once __DIR__ . '/audit_helpers.php';

// Enforce JSON response even if PHP errors occur
header('Content-Type: application/json');
ini_set('display_errors', '0');

try {
    secureSessionStart();
    // Allow students, users, and admin staff to access this endpoint
    requireAuth(['student', 'user', 'admin', 'admincashier', 'superadmin']);

    $conn = Database::getConnection();
    
    // Parse JSON input
    $payload = json_decode(file_get_contents('php://input'), true);
    $ticketId = isset($payload['ticket_id']) ? intval($payload['ticket_id']) : null;

    if (!$ticketId) {
        throw new Exception('Invalid request: Ticket ID is required.');
    }

    $queueModel = new QueueModel();
    $ticket = $queueModel->getById($ticketId);

    if (!$ticket) {
        throw new Exception('The specified queue ticket does not exist.');
    }

    // Check if the ticket is already in a final state
    if (in_array($ticket['status'], ['completed', 'cancelled'])) {
        throw new Exception("Ticket #{$ticket['queue_number']} is already " . $ticket['status'] . ".");
    }

    // Security: Verify ownership unless the requester is an administrator
    $role = $_SESSION['role'] ?? 'users';
    $isAdmin = in_array($role, ['admincashier', 'superadmin']);
    
    if (!$isAdmin) {
        $sessionStudentId = $_SESSION['student_id'] ?? null;
        $sessionUserId = $_SESSION['user_id'] ?? null;
        $authorized = false;

        // Check by integer User ID first, fallback to student_id lookup
        if ($sessionUserId && intval($ticket['user_id']) === intval($sessionUserId)) {
            $authorized = true;
        } elseif ($sessionStudentId) {
            $uStmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? LIMIT 1");
            $uStmt->bind_param("s", $sessionStudentId);
            $uStmt->execute();
            $userRow = $uStmt->get_result()->fetch_assoc();
            if ($userRow && intval($ticket['user_id']) === intval($userRow['id'])) {
                $authorized = true;
            }
            $uStmt->close();
        }

        if (!$authorized) {
            throw new Exception('Access Denied: You are not authorized to cancel this ticket.');
        }
    }

    // Update ticket status to 'cancelled'
    if ($queueModel->updateStatus($ticketId, 'cancelled')) {
        $actorId = $isAdmin ? ($_SESSION['admin_id'] ?? $_SESSION['username']) : ($_SESSION['student_id'] ?? $_SESSION['user_id']);
        logAudit($conn, $role, $actorId, 'cancel_queue_ticket', "Cancelled queue ticket #{$ticket['queue_number']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Ticket successfully cancelled.',
            'queue_number' => $ticket['queue_number']
        ]);
    } else {
        throw new Exception('Database error: Unable to update ticket status.');
    }

} catch (Throwable $e) {
    http_response_code(400); // Bad Request for logical errors
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;