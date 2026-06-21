<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admincashier') {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$category_id = intval($data['category_id'] ?? 0);
if ($category_id > 0) {
    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id=?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $stmt->close();
    updateCategoryCount($conn);
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid category id']);
}

function updateCategoryCount($conn) {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM categories");
    $row = $result->fetch_assoc();
    $total = (int)$row['cnt'];
    // If count_items table is empty, insert a row
    $conn->query("INSERT INTO count_items (total_categories) SELECT $total WHERE NOT EXISTS (SELECT 1 FROM count_items)");
    // Otherwise, update the row
    $conn->query("UPDATE count_items SET total_categories = $total");
}

$conn->close();
?>
