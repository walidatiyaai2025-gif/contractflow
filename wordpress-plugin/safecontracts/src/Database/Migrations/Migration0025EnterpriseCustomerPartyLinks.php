<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0025EnterpriseCustomerPartyLinks implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $table = $wpdb->prefix . 'safecontracts_customer_party_links';

        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            customer_id bigint(20) unsigned NOT NULL,
            party_id bigint(20) unsigned NOT NULL,
            provenance varchar(32) NOT NULL DEFAULT 'manual',
            linked_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_customer (tenant_id, customer_id),
            UNIQUE KEY tenant_party (tenant_id, party_id),
            KEY tenant_pair (tenant_id, customer_id, party_id)
        ) {$charset};");
    }
}
