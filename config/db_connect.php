<?php
require_once __DIR__ . '/../database/connection.php';

// SMS API Configuration (Semaphore)
// You can obtain your API key from the Semaphore dashboard.
if (!defined('SMS_API_KEY')) {
    define('SMS_API_KEY', 'YOUR_ACTUAL_SEMAPHORE_API_KEY_HERE');
}

if (!defined('SMS_SENDER_NAME')) {
    define('SMS_SENDER_NAME', 'GCST-TRACK'); // Change this to your preferred sender name
}
?>