<?php
// actions/test_smtp.php
require_once __DIR__ . '/security.php';
secureSessionStart();
// Allow admin or students to trigger the test while developing
requireAuth(['admincashier', 'superadmin']);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/email_helpers.php';

header('Content-Type: application/json');

$testEmail = env('SMTP_USERNAME');
$subject = "GCST Track System - SMTP Test Email";
$message = "<h1>SMTP Connection Success!</h1><p>This is a test email sent from the GCST Track System to verify your Gmail SMTP settings.</p><p>Time: " . date('Y-m-d H:i:s') . "</p>";

$result = sendEmailWithLog($conn, $testEmail, $subject, $message, 'SMTP Test');

echo json_encode([
    'success' => $result['status'] === 'sent',
    'result' => $result,
    'note' => 'If success is true, check ' . $testEmail . ' for the test message.'
]);
?>