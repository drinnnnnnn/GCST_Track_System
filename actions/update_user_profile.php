<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student']); // Only students can update their own profile
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/email_helpers.php'; // Include the new email helper
require_once __DIR__ . '/../config/env.php'; // For admin email

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$firstName = $payload['first_name'] ?? null;
$lastName = $payload['last_name'] ?? null;
$email = $payload['email'] ?? null;
$contactNumber = $payload['contact_number'] ?? null;

if (empty($firstName) || empty($lastName) || empty($email) || empty($contactNumber)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

$conn->begin_transaction();

try {
    // Fetch current user data for comparison and notification
    $currentStmt = $conn->prepare("SELECT first_name, last_name, email, contact_number, student_id FROM users WHERE id = ?");
    $currentStmt->bind_param('i', $userId);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    $currentUser = $currentResult->fetch_assoc();
    $currentStmt->close();

    if (!$currentUser) {
        throw new Exception('User data not found.');
    }

    // Update user profile
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, contact_number = ? WHERE id = ?");
    $stmt->bind_param('ssssi', $firstName, $lastName, $email, $contactNumber, $userId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update profile: ' . $stmt->error);
    }
    $stmt->close();

    $conn->commit();

    // Send notification email to admin
    $adminEmail = env('SMTP_USERNAME'); // Using the sender email as the admin notification recipient
    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $studentFullName = trim($currentUser['first_name'] . ' ' . $currentUser['last_name']);
        $studentId = $currentUser['student_id'];

        $emailSubject = "Student Profile Update Notification: {$studentFullName} ({$studentId})";
        $emailBody = "<p>Dear Admin,</p>" .
                     "<p>The following student has updated their profile information:</p>" .
                     "<ul>" .
                     "<li><strong>Student Name:</strong> {$studentFullName}</li>" .
                     "<li><strong>Student ID:</strong> {$studentId}</li>" .
                     "<li><strong>Old Email:</strong> {$currentUser['email']} -> <strong>New Email:</strong> {$email}</li>" .
                     "<li><strong>Old Contact:</strong> {$currentUser['contact_number']} -> <strong>New Contact:</strong> {$contactNumber}</li>" .
                     "</ul>" .
                     "<p>Please review these changes in the system if necessary.</p>" .
                     "<p>GCST Tracking System Notification.</p>";

        sendEmailWithLog($conn, $adminEmail, $emailSubject, $emailBody, 'Admin Notification - Profile Update');
    } else {
        error_log("Admin email for profile update notification is not configured or invalid.");
    }

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully. Admin notified.']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Profile update failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>