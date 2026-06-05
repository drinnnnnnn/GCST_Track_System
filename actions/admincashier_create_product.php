<?php
/**
 * admincashier_create_product.php
 * Consolidated backend for creating products with category-specific validation.
 * Supports Uniform Fabrics, Books, and General Inventory items.
 */

header('Content-Type: application/json');
ini_set('display_errors', '0'); // Prevent output corruption
ob_start();

require_once __DIR__ . '/security.php';
secureSessionStart();

// Ensure authorized personnel only
requireAuth(['admin', 'admincashier', 'superadmin', 'cashier']);

try {
    require_once __DIR__ . '/../database/connection.php';
    require_once __DIR__ . '/../database/migrations/MigrationManager.php';
    $conn = Database::getConnection();

    if ($conn->connect_error) {
        throw new Exception('System Error: Unable to establish a database connection.');
    }

    // Centralized Migration Check
    (new MigrationManager())->run();

    // 1. Core Field Normalization (Applicable to all categories)
    $productName = isset($_POST['product_name']) ? trim($_POST['product_name']) : null;
    $productCategory = isset($_POST['product_category']) ? trim($_POST['product_category']) : null;
    $buyPrice = filter_input(INPUT_POST, 'buy_price', FILTER_VALIDATE_FLOAT);
    $stockCount = filter_input(INPUT_POST, 'stock_count', FILTER_VALIDATE_FLOAT);
    $isFeatured = filter_input(INPUT_POST, 'is_featured', FILTER_VALIDATE_INT) ?: 0;
    $productStatus = 'available'; // Default status for new items

    // Book-specific metadata
    $bookAuthor = isset($_POST['book_author']) ? trim($_POST['book_author']) : null;
    $bookPages = filter_input(INPUT_POST, 'book_pages', FILTER_VALIDATE_INT);
    $bookCourse = isset($_POST['book_course']) ? trim($_POST['book_course']) : null;
    $bookSubject = isset($_POST['book_subject']) ? trim($_POST['book_subject']) : null;
    $bookEdition = isset($_POST['book_edition']) ? trim($_POST['book_edition']) : null;
    $bookPublisher = isset($_POST['book_publisher']) ? trim($_POST['book_publisher']) : null;
    $bookIsbn = isset($_POST['book_isbn']) ? trim($_POST['book_isbn']) : null;
    $bookYear = filter_input(INPUT_POST, 'book_publication_year', FILTER_VALIDATE_INT);

    // Uniform-specific metadata
    $uniformCourse = isset($_POST['uniform_course']) ? trim($_POST['uniform_course']) : null;
    $uniformType = isset($_POST['uniform_type']) ? trim($_POST['uniform_type']) : null;
    $upperFabric = isset($_POST['uniform_upper_fabric']) ? trim($_POST['uniform_upper_fabric']) : null;
    $lowerFabric = isset($_POST['uniform_lower_fabric']) ? trim($_POST['uniform_lower_fabric']) : null;
    $minYards = filter_input(INPUT_POST, 'uniform_min_yards', FILTER_VALIDATE_FLOAT);
    $materialType = isset($_POST['uniform_material']) ? trim($_POST['uniform_material']) : null;

    // Primary Validation
    if (empty($productName)) throw new Exception('Validation Error: Product Name is required.');
    if (empty($productCategory)) throw new Exception('Validation Error: Product Category is required.');
    if ($buyPrice === false || $buyPrice <= 0) throw new Exception('Validation Error: A valid price greater than zero is required.');
    if ($stockCount === false || $stockCount < 0) throw new Exception('Validation Error: Valid stock quantity is required.');

    if ($stockCount < 0) throw new Exception('Available stock cannot be negative.');

    // 2. Duplicate Prevention Logic
    // Checks for identical Name + Category to prevent catalog pollution
    $checkDup = $conn->prepare("SELECT 1 FROM products WHERE product_name = ? AND product_category = ? LIMIT 1");
    $checkDup->bind_param('ss', $productName, $productCategory);
    $checkDup->execute();
    if ($checkDup->get_result()->num_rows > 0) {
        $checkDup->close();
        throw new Exception("Conflict: A product named '$productName' already exists within the '$productCategory' category.");
    }
    $checkDup->close();

    // 3. Data Normalization
    // Ensure numeric validation failures from filter_input result in database-safe nulls or defaults
    $bookPages = ($bookPages === false || $bookPages === null) ? null : (int)$bookPages;
    $bookYear = ($bookYear === false || $bookYear === null) ? null : (int)$bookYear;
    $minYards = ($minYards === false || $minYards === null) ? 0.00 : (float)$minYards;

    // 4. Metadata Integrity: Clear irrelevant fields based on the selected category
    if ($productCategory !== 'Books') {
        $bookAuthor = $bookPages = $bookCourse = $bookSubject = $bookEdition = $bookPublisher = $bookIsbn = $bookYear = null;
    }
    if ($productCategory !== 'Uniform Fabrics') {
        $uniformCourse = $uniformType = $upperFabric = $lowerFabric = $materialType = null;
        $minYards = 0.00;
    }

    // 5. Module-Specific Validation
    if ($productCategory === 'Books') {
        if (empty($bookAuthor)) throw new Exception('Books Module: Author is required.');
        if (!$bookPages || $bookPages <= 0) throw new Exception('Books Module: Valid page count is required.');
        if (empty($bookCourse)) throw new Exception('Books Module: Course applicability is required.');

        if ($bookYear !== null && $bookYear !== false) {
            if ($bookYear < 1000 || $bookYear > (int)date('Y') + 5) throw new Exception('Invalid publication year.');
        }

        // Uniqueness check for ISBN
        if (!empty($bookIsbn)) {
            $isbnCheck = $conn->prepare("SELECT 1 FROM products WHERE book_isbn = ? LIMIT 1");
            $isbnCheck->bind_param('s', $bookIsbn);
            $isbnCheck->execute();
            if ($isbnCheck->get_result()->num_rows > 0) {
                $isbnCheck->close();
                throw new Exception('Conflict: The provided ISBN is already registered to another book.');
            }
            $isbnCheck->close();
        }
    } elseif ($productCategory === 'Uniform Fabrics') {
        if (empty($uniformCourse)) throw new Exception('Fabrics Module: Course/Program association is required.');
        if (empty($uniformType)) throw new Exception('Fabrics Module: Uniform Type is required.');
        
        if ($minYards <= 0) {
            throw new Exception('Fabrics Module: A valid Min. Yards per Set (> 0) is required.');
        }
        if (empty($materialType)) {
            throw new Exception('Fabrics Module: Material Type is required.');
        }

        if ($stockCount <= 0) throw new Exception('Fabrics Module: Available yards must be greater than zero.');
        
        if ($uniformType === 'Complete Uniform Set') {
            if (empty($upperFabric) || empty($lowerFabric)) {
                throw new Exception('Fabrics Module: Upper and Lower fabric details are required for complete sets.');
            }
        }
    }

    // 6. Secure Image Upload Processing
    $productImage = 'assets/images/icons/granbylogo.png'; // Default placeholder
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        $uploadDir = __DIR__ . '/../assets/images/products/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $allowedMime = ['image/jpeg', 'image/png', 'image/gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $actualMime = $finfo->file($file['tmp_name']);

        if (!in_array($actualMime, $allowedMime)) throw new Exception('Security Alert: Invalid image format. Use JPG, PNG or GIF.');
        if ($file['size'] > 5 * 1024 * 1024) throw new Exception('Upload Error: Image size cannot exceed 5MB.');

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = 'prod_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $safeName)) {
            $productImage = 'assets/images/products/' . $safeName;
        } else {
            throw new Exception('System Error: Failed to save uploaded image.');
        }
    }

    // 7. Atomic Insertion
    $sql = "INSERT INTO products (
        product_name, product_category, buy_price, product_status, stock_count, is_featured, product_image,
        book_author, book_pages, book_course, book_subject, book_edition, book_publisher, book_isbn, book_publication_year,
        uniform_course, uniform_type, uniform_upper_fabric, uniform_lower_fabric, uniform_min_yards, uniform_material
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Database Error: Preparation failed - ' . $conn->error);

    // Strictly synchronized type string (21 parameters):
    // ssdsdis (7) + si (2) + sssssi (6) + ssssds (6) = 21
    $stmt->bind_param(
        'ssdsdississsssissssds',
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
        $bookEdition,
        $bookPublisher,
        $bookIsbn,
        $bookYear,
        $uniformCourse,
        $uniformType,
        $upperFabric,
        $lowerFabric,
        $minYards,
        $materialType
    );

    if (!$stmt->execute()) throw new Exception('Database Error: Execution failed during product creation.');
    
    $newId = $conn->insert_id;
    $stmt->close();

    // Fetch newly created record for frontend synchronization
    $finalFetch = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $finalFetch->bind_param('i', $newId);
    $finalFetch->execute();
    $productData = $finalFetch->get_result()->fetch_assoc();
    $finalFetch->close();

    // Ensure data is UTF-8 encoded to prevent json_encode failure
    if ($productData) {
        array_walk_recursive($productData, function(&$item) {
            if (is_string($item)) $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
        });
    }

    $json = json_encode(['success' => true, 'message' => 'Product catalog updated successfully.', 'product' => $productData]);
    if ($json === false) throw new Exception('JSON Encoding Error: ' . json_last_error_msg());

    if (ob_get_length()) ob_clean();
    echo $json;
    exit;

} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}