<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
// Both admins and students need access to notifications
requireAuth(['admincashier', 'superadmin', 'student', 'user']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

try {
    // Handle both admin and student notifications
    $currentId = $_SESSION['admin_id'] ?? $_SESSION['student_id'] ?? null;
    $idKey = isset($_SESSION['admin_id']) ? 'admin_id' : 'student_id';
    
    // We use a general query that works with the current session context
    $sql = "SELECT * FROM student_notification WHERE $idKey = ? ORDER BY created_at DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $currentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifs = [];
    while($row = $result->fetch_assoc()) { $notifs[] = $row; }
    echo json_encode($notifs);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}