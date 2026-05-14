﻿<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'admincashier', 'superadmin']);
header('Content-Type: application/json');
require_once __DIR__ . '/../database/connection.php';
$conn = Database::getConnection();

$student_id = $_SESSION['student_id'] ?? null;
$admin_id = $_SESSION['admin_id'] ?? null;

if ($student_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0");
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true]);
}

if ($admin_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE admin_id = ? AND is_read = 0");
    $stmt->bind_param('s', $admin_id);
    $stmt->execute();
    $stmt->close();
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false], 401);
?>
