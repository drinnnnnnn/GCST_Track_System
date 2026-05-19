<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../database/models/SuperAdminModel.php';
if (!isset($conn) && isset($connection)) {
    $conn = $connection;
}

require_once __DIR__ . '/audit_helpers.php'; // Include audit logging helper
secureSessionStart();

if (isset($conn) && $conn->connect_error) {
    header('Location: ../pages/sign_in_superadmin.html?error=database');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/sign_in_superadmin.html?error=invalid');
    exit();
}

$csrf_token = $_POST['_csrf_token'] ?? '';
if (!validateCsrfToken($csrf_token)) {
    header('Location: ../pages/sign_in_superadmin.html?error=csrf');
    exit();
}

$identifier = trim($_POST['identifier'] ?? '');
$password = $_POST['password'] ?? '';
$pin = $_POST['pin'] ?? '';

if ($identifier === '' || $password === '' || $pin === '') {
    header('Location: ../pages/sign_in_superadmin.html?error=invalid');
    exit();
}

$superAdminModel = new SuperAdminModel();

// Authenticate using the specialized model to handle brute-force protection and the correct table
$admin = $superAdminModel->authenticate($identifier, $password, $pin);

if (!$admin) {
    header('Location: ../pages/sign_in_superadmin.html?error=invalid');
    exit();
}

if (isset($admin['error'])) {
    header('Location: ../pages/sign_in_superadmin.html?error=' . $admin['error']);
    exit();
}

if (isset($admin['locked'])) {
    header('Location: ../pages/sign_in_superadmin.html?error=locked');
    exit();
}

if ($admin['status'] !== 'active') {
    header('Location: ../pages/sign_in_superadmin.html?error=unauthorized');
    exit();
}

session_regenerate_id(true);
$_SESSION['superadmin_id'] = $admin['id'];
$_SESSION['admin_id'] = $admin['id'];
$_SESSION['admin_name'] = trim($admin['first_name'] . ' ' . $admin['last_name']);
$_SESSION['role'] = 'superadmin';
$_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
$_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

logAudit($conn, 'superadmin', $admin['id'], 'login', 'Superadmin logged in successfully.');

header('Location: ../pages/superadmin/superadmin_dashb.html');
exit();