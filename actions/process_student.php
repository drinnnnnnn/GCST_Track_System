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
        $query = "SELECT id, student_id, first_name, last_name, middle_name, email, sex, course, department, year_level, year_section, contact_number, address, status, is_pwd, school_id_pic, reg_form, payment_scheme, pwd_front, pwd_back, created_at FROM users WHERE (role = 'student' OR role = 'user' OR role IS NULL OR role = '') ORDER BY created_at DESC";
        $result = $conn->query($query);
        if ($result === false) {
            echo json_encode(['success' => false, 'message' => 'Unable to load student data.']);
            exit;
        }
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
        $middle = trim($input['middle_name'] ?? '');
        $lname = trim($input['last_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $course = trim($input['course'] ?? '');
        $department = trim($input['department'] ?? '');
        $address = trim($input['address'] ?? '');
        $year = filter_var($input['year'] ?? 1, FILTER_VALIDATE_INT);
        $yearSection = trim($input['year_section'] ?? '');
        $contact_number = trim ($input['contact_number'] ?? '');
        $status = trim($input['status'] ?? '');

        if ($status === '') {
            $statusStmt = $conn->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
            $statusStmt->bind_param('i', $id);
            $statusStmt->execute();
            $statusStmt->bind_result($existingStatus);
            $statusStmt->fetch();
            $statusStmt->close();
            $status = $existingStatus ?: 'pending';
        }
        $status = strtolower($status);

        if (!$id || empty($fname) || empty($lname) || empty($department) || empty($address) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be valid.']);
            exit;
        }

        // Sanitize status input
        $valid_statuses = ['active', 'pending', 'rejected', 'suspended'];
        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status value provided.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, course = ?, department = ?, year_level = ?, year_section = ?, address = ?, status = ?, contact_number = ? WHERE id = ?");
        $stmt->bind_param("ssssssisssis", $fname, $middle, $lname, $email, $course, $department, $year, $yearSection, $address, $status, $contact_number, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Student profile updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
        }
        $stmt->close();
        break;

    case 'delete':
        $providedId = $input['id'] ?? $input['student_id'] ?? $input['studentId'] ?? null;
        $providedId = trim((string) $providedId);

        if ($providedId === '') {
            echo json_encode(['success' => false, 'message' => 'Student ID missing.']);
            exit;
        }

        $isNumericId = is_numeric($providedId) && (string)(int)$providedId === (string)$providedId;

        if ($isNumericId) {
            $id = (int) $providedId;
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $id);
        } else {
            $studentCode = $providedId;
            $stmt = $conn->prepare("DELETE FROM users WHERE student_id = ?");
            $stmt->bind_param('s', $studentCode);
        }

        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Delete operation failed.']);
            exit;
        }

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Student record permanently removed.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Student not found.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action not recognized.']);
}
$conn->close();
?>