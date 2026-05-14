<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin', 'student', 'user']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

try {
    // JS sends the transaction number as the 'id' parameter
    $txnNumber = $_GET['id'] ?? $_GET['transaction_number'] ?? '';
    $role = $_SESSION['role'];
    $session_student_id = $_SESSION['student_id'] ?? null;

    $sql = "SELECT ct.*, u.student_id, 
                   CONCAT(ac.first_name, ' ', ac.last_name) as cashier_name
            FROM cashier_transactions ct
            LEFT JOIN users u ON ct.user_id = u.id
            LEFT JOIN admincashier_acc ac ON ct.cashier_id = ac.id
            WHERE ct.transaction_number = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $txnNumber);
    $stmt->execute();
    $txn = $stmt->get_result()->fetch_assoc();

    if (!$txn) {
        throw new Exception('Transaction not found.');
    }

    // Ownership check
    if (in_array($role, ['student', 'user']) && $txn['student_id'] !== $session_student_id) {
        throw new Exception('Access denied.');
    }

    // Fetch line items for the receipt
    $items_sql = "SELECT ti.*, p.product_name 
                  FROM transaction_items ti 
                  JOIN products p ON ti.product_id = p.product_id 
                  WHERE ti.cashier_transaction_id = ?";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param('i', $txn['id']);
    $items_stmt->execute();
    $items_res = $items_stmt->get_result();
    $txn['items'] = [];
    while($row = $items_res->fetch_assoc()) {
        $txn['items'][] = $row;
    }

    echo json_encode(['success' => true, 'transaction' => $txn]);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}