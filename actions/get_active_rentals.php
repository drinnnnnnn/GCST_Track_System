<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin', 'student', 'user']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

try {
    $role = $_SESSION['role'];
    $session_student_id = $_SESSION['student_id'] ?? null;

    $sql = "SELECT ar.rental_id, ar.student_id, ar.product_id, ar.quantity, ar.return_date, ar.status, ar.rejection_reason,
                   u.first_name, u.last_name, p.product_name
            FROM active_rentals ar
            LEFT JOIN users u ON ar.student_id = u.student_id
            LEFT JOIN products p ON ar.product_id = p.product_id
            WHERE ar.status != 'returned'";

    if (in_array($role, ['student', 'user'])) {
        $sql .= " AND ar.student_id = '" . $conn->real_escape_string($session_student_id) . "'";
    }
    $sql .= " ORDER BY ar.return_date ASC";
            
    $result = $conn->query($sql);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}