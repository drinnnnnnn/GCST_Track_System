<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admin', 'cashier', 'admincashier', 'superadmin']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

// Find the highest existing student_id that matches the pattern GC-XXXXXX
$stmt = $conn->prepare("SELECT student_id FROM users WHERE student_id LIKE 'GC-%' ORDER BY student_id DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$lastStudentId = null;
if ($row = $result->fetch_assoc()) {
    $lastStudentId = $row['student_id'];
}
$stmt->close();

$nextStudentId = 'GC-000001'; // Default starting ID if no GC- IDs exist
if ($lastStudentId) {
    // Extract the numeric part (XXXXXX) and increment it
    if (preg_match('/^GC-(\d{6})$/', $lastStudentId, $matches)) {
        $numericPart = intval($matches[1]);
        $nextNumericId = $numericPart + 1;
        $nextStudentId = 'GC-' . str_pad($nextNumericId, 6, '0', STR_PAD_LEFT);
    }
}

echo json_encode(['success' => true, 'next_student_id' => $nextStudentId]);
$conn->close();
?>