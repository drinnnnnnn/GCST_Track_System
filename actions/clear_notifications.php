﻿<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../database/connection.php';
$conn = Database::getConnection();

$student_id = $_SESSION['student_id'] ?? null;
$admin_id = $_SESSION['admin_id'] ?? null;

if ($student_id) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false]);
    }
    $stmt->close();
} elseif ($admin_id) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE admin_id = ?");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false]);
}
$conn->close();
?>
