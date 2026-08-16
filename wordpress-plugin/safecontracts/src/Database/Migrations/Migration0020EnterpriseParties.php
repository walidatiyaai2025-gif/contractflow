<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0020EnterpriseParties implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $parties = $wpdb->prefix . 'safecontracts_parties';

        dbDelta("CREATE TABLE {$parties} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            uuid char(36) NOT NULL,
            party_code varchar(100) NULL,
            display_name varchar(191) NOT NULL,
            legal_name varchar(191) NULL,
            party_kind varchar(32) NOT NULL,
            country_code char(2) NULL,
            registration_number varchar(100) NULL,
            tax_number varchar(100) NULL,
            email varchar(191) NULL,
            phone varchar(64) NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            metadata_json longtext NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            UNIQUE KEY tenant_code (tenant_id, party_code),
            KEY tenant_status_name (tenant_id, status, display_name, id),
            KEY tenant_kind_name (tenant_id, party_kind, display_name, id),
            KEY tenant_registration (tenant_id, country_code, registration_number)
        ) {$charset};");
    }
}
