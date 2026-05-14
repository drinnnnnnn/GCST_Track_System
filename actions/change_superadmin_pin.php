<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['superadmin']);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_connect.php';

if (!isset($conn)) {
    if (isset($mysqli)) {
        $conn = $mysqli;
    } elseif (isset($link)) {
        $conn = $link;
    } elseif (isset($db)) {
        $conn = $db;
    } else {
        jsonResponse(['success' => false, 'message' => 'Database connection not available.'], 500);
    }
}

require_once __DIR__ . '/audit_helpers.php';

$adminId = $_SESSION['admin_id'] ?? null;
if (!$adminId) {
    jsonResponse(['success' => false, 'message' => 'Session expired. Please login again.'], 401);
}

$payload = json_decode(file_get_contents('php://input'), true);
$currentPin = trim((string)($payload['current_pin'] ?? ''));
$newPin = trim((string)($payload['new_pin'] ?? ''));
$confirmPin = trim((string)($payload['confirm_pin'] ?? ''));

if ($currentPin === '' || $newPin === '' || $confirmPin === '') {
    jsonResponse(['success' => false, 'message' => 'All fields are required.'], 400);
}

if ($newPin !== $confirmPin) {
    jsonResponse(['success' => false, 'message' => 'New PIN and confirmation do not match.'], 400);
}

if (!preg_match('/^\d{4,10}$/', $newPin)) {
    jsonResponse(['success' => false, 'message' => 'PIN must be between 4 and 10 digits.'], 400);
}

try {
    // 1. Verify Current PIN
    $stmt = $conn->prepare("SELECT pin FROM admincashier_acc WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $stmt->bind_result($storedPin);
    $stmt->fetch();
    $stmt->close();

    if ($currentPin !== $storedPin) {
        jsonResponse(['success' => false, 'message' => 'The current security PIN is incorrect.'], 403);
    }

    // 2. Update PIN
    $updateStmt = $conn->prepare("UPDATE admincashier_acc SET pin = ? WHERE id = ?");
    $updateStmt->bind_param('si', $newPin, $adminId);
    
    if ($updateStmt->execute()) {
        logAudit($conn, 'superadmin', $adminId, 'change_pin', 'Superadmin updated security PIN.');
        jsonResponse(['success' => true, 'message' => 'Security PIN updated successfully.']);
    } else {
        throw new Exception("Database error during update.");
    }
    $updateStmt->close();

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
}