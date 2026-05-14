<?php
// Prevent HTML error output from corrupting the JSON response
ini_set('display_errors', '0');
if (ob_get_level() == 0) ob_start();

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/QRService.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin', 'student', 'user']);
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
/**
 * Try to find the order using the provided reference.
 * We check for the full transaction number and a version without the 'ORDER-' prefix 
 * to ensure compatibility regardless of how it's stored in the database.
 */
$cleanReference = str_replace('ORDER-', '', $transactionNumber);

$query = "SELECT id, transaction_number, user_id, student_name, transaction_type, items, subtotal, discount_percent, discount_amount, total_amount, payment_status, created_at 
          FROM cashier_transactions 
          WHERE transaction_number = ? OR transaction_number = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $transactionNumber, $cleanReference);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
}

if (!$order || empty($order)) {
    QRService::respond(false, [], 'Order not found.');
}

// 3. Security: Prevent students from scanning/viewing other users' orders (IDOR protection)
if (($_SESSION['role'] === 'student' || $_SESSION['role'] === 'user') && intval($order['user_id']) !== intval($_SESSION['user_id'])) {
    QRService::respond(false, [], 'Unauthorized access: This order does not belong to you.');
}

if ($order['payment_status'] === 'paid') {
    QRService::respond(false, [], 'Order is already paid.');
}

// Get user/student details to resolve student_id if possible
$studentId = null;
if (!empty($order['user_id'])) {
    $uStmt = $conn->prepare("SELECT student_id FROM users WHERE id = ? AND student_id IS NOT NULL LIMIT 1");
    $uStmt->bind_param('i', $order['user_id']);
    $uStmt->execute();
    $uStmt->bind_result($studentId);
    $uStmt->fetch();
    $uStmt->close();
}

$order['student_id'] = $studentId;

// Ensure items are properly decoded into an array for the frontend
if (is_string($order['items'])) {
    $decodedItems = json_decode($order['items'], true);
    $order['items'] = is_array($decodedItems) ? $decodedItems : [];
}

// Ensure data is UTF-8 encoded to prevent json_encode failure
array_walk_recursive($order, function(&$item) {
    if (is_string($item)) $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
});

// Clear rate limit only after all data processing is safe
QRService::resetAttempts($conn, 'order_scan');

ob_clean(); // Clear buffer to ensure only JSON is sent
QRService::respond(true, ['order' => $order]);