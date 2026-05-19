<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/QueueModel.php';

$data = json_decode(file_get_contents('php://input'), true);
$school_id = trim($data['school_id'] ?? '');
$student_name = trim($data['student_name'] ?? 'Walk-in Student');
$purpose = trim($data['purpose'] ?? 'General Inquiry');

$conn = Database::getConnection();
$userId = $_SESSION['user_id'] ?? null;

// Priority: Resolve user_id if school_id is provided via manual form entry
if ($school_id) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? LIMIT 1");
    $stmt->bind_param("s", $school_id);
    $stmt->execute();
    $stmt->bind_result($resolvedId);
    if ($stmt->fetch()) $userId = $resolvedId;
    $stmt->close();
}
// Fallback: Ensure user_id is resolved from student_id if missing in session
elseif (!$userId && isset($_SESSION['student_id'])) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? LIMIT 1");
    $stmt->bind_param("s", $_SESSION['student_id']);
    $stmt->execute();
    $stmt->bind_result($resolvedId);
    if ($stmt->fetch()) {
        $userId = $resolvedId;
        $_SESSION['user_id'] = $userId; // Cache for performance
    }
    $stmt->close();
}

$model = new QueueModel();
try {
    $res = $model->create($userId, null, $student_name, $purpose);
    if ($res === false) {
        throw new Exception('Failed to create queue ticket');
    }

    // Fetch with joined user details (school_id)
    $ticket = $model->getByIdWithDetails((int)$res['id']);
    if (!$ticket) throw new Exception('Unable to fetch created ticket');

    echo json_encode(['success' => true, 'ticket' => $ticket, 'queue_number' => $ticket['queue_number']]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>