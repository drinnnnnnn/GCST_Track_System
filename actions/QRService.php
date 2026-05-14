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
        // Optional: Implement logging scan attempts for security auditing
    }

    public static function checkRateLimit($conn, string $action, int $max, int $seconds): void {
        // Placeholder for rate limiting logic
        // In a production environment, you'd check a `rate_limits` table
    }

    public static function resetAttempts($conn, string $action): void {
        // Placeholder to clear failed attempts upon a successful scan
    }
}