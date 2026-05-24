<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connection.php';

secureSessionStart();
// Restrict access to Superadmins only
requireAuth(['superadmin']);

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Check database connection
$conn = Database::getConnection();
if (!$conn || $conn->connect_error) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed'], 500);
}

// Initialization: Ensure academic fields exist in the users table to prevent Unknown Column errors
$conn->query("SET time_zone = '+08:00'");
$checkStudentId = $conn->query("SHOW COLUMNS FROM `users` LIKE 'student_id'");
if ($checkStudentId && $checkStudentId->num_rows === 0) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `student_id` VARCHAR(50) DEFAULT NULL AFTER `id` ");
}
$checkCourse = $conn->query("SHOW COLUMNS FROM `users` LIKE 'course'");
if ($checkCourse && $checkCourse->num_rows === 0) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `course` VARCHAR(100) DEFAULT NULL AFTER `email` ");
}
$checkYear = $conn->query("SHOW COLUMNS FROM `users` LIKE 'year_level'");
if ($checkYear && $checkYear->num_rows === 0) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `year_level` VARCHAR(50) DEFAULT NULL AFTER `course` ");
}

// Handle Data Retrieval (GET)
if ($method === 'GET') {
    // Default to 'list' if action is missing or empty to ensure smooth student data retrieval
    $action = isset($_GET['action']) && trim($_GET['action']) !== '' ? trim($_GET['action']) : 'list';
    
    switch ($action) {
        case 'list':
            $query = "SELECT id, student_id, first_name, last_name, email, course, year_level, status FROM users WHERE role = 'student' ORDER BY created_at DESC";
            $result = $conn->query($query);
            if (!$result) {
                jsonResponse(['success' => false, 'message' => 'Database error during retrieval: ' . $conn->error], 500);
            }
            $students = [];
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            jsonResponse($students);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid GET action: ' . $action], 400);
    }
}

// Handle Data Operations (POST)
if ($method === 'POST') {
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    
    if (!$input) {
        jsonResponse(['success' => false, 'message' => 'Invalid JSON input'], 400);
    }

    $action = $input['action'] ?? '';
    // Standardize ID extraction to handle varied frontend implementations ('id' vs 'student_id')
    $id = intval($input['id'] ?? $input['student_id'] ?? 0);

    switch ($action) {
        case 'update_status':
            if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid or missing Student ID'], 400);
            
            $status = ($input['status'] === 'active') ? 'active' : 'inactive';
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            jsonResponse(['success' => $stmt->execute()]);
            break;

        case 'update_profile':
            if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid or missing Student ID'], 400);
            
            $fname = filterJsonString($input, 'first_name');
            $lname = filterJsonString($input, 'last_name');
            $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $course = filterJsonString($input, 'course');
            $year = filterJsonString($input, 'year');

            if (empty($fname) || empty($lname) || !$email) {
                jsonResponse(['success' => false, 'message' => 'Invalid or incomplete student profile data'], 400);
            }

            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, course = ?, year_level = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $fname, $lname, $email, $course, $year, $id);
            jsonResponse(['success' => $stmt->execute()]);
            break;

        case 'delete':
            if ($id <= 0) jsonResponse(['success' => false, 'message' => 'Invalid or missing Student ID for deletion'], 400);
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            jsonResponse(['success' => $stmt->execute()]);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Action not recognized: ' . $action], 400);
    }
}

// Fallback for unsupported HTTP methods
jsonResponse(['success' => false, 'message' => 'Unsupported request method: ' . $method], 405);
?>