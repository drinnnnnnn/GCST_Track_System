<?php
require_once __DIR__ . '/../database/connection.php';

class NotificationService {
    /**
     * Sends an alert to the user via In-App Notification and SMS.
     */
    public static function sendQueueAlert($ticket) {
        $userId = $ticket['user_id'];
        $queueNum = $ticket['queue_number'];
        $name = $ticket['student_name'] ?: 'Student';
        $phone = $ticket['phone'] ?: $ticket['contact_number'] ?: null;

        $message = "Hi $name! You are next in line (Ticket #$queueNum). Please proceed to the cashier counter.";

        // 1. Create In-App Notification
        self::createInAppNotification($userId, $message);

        // 2. Send SMS if phone number exists
        if ($phone) {
            self::sendSMS($phone, $message);
        }
    }

    private static function createInAppNotification($userId, $message) {
        if (!$userId) return;
        $conn = Database::getConnection();
        
        // Ensure notifications table exists (Basic check/creation)
        $conn->query("CREATE TABLE IF NOT EXISTS `notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT,
            `message` TEXT,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->bind_param("is", $userId, $message);
        $stmt->execute();
    }

    private static function sendSMS($phone, $message) {
        // Clean phone number
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        try {
            /**
             * INTEGRATION POINT:
             * Replace this block with your SMS Provider API (e.g., Twilio, Infobip, Movider)
             */
            
            /* Example for a generic HTTP API:
            $apiKey = 'YOUR_API_KEY';
            $url = "https://api.smsprovider.com/send?to=$phone&message=" . urlencode($message) . "&key=$apiKey";
            $response = file_get_contents($url);
            */

            // Logging for debugging (Simulating success)
            error_log("SMS Sent to $phone: $message");
            
            // If using a service like Twilio, use their SDK here:
            // $twilio->messages->create($phone, ['from' => $from, 'body' => $message]);
            
            return true;
        } catch (Exception $e) {
            error_log("SMS Delivery Failed for $phone: " . $e->getMessage());
            return false;
        }
    }
}
?>