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
        $deliveries = $wpdb->prefix . 'safecontracts_notification_deliveries';

        // Migration0010 created active_trigger with three columns. This
        // migration intentionally widens that same logical index to include
        // days_after. WordPress dbDelta can attempt ADD KEY using the existing
        // name instead of replacing it on MySQL 8, which emits a duplicate-key
        // database error during real plugin activation. Reconcile only the
        // legacy shape before dbDelta so fresh and upgraded installs converge
        // without changing notification semantics.
        if (method_exists($wpdb, 'get_results') && method_exists($wpdb, 'query')) {
            $indexRows = $wpdb->get_results("SHOW INDEX FROM {$rules} WHERE Key_name = 'active_trigger'", ARRAY_A);
            if (is_array($indexRows) && $indexRows !== []) {
                usort($indexRows, static fn (array $left, array $right): int => ((int) ($left['Seq_in_index'] ?? 0)) <=> ((int) ($right['Seq_in_index'] ?? 0)));
                $columns = array_values(array_filter(array_map(
                    static fn (array $row): string => (string) ($row['Column_name'] ?? ''),
                    $indexRows
                )));
                $required = ['is_active', 'trigger_type', 'days_before', 'days_after'];
                if ($columns !== $required) {
                    $wpdb->query("ALTER TABLE {$rules} DROP INDEX active_trigger");
                }
            }
        }

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
            escalation_roles_json longtext NOT NULL,
            target_assigned_accountant tinyint(1) NOT NULL DEFAULT 0,
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
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY active_code (is_active, code)
        ) {$charset};");

        dbDelta("CREATE TABLE {$tokens} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            token_hash char(64) NOT NULL,
            token longtext NOT NULL,
            platform varchar(20) NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            last_seen_at datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            KEY user_active (user_id, is_active)
        ) {$charset};");

        dbDelta("CREATE TABLE {$deliveries} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rule_id bigint(20) unsigned NULL,
            payment_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            device_token_id bigint(20) unsigned NOT NULL,
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
            KEY retry_lookup (status, scheduled_for, attempt_no)
        ) {$charset};");

        $now = gmdate('Y-m-d H:i:s');
        foreach ([
            ['payment_due_soon', 'Payment due {{due_date}}', '{{contract_number}} payment {{payment_reference}} has {{remaining_amount}} remaining and is due {{due_date}}.'],
            ['payment_due_today', 'Payment due today', '{{contract_number}} payment {{payment_reference}} is due today with {{remaining_amount}} remaining.'],
            ['payment_overdue', 'Payment overdue', '{{contract_number}} payment {{payment_reference}} is {{days_overdue}} day(s) overdue with {{remaining_amount}} remaining.'],
        ] as [$code, $title, $body]) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$templates} (code, title_template, body_template, is_active, created_by, updated_by, created_at, updated_at)
                 VALUES (%s, %s, %s, 1, NULL, NULL, %s, %s)
                 ON DUPLICATE KEY UPDATE code = VALUES(code)",
                $code,
                $title,
                $body,
                $now,
                $now
            ));
        }
    }
}
