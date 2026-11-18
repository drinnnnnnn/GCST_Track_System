<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'user']);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/product_return_helpers.php';
require_once __DIR__ . '/email_helpers.php';
require_once __DIR__ . '/qr_code_generator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
requireProductReturnCsrf();

$photoPath = null;
$transactionStarted = false;
try {
    $transactionItemId = filter_var($_POST['transaction_item_id'] ?? null, FILTER_VALIDATE_INT);
    $quantityInput = trim((string)($_POST['quantity'] ?? ''));
    $quantity = preg_match('/^\d+(?:\.\d{1,2})?$/', $quantityInput) ? (float)$quantityInput : false;
    $requestType = (string)($_POST['request_type'] ?? '');
    $description = trim((string)($_POST['damage_description'] ?? ''));
    $photo = $_FILES['damage_photo'] ?? null;

    if (!$transactionItemId || $transactionItemId <= 0 || $quantity === false || $quantity <= 0) {
        jsonResponse(['success' => false, 'message' => 'Choose a product and enter a valid quantity.'], 400);
    }
    if (!in_array($requestType, ['refund', 'replacement'], true)) {
        jsonResponse(['success' => false, 'message' => 'Choose a refund or replacement.'], 400);
    }
    if ($description === '' || mb_strlen($description) > 2000) {
        jsonResponse(['success' => false, 'message' => 'Describe the damage (up to 2,000 characters).'], 400);
    }
    if (!$photo || !isset($photo['error']) || $photo['error'] !== UPLOAD_ERR_OK || $photo['size'] <= 0 || $photo['size'] > 5 * 1024 * 1024) {
        jsonResponse(['success' => false, 'message' => 'Upload a damage photo no larger than 5 MB.'], 400);
    }

    $imageInfo = @getimagesize($photo['tmp_name']);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($photo['tmp_name']);
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$imageInfo || !isset($allowedTypes[$mimeType])) {
        jsonResponse(['success' => false, 'message' => 'Use a JPEG, PNG, or WebP damage photo.'], 400);
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        jsonResponse(['success' => false, 'message' => 'User session is invalid.'], 401);
    }
    $conn = productReturnsDatabase();

    $itemStmt = $conn->prepare('SELECT cashier_transaction_id FROM transaction_items WHERE id = ? LIMIT 1');
    if (!$itemStmt) {
        throw new RuntimeException('Unable to prepare purchase lookup.');
    }
    $itemStmt->bind_param('i', $transactionItemId);
    if (!$itemStmt->execute()) {
        throw new RuntimeException('Unable to load purchased product.');
    }
    $itemRow = $itemStmt->get_result()->fetch_assoc();
    $itemStmt->close();
    if (!$itemRow) {
        jsonResponse(['success' => false, 'message' => 'Purchased product not found.'], 404);
    }

    $cashierTransactionId = (int)$itemRow['cashier_transaction_id'];
    $uploadDirectory = __DIR__ . '/../uploads/product_returns';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Unable to create product return photo storage.');
    }
    $photoFilename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    $photoPath = $uploadDirectory . '/' . $photoFilename;
    if (!move_uploaded_file($photo['tmp_name'], $photoPath)) {
        throw new RuntimeException('Unable to store the damage photo.');
    }

    if (!$conn->begin_transaction()) {
        throw new RuntimeException('Unable to start return request transaction.');
    }
    $transactionStarted = true;
    $transactionStmt = $conn->prepare("
        SELECT id, transaction_number, subtotal, total_amount
        FROM cashier_transactions
        WHERE id = ? AND user_id = ? AND payment_status = 'paid'
        FOR UPDATE
    ");
    if (!$transactionStmt) {
        throw new RuntimeException('Unable to prepare purchase validation.');
    }
    $transactionStmt->bind_param('ii', $cashierTransactionId, $userId);
    if (!$transactionStmt->execute()) {
        throw new RuntimeException('Unable to validate purchase.');
    }
    $transaction = $transactionStmt->get_result()->fetch_assoc();
    $transactionStmt->close();
    if (!$transaction) {
        throw new DomainException('Only your completed purchases are eligible for a return.');
    }

    $productStmt = $conn->prepare("
        SELECT id, product_id, product_name, quantity, unit_name, unit_price
        FROM transaction_items
        WHERE id = ? AND cashier_transaction_id = ? AND item_type = 'buy' AND product_id > 0
        FOR UPDATE
    ");
    if (!$productStmt) {
        throw new RuntimeException('Unable to prepare purchased product validation.');
    }
    $productStmt->bind_param('ii', $transactionItemId, $cashierTransactionId);
    if (!$productStmt->execute()) {
        throw new RuntimeException('Unable to validate purchased product.');
    }
    $product = $productStmt->get_result()->fetch_assoc();
    $productStmt->close();
    if (!$product) {
        throw new DomainException('This purchase line is not eligible for a return.');
    }

    $reservedStmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity), 0) AS requested_quantity
        FROM product_return_requests
        WHERE transaction_item_id = ? AND status <> 'rejected'
    ");
    if (!$reservedStmt) {
        throw new RuntimeException('Unable to prepare return quantity validation.');
    }
    $reservedStmt->bind_param('i', $transactionItemId);
    if (!$reservedStmt->execute()) {
        throw new RuntimeException('Unable to validate return quantity.');
    }
    $requestedQuantity = (float)$reservedStmt->get_result()->fetch_assoc()['requested_quantity'];
    $reservedStmt->close();
    if ($quantity > (float)$product['quantity'] - $requestedQuantity + 0.00001) {
        throw new DomainException('The requested quantity exceeds the amount available to return.');
    }

    $subtotal = (float)$transaction['subtotal'];
    $paidTotal = (float)$transaction['total_amount'];
    $priceFactor = $subtotal > 0 ? $paidTotal / $subtotal : 1;
    $refundAmount = round((float)$product['unit_price'] * $quantity * $priceFactor, 2);
    $handoffToken = bin2hex(random_bytes(32));
    $handoffTokenHash = hash('sha256', $handoffToken);

    $insertStmt = $conn->prepare("
        INSERT INTO product_return_requests
            (user_id, cashier_transaction_id, transaction_item_id, product_id, product_name,
             quantity, unit_name, unit_price, refund_amount, request_type, damage_description, photo_filename,
             handoff_token_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$insertStmt) {
        throw new RuntimeException('Unable to prepare return request.');
    }
    $productId = (int)$product['product_id'];
    $productName = (string)$product['product_name'];
    $unitName = $product['unit_name'] === null ? null : (string)$product['unit_name'];
    $unitPrice = (float)$product['unit_price'];
    $insertStmt->bind_param(
        'iiiisdsddssss',
        $userId,
        $cashierTransactionId,
        $transactionItemId,
        $productId,
        $productName,
        $quantity,
        $unitName,
        $unitPrice,
        $refundAmount,
        $requestType,
        $description,
        $photoFilename,
        $handoffTokenHash
    );
    if (!$insertStmt->execute()) {
        throw new RuntimeException('Unable to save return request.');
    }
    $requestId = $insertStmt->insert_id;
    $insertStmt->close();
    if (!$conn->commit()) {
        throw new RuntimeException('Unable to commit return request.');
    }
    $transactionStarted = false;
    $photoPath = null;

    $emailStatus = 'failed';
    $emailMessage = 'Your return request was submitted, but we could not email its QR code. Please contact the cashier.';
    $qrPath = null;
    try {
        $userStmt = $conn->prepare('SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1');
        if (!$userStmt) {
            throw new RuntimeException('Unable to prepare return email recipient lookup.');
        }
        $userStmt->bind_param('i', $userId);
        if (!$userStmt->execute()) {
            throw new RuntimeException('Unable to load return email recipient.');
        }
        $user = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
        if (!$user) {
            throw new RuntimeException('Return email recipient was not found.');
        }

        $qrPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gcst_product_return_' . bin2hex(random_bytes(16)) . '.png';
        if (!generateLocalQrCode('GCST-RETURN-' . $handoffToken, $qrPath, 'H', 8, 4)) {
            throw new RuntimeException('Unable to generate product return QR code.');
        }

        $recipientName = trim((string)$user['first_name'] . ' ' . (string)$user['last_name']);
        $recipientName = $recipientName !== '' ? $recipientName : 'there';
        $emailBody = "
            <div style='font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1f2937;line-height:1.6'>
              <h2 style='color:#1e3a5f'>Damaged product return submitted</h2>
              <p>Hello " . htmlspecialchars($recipientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ",</p>
              <p>Your return request is awaiting cashier review. Bring the damaged item and show this QR code to the cashier so they can record the handoff.</p>
              <div style='margin:20px 0;padding:16px;text-align:center;border:1px solid #dbe3ec;border-radius:10px'>
                <img src='cid:product_return_qr' width='220' height='220' alt='Product return QR code' style='display:block;margin:0 auto'>
                <p style='margin:12px 0 0'><strong>Order:</strong> " . htmlspecialchars((string)$transaction['transaction_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                <p style='margin:4px 0'><strong>Product:</strong> " . htmlspecialchars((string)$product['product_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                <p style='margin:4px 0'><strong>Quantity:</strong> " . htmlspecialchars($quantityInput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' ' . htmlspecialchars((string)($product['unit_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
                <p style='margin:4px 0'><strong>Requested resolution:</strong> " . htmlspecialchars(ucfirst($requestType), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>
              </div>
              <p>The QR code records item receipt only. Refunds and replacements are handled separately after review.</p>
            </div>
        ";
        $emailResult = sendEmailWithLog(
            $conn,
            trim((string)$user['email']),
            'Your damaged product return QR code',
            $emailBody,
            'Product Return QR',
            [['path' => $qrPath, 'name' => 'product-return-qr.png', 'cid' => 'product_return_qr']]
        );
        $emailStatus = (string)($emailResult['status'] ?? 'failed');
        if (!empty($emailResult['success'])) {
            $emailMessage = 'Return request submitted. The QR code was sent to your registered email.';
        } else {
            error_log('Product return QR email failed for request ' . $requestId . ': ' . (string)($emailResult['message'] ?? 'Unknown email error.'));
        }
    } catch (Throwable $e) {
        error_log('Product return QR email failed for request ' . $requestId . ': ' . $e->getMessage());
    } finally {
        if ($qrPath && is_file($qrPath) && !unlink($qrPath)) {
            error_log('Unable to remove temporary product return QR file: ' . $qrPath);
        }
    }

    jsonResponse([
        'success' => true,
        'request_id' => $requestId,
        'email_status' => $emailStatus,
        'message' => $emailMessage
    ]);
} catch (DomainException $e) {
    if (isset($conn) && $transactionStarted) {
        $conn->rollback();
    }
    if ($photoPath && is_file($photoPath)) {
        if (!unlink($photoPath)) {
            error_log('Unable to remove rejected product return photo: ' . $photoPath);
        }
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    if (isset($conn) && $transactionStarted) {
        $conn->rollback();
    }
    if ($photoPath && is_file($photoPath) && !unlink($photoPath)) {
        error_log('Unable to remove orphaned product return photo: ' . $photoPath);
    }
    error_log('Product return request failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to submit return request. Please try again.'], 500);
}
