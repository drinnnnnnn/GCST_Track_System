<?php
/**
 * actions/process_student.php
 * Backend controller for Superadmin Student Management.
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/SuperAdminModel.php';

// 1. Security Check: Only Superadmins allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$conn = Database::getConnection();

// Handle GET Requests (Data Retrieval)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'list') {
        $sql = "SELECT id, student_id, first_name, last_name, email, course, year_level, balance, status FROM users ORDER BY created_at DESC";
        $result = $conn->query($sql);
        
        $students = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
        }
        echo json_encode($students);
        exit();
    }
}

// Handle POST Requests (Actions)
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'update_status':
        $id = intval($input['student_id'] ?? 0);
        $status = $input['status'] ?? '';
        
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Status updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        break;

    case 'update_profile':
        $id = intval($input['id'] ?? 0);
        $fName = trim($input['first_name'] ?? '');
        $lName = trim($input['last_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $course = trim($input['course'] ?? '');
        $year = intval($input['year'] ?? 1);

        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, course = ?, year_level = ? WHERE id = ?");
        $stmt->bind_param("ssssii", $fName, $lName, $email, $course, $year, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Profile updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed.']);
        }
        break;

    case 'delete':
        $id = intval($input['student_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed.']);
        }
        break;
}