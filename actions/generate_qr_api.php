<?php
// actions/generate_qr_api.php
if (!file_exists(__DIR__ . '/../vendor/phpqrcode/qrlib.php')) {
    header('Content-Type: text/plain');
    die("Error: QR Library missing in vendor folder.");
}
require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';

$data = isset($_GET['data']) ? $_GET['data'] : 'No Data';

// Output the QR code directly to the browser
header('Content-Type: image/png');
QRcode::png($data, false, 'H', 8, 2);
exit;