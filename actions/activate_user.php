<?php
require_once __DIR__ . '/../config/db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = $_POST['student_id'];

    if (!isset($conn) || $conn->connect_error) {
        echo json_encode(['success' => false]);
        exit();
    }

    $stmt = $conn->prepare("UPDATE users SET status = 'Active' WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $success = $stmt->execute();
    $stmt->close();

    // Update counts
    if ($success) {
        $res = $conn->query("SELECT 
            SUM(status = 'Active') AS active_count, 
            SUM(status = 'Suspended') AS suspended_count 
            FROM users");
        $row = $res->fetch_assoc();
        $active = (int)$row['active_count'];
        $suspended = (int)$row['suspended_count'];
        $conn->query("UPDATE count_items SET total_members = $active, total_suspended = $suspended");
    }

    $conn->close();

    echo json_encode(['success' => $success]);
    exit();
}

echo json_encode(['success' => false]);
?>
