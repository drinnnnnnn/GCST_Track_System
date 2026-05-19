<?php
// Prevent HTML error output from corrupting the JSON response
ini_set('display_errors', '0');
if (ob_get_level() == 0) ob_start();

// Set system timezone to Manila
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin', 'student', 'user']); // Allow relevant roles
header('Content-Type: application/json');

// Ensure the Database class is available and get the connection
require_once __DIR__ . '/../database/connection.php';
$conn = Database::getConnection();
if (!$conn || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$transactionId = $_GET['id'] ?? null;

if (!$transactionId) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID is required.']);
    exit;
}

try {
    // Fetch main transaction details
    $query = "
        SELECT
            ct.id,
            ct.transaction_number,
            ct.receipt_number,
            ct.user_id,
            ct.student_name,
            ct.cashier_id,
            ct.transaction_type,
            ct.subtotal,
            ct.discount_percent,
            ct.discount_amount,
            ct.total_amount,
            ct.payment_received,
            ct.change_amount,
            ct.payment_status,
            ct.created_at,
            aa.name AS cashier_name
        FROM
            cashier_transactions ct
        LEFT JOIN
            admins aa ON ct.cashier_id = aa.id
        WHERE
            ct.transaction_number = ? OR ct.id = ?
        LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $transactionId, $transactionId); // Try matching by number or ID
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found.']);
        exit;
    }

    // Security: Prevent students from viewing other users' orders (IDOR protection)
    if (($_SESSION['role'] === 'student' || $_SESSION['role'] === 'user') && intval($transaction['user_id']) !== intval($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access: This order does not belong to you.']);
        exit;
    }

    // Fetch individual items for the transaction
    $itemsQuery = "
        SELECT
            ti.product_name,
            ti.item_type,
            ti.quantity,
            ti.unit_price,
            ti.duration,
            ti.duration_unit,
            ti.total_item_amount
        FROM
            transaction_items ti
        WHERE
            ti.cashier_transaction_id = ?";
    $itemsStmt = $conn->prepare($itemsQuery);
    $itemsStmt->bind_param('i', $transaction['id']);
    $itemsStmt->execute();
    $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemsStmt->close();

    $transaction['items'] = $items;

    echo json_encode(['success' => true, 'transaction' => $transaction]);

} catch (Exception $e) {
    error_log("Error fetching transaction details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
} finally {
    $conn->close();
}
?>