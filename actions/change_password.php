<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'user', 'admin', 'admincashier', 'superadmin']);

// Strict Security: Check for verification flag
if (!isset($_SESSION['password_update_verified']) || $_SESSION['password_update_verified'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Security check failed. Please verify your email first.']);
    exit();
}

if (time() - $_SESSION['password_update_verified_at'] > 900) {
    unset($_SESSION['password_update_verified'], $_SESSION['password_update_verified_at']);
    echo json_encode(['success' => false, 'message' => 'Verification session expired. Please re-verify.']);
    exit();
}

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/audit_helpers.php';

$input = json_decode(file_get_contents('php://input'), true);
$current = $input['current_password'] ?? '';
$new = $input['new_password'] ?? '';
$confirm = $input['confirm_password'] ?? '';
$userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if (empty($current) || empty($new) || $new !== $confirm || strlen($new) < 8) {
    echo json_encode(['success' => false, 'message' => 'Validation error. Passwords must match and be at least 8 characters.']);
    exit();
}

$conn = Database::getConnection();
$table = ($role === 'student' || $role === 'user') ? 'users' : 'admincashier_acc';
if ($role === 'superadmin') $table = 'superadmins';
$passCol = ($table === 'admincashier_acc') ? 'password' : 'password_hash';

$stmt = $conn->prepare("SELECT $passCol FROM $table WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($current, $user[$passCol])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
    exit();
}

$newHash = password_hash($new, PASSWORD_BCRYPT);
$upd = $conn->prepare("UPDATE $table SET $passCol = ? WHERE id = ?");
$upd->bind_param("si", $newHash, $userId);

if ($upd->execute()) {
    unset($_SESSION['password_update_verified'], $_SESSION['password_update_verified_at']);
    logAudit($conn, $role, $userId, 'change_password', 'Password updated successfully after email verification.');
    echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}