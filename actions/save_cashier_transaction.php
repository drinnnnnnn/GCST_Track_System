<?php
// Force JSON output even if errors occur
header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
ini_set('display_errors', '0'); // Prevent HTML error output
ob_start(); // Buffer any accidental output

require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'user', 'admin', 'admincashier', 'superadmin']);

// Set system timezone to Manila for accurate "Today" calculations
date_default_timezone_set('Asia/Manila');

try {
    require_once __DIR__ . '/../database/migrations/MigrationManager.php';
    // Ensure the Database class is available and get the connection
    require_once __DIR__ . '/../database/connection.php';
    $conn = Database::getConnection();
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Ensure the database session also uses the correct timezone for CURRENT_TIMESTAMP
    $conn->query("SET time_zone = '+08:00'");

    // Centralized Migration Check
    (new MigrationManager())->run();

    // Safe include helper to ensure helpers have access to the database connection
    function safeInclude($path, $name) {
        global $conn; // Add this line to bring the database connection into the function scope
        if (!file_exists($path)) {
            return ["success" => false, "message" => "Component missing: $name at $path"];
        }
        require_once $path;
        return ["success" => true];
    }

    $checkEmail = safeInclude(__DIR__ . '/email_helpers.php', 'Email Helper');
    $checkAudit = safeInclude(__DIR__ . '/audit_helpers.php', 'Audit Helper');
    $checkQR = safeInclude(__DIR__ . '/qr_code_generator.php', 'QR Generator');

    if (!$checkEmail['success']) throw new Exception($checkEmail['message']);
    if (!$checkAudit['success']) throw new Exception($checkAudit['message']);
    if (!$checkQR['success']) throw new Exception($checkQR['message']);

    $adminId = $_SESSION['admin_id'] ?? null;
    $cashierName = trim((string)($_SESSION['admin_name'] ?? $_SESSION['admincashier_name'] ?? $_SESSION['admincashier'] ?? ''));
    if ($cashierName === '') {
        $cashierName = 'Admin Cashier';
    }
    $payload = json_decode(file_get_contents('php://input'), true);
    $isGuest = $payload['is_guest'] ?? false;
    $guestName = $payload['guest_name'] ?? null;
    $guestSchoolId = $payload['guest_school_id'] ?? null;
    $guestEmail = $payload['guest_email'] ?? null;
    
    // Guest Validation Enforcement
    if ($isGuest) {
        if (empty($guestName)) throw new Exception('Guest Name is required.');

        if (empty($guestSchoolId) || !preg_match('/^GC-\d{6}$/', $guestSchoolId)) {
            throw new Exception('A valid 6-digit School ID (Format: GC-######) is required for guest transactions.');
        }
        if (empty($guestEmail) || !preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $guestEmail)) {
            throw new Exception('A valid Gmail address (@gmail.com) is required for guest transactions.');
        }

        // Backend Validation: Ensure discount is only applied to matched School IDs
        if ($discountPercent > 0 && !empty($guestSchoolId)) {
            $checkStudent = $conn->prepare("SELECT 1 FROM users WHERE student_id = ? LIMIT 1");
            $checkStudent->bind_param('s', $guestSchoolId);
            $checkStudent->execute();
            $isRealStudent = $checkStudent->get_result()->num_rows > 0;
            $checkStudent->close();

            if (!$isRealStudent) {
                throw new Exception('Unauthorized Discount: The provided School ID is not eligible for a student discount.');
            }
            if ($discountPercent > 5.01) { // Safety buffer for float comparison
                throw new Exception('Invalid Discount: Applied rate exceeds the permitted student discount.');
            }
        }
    }

    // Security Enforcement: Restrict manual admin transactions without QR scan
    $userRole = $_SESSION['role'] ?? '';
    // Only enforce for PAID transactions finalize at the POS. PENDING orders (student self-service)
    // bypass the manual cashier-unlock mechanism as they are handled by the user themselves.
    // UPDATED: Relaxed to allow walk-ins and guest transactions without QR scanning as requested.
    // if (in_array($userRole, ['admin', 'admincashier', 'superadmin']) && ($payload['payment_status'] ?? 'paid') === 'paid') {
    //     if (!isset($payload['is_scanned']) || $payload['is_scanned'] !== true) {
    //         throw new Exception('Action Restricted: Transactions initiated by cashiers must be unlocked via a valid QR code scan.');
    //     }
    // }
    
    // 1.5 Strict Transaction State Enforcement: Prevent reprocessing processed/expired orders
    $originalTxnNumber = $payload['original_txn_number'] ?? null;
    if ($originalTxnNumber) {
        $checkStmt = $conn->prepare("SELECT payment_status, is_expired FROM cashier_transactions WHERE transaction_number = ? LIMIT 1");
        $checkStmt->bind_param('s', $originalTxnNumber);
        $checkStmt->execute();
        $checkStmt->bind_result($existingStatus, $existingExpired);
        if ($checkStmt->fetch()) {
            if ($existingStatus === 'paid') {
                throw new Exception('Transaction Already Completed: This order has already been finalized and paid.');
            }
            if ($existingStatus === 'voided' || (int)$existingExpired === 1) {
                throw new Exception('QR Code Expired: This order reference is no longer valid for processing.');
            }
        }
        $checkStmt->close();
    }

    if (!$payload || !isset($payload['items']) || !is_array($payload['items']) || count($payload['items']) === 0) {
        throw new Exception('No items in cart.');
    }

    // Strict Identification & Verification
    $userId = null;
    $studentFullName = null;
    $studentId = ($isGuest) ? $guestSchoolId : ($payload['student_id'] ?? $_SESSION['student_id'] ?? null);

    if ($isGuest) {
        $studentFullName = $guestName ?: 'Guest Customer';
    } else {
        if (empty($studentId) || $studentId === 'GUEST-CUSTOMER') {
            throw new Exception('Validation Error: Student identification is required for this transaction.');
        }

        $lookupStmt = $conn->prepare('SELECT id, first_name, last_name FROM users WHERE student_id = ? OR id = ? LIMIT 1');
        $lookupStmt->bind_param('ss', $studentId, $studentId);
        $lookupStmt->execute();
        $fName = '';
        $lName = '';
        $lookupStmt->bind_result($userId, $fName, $lName);
        if ($lookupStmt->fetch() && $userId) {
            $studentFullName = trim((string)$fName . ' ' . (string)$lName);
        } else {
            $lookupStmt->close();
            throw new Exception('Security Alert: The provided Student ID ("' . $studentId . '") is not registered in our system.');
        }
        $lookupStmt->close();
    }

    $discountPercent = isset($payload['discount_percent']) ? floatval($payload['discount_percent']) : 0.0;
    $paymentReceived = isset($payload['payment_received']) ? floatval($payload['payment_received']) : 0.0;
    $paymentStatus = isset($payload['payment_status']) && in_array($payload['payment_status'], ['paid', 'pending'], true) ? $payload['payment_status'] : 'paid';
    $receiptNumber = isset($payload['receipt_number']) ? trim((string)$payload['receipt_number']) : null;
    $receiptCategory = isset($payload['receipt_category']) ? trim((string)$payload['receipt_category']) : null;
    $paymentMethod = isset($payload['payment_method']) ? trim((string)$payload['payment_method']) : 'Cash';
    $checkNumber = isset($payload['check_number']) ? trim((string)$payload['check_number']) : null;

    if (strcasecmp($paymentMethod, 'Check') === 0 && empty($checkNumber)) {
        throw new Exception('Check number is required when payment method is Check.');
    }

    if ($checkNumber === '') {
        $checkNumber = null;
    }

    $allowedTypes = ['buy'];
    $itemTypes = [];
    $cartItems = [];

    $conn->begin_transaction();

    $stockColumn = 'stock_count';
    $stockCheck = $conn->query("SHOW COLUMNS FROM `products` LIKE 'stock_count'");
    if ($stockCheck === false) {
        throw new Exception("Failed to check stock_count column: " . $conn->error);
    }
    if (!$stockCheck || $stockCheck->num_rows === 0) {
        $stockColumn = 'stock';
    }

    // Detect price columns
    $buyPriceCol = 'buy_price';
    $priceCheck = $conn->query("SHOW COLUMNS FROM `products` LIKE 'buy_price'");
    if ($priceCheck === false) {
        throw new Exception("Failed to check buy_price column: " . $conn->error);
    }
    if (!$priceCheck || $priceCheck->num_rows === 0) { $buyPriceCol = 'price'; }

    $rentPriceCol = 'rent_price';
    $rentCheck = $conn->query("SHOW COLUMNS FROM `products` LIKE 'rent_price'");
    if ($rentCheck === false) {
        throw new Exception("Failed to check rent_price column: " . $conn->error);
    }
    if (!$rentCheck || $rentCheck->num_rows === 0) { $rentPriceCol = '0.00'; } // Fallback if no rent price exists

    $calculatedSubtotal = 0;
    foreach ($payload['items'] as $item) {
        $productId = intval($item['product_id'] ?? 0);
        $quantity = floatval($item['quantity'] ?? 0);
        $unitName = isset($item['unit_name']) ? trim($item['unit_name']) : 'pc/s';
        $type = 'buy';

        if ($productId <= 0 || $quantity <= 0 || !in_array($type, $allowedTypes, true)) {
            throw new Exception('Invalid cart item data.');
        }

        $itemTypes[] = $type;

        $stmt = $conn->prepare("SELECT product_id, product_name, product_category, `$stockColumn` AS available_stock, `$buyPriceCol` AS buy_price FROM products WHERE product_id = ? LIMIT 1");
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            throw new Exception('Product not found.');
        }

        if ($product['available_stock'] < $quantity) {
            throw new Exception('Insufficient stock for ' . $product['product_name']);
        }

        $unitPrice = floatval($product['buy_price']);
        $duration = 1;
        
        $itemTotal = round($unitPrice * $quantity, 2);
        $calculatedSubtotal += $itemTotal;

        $cartItems[] = [
            'product_id' => $productId,
            'product_name' => $product['product_name'],
            'display_name' => trim((string)($item['display_name'] ?? $item['displayName'] ?? $product['product_name'])),
            'displayName' => trim((string)($item['displayName'] ?? $item['display_name'] ?? $product['product_name'])),
            'fabric_part' => isset($item['fabric_part']) ? trim((string)$item['fabric_part']) : (isset($item['fabricPart']) ? trim((string)$item['fabricPart']) : null),
            'fabricPart' => isset($item['fabricPart']) ? trim((string)$item['fabricPart']) : (isset($item['fabric_part']) ? trim((string)$item['fabric_part']) : null),
            'uniform_upper_fabric' => isset($item['uniform_upper_fabric']) ? trim((string)$item['uniform_upper_fabric']) : null,
            'uniform_lower_fabric' => isset($item['uniform_lower_fabric']) ? trim((string)$item['uniform_lower_fabric']) : null,
            'type' => $type,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_name' => $unitName,
            'duration' => $duration,
            'duration_unit' => null,
            'return_date' => null,
            'total' => $itemTotal,
        ];

        $updateStmt = $conn->prepare("UPDATE products SET `$stockColumn` = `$stockColumn` - ? WHERE product_id = ?");
        $updateStmt->bind_param('di', $quantity, $productId);
        $updateStmt->execute();
        $updateStmt->close();
    }

    $subtotal = $calculatedSubtotal;
    $discountAmount = round($subtotal * ($discountPercent / 100), 2);
    $totalAmount = max(0, $subtotal - $discountAmount);
    $changeAmount = max(0, $paymentReceived - $totalAmount);

    if ($paymentStatus === 'paid' && $paymentReceived < ($totalAmount - 0.01)) {
        throw new Exception('Payment must cover total amount for paid status.');
    }

    $transactionType = 'buy';
    
    // If we are finalizing a scanned pending order, use the existing transaction number
    if ($originalTxnNumber) {
        $transactionNumber = $originalTxnNumber;
    } else {
        $transactionNumber = 'ORDER-' . time() . '-' . bin2hex(random_bytes(4));
    }
    $itemsJson = json_encode($cartItems, JSON_UNESCAPED_UNICODE);
    $cashierId = $adminId ?? 0;

    if ($receiptNumber !== null && $receiptNumber !== '') {
        $existingReceipt = $conn->prepare('SELECT 1 FROM cashier_transactions WHERE receipt_number = ? LIMIT 1');
        $existingReceipt->bind_param('s', $receiptNumber);
        $existingReceipt->execute();
        $existingReceipt->store_result();
        if ($existingReceipt->num_rows > 0) {
            throw new Exception('Duplicate receipt number detected.');
        }
        $existingReceipt->close();
    }

    // Use ON DUPLICATE KEY UPDATE to either update the scanned pending order to 'paid' 
    // or insert a new transaction if it's a manual cashier-initiated sale.
    $isExpiredFlag = ($paymentStatus === 'paid') ? 1 : 0; // Set is_expired to 1 if paid, 0 otherwise
    $upsertSql = "INSERT INTO cashier_transactions ( 
        transaction_number, receipt_number, receipt_category, user_id, student_name, guest_school_id, guest_email, cashier_id, 
        transaction_type, items, subtotal, discount_percent, discount_amount,
        total_amount, payment_received, change_amount, payment_status, payment_method, check_number, is_expired 
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        receipt_number = VALUES(receipt_number),
        receipt_category = VALUES(receipt_category),
        cashier_id = VALUES(cashier_id),
        student_name = VALUES(student_name),
        guest_school_id = VALUES(guest_school_id),
        guest_email = VALUES(guest_email),
        items = VALUES(items),
        subtotal = VALUES(subtotal),
        discount_percent = VALUES(discount_percent),
        discount_amount = VALUES(discount_amount),
        total_amount = VALUES(total_amount),
        payment_received = VALUES(payment_received),
        change_amount = VALUES(change_amount),
        payment_status = VALUES(payment_status), 
        payment_method = VALUES(payment_method),
        check_number = VALUES(check_number),
        is_expired = VALUES(is_expired)"; // Ensure is_expired is updated based on payment status

    $insertStmt = $conn->prepare($upsertSql);
    // Corrected type string: 20 parameters (s=string, i=int, d=double).
    $insertStmt->bind_param('sssisssissddddddsssi', $transactionNumber, $receiptNumber, $receiptCategory, $userId, $studentFullName, $guestSchoolId, $guestEmail, $cashierId, $transactionType, $itemsJson, $subtotal, $discountPercent, $discountAmount, $totalAmount, $paymentReceived, $changeAmount, $paymentStatus, $paymentMethod, $checkNumber, $isExpiredFlag);
    if (!$insertStmt->execute()) {
        throw new Exception('Could not save transaction: ' . $insertStmt->error);
    }
    $cashierTransactionId = $conn->insert_id; // Get the ID of the newly inserted cashier_transaction
    $insertStmt->close();

    // If we updated an existing record, clear out old transaction_items before adding new ones
    if ($originalTxnNumber && $cashierTransactionId) {
        $conn->query("DELETE FROM transaction_items WHERE cashier_transaction_id = $cashierTransactionId");
    }

    // Insert individual items into the transaction_items table
    $transactionItemInsert = $conn->prepare("INSERT INTO transaction_items (cashier_transaction_id, product_id, product_name, item_type, quantity, unit_price, duration, duration_unit, unit_name, total_item_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$transactionItemInsert) {
        throw new Exception('Failed to prepare transaction_items insert statement: ' . $conn->error);
    }
    foreach ($cartItems as $item) {
        $transactionItemInsert->bind_param(
            'iissddissd',
            $cashierTransactionId, $item['product_id'], $item['product_name'], $item['type'], $item['quantity'], $item['unit_price'], $item['duration'], $item['duration_unit'], $item['unit_name'], $item['total']
        );
        if (!$transactionItemInsert->execute()) {
            throw new Exception('Failed to record transaction item: ' . $transactionItemInsert->error);
        }
    }
    $transactionItemInsert->close();

    if ($userId) {
        $itemInsert = $conn->prepare('INSERT INTO transactions (user_id, product_id, type, quantity, total_amount) VALUES (?, ?, ?, ?, ?)'); // This is for user profile history

        foreach ($cartItems as $item) {
            $itemInsert->bind_param('iisdd', $userId, $item['product_id'], $item['type'], $item['quantity'], $item['total']);
            if (!$itemInsert->execute()) {
                throw new Exception('Failed to record item transaction: ' . $itemInsert->error);
            }
        }
        $itemInsert->close();
    }

    $conn->commit();

    // Generate QR code for the confirmation screen receipt
    $qrBase64 = null;
    try {
        $tempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp';
        if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);
        $tempQrPath = $tempDir . DIRECTORY_SEPARATOR . 'receipt_qr_' . uniqid() . '.png';
        
        // Generate scannable Order QR. We try local generation first for performance/privacy.
        if (function_exists('generateLocalQrCode') && extension_loaded('gd')) {
            if (generateLocalQrCode($transactionNumber, $tempQrPath, 'H', 10, 4)) {
                clearstatcache(true, $tempQrPath);
                $qrRawData = (file_exists($tempQrPath) && filesize($tempQrPath) > 0) ? @file_get_contents($tempQrPath) : false;
                if ($qrRawData !== false) {
                    $qrBase64 = 'data:image/png;base64,' . base64_encode($qrRawData);
                }
                @unlink($tempQrPath);
            }
        }
        
        // Fallback: If local generation failed or PHP GD is unavailable, fetch via Remote API
        if (!$qrBase64) {
            $remoteUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($transactionNumber);
            $remoteData = @file_get_contents($remoteUrl);
            if ($remoteData !== false) {
                $qrBase64 = 'data:image/png;base64,' . base64_encode($remoteData);
                error_log("Used remote QR fallback for transaction: $transactionNumber");
            } else {
                error_log("Failed both local and remote QR generation for transaction: $transactionNumber");
            }
        }
    } catch (Throwable $e) { 
        error_log("Receipt QR Generation Error: " . $e->getMessage()); 
    }

    $emailStatus = 'skipped';
    $userEmail = null;
    $targetEmail = null;
    $targetName = null;

    if ($userId && !$isGuest) {
        $emailStmt = $conn->prepare('SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1');
        if ($emailStmt) {
            $emailStmt->bind_param('i', $userId);
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();
            $userData = $emailResult ? $emailResult->fetch_assoc() : null;
            $emailStmt->close();
            if ($userData) {
                $targetEmail = $userData['email'];
                $targetName = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
            }
        }
    } elseif ($isGuest && !empty($guestEmail)) {
        $targetEmail = $guestEmail;
        $targetName = $guestName ?: 'Guest Customer';
    }

    if ($targetEmail && filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
        $userEmail = $targetEmail;
        $studentFullName = $targetName; // Use guest name or student name for email template

        if (true) { // Wrapped to maintain structure
                $attachments = [];
                $emailBody = "";
                $subject = "";
                $inlineQr = "";
                $tempQrPath = null;
                $tempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp';

                // Ensure temp directory is available for email attachments
                if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);

                // Prepare embedded image attachment (CID) for Gmail/Email clients
                // Only include the QR code for Order Confirmations (Pending), not for finalized receipts
                if ($paymentStatus !== 'paid' && $qrBase64 && strpos($qrBase64, 'data:image') === 0) {
                    $qrRawData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $qrBase64));
                    if ($qrRawData) {
                        $tempQrPath = $tempDir . DIRECTORY_SEPARATOR . 'email_qr_' . uniqid() . '.png';
                        if (file_put_contents($tempQrPath, $qrRawData)) {
                            $attachments[] = ['path' => $tempQrPath, 'name' => 'transaction_qr.png', 'cid' => 'order_qr_code'];
                            $inlineQr = "<div style='text-align:center;margin:25px 0;padding:20px;border:2px dashed #4f46e5;border-radius:16px;background:#f8fafc;'><div style='font-size:14px;font-weight:bold;color:#4f46e5;margin-bottom:15px;'>TRANSACTION QR CODE</div><img src='cid:order_qr_code' width='200' height='200' alt='QR Code' style='display:block;margin:0 auto;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;' /><p style='font-size:11px;color:#64748b;margin-top:10px;font-family:monospace;font-weight:bold;'>$transactionNumber</p></div>";
                        }
                    }
                }

                if ($paymentStatus === 'paid') {
                    $subject = 'Official Transaction Receipt - GCST Tracking System';
                    $itemsHtml = '';
                    foreach ($cartItems as $item) {
                        $itemsHtml .= "<tr>
                            <td style='padding: 12px 8px; border-bottom: 1px solid #f1f5f9; color: #334155;'>{$item['product_name']}</td>
                            <td style='padding: 12px 8px; border-bottom: 1px solid #f1f5f9; text-align: center; color: #334155;'>{$item['quantity']} {$item['unit_name']}</td>
                            <td style='padding: 12px 8px; border-bottom: 1px solid #f1f5f9; text-align: right; color: #334155;'>₱" . number_format($item['unit_price'], 2) . "</td>
                            <td style='padding: 12px 8px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: 600; color: #0f172a;'>₱" . number_format($item['total'], 2) . "</td>
                        </tr>";
                    }
                    $emailBody = "
                    <style>
    @media only screen and (max-width: 600px) {
        .receipt-shell { width: 100% !important; }
        
        .receipt-header { padding: 24px 16px !important; }
        .receipt-body { padding: 20px 14px !important; }
        
        /* Typography refinements */
        .receipt-meta td, .receipt-meta th { font-size: 12px !important; }
        .receipt-totals td { font-size: 14px !important; font-weight: 600; }
        
        /* Mobile Table Stacking Strategy */
        .receipt-items-table thead { display: none !important; }
        
        .receipt-items-table, 
        .receipt-items-table tbody, 
        .receipt-items-table tr, 
        .receipt-items-table td { 
            display: block !important; 
            width: 100% !important; 
            box-sizing: border-box; 
        }
        
        .receipt-items-table tr { 
            margin-bottom: 12px; 
            border-bottom: 1px solid #e2e8f0; 
            padding-bottom: 8px; 
        }
        
        .receipt-items-table td { 
            display: flex !important; 
            justify-content: space-between !important; 
            padding: 4px 0 !important; 
        }
        
        /* Adds the label before the data for context */
        .receipt-items-table td::before {
            content: attr(data-label);
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            font-size: 10px;
        }

        .receipt-footer { padding: 18px 12px !important; }
    }
</style>
                    <div class='receipt-shell' style='font-family: \"Outfit\", \"Segoe UI\", Helvetica, Arial, sans-serif; width: 100%; max-width: 600px; margin: 20px auto; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);'>
                        <div class='receipt-header' style='background-color: #4f46e5; padding: 24px 16px; text-align: center; color: #ffffff;'>
                            <h1 style='margin: 0; font-size: 14px; font-weight: 700; letter-spacing: -0.02em;'>GRANBY COLLEGES SCIENCE AND TECHNOLOGY Tracking System</h1>
                            <p style='margin: 5px 0 0; opacity: 0.9; font-size: 14px;'>Official Transaction Receipt</p>
                        </div>
                        <div class='receipt-body' style='padding: 24px 20px;'>
                            <p style='color: #1e293b; font-size: 16px; line-height: 1.5;'>Dear " . htmlspecialchars($studentFullName) . ",</p>
                            <p style='color: #475569; font-size: 15px; line-height: 1.5;'>Thank you for your transaction. Your payment has been successfully processed. Please find the details of your receipt below:</p>

                            <div style='background-color: #f8fafc; padding: 16px; border-radius: 12px; margin: 22px 0; border: 1px solid #e2e8f0;'>
                                <table class='receipt-meta' style='width: 100%; font-size: 14px; border-collapse: collapse;'>
                                    <tr><td style='color: #64748b; padding-bottom: 8px;'>Transaction Reference:</td><td style='text-align: right; color: #0f172a; font-weight: 600; padding-bottom: 8px;'>$transactionNumber</td></tr>
                                    <tr><td style='color: #64748b; padding-bottom: 8px;'>Processed By:</td><td style='text-align: right; color: #0f172a; padding-bottom: 8px;'>" . htmlspecialchars($cashierName) . "</td></tr>
                                    <tr><td style='color: #64748b;'>Date & Time:</td><td style='text-align: right; color: #0f172a;'>" . date('F d, Y h:i A') . "</td></tr>
                                </table>
                            </div>

                            <table class='receipt-items-table' style='width: 100%; border-collapse: collapse; margin-bottom: 22px;'>
                                <thead>
                                    <tr style='border-bottom: 2px solid #e2e8f0;'>
                                        <th style='padding: 12px 8px; text-align: left; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;'>Description</th>
                                        <th style='padding: 12px 8px; text-align: center; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;'>Qty</th>
                                        <th style='padding: 12px 8px; text-align: right; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;'>Price</th>
                                        <th style='padding: 12px 8px; text-align: right; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;'>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>$itemsHtml</tbody>
                            </table>

                            <div style='background-color: #ffffff; border-top: 1px solid #e2e8f0; padding-top: 12px;'>
                                <table class='receipt-totals' style='width: 100%; font-size: 14px;'>
                                    <tr><td style='padding: 4px 0; color: #64748b;'>Subtotal:</td><td style='text-align: right; color: #0f172a;'>₱" . number_format($subtotal, 2) . "</td></tr>
                                    <tr><td style='padding: 4px 0; color: #ef4444;'>Discount (" . number_format($discountPercent, 0) . "%):</td><td style='text-align: right; color: #ef4444;'>-₱" . number_format($discountAmount, 2) . "</td></tr>
                                    <tr><td style='padding: 15px 0 4px; color: #4f46e5; font-size: 18px; font-weight: 700;'>Total Amount Paid:</td><td style='text-align: right; color: #4f46e5; font-size: 18px; font-weight: 700;'>₱" . number_format($totalAmount, 2) . "</td></tr>
                                    <tr><td style='padding: 4px 0; color: #64748b;'>Cash Received:</td><td style='text-align: right; color: #0f172a;'>₱" . number_format($paymentReceived, 2) . "</td></tr>
                                    <tr><td style='padding: 4px 0; color: #64748b;'>Change:</td><td style='text-align: right; color: #0f172a;'>₱" . number_format($changeAmount, 2) . "</td></tr>
                                </table>
                            </div>

                            <div style='margin-top: 32px; padding: 18px 14px; background-color: #eff6ff; border-radius: 12px; text-align: center;'>
                                <p style='margin: 0; color: #1e40af; font-size: 14px; font-weight: 500;'>Thank you for choosing Granby College of Science and Technology!</p>
                            </div>
                        </div>
                        <div class='receipt-footer' style='background-color: #f8fafc; padding: 22px 18px; text-align: center; border-top: 1px solid #e2e8f0;'>
                            <p style='margin: 0; color: #64748b; font-size: 12px;'>&copy; " . date('Y') . " Granby Colleges of Science and Technology. All rights reserved.</p>
                            <p style='margin: 8px 0 0; color: #94a3b8; font-size: 11px; line-height: 1.4;'>This is an automated system notification regarding your recent transaction. Please do not reply directly to this email.</p>
                        </div>
                    </div>";
                } else {
                    $subject = 'Order Confirmation - GCST Tracking System';
                    $itemsHtml = '';
                    foreach ($cartItems as $item) {
                        $itemsHtml .= "<tr>
                            <td style='padding: 10px 0; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px;'>{$item['product_name']} x {$item['quantity']} {$item['unit_name']}</td>
                            <td style='padding: 10px 0; border-bottom: 1px solid #f1f5f9; text-align: right; color: #0f172a; font-weight: 500;'>₱" . number_format($item['total'], 2) . "</td>
                        </tr>";
                    }

                    $emailBody = "
                    <div style='font-family: \"Outfit\", \"Segoe UI\", Helvetica, Arial, sans-serif; max-width: 600px; margin: 20px auto; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);'>
                        <div style='background-color: #4f46e5; padding: 30px 20px; text-align: center; color: #ffffff;'>
                            <h1 style='margin: 0; font-size: 14px; font-weight: 700; letter-spacing: -0.02em;'>GRANBY COLLEGES OF SCIENCE AND TECHNOLOGY Tracking System</h1>
                            <p style='margin: 5px 0 0; opacity: 0.9; font-size: 14px;'>Order Confirmation & QR Code</p>
                        </div>
                        <div style='padding: 30px;'>
                            <p style='color: #1e293b; font-size: 16px; line-height: 1.5;'>Dear " . htmlspecialchars($studentFullName) . ",</p>
                            <p style='color: #475569; font-size: 15px; line-height: 1.5;'>Your order has been placed successfully. Please present the transaction details or the QR code below to the cashier to finalize your payment and collect your items.</p>
                            
                            <div style='background-color: #f8fafc; padding: 20px; border-radius: 12px; margin: 25px 0; border: 1px solid #e2e8f0;'>
                                <table style='width: 100%; font-size: 14px; border-collapse: collapse;'>
                                    <tr><td style='color: #64748b; padding-bottom: 8px;'>Order Number:</td><td style='text-align: right; color: #4f46e5; font-weight: 700; padding-bottom: 8px;'>$transactionNumber</td></tr>
                                    <tr><td style='color: #64748b; padding-bottom: 8px;'>Status:</td><td style='text-align: right; color: #f59e0b; font-weight: 600; padding-bottom: 8px;'>Awaiting Payment</td></tr>
                                    <tr><td style='color: #64748b; padding-bottom: 8px;'>Order Type:</td><td style='text-align: right; color: #0f172a; padding-bottom: 8px;'>" . ucfirst($transactionType) . "</td></tr>
                                    <tr><td style='color: #64748b;'>Created On:</td><td style='text-align: right; color: #0f172a;'>" . date('F d, Y h:i A') . "</td></tr>
                                </table>
                            </div>

                            <h3 style='font-size: 14px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px;'>Order Summary</h3>
                            <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                                <tbody>$itemsHtml</tbody>
                                <tfoot>
                                    <tr>
                                        <td style='padding: 15px 0; color: #0f172a; font-weight: 700; font-size: 16px;'>Total Payable:</td>
                                        <td style='padding: 15px 0; text-align: right; color: #4f46e5; font-weight: 700; font-size: 18px;'>₱" . number_format($totalAmount, 2) . "</td>
                                    </tr>
                                </tfoot>
                            </table>
                            
                            $inlineQr
                            
                            <div style='margin-top: 30px; text-align: center;'>
                                <p style='color: #64748b; font-size: 13px; font-style: italic;'>Note: This QR code is required for the cashier to process your order.</p>
                            </div>
                        </div>
                        <div style='background-color: #f8fafc; padding: 25px; text-align: center; border-top: 1px solid #e2e8f0;'>
                            <p style='margin: 0; color: #64748b; font-size: 12px;'>&copy; " . date('Y') . " Granby Colleges of Science and Technology. All rights reserved.</p>
                            <p style='margin: 8px 0 0; color: #94a3b8; font-size: 11px; line-height: 1.4;'>This is an automated system notification. We look forward to serving you.</p>
                        </div>
                    </div>";
                }

                $sendResult = sendEmailWithLog($conn, $userEmail, $subject, $emailBody, 'Transaction Details', $attachments);
                $emailStatus = $sendResult['status'] === 'sent' ? 'sent' : 'failed';

                if (isset($tempQrPath) && file_exists($tempQrPath)) {
                    unlink($tempQrPath);
                }
            }
        }

    // Log the transaction
    logAudit($conn, $adminId ? 'admincashier' : 'student', $adminId ?? $userId, 'save_cashier_transaction', 'Saved transaction ' . $transactionNumber . ' and email status: ' . $emailStatus);

    $responseData = [
        'success' => true,
        'message' => 'Transaction completed successfully.',
        'transaction_number' => $transactionNumber,
        'receipt_number' => $receiptNumber,
        'transaction_id' => $conn->insert_id,
        'email_status' => $emailStatus,
        'payment_status' => $paymentStatus,
        'receipt' => [
            'transaction_number' => $transactionNumber,
            'receipt_number' => $receiptNumber,
            'cashier_name' => $cashierName,
            'transaction_type' => $transactionType,
            'items' => $cartItems,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount_amount' => number_format($discountAmount, 2, '.', ''),
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'payment_received' => number_format($paymentReceived, 2, '.', ''),
            'change_amount' => number_format($changeAmount, 2, '.', ''),
            'payment_method' => $paymentMethod,
            'check_number' => $checkNumber,
            'receipt_category' => $receiptCategory,
        ],
    ];

    array_walk_recursive($responseData, function(&$item) {
        if (is_string($item)) $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
    });

    $json = json_encode($responseData);
    if ($json === false) throw new Exception('JSON Encoding Error: ' . json_last_error_msg());

    if (ob_get_length()) ob_clean();
    echo $json;
    exit;
} catch (Throwable $e) {
    if (isset($conn) && $conn->connect_errno === 0) $conn->rollback();
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>