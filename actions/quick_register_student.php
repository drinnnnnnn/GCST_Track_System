<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admin', 'cashier', 'admincashier', 'superadmin']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

$payload = json_decode(file_get_contents('php://input'), true) ?: [];

$studentId = trim($payload['student_id'] ?? '');
$firstName = trim($payload['first_name'] ?? '');
$lastName  = trim($payload['last_name'] ?? '');
$email     = trim($payload['email'] ?? '');

if (empty($studentId) || empty($firstName) || empty($lastName) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (!preg_match('/^GC-\d{6}$/', $studentId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid Student ID format. Expected GC-XXXXXX.']);
    exit;
}

// Check for existing Student ID or Email
$check = $conn->prepare("SELECT id FROM users WHERE student_id = ? OR email = ? LIMIT 1");
$check->bind_param("ss", $studentId, $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Student ID or Email already exists.']);
    exit;
}
$check->close();

// Default password is the student ID
$hashedPassword = password_hash($studentId, PASSWORD_DEFAULT);
$role = 'student';

$stmt = $conn->prepare("INSERT INTO users (student_id, first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $studentId, $firstName, $lastName, $email, $hashedPassword, $role);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>