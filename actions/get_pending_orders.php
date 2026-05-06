<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';

$sql = "SELECT
            ct.id,
            ct.transaction_number,
            ct.receipt_number,
            ct.user_id,
            ct.student_name,
            ct.cashier_id,
            ct.transaction_type,
            ct.total_amount,
            ct.payment_status,
            ct.created_at,
            u.student_id AS student_unique_id,
            u.first_name AS student_first_name,
            u.last_name AS student_last_name,
            a.name AS cashier_name
        FROM
            cashier_transactions ct
        LEFT JOIN
            users u ON ct.user_id = u.id
        LEFT JOIN
            admins a ON ct.cashier_id = a.id
        WHERE
            ct.payment_status = 'pending'";

if (!empty($type_filter)) {
    $sql .= " AND ct.transaction_type = '$type_filter'";
}

if (!empty($search_query)) {
    $sql .= " AND (
                ct.transaction_number LIKE '%$search_query%' OR
                ct.student_name LIKE '%$search_query%' OR
                u.student_id LIKE '%$search_query%' OR
                u.first_name LIKE '%$search_query%' OR
                u.last_name LIKE '%$search_query%'
            )";
}

$sql .= " ORDER BY ct.created_at DESC";

$result = $conn->query($sql);

$pending_orders = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $student_display = '';
        if (!empty($row['student_first_name']) && !empty($row['student_last_name'])) {
            $student_display = trim($row['student_first_name'] . ' ' . $row['student_last_name']);
            if (!empty($row['student_unique_id'])) {
                $student_display .= ' (' . $row['student_unique_id'] . ')';
            }
        } elseif (!empty($row['student_name'])) {
            $student_display = $row['student_name'];
        } elseif (!empty($row['student_unique_id'])) {
            $student_display = $row['student_unique_id'];
        } else {
            $student_display = 'N/A';
        }

        $pending_orders[] = [
            'id' => $row['id'],
            'transaction_number' => $row['transaction_number'],
            'student_display' => $student_display,
            'transaction_type' => $row['transaction_type'],
            'total_amount' => $row['total_amount'],
            'created_at' => $row['created_at'],
            'cashier_name' => $row['cashier_name'] ?? 'System'
        ];
    }
}
echo json_encode($pending_orders);
$conn->close();
?>