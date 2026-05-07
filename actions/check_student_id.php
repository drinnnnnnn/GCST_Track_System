<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'admincashier', 'superadmin']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$studentId = isset($payload['student_id']) ? trim($payload['student_id']) : '';

if (empty($studentId)) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
    exit;
}

$stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE student_id = ? LIMIT 1");
$stmt->bind_param("s", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
    echo json_encode(['success' => true, 'name' => $fullName]);
} else {
    echo json_encode(['success' => false, 'message' => 'Student ID not found in database.']);
}

$stmt->close();
$conn->close();
?>