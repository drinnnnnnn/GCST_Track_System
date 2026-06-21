<?php
// 1. Siguraduhing tama ang relative path patungo sa security.php
// Kung magkasama sila sa loob ng 'actions' folder, gamitin ang: __DIR__ . '/security.php'
if (file_exists(__DIR__ . '/security.php')) {
    require_once __DIR__ . '/security.php';
} else {
    require_once __DIR__ . '/../actions/security.php'; // Fallback path kung nasa ibang folder
}

// 2. Siguraduhing active ang session bago basahin ang role
if (function_exists('secureSessionStart')) {
    secureSessionStart();
} else {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}

// 3. Kunin at i-normalize ang role ng user bago burahin ang data
$userRole = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';

// 4. Puwersahang sirain ang session at cookies
if (function_exists('destroySession')) {
    destroySession();
} else {
    $_SESSION = array();
    session_destroy();
}

// 5. Ligtas na pagtawag sa preventPageCache() para iwas sa Fatal Error
if (function_exists('preventPageCache')) {
    preventPageCache();
} else {
    // Kung hindi mahanap ang function, manu-manong i-inject ang headers dito:
    header("Cache-Control: no-cache, no-store, must-revalidate"); 
    header("Pragma: no-cache"); 
    header("Expires: 0"); 
}

// 6. Pagpasyahan ang tamang landing page base sa Role
switch ($userRole) {
    case 'superadmin':
        $redirect = '/GCST_Track_System/pages/sign_in_superadmin.html';
        break;
        
    case 'admincashier':
        $redirect = '/GCST_Track_System/pages/sign_in_admin_cashier.html';
        break;
        
    case 'user':
    default:
        $redirect = '/GCST_Track_System/pages/user/user_home.html';
        break;
}

// 7. URL Explicit Override Filter
if (isset($_GET['redirect'])) {
    $target = basename($_GET['redirect']);
    if (preg_match('/^[a-zA-Z0-9_\-]+\.html$/', $target)) {
        if ($userRole === 'user') {
            $redirect = '/GCST_Track_System/pages/user/' . $target;
        } else {
            $redirect = '/GCST_Track_System/pages/' . $target;
        }
    }
}

// 8. Ligtas na pag-redirect
header("Location: " . $redirect);
exit();
?>