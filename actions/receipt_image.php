<?php

function findReceiptImageFont(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function loadReceiptImageSignature(string $signaturePath, string $projectRoot)
{
    $relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($signaturePath)), DIRECTORY_SEPARATOR);
    if ($relativePath === '') {
        return null;
    }

    $fullPath = realpath($projectRoot . DIRECTORY_SEPARATOR . $relativePath);
    $projectPrefix = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR;
    if (
        !$fullPath
        || stripos($fullPath, $projectPrefix) !== 0
        || !is_file($fullPath)
        || !is_readable($fullPath)
    ) {
        return null;
    }

    $imageInfo = @getimagesize($fullPath);
    if (!$imageInfo || !in_array($imageInfo['mime'] ?? '', ['image/png', 'image/jpeg'], true)) {
        return null;
    }

    $contents = @file_get_contents($fullPath);
    if ($contents === false) {
        return null;
    }

    $image = @imagecreatefromstring($contents);
    return $image === false ? null : $image;
}

function wrapReceiptImageText(string $text, int $fontSize, int $maxWidth, string $font): array
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 500) {
        $text = mb_substr($text, 0, 497, 'UTF-8') . '...';
    }
    if ($text === '') {
        return ['N/A'];
    }

    $lines = [];
    $line = '';
    foreach (explode(' ', $text) as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        $bounds = imagettfbbox($fontSize, 0, $font, $candidate);
        $width = abs($bounds[2] - $bounds[0]);
        if ($line !== '' && $width > $maxWidth) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }
    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines;
}

