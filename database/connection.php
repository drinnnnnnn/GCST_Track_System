<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $connection = null;
    private static $pdo = null;

    public static function getConnection() {
        if (self::$connection === null) {
            // Increase execution time to 300 seconds for this script
            set_time_limit(300);
            self::$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (self::$connection->connect_error) {
                die('Database connection failed: ' . self::$connection->connect_error);
            }
            self::$connection->set_charset(DB_CHARSET);
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
                die('PDO connection failed: ' . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}

$conn = Database::getConnection();
