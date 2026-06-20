<?php
/**
 * process_superadmin.php
 * 
 * Handles authentication for the Superadmin dashboard.
 */

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/models/SuperAdminModel.php';

// Start a secure session
secureSessionStart();

// 1. Method Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    header("Location: ../pages/sign_in_superadmin.html?error=invalid_method");
    exit();
}

// 1. Collect and Sanitize Inputs
$identifier = isset($_POST['email']) ? trim($_POST['email']) : '';
$password   = isset($_POST['password']) ? $_POST['password'] : '';
$pin        = isset($_POST['pin']) ? trim($_POST['pin']) : '';

// 2. Basic Validation
if (empty($identifier) || empty($password) || empty($pin)) {
    header("Location: ../pages/sign_in_superadmin.html?error=missing");
    exit();
}

try {
    $model = new SuperAdminModel();
    
    // Get metadata for logging
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    // 3. Authenticate via Model
    $result = $model->authenticate($identifier, $password, $pin);

    if ($result === false) {
        // Generic failure (User not found or password wrong)
        $model->logEvent(null, 'login_failed', $identifier, $ip, $ua, 'Invalid identifier or password');
        header("Location: ../pages/sign_in_superadmin.html?error=invalid");
        exit();
    }

    if (isset($result['locked']) && $result['locked'] === true) {
        // Account is temporarily locked
        $model->logEvent($result['id'], 'account_locked', $identifier, $ip, $ua, "Locked until " . $result['until']);
        header("Location: ../pages/sign_in_superadmin.html?error=locked");
        exit();
    }

    if (isset($result['error']) && $result['error'] === 'invalid_pin') {
        // Password was correct, but PIN failed
        $model->logEvent($result['id'], 'login_failed_pin', $identifier, $ip, $ua, 'Incorrect security PIN provided');
        header("Location: ../pages/sign_in_superadmin.html?error=invalid_pin");
        exit();
    }

    // 4. Authentication Successful
    // Verify status is active
    if ($result['status'] !== 'active') {
        $model->logEvent($result['id'], 'access_denied', $identifier, $ip, $ua, 'Account status: ' . $result['status']);
        header("Location: ../pages/sign_in_superadmin.html?error=unauthorized");
        exit();
    }

    // Set Session Variables
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['admin_id']         = $result['id'];
    $_SESSION['admincashier_id']   = $result['id'];
    $_SESSION['superadmin_id']     = $result['id'];
    $_SESSION['username']          = $result['username'];
    $_SESSION['role']              = 'superadmin';
    $_SESSION['admincashier_role'] = 'admincashier';
    $_SESSION['superadmin_role']   = 'superadmin';
    $_SESSION['admin_name']        = $result['first_name'] . ' ' . $result['last_name'];
    $_SESSION['last_login']        = $result['last_login_at'];
    $_SESSION['login_ip']          = $ip;
    $_SESSION['user_agent']        = $ua;

    // Log Success
    $model->logEvent($result['id'], 'login_success', $identifier, $ip, $ua);

    // Redirect to Dashboard
    header("Location: ../pages/superadmin/superadmin_dashb.html");
    exit();

} catch (Exception $e) {
    error_log("Superadmin Login Error: " . $e->getMessage());
    header("Location: ../pages/sign_in_superadmin.html?error=database");
    exit();
}
?>