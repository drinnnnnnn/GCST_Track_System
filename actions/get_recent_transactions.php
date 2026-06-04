<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
// Allow students and users to access their own transaction history
requireAuth(['admincashier', 'superadmin', 'student', 'user']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

try {
    $role = $_SESSION['role'];
    $session_student_id = $_SESSION['student_id'] ?? null;
    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $from = isset($_GET['from']) ? $conn->real_escape_string($_GET['from']) : '';
    $to = isset($_GET['to']) ? $conn->real_escape_string($_GET['to']) : '';
    $offset = ($page - 1) * $limit;

    $where = "WHERE 1=1";
    
    // Security: Students can only see their own transactions
    if (in_array($role, ['student', 'user'])) {
        $where .= " AND u.student_id = '" . $conn->real_escape_string($session_student_id) . "'";
    }

    if ($search) {
        $where .= " AND (ct.id LIKE '%$search%'
                    OR ct.transaction_number LIKE '%$search%' 
                    OR u.student_id LIKE '%$search%' 
                    OR ct.student_name LIKE '%$search%' 
                    OR ct.guest_school_id LIKE '%$search%' 
                    OR ct.transaction_type LIKE '%$search%' 
                    OR ct.items LIKE '%$search%' 
                    OR ct.payment_status LIKE '%$search%'
                    OR ct.created_at LIKE '%$search%'
                    OR ac.first_name LIKE '%$search%' 
                    OR ac.last_name LIKE '%$search%')";
    }
    if ($from) { $where .= " AND DATE(ct.created_at) >= '$from'"; }
    if ($to) { $where .= " AND DATE(ct.created_at) <= '$to'"; }

    // Get total count for pagination
    $countResult = $conn->query("SELECT COUNT(*) as total 
                                FROM cashier_transactions ct 
                                LEFT JOIN users u ON ct.user_id = u.id 
                                LEFT JOIN admincashier_acc ac ON ct.cashier_id = ac.id $where");
    $totalRows = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($totalRows / $limit);

    // Fetch transactions
    $sql = "SELECT ct.*, u.student_id,
                   CONCAT(ac.first_name, ' ', ac.last_name) as cashier_name
            FROM cashier_transactions ct
            LEFT JOIN users u ON ct.user_id = u.id
            LEFT JOIN admincashier_acc ac ON ct.cashier_id = ac.id
            $where
            ORDER BY ct.created_at DESC
            LIMIT $limit OFFSET $offset";

    $result = $conn->query($sql);
    $txns = [];
    while ($row = $result->fetch_assoc()) {
        $txns[] = $row;
    }

    echo json_encode([
        'success' => true,
        'transactions' => $txns,
        'total_pages' => $totalPages,
        'current_page' => $page
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}