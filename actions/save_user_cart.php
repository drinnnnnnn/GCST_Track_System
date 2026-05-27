<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/audit_helpers.php'; // Include audit logging helper

secureSessionStart();
requireAuth(['student', 'admincashier', 'superadmin']);
header('Content-Type: application/json');

$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id) {
    jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    jsonResponse(['success' => false, 'message' => 'Invalid request payload.'], 400);
}

$items = $payload['items'] ?? [];
if (!is_array($items)) {
    jsonResponse(['success' => false, 'message' => 'Invalid cart format.'], 400);
}

$validItems = [];
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }

    $productId = intval($item['product_id'] ?? 0);
    $type = strtolower(trim((string)($item['type'] ?? '')));
    $quantity = intval($item['quantity'] ?? 0);
    $unitPrice = floatval($item['unitPrice'] ?? $item['unit_price'] ?? 0);
    $productName = trim((string)($item['product_name'] ?? ''));
    $productCategory = trim((string)($item['product_category'] ?? ''));
    $productImage = trim((string)($item['product_image'] ?? ''));
    $stock = intval($item['stock'] ?? 0);
    $selected = filter_var($item['selected'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $duration = intval($item['duration'] ?? 1);
    $durationUnit = trim((string)($item['duration_unit'] ?? $item['durationUnit'] ?? 'days'));

    if ($productId <= 0 || $quantity <= 0 || !in_array($type, ['buy', 'rent'], true) || $unitPrice <= 0) {
        continue;
    }

    $validItems[] = [
        'product_id' => $productId,
        'product_name' => $productName,
        'product_category' => $productCategory,
        'product_image' => $productImage,
        'type' => $type,
        'quantity' => $quantity,
        'unit_price' => round($unitPrice, 2),
        'stock' => $stock,
        'selected' => $selected,
        'duration' => $duration,
        'durationUnit' => $durationUnit
    ];
}

$createTableSql = "CREATE TABLE IF NOT EXISTS user_carts (
    student_id VARCHAR(50) NOT NULL,
    cart_items JSON NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createTableSql);

$cartJson = json_encode($validItems, JSON_UNESCAPED_UNICODE);
$stmt = $conn->prepare('INSERT INTO user_carts (student_id, cart_items) VALUES (?, ?) ON DUPLICATE KEY UPDATE cart_items = VALUES(cart_items)');
$stmt->bind_param('ss', $student_id, $cartJson);
$success = $stmt->execute();
$stmt->close();

if (!$success) {
    jsonResponse(['success' => false, 'message' => 'Unable to save cart.'], 500);
}

logAudit($conn, 'student', $student_id, 'save_user_cart', 'Updated shopping cart');
jsonResponse(['success' => true, 'message' => 'Cart saved successfully.']);