function generateReceiptPng(array $receipt): array
{
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('The GD extension is not loaded by the web PHP runtime. Enable GD in Apache PHP and restart Apache.');
    }
    if (!function_exists('imagettftext') || !function_exists('imagettfbbox')) {
        throw new RuntimeException('The web PHP GD extension does not include FreeType/TrueType support. Enable FreeType support for Apache PHP and restart Apache.');
    }

    $regularFont = findReceiptImageFont([
        getenv('GCST_RECEIPT_FONT') ?: '',
        'C:\\Windows\\Fonts\\arial.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
    ]);
    $boldFont = findReceiptImageFont([
        getenv('GCST_RECEIPT_BOLD_FONT') ?: '',
        'C:\\Windows\\Fonts\\arialbd.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
    ]);
    if ($regularFont === null || $boldFont === null) {
        throw new RuntimeException('A readable TrueType font is required to generate receipt images.');
    }

    $projectRoot = realpath(__DIR__ . '/..');
    if ($projectRoot === false) {
        throw new RuntimeException('Unable to resolve the project directory for receipt images.');
    }
    $logoPath = $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images'
        . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . 'granby_logo.png';
    $logo = @imagecreatefrompng($logoPath);
    if ($logo === false) {
        throw new RuntimeException('Unable to load the Granby logo for the receipt image.');
    }

    $signaturePath = trim((string)($receipt['signature_image'] ?? ''));
    $signatureImage = $signaturePath !== ''
        ? loadReceiptImageSignature($signaturePath, $projectRoot)
        : null;
    $signatureError = $signaturePath !== '' && $signatureImage === null
        ? 'The assigned cashier signature could not be loaded and was omitted.'
        : null;

    $referenceNumber = trim((string)($receipt['receipt_number'] ?? '')) ?: (string)$receipt['transaction_number'];
    $category = trim((string)($receipt['receipt_category'] ?? '')) ?: 'Payment Receipt';
    $date = date('F j, Y g:i A', strtotime((string)$receipt['created_at']));
    $paidAmount = 'PHP ' . number_format((float)($receipt['amount_paid'] ?? $receipt['total_amount'] ?? 0), 2);
    $status = ucwords(str_replace('_', ' ', (string)($receipt['payment_status'] ?? '')));

    $detailRows = [
        ['Receipt / Reference No.', $referenceNumber],
        ['Transaction No.', (string)($receipt['transaction_number'] ?? '')],
        ['Date', $date],
        ['Student Name', (string)($receipt['student_name'] ?? '')],
        ['Student ID', (string)($receipt['student_id'] ?? '')],
        ['Receipt Type', $category],
        ['Payment Method', (string)($receipt['payment_method'] ?? '')],
        ['Payment Type', (string)($receipt['payment_type'] ?? '')],
        ['Status', $status],
    ];
    if (!empty($receipt['student_year_level'])) {
        $detailRows[] = ['Year Level', (string)$receipt['student_year_level']];
    }
    if (!empty($receipt['student_semester'])) {
        $detailRows[] = ['Semester', (string)$receipt['student_semester']];
    }
    if (!empty($receipt['check_number'])) {
        $detailRows[] = ['Check No.', (string)$receipt['check_number']];
    }
    $itemRows = [];
    if (!empty($receipt['items']) && is_array($receipt['items'])) {
        foreach ($receipt['items'] as $index => $item) {
            $itemName = trim((string)($item['display_name'] ?? $item['product_name'] ?? 'Item'));
            $quantity = (float)($item['quantity'] ?? 0);
            $unitName = trim((string)($item['unit_name'] ?? ''));
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $itemTotal = (float)($item['total'] ?? 0);
            $quantityText = rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
            $quantityText .= $unitName !== '' ? ' ' . $unitName : '';
            $itemRows[] = [
                'Item ' . ($index + 1),
                $itemName . ' - ' . $quantityText . ' @ PHP ' . number_format($unitPrice, 2) . ' = PHP ' . number_format($itemTotal, 2),
            ];
        }
    }

    $paymentRows = [];
    if (isset($receipt['total_payment']) && (float)$receipt['total_payment'] !== (float)($receipt['amount_paid'] ?? 0)) {
        $paymentRows[] = ['Total Payment / Amount Due', 'PHP ' . number_format((float)$receipt['total_payment'], 2)];
    }
    if (isset($receipt['balance'])) {
        $paymentRows[] = ['Remaining Balance', 'PHP ' . number_format((float)$receipt['balance'], 2)];
    }
    if (!empty($receipt['or_number'])) {
        $detailRows[] = ['Official Receipt No.', (string)$receipt['or_number']];
    }
    if (!empty($receipt['remarks'])) {
        $detailRows[] = ['Remarks', (string)$receipt['remarks']];
    }
    if (!empty($receipt['note'])) {
        $detailRows[] = ['Note', (string)$receipt['note']];
    }
    if (!empty($receipt['authorized_rep'])) {
        $detailRows[] = ['Authorized Representative', (string)$receipt['authorized_rep']];
    }
    if (!empty($receipt['cashier_name'])) {
        $detailRows[] = ['Processed By', (string)$receipt['cashier_name']];
    }
    if (isset($receipt['subtotal'])) {
        $paymentRows[] = ['Subtotal', 'PHP ' . number_format((float)$receipt['subtotal'], 2)];
    }
    if (isset($receipt['discount_amount']) && (float)$receipt['discount_amount'] > 0) {
        $discountLabel = 'Discount';
        if (isset($receipt['discount_percent'])) {
            $discountLabel .= ' (' . number_format((float)$receipt['discount_percent'], 0) . '%)';
        }
        $paymentRows[] = [$discountLabel, '-PHP ' . number_format((float)$receipt['discount_amount'], 2)];
    }
    if (isset($receipt['payment_received'])) {
        $paymentRows[] = ['Cash Received', 'PHP ' . number_format((float)$receipt['payment_received'], 2)];
    }
    if (isset($receipt['change_amount'])) {
        $paymentRows[] = ['Change', 'PHP ' . number_format((float)$receipt['change_amount'], 2)];
    }

    $sections = [
        ['RECEIPT DETAILS', $detailRows],
    ];
    if ($itemRows) {
        $sections[] = ['PURCHASED ITEMS', $itemRows];
    }
    if ($paymentRows) {
        $sections[] = ['PAYMENT SUMMARY', $paymentRows];
    }

    $width = 1200;
    $padding = 80;
    $labelX = 100;
    $valueX = 455;
    $valueWidth = $width - $valueX - $padding;
    $rowFontSize = 24;
    $lineHeight = 38;
    $preparedSections = [];
    $height = 560;

    foreach ($sections as [$sectionTitle, $sectionRows]) {
        $preparedRows = [];
        $sectionHeight = 70;
        foreach ($sectionRows as [$label, $value]) {
            $labelLines = wrapReceiptImageText($label, 20, $valueX - $labelX - 30, $boldFont);
            $lines = wrapReceiptImageText($value !== '' ? $value : 'N/A', $rowFontSize, $valueWidth, $regularFont);
            $rowHeight = max(76, max(count($labelLines), count($lines)) * $lineHeight + 32);
            $preparedRows[] = [$labelLines, $lines, $rowHeight];
            $sectionHeight += $rowHeight;
        }
        $preparedSections[] = [$sectionTitle, $preparedRows];
        $height += $sectionHeight;
    }
    $height += 140 + ($signatureImage !== null ? 160 : 0);

    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        imagedestroy($logo);
        if ($signatureImage !== null) {
            imagedestroy($signatureImage);
        }
        throw new RuntimeException('Unable to allocate receipt image.');
    }

    $white = imagecolorallocate($image, 255, 255, 255);
    $navy = imagecolorallocate($image, 22, 59, 104);
    $gold = imagecolorallocate($image, 198, 155, 44);
    $text = imagecolorallocate($image, 15, 23, 42);
    $muted = imagecolorallocate($image, 71, 85, 105);
    $line = imagecolorallocate($image, 219, 227, 236);
    $paleBlue = imagecolorallocate($image, 239, 246, 255);
    imagefill($image, 0, 0, $white);

    imagefilledrectangle($image, 0, 0, $width, 280, $navy);
    imagefilledrectangle($image, $padding - 10, 30, $padding + 200, 240, $white);
    imagerectangle($image, $padding - 10, 30, $padding + 200, 240, $gold);
    imagecopyresampled($image, $logo, $padding, 40, 0, 0, 190, 190, imagesx($logo), imagesy($logo));
    $headerTitle = 'GRANBY COLLEGES OF SCIENCE AND TECHNOLOGY';
    $headerTitleFontSize = 29;
    $headerTitleX = 300;
    $headerTitleMaxWidth = $width - $headerTitleX - $padding;
    do {
        $headerTitleBounds = imagettfbbox($headerTitleFontSize, 0, $boldFont, $headerTitle);
        if (abs($headerTitleBounds[2] - $headerTitleBounds[0]) <= $headerTitleMaxWidth) {
            break;
        }
        $headerTitleFontSize--;
    } while ($headerTitleFontSize > 18);
    imagettftext($image, $headerTitleFontSize, 0, $headerTitleX, 88, $white, $boldFont, $headerTitle);
    imagettftext($image, 20, 0, $headerTitleX, 132, $white, $regularFont, 'GCST Track System');
    imagefilledrectangle($image, $headerTitleX, 160, $width - $padding, 164, $gold);
    imagettftext($image, 28, 0, $headerTitleX, 224, $white, $boldFont, 'OFFICIAL RECEIPT');

    $y = 330;
    imagefilledrectangle($image, $padding, $y, $width - $padding, $y + 150, $paleBlue);
    imagettftext($image, 20, 0, $labelX, $y + 52, $muted, $boldFont, 'AMOUNT PAID');
    imagettftext($image, 38, 0, $labelX, $y + 112, $navy, $boldFont, $paidAmount);
    $y += 200;

    foreach ($preparedSections as [$sectionTitle, $preparedRows]) {
        imagefilledrectangle($image, $padding, $y, $width - $padding, $y + 42, $paleBlue);
        imagettftext($image, 25, 0, $labelX, $y + 30, $navy, $boldFont, $sectionTitle);
        $y += 52;
        foreach ($preparedRows as [$labelLines, $lines, $rowHeight]) {
            foreach ($labelLines as $index => $labelLine) {
                imagettftext($image, 20, 0, $labelX, $y + 34 + ($index * $lineHeight), $muted, $boldFont, $labelLine);
            }
            foreach ($lines as $index => $lineText) {
                imagettftext($image, $rowFontSize, 0, $valueX, $y + 34 + ($index * $lineHeight), $text, $regularFont, $lineText);
            }
            $y += $rowHeight;
            imageline($image, $padding, $y, $width - $padding, $y, $line);
        }
        $y += 18;
    }

    $y += 12;
    if ($signatureImage !== null) {
        imagefilledrectangle($image, $padding, $y, $width - $padding, $y + 42, $paleBlue);
        imagettftext($image, 25, 0, $labelX, $y + 30, $navy, $boldFont, 'CASHIER APPROVAL');
        imagettftext($image, 18, 0, $labelX, $y + 72, $muted, $boldFont, 'Assigned cashier signature');
        $signatureWidth = 260;
        $signatureHeight = 90;
        $scale = min(
            $signatureWidth / imagesx($signatureImage),
            $signatureHeight / imagesy($signatureImage)
        );
        $drawWidth = max(1, (int)round(imagesx($signatureImage) * $scale));
        $drawHeight = max(1, (int)round(imagesy($signatureImage) * $scale));
        imagecopyresampled(
            $image,
            $signatureImage,
            $padding,
            $y + 80,
            0,
            0,
            $drawWidth,
            $drawHeight,
            imagesx($signatureImage),
            imagesy($signatureImage)
        );
        $cashierName = trim((string)($receipt['cashier_name'] ?? ''));
        if ($cashierName !== '') {
            imagettftext($image, 21, 0, $padding + 300, $y + 135, $text, $boldFont, $cashierName);
        }
        $y += 185;
    }
    $y += 42;
    imagettftext($image, 16, 0, $padding, $y, $muted, $regularFont, 'System-generated receipt. Please keep this image for your records.');
    $safeReference = preg_replace('/[^A-Za-z0-9_-]/', '_', $referenceNumber);

    imagedestroy($logo);
    if ($signatureImage !== null) {
        imagedestroy($signatureImage);
    }
    ob_start();
    $encoded = imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);
    if (!$encoded || !is_string($contents) || $contents === '') {
        throw new RuntimeException('Unable to encode receipt image as PNG.');
    }

    return [
        'content' => $contents,
        'file_name' => 'GCST_Receipt_' . ($safeReference ?: 'receipt') . '.png',
        'logo_included' => true,
        'signature_included' => $signaturePath !== '' && $signatureImage !== null,
        'signature_error' => $signatureError,
    ];
}
