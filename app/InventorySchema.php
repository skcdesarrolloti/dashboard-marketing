<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class InventorySchema
{
    public static function ensure(): void
    {
        $prefix = Database::prefix();
        $queries = [
            "CREATE TABLE IF NOT EXISTS `{$prefix}marketing_inventory_products` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(120) NOT NULL DEFAULT 'Souvenir',
                sku VARCHAR(100) NULL,
                description TEXT NULL,
                image_url TEXT NULL,
                quantity INT NOT NULL DEFAULT 0,
                unit VARCHAR(50) NOT NULL DEFAULT 'unidad',
                minimum_stock INT UNSIGNED NOT NULL DEFAULT 0,
                price DECIMAL(14,2) NULL,
                points INT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sync_pph TINYINT(1) NOT NULL DEFAULT 0,
                pph_product_id BIGINT NULL,
                pph_quantity INT NULL,
                pph_hash CHAR(64) NULL,
                pph_modified_at DATETIME NULL,
                created_by BIGINT NULL,
                updated_by BIGINT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_inventory_products_pph (pph_product_id),
                UNIQUE KEY uq_inventory_products_sku (sku),
                KEY idx_inventory_products_active (active),
                KEY idx_inventory_products_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$prefix}marketing_inventory_deliveries` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                delivered_at DATETIME NOT NULL,
                recipient_name VARCHAR(255) NOT NULL,
                recipient_type VARCHAR(120) NULL,
                recipient_id VARCHAR(120) NULL,
                recipient_source VARCHAR(120) NULL,
                recipient_document VARCHAR(120) NULL,
                recipient_metadata LONGTEXT NULL,
                notes TEXT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'delivered',
                source VARCHAR(30) NOT NULL DEFAULT 'dashboard',
                external_id VARCHAR(191) NULL,
                pph_reward_id BIGINT NULL,
                created_by BIGINT NULL,
                cancelled_by BIGINT NULL,
                cancelled_at DATETIME NULL,
                cancellation_reason TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_inventory_delivery_external (source, external_id),
                UNIQUE KEY uq_inventory_delivery_pph (pph_reward_id),
                KEY idx_inventory_delivery_date (delivered_at),
                KEY idx_inventory_delivery_recipient (recipient_name),
                KEY idx_inventory_delivery_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$prefix}marketing_inventory_delivery_items` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                delivery_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NULL,
                product_name VARCHAR(255) NOT NULL,
                product_type VARCHAR(120) NULL,
                quantity INT UNSIGNED NOT NULL,
                unit_price DECIMAL(14,2) NULL,
                points INT NULL,
                pph_product_id BIGINT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_inventory_item_delivery (delivery_id),
                KEY idx_inventory_item_product (product_id),
                CONSTRAINT fk_inventory_item_delivery FOREIGN KEY (delivery_id)
                    REFERENCES `{$prefix}marketing_inventory_deliveries` (id)
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$prefix}marketing_inventory_movements` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id BIGINT UNSIGNED NOT NULL,
                delivery_id BIGINT UNSIGNED NULL,
                movement_type VARCHAR(40) NOT NULL,
                quantity_delta INT NOT NULL,
                stock_before INT NOT NULL,
                stock_after INT NOT NULL,
                reason VARCHAR(500) NOT NULL,
                source VARCHAR(30) NOT NULL DEFAULT 'dashboard',
                created_by BIGINT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_inventory_movement_product (product_id),
                KEY idx_inventory_movement_delivery (delivery_id),
                KEY idx_inventory_movement_date (created_at),
                CONSTRAINT fk_inventory_movement_product FOREIGN KEY (product_id)
                    REFERENCES `{$prefix}marketing_inventory_products` (id)
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$prefix}marketing_inventory_evidence` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                delivery_id BIGINT UNSIGNED NULL,
                movement_id BIGINT UNSIGNED NULL,
                product_id BIGINT UNSIGNED NULL,
                evidence_type VARCHAR(20) NOT NULL,
                storage_path VARCHAR(500) NULL,
                external_url TEXT NULL,
                original_name VARCHAR(255) NULL,
                mime_type VARCHAR(100) NULL,
                file_size BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_inventory_evidence_delivery (delivery_id),
                KEY idx_inventory_evidence_movement (movement_id),
                KEY idx_inventory_evidence_product (product_id),
                CONSTRAINT fk_inventory_evidence_delivery FOREIGN KEY (delivery_id)
                    REFERENCES `{$prefix}marketing_inventory_deliveries` (id)
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($queries as $sql) {
            if (!Database::connection()->query($sql)) {
                throw new RuntimeException('No fue posible preparar el esquema de inventario: ' . Database::connection()->error);
            }
        }

        $evidenceTable = "{$prefix}marketing_inventory_evidence";
        $deliveryNullable = Database::value(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            'ss',
            [$evidenceTable, 'delivery_id']
        );
        if ((string) $deliveryNullable !== 'YES' && !Database::connection()->query(
            "ALTER TABLE `{$evidenceTable}` MODIFY delivery_id BIGINT UNSIGNED NULL"
        )) {
            throw new RuntimeException('No fue posible permitir evidencias de movimientos: ' . Database::connection()->error);
        }
        self::ensureColumn($evidenceTable, 'movement_id', 'BIGINT UNSIGNED NULL AFTER delivery_id');
        self::ensureColumn($evidenceTable, 'product_id', 'BIGINT UNSIGNED NULL AFTER movement_id');
        self::ensureIndex($evidenceTable, 'idx_inventory_evidence_movement', 'movement_id');
        self::ensureIndex($evidenceTable, 'idx_inventory_evidence_product', 'product_id');
    }

    private static function ensureColumn(string $table, string $column, string $definition): void
    {
        $exists = (int) Database::value(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            'ss',
            [$table, $column]
        ) > 0;
        if (!$exists && !Database::connection()->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}")) {
            throw new RuntimeException("No fue posible agregar {$table}.{$column}: " . Database::connection()->error);
        }
    }

    private static function ensureIndex(string $table, string $index, string $column): void
    {
        $exists = (int) Database::value(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            'ss',
            [$table, $index]
        ) > 0;
        if (!$exists && !Database::connection()->query("ALTER TABLE `{$table}` ADD INDEX `{$index}` (`{$column}`)")) {
            throw new RuntimeException("No fue posible agregar el índice {$index}: " . Database::connection()->error);
        }
    }
}
