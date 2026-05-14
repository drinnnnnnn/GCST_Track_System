<?php
// actions/qr_code_generator.php

$libDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'phpqrcode';
$libPath = $libDir . DIRECTORY_SEPARATOR . 'qrlib.php';

if (file_exists($libPath)) {
    // Add the library directory to the include path so qrlib.php can find its dependencies
    set_include_path(get_include_path() . PATH_SEPARATOR . $libDir);
    require_once $libPath;
}

/**
 * Generates a QR code image locally and saves it to a temporary file.
 *
 * @param string $data The data to encode in the QR code (e.g., transaction number).
 * @param string $filename The full path where the QR code image should be saved.
 * @param int $ecc Error correction level (L, M, Q, H). Default 'L'.
 * @param int $pixelSize Size of each pixel in the QR code. Default 10.
 * @param int $frameSize Size of the white border around the QR code. Default 4.
 * @return bool True on success, false on failure.
 */
function generateLocalQrCode($data, $filename, $ecc = 'L', $pixelSize = 10, $frameSize = 4) {
    if (!extension_loaded('gd')) {
        error_log("PHP GD extension is not loaded. QR code generation requires GD.");
        return false;
    }
    // Basic check for library integrity: qrlib.php needs other files in its directory
    $toolsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'phpqrcode' . DIRECTORY_SEPARATOR . 'qrtools.php';
    if (!file_exists($toolsPath)) {
        error_log("Incomplete QR Library: 'qrtools.php' is missing at $toolsPath. Please copy the full phpqrcode folder content.");
        return false;
    }
    if (!class_exists('QRcode')) {
        $mainPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'phpqrcode' . DIRECTORY_SEPARATOR . 'qrlib.php';
        error_log("QRcode class not found after requiring $mainPath. File exists: " . (file_exists($mainPath) ? 'Yes' : 'No') . ". Include path: " . get_include_path());
        // This might indicate an issue within qrlib.php itself or its dependencies.
        return false;
    }
    try {
        // Ensure the directory exists
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                error_log("Failed to create directory for QR code: " . $dir);
                return false;
            }
        }
        
        QRcode::png($data, $filename, $ecc, $pixelSize, $frameSize);
        if (file_exists($filename)) {
            return true; // Verify file creation
        } else {
            error_log("QRcode::png did not create file at: " . $filename);
            return false;
        }
    } catch (Exception $e) {
        error_log("QR Code generation failed: " . $e->getMessage());
        return false;
    }
}
?>