<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'user', 'admin', 'admincashier', 'superadmin']);

require_once __DIR__ . '/../database/connection.php';

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');
$userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if (empty($code) || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit();
}

$conn = Database::getConnection();
$table = ($role === 'student' || $role === 'user') ? 'users' : 'admincashier_acc';
if ($role === 'superadmin') $table = 'superadmins';

$stmt = $conn->prepare("SELECT email FROM $table WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$email = $stmt->get_result()->fetch_assoc()['email'] ?? '';
$stmt->close();

$check = $conn->prepare("SELECT expires_at FROM password_resets WHERE email = ? AND token = ? LIMIT 1");
$check->bind_param("ss", $email, $code);
$check->execute();
$res = $check->get_result();

if ($row = $res->fetch_assoc()) {
    if (strtotime($row['expires_at']) > time()) {
        $_SESSION['password_update_verified'] = true;
        $_SESSION['password_update_verified_at'] = time();
        $conn->query("DELETE FROM password_resets WHERE email = '" . $conn->real_escape_string($email) . "'");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Verification code has expired.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Incorrect verification code.']);
}