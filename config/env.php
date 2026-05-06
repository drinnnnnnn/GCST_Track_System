<?php
// config/env.php

/**
 * Loads environment variables from a .env file.
 * This is a simple implementation for demonstration. For production, consider a more robust library like phpdotenv.
 */
if (!function_exists('env')) {
    function env($key, $default = null) {
        static $env = null;
        if ($env === null) {
            $env = [];
            $filePath = __DIR__ . '/../.env'; // Path to your .env file
            if (file_exists($filePath)) {
                $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue; // Skip comments
                    list($name, $value) = explode('=', $line, 2);
                    $env[trim($name)] = trim($value);
                }
            }
        }
        return $env[$key] ?? $default;
    }
}
?>