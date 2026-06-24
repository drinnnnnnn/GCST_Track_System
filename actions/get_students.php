<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(["admincashier", "superadmin", "student", "user"]);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

$sql = "SELECT id, student_id, first_name, last_name, email, contact_number, course, year_level, year_section FROM users WHERE student_id IS NOT NULL AND student_id != '' ORDER BY last_name ASC";
$result = $conn->query($sql);

$students = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dbId = isset($row['id']) ? (int) $row['id'] : 0;
        $studentId = trim((string)($row['student_id'] ?? ''));
        $firstName = trim((string)($row['first_name'] ?? ''));
        $lastName = trim((string)($row['last_name'] ?? ''));
        $email = trim((string)($row['email'] ?? ''));
        $contactNumber = trim((string)($row['contact_number'] ?? ''));
        $course = trim((string)($row['course'] ?? ''));
        $yearLevel = trim((string)($row['year_level'] ?? ''));
        $yearSection = trim((string)($row['year_section'] ?? ''));

        $students[] = [
            'id' => $dbId,
            'student_id' => $studentId !== '' ? $studentId : (string) $dbId,
            'name' => trim($firstName . ' ' . $lastName),
            'email' => $email !== '' ? $email : null,
            'contact_number' => $contactNumber !== '' ? $contactNumber : null,
            'course' => $course !== '' ? $course : null,
            'year_level' => $yearLevel !== '' ? $yearLevel : null,
            'year_section' => $yearSection !== '' ? $yearSection : null,
            'program' => $course !== '' ? $course : null
        ];
    }
}

echo json_encode($students);

$conn->close();
?>