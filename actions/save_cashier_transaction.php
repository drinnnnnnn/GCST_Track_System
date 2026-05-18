<?php
// Force JSON output even if errors occur
header('Content-Type: application/json');
ini_set('display_errors', '0'); // Prevent HTML error output
ob_start(); // Buffer any accidental output

require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'user', 'admin', 'admincashier', 'superadmin']);

try {
    // Ensure the Database class is available and get the connection
    require_once __DIR__ . '/../database/connection.php';
    $conn = Database::getConnection();
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

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
    $cashierName = $_SESSION['name'] ?? 'Admin Cashier';
    $payload = json_decode(file_get_contents('php://input'), true);
    
    // Security Enforcement: Restrict manual admin transactions without QR scan
    $userRole = $_SESSION['role'] ?? '';
    // Only enforce for PAID transactions finalize at the POS. PENDING orders (student self-service)
    // bypass the manual cashier-unlock mechanism as they are handled by the user themselves.
    if (in_array($userRole, ['admin', 'admincashier', 'superadmin']) && ($payload['payment_status'] ?? 'paid') === 'paid') {
        if (!isset($payload['is_scanned']) || $payload['is_scanned'] !== true) {
            throw new Exception('Action Restricted: Transactions initiated by cashiers must be unlocked via a valid QR code scan.');
        }
    }

    if (!$payload || !isset($payload['items']) || !is_array($payload['items']) || count($payload['items']) === 0) {
        throw new Exception('No items in cart.');
    }

    $studentId = $payload['student_id'] ?? $_SESSION['student_id'] ?? null;
    if (!$adminId && !$studentId) {
        throw new Exception('Authentication required.');
    }

    $userId = null;
    $studentFullName = null;
    if ($studentId) {
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
            throw new Exception('Unable to resolve student ID: ' . $studentId);
        }
        $lookupStmt->close();
    }

    $discountPercent = isset($payload['discount_percent']) ? floatval($payload['discount_percent']) : 0.0;
    $paymentReceived = isset($payload['payment_received']) ? floatval($payload['payment_received']) : 0.0;
    $paymentStatus = isset($payload['payment_status']) && in_array($payload['payment_status'], ['paid', 'pending'], true) ? $payload['payment_status'] : 'paid';
    $receiptNumber = isset($payload['receipt_number']) ? trim((string)$payload['receipt_number']) : null;

    $allowedTypes = ['buy', 'rent'];
    $itemTypes = [];
    $cartItems = [];

    $conn->begin_transaction();

    // Create the cashier_transactions table if it does not exist.
    $createTableSql = "CREATE TABLE IF NOT EXISTS `cashier_transactions` ( 
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `transaction_number` VARCHAR(50) NOT NULL,
        `receipt_number` VARCHAR(100) DEFAULT NULL,
        `user_id` INT(11) DEFAULT NULL,
        `student_name` VARCHAR(255) DEFAULT NULL,
        `cashier_id` INT(11) NOT NULL,
        `transaction_type` ENUM('buy','rent','mixed') NOT NULL,
        `items` TEXT NOT NULL,
        `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `payment_received` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `payment_status` ENUM('paid','pending') NOT NULL DEFAULT 'pending',
        `is_expired` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_transaction_number` (`transaction_number`),
        UNIQUE KEY `uniq_receipt_number` (`receipt_number`),
        KEY `idx_cashier_transactions_user_id` (`user_id`),
        KEY `idx_cashier_transactions_cashier_id` (`cashier_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($createTableSql)) {
        throw new Exception("Failed to create cashier_transactions table: " . $conn->error);
    }

    // Create the transactions table if it does not exist (used for profile history)
    $conn->query("CREATE TABLE IF NOT EXISTS `transactions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `product_id` INT(11) NOT NULL,
        `type` VARCHAR(20) NOT NULL,
        `quantity` INT(11) NOT NULL,
        `total_amount` DECIMAL(10,2) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create the active_rentals table if it does not exist.
    // This table is used for tracking individual items within a cashier_transaction
    $createTransactionItemsTableSql = "CREATE TABLE IF NOT EXISTS `transaction_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `cashier_transaction_id` INT(11) NOT NULL,
        `product_id` INT(11) NOT NULL,
        `product_name` VARCHAR(255) NOT NULL,
        `item_type` ENUM('buy', 'rent') NOT NULL,
        `quantity` INT(11) NOT NULL,
        `unit_price` DECIMAL(10,2) NOT NULL,
        `duration` INT(11) DEFAULT NULL,
        `duration_unit` VARCHAR(50) DEFAULT NULL,
        `total_item_amount` DECIMAL(10,2) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_cashier_transaction_id` (`cashier_transaction_id`),
        KEY `idx_product_id_ti` (`product_id`),
        CONSTRAINT `fk_cashier_transaction_id` FOREIGN KEY (`cashier_transaction_id`) REFERENCES `cashier_transactions` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $createRentalsTableSql = "CREATE TABLE IF NOT EXISTS `active_rentals` (
        `rental_id` INT(11) NOT NULL AUTO_INCREMENT,
        `transaction_number` VARCHAR(50) NOT NULL,
        `student_id` VARCHAR(50) NOT NULL,
        `product_id` INT(11) NOT NULL,
        `quantity` INT(11) NOT NULL DEFAULT 1,
        `rental_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `return_date` DATETIME NOT NULL,
        `overdue_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `rejection_reason` TEXT DEFAULT NULL,
        `status` ENUM('active','returned','overdue','pending_renewal') NOT NULL DEFAULT 'active',
        PRIMARY KEY (`rental_id`),
        KEY `idx_active_rentals_student` (`student_id`),
        KEY `idx_active_rentals_transaction` (`transaction_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($createTransactionItemsTableSql)) {
        throw new Exception("Failed to create transaction_items table: " . $conn->error);
    }

    // Create the lost_books table if it does not exist
    $createLostBooksTableSql = "CREATE TABLE IF NOT EXISTS `lost_books` (
        `lost_book_id` INT(11) NOT NULL AUTO_INCREMENT,
        `rental_id` INT(11) DEFAULT NULL,
        `product_id` INT(11) NOT NULL,
        `student_id` VARCHAR(50) NOT NULL,
        `quantity` INT(11) NOT NULL DEFAULT 1,
        `lost_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `status` ENUM('lost','found') NOT NULL DEFAULT 'lost',
        `reported_by_cashier_id` INT(11) NOT NULL,
        `found_by_cashier_id` INT(11) DEFAULT NULL,
        `found_date` TIMESTAMP NULL DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        PRIMARY KEY (`lost_book_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($createLostBooksTableSql)) {
        throw new Exception("Failed to create lost_books table: " . $conn->error);
    }
    if (!$conn->query($createRentalsTableSql)) {
        throw new Exception("Failed to create active_rentals table: " . $conn->error);
    }

    $typeColumnInfo = $conn->query("SHOW COLUMNS FROM `cashier_transactions` LIKE 'transaction_type'");
    if ($typeColumnInfo === false) {
        throw new Exception("Failed to check transaction_type column: " . $conn->error);
    }
    if ($typeColumnInfo->num_rows > 0) {
        $typeDef = $typeColumnInfo->fetch_assoc()['Type'];
        if (strpos($typeDef, "'mixed'") === false) {
            if (!$conn->query("ALTER TABLE `cashier_transactions` MODIFY COLUMN `transaction_type` ENUM('buy','rent','mixed') NOT NULL")) {
                throw new Exception("Failed to alter transaction_type column: " . $conn->error);
            }
        }
    }

    $txnCheckCt = $conn->query("SHOW COLUMNS FROM `cashier_transactions` LIKE 'transaction_number'");
    if (!$txnCheckCt || $txnCheckCt->num_rows === 0) {
        $conn->query("ALTER TABLE `cashier_transactions` ADD COLUMN `transaction_number` VARCHAR(50) NOT NULL AFTER `id` , ADD UNIQUE KEY `uniq_transaction_number` (`transaction_number`) ");
    }

    $expiredCheck = $conn->query("SHOW COLUMNS FROM `cashier_transactions` LIKE 'is_expired'");
    if (!$expiredCheck || $expiredCheck->num_rows === 0) {
        $conn->query("ALTER TABLE `cashier_transactions` ADD COLUMN `is_expired` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_status` ");
    }

    $txnCheckAr = $conn->query("SHOW COLUMNS FROM `active_rentals` LIKE 'transaction_number'");
    if (!$txnCheckAr || $txnCheckAr->num_rows === 0) {
        $conn->query("ALTER TABLE `active_rentals` ADD COLUMN `transaction_number` VARCHAR(50) NOT NULL AFTER `rental_id` , ADD INDEX `idx_active_rentals_transaction` (`transaction_number`) ");
    }

    $overdueCheck = $conn->query("SHOW COLUMNS FROM `active_rentals` LIKE 'overdue_charge'");
    if (!$overdueCheck || $overdueCheck->num_rows === 0) {
        $conn->query("ALTER TABLE `active_rentals` ADD COLUMN `overdue_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `return_date` ");
    }

    $userCheck = $conn->query("SHOW COLUMNS FROM `cashier_transactions` LIKE 'user_id'");
    if ($userCheck === false) {
        throw new Exception("Failed to check user_id column: " . $conn->error);
    }
    if (!$userCheck || $userCheck->num_rows === 0) {
        if (!$conn->query("ALTER TABLE `cashier_transactions` ADD COLUMN `user_id` INT(11) DEFAULT NULL AFTER `receipt_number` , ADD INDEX (`user_id`) ")) {
            throw new Exception("Failed to add user_id column: " . $conn->error);
        }
    }

    $nameCheck = $conn->query("SHOW COLUMNS FROM `cashier_transactions` LIKE 'student_name'");
    if ($nameCheck === false) {
        throw new Exception("Failed to check student_name column: " . $conn->error);
    }
    if (!$nameCheck || $nameCheck->num_rows === 0) {
        if (!$conn->query("ALTER TABLE `cashier_transactions` ADD COLUMN `student_name` VARCHAR(255) DEFAULT NULL AFTER `user_id` ")) {
            throw new Exception("Failed to add student_name column: " . $conn->error);
        }
    }

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
        $quantity = intval($item['quantity'] ?? 0);
        $type = strtolower(trim($item['type'] ?? $item['item_type'] ?? 'buy'));

        if ($type === 'rent' && !$studentId) {
            throw new Exception('Student ID is required for rental items.');
        }

        if ($productId <= 0 || $quantity <= 0 || !in_array($type, $allowedTypes, true)) {
            throw new Exception('Invalid cart item data.');
        }

        $itemTypes[] = $type;

        $stmt = $conn->prepare("SELECT product_id, product_name, product_category, `$stockColumn` AS available_stock, `$buyPriceCol` AS buy_price, $rentPriceCol AS rent_price FROM products WHERE product_id = ? LIMIT 1");
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            throw new Exception('Product not found.');
        }

        if ($type === 'buy' && strtolower($product['product_category'] ?? '') === 'books') {
            throw new Exception('Books can only be rented. Invalid item: ' . $product['product_name']);
        }

        if ($type === 'rent' && strtolower($product['product_category'] ?? '') !== 'books') {
            throw new Exception('Rental is only allowed for Books. Invalid item: ' . $product['product_name']);
        }

        if ($product['available_stock'] < $quantity) {
            throw new Exception('Insufficient stock for ' . $product['product_name']);
        }

        $unitPrice = $type === 'rent' ? floatval($product['rent_price']) : floatval($product['buy_price']);
        $duration = ($type === 'rent') ? intval($item['duration'] ?? 1) : 1;
        
        $itemTotal = round($unitPrice * $duration * $quantity, 2);
        $calculatedSubtotal += $itemTotal;

        $returnDate = null;
        if ($type === 'rent') {
            $durationUnit = $item['duration_unit'] ?? 'days';
            $unit = in_array($durationUnit, ['hours', 'days', 'weeks', 'months']) ? $durationUnit : 'days';
            $returnDate = date('Y-m-d H:i:s', strtotime("+$duration $unit"));
        }

        $cartItems[] = [
            'product_id' => $productId,
            'product_name' => $product['product_name'],
            'type' => $type,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'duration' => $duration,
            'duration_unit' => $item['duration_unit'] ?? ($type === 'rent' ? 'days' : null),
            'return_date' => $returnDate,
            'total' => $itemTotal,
        ];

        $updateStmt = $conn->prepare("UPDATE products SET `$stockColumn` = `$stockColumn` - ? WHERE product_id = ?");
        $updateStmt->bind_param('ii', $quantity, $productId);
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

    $transactionType = count(array_unique($itemTypes)) === 1 ? $itemTypes[0] : 'mixed';
    $transactionNumber = 'ORDER-' . time() . '-' . bin2hex(random_bytes(4));
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

    $insertStmt = $conn->prepare("INSERT INTO cashier_transactions (transaction_number, receipt_number, user_id, student_name, cashier_id, transaction_type, items, subtotal, discount_percent, discount_amount, total_amount, payment_received, change_amount, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    // Corrected type string: 14 parameters, s=string, i=int, d=double/float. Removed spaces.
    $insertStmt->bind_param('ssisissdddddds', $transactionNumber, $receiptNumber, $userId, $studentFullName, $cashierId, $transactionType, $itemsJson, $subtotal, $discountPercent, $discountAmount, $totalAmount, $paymentReceived, $changeAmount, $paymentStatus);
    if (!$insertStmt->execute()) {
        throw new Exception('Could not save transaction: ' . $insertStmt->error);
    }
    $cashierTransactionId = $conn->insert_id; // Get the ID of the newly inserted cashier_transaction
    $insertStmt->close();

    // Insert individual items into the transaction_items table
    $transactionItemInsert = $conn->prepare("INSERT INTO transaction_items (cashier_transaction_id, product_id, product_name, item_type, quantity, unit_price, duration, duration_unit, total_item_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$transactionItemInsert) {
        throw new Exception('Failed to prepare transaction_items insert statement: ' . $conn->error);
    }
    foreach ($cartItems as $item) {
        $transactionItemInsert->bind_param(
            'iisssidss',
            $cashierTransactionId, $item['product_id'], $item['product_name'], $item['type'], $item['quantity'], $item['unit_price'], $item['duration'], $item['duration_unit'], $item['total']
        );
        if (!$transactionItemInsert->execute()) {
            throw new Exception('Failed to record transaction item: ' . $transactionItemInsert->error);
        }
    }
    $transactionItemInsert->close();

    if ($userId) {
        $itemInsert = $conn->prepare('INSERT INTO transactions (user_id, product_id, type, quantity, total_amount) VALUES (?, ?, ?, ?, ?)'); // This is for user profile history
        $rentalInsert = $conn->prepare('INSERT INTO active_rentals (transaction_number, student_id, product_id, quantity, return_date, status) VALUES (?, ?, ?, ?, ?, "active")');

        foreach ($cartItems as $item) {
            $itemInsert->bind_param('iisid', $userId, $item['product_id'], $item['type'], $item['quantity'], $item['total']);
            if (!$itemInsert->execute()) {
                throw new Exception('Failed to record item transaction: ' . $itemInsert->error);
            }

            if ($item['type'] === 'rent') {
                $rentalInsert->bind_param('ssiis', $transactionNumber, $studentId, $item['product_id'], $item['quantity'], $item['return_date']);
                if (!$rentalInsert->execute()) {
                    throw new Exception('Failed to record active rental: ' . $rentalInsert->error);
                }
            }
        }
        $itemInsert->close();
        $rentalInsert->close();
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
    if ($userId) {
        $emailStmt = $conn->prepare('SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1');
        if ($emailStmt) {
            $emailStmt->bind_param('i', $userId);
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();
            $userData = $emailResult ? $emailResult->fetch_assoc() : null;
            $emailStmt->close();

            if ($userData && filter_var($userData['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                // Generate QR code image and save temporarily
                $userEmail = $userData['email'];
                $studentFullName = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
                
                $attachments = [];
                $emailBody = "";
                $subject = "";
                $inlineQr = "";
                $tempQrPath = null;
                $tempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp';

                // Ensure temp directory is available for email attachments
                if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);

                // Prepare embedded image attachment (CID) for Gmail/Email clients
                if ($qrBase64 && strpos($qrBase64, 'data:image') === 0) {
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
                    $subject = 'Official Receipt - GCST Tracking System';
                    $itemsHtml = '';
                    foreach ($cartItems as $item) {
                        $itemsHtml .= "<tr>
                            <td style='padding:8px; border-bottom:1px solid #eee; text-align:left;'>{$item['product_name']}</td>
                            <td style='padding:8px; border-bottom:1px solid #eee; text-align:center;'>{$item['quantity']}</td>
                            <td style='padding:8px; border-bottom:1px solid #eee; text-align:right;'>₱" . number_format($item['unit_price'], 2) . "</td>
                            <td style='padding:8px; border-bottom:1px solid #eee; text-align:right;'>₱" . number_format($item['total'], 2) . "</td>
                        </tr>";
                    }
                    $emailBody = "<div style='font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px; border-radius: 15px;'>
                        <h2 style='color: #4f46e5; text-align: center;'>Transaction Receipt</h2>
                        <p>Hi " . htmlspecialchars($studentFullName) . ",</p>
                        <p>Your payment has been processed. Here are your transaction details:</p>

                        $inlineQr

                        <div style='background: #f8fafc; padding: 15px; border-radius: 12px; margin: 20px 0; border: 1px solid #e5e7eb;'>
                            <strong>Transaction #:</strong> $transactionNumber<br><strong>Processed by:</strong> " . htmlspecialchars($cashierName) . "<br><strong>Date:</strong> " . date('M d, Y h:i A') . "
                        </div>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <thead>
                                <tr style='background: #f1f5f9;'>
                                    <th style='padding: 8px; text-align: left;'>Item</th>
                                    <th style='padding: 8px; text-align: center;'>Qty</th>
                                    <th style='padding: 8px; text-align: right;'>Unit Price</th>
                                    <th style='padding: 8px; text-align: right;'>Total</th>
                                </tr>
                            </thead>
                            <tbody>$itemsHtml</tbody>
                        </table>

                        <div style='text-align: right; margin-top: 20px; padding-top: 15px; border-top: 1px dashed #ccc;'>
                            <p style='margin: 5px 0;'><strong>Subtotal:</strong> ₱" . number_format($subtotal, 2) . "</p>
                            <p style='margin: 5px 0; color: #ef4444;'><strong>Discount (" . number_format($discountPercent, 0) . "%):</strong> -₱" . number_format($discountAmount, 2) . "</p>
                            <h3 style='margin: 15px 0 5px; color: #4f46e5; font-size: 1.5rem;'>Total Paid: ₱" . number_format($totalAmount, 2) . "</h3>
                            <p style='margin: 5px 0;'><strong>Cash Received:</strong> ₱" . number_format($paymentReceived, 2) . "</p>
                            <p style='margin: 5px 0;'><strong>Change:</strong> ₱" . number_format($changeAmount, 2) . "</p>
                        </div>
                        <p style='text-align: center; color: #64748b; font-size: 0.8rem; margin-top: 30px;'>Thank you for choosing GCST Tracking System. Please keep this receipt for your records.</p>
                    </div>";
                } else {
                    $subject = 'Your GCST Order QR Code';
                    $itemsHtml = '';
                    foreach ($cartItems as $item) {
                        $itemsHtml .= "<p style='margin: 5px 0;'>- {$item['product_name']} x {$item['quantity']} (₱" . number_format($item['unit_price'], 2) . " each)</p>";
                    }

                    $emailBody = "<div style='font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px; border-radius: 15px; background-color: #fdfdfd;'>
                        <h2 style='color: #4f46e5; text-align: center; margin-bottom: 20px;'>Order Confirmation</h2>
                        <p style='margin-bottom: 10px;'>Hi " . htmlspecialchars($studentFullName) . ",</p>
                        <p style='margin-bottom: 20px;'>Your order has been placed successfully and is awaiting payment/pickup. Please present the QR code below to the cashier to finalize your transaction.</p>
                        
                        <div style='background: #f8fafc; padding: 15px; border-radius: 12px; margin: 20px 0; border: 1px solid #e5e7eb;'>
                            <p style='margin: 5px 0;'><strong>Transaction #:</strong> <span style='color: #4f46e5; font-weight: bold;'>$transactionNumber</span></p>
                            <p style='margin: 5px 0;'><strong>Processed by:</strong> " . htmlspecialchars($cashierName) . "</p>
                            <p style='margin: 5px 0;'><strong>Transaction Type:</strong> " . ucfirst($transactionType) . "</p>
                            <p style='margin: 5px 0;'><strong>Date:</strong> " . date('M d, Y h:i A') . "</p>
                        </div>

                        <p style='margin-bottom: 10px;'><strong>Items in your order:</strong></p>
                        <div style='margin-left: 15px; margin-bottom: 20px;'>$itemsHtml</div>

                        <p style='text-align: right; margin-top: 10px;'><strong>Total Payable: ₱" . number_format($totalAmount, 2) . "</strong></p>
                        
                        $inlineQr
                        
                        <p style='text-align: center; color: #64748b; font-size: 0.8rem; margin-top: 30px;'>Thank you for your order! We look forward to serving you.</p>
                    </div>";
                }

                $sendResult = sendEmailWithLog($conn, $userEmail, $subject, $emailBody, 'Transaction Details', $attachments);
                $emailStatus = $sendResult['status'] === 'sent' ? 'sent' : 'failed';

                if (isset($tempQrPath) && file_exists($tempQrPath)) {
                    unlink($tempQrPath);
                }
            }
        }
    }

    // Log the transaction
    logAudit($conn, $adminId ? 'admincashier' : 'student', $adminId ?? $userId, 'save_cashier_transaction', 'Saved transaction ' . $transactionNumber . ' and email status: ' . $emailStatus);

    echo json_encode([
        'success' => true,
        'message' => 'Transaction completed successfully.',
        'transaction_number' => $transactionNumber,
        'receipt_number' => $receiptNumber,
        'transaction_id' => $conn->insert_id,
        'email_status' => $emailStatus,
        'payment_status' => $paymentStatus,
        'receipt' => [
            'transaction_number' => $transactionNumber,
            'qr_code' => $qrBase64,
            'receipt_number' => $receiptNumber,
            'cashier_name' => $cashierName,
            'transaction_type' => $transactionType,
            'items' => $cartItems,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount_amount' => number_format($discountAmount, 2, '.', ''),
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'payment_received' => number_format($paymentReceived, 2, '.', ''),
            'change_amount' => number_format($changeAmount, 2, '.', ''),
            'payment_status' => ucfirst($paymentStatus),
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
    exit;
} catch (Throwable $e) {
    if (isset($conn) && $conn->connect_errno === 0) $conn->rollback();
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>