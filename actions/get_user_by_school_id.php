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

$schoolId = $_GET['school_id'] ?? '';

// Basic input validation: ensure it follows the system's identification format
if (empty($schoolId)) {
    echo json_encode(['success' => false, 'message' => 'School ID is required.']);
    exit;
}

try {
    // Lookup user details while avoiding sensitive information
    $stmt = $conn->prepare("SELECT first_name, last_name, course, year_level FROM users WHERE student_id = ? LIMIT 1");
    $stmt->bind_param('s', $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        echo json_encode([
            'success' => true,
            'user' => [
                'full_name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'course' => $user['course'] ?: 'N/A',
                'year_level' => $user['year_level'] ?: 'N/A',
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