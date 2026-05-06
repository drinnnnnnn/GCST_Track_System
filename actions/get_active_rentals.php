<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

// Ensure active rentals are synced to overdue status before fetching
$conn->query("UPDATE active_rentals SET status = 'overdue' WHERE status = 'active' AND return_date < NOW()");

$sql = "SELECT r.*, p.product_name, u.first_name, u.last_name 
        FROM active_rentals r
        JOIN products p ON r.product_id = p.product_id
        JOIN users u ON r.student_id = u.student_id
        WHERE r.status = 'active'
        ORDER BY r.return_date ASC";
$result = $conn->query($sql);

$active = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $active[] = $row;
    }
}
echo json_encode($active);
$conn->close();
?>