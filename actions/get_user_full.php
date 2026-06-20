<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';

secureSessionStart();
header('Content-Type: application/json');

$sessionStudentId = $_SESSION['student_id'] ?? null;
$sessionUserId = $_SESSION['user_id'] ?? null;

if (!$sessionStudentId && !$sessionUserId) {
    jsonResponse(['success' => false, 'message' => 'Session expired'], 401);
}

// Allow student/user profiles to load for the intended pages while keeping
// the existing administrative access intact.
requireAuth(['student', 'user', 'admincashier', 'superadmin']);

$studentId = $_GET['student_id'] ?? $sessionStudentId;
$userId = $_GET['user_id'] ?? $sessionUserId;

if (!$studentId && !$userId) {
    jsonResponse(['success' => false, 'message' => 'Session expired'], 401);
}

try {
    $stmt = $conn->prepare(
        'SELECT id, student_id, last_name, first_name, middle_name, email, course, year_section, contact_number, phone, address, status, created_at ' .
        'FROM users WHERE student_id = ? OR id = ? LIMIT 1'
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
        jsonResponse(['success' => false, 'message' => 'User not found.'], 404);
    }

    $user['full_name'] = trim($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name']);
    $user['transaction_history'] = [];
    $user['notification_preferences'] = [
        'email_notifications' => true,
        'rental_reminders' => true,
        'payment_reminders' => true,
        'queue_notifications' => true,
        'system_updates' => true,
    ];

    if ($conn->query("SHOW COLUMNS FROM users LIKE 'last_login'")->num_rows > 0) {
        $loginStmt = $conn->prepare('SELECT last_login FROM users WHERE student_id = ? OR id = ? LIMIT 1');
        if ($loginStmt) {
            $loginStmt->bind_param('si', $studentId, $userId);
            $loginStmt->execute();
            $loginResult = $loginStmt->get_result();
            $loginRow = $loginResult ? $loginResult->fetch_assoc() : null;
            $loginStmt->close();
            if ($loginRow && isset($loginRow['last_login'])) {
                $user['last_login'] = $loginRow['last_login'];
            }
        }
    }

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
    jsonResponse(['success' => false, 'message' => 'Unable to load profile data.'], 500);
}

function tableExists(mysqli $conn, string $tableName): bool {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}
?>