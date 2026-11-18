<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'user']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
require_once __DIR__ . '/product_return_helpers.php';

try {
    $conn = productReturnsDatabase();
    $transactionNumber = trim((string)($_GET['transaction_number'] ?? ''));
    if ($transactionNumber === '') {
        jsonResponse(['success' => false, 'message' => 'Transaction number is required.'], 400);
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        jsonResponse(['success' => false, 'message' => 'User session is invalid.'], 401);
    }

    $stmt = $conn->prepare("
        SELECT ti.id, ti.product_name, ti.quantity, ti.unit_name,
               COALESCE(SUM(CASE WHEN pr.status <> 'rejected' THEN pr.quantity ELSE 0 END), 0) AS requested_quantity
        FROM cashier_transactions ct
        JOIN transaction_items ti ON ti.cashier_transaction_id = ct.id
        LEFT JOIN product_return_requests pr ON pr.transaction_item_id = ti.id
        WHERE ct.transaction_number = ? AND ct.user_id = ? AND ct.payment_status = 'paid'
          AND ti.item_type = 'buy' AND ti.product_id > 0
        GROUP BY ti.id, ti.product_name, ti.quantity, ti.unit_name
        ORDER BY ti.id
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare product lookup.');
    }
    $stmt->bind_param('si', $transactionNumber, $userId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Unable to load purchased products.');
    }
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $row['remaining_quantity'] = max(0, (float)$row['quantity'] - (float)$row['requested_quantity']);
        unset($row['requested_quantity']);
        if ($row['remaining_quantity'] > 0) {
            $items[] = $row;
        }
    }
    $stmt->close();

    jsonResponse(['success' => true, 'items' => $items, 'csrf_token' => getCsrfToken()]);
} catch (Throwable $e) {
    error_log('Product return item lookup failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to load eligible products.'], 500);
}
