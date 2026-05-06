<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';

require_once __DIR__ . '/audit_helpers.php'; // Include audit logging helper
secureSessionStart();

if ($conn->connect_error) {
    header('Location: ../pages/sign_in_superadmin.php?error=database');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/sign_in_superadmin.php?error=invalid');
    exit();
}

$csrf_token = $_POST['_csrf_token'] ?? '';
if (!validateCsrfToken($csrf_token)) {
    header('Location: ../pages/sign_in_superadmin.php?error=csrf');
    exit();
}

$email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
        header('Location: ../pages/sign_in_superadmin.php?error=invalid');
}

$stmt = $conn->prepare('SELECT id, last_name, first_name, middle_name, password, role, status FROM admincashier_acc WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($user_id, $last_name, $first_name, $middle_name, $hashed_password, $role, $status);
    if ($stmt->fetch() && $hashed_password !== null && password_verify($password, $hashed_password)) {
        if ($role !== 'superadmin' || $status !== 'active') {
            $stmt->close();
            header('Location: ../pages/sign_in_superadmin.php?error=unauthorized');
            exit();
        }

        session_regenerate_id(true);
        $admin_name = trim($first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name);
        $_SESSION['superadmin_id'] = $user_id;
        $_SESSION['admin_id'] = $user_id;
        $_SESSION['admin_name'] = $admin_name;
        $_SESSION['role'] = 'superadmin';
        $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        logAudit($conn, 'superadmin', $user_id, 'login', 'Superadmin logged in.');

        header('Location: ../pages/superadmin/superadmin_dashb.html');
        exit();
    }
}

$stmt->close();
header('Location: ../pages/sign_in_superadmin.php?error=invalid');
exit();
