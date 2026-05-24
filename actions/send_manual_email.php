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

// Basic validation
if (empty($recipient) || empty($subject) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Recipient Email, Subject, and Message are required.']);
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

$result = sendEmailWithLog($conn, $recipient, $subject, $message, $type, $attachments);

// Clean up temporary attachment files
foreach ($attachments as $att) {
    if (file_exists($att['path'])) {
        unlink($att['path']);
    }
}

if ($result['success']) {
    echo json_encode([
        'success' => true, 
        'message' => "Email: " . ucfirst($result['email_status'])
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => "Email: Failed. Details: " . ($result['message'] ?? 'Unknown error.')
    ]);
}

$conn->close();
?>