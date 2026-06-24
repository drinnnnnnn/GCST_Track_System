<?php
/**
 * Action: Process Queue Notifications
 * Monitors queue changes and sends Gmail alerts for "Next in Line" and "Now Serving".
 */
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/QueueModel.php';
require_once __DIR__ . '/email_helpers.php';

secureSessionStart();
header('Content-Type: application/json');

try {
    $conn = Database::getConnection();
    $queueModel = new QueueModel();
    $alertsSent = 0;

    // 1. Process "NOW SERVING" Alerts
    $serving = $queueModel->getServingToNotify();
    if ($serving && (!empty($serving['email']) || !empty($serving['phone']) || !empty($serving['contact_number']))) {
        $subject = "Now Serving: Ticket #{$serving['queue_number']}";
        $name = htmlspecialchars($serving['first_name'] . ' ' . $serving['last_name']);
        
        $body = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 20px auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
            <div style='background-color: #22c55e; padding: 20px; text-align: center; color: white;'>
                <h2 style='margin:0;'>It's Your Turn!</h2>
            </div>
            <div style='padding: 30px; line-height: 1.6; color: #334155;'>
                <p>Hello <strong>$name</strong>,</p>
                <p>Your queue ticket <strong>#{$serving['queue_number']}</strong> is now being called at the window.</p>
                <div style='background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #22c55e; margin: 20px 0;'>
                    <strong>Ticket Details:</strong><br>
                    Purpose: {$serving['purpose']}<br>
                    Status: <strong>NOW SERVING</strong>
                </div>
                <p>Please proceed to the cashier window immediately. Thank you!</p>
            </div>
            <div style='background: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #64748b;'>
                © " . date('Y') . " Granby Colleges of Science and Technology
            </div>
        </div>";

        // Validate Phone
        $phoneNumber = !empty($serving['phone']) ? $serving['phone'] : ($serving['contact_number'] ?? '');
        $cleanPhone = str_replace([' ', '-', '(', ')'], '', $phoneNumber);
        if (strpos($cleanPhone, '09') === 0 && strlen($cleanPhone) === 11) {
            $cleanPhone = '+63' . substr($cleanPhone, 1);
        }
        if (!preg_match('/^\+639\d{9}$/', $cleanPhone)) {
            $cleanPhone = '';
        }

        $res = sendEmailWithLog($conn, $serving['email'], $subject, $body, 'Queue Serving Alert', [], $cleanPhone);
        if ($res['success']) {
            $queueModel->markServingAlertSent($serving['id']);
            $alertsSent++;
        }
    }

    // 2. Process "NEXT IN LINE" Alerts
    // We only notify the person who is exactly index 0 in the 'waiting' list
    $next = $queueModel->getNextToNotify();
    if ($next && (!empty($next['email']) || !empty($next['phone']) || !empty($next['contact_number']))) {
        // We only send the "Next" alert if there is someone currently being served
        // This ensures the "Next" alert isn't sent to the very first person of the day immediately
        $counts = $queueModel->getQueueCounts();
        if (($counts['serving'] ?? 0) > 0) {
            $subject = "Queue Update: You are Next in Line (#{$next['queue_number']})";
            $name = htmlspecialchars($next['first_name'] . ' ' . $next['last_name']);

            $body = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 20px auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
                <div style='background-color: #2563eb; padding: 20px; text-align: center; color: white;'>
                    <h2 style='margin:0;'>Get Ready!</h2>
                </div>
                <div style='padding: 30px; line-height: 1.6; color: #334155;'>
                    <p>Hello <strong>$name</strong>,</p>
                    <p>This is an automated update regarding your position in the queue.</p>
                    <div style='background: #eff6ff; padding: 15px; border-radius: 8px; border-left: 4px solid #2563eb; margin: 20px 0;'>
                        <strong>Ticket: #{$next['queue_number']}</strong><br>
                        Position: <strong>NEXT IN LINE</strong>
                    </div>
                    <p>The person ahead of you is currently being served. Please stay near the window or prepare your documents.</p>
                </div>
                <div style='background: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #64748b;'>
                    Generated at: " . date('h:i A', strtotime($next['created_at'])) . "
                </div>
            </div>";

            // Validate Phone
            $phoneNumber = !empty($next['phone']) ? $next['phone'] : ($next['contact_number'] ?? '');
            $cleanPhone = str_replace([' ', '-', '(', ')'], '', $phoneNumber);
            if (strpos($cleanPhone, '09') === 0 && strlen($cleanPhone) === 11) {
                $cleanPhone = '+63' . substr($cleanPhone, 1);
            }
            if (!preg_match('/^\+639\d{9}$/', $cleanPhone)) {
                $cleanPhone = '';
            }

            $res = sendEmailWithLog($conn, $next['email'], $subject, $body, 'Queue Next Alert', [], $cleanPhone);
            if ($res['success']) {
                $queueModel->markAlertSent($next['id']);
                $alertsSent++;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'alerts_processed' => $alertsSent,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;