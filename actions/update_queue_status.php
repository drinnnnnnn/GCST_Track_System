<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
require_once __DIR__ . '/NotificationService.php';

require_once __DIR__ . '/../database/models/QueueModel.php';

// Support both POST (FormData) and JSON input
$id = $_POST['id'] ?? $_POST['queue_id'] ?? null;
$status = $_POST['status'] ?? null;
$windowNumber = $_POST['window_number'] ?? null;

if (!$id || !$status) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? $data['queue_id'] ?? null;
    $status = $data['status'] ?? null;
    $windowNumber = $windowNumber ?? $data['window_number'] ?? null;
}

if (!$id || !in_array($status, ['waiting', 'serving', 'completed', 'cancelled'])) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid parameters']);
    exit;
}

try {
    $model = new QueueModel();
    // Capture the cashier ID from the session if the ticket is being served
    $servedBy = ($status === 'serving') ? ($_SESSION['admin_id'] ?? null) : null;

    // Pass windowNumber to ensure serving logic correctly clears previous tickets in that window
    $success = $model->updateStatus((int)$id, $status, $windowNumber, $servedBy);

    $updatedTicket = null;
    if ($success) {
        // Fetch the complete ticket object including joined user details (school_id, name)
        $updatedTicket = $model->getByIdWithDetails((int)$id);

        // Trigger "Next in Line" alerts when a ticket is moved to 'serving'
        if ($status === 'serving') {
            $nextTicket = $model->getNextToNotify();
            if ($nextTicket) {
                NotificationService::sendQueueAlert($nextTicket);
                $model->markAlertSent($nextTicket['id']);
            }
        }
    }

    echo json_encode(['success' => $success, 'ticket' => $updatedTicket]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>