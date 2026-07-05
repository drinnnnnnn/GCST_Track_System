<?php
// actions/get_user_by_school_id.php
// Prevent HTML error output from corrupting the JSON response
ini_set('display_errors', '0');
if (ob_get_level() == 0) ob_start();

require_once __DIR__ . '/security.php';
secureSessionStart();
// Ensure only authorized roles can perform this lookup
requireAuth(['admincashier', 'superadmin', 'admin']);

header('Content-Type: application/json');

// Prevent direct external access
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode(['success' => false, 'message' => 'Direct access not allowed.']);
    exit;
}

// Ensure the Database class is available
require_once __DIR__ . '/../database/connection.php';
$conn = Database::getConnection();

if (!$conn || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$schoolId = trim((string)($_GET['school_id'] ?? $_GET['student_id'] ?? ''));

// Basic input validation: ensure it follows the system's identification format
if ($schoolId === '') {
    echo json_encode(['success' => false, 'message' => 'Student identifier is required.']);
    exit;
}

try {
    $searchText = strtolower($schoolId);
    $normalizedSearch = preg_replace('/[^a-z0-9]/', '', $searchText);
    $searchPattern = '%' . $conn->real_escape_string($schoolId) . '%';

    $stmt = $conn->prepare(
        'SELECT id, student_id, first_name, last_name, course, year_level, year_section, email
         FROM users
         WHERE student_id = ?
            OR REPLACE(LOWER(student_id), "gc-", "") = ?
            OR LOWER(CONCAT(first_name, " ", last_name)) = ?
            OR LOWER(CONCAT(first_name, " ", last_name)) LIKE ?
            OR LOWER(first_name) LIKE ?
            OR LOWER(last_name) LIKE ?
         ORDER BY CASE
            WHEN student_id = ? THEN 0
            WHEN REPLACE(LOWER(student_id), "gc-", "") = ? THEN 1
            WHEN LOWER(CONCAT(first_name, " ", last_name)) = ? THEN 2
            ELSE 3
         END, last_name, first_name
         LIMIT 1'
    );

    if (!$stmt) {
        throw new Exception('Failed to prepare student lookup query.');
    }

    $stmt->bind_param('sssssssss', $schoolId, $normalizedSearch, $searchText, $searchPattern, $searchPattern, $searchPattern, $schoolId, $normalizedSearch, $searchText);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $course = !empty($user['course']) ? trim($user['course']) : 'Not Assigned';
        $yearLevel = !empty($user['year_level']) ? trim($user['year_level']) : 'Not Assigned';
        $yearSection = !empty($user['year_section']) ? trim($user['year_section']) : '';
        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => (int)($user['id'] ?? 0),
                'student_id' => trim((string)($user['student_id'] ?? '')),
                'first_name' => trim((string)($user['first_name'] ?? '')),
                'last_name' => trim((string)($user['last_name'] ?? '')),
                'full_name' => $fullName,
                'course' => $course,
                'year_level' => $yearLevel,
                'year_section' => $yearSection,
                'email' => trim((string)($user['email'] ?? '')),
                'discount_rate' => 5.0 // Configured student discount percentage
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No matching student record found.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An unexpected server error occurred.']);
} finally {
    $conn->close();
}