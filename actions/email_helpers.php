<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../vendor/autoload.php';

function normalizeEmailBody(string $body): string {
    $decodedBody = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decodedBody = preg_replace('/\r\n|\r/', "\n", $decodedBody) ?? $body;
    $decodedBody = trim($decodedBody);

    // If the content already looks like HTML, keep it as-is so styles render correctly.
    if (preg_match('/<[^>]+>/', $decodedBody)) {
        return $decodedBody;
    }

    // For plain text content, preserve readability while preventing raw markup from leaking.
    return '<div style="font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #0f172a;">'
        . nl2br(htmlspecialchars($decodedBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false)
        . '</div>';
}

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
    $body        = normalizeEmailBody((string)($body ?? ''));

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    // 1. Process Email Notification
    if (!empty($toEmail)) {
        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            // 1. Validate Configuration
            if (!defined('SMTP_USER') || SMTP_USER === 'your-email@gmail.com' || empty(SMTP_PASS) || SMTP_PASS === 'your-app-password') {
                throw new Exception("SMTP service is currently unavailable (Configuration missing).");
            }

            // 2. Configure PHPMailer using constants
            $mail->isSMTP();
            
            // If debugging is enabled, log the full SMTP transaction to the server error log
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER; 
                $mail->Debugoutput = 'error_log';
            }

            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = SMTP_AUTH;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->Port       = SMTP_PORT;
            $mail->SMTPAutoTLS = false;
            
            // Dynamically set encryption based on config
            $mail->SMTPSecure = (defined('SMTP_SECURE') && strtolower(SMTP_SECURE) === 'ssl') 
                                ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

            // XAMPP SSL Certificate workaround
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Use the authenticated SMTP account as the envelope sender for Gmail compatibility.
            // Keep MAIL_FROM as a Reply-To address only when it differs.
            $mail->setFrom(SMTP_USER, MAIL_FROM_NAME);
            $mail->Sender = SMTP_USER;
            if (!empty(MAIL_FROM) && strcasecmp(MAIL_FROM, SMTP_USER) !== 0) {
                $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);
            }
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            
            // Generate a plain-text version for better compatibility and lower spam scores
            $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $body));

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
            if (strpos($err, 'Daily user sending limit exceeded') !== false) {
                $err = 'Gmail SMTP daily sending limit exceeded. Please wait 24 hours or use another SMTP service.';
            }
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