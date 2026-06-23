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

$emailBody = "
<div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border-top: 4px solid #2563eb; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; border-radius: 8px;'>
    <h2 style='color: #2563eb; font-size: 22px; margin-bottom: 16px; margin-top: 0;'>Profile Updated</h2>
    <p style='color: #374151; line-height: 1.5;'>Dear Admin,</p>
    <p style='color: #374151; line-height: 1.5;'>The following student has updated their profile information in the GCST Tracking System:</p>
    
    <ul style='list-style-type: none; padding: 0;'>
        <li style='margin-bottom: 10px;'><strong>Student Name:</strong> {$studentFullName}</li>
        <li style='margin-bottom: 10px;'><strong>Student ID:</strong> {$studentId}</li>
        <li style='margin-bottom: 10px; padding: 8px; background-color: #eff6ff; border-left: 4px solid #2563eb;'>
            <strong>Email:</strong> <span style='color: #6b7280; text-decoration: line-through;'>{$currentUser['email']}</span> &rarr; <strong>{$email}</strong>
        </li>
        <li style='margin-bottom: 10px; padding: 8px; background-color: #eff6ff; border-left: 4px solid #2563eb;'>
            <strong>Contact:</strong> <span style='color: #6b7280; text-decoration: line-through;'>{$currentUser['contact_number']}</span> &rarr; <strong>{$contactNumber}</strong>
        </li>
        <li style='margin-bottom: 10px; padding: 8px; background-color: #eff6ff; border-left: 4px solid #2563eb;'>
            <strong>Course:</strong> <span style='color: #6b7280; text-decoration: line-through;'>{$currentUser['course']}</span> &rarr; <strong>{$course}</strong>
        </li>
        <li style='margin-bottom: 10px; padding: 8px; background-color: #eff6ff; border-left: 4px solid #2563eb;'>
            <strong>Grade Level:</strong> <span style='color: #6b7280; text-decoration: line-through;'>{$currentUser['year_section']}</span> &rarr; <strong>{$gradeLevel}</strong>
        </li>
        <li style='margin-top: 15px;'><strong>Address:</strong> {$address}</li>
    </ul>

    <div style='margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb;'>
        <p style='color: #374151;'>Please review these changes in the system if necessary.</p>
        <p style='font-size: 12px; color: #9ca3af;'>GCST Tracking System Notification.</p>
    </div>
</div>";

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