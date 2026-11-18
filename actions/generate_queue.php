<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/QueueModel.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = [];
}

$school_id = isset($data['school_id']) ? trim((string)$data['school_id']) : '';
$student_name = isset($data['student_name']) ? trim((string)$data['student_name']) : '';
$queue_type = isset($data['queue_type']) && in_array($data['queue_type'], ['regular', 'priority'], true)
    ? $data['queue_type']
    : 'regular';
$purpose = isset($data['purpose']) ? trim((string)$data['purpose']) : '';
if ($purpose === '') {
    $purpose = 'General Inquiry';
}

if ($student_name === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Student name is required.']);
    exit;
}

$conn = Database::getConnection();
$sessionRole = $_SESSION['role'] ?? '';
$hasValidSession = !empty($sessionRole)
    || !empty($_SESSION['admin_id'])
    || !empty($_SESSION['user_id'])
    || !empty($_SESSION['student_id']);

if (!$hasValidSession) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Your session is invalid. Please sign in again.'
    ]);
    exit;
}

$userId = null;
$sessionUserId = $_SESSION['user_id'] ?? null;
if (is_numeric($sessionUserId)) {
    $userId = (int)$sessionUserId;
}

if ($school_id !== '') {
    $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? LIMIT 1");
    $stmt->bind_param('s', $school_id);
    $stmt->execute();
    $stmt->bind_result($resolvedId);
    if ($stmt->fetch()) {
        $userId = $resolvedId;
    }
    $stmt->close();
}

if (!$userId && !empty($_SESSION['student_id'])) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? LIMIT 1");
    $stmt->bind_param('s', $_SESSION['student_id']);
    $stmt->execute();
    $stmt->bind_result($resolvedId);
    if ($stmt->fetch()) {
        $userId = $resolvedId;
        $_SESSION['user_id'] = $userId;
    }
    $stmt->close();
}

$resolvedUserId = ($userId !== null && is_numeric($userId)) ? (int)$userId : null;
$isAdminQueueContext = in_array($sessionRole, ['admin', 'cashier', 'admincashier', 'superadmin'], true)
    || !empty($_SESSION['admin_id']);

if (!$resolvedUserId && !$isAdminQueueContext) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Your session is invalid. Please sign in again.'
    ]);
    exit;
}

$model = new QueueModel();
try {
    $res = $model->create($resolvedUserId, null, $student_name, $purpose, $queue_type);
    if (!is_array($res) || empty($res['id'])) {
        throw new Exception('Failed to create queue ticket.');
    }

    $ticket = $model->getByIdWithDetails((int)$res['id']);
    if (!$ticket || empty($ticket['queue_number'])) {
        throw new Exception('Unable to fetch the created ticket details.');
    }

    echo json_encode([
        'success' => true,
        'ticket' => $ticket,
        'queue_number' => $ticket['queue_number']
    ]);
} catch (Throwable $e) {
    error_log('generate_queue.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>