<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'user', 'admin', 'admincashier', 'superadmin']);

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/email_helpers.php';

$conn = Database::getConnection();
$userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized session.']);
    exit();
}

$table = ($role === 'student' || $role === 'user') ? 'users' : 'admincashier_acc';
if ($role === 'superadmin') $table = 'superadmins';

$stmt = $conn->prepare("SELECT email, first_name FROM $table WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Account not found.']);
    exit();
}

$email = $user['email'];
$code = sprintf("%06d", mt_rand(100000, 999999));
$expires_at = date("Y-m-d H:i:s", strtotime("+10 minutes"));

$conn->query("DELETE FROM password_resets WHERE email = '" . $conn->real_escape_string($email) . "'");
$ins = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
$ins->bind_param("sss", $email, $code, $expires_at);

if ($ins->execute()) {
    $subject = 'Verification Code - Password Change';

$body = "
<div style='font-family: sans-serif; max-width: 500px; margin: 20px auto; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);'>
    
    <!-- Header -->
    <div style='background-color: #2563eb; padding: 20px; text-align: center;'>
        <h2 style='color: #ffffff; margin: 0; font-size: 20px;'>Security Verification</h2>
    </div>

    <!-- Content -->
    <div style='padding: 30px;'>
        <p style='color: #374151; font-size: 16px; margin: 0 0 20px 0;'>Hello <strong>{$user['first_name']}</strong>,</p>
        <p style='color: #4b5563; line-height: 1.6; margin: 0 0 20px 0;'>
            You are attempting to change your account password. Please use the verification code below to proceed:
        </p>
        
        <!-- Code Display -->
        <div style='background-color: #f3f4f6; border: 2px dashed #2563eb; padding: 20px; text-align: center; border-radius: 8px; margin: 25px 0;'>
            <h2 style='letter-spacing: 8px; color: #2563eb; font-size: 32px; margin: 0;'>{$code}</h2>
        </div>

        <p style='color: #6b7280; font-size: 14px; margin: 0 0 10px 0;'>
            This code is valid for <strong>10 minutes</strong>.
        </p>
        <p style='color: #9ca3af; font-size: 12px; line-height: 1.5; margin: 0;'>
            If you did not request this, please ignore this email or contact support if you are concerned about your account security.
        </p>
    </div>

    <!-- Footer -->
    <div style='background-color: #f9fafb; padding: 15px; text-align: center; border-top: 1px solid #e5e7eb;'>
        <p style='color: #9ca3af; font-size: 11px; margin: 0;'>GCST Tracking System</p>
    </div>
</div>";
    
    $result = sendEmailWithLog($conn, $email, $subject, $body, 'Password Change Verification');
    echo json_encode(['success' => ($result['status'] === 'sent'), 'message' => ($result['status'] === 'sent' ? 'Code sent successfully.' : 'Failed to send email.')]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}