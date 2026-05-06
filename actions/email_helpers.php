<?php
// actions/email_helpers.php

require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer autoloader
require_once __DIR__ . '/../config/db_connect.php'; // For database connection to log emails
require_once __DIR__ . '/../config/env.php'; // For environment variables

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends an email using PHPMailer and logs the attempt to the database.
 *
 * @param mysqli $conn The database connection object.
 * @param string $to The recipient's email address.
 * @param string $subject The email subject.
 * @param string $htmlBody The HTML content of the email.
 * @param string $emailType A category for the email (e.g., 'Order Confirmation', 'Renewal Approval').
 * @param array $attachments An array of arrays, each with 'path', 'name', and optional 'cid' for embedding.
 * @return array An associative array with 'status' ('sent' or 'failed') and 'message'.
 */
function sendEmailWithLog($conn, $to, $subject, $htmlBody, $emailType = 'General', $attachments = []) {
    $mail = new PHPMailer(true); // Enable exceptions

    $recipient = $to;
    $status = 'failed';
    $errorMessage = '';
    $logMessage = $htmlBody; // Store full HTML body for debugging
    $debugOutput = '';

    try {
        // Server settings
        $mail->SMTPDebug  = 0; // Set to 0 for production, but we will capture it on failure
        $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
            $debugOutput .= "$level: $str\n";
        };

        $mail->isSMTP();
        $mail->Host       = env('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('SMTP_USERNAME');
        $mail->Password   = env('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use TLS
        $mail->Port       = env('SMTP_PORT', 587);
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom(env('SMTP_FROM_EMAIL'), env('SMTP_FROM_NAME'));
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody); // Plain text for non-HTML mail clients

        // Attachments
        foreach ($attachments as $attachment) {
            if (file_exists($attachment['path'])) {
                if (!empty($attachment['cid'])) {
                    $mail->addEmbeddedImage($attachment['path'], $attachment['cid'], $attachment['name']);
                } else {
                    $mail->addAttachment($attachment['path'], $attachment['name']);
                }
            } else {
                $resolvedPath = realpath($attachment['path']) ?: 'Path does not exist: ' . $attachment['path'];
                error_log("PHPMailer Error: Attachment file not found. Path: " . $resolvedPath);
            }
        }

        $mail->send();
        $status = 'sent';
        $message = 'Email sent successfully.';
    } catch (Exception $e) {
        // If it fails, we capture the error and the detailed SMTP log
        $errorMessage = $e->getMessage() . (empty($debugOutput) ? "" : "\nDebug Log:\n" . $debugOutput);
        $message = "Email could not be sent. Mailer Error: " . $mail->ErrorInfo;
        error_log("PHPMailer Auth Error to $to: " . $errorMessage);
    }

    // Log the email attempt to the database
    $logStmt = $conn->prepare("INSERT INTO email_logs (recipient, subject, message, email_type, status, error_message) VALUES (?, ?, ?, ?, ?, ?)");
    if ($logStmt) {
        $logStmt->bind_param('ssssss', $recipient, $subject, $logMessage, $emailType, $status, $errorMessage);
        $logStmt->execute();
        $logStmt->close();
    } else {
        error_log("Failed to prepare email log statement: " . $conn->error);
    }

    return ['status' => $status, 'message' => $message];
}

/**
 * Ensures the email_logs table exists in the database.
 * This function is called automatically when email_helpers.php is included.
 */
function createEmailLogTableIfNotExists($conn) {
    static $isTableChecked = false;
    if ($isTableChecked) return;

    $sql = "CREATE TABLE IF NOT EXISTS `email_logs` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        recipient VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT,
        email_type VARCHAR(100) DEFAULT 'General',
        status ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
        sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        error_message TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);

    // Check for columns that might be missing in older table versions
    $checkColumn = $conn->query("SHOW COLUMNS FROM `email_logs` LIKE 'error_message'");
    if ($checkColumn && $checkColumn->num_rows === 0) {
        $conn->query("ALTER TABLE `email_logs` ADD COLUMN `error_message` TEXT DEFAULT NULL AFTER `sent_at` ");
    }

    $isTableChecked = true;
}

// Ensure the table exists when this helper is included
global $conn; // Ensure we are using the global connection
createEmailLogTableIfNotExists($conn);
?>