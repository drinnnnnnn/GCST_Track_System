<?php
/**
 * GCST Track System - Global Configuration
 */

// Semaphore SMS API Key (Get this from your Semaphore Dashboard)
define('SMS_API_KEY', 'YOUR_ACTUAL_SEMAPHORE_API_KEY_HERE');

// Semaphore Approved Sender Name (Default is 'SEMAPHORE')
define('SMS_SENDER_NAME', 'SEMAPHORE');

// SMTP Configuration for Password Recovery
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_AUTH', true);
define('SMTP_PORT', 587); // Use 587 for TLS, 465 for SSL

// Encryption: 'tls' for Port 587, 'ssl' for Port 465
define('SMTP_SECURE', 'tls'); 

define('SMTP_USER', 'aldrinbautista0425@gmail.com'); // Full email required for authentication
define('SMTP_PASS', 'kmmlwgyxoaqvglfm');   // Update this with your 16-character App Password
define('MAIL_FROM', 'no-reply@gcst-track.edu');
define('MAIL_FROM_NAME', 'GCST Support');

// Add other system-wide settings below...
?>