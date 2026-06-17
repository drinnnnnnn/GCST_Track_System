<?php
/**
 * Action: Reassign Queue Ticket
 * Allows staff to transfer a currently serving ticket from one service window to another.
 */
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/QueueModel.php';
require_once __DIR__ . '/audit_helpers.php';

// Enforce JSON response
header('Content-Type: application/json');
ini_set('display_errors', '0'); // Suppress errors in production
ob_start();

try {
    secureSessionStart();

    // Allow authorized personnel to reassign tickets (including cashiers)
    requireAuth(['admin', 'admincashier', 'superadmin', 'cashier']);

    $conn = Database::getConnection();
    $queueModel = new QueueModel();

    // Parse JSON input
    $payload = json_decode(file_get_contents('php://input'), true);
    $ticketId = isset($payload['ticket_id']) ? intval($payload['ticket_id']) : null;
    $newWindowNumber = isset($payload['new_window_number']) ? intval($payload['new_window_number']) : null;

    // Input validation
    if (!$ticketId || !$newWindowNumber) {
        throw new Exception('Invalid request: Ticket ID and new window number are required.');
    }
    if ($newWindowNumber < 1 || $newWindowNumber > 3) { // Assuming 3 windows
        throw new Exception('Invalid window number. Must be between 1 and 3.');
    }

    $actorId = $_SESSION['admin_id'] ?? null;
    // Perform the reassignment using the QueueModel
    $success = $queueModel->reassignTicket($ticketId, $newWindowNumber, $actorId);

    if ($success) {
        $ticket = $queueModel->getById($ticketId); // Fetch updated ticket for logging
        if ($ticket && $actorId) {
            logAudit($conn, $_SESSION['role'], $actorId, 'reassign_queue_ticket', "Reassigned ticket #{$ticket['queue_number']} to Window {$newWindowNumber}");
        }
        
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => true, 'message' => 'Ticket reassigned successfully.']);
    } else {
        throw new Exception('Database error: Unable to reassign ticket.');
    }

} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

ob_end_flush();
exit;
