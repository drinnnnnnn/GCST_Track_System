<?php
/**
 * GCST Track System - Global Configuration
 */

if (defined('GCST_CONFIG_LOADED')) {
    return;
}

define('GCST_CONFIG_LOADED', true);

// Semaphore SMS API Key (Get this from your Semaphore Dashboard)
if (!defined('SMS_API_KEY')) {
    define('SMS_API_KEY', 'YOUR_ACTUAL_SEMAPHORE_API_KEY_HERE');
}

// Semaphore Approved Sender Name (Default is 'SEMAPHORE')
if (!defined('SMS_SENDER_NAME')) {
    define('SMS_SENDER_NAME', 'SEMAPHORE');
}

// SMTP Configuration for Password Recovery
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
}
if (!defined('SMTP_AUTH')) {
    define('SMTP_AUTH', true);
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587); // Use 587 for TLS, 465 for SSL
}

// Encryption: 'tls' for Port 587, 'ssl' for Port 465
if (!defined('SMTP_SECURE')) {
    define('SMTP_SECURE', 'tls');
}

if (!defined('SMTP_USER')) {
    define('SMTP_USER', 'aldrinbautista0425@gmail.com'); // Full email required for authentication
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', 'kmmlwgyxoaqvglfm');   // Update this with your 16-character App Password
}
if (!defined('MAIL_FROM')) {
    define('MAIL_FROM', 'no-reply@mail.com');
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', 'GRANBY COLLEGES OF SCIENCE AND TECHNOLOGY');
}

// Add other system-wide settings below...