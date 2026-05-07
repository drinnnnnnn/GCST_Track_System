<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
header('Content-Type: application/json');

if (!file_exists(__DIR__ . '/../config/db_connect.php')) {
    throw new Exception("Configuration error: db_connect.php not found.");
}
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/audit_helpers.php';

$adminId = $_SESSION['admin_id'] ?? null;

if (!$adminId) {
    echo json_encode(['success' => false, 'message' => 'Admin ID not found in session.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);

$fullName = $payload['full_name'] ?? null;
$email = $payload['email'] ?? null;
$contactNumber = $payload['contact_number'] ?? null;

if (!$fullName || !$email || !$contactNumber) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

try {
    // Split full name into first and last name (simple approach, might need more robust parsing)
    $nameParts = explode(' ', $fullName, 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';

    $stmt = $conn->prepare("UPDATE admincashier_acc SET first_name = ?, last_name = ?, email = ?, contact_number = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    $stmt->bind_param('ssssi', $firstName, $lastName, $email, $contactNumber, $adminId);
    $stmt->execute();
    $stmt->close();

    logAudit($conn, 'admincashier', $adminId, 'profile_update', 'Admin cashier profile updated.');
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>