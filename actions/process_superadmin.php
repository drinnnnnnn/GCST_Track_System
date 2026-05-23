<?php
/**
 * process_superadmin.php
 * Refactored login handler for Super Admin with enhanced request validation and 405 error mitigation.
 */
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/SuperAdminModel.php';
require_once __DIR__ . '/audit_helpers.php';

secureSessionStart();

/**
 * Centralized redirect/response handler.
 * Supports both traditional form submissions and AJAX requests.
 */
function respond($errorCode = null, $redirect = null) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $errorCode === null,
            'error' => $errorCode,
            'redirect' => $redirect
        ]);
    } else {
        $url = $redirect ?: '../pages/sign_in_superadmin.html' . ($errorCode ? "?error=$errorCode" : '');
        header("Location: $url");
    }
    exit();
}

try {
    // Ensure correct HTTP method (POST). If GET/other, redirect to login.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        respond('invalid');
    }

    $conn = Database::getConnection();
    
    $csrf_token = $_POST['_csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        respond('csrf');
    }

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $pin = $_POST['pin'] ?? '';

    if ($identifier === '' || $password === '' || $pin === '') {
        respond('invalid');
    }

    $superAdminModel = new SuperAdminModel();
    $admin = $superAdminModel->authenticate($identifier, $password, $pin);

    if (!$admin) respond('invalid');
    if (is_array($admin) && isset($admin['error'])) respond($admin['error']);
    if (is_array($admin) && isset($admin['locked'])) respond('locked');
    if (isset($admin['status']) && $admin['status'] !== 'active') respond('unauthorized');

    // Authentication Successful
    session_regenerate_id(true);
    $_SESSION['superadmin_id'] = $admin['id'];
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = trim($admin['first_name'] . ' ' . $admin['last_name']);
    $_SESSION['role'] = 'superadmin';
    $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

    logAudit($conn, 'superadmin', $admin['id'], 'login', 'Superadmin logged in successfully.');

    respond(null, '../pages/superadmin/superadmin_dashb.html');

} catch (Throwable $e) {
    error_log("SuperAdmin Auth Critical Error: " . $e->getMessage());
    respond('database');
}