<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';

secureSessionStart();
// Allow admins to fetch student details for verification or profile viewing
requireAuth(['student', 'admincashier', 'superadmin']);
header('Content-Type: application/json');

$student_id = $_GET['student_id'] ?? $_SESSION['student_id'] ?? null;
if (!$student_id) {
    jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$stmt = $conn->prepare('SELECT id, student_id, last_name, first_name, middle_name, email, course, year_section, contact_number, phone, address, status, created_at FROM users WHERE student_id = ? LIMIT 1');
$stmt->bind_param('s', $student_id);
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

$lastLoginColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
if ($lastLoginColumn && $lastLoginColumn->num_rows > 0) {
    $loginStmt = $conn->prepare('SELECT last_login FROM users WHERE student_id = ? LIMIT 1');
    $loginStmt->bind_param('s', $student_id);
    $loginStmt->execute();
    $loginResult = $loginStmt->get_result();
    $loginRow = $loginResult ? $loginResult->fetch_assoc() : null;
    $loginStmt->close();
    if ($loginRow && isset($loginRow['last_login'])) {
        $user['last_login'] = $loginRow['last_login'];
    }
}

$historyStmt = $conn->prepare('SELECT t.transaction_date, p.product_name, t.type, t.quantity, t.total_amount FROM transactions t JOIN products p ON p.product_id = t.product_id WHERE t.user_id = ? ORDER BY t.transaction_date DESC LIMIT 20');
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

$prefStmt = $conn->prepare('SELECT preferences FROM notification_preferences WHERE student_id = ? LIMIT 1');
$prefStmt->bind_param('s', $student_id);
$prefStmt->execute();
$prefResult = $prefStmt->get_result();
if ($prefResult && $prefRow = $prefResult->fetch_assoc()) {
    $prefs = json_decode($prefRow['preferences'], true);
    if (is_array($prefs)) {
        $user['notification_preferences'] = array_merge($user['notification_preferences'], $prefs);
    }
}
$prefStmt->close();

jsonResponse(['success' => true, 'user' => $user]);
?>