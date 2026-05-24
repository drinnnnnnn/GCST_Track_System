<?php
/**
 * actions/reset_request.php
 * Handles password reset initiation for students.
 */
ini_set('display_errors', (defined('APP_DEBUG') && APP_DEBUG) ? '1' : '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/SuperAdminModel.php';
require_once __DIR__ . '/email_helpers.php';

// Ensure tables exist by instantiating the custodian model
new SuperAdminModel();

header('Content-Type: application/json');

function sendJsonResponse($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$identifier = trim($input['identifier'] ?? '');

if (empty($identifier)) {
    sendJsonResponse(['success' => false, 'message' => 'Email address or ID is required.']);
}

$conn = Database::getConnection();

// Verify account existence and status
$stmt = $conn->prepare("SELECT email, first_name, status FROM users WHERE email = ? OR student_id = ? LIMIT 1");
$stmt->bind_param("ss", $identifier, $identifier);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    sendJsonResponse(['success' => false, 'message' => 'No account found with that identification.']);
}

if (in_array($user['status'], ['suspended', 'rejected'])) {
    sendJsonResponse(['success' => false, 'message' => 'Account access restricted. Please contact support.']);
}

$email = $user['email'];
$code = sprintf("%06d", mt_rand(100000, 999999));
$expires_at = date("Y-m-d H:i:s", strtotime("+15 minutes"));

// Invalidate existing tokens for this email
$del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
$del->bind_param("s", $email);
$del->execute();

// Store the new verification code
$ins = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
$ins->bind_param("sss", $email, $code, $expires_at);

if ($ins->execute()) {
    $subject = 'Password Recovery Code';
    $body = "Hello {$user['first_name']},<br><br>Use the following code to verify your password reset: <br><h2 style='letter-spacing:5px;'>$code</h2><br>This code expires in 15 minutes.";

    // Use the unified helper which handles config loading and database logging
    $result = sendEmailWithLog($conn, $email, $subject, $body, 'Password Reset');

    if ($result['success']) {
        sendJsonResponse([
            'success' => true, 
            'email' => $email, 
            'message' => "Verification code sent to " . substr($email, 0, 4) . "****" . substr($email, strpos($email, '@') - 2)
        ]);
    } else {
        // Secure error message for users
        $msg = "We encountered an issue sending your recovery email. Please try again later.";
        if (defined('APP_DEBUG') && APP_DEBUG) {
            $msg = "Mail error: " . $result['message'] . " | DEV CODE: $code";
        }
        sendJsonResponse(['success' => false, 'message' => $msg]);
    }
}
sendJsonResponse(['success' => false, 'message' => 'Internal server error.']);