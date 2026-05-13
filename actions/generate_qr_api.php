<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
// Allow students and staff to generate/view QR codes
requireAuth(['student', 'admincashier', 'superadmin', 'users']);

require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';

$data = $_GET['data'] ?? '';

if (empty($data)) {
    header("HTTP/1.0 400 Bad Request");
    exit("Data parameter is required");
}

// Output the QR code directly to the browser as a PNG
header('Content-Type: image/png');
QRcode::png($data, false, QR_ECLEVEL_L, 10, 2);