<?php
/**
 * BaseMigration.php
 * Abstract class that all database migrations must extend.
 */
abstract class BaseMigration {
    protected $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Returns a unique version identifier for this migration (e.g., timestamp).
     */
    abstract public function getVersion(): string;

    /**
     * Returns a descriptive name for the migration.
     */
    abstract public function getName(): string;

    /**
     * Executes the migration logic.
     * @return bool Success status
     */
    abstract public function up(): bool;
}