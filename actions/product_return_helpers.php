<?php
require_once __DIR__ . '/security.php';

function productReturnsDatabase(): mysqli {
    require_once __DIR__ . '/../database/migrations/MigrationManager.php';
    $conn = Database::getConnection();
    if ($conn->connect_error) {
        throw new RuntimeException('Database connection failed.');
    }

    (new MigrationManager())->run();
    $table = $conn->query("SHOW TABLES LIKE 'product_return_requests'");
    if (!$table || $table->num_rows === 0) {
        throw new RuntimeException('Product returns schema is unavailable.');
    }

    return $conn;
}

function requireProductReturnCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !validateCsrfToken($token)) {
        jsonResponse(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 403);
    }
}

function productStockColumn(mysqli $conn): string {
    $result = $conn->query("SHOW COLUMNS FROM `products` LIKE 'stock_count'");
    if (!$result) {
        throw new RuntimeException('Unable to inspect product inventory.');
    }
    if ($result->num_rows > 0) {
        return 'stock_count';
    }

    $result = $conn->query("SHOW COLUMNS FROM `products` LIKE 'stock'");
    if (!$result || $result->num_rows === 0) {
        throw new RuntimeException('Product inventory column is unavailable.');
    }
    return 'stock';
}
