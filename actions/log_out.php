<?php
/**
 * GCST Track System - Secure Logout
 * This script ensures all server-side session data is destroyed and the 
 * browser session cookie is invalidated.
 */

// 1. Start the session to access the current session data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Completely unset all session variables
$_SESSION = array();

// 3. Invalidate the session cookie in the browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy the session on the server
session_destroy();

// 5. Redirect the user to the sign-in page
header("Location: /GCST_Track_System/pages/user/user_home.html");
exit;