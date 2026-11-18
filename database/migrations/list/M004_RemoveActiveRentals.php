<?php
class M004_RemoveActiveRentals extends BaseMigration {
    public function getVersion(): string { return '20261118001'; }
    public function getName(): string { return 'Remove active rentals'; }

    public function up(): bool {
        return $this->conn->query("DROP TABLE IF EXISTS `active_rentals`") === true;
    }
}
