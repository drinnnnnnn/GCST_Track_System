<?php
/**
 * Action: Send Queue Ticket Details via Email and SMS
 */
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/QueueModel.php';
require_once __DIR__ . '/email_helpers.php';

header('Content-Type: application/json');
ini_set('display_errors', '0');

try {
    secureSessionStart();
    requireAuth(['student', 'user', 'admin', 'admincashier', 'superadmin']);

    $conn = Database::getConnection();
    $payload = json_decode(file_get_contents('php://input'), true);
    $ticketId = isset($payload['ticket_id']) ? intval($payload['ticket_id']) : null;

    if (!$ticketId) {
        throw new Exception('Ticket ID is required.');
    }

    $queueModel = new QueueModel();
    $ticket = $queueModel->getByIdWithDetails($ticketId);

    if (!$ticket) {
        throw new Exception('Queue ticket not found.');
    }

    $phoneNumber = !empty($ticket['phone']) ? $ticket['phone'] : ($ticket['contact_number'] ?? '');
    if (empty($ticket['email']) && empty($phoneNumber)) {
        throw new Exception('No contact information (Email or Phone) associated with this account.');
    }

    $subject = "Queue Ticket: #{$ticket['queue_number']}";
    $name = htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']);
    $status = ucfirst($ticket['status']);
    $createdAt = date('F j, Y, g:i A', strtotime($ticket['created_at']));

    $body = "
    <div style='font-family: sans-serif; max-width: 600px; margin: 20px auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
        <div style='background-color: #4f46e5; padding: 20px; text-align: center; color: white;'>
            <h2 style='margin:0;'>Your Queue Ticket</h2>
        </div>
        <div style='padding: 30px; line-height: 1.6; color: #334155;'>
            <p>Hello <strong>$name</strong>,</p>
            <p>Here are the details of your queue ticket for your reference:</p>
            <div style='background: #f8fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #4f46e5; margin: 20px 0;'>
                <h1 style='margin: 0; color: #4f46e5; font-size: 32px;'>#{$ticket['queue_number']}</h1>
                <p style='margin: 10px 0 0;'><strong>Purpose:</strong> {$ticket['purpose']}</p>
                <p style='margin: 5px 0 0;'><strong>Status:</strong> $status</p>
                <p style='margin: 5px 0 0;'><strong>Generated:</strong> $createdAt</p>
            </div>
            <p>Please present this information or have your ticket ready when your number is called.</p>
            <p style='font-size: 14px; color: #64748b; font-style: italic;'>Note: Tickets are valid for 1 hour from the time of generation.</p>
        </div>
        <div style='background: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #64748b;'>
            © " . date('Y') . " Granby Colleges of Science and Technology
        </div>
    </div>";

    // Normalize and validate Philippine mobile number for SMS
    $cleanPhone = str_replace([' ', '-', '(', ')'], '', $phoneNumber);
    if (strpos($cleanPhone, '09') === 0 && strlen($cleanPhone) === 11) {
        $cleanPhone = '+63' . substr($cleanPhone, 1);
    }
    
    if (!preg_match('/^\+639\d{9}$/', $cleanPhone)) {
        $cleanPhone = ''; // Reset if invalid
    }

    $res = sendEmailWithLog($conn, $ticket['email'], $subject, $body, 'Manual Ticket Email', [], $cleanPhone);
    
    if ($res['success']) {
        $statusMsg = "Ticket details sent";
        if ($res['email_status'] === 'sent' && $res['sms_status'] === 'sent') $statusMsg .= " via Email and SMS.";
        elseif ($res['email_status'] === 'sent') $statusMsg .= " via Email.";
        elseif ($res['sms_status'] === 'sent') $statusMsg .= " via SMS.";
        else $statusMsg = "Notification processed.";

        echo json_encode(['success' => true, 'message' => $statusMsg]);
    } else {
        throw new Exception($res['message'] ?? 'Failed to send notifications.');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;