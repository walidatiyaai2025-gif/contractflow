<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0014NotificationSchedule implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $schedule = $wpdb->prefix . 'safecontracts_notification_schedule';

        dbDelta("CREATE TABLE {$schedule} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rule_id bigint(20) unsigned NOT NULL,
            payment_id bigint(20) unsigned NOT NULL,
            attempt_no int(11) unsigned NOT NULL DEFAULT 0,
            recipient_ids_json longtext NOT NULL,
            template_code varchar(100) NOT NULL,
            channel varchar(32) NOT NULL DEFAULT 'push',
            scheduled_for datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            recipient_count int(11) unsigned NOT NULL DEFAULT 0,
            sent_count int(11) unsigned NOT NULL DEFAULT 0,
            failed_count int(11) unsigned NOT NULL DEFAULT 0,
            manual_attempts int(11) unsigned NOT NULL DEFAULT 0,
            last_attempt_at datetime NULL,
            sent_at datetime NULL,
            last_error_code varchar(100) NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY rule_payment_attempt (rule_id, payment_id, attempt_no),
            KEY due_status (status, scheduled_for, id),
            KEY payment_schedule (payment_id, scheduled_for, id)
        ) {$charset};");
    }
}
