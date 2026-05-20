<?php
/**
 * Handles manual email sending with multi-part file attachments.
 */
header('Content-Type: application/json');
ini_set('display_errors', '0'); // Prevent HTML errors from breaking JSON

require_once __DIR__ . '/../../actions/security.php';
secureSessionStart();
requireAuth(['admin', 'admincashier', 'superadmin', 'cashier']);

require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/../../actions/email_helpers.php';

try {
    $conn = Database::getConnection();
    
    // Sanitize inputs
    $recipient = filter_input(INPUT_POST, 'recipient', FILTER_VALIDATE_EMAIL);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $type = trim($_POST['type'] ?? 'User Notification');

    if (!$recipient) throw new Exception("A valid recipient email address is required.");
    if (empty($subject)) throw new Exception("Email subject cannot be empty.");
    if (empty($message)) throw new Exception("Email message body cannot be empty.");

    $attachments = [];
    
    // Process file uploads
    if (!empty($_FILES['attachments'])) {
        $files = $_FILES['attachments'];
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'zip', 'csv', 'xlsx'];
        $maxSize = 5 * 1024 * 1024; // 5MB per file

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                throw new Exception("Error uploading file: " . $files['name'][$i]);
            }

            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) {
                throw new Exception("File type not allowed: " . $files['name'][$i]);
            }
            if ($files['size'][$i] > $maxSize) {
                throw new Exception("File exceeds size limit (5MB): " . $files['name'][$i]);
            }

            $attachments[] = [
                'path' => $files['tmp_name'][$i],
                'name' => $files['name'][$i]
            ];
        }
    }

    $result = sendEmailWithLog($conn, $recipient, $subject, $message, $type, $attachments);

    echo json_encode(['success' => $result['success'], 'message' => $result['success'] ? 'Email sent' : $result['status']]);

} catch (Exception $e) {
    error_log("Manual Email Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}