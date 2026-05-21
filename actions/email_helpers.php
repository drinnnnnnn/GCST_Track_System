<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db_connect.php';

/**
 * Sends an email and logs it to the email_notifications table.
 */
function sendEmailWithLog($conn, $toEmail, $subject, $body, $type, $attachments = [], $phoneNumber = null) {
    // 1. Ensure all variables are properly declared and initialized to prevent warnings
    $emailStatus = 'not_sent'; 
    $smsStatus   = 'not_sent';
    $finalStatus = 'failed';   
    $logMessage  = "";

    // 2. Sanitize parameters to ensure non-null values for database insertion
    $toEmail     = (string)($toEmail ?? '');
    $phoneNumber = (string)($phoneNumber ?? '');
    $subject     = (string)($subject ?? '(No Subject)');
    $type        = (string)($type ?? 'Manual Notification');
    $body        = (string)($body ?? '');

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    // 1. Process Email Notification
    if (!empty($toEmail)) {
        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            // Server settings (Update these with your real Gmail/SMTP credentials)
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'aldrinbautista0425@gmail.com'; 
            $mail->Password   = 'kmml wgyx oaqv glfm'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // XAMPP SSL Certificate workaround
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Using the Username as the From address ensures better deliverability with Gmail
            $mail->setFrom($mail->Username, 'GCST Tracking System');
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            
            // Generate a plain-text version for better compatibility and lower spam scores
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            // Process attachments (QR codes, etc.)
            foreach ($attachments as $att) {
                if (isset($att['path'], $att['cid'])) {
                    $mail->addEmbeddedImage($att['path'], $att['cid'], $att['name'] ?? 'image.png');
                } elseif (isset($att['path'])) {
                    $mail->addAttachment($att['path'], $att['name'] ?? '');
                }
            }

            if ($mail->send()) {
                $emailStatus = 'sent';
                $logMessage .= "Email sent successfully. ";
            } else {
                $emailStatus = 'failed';
                $logMessage .= "Email send() failed. ";
            }
        } catch (Exception $e) {
            $emailStatus = 'failed';
            $err = $e->getMessage() ?: $mail->ErrorInfo;
            $logMessage .= "Email failed: $err. ";
            error_log("Mailer Error: " . $err);
        }
        } else {
            $emailStatus = 'failed';
            $logMessage .= "Email failed: Invalid address format ($toEmail). ";
        }
    } else {
        $logMessage .= "Email skipped: No recipient provided. ";
    }

    // 2. Process SMS Notification
    if (!empty($phoneNumber)) {
        $smsResult = sendSMS($phoneNumber, strip_tags($body));
        if ($smsResult['success']) {
            $smsStatus = 'sent';
            $logMessage .= "SMS sent to $phoneNumber. ";
        } else {
            $smsStatus = 'failed';
            $logMessage .= "SMS failed for $phoneNumber: " . ($smsResult['error'] ?? 'Unknown error') . ". ";
        }
    }

    // 3. Determine Overall Delivery Status
    // Enforce that finalStatus is one of the valid ENUM values ('sent', 'failed')
    // to prevent "Column 'status' cannot be null" database errors.
    if ($emailStatus === 'sent' || $smsStatus === 'sent') {
        $finalStatus = 'sent';
    } else {
        $finalStatus = 'failed';
        if (empty($logMessage)) $logMessage = "No valid delivery channels provided.";
    }
    $logMessage = trim($logMessage);

    // Log the notification to the database
    // The 'status' column will store 'sent', 'failed', or 'pending'.
    // 'created_at' is a TIMESTAMP, useful for date-based filtering.
    // 'notification_type' stores the category of the email.
    try {
        // Ensure the email_notifications table exists with all required columns
        $conn->query("CREATE TABLE IF NOT EXISTS email_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipient VARCHAR(255) NOT NULL,
            phone_number VARCHAR(20) DEFAULT NULL,
            subject VARCHAR(255),
            notification_type VARCHAR(50),
            status ENUM('sent','failed') NOT NULL, -- Match init_db.php schema
            error_message TEXT,
            email_body LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Ensure created_at exists for older database versions
        $checkTime = $conn->query("SHOW COLUMNS FROM `email_notifications` LIKE 'created_at'");
        if ($checkTime && $checkTime->num_rows === 0) {
            $conn->query("ALTER TABLE `email_notifications` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `error_message` ");
        }

        $checkColumn = $conn->query("SHOW COLUMNS FROM `email_notifications` LIKE 'email_body'");
        if ($checkColumn && $checkColumn->num_rows === 0) {
            $conn->query("ALTER TABLE `email_notifications` ADD COLUMN `email_body` LONGTEXT AFTER `error_message`");
        }

        $stmt = $conn->prepare("INSERT INTO email_notifications (recipient, phone_number, subject, notification_type, status, error_message, email_body) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('sssssss', $toEmail, $phoneNumber, $subject, $type, $finalStatus, $logMessage, $body);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log("Email Logging Error: " . $e->getMessage());
    }

    return [
        'success' => $finalStatus === 'sent',
        'status' => $finalStatus,
        'message' => trim($logMessage),
        'email_status' => $emailStatus,
        'sms_status' => $smsStatus
    ];
}

/**
 * Core logic for triggering SMS via provider API.
 */
function sendSMS($to, $message) {
    // Priority: Use global constant if defined in config.php, otherwise fall back to local variable
    $apiKey = defined('SMS_API_KEY') ? SMS_API_KEY : '';
    $senderName = defined('SMS_SENDER_NAME') ? SMS_SENDER_NAME : 'Semaphore';

    try {
        // Validate configuration
        $placeholders = ['', 'YOUR_SEMAPHORE_API_KEY', 'YOUR_ACTUAL_SEMAPHORE_API_KEY_HERE'];
        if (in_array($apiKey, $placeholders)) {
            throw new Exception("SMS API configuration missing. Please define 'SMS_API_KEY' in your configuration or update email_helpers.php.");
        }

        if (empty($to)) throw new Exception("Recipient missing");
        
        // Clean phone number: Ensure it is in the format 09XXXXXXXXX or 639XXXXXXXXX for the API
        // Semaphore typically expects 11 digits starting with 09 or the full 12 digits.
        $cleanTo = preg_replace('/[^0-9]/', '', $to);
        if (strpos($cleanTo, '63') === 0) {
            $cleanTo = '0' . substr($cleanTo, 2);
        }

        // Truncate message to avoid excessive billing (SMS limit is usually 160 chars per credit)
        $shortMessage = mb_substr($message, 0, 160);

        $ch = curl_init();
        $parameters = array(
            'apikey' => $apiKey,
            'number' => $cleanTo,
            'message' => $shortMessage,
            'sendername' => $senderName
        );

        curl_setopt($ch, CURLOPT_URL, 'https://semaphore.co/api/v4/messages');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Required if XAMPP local SSL is not configured

        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $resData = json_decode($output, true);
        curl_close($ch);
        
        // Check for JSON decoding errors first
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Semaphore API returned malformed JSON: " . json_last_error_msg() . " Raw response: " . $output);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            // Semaphore returns HTTP 200 even for errors like invalid API keys.
            // Successful sends return a sequential array of message objects.
            // Errors return an associative array with keys like 'apikey' or 'message'.
            if (is_array($resData) && !empty($resData) && !isset($resData[0])) { // Added !empty($resData) for robustness
                $errorDetail = isset($resData['apikey']) ? "Invalid API Key" : "API Error: " . json_encode($resData);
                throw new Exception($errorDetail);
            }

            // For successful sends, Semaphore typically returns an array of message objects,
            // where each object has a 'status' key.
            if (is_array($resData) && isset($resData[0]) && isset($resData[0]['status']) && $resData[0]['status'] === 'success') {
                error_log("SMS SUCCESS: Sent to $to. Response: $output");
                return ['success' => true];
            } else {
                // If it's 2xx but not a clear success message structure, treat as failure
                throw new Exception("Semaphore API returned 2xx but unexpected success format. Response: " . $output);
            }
        } else {
            $errorDetail = $resData[0]['message'] ?? ($resData['message'] ?? "HTTP Error $httpCode");
            throw new Exception($errorDetail);
        }
    } catch (Exception $e) {
        error_log("SMS FAILURE: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>