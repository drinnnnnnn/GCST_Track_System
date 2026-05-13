<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
// Allow students and staff to browse products
requireAuth(['users', 'admincashier', 'superadmin', 'student']);
header('Content-Type: application/json');

require_once __DIR__ . '/../database/connection.php';
$conn = Database::getConnection();

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Capture filters from GET request
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$availableOnly = isset($_GET['available_only']) && $_GET['available_only'] === '1';

// Base Query
$sql = "SELECT product_id, product_name, product_category, product_description, 
               product_image, product_status, stock_count, buy_price, rent_price, barcode 
        FROM products WHERE 1=1";
$params = [];
$types = "";

// Apply Category Filter
if (!empty($category) && $category !== 'all') {
    $sql .= " AND LOWER(product_category) = LOWER(?)";
    $params[] = $category;
    $types .= "s";
}

// Apply Search Filter
if (!empty($search)) {
    $sql .= " AND (product_name LIKE ? OR product_description LIKE ? OR barcode LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// Apply Availability Filter (Status must be 'available' and stock > 0)
if ($availableOnly) {
    $sql .= " AND product_status = 'available' AND stock_count > 0";
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$products = [];

while ($row = $result->fetch_assoc()) {
    // Ensure numeric values are formatted correctly for JSON
    $row['product_id'] = (int)$row['product_id'];
    $row['stock_count'] = (int)$row['stock_count'];
    $products[] = $row;
}

echo json_encode($products);

$stmt->close();
$conn->close();