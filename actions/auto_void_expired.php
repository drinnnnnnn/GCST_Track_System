<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/email_helpers.php';

$conn->begin_transaction();

try {
    // 1. Find pending orders older than 2 days
    $stmt = $conn->prepare("
        SELECT ct.id, ct.transaction_number, ct.items, ct.student_name,
               ct.guest_email, ct.total_amount, ct.created_at, ct.user_id,
               u.email AS user_email, u.first_name, u.last_name
        FROM cashier_transactions ct
        LEFT JOIN users u ON u.id = ct.user_id
        WHERE ct.payment_status = 'pending'
          AND ct.created_at < (NOW() - INTERVAL 2 DAY)
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $expiredOrders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $voidedCount = 0;
    $ordersToNotify = [];

    if (!empty($expiredOrders)) {
        // Determine stock column
        $stockColumn = 'stock_count';
        $colCheck = $conn->query("SHOW COLUMNS FROM `products` LIKE 'stock_count'");
        if (!$colCheck || $colCheck->num_rows === 0) $stockColumn = 'stock';

        foreach ($expiredOrders as $txn) {
            $txnNumber = $txn['transaction_number'];
            $items = json_decode($txn['items'], true);

            // Claim the order before restoring stock so concurrent runs cannot void it twice.
            $updateTxn = $conn->prepare("UPDATE cashier_transactions SET payment_status = 'voided' WHERE id = ? AND payment_status = 'pending'");
            $updateTxn->bind_param('i', $txn['id']);
            $updateTxn->execute();
            $wasVoided = $updateTxn->affected_rows === 1;
            $updateTxn->close();

            if (!$wasVoided) {
                continue;
            }

            // Restore Stock
            if (is_array($items)) {
                foreach ($items as $item) {
                    $productId = intval($item['product_id']);
                    $qty = intval($item['quantity']);
                    
                    $updateStock = $conn->prepare("UPDATE products SET `$stockColumn` = `$stockColumn` + ? WHERE product_id = ?");
                    $updateStock->bind_param('ii', $qty, $productId);
                    $updateStock->execute();
                    $updateStock->close();
                }
            }

            // Cancel associated rentals
            $updateRentals = $conn->prepare("UPDATE active_rentals SET status = 'returned', rejection_reason = 'Auto-voided: Order Expired (2-day limit)' WHERE transaction_number = ? AND status != 'returned'");
            $updateRentals->bind_param('s', $txnNumber);
            $updateRentals->execute();
            $updateRentals->close();

            if (function_exists('logAudit')) {
                logAudit('system', '0', 'auto_void_transaction', "Auto-voided expired transaction: $txnNumber");
            }
            $ordersToNotify[] = $txn;
            $voidedCount++;
        }
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$notificationSentCount = 0;
$notificationFailedCount = 0;
date_default_timezone_set('Asia/Manila');

foreach ($ordersToNotify as $txn) {
    $recipientEmail = trim((string)($txn['user_email'] ?: $txn['guest_email']));
    $recipientName = trim((string)($txn['first_name'] ?? '') . ' ' . (string)($txn['last_name'] ?? ''));
    if ($recipientName === '') {
        $recipientName = trim((string)($txn['student_name'] ?? '')) ?: 'Student';
    }

    $safeName = htmlspecialchars($recipientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeTransactionNumber = htmlspecialchars((string)$txn['transaction_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $createdAt = strtotime((string)$txn['created_at']);
    $formattedCreatedAt = $createdAt === false ? 'N/A' : date('F j, Y, g:i A', $createdAt);
    $subject = "Order Voided - {$txn['transaction_number']}";
    $body = "
        <div style='font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 20px auto; color: #334155; line-height: 1.6;'>
            <h2 style='color: #1d4ed8;'>Your order has expired</h2>
            <p>Hello <strong>{$safeName}</strong>,</p>
            <p>Your order was voided because it was not validated within 48 hours of being placed.</p>
            <div style='padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;'>
                <p><strong>Order number:</strong> {$safeTransactionNumber}</p>
                <p><strong>Order date:</strong> {$formattedCreatedAt}</p>
                <p><strong>Amount:</strong> ₱" . number_format((float)$txn['total_amount'], 2) . "</p>
            </div>
            <p>If you still need these items, please place a new order.</p>
            <p style='font-size: 12px; color: #64748b;'>Granby Colleges of Science and Technology</p>
        </div>";

    $emailResult = sendEmailWithLog(
        $conn,
        $recipientEmail,
        $subject,
        $body,
        'Expired Order Voided'
    );

    if ($emailResult['success']) {
        $notificationSentCount++;
    } else {
        $notificationFailedCount++;
        error_log("Expired order notification failed for transaction {$txn['transaction_number']}: " . ($emailResult['message'] ?? 'Unknown email error'));
    }
}

echo json_encode([
    'success' => true,
    'voided_count' => $voidedCount,
    'notification_sent_count' => $notificationSentCount,
    'notification_failed_count' => $notificationFailedCount,
]);