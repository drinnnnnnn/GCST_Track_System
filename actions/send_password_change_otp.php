<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'user', 'admin', 'admincashier', 'superadmin']);

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/email_helpers.php';

$conn = Database::getConnection();
$userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized session.']);
    exit();
}

$table = ($role === 'student' || $role === 'user') ? 'users' : 'admincashier_acc';
if ($role === 'superadmin') $table = 'superadmins';

$stmt = $conn->prepare("SELECT email, first_name FROM $table WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Account not found.']);
    exit();
}

$email = $user['email'];
$code = sprintf("%06d", mt_rand(100000, 999999));
$expires_at = date("Y-m-d H:i:s", strtotime("+10 minutes"));

$conn->query("DELETE FROM password_resets WHERE email = '" . $conn->real_escape_string($email) . "'");
$ins = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
$ins->bind_param("sss", $email, $code, $expires_at);

if ($ins->execute()) {
    $subject = 'Verification Code - Password Change';
    $body = "Hello {$user['first_name']},<br><br>You are attempting to change your account password. Please use the verification code below to proceed:<br><h2 style='letter-spacing:5px; color: #2563eb;'>$code</h2><br>This code is valid for 10 minutes. If you did not request this, please ignore this email.";
    
    $result = sendEmailWithLog($conn, $email, $subject, $body, 'Password Change Verification');
    echo json_encode(['success' => ($result['status'] === 'sent'), 'message' => ($result['status'] === 'sent' ? 'Code sent successfully.' : 'Failed to send email.')]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}