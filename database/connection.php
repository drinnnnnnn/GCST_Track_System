<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $connection = null;
    private static $pdo = null;

    public static function getConnection() {
        if (self::$connection === null) {
            try {
                // Enable internal error reporting for mysqli
                mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
                
                // Increase execution time for potentially heavy operations
                set_time_limit(300);

                self::$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                self::$connection->set_charset(DB_CHARSET);
            } catch (mysqli_sql_exception $e) {
                self::handleConnectionFailure($e);
            }
        }
        return self::$connection;
    }

    public static function getPdo() {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                self::handleConnectionFailure($e);
            }
        }
        return self::$pdo;
    }

    /**
     * Centralized diagnostic error handler
     */
    private static function handleConnectionFailure(Exception $e) {
        $code = $e->getCode();
        $rawMessage = $e->getMessage();
        
        // Log detailed error to server-side log for the developer
        error_log("GCST Database Error [$code]: " . $rawMessage);

        // Determine user-friendly message based on common MySQL error codes
        $friendlyMessage = "Unable to connect to the database. ";
        
        switch ($code) {
            case 1045: $friendlyMessage .= "Access Denied: Please check database username and password."; break;
            case 1049: $friendlyMessage .= "Database not found: Ensure '" . DB_NAME . "' exists in phpMyAdmin."; break;
            case 2002: $friendlyMessage .= "Connection Refused: Ensure MySQL service is running in XAMPP."; break;
            default: $friendlyMessage .= "Technical error occurred. Please try again later."; break;
        }

        // Handle AJAX/JSON requests to prevent front-end parsing crashes
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
                  (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        if ($isAjax) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $friendlyMessage]);
            exit;
        }

        // Fallback for standard page loads
        die("<div style='font-family:sans-serif;padding:20px;border:1px solid #f5c6cb;background:#f8d7da;color:#721c24;border-radius:5px;'>
                <strong>Fatal:</strong> " . htmlspecialchars($friendlyMessage) . "
             </div>");
    }
}

$conn = Database::getConnection();
