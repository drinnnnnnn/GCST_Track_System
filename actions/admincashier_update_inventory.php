﻿﻿﻿﻿﻿﻿﻿﻿﻿﻿<?php
// Force JSON output even if errors occur
header('Content-Type: application/json');
ini_set('display_errors', '0'); // Prevent HTML error output
ob_start(); // Buffer any accidental output

require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admin', 'admincashier', 'superadmin', 'cashier']);

try {
    require_once __DIR__ . '/../database/connection.php';
    require_once __DIR__ . '/../database/migrations/MigrationManager.php';
    $conn = Database::getConnection();

    // Check if product_id is provided early for migration checks
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    if (!$productId) {
        throw new Exception('Product ID is required for update.');
    }

    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Ensure schema is up to date
    (new MigrationManager())->run();

    // Backward compatibility: Rename category 'Fabric Uniform' to 'Uniform Fabrics'
    $catCheck = $conn->prepare("SELECT product_category FROM products WHERE product_id = ?");
    $catCheck->bind_param('i', $productId);
    $catCheck->execute();
    $catRes = $catCheck->get_result()->fetch_assoc();
    $catCheck->close();

    if ($catRes && $catRes['product_category'] === 'Fabric Uniform') {
        $stmtUpdate = $conn->prepare("UPDATE products SET product_category = 'Uniform Fabrics' WHERE product_id = ?");
        if ($stmtUpdate) {
            $stmtUpdate->bind_param('i', $productId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
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
            if ($productImage && $productImage !== 'assets/images/icons/granby_logo.png' && file_exists(__DIR__ . '/../' . $productImage)) {
                @unlink(__DIR__ . '/../' . $productImage);
            }
            $productImage = 'assets/images/products/' . $newFileName; // Store relative path
        } else {
            throw new Exception('Failed to upload image.');
        }
    }

    // Sanitize and validate other product data
    $productName = isset($_POST['product_name']) ? trim($_POST['product_name']) : null;
    $productCategory = isset($_POST['product_category']) ? trim($_POST['product_category']) : null;
    $buyPrice = filter_input(INPUT_POST, 'buy_price', FILTER_VALIDATE_FLOAT);
    $productStatus = isset($_POST['product_status']) ? trim($_POST['product_status']) : null;
    $stockCount = filter_input(INPUT_POST, 'stock_count', FILTER_VALIDATE_FLOAT);
    $isFeatured = filter_input(INPUT_POST, 'is_featured', FILTER_VALIDATE_INT) ?: 0;

    // Book-specific metadata
    $bookAuthor = isset($_POST['book_author']) ? trim($_POST['book_author']) : null;
    $bookPages = filter_input(INPUT_POST, 'book_pages', FILTER_VALIDATE_INT);
    $bookCourse = isset($_POST['book_course']) ? trim($_POST['book_course']) : null;
    $bookSubject = isset($_POST['book_subject']) ? trim($_POST['book_subject']) : null;
    // Note: book_author is handled correctly here and in create_product.php. Ensure get_admincashier_products.php also selects this field.
    $bookYear = filter_input(INPUT_POST, 'book_publication_year', FILTER_VALIDATE_INT);

    // Uniform-specific metadata
    $uniformCourse = isset($_POST['course_program']) ? trim($_POST['course_program']) : (isset($_POST['uniform_course']) ? trim($_POST['uniform_course']) : null);
    $uniformType = isset($_POST['uniform_type']) ? trim($_POST['uniform_type']) : null;
    $upperFabric = isset($_POST['uniform_upper_fabric']) ? trim($_POST['uniform_upper_fabric']) : null;
    $lowerFabric = isset($_POST['uniform_lower_fabric']) ? trim($_POST['uniform_lower_fabric']) : null;
    $materialType = isset($_POST['material_type']) ? trim($_POST['material_type']) : (isset($_POST['uniform_material']) ? trim($_POST['uniform_material']) : null);

    if (!$productName || !$productCategory || $buyPrice === false || !$productStatus || $stockCount === false) {
        throw new Exception('Invalid or missing product data.');
    }

    if ($stockCount < 0) throw new Exception('Available stock cannot be negative.');

    // 1. Data Normalization
    // Ensure numeric validation failures from filter_input result in database-safe nulls or defaults
    $bookPages = ($bookPages === false || $bookPages === null) ? null : (int)$bookPages;
    $bookYear = ($bookYear === false || $bookYear === null) ? null : (int)$bookYear;

    // 2. Metadata Integrity: Clear irrelevant fields based on the selected category
    // This prevents "ghost data" if a product is moved between modules (e.g. Book to Fabric)
    if ($productCategory !== 'Books') {
        $bookAuthor = $bookPages = $bookCourse = $bookSubject = $bookYear = null;
    }
    
    if ($productCategory !== 'Uniform Fabrics') {
        $uniformCourse = $uniformType = $upperFabric = $lowerFabric = $materialType = null;
    }

    // 3. Module-Specific Validation
    $validCourses = [
        'BS Information Technology', 
        'BS Computer Science', 
        'BS Tourism Management', 
        'BS Business Administration', 
        'B Elementary Education', 
        'B Secondary Education', 
        'BS Criminology', 
        'BS Accountancy'
    ];

    if ($productCategory === 'Books') {
        if (empty($bookAuthor)) throw new Exception('Book Author is required.');
        if (!$bookPages || $bookPages <= 0) throw new Exception('A valid page count is required.');
        if (empty($bookCourse)) throw new Exception('Applicable course/program is required.');
        if (!in_array($bookCourse, $validCourses)) throw new Exception('Books Module: Please select a valid course/program from the whitelist.');
        
        if ($bookYear !== null && $bookYear !== false) {
            if ($bookYear < 1000 || $bookYear > (int)date('Y') + 5) throw new Exception('Invalid publication year.');
        }
    } elseif ($productCategory === 'Uniform Fabrics') {
        if (empty($uniformCourse)) throw new Exception('Applicable Course/Program is required.');
        if (!in_array($uniformCourse, $validCourses)) throw new Exception('Fabrics Module: Please select a valid course/program from the whitelist.');
        if (empty($uniformType)) throw new Exception('Uniform Type is required.');

        if (empty($materialType)) {
            throw new Exception('Fabrics Module: Material Type is required.');
        }
        
        if ($uniformType === 'Complete Uniform Set') {
            if (empty($upperFabric) || empty($lowerFabric)) throw new Exception('Fabric Combination details (Upper & Lower) are required for complete sets.');
        }
    }

    // Prepare the update statement
    $updateQuery = "UPDATE products SET 
                        product_name = ?, 
                        product_category = ?, 
                        buy_price = ?, 
                        product_status = ?, 
                        stock_count = ?, 
                        is_featured = ?,
                        product_image = ?,
                        book_author = ?,
                        book_pages = ?,
                        book_course = ?,
                        book_subject = ?,
                        book_publication_year = ?,
                        uniform_course = ?,
                        uniform_type = ?,
                        uniform_upper_fabric = ?,
                        uniform_lower_fabric = ?,
                        uniform_material = ?
                    WHERE product_id = ?";

    $stmt = $conn->prepare($updateQuery);
    if (!$stmt) {
        throw new Exception('SQL Preparation Error: ' . $conn->error);
    }

    // Strictly synchronized type string and variable list (18 parameters):
    // ssdsdis (7) + sis s (4) + i (1) + sssss (5) + i (1) = 18
    $stmt->bind_param(
        'ssdsdissississsssi', 
        $productName,
        $productCategory,
        $buyPrice,
        $productStatus,
        $stockCount,
        $isFeatured,
        $productImage,
        $bookAuthor,
        $bookPages,
        $bookCourse,
        $bookSubject,
        $bookYear,
        $uniformCourse,
        $uniformType,
        $upperFabric,
        $lowerFabric,
        $materialType,
        $productId
    );

    if (!$stmt->execute()) {
        error_log("Database Error (Product Update ID $productId): " . $stmt->error);
        throw new Exception('Failed to update product: ' . $stmt->error);
    }
    $stmt->close();

    // Fetch the updated product to return to the frontend
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $updatedProduct = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Ensure data is UTF-8 encoded to prevent json_encode failure
    if ($updatedProduct) {
        // Robust aliasing for frontend consistency across different load sources
        $updatedProduct['course_program'] = $updatedProduct['uniform_course'] ?? $updatedProduct['course_program'] ?? null;
        $updatedProduct['material_type'] = $updatedProduct['uniform_material'] ?? $updatedProduct['material_type'] ?? null;
        
        array_walk_recursive($updatedProduct, function(&$item) {
            if (is_string($item)) $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
        });
    }

    $json = json_encode([
        'success' => true,
        'message' => 'Product updated successfully.',
        'product' => $updatedProduct
    ]);

    if ($json === false) throw new Exception('JSON Encoding Error: ' . json_last_error_msg());

    // Standardized successful response
    if (ob_get_length()) ob_clean(); 
    echo $json;
    exit;

} catch (Throwable $e) {
    error_log("Inventory Update Exception: " . $e->getMessage());
    if (ob_get_length()) ob_clean(); // Clear any buffered output before sending JSON error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>