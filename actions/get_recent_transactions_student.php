<?php
require_once __DIR__ . '/security.php';
secureSessionStart();

// Restricted to student/user roles only for security
requireAuth(['student', 'user']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

try {
    $session_student_id = $_SESSION['student_id'] ?? null;
    if (!$session_student_id) {
        throw new Exception('Student ID not found in session.');
    }

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $from = isset($_GET['from']) ? $conn->real_escape_string($_GET['from']) : '';
    $to = isset($_GET['to']) ? $conn->real_escape_string($_GET['to']) : '';
    $productFilter = isset($_GET['product_filter']) ? (string)$_GET['product_filter'] : 'all';
    if (!in_array($productFilter, ['all', 'products', 'paid_products'], true)) {
        throw new Exception('Invalid product filter.');
    }
    $offset = ($page - 1) * $limit;

    // Strictly filter where the student_id matches the active session
    $studentId = $conn->real_escape_string($session_student_id);
    $filterClausesCashier = ["(u.student_id = '$studentId' OR ct.guest_school_id = '$studentId')", 'tr.transaction_number IS NULL'];
    $filterClausesTuition = ["(tr.student_id = '$studentId' OR u2.student_id = '$studentId')"];

    if ($productFilter === 'products') {
        $filterClausesCashier[] = "ct.transaction_type = 'buy'";
        $filterClausesTuition[] = '1 = 0';
    } elseif ($productFilter === 'paid_products') {
        $filterClausesCashier[] = "ct.transaction_type = 'buy' AND ct.payment_status = 'paid'";
        $filterClausesTuition[] = '1 = 0';
    }

    if ($from) {
        $filterClausesCashier[] = "DATE(ct.created_at) >= '$from'";
        $filterClausesTuition[] = "DATE(tr.created_at) >= '$from'";
    }
    if ($to) {
        $filterClausesCashier[] = "DATE(ct.created_at) <= '$to'";
        $filterClausesTuition[] = "DATE(tr.created_at) <= '$to'";
    }
    if ($search) {
        $filterClausesCashier[] = "(ct.transaction_number LIKE '%$search%' OR ct.receipt_number LIKE '%$search%')";
        $filterClausesTuition[] = "(tr.transaction_number LIKE '%$search%' OR tr.receipt_number LIKE '%$search%')";
    }

    $cashierWhere = 'WHERE ' . implode(' AND ', $filterClausesCashier);
    $tuitionWhere = 'WHERE ' . implode(' AND ', $filterClausesTuition);

    $countSql = "SELECT COUNT(*) as total FROM (
        SELECT ct.id AS record_id
        FROM cashier_transactions ct
        LEFT JOIN users u ON ct.user_id = u.id
        LEFT JOIN tuition_receipts tr ON (tr.transaction_number = ct.transaction_number OR tr.receipt_number = ct.receipt_number)
        $cashierWhere
        UNION ALL
        SELECT tr.id AS record_id
        FROM tuition_receipts tr
        LEFT JOIN users u2 ON tr.user_id = u2.id
        $tuitionWhere
    ) AS combined_transactions";

    $countResult = $conn->query($countSql);
    if (!$countResult) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $totalRows = intval($countResult->fetch_assoc()['total'] ?? 0);
    $totalPages = ceil($totalRows / $limit);

    $sql = "SELECT * FROM (
        SELECT ct.id,
               ct.transaction_number,
               ct.receipt_number,
               ct.user_id,
               u.student_id,
               ct.cashier_id,
               ct.transaction_type,
               ct.receipt_category,
               CASE
                   WHEN TRIM(COALESCE(ct.receipt_category, '')) = '' OR TRIM(ct.receipt_category) = '0' THEN COALESCE(NULLIF(ct.transaction_type, ''), 'Payment Receipt')
                   WHEN LOWER(TRIM(ct.receipt_category)) IN ('tuition fee', 'tuition fee receipt') THEN 'Tuition Fee Receipt'
                   ELSE ct.receipt_category
               END AS receipt_type,
               ct.subtotal,
               ct.discount_percent,
               ct.discount_amount,
               ct.total_amount,
               ct.payment_received,
               ct.change_amount,
               ct.payment_status,
               ct.payment_method,
               ct.created_at,
               CONCAT(ac.first_name, ' ', ac.last_name) AS cashier_name,
               'cashier' AS source,
               NULL AS payment_type,
               NULL AS balance
        FROM cashier_transactions ct
        LEFT JOIN users u ON ct.user_id = u.id
        LEFT JOIN tuition_receipts tr ON (tr.transaction_number = ct.transaction_number OR tr.receipt_number = ct.receipt_number)
        LEFT JOIN admincashier_acc ac ON ct.cashier_id = ac.id
        $cashierWhere
        UNION ALL
        SELECT tr.id,
               tr.transaction_number,
               tr.receipt_number,
               tr.user_id,
               tr.student_id AS student_id,
               tr.cashier_id,
               'tuition' AS transaction_type,
               CASE
                   WHEN TRIM(COALESCE(tr.receipt_category, '')) = '' OR TRIM(tr.receipt_category) = '0' THEN 'Tuition Receipt'
                   WHEN LOWER(TRIM(tr.receipt_category)) IN ('tuition fee', 'tuition fee receipt') THEN 'Tuition Fee Receipt'
                   ELSE tr.receipt_category
               END AS receipt_category,
               CASE
                   WHEN TRIM(COALESCE(tr.receipt_category, '')) = '' OR TRIM(tr.receipt_category) = '0' THEN 'Tuition Receipt'
                   WHEN LOWER(TRIM(tr.receipt_category)) IN ('tuition fee', 'tuition fee receipt') THEN 'Tuition Fee Receipt'
                   ELSE tr.receipt_category
               END AS receipt_type,
               tr.amount_paid AS subtotal,
               0 AS discount_percent,
               0 AS discount_amount,
               tr.amount_paid AS total_amount,
               tr.amount_paid AS payment_received,
               0 AS change_amount,
               tr.payment_status,
               tr.payment_method,
               tr.created_at,
               CONCAT(aa.first_name, ' ', aa.last_name) AS cashier_name,
               CASE
                   WHEN LOWER(TRIM(COALESCE(tr.receipt_category, ''))) LIKE '%tuition%' THEN 'tuition'
                   WHEN TRIM(COALESCE(tr.receipt_category, '')) = '' THEN 'tuition'
                   WHEN LOWER(TRIM(COALESCE(tr.receipt_category, ''))) IN (
                       'payment receipt',
                       'medical receipt',
                       'insurance receipt',
                       'educational receipt',
                       'foundation day receipt'
                   ) THEN 'payment'
                   ELSE 'tuition'
               END AS source,
               tr.payment_type AS payment_type,
               tr.balance AS balance
        FROM tuition_receipts tr
        LEFT JOIN users u2 ON tr.user_id = u2.id
        LEFT JOIN admincashier_acc aa ON tr.cashier_id = aa.id
        $tuitionWhere
    ) AS combined_txns
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset";

    if (!$countResult) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $txns = [];
    while ($row = $result->fetch_assoc()) {
        $statusText = strtolower(trim((string)($row['payment_status'] ?? '')));
        $paymentTypeText = strtolower(trim((string)($row['payment_type'] ?? '')));
        $balanceValue = $row['balance'] ?? null;

        if (strtolower((string)($row['source'] ?? '')) === 'tuition' || strpos(strtolower((string)($row['receipt_type'] ?? '')), 'tuition') !== false) {
            if ($paymentTypeText === 'partial payment') {
                $row['payment_status_text'] = 'Partial Payment';
            } elseif ($balanceValue !== null && is_numeric($balanceValue) && floatval($balanceValue) <= 0.0) {
                $row['payment_status_text'] = 'Fully Paid';
            } elseif ($statusText === 'pending') {
                $row['payment_status_text'] = 'Pending';
            } elseif ($statusText === 'paid') {
                $row['payment_status_text'] = 'Paid';
            } else {
                $row['payment_status_text'] = ucfirst($statusText ?: 'Paid');
            }
        } else {
            $row['payment_status_text'] = $statusText === 'pending' ? 'Pending' : ($statusText === 'paid' ? 'Paid' : ucfirst($statusText ?: 'Paid'));
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
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'message' => $e->getMessage()]);
}