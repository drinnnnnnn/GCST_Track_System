﻿﻿﻿<?php
// Force JSON output even if errors occur
header('Content-Type: application/json');
ini_set('display_errors', '0'); // Prevent HTML error output
ob_start(); // Buffer any accidental output

session_start();
require_once __DIR__ . '/../config/db_connect.php';

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    $adminId = $_SESSION['admin_id'] ?? null;
    if (!$adminId) {
        throw new Exception('Authentication required.');
    }

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $payload = json_decode(file_get_contents('php://input'), true);
} else {
    $payload = $_POST;
}

$productName = trim($payload['product_name'] ?? '');
$category = trim($payload['product_category'] ?? '');
$description = trim($payload['product_description'] ?? '');
$productStatus = trim($payload['product_status'] ?? 'available');
$buyPrice = isset($payload['buy_price']) ? floatval($payload['buy_price']) : null;
$stockCount = isset($payload['stock_count']) ? floatval($payload['stock_count']) : null;

// If the product is a book, its buy price should be 0.
if (strtolower($category) === 'books') {
    $buyPrice = 0.00;
}

if ($productName === '') {
    throw new Exception('Product name is required.');
}

if ($stockCount === null || $stockCount < 0) {
    throw new Exception('Stock quantity must be a non-negative number.');
}

// Validate buyPrice only if it's NOT a book
if (strtolower($category) !== 'books' && ($buyPrice === null || $buyPrice < 0)) {
    throw new Exception('Price must be a valid non-negative number.');
}

$productStatus = in_array($productStatus, ['available', 'unavailable'], true) ? $productStatus : 'available';

$stockColumn = 'stock_count';
$stockCheck = $conn->query("SHOW COLUMNS FROM `products` LIKE 'stock_count'");
if (!$stockCheck || $stockCheck->num_rows === 0) {
    $stockColumn = 'stock';
}

$priceColumn = 'buy_price';
$priceCheck = $conn->query("SHOW COLUMNS FROM `products` LIKE 'buy_price'");
if (!$priceCheck || $priceCheck->num_rows === 0) {
    $priceColumn = 'price';
}

$imagePath = null;
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $tmpFile = $_FILES['product_image']['tmp_name'];
    $originalName = basename($_FILES['product_image']['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($extension, $allowed, true)) {
        $fileName = uniqid('product_', true) . '.' . $extension;
        $destPath = $uploadDir . $fileName;
        if (move_uploaded_file($tmpFile, $destPath)) {
            $imagePath = 'uploads/' . $fileName;
        }
    }
}

$sql = "INSERT INTO products (product_name, product_category, product_description, product_status, $priceColumn, $stockColumn, product_image) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    throw new Exception('Failed to prepare insert statement.');
}

$stmt->bind_param('ssssdds', $productName, $category, $description, $productStatus, $buyPrice, $stockCount, $imagePath);
if (!$stmt->execute()) {
    throw new Exception('Unable to create product: ' . $stmt->error);
}
$productId = $stmt->insert_id;
$stmt->close();

$selectSql = "SELECT product_id, product_name, COALESCE(stock_count, stock, 0) AS stock_count, COALESCE(buy_price, price, 0.00) AS buy_price, product_category, product_image, COALESCE(product_status, 'available') AS product_status, CASE WHEN COALESCE(stock_count, stock, 0) = 0 THEN 'Out of Stock' WHEN COALESCE(stock_count, stock, 0) < 10 THEN 'Low Stock' ELSE 'In Stock' END AS status FROM products WHERE product_id = ? LIMIT 1";
$selectStmt = $conn->prepare($selectSql);
$selectStmt->bind_param('i', $productId);
$selectStmt->execute();
$result = $selectStmt->get_result();
$product = $result->fetch_assoc();
$selectStmt->close();

if (!$product) {
    throw new Exception('Product created but could not load product data.');
}

    echo json_encode(['success' => true, 'message' => 'Product created successfully.', 'product' => $product]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
exit;
?>
