<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'user']); // Allow student/user roles to update their own profile
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
$firstName = trim($payload['first_name'] ?? '');
$middleName = trim($payload['middle_name'] ?? '');
$lastName = trim($payload['last_name'] ?? '');
$email = trim($payload['email'] ?? '');
$contactNumber = trim($payload['contact_number'] ?? '');
$address = trim($payload['address'] ?? '');
$course = trim($payload['course'] ?? '');
$gradeLevel = trim($payload['grade_level'] ?? '');

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
    $currentStmt = $conn->prepare("SELECT first_name, middle_name, last_name, email, contact_number, student_id, course, year_section, address FROM users WHERE id = ?");
    $currentStmt->bind_param('i', $userId);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    $currentUser = $currentResult->fetch_assoc();
    $currentStmt->close();

    if (!$currentUser) {
        throw new Exception('User data not found.');
    }

    // Update user profile
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, contact_number = ?, address = ?, course = ?, year_section = ? WHERE id = ?");
    $stmt->bind_param('ssssssssi', $firstName, $middleName, $lastName, $email, $contactNumber, $address, $course, $gradeLevel, $userId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update profile: ' . $stmt->error);
    }
    $stmt->close();

    $conn->commit();

    // Send notification email to admin
    $adminEmail = env('SMTP_USERNAME'); // Using the sender email as the admin notification recipient
    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $studentFullName = trim($currentUser['first_name'] . ' ' . ($currentUser['middle_name'] ? $currentUser['middle_name'] . ' ' : '') . $currentUser['last_name']);
        $studentId = $currentUser['student_id'];

        $emailSubject = "Student Profile Update Notification: {$studentFullName} ({$studentId})";
        $emailBody = "<p>Dear Admin,</p>" .
                     "<p>The following student has updated their profile information:</p>" .
                     "<ul>" .
                     "<li><strong>Student Name:</strong> {$studentFullName}</li>" .
                     "<li><strong>Student ID:</strong> {$studentId}</li>" .
                     "<li><strong>Old Email:</strong> {$currentUser['email']} -> <strong>New Email:</strong> {$email}</li>" .
                     "<li><strong>Old Contact:</strong> {$currentUser['contact_number']} -> <strong>New Contact:</strong> {$contactNumber}</li>" .
                     "<li><strong>Old Course:</strong> {$currentUser['course']} -> <strong>New Course:</strong> {$course}</li>" .
                     "<li><strong>Old Grade Level:</strong> {$currentUser['year_section']} -> <strong>New Grade Level:</strong> {$gradeLevel}</li>" .
                     "<li><strong>Address:</strong> {$address}</li>" .
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