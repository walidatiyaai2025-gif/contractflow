<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0022EnterprisePartyRelationships implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $table = $wpdb->prefix . 'safecontracts_party_relationships';

        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            source_party_id bigint(20) unsigned NOT NULL,
            target_party_id bigint(20) unsigned NOT NULL,
            relationship_code varchar(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            valid_from date NULL,
            valid_to date NULL,
            metadata_json longtext NULL,
            assigned_by bigint(20) unsigned NULL,
            revoked_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            revoked_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_relationship (tenant_id, source_party_id, target_party_id, relationship_code),
            KEY tenant_source_status (tenant_id, source_party_id, status, relationship_code, target_party_id),
            KEY tenant_target_status (tenant_id, target_party_id, status, relationship_code, source_party_id),
            KEY tenant_type_status (tenant_id, relationship_code, status, source_party_id, target_party_id)
        ) {$charset};");
    }
}
