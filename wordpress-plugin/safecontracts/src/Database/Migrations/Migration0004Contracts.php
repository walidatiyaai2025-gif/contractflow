<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0004Contracts implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';

        dbDelta("CREATE TABLE {$contracts} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contract_number varchar(100) NOT NULL,
            customer_id bigint(20) unsigned NOT NULL,
            accountant_user_id bigint(20) unsigned NULL,
            status varchar(32) NOT NULL DEFAULT 'draft',
            start_date date NULL,
            end_date date NULL,
            base_value decimal(20,4) NOT NULL DEFAULT 0.0000,
            notes longtext NULL,
            is_archived tinyint(1) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_number (contract_number),
            KEY customer_status (customer_id, status, is_archived),
            KEY accountant_status (accountant_user_id, status, is_archived),
            KEY contract_dates (start_date, end_date)
        ) {$charset};");
    }
}
