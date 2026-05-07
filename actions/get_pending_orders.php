<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin', 'student', 'user']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

try {
    $role = $_SESSION['role'];
    $session_student_id = $_SESSION['student_id'] ?? null;

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';

    $where = "WHERE ct.payment_status = 'pending'";

    // If student, only show their own pending orders
    if (in_array($role, ['student', 'user'])) {
        $where .= " AND u.student_id = '" . $conn->real_escape_string($session_student_id) . "'";
    }

    if ($search) {
        $where .= " AND (ct.transaction_number LIKE '%$search%' OR ct.student_name LIKE '%$search%' OR u.student_id LIKE '%$search%')";
    }
    if ($type) {
        $where .= " AND ct.transaction_type = '$type'";
    }

    // Count total rows for pagination
    $countResult = $conn->query("SELECT COUNT(*) as total FROM cashier_transactions ct LEFT JOIN users u ON ct.user_id = u.id $where");
    $totalRows = $countResult ? $countResult->fetch_assoc()['total'] : 0;
    $totalPages = ceil($totalRows / $limit);

    $sql = "SELECT ct.id, ct.transaction_number, ct.created_at, ct.transaction_type, ct.total_amount, ct.student_name, u.student_id, ct.items
            FROM cashier_transactions ct
            LEFT JOIN users u ON ct.user_id = u.id
            $where
            ORDER BY ct.created_at DESC
            LIMIT $limit OFFSET $offset";

    $result = $conn->query($sql);
    $orders = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['student_display'] = ($row['student_name'] ?? 'Guest') . ($row['student_id'] ? " ({$row['student_id']})" : "");
            $orders[] = $row;
        }
    }
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'total_pages' => $totalPages,
        'current_page' => $page
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}