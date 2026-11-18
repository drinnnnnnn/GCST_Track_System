<?php
class M008_AdminCashierRoleSeparation extends BaseMigration {
    public function getVersion(): string { return '20261118005'; }
    public function getName(): string { return 'Separate admin and cashier roles'; }

    public function up(): bool {
        return $this->conn->query("ALTER TABLE `admincashier_acc`
            MODIFY COLUMN `role` ENUM('admin', 'cashier', 'admincashier', 'superadmin')
            NOT NULL DEFAULT 'admincashier'
        ") === true;
    }
}
