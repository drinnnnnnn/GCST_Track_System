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

    try {
        // Server settings (Update these with your real Gmail/SMTP credentials)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'aldrinbautista0425@gmail.com'; 
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

        $mail->setFrom('no-reply@gcst.edu', 'GCST Tracking System');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

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
        } else {
            $status = 'failed';
        }
    } catch (Exception $e) {
        $status = 'failed';
        error_log("Mailer Error: " . $mail->ErrorInfo);
    }

    // Log the notification to the database
    try {
        $stmt = $conn->prepare("INSERT INTO email_notifications (recipient, subject, notification_type, status, error_message) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $cleanBody = strip_tags($body);
            $stmt->bind_param('sssss', $toEmail, $subject, $type, $status, $cleanBody);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log("Email Logging Error: " . $e->getMessage());
    }

    return ['success' => $status === 'sent', 'status' => $status];
}
?>