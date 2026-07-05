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
$orNumber = isset($input['or_number']) ? trim((string)$input['or_number']) : null;
$totalPayment = isset($input['total_payment']) ? floatval($input['total_payment']) : null;
$balance = isset($input['balance']) ? floatval($input['balance']) : 0.0;
$paymentType = isset($input['payment_type']) ? trim((string)$input['payment_type']) : 'Partial Payment';

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

    // Generate a unique receipt number if the provided one already exists
    // Check ONLY in tuition_receipts table (isolated from regular transactions)
    $originalReceiptNumber = $receiptNumber;
    $attemptCount = 0;
    $maxAttempts = 50;
    
    while ($attemptCount < $maxAttempts) {
        $existingReceipt = $conn->prepare('SELECT 1 FROM tuition_receipts WHERE receipt_number = ? LIMIT 1');
        if (!$existingReceipt) {
            throw new Exception('Unable to prepare receipt validation query.');
        }
        $existingReceipt->bind_param('s', $receiptNumber);
        $existingReceipt->execute();
        $existingReceipt->store_result();
        
        if ($existingReceipt->num_rows === 0) {
            $existingReceipt->close();
            break; // Receipt number is unique, proceed
        }
        $existingReceipt->close();
        
        // Generate a new unique 6-digit receipt number
        $receiptNumber = sprintf('%06d', random_int(0, 999999));
        $attemptCount++;
    }
    
    if ($attemptCount >= $maxAttempts) {
        throw new Exception('Unable to generate a unique receipt number. Please try again.');
    }

    $transactionNumber = 'TUI-' . time() . '-' . bin2hex(random_bytes(4));
    
    $conn->begin_transaction();

    // Insert into separate tuition_receipts table
    $insertSql = "INSERT INTO tuition_receipts (
        transaction_number, receipt_number, user_id, student_id, student_name,
        student_email, cashier_id, receipt_category, amount_paid, total_payment,
        balance, or_number, check_number, payment_method, payment_type,
        remarks, note, authorized_rep, payment_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $cashierId = $_SESSION['admin_id'] ?? 0;
    $finalTotalPayment = $totalPayment !== null ? $totalPayment : $paymentReceived;
    $finalBalance = $balance !== null ? $balance : 0.0;

    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare save query: ' . $conn->error);
    }
    $stmt->bind_param(
        'ssisssissddssssssss',
        $transactionNumber,
        $receiptNumber,
        $userId,
        $studentId,
        $studentName,
        $studentEmail,
        $cashierId,
        $receiptCategory,
        $paymentReceived,
        $finalTotalPayment,
        $finalBalance,
        $orNumber,
        $checkNumber,
        $payment_method,
        $paymentType,
        $remarks,
        $note,
        $authorizedRep,
        $paymentStatus
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to save tuition receipt: ' . $stmt->error);
    }

    $lastInsertId = $stmt->insert_id;
    $stmt->close();

    $historyItemsJson = json_encode([
        [
            'type' => 'receipt',
            'receipt_number' => $receiptNumber,
            'receipt_category' => $receiptCategory,
            'student_name' => $studentName,
            'payment_status' => $paymentStatus,
        ]
    ], JSON_UNESCAPED_UNICODE);
    if ($historyItemsJson === false) {
        $historyItemsJson = '[]';
    }

    $historyUserId = $userId !== null ? (int) $userId : 0;
    $historyGuestSchoolId = '';
    $historyGuestEmail = '';
    $historyTransactionType = 'buy';
    $historySubtotal = round($paymentReceived, 2);
    $historyDiscountPercent = 0.0;
    $historyDiscountAmount = 0.0;
    $historyTotalAmount = round($finalTotalPayment !== null ? $finalTotalPayment : $paymentReceived, 2);
    $historyChangeAmount = round($changeAmount, 2);
    $historyPaymentMethod = $payment_method;
    $historyCheckNumber = $checkNumber !== null ? $checkNumber : '';
    $historyIsExpired = $paymentStatus === 'paid' ? 1 : 0;

    $historyInsertSql = "INSERT INTO cashier_transactions (
        transaction_number, receipt_number, receipt_category, user_id, student_name, guest_school_id, guest_email, cashier_id,
        transaction_type, items, subtotal, discount_percent, discount_amount, total_amount, payment_received, change_amount,
        payment_status, payment_method, check_number, is_expired
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $historyStmt = $conn->prepare($historyInsertSql);
    if (!$historyStmt) {
        throw new Exception('Failed to prepare cashier history insert: ' . $conn->error);
    }

    $historyStmt->bind_param(
        'sssisssissddddddsssi',
        $transactionNumber,
        $receiptNumber,
        $receiptCategory,
        $historyUserId,
        $studentName,
        $historyGuestSchoolId,
        $historyGuestEmail,
        $cashierId,
        $historyTransactionType,
        $historyItemsJson,
        $historySubtotal,
        $historyDiscountPercent,
        $historyDiscountAmount,
        $historyTotalAmount,
        $paymentReceived,
        $historyChangeAmount,
        $paymentStatus,
        $historyPaymentMethod,
        $historyCheckNumber,
        $historyIsExpired
    );

    if (!$historyStmt->execute()) {
        throw new Exception('Failed to store receipt history entry: ' . $historyStmt->error);
    }
    $historyStmt->close();

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
