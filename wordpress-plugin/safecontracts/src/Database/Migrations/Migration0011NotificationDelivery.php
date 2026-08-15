<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0011NotificationDelivery implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $rules = $wpdb->prefix . 'safecontracts_notification_rules';
        $templates = $wpdb->prefix . 'safecontracts_notification_templates';
        $tokens = $wpdb->prefix . 'safecontracts_device_tokens';
        $notifications = $wpdb->prefix . 'safecontracts_notifications';
        $deliveryLog = $wpdb->prefix . 'safecontracts_notification_delivery_log';

        dbDelta("CREATE TABLE {$rules} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            trigger_type varchar(32) NOT NULL DEFAULT 'before_due',
            days_before int(11) unsigned NOT NULL DEFAULT 0,
            recipient_roles_json longtext NOT NULL,
            target_assigned_accountant tinyint(1) NOT NULL DEFAULT 0,
            repeat_interval_days int(11) unsigned NOT NULL DEFAULT 0,
            max_repeats int(11) unsigned NOT NULL DEFAULT 0,
            escalation_after_repeat int(11) unsigned NOT NULL DEFAULT 0,
            escalation_roles_json longtext NOT NULL,
            template_code varchar(100) NOT NULL DEFAULT 'payment_due',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY active_trigger (is_active, trigger_type, days_before),
            KEY template_code (template_code)
        ) {$charset};");

        dbDelta("CREATE TABLE {$templates} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            title_template varchar(255) NOT NULL,
            body_template text NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY active_name (is_active, name)
        ) {$charset};");

        dbDelta("CREATE TABLE {$tokens} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            device_id varchar(191) NOT NULL,
            platform varchar(16) NOT NULL,
            token text NOT NULL,
            token_hash char(64) NOT NULL,
            app_version varchar(64) NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            last_seen_at datetime NOT NULL,
            last_error_at datetime NULL,
            last_error_code varchar(100) NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            UNIQUE KEY user_device (user_id, device_id, platform),
            KEY user_active (user_id, is_active),
            KEY active_seen (is_active, last_seen_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$notifications} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rule_id bigint(20) unsigned NOT NULL,
            payment_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            occurrence_date date NOT NULL,
            occurrence_index int(11) unsigned NOT NULL DEFAULT 0,
            dedupe_key char(64) NOT NULL,
            template_code varchar(100) NOT NULL,
            title varchar(255) NOT NULL,
            body text NOT NULL,
            data_json longtext NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'queued',
            attempt_count int(11) unsigned NOT NULL DEFAULT 0,
            next_attempt_at datetime NULL,
            last_error_code varchar(100) NULL,
            last_error_message varchar(1000) NULL,
            sent_at datetime NULL,
            read_at datetime NULL,
            suppressed_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY dedupe_key (dedupe_key),
            KEY status_next (status, next_attempt_at),
            KEY payment_status (payment_id, status),
            KEY user_status (user_id, status, created_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$deliveryLog} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            notification_id bigint(20) unsigned NOT NULL,
            device_token_id bigint(20) unsigned NULL,
            attempt_no int(11) unsigned NOT NULL,
            status varchar(32) NOT NULL,
            http_status int(11) unsigned NULL,
            error_code varchar(100) NULL,
            error_message varchar(1000) NULL,
            provider_message_id varchar(255) NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY notification_attempt (notification_id, attempt_no),
            KEY status_created (status, created_at)
        ) {$charset};");

        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$templates}
                (code, name, title_template, body_template, is_active, created_by, updated_by, created_at, updated_at)
             VALUES (%s, %s, %s, %s, 1, NULL, NULL, %s, %s)
             ON DUPLICATE KEY UPDATE code = VALUES(code)",
            'payment_due',
            'Payment due reminder',
            'Payment reminder — {payment_reference}',
            '{client_name} — contract {contract_number}: {remaining_amount} is due on {due_date}.',
            $now,
            $now
        ));
    }
}
