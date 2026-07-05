<?php
// Prevent HTML error output from corrupting the JSON response
ini_set('display_errors', '0');
if (ob_get_level() == 0) ob_start();

// Set system timezone to Manila
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin', 'student', 'user']); // Allow relevant roles
header('Content-Type: application/json');

// Ensure the Database class is available and get the connection
require_once __DIR__ . '/../database/connection.php';
$conn = Database::getConnection();
if (!$conn || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$lookupValue = $_GET['id'] ?? $_GET['transaction_number'] ?? $_GET['receipt_number'] ?? $_GET['transactionId'] ?? null;
$source = strtolower($_GET['source'] ?? $_GET['type'] ?? '');

if (!$lookupValue) {
    echo json_encode(['success' => false, 'message' => 'Transaction identifier is required.']);
    exit;
}

function resolveUserCourse(mysqli $conn, $userId, $guestSchoolId) {
    $userCourse = 'N/A';
    if (!empty($userId)) {
        $uid = intval($userId);
        $uStmt = $conn->prepare('SELECT course, year_section FROM users WHERE id = ? LIMIT 1');
        if ($uStmt) {
            $uStmt->bind_param('i', $uid);
            $uStmt->execute();
            $uRow = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();
            if ($uRow) {
                if (!empty($uRow['course'])) $userCourse = $uRow['course'];
                elseif (!empty($uRow['year_section'])) $userCourse = $uRow['year_section'];
            }
        }
    }

    if ($userCourse === 'N/A' && !empty($guestSchoolId)) {
        $gStmt = $conn->prepare('SELECT course, year_section FROM users WHERE student_id = ? LIMIT 1');
        if ($gStmt) {
            $gStmt->bind_param('s', $guestSchoolId);
            $gStmt->execute();
            $gRow = $gStmt->get_result()->fetch_assoc();
            $gStmt->close();
            if ($gRow) {
                if (!empty($gRow['course'])) $userCourse = $gRow['course'];
                elseif (!empty($gRow['year_section'])) $userCourse = $gRow['year_section'];
            }
        }
    }

    return $userCourse;
}

try {
    $transaction = null;
    $resolvedSource = '';

    $shouldSearchTuition = ($source === 'tuition');
    $shouldSearchReceipt = ($source === 'receipt' || $source === '');

    if ($shouldSearchTuition || $shouldSearchReceipt) {
        $receiptQuery = "
            SELECT
                tr.id,
                tr.transaction_number,
                tr.receipt_number,
                tr.user_id,
                tr.student_id,
                tr.student_name,
                tr.student_email,
                tr.cashier_id,
                tr.receipt_category,
                tr.amount_paid,
                tr.total_payment,
                tr.balance,
                tr.or_number,
                tr.check_number,
                tr.payment_method,
                tr.payment_type,
                tr.remarks,
                tr.note,
                tr.authorized_rep,
                tr.payment_status,
                tr.created_at,
                CONCAT(aa.first_name, ' ', aa.middle_name, ' ', aa.last_name) AS cashier_name,
                COALESCE(NULLIF(us.course, ''), NULLIF(us.year_section, ''), 'N/A') AS user_course
            FROM tuition_receipts tr
            LEFT JOIN admincashier_acc aa ON tr.cashier_id = aa.id
            LEFT JOIN users us ON tr.user_id = us.id
            WHERE tr.transaction_number = ? OR tr.receipt_number = ? OR tr.id = ?
            LIMIT 1";
        $stmt = $conn->prepare($receiptQuery);
        if ($stmt) {
            $stmt->bind_param('sss', $lookupValue, $lookupValue, $lookupValue);
            $stmt->execute();
            $transaction = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($transaction) {
                $resolvedSource = 'tuition';
            }
        }
    }

    if (!$transaction) {
        $query = "
            SELECT
                ct.id,
                ct.transaction_number,
                ct.receipt_number,
                ct.user_id,
                ct.student_name,
                ct.guest_school_id,
                ct.cashier_id,
                ct.transaction_type,
                ct.receipt_category,
                ct.subtotal,
                ct.discount_percent,
                ct.discount_amount,
                ct.total_amount,
                ct.payment_received,
                ct.change_amount,
                ct.payment_status,
                ct.created_at,
                ct.items AS raw_items,
                CONCAT(aa.first_name, ' ', aa.middle_name, ' ', aa.last_name) AS cashier_name,
                COALESCE(NULLIF(us.course, ''), NULLIF(us.year_section, ''), 'N/A') AS user_course,
                us.student_id AS student_id
            FROM cashier_transactions ct
            LEFT JOIN admincashier_acc aa ON ct.cashier_id = aa.id
            LEFT JOIN users us ON ct.user_id = us.id
            WHERE ct.transaction_number = ? OR ct.receipt_number = ? OR ct.id = ?
            LIMIT 1";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param('sss', $lookupValue, $lookupValue, $lookupValue);
            $stmt->execute();
            $transaction = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($transaction) {
                $resolvedSource = 'cashier';
            }
        }
    }

    if (!$transaction && ($source === 'tuition' || $source === 'receipt' || $source === '')) {
        $receiptQuery = "
            SELECT
                tr.id,
                tr.transaction_number,
                tr.receipt_number,
                tr.user_id,
                tr.student_id,
                tr.student_name,
                tr.student_email,
                tr.cashier_id,
                tr.receipt_category,
                tr.amount_paid,
                tr.total_payment,
                tr.balance,
                tr.or_number,
                tr.check_number,
                tr.payment_method,
                tr.payment_type,
                tr.remarks,
                tr.note,
                tr.authorized_rep,
                tr.payment_status,
                tr.created_at,
                CONCAT(aa.first_name, ' ', aa.middle_name, ' ', aa.last_name) AS cashier_name,
                COALESCE(NULLIF(us.course, ''), NULLIF(us.year_section, ''), 'N/A') AS user_course
            FROM tuition_receipts tr
            LEFT JOIN admincashier_acc aa ON tr.cashier_id = aa.id
            LEFT JOIN users us ON tr.user_id = us.id
            WHERE tr.transaction_number = ? OR tr.receipt_number = ? OR tr.id = ?
            LIMIT 1";
        $stmt = $conn->prepare($receiptQuery);
        if ($stmt) {
            $stmt->bind_param('sss', $lookupValue, $lookupValue, $lookupValue);
            $stmt->execute();
            $transaction = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($transaction) {
                $resolvedSource = 'tuition';
            }
        }
    }

    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found.']);
        exit;
    }

    $transaction['source'] = $resolvedSource;
    $transaction['user_course'] = resolveUserCourse($conn, $transaction['user_id'] ?? null, $transaction['guest_school_id'] ?? null);

    if (empty($transaction['student_id']) && !empty($transaction['guest_school_id'])) {
        $transaction['student_id'] = $transaction['guest_school_id'];
    }

    if (($_SESSION['role'] === 'student' || $_SESSION['role'] === 'user') && !empty($transaction['user_id']) && intval($transaction['user_id']) !== intval($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access: This order does not belong to you.']);
        exit;
    }

    if ($resolvedSource === 'cashier') {
        $transaction['items'] = [];
        if (!empty($transaction['raw_items'])) {
            $decodedItems = json_decode($transaction['raw_items'], true);
            if (is_array($decodedItems) && count($decodedItems) > 0) {
                $transaction['items'] = $decodedItems;
            }
        }

        if (empty($transaction['items'])) {
            $itemsQuery = "
                SELECT
                    ti.product_name,
                    ti.item_type,
                    ti.quantity,
                    ti.unit_price,
                    ti.duration,
                    ti.duration_unit,
                    ti.total_item_amount
                FROM transaction_items ti
                WHERE ti.cashier_transaction_id = ?";
            $itemsStmt = $conn->prepare($itemsQuery);
            $itemsStmt->bind_param('i', $transaction['id']);
            $itemsStmt->execute();
            $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $itemsStmt->close();
            $transaction['items'] = $items;
        }
        $transaction['receipt_type'] = $transaction['receipt_category'] ?? 'Transaction';
    } else {
        $transaction['items'] = [[
            'product_name' => $transaction['receipt_category'] ?? 'Receipt Item',
            'quantity' => 1,
            'unit_price' => $transaction['amount_paid'] ?? 0,
            'total' => $transaction['amount_paid'] ?? 0,
            'details' => 'Receipt record'
        ]];
        $transaction['subtotal'] = floatval($transaction['amount_paid'] ?? 0);
        $transaction['discount_percent'] = 0;
        $transaction['discount_amount'] = 0;
        $transaction['total_amount'] = floatval($transaction['total_payment'] ?? $transaction['amount_paid'] ?? 0);
        $transaction['payment_received'] = floatval($transaction['amount_paid'] ?? 0);
        $transaction['change_amount'] = 0;
        $transaction['receipt_type'] = $transaction['receipt_category'] ?? 'Receipt';
        $transaction['transaction_type'] = $transaction['receipt_category'] ?? 'receipt';
    }

    echo json_encode(['success' => true, 'transaction' => $transaction]);
} catch (Exception $e) {
    error_log('Error fetching transaction details: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
} finally {
    $conn->close();
}
?>