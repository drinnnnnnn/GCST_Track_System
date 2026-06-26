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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$contactNumber = trim($_POST['contact_number'] ?? '');

if ($fullName === '' || $email === '') {
    echo json_encode(['success' => false, 'message' => 'Full name and email are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

try {
    // Preserve existing contact number when none is submitted
    if ($contactNumber === '') {
        $existingStmt = $conn->prepare("SELECT contact_number FROM admincashier_acc WHERE id = ?");
        if ($existingStmt) {
            $existingStmt->bind_param('i', $adminId);
            $existingStmt->execute();
            $existingResult = $existingStmt->get_result();
            $existingRow = $existingResult->fetch_assoc();
            $contactNumber = $existingRow['contact_number'] ?? null;
            $existingStmt->close();
        }
    }

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