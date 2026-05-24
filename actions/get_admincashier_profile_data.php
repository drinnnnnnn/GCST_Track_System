<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_connect.php';

$adminId = $_SESSION['admin_id'] ?? null;

if (!$adminId) {
    echo json_encode(['success' => false, 'message' => 'Admin ID not found in session.']);
    exit;
}

try {
    // Ensure admincashier_acc table has extended profile columns
    $conn->query("ALTER TABLE `admincashier_acc` 
        ADD COLUMN IF NOT EXISTS `username` VARCHAR(100) DEFAULT NULL AFTER `id`,
        ADD COLUMN IF NOT EXISTS `contact_number` VARCHAR(20) DEFAULT NULL AFTER `email`,
        ADD COLUMN IF NOT EXISTS `role` VARCHAR(50) DEFAULT 'admincashier' AFTER `password`,
        ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ADD COLUMN IF NOT EXISTS `last_login` DATETIME DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `status` ENUM('active','inactive') DEFAULT 'active'");

    // Fetch admin details
    $stmt = $conn->prepare("SELECT id AS admin_id, username, first_name, last_name, middle_name, email, contact_number, role, created_at, last_login, status FROM admincashier_acc WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $idVal = (int)$adminId;
    $stmt->bind_param('i', $idVal);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();

    if (!$admin) {
        throw new Exception('Admin profile not found.');
    }

    // Combine first_name and last_name for full_name
    $admin['full_name'] = trim($admin['first_name'] . ' ' . ($admin['middle_name'] ? $admin['middle_name'] . ' ' : '') . $admin['last_name']);
    unset($admin['first_name']);
    unset($admin['middle_name']);
    unset($admin['last_name']);

    // Ensure transaction tables exist to prevent 500 error on fresh systems
    $conn->query("CREATE TABLE IF NOT EXISTS `cashier_transactions` ( 
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
        UNIQUE KEY `uniq_transaction_number` (`transaction_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS `transaction_items` (
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
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Fetch transaction history for this admin (assuming admin_id can be user_id in transactions table)
    // This part might need adjustment based on how your system links admin actions to user transactions.
    // For now, it fetches transactions where the admin_id matches a user_id.
    $historyStmt = $conn->prepare("SELECT 
        t.created_at AS date, 
        GROUP_CONCAT(p.product_name SEPARATOR ', ') AS description, 
        t.total_amount AS amount, 
        t.transaction_type AS type, 
        t.payment_status AS status
        FROM cashier_transactions t
        LEFT JOIN transaction_items ti ON t.id = ti.cashier_transaction_id
        LEFT JOIN products p ON ti.product_id = p.product_id
        WHERE t.cashier_id = ? -- Assuming cashier_id in cashier_transactions links to admin_cashier.id
        GROUP BY t.id
        ORDER BY t.created_at DESC
        LIMIT 10"); // Limit to recent 10 transactions

    if (!$historyStmt) {
        throw new Exception("Failed to prepare history statement: " . $conn->error);
    }
    $historyStmt->bind_param('i', $adminId);
    $historyStmt->execute();
    $historyResult = $historyStmt->get_result();
    $transactionHistory = [];
    while ($row = $historyResult->fetch_assoc()) {
        $transactionHistory[] = $row;
    }
    $historyStmt->close();

    $admin['transaction_history'] = $transactionHistory;

    echo json_encode(['success' => true, 'admin' => $admin]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>