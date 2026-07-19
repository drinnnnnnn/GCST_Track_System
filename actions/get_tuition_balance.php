<?php
header('Content-Type: application/json');
ini_set('display_errors', '0');
if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';

secureSessionStart();
requireAuth(['admincashier', 'superadmin']);

$studentId = isset($_GET['student_id']) ? trim((string)$_GET['student_id']) : '';
if ($studentId === '') {
    echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
    exit;
}

try {
    $stmt = $conn->prepare(
        'SELECT tf.total_fees, tf.total_paid, tf.balance, tf.payment_status, u.id AS user_id, u.student_id, u.first_name, u.last_name, u.email
         FROM tuition_fees tf
         JOIN users u ON tf.user_id = u.id
         WHERE u.student_id = ?
         LIMIT 1'
    );
    if (!$stmt) {
        throw new Exception('Failed to prepare balance query.');
    }

    $stmt->bind_param('s', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if ($student) {
        echo json_encode([
            'success' => true,
            'student' => [
                'user_id' => $student['user_id'],
                'student_id' => $student['student_id'],
                'full_name' => trim($student['first_name'] . ' ' . $student['last_name']),
                'email' => trim((string)($student['email'] ?? '')),
                'total_fees' => (float)($student['total_fees'] ?? 0),
                'total_paid' => (float)($student['total_paid'] ?? 0),
                'tuition_balance' => (float)($student['balance'] ?? 0),
                'payment_status' => $student['payment_status'] ?? 'Unpaid'
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'student' => [
                'student_id' => $studentId,
                'total_fees' => 0.0,
                'total_paid' => 0.0,
                'tuition_balance' => 0.0,
                'payment_status' => 'Unpaid'
            ]
        ]);
    }
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
