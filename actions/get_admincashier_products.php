<?php
// Prevent HTML error output from corrupting the JSON response
ini_set('display_errors', '0');
if (ob_get_level() == 0) ob_start();

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/security.php';
    secureSessionStart();
    requireAuth(['admin', 'admincashier', 'superadmin', 'cashier']);

    require_once __DIR__ . '/../config/db_connect.php';

    if (!isset($conn) || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }

$sql = "SELECT product_id, product_name, product_description, product_category, product_image, barcode, is_featured,
        COALESCE(stock_count, stock, 0) AS stock_count, 
        COALESCE(buy_price, price, 0.00) AS buy_price, 
        COALESCE(rent_price, 0.00) AS rent_price, 
        COALESCE(product_status, 'available') AS product_status, 
        book_author, book_pages, book_course, book_subject, book_edition, book_publisher, book_isbn, book_publication_year,
        uniform_course, uniform_type, uniform_upper_fabric, uniform_lower_fabric, uniform_material,
        uniform_upper_fabric AS upperFabric,
        uniform_lower_fabric AS lowerFabric,
        uniform_course AS course_program, 
        uniform_material AS material_type,
        CASE WHEN COALESCE(stock_count, stock, 0) < 10 THEN 'Low Stock' ELSE 'In Stock' END AS status 
        FROM products";

$result = $conn->query($sql);
$products = [];

if ($result) {
    while($row = $result->fetch_assoc()) {
        // Ensure course_program and material_type are always present for frontend consistency
        $row['course_program'] = $row['course_program'] ?? $row['uniform_course'] ?? null;
        $row['material_type'] = $row['material_type'] ?? $row['uniform_material'] ?? null;
        $row['upperFabric'] = $row['upperFabric'] ?? $row['uniform_upper_fabric'] ?? null;
        $row['lowerFabric'] = $row['lowerFabric'] ?? $row['uniform_lower_fabric'] ?? null;
        $products[] = $row;
    }
} else {
    throw new Exception('Query execution failed: ' . $conn->error);
}

// Ensure data is UTF-8 encoded to prevent json_encode failure
array_walk_recursive($products, function(&$item) {
    if (is_string($item)) $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
});

$json = json_encode($products);
if ($json === false) {
    throw new Exception('JSON Encoding Error: ' . json_last_error_msg());
}

// Ensure the buffer is cleared of any whitespace or notices before outputting JSON
if (ob_get_length()) ob_clean();
echo $json;
exit;

} catch (Throwable $e) {
    if (ob_get_length()) ob_clean(); 
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
} finally {
    if (isset($conn)) $conn->close();
}
?>