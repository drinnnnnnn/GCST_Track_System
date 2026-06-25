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
$signatureImagePath = null;

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

    if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['signature_image'];
        $allowedMime = ['image/jpeg', 'image/png'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $actualMime = $finfo->file($file['tmp_name']);

        if (!in_array($actualMime, $allowedMime, true)) {
            throw new Exception('Invalid image format. Please upload a PNG or JPG file.');
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception('Signature image file size must be 2MB or smaller.');
        }

        $uploadDir = __DIR__ . '/../assets/images/signatures/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new Exception('Unable to create signature upload directory.');
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $newFileName = 'signature_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = $uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Unable to save uploaded signature image.');
        }

        $signatureImagePath = 'assets/images/signatures/' . $newFileName;
    }

    $nameParts = explode(' ', $fullName, 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';

    if ($signatureImagePath) {
        $stmt = $conn->prepare("UPDATE admincashier_acc SET first_name = ?, last_name = ?, email = ?, contact_number = ?, signature_image = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        $stmt->bind_param('sssssi', $firstName, $lastName, $email, $contactNumber, $signatureImagePath, $adminId);
    } else {
        $stmt = $conn->prepare("UPDATE admincashier_acc SET first_name = ?, last_name = ?, email = ?, contact_number = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        $stmt->bind_param('ssssi', $firstName, $lastName, $email, $contactNumber, $adminId);
    }

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