<?php
class QRService {
    /**
     * Parses scanned text to determine if it is an Order or a Renewal.
     */
    public static function parse(string $input): array {
        $input = trim($input);
        
        if (stripos($input, 'ORDER-') === 0) {
            // Keep the full prefix as it is part of the transaction_number in the DB
            return ['type' => 'order', 'reference' => $input];
        }
        
        if (stripos($input, 'RENEW-') === 0) {
            return ['type' => 'renewal', 'reference' => trim(preg_replace('/^RENEW-/i', '', $input))];
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