﻿﻿﻿﻿﻿﻿<?php
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

function normalizeAdminStatus($value): string {
    $normalized = strtolower(trim((string) $value));
    if (in_array($normalized, ['active', 'enabled', '1', 'true'], true)) {
        return 'active';
    }
    return 'inactive';
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
            $query = "SELECT id, username, first_name, last_name, middle_name, email, contact_number, role, status, last_login, login_attempts FROM admincashier_acc ORDER BY created_at DESC";
            $result = $conn->query($query);
            
            if (!$result) {
                throw new Exception("Failed to retrieve accounts: " . $conn->error);
            }

            $admincashier = [];
            while ($row = $result->fetch_assoc()) {
                $row['name'] = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']);
                $admincashier[] = $row;
            }
            sendJsonResponse($admincashier);
        }
        sendJsonResponse(['success' => false, 'message' => 'Invalid GET action'], 400);
    }

    // --- POST OPERATIONS ---
    if ($method === 'POST') {
        // 1. Handling Registration (Form or AJAX)
        if (isset($inputData['last_name']) && !isset($inputData['action'])) {
            requireAuth(['superadmin']);
            $username = trim($inputData['username'] ?? '');
            $last_name = trim($inputData['last_name'] ?? '');
            $first_name = trim($inputData['first_name'] ?? '');
            $middle_name = trim($inputData['middle_name'] ?? '');
            $email = trim(filter_var($inputData['email'] ?? '', FILTER_VALIDATE_EMAIL));
            $contact_number = trim($inputData['contact_number'] ?? '');
            $pin = trim($inputData['pin'] ?? '');
            $password = $inputData['password'] ?? '';
            $confirm_password = $inputData['confirm_password'] ?? '';
            $signatureImagePath = null;

            if (!$username || !$last_name || !$first_name || !$email || !$contact_number || !$pin || !$password) {
                if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Missing fields'], 400);
                header('Location: ../pages/superadmin/register_admin_cashier.html?error=missing');
                exit();
            }

            if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['signature_image'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Signature upload failed'], 400);
                    header('Location: ../pages/superadmin/register_admin_cashier.html?error=invalid_signature');
                    exit();
                }

                $allowedMime = ['image/jpeg', 'image/png'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $actualMime = $finfo->file($file['tmp_name']);

                if (!in_array($actualMime, $allowedMime, true)) {
                    if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Invalid signature image format'], 400);
                    header('Location: ../pages/superadmin/register_admin_cashier.html?error=invalid_signature');
                    exit();
                }

                if ($file['size'] > 2 * 1024 * 1024) {
                    if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Signature image file size must be 2MB or smaller'], 400);
                    header('Location: ../pages/superadmin/register_admin_cashier.html?error=invalid_signature');
                    exit();
                }

                $uploadDir = __DIR__ . '/../assets/images/signatures/';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                    throw new Exception('Unable to create signature upload directory.');
                }

                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $newFileName = 'signature_' . bin2hex(random_bytes(8)) . '.' . $extension;
                $destination = $uploadDir . $newFileName;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Unable to save uploaded signature image'], 500);
                    header('Location: ../pages/superadmin/register_admin_cashier.html?error=invalid_signature');
                    exit();
                }

                $signatureImagePath = 'assets/images/signatures/' . $newFileName;
            }

            if (!preg_match('/^\d{11}$/', $contact_number)) {
                if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Contact number must be 11 digits'], 400);
                header('Location: ../pages/superadmin/register_admin_cashier.html?error=invalid_contact');
                exit();
            }

            if (!preg_match('/^\d{4}$/', $pin)) {
                if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'PIN must be 4 digits'], 400);
                header('Location: ../pages/superadmin/register_admin_cashier.html?error=invalid_pin');
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

            $stmt = $conn->prepare('SELECT id FROM admincashier_acc WHERE email = ? OR username = ?');
            $stmt->bind_param('ss', $email, $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Email or username already exists'], 409);
                header('Location: ../pages/superadmin/register_admin_cashier.html?error=exists');
                exit();
            }
            $stmt->close();

            $pinCheck = $conn->prepare('SELECT id, pin FROM admincashier_acc WHERE pin IS NOT NULL AND pin <> ""');
            $pinCheck->execute();
            $pinResult = $pinCheck->get_result();
            while ($pinRow = $pinResult->fetch_assoc()) {
                $existingPin = $pinRow['pin'];
                if ($existingPin !== '' && (password_verify($pin, $existingPin) || $existingPin === $pin)) {
                    $pinCheck->close();
                    if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'PIN already in use'], 409);
                    header('Location: ../pages/superadmin/register_admin_cashier.html?error=pin_duplicate');
                    exit();
                }
            }
            $pinCheck->close();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);
            $role = 'admincashier';
            if ($signatureImagePath) {
                $stmt = $conn->prepare('INSERT INTO admincashier_acc (username, last_name, first_name, middle_name, email, contact_number, pin, password, role, signature_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active")');
                $stmt->bind_param('ssssssssss', $username, $last_name, $first_name, $middle_name, $email, $contact_number, $hashed_pin, $hashed_password, $role, $signatureImagePath);
            } else {
                $stmt = $conn->prepare('INSERT INTO admincashier_acc (username, last_name, first_name, middle_name, email, contact_number, pin, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "active")');
                $stmt->bind_param('sssssssss', $username, $last_name, $first_name, $middle_name, $email, $contact_number, $hashed_pin, $hashed_password, $role);
            }

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
        if (isset($inputData['email']) && isset($inputData['password']) && isset($inputData['pin'])) {
            $identifier = trim($inputData['email']);
            $password = $inputData['password'];
            $pin = trim($inputData['pin']);

            if (empty($identifier) || empty($password) || empty($pin)) {
                if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Username or email, password, and PIN are required'], 400);
                header('Location: ../pages/sign_in_admin_cashier.html?error=invalid');
                exit();
            }

            $stmt = $conn->prepare('SELECT id, username, last_name, first_name, middle_name, password, pin, status FROM admincashier_acc WHERE email = ? OR username = ? LIMIT 1');
            $stmt->bind_param('ss', $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $userId = $user['id'];
                $passwordMatches = password_verify($password, $user['password']);
                $pinValid = false;

                if ($passwordMatches) {
                    if (!empty($user['pin']) && password_verify($pin, $user['pin'])) {
                        $pinValid = true;
                    } elseif ($user['pin'] === $pin) {
                        $pinValid = true;
                        $rehashPin = password_hash($pin, PASSWORD_DEFAULT);
                        $rehashStmt = $conn->prepare('UPDATE admincashier_acc SET pin = ? WHERE id = ?');
                        $rehashStmt->bind_param('si', $rehashPin, $userId);
                        $rehashStmt->execute();
                        $rehashStmt->close();
                    }
                }

                if ($passwordMatches && $pinValid) {
                    if ($user['status'] !== 'active') {
                        if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Account suspended'], 403);
                        header('Location: ../pages/sign_in_admin_cashier.html?error=suspended');
                        exit();
                    }

                    session_regenerate_id(true);
                    $_SESSION = [];
                    $updateLogin = $conn->prepare("UPDATE admincashier_acc SET last_login = NOW(), login_attempts = 0 WHERE id = ?");
                    $updateLogin->bind_param("i", $userId);
                    $updateLogin->execute();

                    $_SESSION['admin_id'] = $userId;
                    $_SESSION['admincashier_id'] = $userId;
                    $_SESSION['admin_name'] = trim(implode(' ', array_filter([
                        $user['first_name'], 
                        $user['middle_name'], 
                        $user['last_name']
                    ])));
                    $_SESSION['contact_number'] = $user['contact_number'] ?? '';
                    $_SESSION['username'] = $user['username'] ?? '';
                    $_SESSION['role'] = 'admincashier';
                    $_SESSION['admincashier_role'] = 'admincashier';
                    $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    
                    logAudit($conn, 'admincashier', $userId, 'login', 'Admin Cashier logged in.');

                    if (isAjax()) sendJsonResponse(['success' => true, 'redirect' => '../pages/admincashier/admincashier_dashb.html']);
                    header('Location: ../pages/admincashier/admincashier_dashb.html');
                    exit();
                }

                $incrementAttempts = $conn->prepare(
                    'UPDATE admincashier_acc SET login_attempts = COALESCE(login_attempts, 0) + 1, status = IF(COALESCE(login_attempts, 0) + 1 >= 4, \'inactive\', status) WHERE id = ?'
                );
                $incrementAttempts->bind_param('i', $userId);
                if (!$incrementAttempts->execute()) {
                    error_log('Login attempts update failed for admincashier_acc id=' . $userId . ': ' . $incrementAttempts->error);
                }

                $incrementAttempts->close();

                $statusCheck = $conn->prepare('SELECT login_attempts, status FROM admincashier_acc WHERE id = ?');
                $statusCheck->bind_param('i', $userId);
                $statusCheck->execute();
                $statusResult = $statusCheck->get_result();
                $statusRow = $statusResult ? $statusResult->fetch_assoc() : null;
                $statusCheck->close();

                if ($statusRow && $statusRow['status'] === 'inactive' && intval($statusRow['login_attempts']) >= 4) {
                    if (isAjax()) sendJsonResponse(['success' => false, 'message' => 'Account suspended after too many failed login attempts.'], 403);
                    header('Location: ../pages/sign_in_admin_cashier.html?error=locked');
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
                    $status = normalizeAdminStatus($inputData['status'] ?? 'inactive');
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
                    $username = trim((string) ($inputData['username'] ?? ''));
                    $fname = trim((string) ($inputData['first_name'] ?? ''));
                    $mname = trim((string) ($inputData['middle_name'] ?? ''));
                    $lname = trim((string) ($inputData['last_name'] ?? ''));
                    $contactNumber = trim((string) ($inputData['contact_number'] ?? ''));
                    $pin = trim((string) ($inputData['pin'] ?? ''));
                    $email = trim((string) ($inputData['email'] ?? ''));
                    if (!$id || !$username || !$fname || !$lname || !$email) throw new Exception("Invalid fields");
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Please provide a valid email address.');
                    if ($contactNumber !== '' && !preg_match('/^\d{11}$/', $contactNumber)) throw new Exception('Contact number must be exactly 11 digits.');

                    if ($pin !== '' && !preg_match('/^\d{4}$/', $pin)) {
                        throw new Exception('Security PIN must be exactly 4 digits.');
                    }

                    $check = $conn->prepare('SELECT id FROM admincashier_acc WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
                    $check->bind_param('ssi', $username, $email, $id);
                    $check->execute();
                    $result = $check->get_result();
                    if ($result && $result->num_rows > 0) {
                        throw new Exception('Username or email already in use by another account.');
                    }
                    $check->close();

                    $signatureImagePath = null;
                    if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $file = $_FILES['signature_image'];
                        if ($file['error'] !== UPLOAD_ERR_OK) {
                            throw new Exception('Signature upload failed. Please try again.');
                        }

                        $allowedMime = ['image/jpeg', 'image/png'];
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $actualMime = $finfo->file($file['tmp_name']);
                        if (!in_array($actualMime, $allowedMime, true)) {
                            throw new Exception('Invalid signature image format. Only JPG and PNG are allowed.');
                        }

                        if ($file['size'] > 2 * 1024 * 1024) {
                            throw new Exception('Signature image file size must be 2MB or smaller.');
                        }

                        $uploadDir = __DIR__ . '/../assets/images/signatures/';
                        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                            throw new Exception('Unable to create signature upload directory.');
                        }

                        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                            $extension = $actualMime === 'image/png' ? 'png' : 'jpg';
                        }

                        $newFileName = 'signature_' . bin2hex(random_bytes(8)) . '.' . $extension;
                        $destination = $uploadDir . $newFileName;
                        if (!move_uploaded_file($file['tmp_name'], $destination)) {
                            throw new Exception('Unable to save uploaded signature image.');
                        }
                        $signatureImagePath = 'assets/images/signatures/' . $newFileName;
                    }

                    if ($pin !== '') {
                        $pinCheck = $conn->prepare('SELECT id, pin FROM admincashier_acc WHERE pin IS NOT NULL AND pin <> "" AND id <> ?');
                        $pinCheck->bind_param('i', $id);
                        $pinCheck->execute();
                        $pinResult = $pinCheck->get_result();
                        while ($pinRow = $pinResult->fetch_assoc()) {
                            $existingPin = $pinRow['pin'];
                            if ($existingPin !== '' && (password_verify($pin, $existingPin) || $existingPin === $pin)) {
                                $pinCheck->close();
                                throw new Exception('Security PIN is already used by another account.');
                            }
                        }
                        $pinCheck->close();
                        $pin = password_hash($pin, PASSWORD_DEFAULT);

                        if ($signatureImagePath) {
                            $stmt = $conn->prepare("UPDATE admincashier_acc SET username = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?, contact_number = ?, pin = ?, signature_image = ? WHERE id = ?");
                            $stmt->bind_param("ssssssssi", $username, $fname, $mname, $lname, $email, $contactNumber, $pin, $signatureImagePath, $id);
                        } else {
                            $stmt = $conn->prepare("UPDATE admincashier_acc SET username = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?, contact_number = ?, pin = ? WHERE id = ?");
                            $stmt->bind_param("sssssssi", $username, $fname, $mname, $lname, $email, $contactNumber, $pin, $id);
                        }
                    } else {
                        if ($signatureImagePath) {
                            $stmt = $conn->prepare("UPDATE admincashier_acc SET username = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?, contact_number = ?, signature_image = ? WHERE id = ?");
                            $stmt->bind_param("sssssssi", $username, $fname, $mname, $lname, $email, $contactNumber, $signatureImagePath, $id);
                        } else {
                            $stmt = $conn->prepare("UPDATE admincashier_acc SET username = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?, contact_number = ? WHERE id = ?");
                            $stmt->bind_param("ssssssi", $username, $fname, $mname, $lname, $email, $contactNumber, $id);
                        }
                    }
                    if ($stmt->execute()) sendJsonResponse(['success' => true, 'message' => 'Account updated']);
                    throw new Exception("Update failed: " . $stmt->error);

                case 'update_pin':
                    $id = filter_var($inputData['id'] ?? 0, FILTER_VALIDATE_INT);
                    $pin = trim($inputData['pin'] ?? '');
                    if (!$id || !preg_match('/^\d{4}$/', $pin)) {
                        throw new Exception('Security PIN must be exactly 4 digits.');
                    }

                    $pinCheck = $conn->prepare('SELECT id, pin FROM admincashier_acc WHERE pin IS NOT NULL AND pin <> "" AND id <> ?');
                    $pinCheck->bind_param('i', $id);
                    $pinCheck->execute();
                    $pinResult = $pinCheck->get_result();
                    while ($pinRow = $pinResult->fetch_assoc()) {
                        $existingPin = $pinRow['pin'];
                        if ($existingPin !== '' && (password_verify($pin, $existingPin) || $existingPin === $pin)) {
                            $pinCheck->close();
                            throw new Exception('Security PIN is already used by another account.');
                        }
                    }
                    $pinCheck->close();

                    $hashedPin = password_hash($pin, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admincashier_acc SET pin = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashedPin, $id);
                    if ($stmt->execute()) {
                        logAudit($conn, 'superadmin', $_SESSION['admin_id'], 'pin_update', "Updated PIN for ID: $id");
                        sendJsonResponse(['success' => true, 'message' => 'Security PIN updated']);
                    }
                    throw new Exception("PIN update failed: " . $stmt->error);

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