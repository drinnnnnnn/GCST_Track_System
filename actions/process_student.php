<?php
/**
 * process_student.php
 * Centralized controller for Student account management and approvals.
 */
ini_set('display_errors', '0');
header('Content-Type: application/json');

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connection.php';

secureSessionStart();
requireAuth(['superadmin']); // Only Superadmins can manage student approvals

$conn = Database::getConnection();
if (!$conn || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);
if ($input && isset($input['action'])) {
    $action = $input['action'];
}

switch ($action) {
    case 'list':
        $query = "SELECT id, student_id, first_name, last_name, middle_name, email, sex, course, department, year_level, contact_number, address, status, school_id_pic, reg_form, payment_scheme, created_at FROM users ORDER BY created_at DESC";
        $result = $conn->query($query);
        $user = [];
        while ($row = $result->fetch_assoc()) {
            $user[] = $row;
        }
        echo json_encode($user);
        break;

    case 'update_status':
        $id = filter_var($input['student_id'] ?? 0, FILTER_VALIDATE_INT);
        $status = $input['status'] ?? '';
        if (!$id || !in_array($status, ['active', 'pending', 'rejected', 'suspended'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status change requested.']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "Account status updated to $status."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
        }
        break;

    case 'update_profile':
        $id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);
        $fname = trim($input['first_name'] ?? '');
        $lname = trim($input['last_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $course = trim($input['course'] ?? '');
        $year = filter_var($input['year'] ?? 1, FILTER_VALIDATE_INT);
        $status = $input['status'] ?? 'pending';

        if (!$id || empty($fname) || empty($lname) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be valid.']);
            exit;
        }

        // Sanitize status input
        $valid_statuses = ['active', 'pending', 'rejected', 'suspended'];
        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status value provided.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, course = ?, year_level = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssssisi", $fname, $lname, $email, $course, $year, $status, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Student profile updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
        }
        $stmt->close();
        break;

    case 'delete':
        $id = filter_var($input['student_id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID provided.']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Student record permanently removed.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete operation failed.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action not recognized.']);
}
$conn->close();
?>