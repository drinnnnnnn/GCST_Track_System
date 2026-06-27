<?php
header('Content-Type: application/json');
ini_set('display_errors', '0');
if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connection.php';

secureSessionStart();
requireAuth(['admincashier', 'superadmin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    echo json_encode(['success' => false, 'message' => 'Malformed JSON payload.']);
    exit;
}

$receiptNumber = isset($input['receipt_number']) ? trim((string)$input['receipt_number']) : null;
$receiptCategory = isset($input['receipt_category']) ? trim((string)$input['receipt_category']) : null;
$studentName = isset($input['student_name']) ? trim((string)$input['student_name']) : null;
$studentEmail = isset($input['student_email']) ? trim((string)$input['student_email']) : null;
$studentId = isset($input['student_id']) ? trim((string)$input['student_id']) : null;
$payment_method = isset($input['payment_method']) ? trim((string)$input['payment_method']) : 'Cash';
$checkNumber = isset($input['check_number']) ? trim((string)$input['check_number']) : null;
$paymentStatus = isset($input['payment_status']) && in_array($input['payment_status'], ['paid', 'pending'], true) ? $input['payment_status'] : 'paid';
$totalAmount = isset($input['total_amount']) ? floatval($input['total_amount']) : 0.0;
$paymentReceived = isset($input['payment_received']) ? floatval($input['payment_received']) : 0.0;
$changeAmount = isset($input['change_amount']) ? floatval($input['change_amount']) : max(0, $paymentReceived - $totalAmount);
$authorizedRep = isset($input['authorized_rep']) ? trim((string)$input['authorized_rep']) : null;
$remarks = isset($input['remarks']) ? trim((string)$input['remarks']) : null;
$note = isset($input['note']) ? trim((string)$input['note']) : null;

try {
    if (empty($receiptNumber) || !preg_match('/^[0-9]{6}$/', $receiptNumber)) {
        throw new Exception('A valid 6-digit provisional receipt number is required.');
    }
    if (empty($receiptCategory)) {
        throw new Exception('Receipt category is required.');
    }
    if (empty($studentName)) {
        throw new Exception('Student name is required.');
    }
    if (strcasecmp($payment_method, 'Check') === 0 && empty($checkNumber)) {
        throw new Exception('Check number is required when payment method is Check.');
    }
    if (empty($authorizedRep)) {
        throw new Exception('Authorized representative is required.');
    }

    $conn = Database::getConnection();
    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed.');
    }

    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+08:00'");

    $receiptNumber = $conn->real_escape_string($receiptNumber);
    $receiptCategory = $conn->real_escape_string($receiptCategory);
    $studentName = $conn->real_escape_string($studentName);
    $studentEmail = $conn->real_escape_string($studentEmail);
    $studentId = $conn->real_escape_string($studentId);
    $payment_method = $conn->real_escape_string($payment_method);
    $checkNumber = $conn->real_escape_string($checkNumber);
    $authorizedRep = $conn->real_escape_string($authorizedRep);
    $remarks = $conn->real_escape_string($remarks);
    $note = $conn->real_escape_string($note);

    if ($studentId !== '') {
        $userId = null;
        $stmt = $conn->prepare('SELECT id FROM users WHERE student_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $studentId);
            $stmt->execute();
            $stmt->bind_result($userId);
            $stmt->fetch();
            $stmt->close();
        }
    } else {
        $userId = null;
    }

    $existingReceipt = $conn->prepare('SELECT 1 FROM cashier_transactions WHERE receipt_number = ? LIMIT 1');
    if (!$existingReceipt) {
        throw new Exception('Unable to prepare receipt validation query.');
    }
    $existingReceipt->bind_param('s', $receiptNumber);
    $existingReceipt->execute();
    $existingReceipt->store_result();
    if ($existingReceipt->num_rows > 0) {
        throw new Exception('Duplicate receipt number detected.');
    }
    $existingReceipt->close();

    $transactionNumber = 'TUI-' . time() . '-' . bin2hex(random_bytes(4));
    $itemsJson = json_encode([[
        'product_id' => null,
        'product_name' => $receiptCategory,
        'display_name' => $receiptCategory,
        'type' => 'buy',
        'quantity' => 1,
        'unit_price' => $paymentReceived,
        'unit_name' => 'receipt',
        'total' => $paymentReceived
    ]], JSON_UNESCAPED_UNICODE);

    $conn->begin_transaction();

    $insertSql = "INSERT INTO cashier_transactions (
        transaction_number, receipt_number, receipt_category, user_id, student_name,
        guest_school_id, guest_email, cashier_id, transaction_type, items,
        subtotal, discount_percent, discount_amount, total_amount,
        payment_received, change_amount, payment_status, payment_method,
        check_number, is_expired
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $invoiceType = 'buy';
    $subtotal = $paymentReceived;
    $discountPercent = 0.0;
    $discountAmount = 0.0;
    $isExpiredFlag = ($paymentStatus === 'paid') ? 1 : 0;
    $cashierId = $_SESSION['admin_id'] ?? 0;

    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare save query: ' . $conn->error);
    }
    $stmt->bind_param(
        'sssiississddddddsssi',
        $transactionNumber,
        $receiptNumber,
        $receiptCategory,
        $userId,
        $studentName,
        $studentId,
        $studentEmail,
        $cashierId,
        $invoiceType,
        $itemsJson,
        $subtotal,
        $discountPercent,
        $discountAmount,
        $totalAmount,
        $paymentReceived,
        $changeAmount,
        $paymentStatus,
        $payment_method,
        $checkNumber,
        $isExpiredFlag
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to save tuition receipt: ' . $stmt->error);
    }

    $lastInsertId = $stmt->insert_id;
    $stmt->close();

    if ($studentId !== '' && $receiptCategory === 'Tuition Receipt' && $userId !== null) {
        $feeStmt = $conn->prepare('SELECT total_fees, total_paid, balance FROM tuition_fees WHERE user_id = ? LIMIT 1');
        if ($feeStmt) {
            $feeStmt->bind_param('i', $userId);
            $feeStmt->execute();
            $feeStmt->bind_result($existingTotalFees, $existingTotalPaid, $existingBalance);
            $hasFeeRecord = $feeStmt->fetch();
            $feeStmt->close();

            $newTotalPaid = $paymentReceived;
            $newBalance = max($totalAmount - $paymentReceived, 0.0);
            $newStatus = $newBalance <= 0.0 ? 'Paid' : 'Partial';

            if ($hasFeeRecord) {
                $newTotalPaid = $existingTotalPaid + $paymentReceived;
                $newBalance = max($existingBalance - $paymentReceived, 0.0);
                $newStatus = $newBalance <= 0.0 ? 'Paid' : 'Partial';
                $updateFeeStmt = $conn->prepare('UPDATE tuition_fees SET total_paid = ?, balance = ?, payment_status = ?, updated_at = NOW() WHERE user_id = ?');
                if ($updateFeeStmt) {
                    $updateFeeStmt->bind_param('ddsi', $newTotalPaid, $newBalance, $newStatus, $userId);
                    $updateFeeStmt->execute();
                    $updateFeeStmt->close();
                }
            } else {
                $insertFeeStmt = $conn->prepare('INSERT INTO tuition_fees (user_id, total_fees, total_paid, balance, payment_status) VALUES (?, ?, ?, ?, ?)');
                if ($insertFeeStmt) {
                    $insertFeeStmt->bind_param('iddds', $userId, $totalAmount, $newTotalPaid, $newBalance, $newStatus);
                    $insertFeeStmt->execute();
                    $insertFeeStmt->close();
                }
            }
        }
    }

    $conn->commit();

    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tuition receipt saved successfully.',
        'transaction_number' => $transactionNumber,
        'transaction_id' => $lastInsertId,
        'receipt_number' => $receiptNumber,
        'receipt_category' => $receiptCategory
    ]);
    exit;
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli && $conn->errno === 0) {
        $conn->rollback();
    }
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
