<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'admincashier', 'superadmin']);
header('Content-Type: application/json'); // Ensure JSON header is always sent

require_once __DIR__ . '/../database/connection.php'; // Use the standardized database connection
$conn = Database::getConnection(); // Get the database connection instance

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$studentId = isset($payload['student_id']) ? trim($payload['student_id']) : '';

if (empty($studentId)) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
    exit;
}

// Check for database connection errors
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
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