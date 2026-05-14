<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../database/models/QueueModel.php';
require_once __DIR__ . '/../config/db_connect.php';

// Allow either students or administrators to generate a ticket
$studentId = $_SESSION['student_id'] ?? null;
$adminId = $_SESSION['admin_id'] ?? null;

if (!$studentId && !$adminId) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$queueModel = new QueueModel();
$payload = json_decode(file_get_contents('php://input'), true) ?: [];

$userId = null;
if ($studentId) {
    // If a student is logged in, find their internal ID and check rate limits
    $lookupStmt = $conn->prepare('SELECT id FROM users WHERE student_id = ? LIMIT 1');
    $lookupStmt->bind_param('s', $studentId);
    $lookupStmt->execute();
    $lookupStmt->bind_result($userId);
    if (!$lookupStmt->fetch()) {
        $lookupStmt->close();
        echo json_encode(['success' => false, 'error' => 'User record not found']);
        exit;
    }
    $lookupStmt->close();

    // Rate limiting for students: 1 ticket every 30 minutes
    $checkStmt = $conn->prepare("SELECT created_at FROM queue WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $checkStmt->bind_param('i', $userId);
    $checkStmt->execute();
    $checkStmt->bind_result($lastCreatedAt);
    if ($checkStmt->fetch()) {
        $lastTime = strtotime($lastCreatedAt);
        $diff = time() - $lastTime;
        if ($diff < 1800) {
            $minutesLeft = ceil((1800 - $diff) / 60);
            $checkStmt->close();
            echo json_encode(['success' => false, 'error' => "Rate limit: please wait $minutesLeft more minute(s)."]);
            exit;
        }
    }
    $checkStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'queue_number' => $queueModel->getNextQueueNumber()]);
    exit;
}

// Collect data from payload or defaults for walk-ins
$queueNumber = $payload['queue_number'] ?? null;
$studentName = $payload['student_name'] ?? ($studentId ? '' : 'Walk-in Student');
$purpose = $payload['purpose'] ?? ($studentId ? '' : 'Cashier Transaction');

$result = $queueModel->create($queueNumber, $userId, $studentName, $purpose);
if ($result !== false) {
    $ticket = $queueModel->getById($result['id']);
    echo json_encode(['success' => true, 'queue_number' => $result['queue_number'], 'ticket' => $ticket]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to generate queue']);
}
?>