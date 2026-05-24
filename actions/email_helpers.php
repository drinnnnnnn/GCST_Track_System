<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Sends an email and logs it to the email_notifications table.
 */
function sendEmailWithLog($conn, $toEmail, $subject, $body, $type, $attachments = []) {
    // 1. Ensure all variables are properly declared and initialized to prevent warnings
    $emailStatus = 'not_sent'; 
    $finalStatus = 'failed';   
    $logMessage  = "";

    // 2. Sanitize parameters to ensure non-null values for database insertion
    $toEmail     = (string)($toEmail ?? '');
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

    // 3. Determine Overall Delivery Status
    // Enforce that finalStatus is one of the valid ENUM values ('sent', 'failed')
    // to prevent "Column 'status' cannot be null" database errors.
    if ($emailStatus === 'sent') {
        $finalStatus = 'sent';
        $logMessage = "Email sent successfully.";
    } else {
        $finalStatus = 'failed';
        if (empty($logMessage)) $logMessage = "No valid delivery channels provided.";
    }
    $logMessage = trim($logMessage);

    try {
        $stmt = $conn->prepare("INSERT INTO email_notifications (recipient, subject, notification_type, status, error_message, email_body) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssssss', $toEmail, $subject, $type, $finalStatus, $logMessage, $body);
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
    ];
}
?>