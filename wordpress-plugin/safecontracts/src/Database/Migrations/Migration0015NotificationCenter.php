<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0015NotificationCenter implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $rules = $wpdb->prefix . 'safecontracts_notification_rules';
        $templates = $wpdb->prefix . 'safecontracts_notification_templates';
        $deliveries = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $suppressions = $wpdb->prefix . 'safecontracts_notification_suppressions';

        dbDelta("CREATE TABLE {$rules} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            trigger_type varchar(32) NOT NULL DEFAULT 'before_due',
            days_before int(11) unsigned NOT NULL DEFAULT 0,
            days_after int(11) unsigned NOT NULL DEFAULT 0,
            repeat_interval_days int(11) unsigned NOT NULL DEFAULT 0,
            max_repeats int(11) unsigned NOT NULL DEFAULT 0,
            recipient_roles_json longtext NOT NULL,
            recipient_user_ids_json longtext NOT NULL,
            escalation_roles_json longtext NOT NULL,
            target_assigned_accountant tinyint(1) NOT NULL DEFAULT 0,
            push_enabled tinyint(1) NOT NULL DEFAULT 1,
            email_enabled tinyint(1) NOT NULL DEFAULT 0,
            template_code varchar(100) NOT NULL DEFAULT 'payment_due_soon',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY active_trigger (is_active, trigger_type, days_before, days_after)
        ) {$charset};");

        dbDelta("CREATE TABLE {$templates} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(100) NOT NULL,
            title_template varchar(191) NOT NULL,
            body_template text NOT NULL,
            email_subject_template varchar(191) NOT NULL DEFAULT '',
            email_body_template text NOT NULL,
            icon_key varchar(64) NOT NULL DEFAULT 'contract_due',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY active_code (is_active, code)
        ) {$charset};");

        dbDelta("CREATE TABLE {$deliveries} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rule_id bigint(20) unsigned NULL,
            payment_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            device_token_id bigint(20) unsigned NULL,
            channel varchar(20) NOT NULL DEFAULT 'push',
            template_code varchar(100) NOT NULL,
            scheduled_for date NOT NULL,
            attempt_no int(11) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL,
            response_code int(11) NULL,
            error_code varchar(100) NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY payment_created (payment_id, created_at, id),
            KEY user_created (user_id, created_at, id),
            KEY channel_created (channel, created_at, id),
            KEY retry_lookup (status, scheduled_for, attempt_no)
        ) {$charset};");

        dbDelta("CREATE TABLE {$suppressions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scope_type varchar(20) NOT NULL,
            scope_id bigint(20) unsigned NOT NULL,
            reason varchar(191) NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY scope (scope_type, scope_id),
            KEY active_scope (is_active, scope_type, scope_id)
        ) {$charset};");

        $wpdb->query("UPDATE {$rules} SET recipient_user_ids_json = '[]' WHERE recipient_user_ids_json IS NULL OR recipient_user_ids_json = ''");
        $wpdb->query("UPDATE {$templates}
            SET email_subject_template = title_template
            WHERE email_subject_template IS NULL OR email_subject_template = ''");
        $wpdb->query("UPDATE {$templates}
            SET email_body_template = body_template
            WHERE email_body_template IS NULL OR email_body_template = ''");
    }
}
