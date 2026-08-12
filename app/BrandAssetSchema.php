<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class BrandAssetSchema
{
    public static function ensure(): void
    {
        $table = Database::table('marketing_brand_assets');
        $linksTable = Database::table('marketing_brand_asset_posts');
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            asset_code VARCHAR(80) NULL,
            description TEXT NULL,
            category VARCHAR(40) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'designed',
            channels_json TEXT NULL,
            campaign_name VARCHAR(191) NULL,
            designer_name VARCHAR(191) NULL,
            approver_name VARCHAR(191) NULL,
            implementer_name VARCHAR(191) NULL,
            creation_date DATE NOT NULL,
            approval_date DATE NULL,
            publication_date DATE NULL,
            implementation_date DATE NULL,
            brand_compliance TINYINT UNSIGNED NOT NULL DEFAULT 0,
            views_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            reach_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            clicks_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            leads_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            referrals_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            acquisitions_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            observations TEXT NULL,
            storage_path VARCHAR(500) NULL,
            original_name VARCHAR(255) NULL,
            mime_type VARCHAR(120) NULL,
            file_size BIGINT UNSIGNED NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT NULL,
            updated_by BIGINT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_brand_assets_code (asset_code),
            KEY idx_brand_assets_status (status),
            KEY idx_brand_assets_category (category),
            KEY idx_brand_assets_creation_date (creation_date),
            KEY idx_brand_assets_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!Database::connection()->query($sql)) {
            throw new RuntimeException('No fue posible preparar el Banco de Piezas: ' . Database::connection()->error);
        }

        $linksSql = "CREATE TABLE IF NOT EXISTS `{$linksTable}` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            asset_id BIGINT UNSIGNED NOT NULL,
            social_post_id BIGINT UNSIGNED NOT NULL,
            created_by BIGINT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_brand_asset_post (asset_id, social_post_id),
            UNIQUE KEY uq_brand_social_post (social_post_id),
            KEY idx_brand_asset_links_asset (asset_id),
            KEY idx_brand_asset_links_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!Database::connection()->query($linksSql)) {
            throw new RuntimeException('No fue posible preparar los vínculos de publicaciones: ' . Database::connection()->error);
        }
    }
}
