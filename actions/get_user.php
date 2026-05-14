<?php
require_once __DIR__ . '/security.php';

secureSessionStart();

$role = $_SESSION['role'] ?? null;
$name = $_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? null;
$isLoggedIn = isset($_SESSION['student_id']) || isset($_SESSION['admin_id']);

// Enhanced Session Integrity Check
if ($isLoggedIn) {
    $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // If the IP or User Agent has changed since login, treat it as a hijacking attempt
    if (($_SESSION['login_ip'] ?? $currentIp) !== $currentIp || 
        ($_SESSION['user_agent'] ?? $currentUserAgent) !== $currentUserAgent) {
        destroySession();
        $isLoggedIn = false;
        $role = null;
        $name = null;
    }
}

jsonResponse([
    'logged_in' => $isLoggedIn,
    'role' => $role,
    'name' => $name,
    'student_id' => $_SESSION['student_id'] ?? null,
    'admin_id' => $_SESSION['admin_id'] ?? null
]);
