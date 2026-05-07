<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

try {
    $rental_id = isset($_GET['rental_id']) ? intval($_GET['rental_id']) : 0;

    $sql = "SELECT ar.rental_id, ar.return_date, u.first_name, u.last_name, u.student_id, p.product_name, ar.status 
            FROM active_rentals ar 
            JOIN users u ON ar.student_id = u.student_id 
            JOIN products p ON ar.product_id = p.product_id 
            WHERE ar.rental_id = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        echo json_encode([
            'success' => true,
            'student' => $res['first_name'] . ' ' . $res['last_name'] . ' (' . $res['student_id'] . ')',
            'product' => $res['product_name'],
            'new_date' => $res['return_date'],
            'status' => $res['status']
        ]);
    } else {
        throw new Exception('Renewal record not found.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
$conn->close();