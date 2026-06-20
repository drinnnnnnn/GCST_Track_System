<?php
require_once __DIR__ . '/security.php';

secureSessionStart();

$role = $_SESSION['role'] ?? null;
$name = $_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? null;
$studentId = $_SESSION['student_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$adminId = $_SESSION['admin_id'] ?? $_SESSION['admincashier_id'] ?? null;
$isLoggedIn = false;

if (($role === 'student' || $role === 'user') && !empty($studentId)) {
    $isLoggedIn = true;
} elseif (($role === 'admincashier' || $role === 'superadmin') && !empty($adminId)) {
    $isLoggedIn = true;
} elseif (!empty($studentId) || !empty($userId) || !empty($adminId)) {
    $isLoggedIn = true;
}

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
        $studentId = null;
        $userId = null;
        $adminId = null;
    }
}

jsonResponse([
    'logged_in' => $isLoggedIn,
    'role' => $role,
    'name' => $name,
    'student_id' => $studentId,
    'student_role' => $_SESSION['student_role'] ?? null,
    'user_id' => $userId,
    'admin_id' => $adminId,
    'admincashier_id' => $_SESSION['admincashier_id'] ?? null,
    'admincashier_role' => $_SESSION['admincashier_role'] ?? null,
    'superadmin_id' => $_SESSION['superadmin_id'] ?? null,
    'superadmin_role' => $_SESSION['superadmin_role'] ?? null
]);
