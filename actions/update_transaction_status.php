<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

$payload = json_decode(file_get_contents('php://input'), true);
$transactionId = $payload['transaction_id'] ?? null;
$newStatus = $payload['status'] ?? null;

if (!$transactionId || !in_array($newStatus, ['paid', 'voided'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$conn->begin_transaction();

try {
    // Fetch transaction details
    $stmt = $conn->prepare("SELECT transaction_number, items, payment_status FROM cashier_transactions WHERE id = ?");
    $stmt->bind_param('i', $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();
    $stmt->close();

    if (!$transaction) {
        throw new Exception('Transaction not found.');
    }

    if ($transaction['payment_status'] !== 'pending') {
        throw new Exception('Only pending transactions can be updated.');
    }

    // Update transaction status
    $stmt = $conn->prepare("UPDATE cashier_transactions SET payment_status = ? WHERE id = ?");
    $stmt->bind_param('si', $newStatus, $transactionId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update transaction status: ' . $stmt->error);
    }
    $stmt->close();

    // If voided, return stock to products table
    if ($newStatus === 'voided') {
        $items = json_decode($transaction['items'], true);
        if (is_array($items)) {
            // Determine the correct stock column name
            $stockColumn = 'stock_count';
            $stockCheck = $conn->query("SHOW COLUMNS FROM `products` LIKE 'stock_count'");
            if (!$stockCheck || $stockCheck->num_rows === 0) {
                $stockColumn = 'stock'; // Fallback to 'stock' if 'stock_count' doesn't exist
            }

            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $quantity = $item['quantity'] ?? null;

                if ($productId && $quantity > 0) {
                    $updateStockStmt = $conn->prepare("UPDATE products SET `$stockColumn` = `$stockColumn` + ? WHERE product_id = ?");
                    $updateStockStmt->bind_param('ii', $quantity, $productId);
                    if (!$updateStockStmt->execute()) {
                        throw new Exception('Failed to return stock for product ' . $productId . ': ' . $updateStockStmt->error);
                    }
                    $updateStockStmt->close();
                }
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Transaction status updated successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>