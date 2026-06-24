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

$transactionId = $_GET['id'] ?? null;

if (!$transactionId) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID is required.']);
    exit;
}

try {
    // Fetch main transaction details
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
        FROM
            cashier_transactions ct
        LEFT JOIN
            admincashier_acc aa ON ct.cashier_id = aa.id
        LEFT JOIN
            users us ON ct.user_id = us.id
        WHERE
            ct.transaction_number = ? OR ct.id = ?
        LIMIT 1";
    $stmt = $conn->prepare($query);
    // Bind both parameters as strings to avoid type coercion issues when transaction number is used
    $stmt->bind_param('ss', $transactionId, $transactionId); // Try matching by number or ID
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found.']);
        exit;
    }

    // Ensure we have a reliable `user_course` value.
    // Prefer the registered user's `course`, then `year_section`.
    // For guest orders with a valid guest_school_id, resolve the student record and use its course.
    $userCourse = 'N/A';
    if (!empty($transaction['user_id'])) {
        $uid = intval($transaction['user_id']);
        $uStmt = $conn->prepare("SELECT course, year_section FROM users WHERE id = ? LIMIT 1");
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

    if ($userCourse === 'N/A' && !empty($transaction['guest_school_id'])) {
        $gStmt = $conn->prepare("SELECT course, year_section FROM users WHERE student_id = ? LIMIT 1");
        if ($gStmt) {
            $gStmt->bind_param('s', $transaction['guest_school_id']);
            $gStmt->execute();
            $gRow = $gStmt->get_result()->fetch_assoc();
            $gStmt->close();
            if ($gRow) {
                if (!empty($gRow['course'])) $userCourse = $gRow['course'];
                elseif (!empty($gRow['year_section'])) $userCourse = $gRow['year_section'];
            }
        }
    }

    if (empty($transaction['student_id']) && !empty($transaction['guest_school_id'])) {
        $transaction['student_id'] = $transaction['guest_school_id'];
    }

    $transaction['user_course'] = $userCourse;

    // Security: Prevent students from viewing other users' orders (IDOR protection)
    if (($_SESSION['role'] === 'student' || $_SESSION['role'] === 'user') && intval($transaction['user_id']) !== intval($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access: This order does not belong to you.']);
        exit;
    }

    // Prefer using the saved cart JSON if available, to preserve display metadata like upper/lower uniform details.
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
            FROM
                transaction_items ti
            WHERE
                ti.cashier_transaction_id = ?";
        $itemsStmt = $conn->prepare($itemsQuery);
        $itemsStmt->bind_param('i', $transaction['id']);
        $itemsStmt->execute();
        $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $itemsStmt->close();
        $transaction['items'] = $items;
    }

    echo json_encode(['success' => true, 'transaction' => $transaction]);

} catch (Exception $e) {
    error_log("Error fetching transaction details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
} finally {
    $conn->close();
}
?>