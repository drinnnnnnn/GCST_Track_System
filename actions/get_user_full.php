<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';

secureSessionStart();
header('Content-Type: application/json');

$sessionRole = $_SESSION['role'] ?? null;
$sessionStudentId = $_SESSION['student_id'] ?? null;
$sessionUserId = $_SESSION['user_id'] ?? null;
$sessionAdminCashierId = $_SESSION['admincashier_id'] ?? $_SESSION['admin_id'] ?? null;

if (!$sessionStudentId && !$sessionUserId && !$sessionAdminCashierId) {
    jsonResponse(['success' => false, 'message' => 'Session expired. Please sign in again.'], 401);
}

// Allow student/user profiles to load for the intended pages while keeping
// the existing administrative access intact.
requireAuth(['student', 'user', 'admincashier', 'superadmin']);

$studentId = $_GET['student_id'] ?? $sessionStudentId;
$userId = $_GET['user_id'] ?? $sessionUserId;

if (!$studentId && !$userId && !in_array($sessionRole, ['admincashier', 'superadmin'], true)) {
    jsonResponse(['success' => false, 'message' => 'Session expired. Please sign in again.'], 401);
}

try {
    $hasLastLoginColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'")->num_rows > 0;
    $selectColumns = 'id, student_id, last_name, first_name, middle_name, email, course, year_section, contact_number, phone, address, status, created_at';
    if ($hasLastLoginColumn) {
        $selectColumns .= ', last_login';
    }

    $stmt = $conn->prepare(
        "SELECT $selectColumns FROM users WHERE student_id = ? OR id = ? LIMIT 1"
    );
    if (!$stmt) {
        throw new Exception('Unable to prepare user lookup statement.');
    }

    $stmt->bind_param('si', $studentId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'User not found for the current session.'], 404);
    }

    if (empty($user['contact_number']) && !empty($user['phone'])) {
        $user['contact_number'] = $user['phone'];
    }

    $user['last_login'] = $hasLastLoginColumn ? ($user['last_login'] ?? null) : null;
    $user['full_name'] = trim($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name']);
    $user['transaction_history'] = [];
    $user['notification_preferences'] = [
        'email_notifications' => true,
        'rental_reminders' => true,
        'payment_reminders' => true,
        'queue_notifications' => true,
        'system_updates' => true,
    ];

    // Load recent transaction history if the related tables exist.
    if (tableExists($conn, 'transactions') && tableExists($conn, 'products')) {
        $historyStmt = $conn->prepare(
            'SELECT t.transaction_date, p.product_name, t.type, t.quantity, t.total_amount ' .
            'FROM transactions t ' .
            'JOIN products p ON p.product_id = t.product_id ' .
            'WHERE t.user_id = ? ' .
            'ORDER BY t.transaction_date DESC LIMIT 20'
        );
        if ($historyStmt) {
            $historyStmt->bind_param('i', $user['id']);
            $historyStmt->execute();
            $historyResult = $historyStmt->get_result();
            if ($historyResult) {
                while ($row = $historyResult->fetch_assoc()) {
                    $user['transaction_history'][] = [
                        'date' => $row['transaction_date'],
                        'description' => $row['product_name'],
                        'type' => ucfirst($row['type']),
                        'amount' => number_format($row['total_amount'], 2)
                    ];
                }
            }
            $historyStmt->close();
        }
    }

    if (tableExists($conn, 'notification_preferences')) {
        $prefStmt = $conn->prepare('SELECT preferences FROM notification_preferences WHERE student_id = ? LIMIT 1');
        if ($prefStmt) {
            $prefStmt->bind_param('s', $studentId);
            $prefStmt->execute();
            $prefResult = $prefStmt->get_result();
            if ($prefResult && $prefRow = $prefResult->fetch_assoc()) {
                $prefs = json_decode($prefRow['preferences'], true);
                if (is_array($prefs)) {
                    $user['notification_preferences'] = array_merge($user['notification_preferences'], $prefs);
                }
            }
            $prefStmt->close();
        }
    }

    jsonResponse(['success' => true, 'user' => $user]);
} catch (Exception $e) {
    error_log('get_user_full.php error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function tableExists(mysqli $conn, string $tableName): bool {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}
?>