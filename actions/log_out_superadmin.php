<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/audit_helpers.php';

secureSessionStart();

// 1. Audit Logging: Record the logout event before destroying the session
if (isset($conn, $_SESSION['admin_id'], $_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
    logAudit($conn, 'superadmin', $_SESSION['admin_id'], 'logout', 'Superadmin logged out safely.');
}

// 2. Clear Session Data: Unset all of the session variables
$_SESSION = array();

// 3. Cookie Invalidation: If it's desired to kill the session, also delete the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Session Destruction: Finally, destroy the session on the server
session_destroy();

// 5. Secure Redirect: Send the user back to the superadmin login page
header("Location: http://localhost/GCST_Track_System/pages/sign_in_superadmin.html");
exit();
?>