<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(["admincashier", "superadmin", "student", "user"]);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

$sql = "SELECT student_id, first_name, last_name FROM users WHERE student_id IS NOT NULL AND student_id != '' ORDER BY last_name ASC";
$result = $conn->query($sql);

$students = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $students[] = [
            'id' => $row['student_id'],
            'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))
        ];
    }
}

echo json_encode($students);

$conn->close();
?>