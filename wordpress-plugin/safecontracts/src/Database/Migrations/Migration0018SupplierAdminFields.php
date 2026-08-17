<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0018SupplierAdminFields implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';

        dbDelta("CREATE TABLE {$suppliers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            internal_code varchar(100) NULL,
            name varchar(191) NOT NULL,
            legal_name varchar(191) NULL,
            trading_name varchar(191) NULL,
            contact_name varchar(191) NULL,
            email varchar(191) NULL,
            phone varchar(64) NULL,
            address text NULL,
            country_code char(2) NULL,
            registration_number varchar(100) NULL,
            tax_number varchar(100) NULL,
            default_currency char(3) NULL,
            payment_terms varchar(191) NULL,
            status varchar(16) NOT NULL DEFAULT 'active',
            notes text NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_archived tinyint(1) NOT NULL DEFAULT 0,
            archived_by bigint(20) unsigned NULL,
            archived_at datetime NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY internal_code (internal_code),
            KEY active_name (is_active, is_archived, name),
            KEY archived_name (is_archived, name),
            KEY status_legal_name (status, is_archived, legal_name),
            KEY registration_number (registration_number),
            KEY tax_number (tax_number)
        ) {$charset};");

        // Existing Supplier rows are proven records; preserve their display name
        // as legal_name and deterministically derive lifecycle status from the
        // pre-P11-003 active flag. Do not guess country, tax, payment terms, or currency.
        $wpdb->query("UPDATE {$suppliers}
            SET legal_name = name
            WHERE legal_name IS NULL OR legal_name = ''");
        $wpdb->query("UPDATE {$suppliers}
            SET status = CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END
            WHERE status IS NULL OR status = ''");
    }
}
