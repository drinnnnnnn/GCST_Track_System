<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/email_helpers.php'; // Contains sendEmailWithLog function

// Load global configuration for API keys and environment settings
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
}

// Ensure only authenticated users with appropriate roles can access this
secureSessionStart();
requireAuth(['admincashier', 'superadmin']); // Adjust roles as necessary

$conn = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$recipient = $_POST['recipient'] ?? '';
$subject = $_POST['subject'] ?? '';
$message = $_POST['message'] ?? '';
$type = $_POST['type'] ?? 'Manual Notification';
$phoneNumber = $_POST['phone_number'] ?? '';

// Basic validation
if ((empty($recipient) && empty($phoneNumber)) || empty($subject) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Recipient (Email or Phone), Subject, and Message are required.']);
    exit;
}

// Validate email format
if (!empty($recipient) && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid recipient email format.']);
    exit;
}

$attachments = [];
if (isset($_FILES['attachments'])) {
    $uploadDir = __DIR__ . '/../uploads/temp_email_attachments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true); // Create directory if it doesn't exist
    }

    foreach ($_FILES['attachments']['name'] as $key => $name) {
        $tmpName = $_FILES['attachments']['tmp_name'][$key];
        $error = $_FILES['attachments']['error'][$key];
        $size = $_FILES['attachments']['size'][$key];

        if ($error === UPLOAD_ERR_OK) {
            // Generate a unique filename to prevent conflicts
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $uniqueName = uniqid('attachment_') . '.' . $extension;
            $targetFile = $uploadDir . $uniqueName;

            if (move_uploaded_file($tmpName, $targetFile)) {
                $attachments[] = [
                    'path' => $targetFile,
                    'name' => $name // Original filename for the email client
                ];
            } else {
                error_log("Failed to move uploaded file: {$name}");
            }
        } else {
            error_log("File upload error for {$name}: {$error}");
        }
    }
}

// Server-side validation for Philippine mobile number format
if (!empty($phoneNumber)) {
    $cleanPhone = str_replace([' ', '-', '(', ')'], '', $phoneNumber);
    if (!preg_match('/^\+639\d{9}$/', $cleanPhone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid Philippine mobile number format. Must start with +639.']);
        exit;
    }
}

$result = sendEmailWithLog($conn, $recipient, $subject, $message, $type, $attachments, $phoneNumber);

// Clean up temporary attachment files
foreach ($attachments as $att) {
    if (file_exists($att['path'])) {
        unlink($att['path']);
    }
}

// Construct a more detailed message for the user
$feedbackMessage = [];
if (!empty($recipient)) {
    $feedbackMessage[] = "Email: " . ucfirst($result['email_status']);
} else {
    $feedbackMessage[] = "Email: Skipped (no recipient)";
}

if (!empty($phoneNumber)) {
    $feedbackMessage[] = "SMS: " . ucfirst($result['sms_status']);
} else {
    $feedbackMessage[] = "SMS: Skipped (no phone number)";
}

$finalUserMessage = implode(". ", array_filter($feedbackMessage)); // Filter out empty messages

if ($result['success']) {
    echo json_encode(['success' => true, 'message' => $finalUserMessage]);
} else {
    // If overall failed, include the detailed log message from email_helpers.php
    echo json_encode(['success' => false, 'message' => $finalUserMessage . ". Details: " . ($result['message'] ?? 'Unknown error.')]);
}

$conn->close();
?>