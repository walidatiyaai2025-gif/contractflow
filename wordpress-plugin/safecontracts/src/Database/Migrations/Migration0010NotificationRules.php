<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;
use SafeContracts\Roles\RoleRegistrar;

final class Migration0010NotificationRules implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $rules = $wpdb->prefix . 'safecontracts_notification_rules';

        dbDelta("CREATE TABLE {$rules} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            trigger_type varchar(32) NOT NULL DEFAULT 'before_due',
            days_before int(11) unsigned NOT NULL DEFAULT 0,
            recipient_roles_json longtext NOT NULL,
            target_assigned_accountant tinyint(1) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY active_trigger (is_active, trigger_type, days_before)
        ) {$charset};");

        $now = gmdate('Y-m-d H:i:s');
        $rolesJson = json_encode([RoleRegistrar::MANAGER], JSON_UNESCAPED_SLASHES);
        if (! is_string($rolesJson)) {
            $rolesJson = '["safecontracts_manager"]';
        }

        $sql = $wpdb->prepare(
            "INSERT INTO {$rules}
                (code, name, trigger_type, days_before, recipient_roles_json, target_assigned_accountant, is_active, created_by, updated_by, created_at, updated_at)
             VALUES (%s, %s, %s, %d, %s, 1, 1, NULL, NULL, %s, %s)
             ON DUPLICATE KEY UPDATE code = VALUES(code)",
            'default_due_10_days',
            'Default 10-day due reminder',
            'before_due',
            10,
            $rolesJson,
            $now,
            $now
        );
        $wpdb->query($sql);
    }
}
