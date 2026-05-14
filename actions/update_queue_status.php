<?php
header('Content-Type: application/json');
session_start();

// Ensure only logged-in administrators can update queue statuses
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../database/models/QueueModel.php';

// Handle incoming JSON data from the fetch request
$data = json_decode(file_get_contents('php://input'), true);
$queue_id = $data['queue_id'] ?? null;
$status = $data['status'] ?? null;

// Validate that the necessary parameters were provided
if (!$queue_id || !$status) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// Define allowed status updates for the cashier dashboard
$allowed_statuses = ['serving', 'completed', 'cancelled'];
if (!in_array($status, $allowed_statuses, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    $queueModel = new QueueModel();
    $success = $queueModel->updateStatus($queue_id, $status);

    echo json_encode(['success' => (bool)$success]);
} catch (Throwable $e) {
    error_log("Queue status update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred while updating queue.']);
}
?>