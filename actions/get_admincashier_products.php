<?php
// Prevent HTML error output from corrupting the JSON response
ini_set('display_errors', '0');
if (ob_get_level() == 0) ob_start();

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

if (!isset($conn) || $conn->connect_error) {
    ob_clean();
    die(json_encode(['error' => 'Database connection failed']));
}

$sql = "SELECT product_id, product_name, product_description, COALESCE(stock_count, stock, 0) AS stock_count, 
        COALESCE(buy_price, price, 0.00) AS buy_price, COALESCE(rent_price, 0.00) AS rent_price, 
        product_category, product_image, barcode, COALESCE(product_status, 'available') AS product_status, 
        CASE WHEN COALESCE(stock_count, stock, 0) < 10 THEN 'Low Stock' ELSE 'In Stock' END AS status 
        FROM products";

$result = $conn->query($sql);
$products = [];

if ($result) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $conn->close();
} else {
    ob_clean();
    die(json_encode(['error' => 'Query execution failed: ' . $conn->error]));
}

ob_clean();
echo json_encode($products);
?>