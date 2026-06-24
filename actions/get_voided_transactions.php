<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_connect.php';

try {
    $stmt = $conn->prepare("
    SELECT ct.*, u.student_id, ct.student_name AS cashier_name 
    FROM cashier_transactions ct 
    LEFT JOIN users u ON ct.user_id = u.id 
    LEFT JOIN admincashier_acc aa ON ct.cashier_id = aa.id 
    WHERE ct.payment_status = 'voided' 
    ORDER BY ct.created_at DESC
    ");

    if (!$stmt) throw new Exception($conn->error);
    $stmt->execute();
    $result = $stmt->get_result();
    $voided = [];
    while ($row = $result->fetch_assoc()) {
        $voided[] = $row;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'voided' => $voided]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'voided' => []]);
}