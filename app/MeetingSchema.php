<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class MeetingSchema
{
    public static function ensure(): void
    {
        $meetings = Database::table('marketing_meetings');
        $items = Database::table('marketing_meeting_items');
        $history = Database::table('marketing_meeting_action_history');

        $queries = [
            "CREATE TABLE IF NOT EXISTS `{$meetings}` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(220) NOT NULL,
                meeting_date DATE NOT NULL,
                objective TEXT NULL,
                participants_json MEDIUMTEXT NULL,
                conclusions MEDIUMTEXT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'draft',
                carried_from_meeting_id BIGINT UNSIGNED NULL,
                created_by BIGINT NULL,
                created_by_name VARCHAR(191) NOT NULL DEFAULT '',
                updated_by BIGINT NULL,
                updated_by_name VARCHAR(191) NOT NULL DEFAULT '',
                started_at DATETIME NULL,
                completed_at DATETIME NULL,
                archived_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_meetings_date (meeting_date),
                KEY idx_meetings_status (status),
                UNIQUE KEY uq_meetings_previous (carried_from_meeting_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$items}` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                meeting_id BIGINT UNSIGNED NOT NULL,
                section VARCHAR(24) NOT NULL,
                title VARCHAR(300) NOT NULL,
                content MEDIUMTEXT NULL,
                observation MEDIUMTEXT NULL,
                related_improvement_id BIGINT UNSIGNED NULL,
                responsible_employee_id BIGINT NULL,
                responsible_name VARCHAR(191) NOT NULL DEFAULT '',
                due_date DATE NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'pending',
                failure_reason MEDIUMTEXT NULL,
                carried_from_item_id BIGINT UNSIGNED NULL,
                sort_order INT NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by BIGINT NULL,
                created_by_name VARCHAR(191) NOT NULL DEFAULT '',
                updated_by BIGINT NULL,
                updated_by_name VARCHAR(191) NOT NULL DEFAULT '',
                completed_by BIGINT NULL,
                completed_by_name VARCHAR(191) NOT NULL DEFAULT '',
                completed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_meeting_carried_item (meeting_id, carried_from_item_id),
                KEY idx_meeting_items_meeting (meeting_id, active, section, sort_order),
                KEY idx_meeting_items_status (status),
                KEY idx_meeting_items_responsible (responsible_employee_id),
                KEY idx_meeting_items_due (due_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$history}` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                meeting_id BIGINT UNSIGNED NOT NULL,
                action_item_id BIGINT UNSIGNED NOT NULL,
                from_status VARCHAR(24) NOT NULL DEFAULT '',
                to_status VARCHAR(24) NOT NULL,
                reason MEDIUMTEXT NULL,
                changed_by BIGINT NULL,
                changed_by_name VARCHAR(191) NOT NULL DEFAULT '',
                changed_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_meeting_history_action (action_item_id, changed_at),
                KEY idx_meeting_history_meeting (meeting_id, changed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($queries as $query) {
            if (!Database::connection()->query($query)) {
                throw new RuntimeException('No fue posible preparar el módulo de Reuniones: ' . Database::connection()->error);
            }
        }
    }
}
