﻿<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';

require_once __DIR__ . '/audit_helpers.php'; // Include audit logging helper
secureSessionStart();

if ($conn->connect_error) {
    header('Location: ../pages/sign_in_admin_cashier.html?error=database');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/sign_in_admin_cashier.html?error=invalid');
    exit();
}

if (isset($_POST['last_name'])) {
    $last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $middle_name = trim(filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($last_name === '' || $first_name === '' || $email === '' || $password === '' || $confirm_password === '') {
        header('Location: ../pages/register_admin_cashier.html?error=missing');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ../pages/register_admin_cashier.html?error=invalid_email');
        exit();
    }

    if ($password !== $confirm_password) {
        header('Location: ../pages/register_admin_cashier.html?error=nomatch');
        exit();
    }

    if (strlen($password) < 8) {
        header('Location: ../pages/register_admin_cashier.html?error=weak_password');
        exit();
    }

    $stmt = $conn->prepare('SELECT id FROM admincashier_acc WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        header('Location: ../pages/register_admin_cashier.html?error=exists');
        exit();
    }
    $stmt->close();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO admincashier_acc (last_name, first_name, middle_name, email, password) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $last_name, $first_name, $middle_name, $email, $hashed_password);

    if ($stmt->execute()) {
        $stmt->close();
        header('Location: ../pages/register_admin_cashier.html?success=1');
        exit();
    }

    $stmt->close();
    header('Location: ../pages/register_admin_cashier.html?error=database');
    exit();
}

if (isset($_POST['email']) && isset($_POST['password'])) {
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        header('Location: ../pages/sign_in_admin_cashier.html?error=invalid');
        exit();
    }

    $stmt = $conn->prepare('SELECT id, last_name, first_name, middle_name, password FROM admincashier_acc WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $last_name, $first_name, $middle_name, $hashed_password);
        if ($stmt->fetch() && $hashed_password !== null && password_verify($password, $hashed_password)) {
            session_regenerate_id(true);
            $admin_name = trim($first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name);
            $_SESSION['admin_id'] = $user_id;
            $_SESSION['admin_name'] = $admin_name;
            $_SESSION['role'] = 'admincashier';
            $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            logAudit($conn, 'admincashier', $user_id, 'login', 'Admin Cashier logged in.');

            header('Location: ../pages/admincashier/admincashier_dashb.html');
            exit();
        }
    }

    $stmt->close();
    header('Location: ../pages/sign_in_admin_cashier.html?error=invalid');
    exit();
}

header('Location: ../pages/sign_in_admin_cashier.html?error=invalid');
exit();
?>
