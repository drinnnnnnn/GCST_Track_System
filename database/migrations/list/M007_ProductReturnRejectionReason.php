<?php
class M007_ProductReturnRejectionReason extends BaseMigration {
    public function getVersion(): string { return '20261118004'; }
    public function getName(): string { return 'Product return rejection reasons'; }

    public function up(): bool {
        return $this->conn->query("ALTER TABLE `product_return_requests`
            ADD COLUMN `rejection_reason` VARCHAR(1000) DEFAULT NULL AFTER `status`
        ") === true;
    }
}
