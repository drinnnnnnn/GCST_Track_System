<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/audit_helpers.php';

$adminId = $_SESSION['admin_id'] ?? null;

if (!$adminId) {
    echo json_encode(['success' => false, 'message' => 'Admin ID not found in session.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);

$currentPassword = $payload['current_password'] ?? null;
$newPassword = $payload['new_password'] ?? null;
$confirmPassword = $payload['confirm_password'] ?? null;

if (!$currentPassword || !$newPassword || !$confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match.']);
    exit;
}

if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
    exit;
}

try {
    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM admin_cashier WHERE id = ?");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();

    if (!$admin || !password_verify($currentPassword, $admin['password'])) {
        throw new Exception('Incorrect current password.');
    }

    // Update password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare("UPDATE admin_cashier SET password = ? WHERE id = ?");
    $updateStmt->bind_param('si', $hashedPassword, $adminId);
    $updateStmt->execute();
    $updateStmt->close();

    logAudit($conn, 'admincashier', $adminId, 'password_change', 'Admin cashier password changed.');
    echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>