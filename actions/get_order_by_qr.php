<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'admin', 'admincashier', 'superadmin']);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_connect.php';

$transactionNumber = $_GET['transaction_number'] ?? null;

if (!$transactionNumber) {
    echo json_encode(['success' => false, 'message' => 'Transaction number required.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, transaction_number, user_id, student_name, transaction_type, items, subtotal, discount_percent, discount_amount, total_amount, payment_status FROM cashier_transactions WHERE transaction_number = ? LIMIT 1");
$stmt->bind_param('s', $transactionNumber);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

if ($order['payment_status'] === 'paid') {
    echo json_encode(['success' => false, 'message' => 'Order is already paid.']);
    exit;
}

// Get user/student details to resolve student_id if possible
$studentId = null;
if ($order['user_id']) {
    $uStmt = $conn->prepare("SELECT student_id FROM users WHERE id = ? LIMIT 1");
    $uStmt->bind_param('i', $order['user_id']);
    $uStmt->execute();
    $uStmt->bind_result($studentId);
    $uStmt->fetch();
    $uStmt->close();
}

$order['student_id'] = $studentId;
$order['items'] = json_decode($order['items'], true);

echo json_encode(['success' => true, 'order' => $order]);
exit;
?>