<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admin', 'cashier', 'admincashier', 'superadmin']);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/product_return_helpers.php';
require_once __DIR__ . '/email_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
requireProductReturnCsrf();

$transactionStarted = false;
try {
    $payload = json_decode(file_get_contents('php://input'), true);
    $requestId = filter_var($payload['request_id'] ?? null, FILTER_VALIDATE_INT);
    $action = (string)($payload['action'] ?? '');
    $rejectionReason = trim((string)($payload['rejection_reason'] ?? ''));
    $resolution = trim((string)($payload['resolution'] ?? ''));
    if (!$requestId || $requestId <= 0 || !in_array($action, ['approve', 'reject', 'confirm_refund', 'fulfill_replacement', 'resend_refund_receipt'], true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid return action.'], 400);
    }
    if ($action === 'reject' && ($rejectionReason === '' || mb_strlen($rejectionReason) > 1000)) {
        jsonResponse(['success' => false, 'message' => 'Enter a rejection reason of up to 1,000 characters.'], 400);
    }
    if ($action === 'approve' && !in_array($resolution, ['refund', 'replacement'], true)) {
        jsonResponse(['success' => false, 'message' => 'Choose refund or replacement.'], 400);
    }

    $conn = productReturnsDatabase();
    if (!$conn->begin_transaction()) {
        throw new RuntimeException('Unable to start return review transaction.');
    }
    $transactionStarted = true;
    $requestStmt = $conn->prepare("
        SELECT id, product_id, quantity, request_type, status, handoff_received_at
        FROM product_return_requests
        WHERE id = ?
        FOR UPDATE
    ");
    if (!$requestStmt) {
        throw new RuntimeException('Unable to prepare return review.');
    }
    $requestStmt->bind_param('i', $requestId);
    if (!$requestStmt->execute()) {
        throw new RuntimeException('Unable to load return request.');
    }
    $request = $requestStmt->get_result()->fetch_assoc();
    $requestStmt->close();
    if (!$request) {
        throw new DomainException('Return request not found.');
    }

    $cashierId = (int)($_SESSION['admin_id'] ?? $_SESSION['admincashier_id'] ?? 0);
    $cashierId = $cashierId > 0 ? $cashierId : null;

    if ($action === 'approve' || $action === 'reject') {
        if ($request['status'] !== 'pending') {
            throw new DomainException('This request has already been reviewed.');
        }

        if ($action === 'reject') {
            $update = $conn->prepare("UPDATE product_return_requests SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            if (!$update) {
                throw new RuntimeException('Unable to prepare request rejection.');
            }
            $update->bind_param('sii', $rejectionReason, $cashierId, $requestId);
        } elseif ($resolution === 'refund') {
            $update = $conn->prepare("UPDATE product_return_requests SET request_type = 'refund', status = 'refund_pending', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            if (!$update) {
                throw new RuntimeException('Unable to prepare refund approval.');
            }
            $update->bind_param('ii', $cashierId, $requestId);
        } else {
            $stockColumn = productStockColumn($conn);
            $productId = (int)$request['product_id'];
            $stockStmt = $conn->prepare("SELECT `$stockColumn` AS stock FROM products WHERE product_id = ? FOR UPDATE");
            if (!$stockStmt) {
                throw new RuntimeException('Unable to prepare replacement stock check.');
            }
            $stockStmt->bind_param('i', $productId);
            if (!$stockStmt->execute()) {
                throw new RuntimeException('Unable to check replacement stock.');
            }
            $product = $stockStmt->get_result()->fetch_assoc();
            $stockStmt->close();
            if (!$product) {
                throw new DomainException('The product is no longer available for replacement.');
            }

            $reservedStmt = $conn->prepare("
                SELECT COALESCE(SUM(quantity), 0) AS reserved
                FROM product_return_requests
                WHERE product_id = ? AND status = 'replacement_pending'
            ");
            if (!$reservedStmt) {
                throw new RuntimeException('Unable to prepare replacement reservation check.');
            }
            $reservedStmt->bind_param('i', $productId);
            if (!$reservedStmt->execute()) {
                throw new RuntimeException('Unable to check replacement reservations.');
            }
            $reserved = (float)$reservedStmt->get_result()->fetch_assoc()['reserved'];
            $reservedStmt->close();
            if ((float)$product['stock'] - $reserved + 0.00001 < (float)$request['quantity']) {
                throw new DomainException('There is not enough available stock to approve this replacement.');
            }

            $update = $conn->prepare("UPDATE product_return_requests SET request_type = 'replacement', status = 'replacement_pending', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            if (!$update) {
                throw new RuntimeException('Unable to prepare replacement approval.');
            }
            $update->bind_param('ii', $cashierId, $requestId);
        }
        if (!$update->execute()) {
            throw new RuntimeException('Unable to save return review.');
        }
        $update->close();
    } elseif ($action === 'confirm_refund') {
        if ($request['status'] !== 'refund_pending') {
            throw new DomainException('This request is not awaiting a refund.');
        }
        if (empty($request['handoff_received_at'])) {
            throw new DomainException('Scan the return QR code and record the item handoff before confirming the refund.');
        }
        $update = $conn->prepare("UPDATE product_return_requests SET status = 'refunded', fulfilled_by = ?, fulfilled_at = NOW() WHERE id = ?");
        if (!$update) {
            throw new RuntimeException('Unable to prepare refund confirmation.');
        }
        $update->bind_param('ii', $cashierId, $requestId);
        if (!$update->execute()) {
            throw new RuntimeException('Unable to record refund confirmation.');
        }
        $update->close();
    } elseif ($action === 'resend_refund_receipt') {
        if ($request['status'] !== 'refunded' || $request['request_type'] !== 'refund') {
            throw new DomainException('A receipt can only be resent for a completed refund.');
        }
    } else {
        if ($request['status'] !== 'replacement_pending') {
            throw new DomainException('This request is not awaiting a replacement.');
        }
        if (empty($request['handoff_received_at'])) {
            throw new DomainException('Scan the return QR code and record the item handoff before fulfilling the replacement.');
        }

        $stockColumn = productStockColumn($conn);
        $productId = (int)$request['product_id'];
        $quantity = (float)$request['quantity'];
        $stockStmt = $conn->prepare("SELECT `$stockColumn` AS stock FROM products WHERE product_id = ? FOR UPDATE");
        if (!$stockStmt) {
            throw new RuntimeException('Unable to prepare replacement stock check.');
        }
        $stockStmt->bind_param('i', $productId);
        if (!$stockStmt->execute()) {
            throw new RuntimeException('Unable to check replacement stock.');
        }
        $product = $stockStmt->get_result()->fetch_assoc();
        $stockStmt->close();
        if (!$product || (float)$product['stock'] + 0.00001 < $quantity) {
            throw new DomainException('Replacement stock is no longer sufficient. The request remains pending fulfillment.');
        }

        $stockUpdate = $conn->prepare("UPDATE products SET `$stockColumn` = `$stockColumn` - ? WHERE product_id = ? AND `$stockColumn` >= ?");
        if (!$stockUpdate) {
            throw new RuntimeException('Unable to prepare replacement inventory update.');
        }
        $stockUpdate->bind_param('did', $quantity, $productId, $quantity);
        if (!$stockUpdate->execute() || $stockUpdate->affected_rows !== 1) {
            throw new RuntimeException('Unable to deduct replacement inventory.');
        }
        $stockUpdate->close();

        $update = $conn->prepare("UPDATE product_return_requests SET status = 'completed', fulfilled_by = ?, fulfilled_at = NOW() WHERE id = ?");
        if (!$update) {
            throw new RuntimeException('Unable to prepare replacement fulfillment.');
        }
        $update->bind_param('ii', $cashierId, $requestId);
        if (!$update->execute()) {
            throw new RuntimeException('Unable to record replacement fulfillment.');
        }
        $update->close();
    }

    if (!$conn->commit()) {
        throw new RuntimeException('Unable to commit return review.');
    }
    $transactionStarted = false;

    $emailStatus = 'not_applicable';
    $message = 'Return request updated.';
    if ($action === 'reject' || $action === 'confirm_refund' || $action === 'fulfill_replacement' || $action === 'resend_refund_receipt') {
        $emailStatus = 'failed';
        try {
            $receiptStmt = $conn->prepare("
                SELECT pr.id, pr.product_name, pr.quantity, pr.unit_name,
                       pr.refund_amount, pr.status, pr.rejection_reason, pr.reviewed_at,
                       pr.fulfilled_at,
                       COALESCE(ct.transaction_number, CONCAT('Order #', pr.cashier_transaction_id)) AS transaction_number,
                       u.email, u.first_name, u.last_name,
                       CONCAT_WS(' ', fulfiller.first_name, fulfiller.last_name) AS cashier_name,
                       CONCAT_WS(' ', reviewer.first_name, reviewer.last_name) AS reviewer_name
                FROM product_return_requests pr
                LEFT JOIN cashier_transactions ct ON ct.id = pr.cashier_transaction_id
                JOIN users u ON u.id = pr.user_id
                LEFT JOIN admincashier_acc fulfiller ON fulfiller.id = pr.fulfilled_by
                LEFT JOIN admincashier_acc reviewer ON reviewer.id = pr.reviewed_by
                WHERE pr.id = ?
                LIMIT 1
            ");
            if (!$receiptStmt) {
                throw new RuntimeException('Unable to prepare return e-receipt details.');
            }
            $receiptStmt->bind_param('i', $requestId);
            if (!$receiptStmt->execute()) {
                throw new RuntimeException('Unable to load return e-receipt details.');
            }
            $receipt = $receiptStmt->get_result()->fetch_assoc();
            $receiptStmt->close();
            if (!$receipt) {
                throw new RuntimeException('Return e-receipt details were not found.');
            }

            $recipientEmail = trim((string)$receipt['email']);
            if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The user does not have a valid email address.');
            }

            $recipientName = trim((string)$receipt['first_name'] . ' ' . (string)$receipt['last_name']);
            $recipientName = $recipientName !== '' ? $recipientName : 'Customer';
            $receiptNumber = 'PR-' . (int)$receipt['id'];
            if ($action === 'reject') {
                $emailSubject = 'Update on your product return request';
                $emailType = 'Product Return Rejection';
                $emailBody = "
                    <div style='font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1f2937;line-height:1.6'>
                      <h2 style='color:#1e3a5f'>Product Return Request Update</h2>
                      <p>Hello " . htmlspecialchars($recipientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ",</p>
                      <p>We reviewed your damaged-product return request. We are unable to approve it.</p>
                      <div style='margin:20px 0;padding:16px;border:1px solid #dbe3ec;border-radius:10px'>
                        <p><strong>Return reference:</strong> " . htmlspecialchars($receiptNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                        <p><strong>Order:</strong> " . htmlspecialchars((string)$receipt['transaction_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                        <p><strong>Product:</strong> " . htmlspecialchars((string)$receipt['product_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                        <p><strong>Quantity:</strong> " . htmlspecialchars((string)$receipt['quantity'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' ' . htmlspecialchars((string)($receipt['unit_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                        <p><strong>Reason:</strong> " . nl2br(htmlspecialchars((string)$receipt['rejection_reason'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . "</p>
                        <p><strong>Reviewed:</strong> " . htmlspecialchars((string)$receipt['reviewed_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                        <p><strong>Reviewed by:</strong> " . htmlspecialchars(trim((string)($receipt['reviewer_name'] ?? '')) ?: 'Cashier', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                      </div>
                      <p>If you have questions about this decision, please contact the cashier.</p>
                    </div>
                ";
            } else {
                $emailSubject = 'Your product return e-receipt';
                $emailType = 'Product Return E-Receipt';
                $resolution = $receipt['status'] === 'refunded' ? 'Refund paid' : 'Replacement fulfilled';
                $resolutionDetails = $receipt['status'] === 'refunded'
                    ? '<p><strong>Refund amount:</strong> ₱' . number_format((float)$receipt['refund_amount'], 2) . '</p><p><strong>Refund method:</strong> Cash</p>'
                    : '';
                $emailBody = "
                    <div style='font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1f2937;line-height:1.6'>
                      <h2 style='color:#1e3a5f'>Product Return E-Receipt</h2>
                      <p>Hello " . htmlspecialchars($recipientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ",</p>
                      <p>Your product return has been completed. Keep this email as your receipt.</p>
                      <div style='margin:20px 0;padding:16px;border:1px solid #dbe3ec;border-radius:10px'>
                        <p><strong>Receipt:</strong> " . htmlspecialchars($receiptNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                        <p><strong>Order:</strong> " . htmlspecialchars((string)$receipt['transaction_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                        <p><strong>Product:</strong> " . htmlspecialchars((string)$receipt['product_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                        <p><strong>Quantity:</strong> " . htmlspecialchars((string)$receipt['quantity'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' ' . htmlspecialchars((string)($receipt['unit_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                        <p><strong>Resolution:</strong> " . htmlspecialchars($resolution, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                        $resolutionDetails
                        <p><strong>Completed:</strong> " . htmlspecialchars((string)$receipt['fulfilled_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                        <p><strong>Processed by:</strong> " . htmlspecialchars(trim((string)($receipt['cashier_name'] ?? '')) ?: 'Cashier', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                      </div>
                    </div>
                ";
            }
            $emailResult = sendEmailWithLog(
                $conn,
                $recipientEmail,
                $emailSubject,
                $emailBody,
                $emailType
            );
            $emailStatus = (string)($emailResult['status'] ?? 'failed');
            if (!empty($emailResult['success'])) {
                $message = $action === 'reject'
                    ? 'Return rejected and the explanation email was sent to the user.'
                    : ($action === 'resend_refund_receipt'
                        ? 'The refund receipt was sent to the user again.'
                        : 'Return completed and the e-receipt was sent to the user.');
            } else {
                error_log('Product return e-receipt email failed for request ' . $requestId . ': ' . (string)($emailResult['message'] ?? 'Unknown email error.'));
                $message = $action === 'reject'
                    ? 'Return rejected, but the explanation email could not be sent. Check the user email and mail configuration.'
                    : ($action === 'resend_refund_receipt'
                        ? 'The refund receipt could not be emailed. Check the user email and mail configuration.'
                        : 'Return completed, but the e-receipt could not be emailed. Check the user email and mail configuration.');
            }
        } catch (Throwable $e) {
            error_log('Product return email failed for request ' . $requestId . ': ' . $e->getMessage());
            $message = $action === 'reject'
                ? 'Return rejected, but the explanation email could not be sent. Check the user email and mail configuration.'
                : ($action === 'resend_refund_receipt'
                    ? 'The refund receipt could not be emailed. Check the user email and mail configuration.'
                    : 'Return completed, but the e-receipt could not be emailed. Check the user email and mail configuration.');
        }
    }

    jsonResponse([
        'success' => true,
        'message' => $message,
        'email_status' => $emailStatus
    ]);
} catch (DomainException $e) {
    if (isset($conn) && $transactionStarted) {
        $conn->rollback();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    if (isset($conn) && $transactionStarted) {
        $conn->rollback();
    }
    error_log('Product return update failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to update return request. Please try again.'], 500);
}
