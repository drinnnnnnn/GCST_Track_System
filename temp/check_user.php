<?php
require_once __DIR__ . '/../database/connection.php';

$stmt = $conn->prepare('SELECT id, student_id, email, password_hash, status, role FROM users WHERE student_id = ?');
if (!$stmt) {
    echo "PREPARE_FAILED\n";
    echo $conn->error;
    exit;
}
$student = 'GC-123456';
$stmt->bind_param('s', $student);
$stmt->execute();
$stmt->bind_result($id, $student_id, $email, $hash, $status, $role);
if (!$stmt->fetch()) {
    echo "NO_USER\n";
} else {
    echo json_encode([
        'id' => $id,
        'student_id' => $student_id,
        'email' => $email,
        'status' => $status,
        'role' => $role,
        'hash_prefix' => substr($hash, 0, 20),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
$stmt->close();
$conn->close();
