<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admin', 'admincashier', 'superadmin', 'cashier']);

try {
    require_once __DIR__ . '/../database/connection.php';
    $conn = Database::getConnection();

    if ($conn->connect_error) {
        throw new Exception('Database connection failed.');
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $payload = (strpos($contentType, 'application/json') !== false)
        ? json_decode(file_get_contents('php://input'), true)
        : $_POST;

    $productId = isset($payload['product_id']) ? intval($payload['product_id']) : 0;
    if ($productId <= 0) {
        throw new Exception('Invalid product ID.');
    }

    $stmt = $conn->prepare('DELETE FROM products WHERE product_id = ?');
    if (!$stmt) {
        throw new Exception('Failed to prepare delete statement.');
    }

    $stmt->bind_param('i', $productId);
    if (!$stmt->execute()) {
        throw new Exception('Unable to delete product: ' . $stmt->error);
    }

    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$deleted) {
        throw new Exception('No product was deleted.');
    }

    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true, 'message' => 'Product deleted successfully.']);
    exit;
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
