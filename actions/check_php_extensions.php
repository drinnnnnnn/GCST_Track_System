<?php
// c:\xampp\htdocs\GCST_Track_System\actions\check_php_extensions.php

header('Content-Type: text/html; charset=utf-8');

echo "<h1>PHP Extension Check for GCST Track System</h1>";
echo "<p>This script verifies if essential PHP extensions are enabled on your server.</p>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<thead><tr><th>Extension</th><th>Status</th><th>Recommendation</th></tr></thead>";
echo "<tbody>";

$requiredExtensions = [
    'mysqli' => 'Database connection (essential)',
    'json' => 'JSON encoding/decoding (essential for API responses)',
    'session' => 'Session management (essential for user authentication)',
    'openssl' => 'Secure communication (essential for HTTPS, SMTP TLS)',
    'gd' => 'Image manipulation (essential for local QR code generation)',
    'mbstring' => 'Multibyte string functions (good practice for UTF-8 handling)',
    'fileinfo' => 'File type detection (useful for uploads)',
    'dom' => 'XML/HTML document manipulation (PHPMailer might use it)',
];

$allGood = true;

foreach ($requiredExtensions as $ext => $recommendation) {
    $status = extension_loaded($ext) ? 'Enabled' : 'Disabled';
    $color = extension_loaded($ext) ? 'green' : 'red';
    $icon = extension_loaded($ext) ? '&#10004;' : '&#10008;'; // Checkmark or X

    echo "<tr>";
    echo "<td><strong>$ext</strong></td>";
    echo "<td style='color: $color;'>$icon $status</td>";
    echo "<td>$recommendation</td>";
    echo "</tr>";

    if (!extension_loaded($ext)) {
        $allGood = false;
    }
}

echo "</tbody>";
echo "</table>";

if ($allGood) {
    echo "<p style='color: green; font-weight: bold;'>&#10004; All essential PHP extensions are enabled!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>&#10008; Some essential PHP extensions are disabled. Please enable them in your <code>php.ini</code> file and restart your web server (Apache/Nginx).</p>";
}
?>