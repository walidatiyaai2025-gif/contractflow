<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0007PaymentSchedule implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';

        dbDelta("CREATE TABLE {$payments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) unsigned NOT NULL,
            sequence_no int(11) unsigned NOT NULL,
            reference varchar(100) NULL,
            original_amount decimal(20,4) NOT NULL,
            due_date date NOT NULL,
            expected_payment_date date NULL,
            is_cancelled tinyint(1) NOT NULL DEFAULT 0,
            cancelled_at datetime NULL,
            cancelled_by bigint(20) unsigned NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_sequence (contract_id, sequence_no),
            KEY contract_due (contract_id, is_cancelled, due_date, id),
            KEY due_state (is_cancelled, due_date, id),
            KEY expected_state (is_cancelled, expected_payment_date, id)
        ) {$charset};");
    }
}
