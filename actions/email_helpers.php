<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Sends an email and logs it to the email_notifications table.
 */
function sendEmailWithLog($conn, $toEmail, $subject, $body, $type, $attachments = []) {
    $mail = new PHPMailer(true);
    $status = 'pending';
    $logMessage = null;

    $mail->CharSet = 'UTF-8';
    try {
        // Server settings (Update these with your real Gmail/SMTP credentials)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'aldrinbautista0425@gmail.com'; // Consider moving to a config file or .env
        $mail->Password   = 'kmml wgyx oaqv glfm'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Add this to fix "Could not authenticate" errors caused by SSL certificate issues in XAMPP
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
            $status = 'sent';
            $logMessage = 'Sent successfully';
        } else {
            $status = 'failed';
            $logMessage = 'Mail accepted but send() returned false';
        }
    } catch (Exception $e) {
        $status = 'failed';
        $logMessage = $e->getMessage() ?: $mail->ErrorInfo;
        error_log("Mailer Error: " . $logMessage);
    }

    // Log the notification to the database
    try {
        // Ensure the email_notifications table exists with all required columns
        $conn->query("CREATE TABLE IF NOT EXISTS email_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255),
            notification_type VARCHAR(50),
            status VARCHAR(20),
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $checkColumn = $conn->query("SHOW COLUMNS FROM `email_notifications` LIKE 'email_body'");
        if ($checkColumn && $checkColumn->num_rows === 0) {
            $conn->query("ALTER TABLE `email_notifications` ADD COLUMN `email_body` LONGTEXT AFTER `error_message`");
        }

        $stmt = $conn->prepare("INSERT INTO email_notifications (recipient, subject, notification_type, status, error_message, email_body) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssssss', $toEmail, $subject, $type, $status, $logMessage, $body);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log("Email Logging Error: " . $e->getMessage());
    }

    return ['success' => $status === 'sent', 'status' => $status];
}
?>