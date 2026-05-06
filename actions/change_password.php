<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';

require_once __DIR__ . '/audit_helpers.php'; // Include audit logging helper
secureSessionStart();
requireAuth(['student']);
header('Content-Type: application/json');

$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id) {
    jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$current_password = trim((string)($payload['current_password'] ?? ''));
$new_password = trim((string)($payload['new_password'] ?? ''));
$confirm_password = trim((string)($payload['confirm_password'] ?? ''));

if ($current_password === '' || $new_password === '' || $confirm_password === '') {
    jsonResponse(['success' => false, 'message' => 'All password fields are required.'], 422);
}

if ($new_password !== $confirm_password) {
    jsonResponse(['success' => false, 'message' => 'New password and confirmation do not match.'], 422);
}

if (strlen($new_password) < 8) {
    jsonResponse(['success' => false, 'message' => 'New password must be at least 8 characters long.'], 422);
}

$stmt = $conn->prepare('SELECT password FROM users WHERE student_id = ? LIMIT 1');
$stmt->bind_param('s', $student_id);
$stmt->execute();
$stmt->bind_result($hashed_password);
if (!$stmt->fetch() || $hashed_password === null) {
    $stmt->close();
    jsonResponse(['success' => false, 'message' => 'User record not found.'], 404);
}
$stmt->close();

if (!password_verify($current_password, $hashed_password)) {
    jsonResponse(['success' => false, 'message' => 'Current password is incorrect.'], 403);
}

$updatedPassword = password_hash($new_password, PASSWORD_DEFAULT);
$updateStmt = $conn->prepare('UPDATE users SET password = ? WHERE student_id = ?');
$updateStmt->bind_param('ss', $updatedPassword, $student_id);
$success = $updateStmt->execute();
$updateStmt->close();

if (!$success) {
    jsonResponse(['success' => false, 'message' => 'Unable to update password. Please try again.'], 500);
}

logAudit($conn, 'student', $student_id, 'change_password', 'Changed account password.');
jsonResponse(['success' => true, 'message' => 'Password changed successfully.']);
