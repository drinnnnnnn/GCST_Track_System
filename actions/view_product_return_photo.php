<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'user', 'admin', 'cashier', 'admincashier', 'superadmin']);
require_once __DIR__ . '/product_return_helpers.php';

try {
    $requestId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$requestId || $requestId <= 0) {
        http_response_code(400);
        exit;
    }

    $conn = productReturnsDatabase();
    $stmt = $conn->prepare('SELECT user_id, photo_filename FROM product_return_requests WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare product return photo lookup.');
    }
    $stmt->bind_param('i', $requestId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Unable to load product return photo.');
    }
    $returnRequest = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$returnRequest) {
        http_response_code(404);
        exit;
    }

    $isCashier = in_array($_SESSION['role'], ['admin', 'cashier', 'admincashier', 'superadmin'], true);
    if (!$isCashier && (int)$returnRequest['user_id'] !== (int)($_SESSION['user_id'] ?? 0)) {
        http_response_code(403);
        exit;
    }

    $filename = (string)$returnRequest['photo_filename'];
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
        throw new RuntimeException('Invalid product return photo reference.');
    }
    $photoPath = realpath(__DIR__ . '/../uploads/product_returns/' . $filename);
    $photoDirectory = realpath(__DIR__ . '/../uploads/product_returns');
    if (!$photoPath || !$photoDirectory || dirname($photoPath) !== $photoDirectory || !is_file($photoPath) || !is_readable($photoPath)) {
        http_response_code(404);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($photoPath);
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Stored product return photo is not a supported image.');
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($photoPath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($photoPath);
} catch (Throwable $e) {
    error_log('Product return photo lookup failed: ' . $e->getMessage());
    http_response_code(500);
}
