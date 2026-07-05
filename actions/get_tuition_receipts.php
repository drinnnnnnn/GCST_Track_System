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
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $from = isset($_GET['from']) ? $conn->real_escape_string($_GET['from']) : '';
    $to = isset($_GET['to']) ? $conn->real_escape_string($_GET['to']) : '';
    $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : 'all';
    $receiptCategory = isset($_GET['receipt_category']) ? trim($_GET['receipt_category']) : '';
    $studentId = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
    $offset = ($page - 1) * $limit;

    $where = "WHERE 1=1";

    if ($receiptCategory !== '') {
        $receiptCategoryEscaped = $conn->real_escape_string($receiptCategory);
        $where .= " AND tr.receipt_category = '{$receiptCategoryEscaped}'";
    }

    if ($studentId !== '') {
        $studentIdEscaped = $conn->real_escape_string($studentId);
        $where .= " AND (u.student_id = '{$studentIdEscaped}' OR tr.student_id = '{$studentIdEscaped}')";
    }

    if (in_array($role, ['student', 'user'], true)) {
        $where .= " AND u.student_id = '" . $conn->real_escape_string($session_student_id) . "'";
    }

    if ($search) {
        $where .= " AND (tr.id LIKE '%$search%'"
                    . " OR tr.transaction_number LIKE '%$search%'"
                    . " OR tr.receipt_number LIKE '%$search%'"
                    . " OR u.student_id LIKE '%$search%'"
                    . " OR tr.student_name LIKE '%$search%'"
                    . " OR tr.student_id LIKE '%$search%'"
                    . " OR tr.receipt_category LIKE '%$search%'"
                    . " OR tr.payment_status LIKE '%$search%'"
                    . " OR tr.created_at LIKE '%$search%'"
                    . " OR ac.first_name LIKE '%$search%'"
                    . " OR ac.last_name LIKE '%$search%')";
    }

    if ($status === 'paid') {
        $where .= " AND tr.payment_status = 'paid'";
    } elseif ($status === 'pending') {
        $where .= " AND tr.payment_status = 'pending'";
    }

    if ($from) { $where .= " AND DATE(tr.created_at) >= '$from'"; }
    if ($to) { $where .= " AND DATE(tr.created_at) <= '$to'"; }

    $countResult = $conn->query("SELECT COUNT(*) as total
                                FROM tuition_receipts tr
                                LEFT JOIN users u ON tr.user_id = u.id
                                LEFT JOIN admincashier_acc ac ON tr.cashier_id = ac.id $where");
    $totalRows = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($totalRows / $limit);

        $sql = "SELECT tr.*, u.student_id, u.first_name AS user_first_name, u.last_name AS user_last_name,
                 CONCAT(ac.first_name, ' ', ac.last_name) as cashier_name
             FROM tuition_receipts tr
             LEFT JOIN users u ON tr.user_id = u.id
             LEFT JOIN admincashier_acc ac ON tr.cashier_id = ac.id
             $where
             ORDER BY tr.created_at DESC
             LIMIT $limit OFFSET $offset";

    $result = $conn->query($sql);
    $txns = [];
    while ($row = $result->fetch_assoc()) {
        // If student_name is missing in the receipt row, fallback to linked user name
        if (empty($row['student_name'])) {
            $first = trim($row['user_first_name'] ?? '');
            $last = trim($row['user_last_name'] ?? '');
            $full = trim(($first . ' ' . $last));
            if ($full !== '') {
                $row['student_name'] = $full;
            }
        }
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
