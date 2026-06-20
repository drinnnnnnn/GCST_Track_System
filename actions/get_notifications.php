<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
// Both admins and students need access to notifications
requireAuth(['admincashier', 'superadmin', 'student', 'user']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

try {
    $role = $_SESSION['role'] ?? '';
    if (in_array($role, ['admincashier', 'superadmin'], true)) {
        $currentId = $_SESSION['admin_id'] ?? $_SESSION['admincashier_id'] ?? null;
        if (!$currentId) {
            echo json_encode([]);
            exit;
        }

        $sql = "SELECT * FROM admin_notification WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10";
        if (!$conn->query("SHOW TABLES LIKE 'admin_notification'")) {
            echo json_encode([]);
            exit;
        }
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $currentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifs = [];
        while ($row = $result->fetch_assoc()) { $notifs[] = $row; }
        echo json_encode($notifs);
        exit;
    }

    $currentId = $_SESSION['student_id'] ?? null;
    if (!$currentId) {
        echo json_encode([]);
        exit;
    }

    $sql = "SELECT * FROM student_notification WHERE student_id = ? ORDER BY created_at DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $currentId);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifs = [];
    while ($row = $result->fetch_assoc()) { $notifs[] = $row; }
    echo json_encode($notifs);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}