<?php
/**
 * actions/verify_reset_code.php
 * Validates the 6-digit OTP for password recovery.
 */
require_once __DIR__ . '/../database/connection.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$code = trim($input['code'] ?? '');

if (empty($email) || empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Email and code are required.']);
    exit();
}

$conn = Database::getConnection();

// Check if the token exists and belongs to the email
$stmt = $conn->prepare("SELECT expires_at FROM password_resets WHERE email = ? AND token = ? LIMIT 1");
$stmt->bind_param("ss", $email, $code);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    if (strtotime($row['expires_at']) > time()) {
        echo json_encode(['success' => true, 'message' => 'Code verified.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Verification code has expired.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Incorrect verification code.']);
}