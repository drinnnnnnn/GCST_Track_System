<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/email_helpers.php';

try {
    $conn = Database::getConnection();
    $data = json_decode(file_get_contents('php://input'), true);
    $identifier = trim($data['identifier'] ?? '');

    if (empty($identifier)) {
        throw new Exception('Please enter your registered Email Address.');
    }

    if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format. Please enter a valid email address.');
    }

    // 1. Verify User Exists
    $stmt = $conn->prepare("SELECT first_name, email FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        throw new Exception('No account found with that email address.');
    }

    $email = $user['email'];

    // 1.5 Rate Limiting: Check for requests made within the last 5 minutes
    $limitStmt = $conn->prepare("SELECT id FROM password_resets WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND is_used = 0 LIMIT 1");
    $limitStmt->bind_param('s', $email);
    $limitStmt->execute();
    if ($limitStmt->get_result()->num_rows > 0) {
        throw new Exception('Please wait at least 5 minutes before requesting another verification code.');
    }
    $limitStmt->close();

    // 2. Generate 6-digit OTP
    $otp = sprintf("%06d", random_int(100000, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // 3. Prepare DB Table
    $conn->query("CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL,
        `code` VARCHAR(6) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `is_used` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Deactivate previous codes for this student
    $stmt = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();

    // Insert new OTP
    $stmt = $conn->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $email, $otp, $expires_at);
    $stmt->execute();
    $stmt->close();

    // 4. Send Email
    $subject = "Your Password Reset Verification Code";
    $body = "
    <div style='font-family: sans-serif; max-width: 500px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 15px;'>
        <h2 style='color: #4f46e5; text-align: center;'>Password Reset</h2>
        <p>Hi " . htmlspecialchars($user['first_name']) . ",</p>
        <p>You requested to reset your password. Use the verification code below to proceed. This code expires in 15 minutes.</p>
        <div style='text-align: center; margin: 30px 0;'>
            <span style='font-size: 32px; font-weight: 800; letter-spacing: 5px; color: #4f46e5; background: #f1f5f9; padding: 10px 20px; border-radius: 10px;'>$otp</span>
        </div>
        <p style='color: #64748b; font-size: 0.85rem; text-align: center;'>If you did not request this, please ignore this email.</p>
    </div>";

    $emailResult = sendEmailWithLog($conn, $email, $subject, $body, 'Password Reset OTP');

    if ($emailResult['status'] !== 'sent') {
        throw new Exception('Failed to send verification email. Please try again later.');
    }

    echo json_encode([
        'success' => true, 
        'email' => $email,
        'message' => 'Verification code sent to ' . substr($email, 0, 3) . '***' . strstr($email, '@')
    ]);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
