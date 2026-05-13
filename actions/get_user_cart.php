<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';

secureSessionStart();
requireAuth(['student', 'users', 'admincashier']);
header('Content-Type: application/json');

$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id) {
    jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$createTableSql = "CREATE TABLE IF NOT EXISTS user_carts (
    student_id VARCHAR(50) NOT NULL,
    cart_items JSON NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createTableSql);

$stmt = $conn->prepare('SELECT cart_items FROM user_carts WHERE student_id = ? LIMIT 1');
$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();
$cart = [];
if ($result && $row = $result->fetch_assoc()) {
    $decoded = json_decode($row['cart_items'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $cart[] = [
                'product_id' => intval($item['product_id'] ?? 0),
                'product_name' => trim((string)($item['product_name'] ?? '')),
                'type' => in_array(strtolower((string)($item['type'] ?? '')), ['buy', 'rent'], true) ? strtolower((string)$item['type']) : 'buy',
                'quantity' => max(1, intval($item['quantity'] ?? 1)),
                'unit_price' => floatval($item['unit_price'] ?? 0),
                'stock' => intval($item['stock'] ?? 0),
            ];
        }
    }
}
$stmt->close();

jsonResponse(['success' => true, 'cart' => $cart]);