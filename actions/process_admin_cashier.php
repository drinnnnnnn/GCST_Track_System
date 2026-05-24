﻿﻿﻿<?php
/**
 * process_admin_cashier.php
 * Centralized controller for Admin/Cashier account management.
 * Handles CRUD operations for Superadmins and Login for Staff.
 */
// Prevent error output from breaking the JSON format
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php'; // Explicitly load config first
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/SuperAdminModel.php';

secureSessionStart();

/**
 * Standardized JSON response helper
 */
function sendJsonResponse($data) {
    if (ob_get_length()) ob_clean(); // Clear any accidental whitespace or BOM
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Ensure we have a valid connection before proceeding
$conn = Database::getConnection();
if (!$conn instanceof mysqli || $conn->connect_errno) {
    $errorDetail = ($conn instanceof mysqli) ? $conn->connect_error : 'Connection instance failed';
    error_log("Database Error: " . $errorDetail);
    http_response_code(500);
    sendJsonResponse(['success' => false, 'message' => 'Database connection failed. Verify MySQL is running on the correct port.']);
}

require_once __DIR__ . '/audit_helpers.php';

// Initialization: Ensure the admincashier_acc table exists with required fields
$conn->query("CREATE TABLE IF NOT EXISTS `admincashier_acc` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `middle_name` VARCHAR(100),
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) DEFAULT 'admincashier',
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `last_login` DATETIME,
    `login_attempts` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --- GET OPERATIONS (Listing) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireAuth(['superadmin']);
    $action = $_GET['action'] ?? '';

    if ($action === 'list') {
        $query = "SELECT id, first_name, last_name, middle_name, email, role, status, last_login, login_attempts FROM admincashier_acc ORDER BY created_at DESC";
        $result = $conn->query($query);
        
        if (!$result) {
            sendJsonResponse(['success' => false, 'message' => 'Failed to retrieve accounts: ' . $conn->error]);
        }

        $admins = [];
        while ($row = $result->fetch_assoc()) {
            $row['name'] = trim($row['first_name'] . ' ' . $row['last_name']);
            $admins[] = $row;
        }
        sendJsonResponse($admins);
    }
    sendJsonResponse(['success' => false, 'message' => 'Invalid action']);
}

