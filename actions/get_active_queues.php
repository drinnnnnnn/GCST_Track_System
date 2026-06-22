<?php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);

require_once __DIR__ . '/../database/models/QueueModel.php';
require_once __DIR__ . '/email_helpers.php';

$model = new QueueModel();
try {
    $expiredCount = $model->expireTickets();

    // Automated Expiration Notifications (110 minutes = 10 minutes before the 2-hour threshold)
    $nearingExpiry = $model->getTicketsNearingExpiry(110);
    foreach ($nearingExpiry as $ticket) {
        if (!empty($ticket['email'])) {
            $subject = "Queue Ticket Expiration Warning - #{$ticket['queue_number']}";
            $body = "<h2>Expiration Warning</h2>
                     <p>Dear {$ticket['student_name']},</p>
                     <p>Your queue ticket <strong>#{$ticket['queue_number']}</strong> is set to expire in approximately 10 minutes.</p>
                     <p>If you still require service, please make yourself known at the cashier counter immediately.</p>
                     <p>Thank you for using the GRANBY COLLEGES OF SCIENCE AND TECHNOLOGY Tracking System.</p>";
            
            $emailResult = sendEmailWithLog($model->getConnection(), $ticket['email'], $subject, $body, 'Queue Expiry Warning');
            if ($emailResult['success']) {
                $model->markExpiryAlertSent($ticket['id']);
            }
        }
    }

    $tickets = $model->getActiveQueues();
    
    // Determine status values for the display panel
    $nowServing = null;
    $nextQueue = null;
    
    foreach($tickets as $t) {
        $details = ($t['student_name'] ?: 'Guest') . ' (' . ($t['school_id'] ?: 'Walk-in') . ')';
        if ($t['status'] === 'serving' && !$nowServing) {
            $nowServing = $details;
        } elseif ($t['status'] === 'waiting' && !$nextQueue) {
            $nextQueue = $details;
        }
    }

    echo json_encode([
        'success' => true, 
        'tickets' => $tickets,
        'expired_count' => $expiredCount,
        'queues' => $tickets, // Maintained for admincashier.js compatibility
        'status' => [
            'nowServing' => $nowServing ?? 'None',
            'nextQueue' => $nextQueue ?? 'None'
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>