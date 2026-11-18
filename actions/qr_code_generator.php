<?php
// actions/qr_code_generator.php

$libDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'phpqrcode';
$libPath = $libDir . DIRECTORY_SEPARATOR . 'qrlib.php';

if (file_exists($libPath) && extension_loaded('gd')) {
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
        return generateLocalQrCodeWithoutGd($data, $filename, $ecc, $pixelSize, $frameSize);
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

function generateLocalQrCodeWithoutGd($data, $filename, $ecc, $pixelSize, $frameSize) {
    $barcodePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor'
        . DIRECTORY_SEPARATOR . 'tecnickcom' . DIRECTORY_SEPARATOR . 'tcpdf'
        . DIRECTORY_SEPARATOR . 'tcpdf_barcodes_2d.php';
    if (!is_file($barcodePath) || !function_exists('gzcompress')) {
        error_log('Local QR generation requires the TCPDF barcode library and zlib when GD is unavailable.');
        return false;
    }

    $ecc = strtoupper((string)$ecc);
    if (!in_array($ecc, ['L', 'M', 'Q', 'H'], true)) {
        error_log('Unsupported QR error correction level.');
        return false;
    }

    try {
        require_once $barcodePath;
        $barcode = new TCPDF2DBarcode((string)$data, 'QRCODE,' . $ecc);
        $matrix = $barcode->getBarcodeArray();
        if (empty($matrix['bcode']) || empty($matrix['num_rows']) || empty($matrix['num_cols'])) {
            throw new RuntimeException('The QR code matrix could not be generated.');
        }

        $scale = max(1, (int)$pixelSize);
        $quietZone = max(4, (int)$frameSize);
        $dimension = ((int)$matrix['num_rows'] + ($quietZone * 2)) * $scale;
        $rawImage = '';
        for ($y = 0; $y < $dimension; $y++) {
            $row = "\0";
            $moduleY = intdiv($y, $scale) - $quietZone;
            for ($x = 0; $x < $dimension; $x++) {
                $moduleX = intdiv($x, $scale) - $quietZone;
                $isBlack = $moduleY >= 0
                    && $moduleY < $matrix['num_rows']
                    && $moduleX >= 0
                    && $moduleX < $matrix['num_cols']
                    && $matrix['bcode'][$moduleY][$moduleX] === 1;
                $row .= $isBlack ? "\0" : "\xff";
            }
            $rawImage .= $row;
        }

        $compressedImage = gzcompress($rawImage, 6);
        if ($compressedImage === false) {
            throw new RuntimeException('The QR code image could not be compressed.');
        }
        $pngChunk = static function ($type, $content) {
            $crc = crc32($type . $content);
            return pack('N', strlen($content)) . $type . $content . pack('N', $crc);
        };
        $png = "\x89PNG\r\n\x1a\n"
            . $pngChunk('IHDR', pack('N2C5', $dimension, $dimension, 8, 0, 0, 0, 0))
            . $pngChunk('IDAT', $compressedImage)
            . $pngChunk('IEND', '');

        $directory = dirname($filename);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the QR code directory.');
        }
        $written = file_put_contents($filename, $png, LOCK_EX);
        return $written === strlen($png);
    } catch (Throwable $e) {
        error_log('Local QR code generation failed: ' . $e->getMessage());
        return false;
    }
}
?>