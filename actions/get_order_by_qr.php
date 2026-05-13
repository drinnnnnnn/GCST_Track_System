<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/QRService.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin', 'users']);
header('Content-Type: application/json');

// 1. Prevent direct external access by checking for AJAX header
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    QRService::respond(false, [], 'Direct access not allowed.');
}

// Ensure the Database class is available and get the connection
require_once __DIR__ . '/../database/connection.php';
$conn = Database::getConnection();
if (!$conn || $conn->connect_error) {
    QRService::respond(false, [], 'Database connection failed.');
}

// 1.5 Apply Rate Limiting (5 attempts per minute)
QRService::checkRateLimit($conn, 'order_scan', 5, 60);

// 2. Use QRService to parse and sanitize the input
$rawInput = $_GET['transaction_number'] ?? '';
$parsed = QRService::parse($rawInput);

// Log the attempt regardless of whether it parses correctly to prevent probing
QRService::logAttempt($conn, 'order_scan');

if ($parsed['type'] !== 'order' || !isset($parsed['reference'])) {
    QRService::respond(false, [], 'Invalid order QR code format.');
}

$transactionNumber = $parsed['reference'];

$stmt = $conn->prepare("SELECT id, transaction_number, user_id, student_name, transaction_type, items, subtotal, discount_percent, discount_amount, total_amount, payment_status FROM cashier_transactions WHERE transaction_number = ? LIMIT 1");
$stmt->bind_param('s', $transactionNumber);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    QRService::respond(false, [], 'Order not found.');
}

// 3. Security: Prevent students from scanning/viewing other users' orders (IDOR protection)
if ($_SESSION['role'] === 'student' && intval($order['user_id']) !== intval($_SESSION['user_id'])) {
    QRService::respond(false, [], 'Unauthorized access: This order does not belong to you.');
}

if ($order['payment_status'] === 'paid') {
    QRService::respond(false, [], 'Order is already paid.');
}

// Get user/student details to resolve student_id if possible
$studentId = null;
if ($order['user_id']) {
    $uStmt = $conn->prepare("SELECT student_id FROM users WHERE id = ? AND student_id IS NOT NULL LIMIT 1");
    $uStmt->bind_param('i', $order['user_id']);
    $uStmt->execute();
    $uStmt->bind_result($studentId);
    $uStmt->fetch();
    $uStmt->close();
}

// 4. Clear rate limit on success (Optional: provides better UX for legitimate users)
QRService::resetAttempts($conn, 'order_scan');

$order['student_id'] = $studentId;
$order['items'] = json_decode($order['items'], true);

QRService::respond(true, ['order' => $order]);