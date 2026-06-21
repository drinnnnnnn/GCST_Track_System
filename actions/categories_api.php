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

// Initialize default categories
$default_categories = ['Books', 'Uniform', 'Accessories'];
foreach ($default_categories as $cat) {
    $stmt = $conn->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
    $stmt->bind_param("s", $cat);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $category = trim($data['name'] ?? '');
    if ($category === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Category name required']);
        exit;
    }
    $stmt = $conn->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
    $stmt->bind_param("s", $category);
    if ($stmt->execute()) {
        updateCategoryCount($conn);
        echo json_encode(['success' => true, 'name' => $category]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Insert failed']);
    }
    $stmt->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT category_id, name FROM categories ORDER BY name ASC");
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    echo json_encode($categories);
    exit;
}

function updateCategoryCount($conn) {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM categories");
    $row = $result->fetch_assoc();
    $total = (int)$row['cnt'];
    $conn->query("INSERT INTO count_items (total_categories) SELECT $total WHERE NOT EXISTS (SELECT 1 FROM count_items)");
    $conn->query("UPDATE count_items SET total_categories = $total");
}
?>
