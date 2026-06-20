﻿﻿﻿﻿﻿﻿﻿﻿﻿<?php
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
require_once __DIR__ . '/../database/migrations/MigrationManager.php';
require_once __DIR__ . '/../database/models/SuperAdminModel.php';
require_once __DIR__ . '/audit_helpers.php';

secureSessionStart();

/**
 * Standardized JSON response helper
 */
function sendJsonResponse($data, $httpCode = 200) {
    if (ob_get_length()) ob_clean(); // Clear any accidental whitespace or BOM
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Check if request is AJAX
 */
function isAjax() {
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') || 
           (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);
}

try {
    // Ensure we have a valid connection before proceeding
    $conn = Database::getConnection();
    if (!$conn instanceof mysqli || $conn->connect_errno) {
        $errorDetail = ($conn instanceof mysqli) ? $conn->connect_error : 'Connection instance failed';
        error_log("Database Error: " . $errorDetail);
        throw new Exception("Database connection failed. Please ensure the MySQL service is running.");
    }

    // Run migrations to ensure schema is up to date
    (new MigrationManager())->run();

    $method = $_SERVER['REQUEST_METHOD'];
    $inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $_GET['action'] ?? $inputData['action'] ?? '';

    // --- GET OPERATIONS ---
    if ($method === 'GET') {
        requireAuth(['superadmin']);
        
        if ($action === 'list') {
            $query = "SELECT id, first_name, last_name, middle_name, email, role, status, last_login, login_attempts FROM admincashier_acc ORDER BY created_at DESC";
            $result = $conn->query($query);
            
            if (!$result) {
                throw new Exception("Failed to retrieve accounts: " . $conn->error);
            }

            $admins = [];
            while ($row = $result->fetch_assoc()) {
                $row['name'] = trim($row['first_name'] . ' ' . $row['last_name']);
                $admins[] = $row;
            }
            sendJsonResponse($admins);
        }
        sendJsonResponse(['success' => false, 'message' => 'Invalid GET action'], 400);
    }

    // --- POST OPERATIONS ---
    if ($method === 'POST') {
        // 1. Handling Registration (Form or AJAX)
        if (isset($inputData['last_name']) && !isset($inputData['action'])) {
            requireAuth(['superadmin']);
            $last_name = trim($inputData['last_name'] ?? '');
            $first_name = trim($inputData['first_name'] ?? '');
            $middle_name = trim($inputData['middle_name'] ?? '');
            $email = trim(filter_var($inputData['email'] ?? '', FILTER_VALIDATE_EMAIL));
            $password = $inputData['password'] ?? '';
            $confirm_password = $inputData['confirm_password'] ?? '';

            if (!$last_name || !$first_name || !$email || !$password) {
                if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Missing fields'], 400);
                header('Location: ../pages/superadmin/register_admin_cashier.html?error=missing');
                exit();
            }

            if ($password !== $confirm_password) {
                if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Passwords do not match'], 400);
                header('Location: ../pages/superadmin/register_admin_cashier.html?error=nomatch');
                exit();
            }

            if (strlen($password) < 8) {
                if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Password too weak'], 400);
                header('Location: ../pages/superadmin/register_admin_cashier.html?error=weak_password');
                exit();
            }

            $stmt = $conn->prepare('SELECT id FROM admincashier_acc WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Email already exists'], 409);
                header('Location: ../pages/superadmin/register_admin_cashier.html?error=exists');
                exit();
            }
            $stmt->close();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'admincashier';
            $stmt = $conn->prepare('INSERT INTO admincashier_acc (last_name, first_name, middle_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?, "active")');
            $stmt->bind_param('ssssss', $last_name, $first_name, $middle_name, $email, $hashed_password, $role);

            if ($stmt->execute()) {
                $superModel = new SuperAdminModel();
                $responsibleAdminId = $_SESSION['admin_id'] ?? null;
                $targetFullName = trim("$first_name $last_name");
                $superModel->logStaffRegistration($responsibleAdminId, $email, $targetFullName, $role);
                $stmt->close();
                
                if (isAjax()) sendJsonResponse(['success' => true, 'message' => 'Account registered successfully']);
                header('Location: ../pages/superadmin/register_admin_cashier.html?success=1');
                exit();
            }
            throw new Exception("Registration database error: " . $stmt->error);
        }

        // 2. Handling Login (Form or AJAX)
        if (isset($inputData['email']) && isset($inputData['password'])) {
            $email = trim($inputData['email']);
            $password = $inputData['password'];

            if (empty($email) || empty($password)) {
                if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Email and password required'], 400);
                header('Location: ../pages/sign_in_admin_cashier.html?error=invalid');
                exit();
            }

            $stmt = $conn->prepare('SELECT id, last_name, first_name, middle_name, password, status FROM admincashier_acc WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    if ($user['status'] !== 'active') {
                        if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Account suspended'], 403);
                        header('Location: ../pages/sign_in_admin_cashier.html?error=suspended');
                        exit();
                    }

                    session_regenerate_id(true);
                    $_SESSION = [];
                    $updateLogin = $conn->prepare("UPDATE admincashier_acc SET last_login = NOW(), login_attempts = 0 WHERE id = ?");
                    $updateLogin->bind_param("i", $user['id']);
                    $updateLogin->execute();

                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admincashier_id'] = $user['id'];
                    $_SESSION['admin_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                    $_SESSION['role'] = 'admincashier';
                    $_SESSION['admincashier_role'] = 'admincashier';
                    $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    
                    logAudit($conn, 'admincashier', $user['id'], 'login', 'Admin Cashier logged in.');

                    if (isAjax()) sendJsonResponse(['success' => true, 'redirect' => '../pages/admincashier/admincashier_dashb.html']);
                    header('Location: ../pages/admincashier/admincashier_dashb.html');
                    exit();
                }
            }
            if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Invalid credentials'], 401);
            header('Location: ../pages/sign_in_admin_cashier.html?error=invalid');
            exit();
        }

        // 3. Handling Management Actions (Account updates, etc.)
        if ($action) {
            requireAuth(['superadmin']);
            switch ($action) {
                case 'update_status':
                    $adminId = filter_var($inputData['admin_id'] ?? 0, FILTER_VALIDATE_INT);
                    $status = in_array($inputData['status'], ['active', 'inactive']) ? $inputData['status'] : 'inactive';
                    if (!$adminId) throw new Exception("Invalid ID");
                    
                    $stmt = $conn->prepare("UPDATE admincashier_acc SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $status, $adminId);
                    if ($stmt->execute()) sendJsonResponse(['success' => true, 'message' => 'Status updated']);
                    throw new Exception("Update status failed: " . $stmt->error);

                case 'delete':
                    $adminId = filter_var($inputData['admin_id'] ?? 0, FILTER_VALIDATE_INT);
                    if (!$adminId) throw new Exception("Invalid ID");
                    $stmt = $conn->prepare("DELETE FROM admincashier_acc WHERE id = ?");
                    $stmt->bind_param("i", $adminId);
                    if ($stmt->execute()) sendJsonResponse(['success' => true, 'message' => 'Account removed']);
                    throw new Exception("Delete failed: " . $stmt->error);

                case 'update_account':
                    $id = filter_var($inputData['id'] ?? 0, FILTER_VALIDATE_INT);
                    $fname = trim($inputData['first_name'] ?? '');
                    $lname = trim($inputData['last_name'] ?? '');
                    $email = trim(filter_var($inputData['email'] ?? '', FILTER_VALIDATE_EMAIL));
                    if (!$id || !$fname || !$lname || !$email) throw new Exception("Invalid fields");
                    $stmt = $conn->prepare("UPDATE admincashier_acc SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $fname, $lname, $email, $id);
                    if ($stmt->execute()) sendJsonResponse(['success' => true, 'message' => 'Account updated']);
                    throw new Exception("Update failed: " . $stmt->error);

                case 'update_password':
                    $id = filter_var($inputData['id'] ?? 0, FILTER_VALIDATE_INT);
                    $newPwd = $inputData['new_password'] ?? '';
                    $confirmPwd = $inputData['confirm_password'] ?? '';
                    if (!$id || empty($newPwd) || $newPwd !== $confirmPwd) throw new Exception("Passwords do not match");
                    if (strlen($newPwd) < 8) throw new Exception("Password too short");
                    $hashed = password_hash($newPwd, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admincashier_acc SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed, $id);
                    if ($stmt->execute()) {
                        logAudit($conn, 'superadmin', $_SESSION['admin_id'], 'password_reset', "Reset password for ID: $id");
                        sendJsonResponse(['success' => true, 'message' => 'Password updated']);
                    }
                    throw new Exception("Password update failed: " . $stmt->error);

                default:
                    throw new Exception("Action '$action' not recognized.");
            }
        }
    }

    sendJsonResponse(['success' => false, 'message' => 'Invalid request'], 405);

} catch (Throwable $e) {
    error_log("Controller Error in process_admin_cashier.php: " . $e->getMessage());
    if (isAjax()) {
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
    // Fallback for non-ajax errors
    die("Critical Error: " . htmlspecialchars($e->getMessage()));
}