<?php
class QRService {
    /**
     * Parses scanned text to determine if it is an Order or a Renewal.
     */
    public static function parse(string $input): array {
        $input = trim($input);
        
        // Use regex to find ORDER- prefix anywhere in the string to handle descriptive labels
        if (preg_match('/(ORDER-[a-zA-Z0-9-]+)/i', $input, $matches)) {
            // Return the captured reference (e.g., ORDER-12345)
            return ['type' => 'order', 'reference' => $matches[1]];
        }
        
        // Use regex to find RENEW- prefix anywhere in the string
        if (preg_match('/RENEW-([a-zA-Z0-9-]+)/i', $input, $matches)) {
            // Return type renewal and the ID portion only
            return ['type' => 'renewal', 'reference' => $matches[1]];
        }

        return ['type' => 'unknown', 'reference' => $input];
    }

    public static function respond(bool $success, array $data = [], string $message = ''): void {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
        exit;
    }

    public static function logAttempt($conn, string $type): void {
        $ip = $_SERVER['REMOTE_ADDR'];
        $userId = $_SESSION['user_id'] ?? 0;

        // Increment attempts. If the last attempt was over an hour ago, reset the count to 1.
        $stmt = $conn->prepare("INSERT INTO rate_limits (ip_address, user_id, action_type, attempt_count, last_attempt) 
                                VALUES (?, ?, ?, 1, NOW()) 
                                ON DUPLICATE KEY UPDATE 
                                attempt_count = IF(last_attempt < DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, attempt_count + 1),
                                last_attempt = NOW()");
        $stmt->bind_param('sis', $ip, $userId, $type);
        $stmt->execute();
        $stmt->close();
    }

    public static function checkRateLimit($conn, string $action, int $max, int $seconds): void {
        $ip = $_SERVER['REMOTE_ADDR'];
        $userId = $_SESSION['user_id'] ?? 0;

        // Ensure the monitoring table exists
        $conn->query("CREATE TABLE IF NOT EXISTS `rate_limits` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_id` INT DEFAULT 0,
            `action_type` VARCHAR(50) NOT NULL,
            `attempt_count` INT DEFAULT 0,
            `last_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `blocked_until` DATETIME DEFAULT NULL,
            UNIQUE KEY `ui_limit` (`ip_address`, `user_id`, `action_type`)
        )");

        $stmt = $conn->prepare("SELECT attempt_count, last_attempt, blocked_until FROM rate_limits WHERE ip_address = ? AND user_id = ? AND action_type = ?");
        $stmt->bind_param('sis', $ip, $userId, $action);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_assoc();
        $stmt->close();

        if ($data) {
            $now = time();
            $blockedUntil = $data['blocked_until'] ? strtotime($data['blocked_until']) : 0;
            
            if ($blockedUntil > $now) {
                $remaining = $blockedUntil - $now;
                self::respond(false, [], "Security: Too many failed attempts. Please try again in $remaining seconds.");
            }

            $lastAttempt = strtotime($data['last_attempt']);
            if ($data['attempt_count'] >= $max && ($now - $lastAttempt) < $seconds) {
                $blockTime = date('Y-m-d H:i:s', $now + $seconds);
                $stmt = $conn->prepare("UPDATE rate_limits SET blocked_until = ? WHERE ip_address = ? AND user_id = ? AND action_type = ?");
                $stmt->bind_param('ssis', $blockTime, $ip, $userId, $action);
                $stmt->execute();
                $stmt->close();
                self::respond(false, [], "Rate limit reached. Temporary scan block applied for $seconds seconds.");
            }
        }
    }

    public static function resetAttempts($conn, string $action): void {
        $ip = $_SERVER['REMOTE_ADDR'];
        $userId = $_SESSION['user_id'] ?? 0;
        $stmt = $conn->prepare("UPDATE rate_limits SET attempt_count = 0, blocked_until = NULL WHERE ip_address = ? AND user_id = ? AND action_type = ?");
        $stmt->bind_param('sis', $ip, $userId, $action);
        $stmt->execute();
        $stmt->close();
    }
}