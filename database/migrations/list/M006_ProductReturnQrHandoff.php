<?php
class M006_ProductReturnQrHandoff extends BaseMigration {
    public function getVersion(): string { return '20261118003'; }
    public function getName(): string { return 'Product return QR handoff tracking'; }

    public function up(): bool {
        return $this->conn->query("ALTER TABLE `product_return_requests`
            ADD COLUMN `handoff_token_hash` CHAR(64) DEFAULT NULL AFTER `photo_filename`,
            ADD COLUMN `handoff_received_at` DATETIME DEFAULT NULL AFTER `fulfilled_at`,
            ADD COLUMN `handoff_received_by` INT(11) DEFAULT NULL AFTER `handoff_received_at`,
            ADD UNIQUE KEY `uq_product_return_handoff_token` (`handoff_token_hash`),
            ADD KEY `idx_product_return_handoff_received` (`handoff_received_at`)
        ") === true;
    }
}
