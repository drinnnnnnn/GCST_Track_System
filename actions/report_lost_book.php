<?php
header('Content-Type: application/json');
ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'admin', 'superadmin']);

try {
    require_once __DIR__ . '/../database/connection.php';
    $conn = Database::getConnection();
    require_once __DIR__ . '/audit_helpers.php';

    $adminId = $_SESSION['admin_id'] ?? 0;
    $payload = json_decode(file_get_contents('php://input'), true);
    
    $rentalId = filter_var($payload['rental_id'] ?? null, FILTER_VALIDATE_INT);
    $quantity = filter_var($payload['quantity'] ?? 0, FILTER_VALIDATE_INT);
    $penaltyAmount = filter_var($payload['penalty_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $notes = trim($payload['notes'] ?? '');
    $productId = $payload['product_id'] ?? null;
    $studentId = $payload['student_id'] ?? null;

    if (!$rentalId || $quantity <= 0 || empty($notes)) {
        throw new Exception('Missing required information or invalid quantity.');
    }

    $conn->begin_transaction();

    // Ensure schema supports penalty
    $penaltyCheck = $conn->query("SHOW COLUMNS FROM `lost_books` LIKE 'penalty_amount'");
    if (!$penaltyCheck || $penaltyCheck->num_rows === 0) {
        $conn->query("ALTER TABLE `lost_books` ADD COLUMN `penalty_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `quantity` ");
    }

    // 1. Verify and Update Active Rental
    $stmt = $conn->prepare("SELECT quantity, status FROM active_rentals WHERE rental_id = ? FOR UPDATE");
    $stmt->bind_param('i', $rentalId);
    $stmt->execute();
    $rental = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$rental) throw new Exception('Rental record not found.');
    if ($quantity > $rental['quantity']) throw new Exception('Reported lost quantity exceeds borrowed quantity.');

    $newQty = $rental['quantity'] - $quantity;
    if ($newQty == 0) {
        // If all items are accounted for (lost/returned), mark the rental as closed
        $updateStmt = $conn->prepare("UPDATE active_rentals SET quantity = 0, status = 'returned' WHERE rental_id = ?");
    } else {
        $updateStmt = $conn->prepare("UPDATE active_rentals SET quantity = ? WHERE rental_id = ?");
        $updateStmt->bind_param('ii', $newQty, $rentalId);
    }
    
    if ($newQty == 0) $updateStmt->bind_param('i', $rentalId);
    $updateStmt->execute();
    $updateStmt->close();

    // 2. Insert into lost_books
    $lostStmt = $conn->prepare("INSERT INTO lost_books (rental_id, product_id, student_id, quantity, penalty_amount, reported_by_cashier_id, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'lost')");
    $lostStmt->bind_param('iisdiis', $rentalId, $productId, $studentId, $quantity, $penaltyAmount, $adminId, $notes);
    
    if (!$lostStmt->execute()) {
        throw new Exception('Failed to log lost book: ' . $lostStmt->error);
    }
    $lostStmt->close();

    $conn->commit();

    logAudit($conn, 'admincashier', $adminId, 'report_lost_book', "Marked {$quantity} unit(s) of product {$productId} as LOST for student {$studentId}. Rental ID: {$rentalId}");

    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Book reported as lost successfully. The item has been removed from active inventory.']);
    exit;

} catch (Throwable $e) {
    if (isset($conn)) $conn->rollback();
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}