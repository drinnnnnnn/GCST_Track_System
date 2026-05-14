<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admin', 'admincashier', 'superadmin']);

require_once __DIR__ . '/../database/connection.php';
$conn = Database::getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$queue_id = intval($data['queue_id'] ?? 0);
$status = trim($data['status'] ?? '');

if ($queue_id <= 0 || !in_array($status, ['waiting', 'serving', 'completed', 'cancelled'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

try {
    $conn->begin_transaction();

    // Ensure only one 'serving' ticket exists. If we're switching one to 'serving',
    // mark any other currently serving tickets as 'completed' and set served_at.
    if ($status === 'serving') {
        $conn->query("UPDATE queue SET status = 'completed', served_at = NOW() WHERE status = 'serving'");
    }

    $stmt = $conn->prepare("UPDATE queue SET status = ?, served_at = CASE WHEN ? IN ('serving','completed','cancelled') THEN NOW() ELSE NULL END WHERE id = ?");
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param('ssi', $status, $status, $queue_id);
    $success = $stmt->execute();
    if (!$success) {
        throw new Exception($stmt->error);
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>