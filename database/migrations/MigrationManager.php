<?php
/**
 * MigrationManager.php
 * Centralized controller for database schema versioning and execution.
 */
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/BaseMigration.php';

class MigrationManager {
    private $conn;
    private $migrationsTable = 'database_migrations';

    public function __construct() {
        $this->conn = Database::getConnection();
        $this->initializeTrackingTable();
    }

    /**
     * Creates the migrations tracking table if it doesn't exist.
     */
    private function initializeTrackingTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `version` VARCHAR(50) NOT NULL UNIQUE,
            `name` VARCHAR(255) NOT NULL,
            `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `status` ENUM('success', 'failed') DEFAULT 'success'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
    }

    /**
     * Runs all pending migrations.
     */
    public function run() {
        // In a production environment, you would scan the directory for files.
        // For this implementation, we manually register the core migrations.
        $migrations = $this->getRegisteredMigrations();

        foreach ($migrations as $migration) {
            if ($this->isApplied($migration->getVersion())) {
                continue;
            }

            $this->applyMigration($migration);
        }
    }

    /**
     * Check if a specific version has already been applied.
     */
    private function isApplied($version) {
        $stmt = $this->conn->prepare("SELECT 1 FROM `{$this->migrationsTable}` WHERE version = ? AND status = 'success' LIMIT 1");
        $stmt->bind_param('s', $version);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Executes a migration and logs the result.
     */
    private function applyMigration(BaseMigration $migration) {
        $version = $migration->getVersion();
        $name = $migration->getName();

        try {
            $this->conn->begin_transaction();
            
            if ($migration->up()) {
                $stmt = $this->conn->prepare("INSERT INTO `{$this->migrationsTable}` (version, name, status) VALUES (?, ?, 'success')");
                $stmt->bind_param('ss', $version, $name);
                $stmt->execute();
                $stmt->close();
                $this->conn->commit();
                error_log("Migration Applied: [$version] $name");
            } else {
                throw new Exception("Migration execution returned false.");
            }
        } catch (Throwable $e) {
            $this->conn->rollback();
            $errorMsg = $e->getMessage();
            $stmt = $this->conn->prepare("INSERT INTO `{$this->migrationsTable}` (version, name, status) VALUES (?, ?, 'failed')");
            $stmt->bind_param('ss', $version, $name);
            $stmt->execute();
            $stmt->close();
            error_log("Migration Failed: [$version] $name - Error: $errorMsg");
        }
    }

    /**
     * List of migrations to be processed. 
     * Note: In a larger app, this would dynamically include files from a directory.
     */
    private function getRegisteredMigrations() {
        $migrations = [];
        // Use absolute path with platform-agnostic directory separators
        $listDir = __DIR__ . DIRECTORY_SEPARATOR . 'list';

        if (!is_dir($listDir)) {
            // Silently attempt to create the directory if it's missing to avoid runtime warnings
            if (!@mkdir($listDir, 0777, true) && !is_dir($listDir)) {
                error_log("Migration Manager: Directory missing and auto-creation failed at " . $listDir);
                return [];
            }
        }

        // Centralized Discovery: Scan for migration files matching the pattern M###_*.php
        // This avoids hardcoding filenames and prevents fatal errors if a file is missing.
        $files = glob($listDir . DIRECTORY_SEPARATOR . 'M[0-9][0-9][0-9]_*.php');

        if ($files === false) {
            error_log("Migration Manager Error: Failed to scan directory " . $listDir);
            return [];
        }

        // Ensure migrations are processed in sequential order (M001, M002, M003...)
        sort($files);

        foreach ($files as $file) {
            try {
                // Verify readability to prevent 'Failed opening' errors
                if (is_readable($file)) {
                    require_once $file;
                    $className = pathinfo($file, PATHINFO_FILENAME);

                    if (class_exists($className)) {
                        $migrationInstance = new $className($this->conn);
                        // Verify class implementation before adding to queue
                        if ($migrationInstance instanceof BaseMigration) {
                            $migrations[] = $migrationInstance;
                        }
                    } else {
                        error_log("Migration Manager Error: Class $className not found in $file");
                    }
                }
            } catch (Throwable $e) {
                // Graceful error handling for environment or syntax issues in specific files
                error_log("Migration Manager Fatal: " . $e->getMessage() . " in " . $file);
            }
        }

        return $migrations;
    }

    /**
     * Helper to check if a column exists.
     */
    public static function columnExists($conn, $table, $column) {
        $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}