// --- POST OPERATIONS (CRUD & Login) ---
if (isset($_POST['last_name'])) {
    requireAuth(['superadmin']); // Secure registration
    $last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $middle_name = trim(filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');
    $role = 'admincashier';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($last_name === '' || $first_name === '' || $email === '' || $password === '' || $confirm_password === '') {
        header('Location: ../pages/superadmin/register_admin_cashier.html?error=missing');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ../pages/superadmin/register_admin_cashier.html?error=invalid_email');
        exit();
    }

    if ($password !== $confirm_password) {
        header('Location: ../pages/superadmin/register_admin_cashier.html?error=nomatch');
        exit();
    }

    if (strlen($password) < 8) {
        header('Location: ../pages/superadmin/register_admin_cashier.html?error=weak_password');
        exit();
    }

    $stmt = $conn->prepare('SELECT id FROM admincashier_acc WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        header('Location: ../pages/superadmin/register_admin_cashier.html?error=exists');
        exit();
    }
    $stmt->close();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO admincashier_acc (last_name, first_name, middle_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?, "active")');
    $stmt->bind_param('ssssss', $last_name, $first_name, $middle_name, $email, $hashed_password, $role);

    if ($stmt->execute()) {
        // Log the successful registration event
        $superModel = new SuperAdminModel();
        $responsibleAdminId = $_SESSION['admin_id'] ?? null;
        $targetFullName = trim("$first_name $last_name");
        $superModel->logStaffRegistration($responsibleAdminId, $email, $targetFullName, $role);

        $stmt->close();
        header('Location: ../pages/superadmin/register_admin_cashier.html?success=1');
        exit();
    }

    $stmt->close();
    header('Location: ../pages/superadmin/register_admin_cashier.html?error=database');
    exit();
}

// Handle JSON POST Actions (Management)
$input = json_decode(file_get_contents('php://input'), true);
if ($input && isset($input['action'])) {
    requireAuth(['superadmin']);
    $action = $input['action'];

    switch ($action) {
        case 'update_status':
            $adminId = filter_var($input['admin_id'] ?? 0, FILTER_VALIDATE_INT);
            $status = in_array($input['status'], ['active', 'inactive']) ? $input['status'] : 'inactive';
            
            if (!$adminId) sendJsonResponse(['success' => false, 'message' => 'Invalid ID']);
            
            $stmt = $conn->prepare("UPDATE admincashier_acc SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $adminId);
            if ($stmt->execute()) {
                sendJsonResponse(['success' => true, 'message' => 'Status updated successfully.']);
            }
            sendJsonResponse(['success' => false, 'message' => 'Failed to update status.']);
            break;

        case 'delete':
            $adminId = filter_var($input['admin_id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$adminId) sendJsonResponse(['success' => false, 'message' => 'Invalid ID']);

            $stmt = $conn->prepare("DELETE FROM admincashier_acc WHERE id = ?");
            $stmt->bind_param("i", $adminId);
            if ($stmt->execute()) {
                sendJsonResponse(['success' => true, 'message' => 'Account removed.']);
            }
            sendJsonResponse(['success' => false, 'message' => 'Delete operation failed.']);
            break;

        case 'update_account':
            $id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);
            $fname = trim($input['first_name'] ?? '');
            $lname = trim($input['last_name'] ?? '');
            $email = trim($input['email'] ?? '');

            if (!$id || empty($fname) || empty($lname) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendJsonResponse(['success' => false, 'message' => 'All fields are required and must be valid.']);
            }

            $stmt = $conn->prepare("UPDATE admincashier_acc SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("sssi", $fname, $lname, $email, $id);
            if ($stmt->execute()) {
                sendJsonResponse(['success' => true, 'message' => 'Account updated.']);
            }
            sendJsonResponse(['success' => false, 'message' => 'Update failed.']);
            break;

        case 'update_password':
            $id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);
            $newPwd = $input['new_password'] ?? '';
            $confirmPwd = $input['confirm_password'] ?? '';

            if (!$id || empty($newPwd) || $newPwd !== $confirmPwd) {
                sendJsonResponse(['success' => false, 'message' => 'Passwords must match and be valid.']);
            }
            if (strlen($newPwd) < 8) {
                sendJsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters long for security.']);
            }

            $hashed = password_hash($newPwd, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admincashier_acc SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $id);
            if ($stmt->execute()) {
                logAudit($conn, 'superadmin', $_SESSION['admin_id'] ?? 'system', 'password_reset', "Superadmin reset password for Admin Account ID: $id");
                sendJsonResponse(['success' => true, 'message' => 'Password updated successfully.']);
            }
            sendJsonResponse(['success' => false, 'message' => 'Database update failed.']);
            break;

        default:
            sendJsonResponse(['success' => false, 'message' => 'Action not recognized.']);
    }
}

if (isset($_POST['email']) && isset($_POST['password'])) {
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        header('Location: ../pages/sign_in_admin_cashier.html?error=invalid');
        exit();
    }

    $stmt = $conn->prepare('SELECT id, last_name, first_name, middle_name, password, status FROM admincashier_acc WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $last_name, $first_name, $middle_name, $hashed_password, $status);
        if ($stmt->fetch() && $hashed_password !== null && password_verify($password, $hashed_password)) {
            if ($status !== 'active') {
                $stmt->close();
                header('Location: ../pages/sign_in_admin_cashier.html?error=suspended');
                exit();
            }

            session_regenerate_id(true);
            $admin_name = trim($first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name);
            $_SESSION['admin_id'] = $user_id;
            $_SESSION['admin_name'] = $admin_name;
            $_SESSION['role'] = 'admincashier';
            $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            logAudit($conn, 'admincashier', $user_id, 'login', 'Admin Cashier logged in.');

            header('Location: ../pages/admincashier/admincashier_dashb.html');
            exit();
        }
    }

    $stmt->close();
    header('Location: ../pages/sign_in_admin_cashier.html?error=invalid');
    exit();
}
?>