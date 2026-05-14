<?php
// Force JSON output even if errors occur
header('Content-Type: application/json');
ini_set('display_errors', '0'); // Prevent HTML error output
ob_start(); // Buffer any accidental output

require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'admin', 'admincashier', 'superadmin']);

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
    $payload = json_decode(file_get_contents('php://input'), true);
    
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
            $studentFullName = trim($fName . ' ' . $lName);
        } else {
            $lookupStmt->close();
            throw new Exception('Unable to resolve student ID: ' . $studentId);
        }
        $lookupStmt->close();
    }

    $subtotal = isset($payload['subtotal']) ? floatval($payload['subtotal']) : 0.0;
    $discountPercent = isset($payload['discount_percent']) ? floatval($payload['discount_percent']) : 0.0;
    $discountAmount = isset($payload['discount_amount']) ? floatval($payload['discount_amount']) : 0.0;
    $totalAmount = isset($payload['total_amount']) ? floatval($payload['total_amount']) : 0.0;
    $paymentReceived = isset($payload['payment_received']) ? floatval($payload['payment_received']) : 0.0;
    $paymentStatus = isset($payload['payment_status']) && in_array($payload['payment_status'], ['paid', 'pending'], true) ? $payload['payment_status'] : 'paid';
    $changeAmount = isset($payload['change_amount']) ? floatval($payload['change_amount']) : 0.0;
    $receiptNumber = isset($payload['receipt_number']) ? trim((string)$payload['receipt_number']) : null;

    if ($totalAmount < 0 || $subtotal < 0) throw new Exception('Invalid amount values.');
    if ($paymentStatus === 'paid' && $paymentReceived < $totalAmount) throw new Exception('Payment must cover total amount for paid status.');

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
        `rejection_reason` TEXT DEFAULT NULL,
        `status` ENUM('active','returned','overdue','pending_renewal') NOT NULL DEFAULT 'active',
        PRIMARY KEY (`rental_id`),
        KEY `idx_active_rentals_student` (`student_id`),
        KEY `idx_active_rentals_transaction` (`transaction_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($createTransactionItemsTableSql)) {
        throw new Exception("Failed to create transaction_items table: " . $conn->error);
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
        if ($unitPrice <= 0) {
            throw new Exception('Invalid selected price for ' . $product['product_name']);
        }

        $duration = ($type === 'rent') ? intval($item['duration'] ?? 1) : 1;
        $durationUnit = $item['duration_unit'] ?? ($type === 'rent' ? 'days' : null);

        $returnDate = null;
        if ($type === 'rent') {
            $unit = in_array($durationUnit, ['hours', 'days', 'weeks', 'months']) ? $durationUnit : 'days';
            $returnDate = date('Y-m-d H:i:s', strtotime("+$duration $unit"));
        }

        $itemTotal = round($unitPrice * $duration * $quantity, 2);

        $cartItems[] = [
            'product_id' => $productId,
            'product_name' => $product['product_name'],
            'type' => $type,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'duration' => $duration,
            'duration_unit' => $durationUnit,
            'return_date' => $returnDate,
            'total' => $itemTotal,
        ];

        $updateStmt = $conn->prepare("UPDATE products SET `$stockColumn` = `$stockColumn` - ? WHERE product_id = ?");
        $updateStmt->bind_param('ii', $quantity, $productId);
        $updateStmt->execute();
        $updateStmt->close();
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

                if ($paymentStatus === 'paid') {
                    $subject = 'Official Receipt - GCST Tracking System';
                    $itemsHtml = '';
                    foreach ($cartItems as $item) {
                        $itemsHtml .= "<tr><td style='padding:8px; border-bottom:1px solid #eee;'>{$item['product_name']} x {$item['quantity']}</td><td style='padding:8px; border-bottom:1px solid #eee; text-align:right;'>₱" . number_format($item['total'], 2) . "</td></tr>";
                    }
                    $emailBody = "<div style='font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px; border-radius: 15px;'>
                        <h2 style='color: #4f46e5; text-align: center;'>Transaction Receipt</h2>
                        <p>Hi " . htmlspecialchars($studentFullName) . ",</p>
                        <p>Your payment has been processed. Here are your transaction details:</p>
                        <div style='background: #f8fafc; padding: 15px; border-radius: 12px; margin: 20px 0;'>
                            <strong>Transaction #:</strong> $transactionNumber<br><strong>Date:</strong> " . date('M d, Y h:i A') . "
                        </div>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <thead><tr style='background: #f1f5f9;'><th style='padding: 8px; text-align: left;'>Item</th><th style='padding: 8px; text-align: right;'>Amount</th></tr></thead>
                            <tbody>$itemsHtml</tbody>
                        </table>
                        <div style='border-top: 2px solid #4f46e5; padding-top: 15px; text-align: right;'>
                            <p><strong>Total Paid: ₱" . number_format($totalAmount, 2) . "</strong></p>
                        </div>
                        <p style='text-align: center; color: #64748b; font-size: 0.8rem; margin-top: 20px;'>Thank you for using GCST Tracking System.</p>
                    </div>";
                } else {
                    $subject = 'Your GCST Order QR Code';
                    $tempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp';
                    if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);
                    $tempQrPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('qr_') . '.png';
                    $inlineQr = '';
                    try {
                        // Create a detailed content string for the QR code
                        $qrContent = "Order ID: $transactionNumber\nItems:\n";
                        foreach ($cartItems as $item) {
                            $qrContent .= "- " . $item['product_name'] . " (Qty: " . $item['quantity'] . ")\n";
                        }

                        if (function_exists('generateLocalQrCode') && generateLocalQrCode($qrContent, $tempQrPath, 'H', 10, 4)) {
                            $attachments[] = ['path' => $tempQrPath, 'name' => 'order_qr_code.png', 'cid' => 'order_qr_code'];
                            $inlineQr = "<div style='text-align:center;margin:30px 0;padding:20px;border:2px dashed #2563eb;border-radius:16px;background:#f8fafc;'><div style='font-size:18px;font-weight:bold;color:#2563eb;margin-bottom:15px;'>PRESENT TO CASHIER</div><img src='cid:order_qr_code' style='max-width:300px;height:auto;' /></div>";
                        }
                    } catch (Throwable $e) { error_log($e->getMessage()); }

                    $emailBody = "<p>Hi " . htmlspecialchars($studentFullName) . ",</p><p>Your order has been placed. Present this QR code to the cashier.</p>$inlineQr<p>Order Total: ₱" . number_format($totalAmount, 2) . "</p><p>Thank you for your ordering!</p>";
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
            'receipt_number' => $receiptNumber,
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