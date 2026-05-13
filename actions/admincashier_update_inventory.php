<?php
// Force JSON output even if errors occur
header('Content-Type: application/json');
ini_set('display_errors', '0'); // Prevent HTML error output
ob_start(); // Buffer any accidental output

require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admin', 'admincashier', 'superadmin']);

try {
    require_once __DIR__ . '/../database/connection.php';
    $conn = Database::getConnection();

    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Check if product_id is provided
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    if (!$productId) {
        throw new Exception('Product ID is required for update.');
    }

    // Fetch current product data to get existing image path if no new image is uploaded
    $stmt = $conn->prepare("SELECT product_image FROM products WHERE product_id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentProduct = $result->fetch_assoc();
    $stmt->close();

    if (!$currentProduct) {
        throw new Exception('Product not found.');
    }

    $productImage = $currentProduct['product_image']; // Default to existing image

    // Handle image upload if a new file is provided
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        $uploadDir = __DIR__ . '/../assets/images/products/';
        
        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxFileSize = 5 * 1024 * 1024; // 5 MB

        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Only JPG, PNG, and GIF images are allowed.');
        }
        if ($file['size'] > $maxFileSize) {
            throw new Exception('File size exceeds the maximum limit of 5MB.');
        }

        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = uniqid('product_') . '.' . $fileExtension;
        $destination = $uploadDir . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Delete old image if it's not the default fallback and exists
            if ($productImage && $productImage !== 'assets/images/icons/granbylogo.png' && file_exists(__DIR__ . '/../' . $productImage)) {
                unlink(__DIR__ . '/../' . $productImage);
            }
            $productImage = 'assets/images/products/' . $newFileName; // Store relative path
        } else {
            throw new Exception('Failed to upload image.');
        }
    }

    // Sanitize and validate other product data
    $productName = filter_input(INPUT_POST, 'product_name', FILTER_SANITIZE_STRING);
    $productCategory = filter_input(INPUT_POST, 'product_category', FILTER_SANITIZE_STRING);
    $buyPrice = filter_input(INPUT_POST, 'buy_price', FILTER_VALIDATE_FLOAT);
    $rentPrice = filter_input(INPUT_POST, 'rent_price', FILTER_VALIDATE_FLOAT);
    $barcode = filter_input(INPUT_POST, 'barcode', FILTER_SANITIZE_STRING);
    $productStatus = filter_input(INPUT_POST, 'product_status', FILTER_SANITIZE_STRING);
    $stockCount = filter_input(INPUT_POST, 'stock_count', FILTER_VALIDATE_INT);

    if (!$productName || !$productCategory || $buyPrice === false || $rentPrice === false || !$barcode || !$productStatus || $stockCount === false) {
        throw new Exception('Invalid or missing product data.');
    }

    // Prepare the update statement
    $updateQuery = "UPDATE products SET 
                        product_name = ?, 
                        product_category = ?, 
                        buy_price = ?, 
                        rent_price = ?, 
                        barcode = ?, 
                        product_status = ?, 
                        stock_count = ?, 
                        product_image = ? 
                    WHERE product_id = ?";

    $stmt = $conn->prepare($updateQuery);
    if (!$stmt) {
        throw new Exception('Failed to prepare update statement: ' . $conn->error);
    }

    $stmt->bind_param(
        'ssddssisi',
        $productName,
        $productCategory,
        $buyPrice,
        $rentPrice,
        $barcode,
        $productStatus,
        $stockCount,
        $productImage,
        $productId
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to update product: ' . $stmt->error);
    }
    $stmt->close();

    // Fetch the updated product to return to the frontend
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $updatedProduct = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Product updated successfully.',
        'product' => $updatedProduct
    ]);

} catch (Exception $e) {
    ob_clean(); // Clear any buffered output before sending JSON error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>