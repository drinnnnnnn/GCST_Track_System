<?php
/**
 * actions/update_password.php
 * Finalizes the password reset process after OTP verification.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/SuperAdminModel.php';

// Ensure tables and columns are consistent before proceeding
new SuperAdminModel();

// Prevent direct browser access
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$code = trim($input['code'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($code) || strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Validation failed. Password must be at least 6 characters.']);
    exit();
}

$conn = Database::getConnection();

// Security Check: Re-verify that this specific OTP is still valid and linked to this email.
// We fetch the timestamp and compare it in PHP to avoid timezone discrepancies with MySQL's NOW().
$stmt = $conn->prepare("SELECT expires_at FROM password_resets WHERE email = ? AND token = ? LIMIT 1");
$stmt->bind_param("ss", $email, $code);
$stmt->execute();
$res = $stmt->get_result();
if (!($row = $res->fetch_assoc()) || strtotime($row['expires_at']) <= time()) {
    echo json_encode(['success' => false, 'message' => 'Verification context expired. Please restart the reset process.']);
    exit();
}
$stmt->close();

// Hash new password using BCRYPT
$newHash = password_hash($password, PASSWORD_BCRYPT);

// Perform the update
$upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$upd->bind_param("ss", $newHash, $email);

if ($upd->execute()) {
    // Clean up all reset tokens for this account to prevent reuse
    $conn->query("DELETE FROM password_resets WHERE email = '" . $conn->real_escape_string($email) . "'");
    echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
} else {
    error_log("Password Update Error: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Internal database error.']);
}
$upd->close();